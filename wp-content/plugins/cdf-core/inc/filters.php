<?php
/**
 * Filtros de catálogo (lógica). O tema trata da apresentação.
 *
 * Usa os parâmetros nativos do WooCommerce para atributos (filter_tom, filter_tamanho) e
 * acrescenta «marca» (taxonomia product_brand). As contagens respeitam a categoria/pesquisa atual
 * e os restantes filtros ativos. Só são mostradas facetas com dados reais.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Facetas disponíveis: chave do pedido => taxonomia.
 */
function cdf_filter_facets(): array {
	return array(
		'marca'          => array( 'taxonomy' => 'product_brand', 'label' => __( 'Marca', 'cdf-core' ) ),
		// Famílias de cor (Azul, Verde…): os 490+ nomes exatos dos fabricantes ficam nas variações.
		'filter_tom'     => array( 'taxonomy' => 'pa_tom', 'label' => __( 'Cor', 'cdf-core' ) ),
		'filter_tamanho' => array( 'taxonomy' => 'pa_tamanho', 'label' => __( 'Tamanho', 'cdf-core' ) ),
	);
}

function cdf_filter_selected( string $param ): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de leitura.
	if ( empty( $_GET[ $param ] ) ) {
		return array();
	}
	$raw = sanitize_text_field( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return array_values( array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) ) );
}

function cdf_filters_active(): bool {
	foreach ( array_keys( cdf_filter_facets() ) as $param ) {
		if ( cdf_filter_selected( $param ) ) {
			return true;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $_GET['min_price'] ) || isset( $_GET['max_price'] );
}

/**
 * «marca» aplicada à consulta principal da loja.
 */
add_action(
	'woocommerce_product_query',
	static function ( WP_Query $query ) {
		$brands = cdf_filter_selected( 'marca' );
		if ( ! $brands ) {
			return;
		}
		$tax   = (array) $query->get( 'tax_query' );
		$tax[] = array(
			'taxonomy' => 'product_brand',
			'field'    => 'slug',
			'terms'    => $brands,
			'operator' => 'IN',
		);
		$query->set( 'tax_query', $tax );
	}
);

// Seleção múltipla dentro da mesma faceta = «ou».
add_filter( 'woocommerce_layered_nav_default_query_type', static fn() => 'or' );

/**
 * IDs dos produtos no contexto atual (categoria, marca, pesquisa ou loja), sem outros filtros.
 */
function cdf_filter_context_ids(): array {
	$args = array(
		'post_type'        => 'product',
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'tax_query'        => array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		),
	);
	$key = 'shop';
	if ( is_product_category() || is_product_taxonomy() ) {
		$term                = get_queried_object();
		$args['tax_query'][] = array(
			'taxonomy'         => $term->taxonomy,
			'terms'            => $term->term_id,
			'include_children' => true,
		);
		$key                 = $term->taxonomy . ':' . $term->term_id;
	} elseif ( is_search() ) {
		$args['s'] = get_search_query( false );
		$key       = 's:' . md5( $args['s'] );
	}
	$cache_key = 'cdf_ctx_' . md5( $key . '|' . wp_cache_get_last_changed( 'posts' ) );
	$ids       = wp_cache_get( $cache_key, 'cdf' );
	if ( false === $ids ) {
		$ids = isset( $args['s'] ) ? cdf_search_ids( $args ) : get_posts( $args );
		wp_cache_set( $cache_key, $ids, 'cdf', HOUR_IN_SECONDS );
	}
	return array_map( 'intval', $ids );
}

/**
 * Pesquisa com a mesma lógica de referência da pesquisa principal.
 */
function cdf_search_ids( array $args ): array {
	$ids = get_posts( $args );
	return array_values( array_unique( array_merge( $ids, cdf_products_matching_sku( $args['s'], 500 ) ) ) );
}

/**
 * IDs que têm algum dos termos indicados.
 */
function cdf_ids_with_terms( string $taxonomy, array $slugs ): array {
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slugs,
				),
			),
		)
	);
	return array_map( 'intval', $ids );
}

/**
 * Facetas com opções e contagens para o contexto atual.
 *
 * @return array<string, array{label:string, taxonomy:string, options:array}>
 */
