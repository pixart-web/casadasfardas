<?php
/**
 * Pesquisa: por nome e por referência (SKU de produtos e de variações/cores) e sugestões em tempo real.
 */

defined( 'ABSPATH' ) || exit;

/**
 * IDs de produtos (pais) cujo SKU, ou o SKU de uma variação, contém o termo.
 */
function cdf_products_matching_sku( string $term, int $limit = 50 ): array {
	global $wpdb;
	$term = trim( $term );
	if ( mb_strlen( $term ) < 2 ) {
		return array();
	}
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT l.product_id, p.post_parent, p.post_type
			   FROM {$wpdb->wc_product_meta_lookup} l
			   JOIN {$wpdb->posts} p ON p.ID = l.product_id
			  WHERE l.sku LIKE %s AND p.post_status = 'publish'
			  ORDER BY (l.sku = %s) DESC, l.sku ASC
			  LIMIT %d",
			$like,
			$term,
			$limit
		)
	);
	$ids = array();
	foreach ( $rows as $row ) {
		$ids[] = 'product_variation' === $row->post_type ? (int) $row->post_parent : (int) $row->product_id;
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Inclui correspondências por referência na pesquisa principal da loja.
 */
add_filter(
	'posts_search',
	static function ( string $search, WP_Query $query ) {
		global $wpdb;
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() || '' === $search ) {
			return $search;
		}
		$types = (array) $query->get( 'post_type' );
		if ( ! in_array( 'product', $types, true ) ) {
			return $search;
		}
		$ids = cdf_products_matching_sku( (string) $query->get( 's' ) );
		if ( ! $ids ) {
			return $search;
		}
		$inner = preg_replace( '/^\s*AND\s*/', '', $search );
		return ' AND ( ' . $inner . ' OR ' . $wpdb->posts . '.ID IN (' . implode( ',', array_map( 'intval', $ids ) ) . ') ) ';
	},
	10,
	2
);

/**
 * A pesquisa do site é sempre uma pesquisa de produtos.
 */
add_action(
	'pre_get_posts',
	static function ( WP_Query $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() && ! $query->get( 'post_type' ) ) {
			$query->set( 'post_type', 'product' );
		}
	}
);

// ---------------------------------------------------------------- sugestões

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'cdf/v1',
			'/suggest',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'callback'            => 'cdf_rest_suggest',
			)
		);
	}
);

function cdf_rest_suggest( WP_REST_Request $request ) {
	$q = trim( (string) $request->get_param( 'q' ) );
	if ( mb_strlen( $q ) < 2 || mb_strlen( $q ) > 80 ) {
		return new WP_REST_Response( array( 'products' => array(), 'categories' => array() ), 200 );
	}
	$cache_key = 'cdf_sug_' . md5( mb_strtolower( $q ) );
	$cached    = get_transient( $cache_key );
	if ( false === $cached ) {
		$sku_ids  = cdf_products_matching_sku( $q, 6 );
		$text_ids = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				's'                => $q,
				'posts_per_page'   => 6,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'orderby'          => 'relevance',
			)
		);
		$ids      = array_slice( array_values( array_unique( array_merge( $sku_ids, $text_ids ) ) ), 0, 6 );
		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			$img        = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
			$terms      = get_the_terms( $id, 'product_cat' );
			$products[] = array(
				'name'     => wp_strip_all_tags( $product->get_name() ),
				'sku'      => $product->get_sku(),
				'url'      => $product->get_permalink(),
				'image'    => $img ?: '',
				'category' => ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '',
			);
		}
		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name__like' => $q,
				'hide_empty' => true,
				'number'     => 4,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$parent       = $term->parent ? get_term( $term->parent, 'product_cat' ) : null;
				$categories[] = array(
					'name'   => $term->name,
					'parent' => ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : '',
					'url'    => get_term_link( $term ),
					'count'  => (int) $term->count,
				);
			}
		}
		$cached = array(
			'products'   => $products,
			'categories' => $categories,
			'all'        => add_query_arg(
				array(
					's'         => rawurlencode( $q ),
					'post_type' => 'product',
				),
				home_url( '/' )
			),
		);
		set_transient( $cache_key, $cached, 10 * MINUTE_IN_SECONDS );
	}
	$response = new WP_REST_Response( $cached, 200 );
	$response->header( 'Cache-Control', 'public, max-age=300' );
	return $response;
}

// Invalida sugestões quando um produto muda.
add_action( 'save_post_product', static function () {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_cdf\_sug\_%' OR option_name LIKE '\_transient\_timeout\_cdf\_sug\_%'" );
} );