function cdf_get_facets(): array {
	$base = cdf_filter_context_ids();
	if ( ! $base ) {
		return array();
	}
	$facets   = cdf_filter_facets();
	$selected = array();
	$matching = array();
	foreach ( $facets as $param => $facet ) {
		$selected[ $param ] = cdf_filter_selected( $param );
		if ( $selected[ $param ] && taxonomy_exists( $facet['taxonomy'] ) ) {
			$matching[ $param ] = cdf_ids_with_terms( $facet['taxonomy'], $selected[ $param ] );
		}
	}
	global $wpdb;
	$out = array();
	foreach ( $facets as $param => $facet ) {
		if ( ! taxonomy_exists( $facet['taxonomy'] ) ) {
			continue;
		}
		$ids = $base;
		foreach ( $matching as $other => $other_ids ) {
			if ( $other !== $param ) {
				$ids = array_intersect( $ids, $other_ids );
			}
		}
		if ( ! $ids ) {
			$counts = array();
		} else {
			$in     = implode( ',', array_map( 'intval', $ids ) );
			$counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.slug, t.name, t.term_id, COUNT(DISTINCT tr.object_id) AS n
					   FROM {$wpdb->term_relationships} tr
					   JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					   JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					  WHERE tt.taxonomy = %s AND tr.object_id IN ($in)
					  GROUP BY t.term_id",
					$facet['taxonomy']
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in são inteiros.
		}
		$options = array();
		foreach ( $counts as $row ) {
			$options[] = array(
				'slug'     => $row->slug,
				'name'     => $row->name,
				'count'    => (int) $row->n,
				'order'    => (int) get_term_meta( (int) $row->term_id, 'order', true ),
				'selected' => in_array( $row->slug, $selected[ $param ], true ),
			);
		}
		// Mantém visíveis opções selecionadas mesmo sem resultados.
		foreach ( $selected[ $param ] as $slug ) {
			if ( ! wp_list_filter( $options, array( 'slug' => $slug ) ) ) {
				$term = get_term_by( 'slug', $slug, $facet['taxonomy'] );
				if ( $term ) {
					$options[] = array( 'slug' => $slug, 'name' => $term->name, 'count' => 0, 'order' => 0, 'selected' => true );
				}
			}
		}
		if ( 'filter_tamanho' === $param ) {
			usort( $options, static fn( $a, $b ) => cdf_size_rank( $a['name'] ) <=> cdf_size_rank( $b['name'] ) );
		} else {
			usort( $options, static fn( $a, $b ) => strcoll( $a['name'], $b['name'] ) );
		}
		// Uma faceta com uma única opção que abrange tudo não ajuda a filtrar.
		if ( count( $options ) < 2 && ! $selected[ $param ] ) {
			continue;
		}
		$out[ $param ] = array(
			'label'    => $facet['label'],
			'taxonomy' => $facet['taxonomy'],
			'options'  => $options,
		);
	}
	return $out;
}

/**
 * Ordem natural de tamanhos (letras e números).
 */
function cdf_size_rank( string $size ): float {
	$order = array( '3XS' => 1, 'XXS' => 2, 'XS' => 3, 'S' => 4, 'M' => 5, 'L' => 6, 'XL' => 7, 'XXL' => 8, '2XL' => 8, '3XL' => 9, 'XXXL' => 9, '4XL' => 10, '5XL' => 11, '6XL' => 12, '7XL' => 13 );
	$key   = strtoupper( trim( $size ) );
	if ( isset( $order[ $key ] ) ) {
		return $order[ $key ];
	}
	if ( is_numeric( str_replace( ',', '.', $key ) ) ) {
		return 100 + (float) str_replace( ',', '.', $key );
	}
	return 1000;
}

/**
 * URL com um valor de faceta alternado (mantém os restantes parâmetros e volta à página 1).
 */
function cdf_filter_toggle_url( string $param, string $slug ): string {
	$current = cdf_filter_selected( $param );
	$next    = in_array( $slug, $current, true ) ? array_diff( $current, array( $slug ) ) : array_merge( $current, array( $slug ) );
	$url     = cdf_filter_base_url();
	$args    = array();
	foreach ( array_keys( cdf_filter_facets() ) as $p ) {
		$values = $p === $param ? $next : cdf_filter_selected( $p );
		if ( $values ) {
			$args[ $p ] = implode( ',', $values );
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	foreach ( array( 'orderby', 's', 'post_type', 'min_price', 'max_price' ) as $keep ) {
		if ( isset( $_GET[ $keep ] ) && '' !== $_GET[ $keep ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args[ $keep ] = sanitize_text_field( wp_unslash( $_GET[ $keep ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), $url );
}

/**
 * URL base do contexto atual, sem paginação nem filtros.
 */
function cdf_filter_base_url(): string {
	if ( is_product_category() || is_product_taxonomy() ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? home_url( '/' ) : $link;
	}
	if ( is_search() ) {
		return home_url( '/' );
	}
	return wc_get_page_permalink( 'shop' );
}

function cdf_filter_clear_url(): string {
	$url = cdf_filter_base_url();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_search() ) {
		$url = add_query_arg( array( 's' => rawurlencode( get_search_query( false ) ), 'post_type' => 'product' ), $url );
	}
	return $url;
}
