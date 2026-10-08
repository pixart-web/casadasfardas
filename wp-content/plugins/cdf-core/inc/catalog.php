<?php
/**
 * Catálogo: URLs, taxonomias, avaliações e informação de origem no backoffice.
 */

defined( 'ABSPATH' ) || exit;

/**
 * URLs de categoria planas e estáveis: /categoria/{slug}/ (os slugs são únicos).
 */
add_filter(
	'woocommerce_taxonomy_args_product_cat',
	static function ( array $args ) {
		$args['rewrite'] = array(
			'slug'         => 'categoria',
			'with_front'   => false,
			'hierarchical' => false,
		);
		return $args;
	}
);

add_filter(
	'register_taxonomy_args',
	static function ( array $args, string $taxonomy ) {
		if ( 'product_brand' === $taxonomy ) {
			$args['rewrite'] = array(
				'slug'         => 'marca',
				'with_front'   => false,
				'hierarchical' => false,
			);
		}
		return $args;
	},
	10,
	2
);

/**
 * Sem avaliações: a loja antiga não tinha avaliações verificáveis para migrar.
 */
add_filter( 'woocommerce_product_tabs', static function ( array $tabs ) {
	unset( $tabs['reviews'] );
	return $tabs;
}, 98 );
add_filter( 'woocommerce_product_get_reviews_allowed', '__return_false' );
add_filter( 'pre_option_woocommerce_enable_reviews', static fn() => 'no' );

/**
 * Produtos relacionados: mesma categoria final (sem «recomendações técnicas» simuladas).
 */
add_filter( 'woocommerce_product_related_posts_relate_by_tag', '__return_false' );
add_filter( 'woocommerce_output_related_products_args', static function ( array $args ) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
} );

/**
 * Imagem de categoria: se não tiver imagem própria, usa a do primeiro produto com imagem.
 */
function cdf_term_image_id( WP_Term $term ): int {
	$id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $id ) {
		return $id;
	}
	$cached = get_transient( 'cdf_term_img_' . $term->term_id );
	if ( false !== $cached ) {
		return (int) $cached;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
			'tax_query'      => array(
				array(
					'taxonomy' => $term->taxonomy,
					'terms'    => $term->term_id,
				),
			),
		)
	);
	$img = $ids ? (int) get_post_thumbnail_id( $ids[0] ) : 0;
	set_transient( 'cdf_term_img_' . $term->term_id, $img, DAY_IN_SECONDS );
	return $img;
}

/**
 * Introdução curta da categoria (meta editável) separada da descrição longa.
 */
add_action( 'product_cat_edit_form_fields', static function ( WP_Term $term ) {
	$intro = get_term_meta( $term->term_id, 'cdf_intro', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="cdf_intro"><?php esc_html_e( 'Introdução curta', 'cdf-core' ); ?></label></th>
		<td><textarea name="cdf_intro" id="cdf_intro" rows="3"><?php echo esc_textarea( $intro ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Uma a duas frases mostradas por baixo do título da categoria.', 'cdf-core' ); ?></p></td>
	</tr>
	<?php
	wp_nonce_field( 'cdf_term_intro', 'cdf_term_intro_nonce' );
} );

add_action( 'edited_product_cat', static function ( int $term_id ) {
	if ( ! isset( $_POST['cdf_term_intro_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cdf_term_intro_nonce'] ), 'cdf_term_intro' ) ) {
		return;
	}
	if ( current_user_can( 'manage_product_terms' ) && isset( $_POST['cdf_intro'] ) ) {
		update_term_meta( $term_id, 'cdf_intro', sanitize_textarea_field( wp_unslash( $_POST['cdf_intro'] ) ) );
	}
} );

/**
 * Caixa «Origem da migração» na edição de produto.
 */
add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'cdf-source', __( 'Origem (loja antiga)', 'cdf-core' ), 'cdf_render_source_box', 'product', 'side', 'low' );
} );

function cdf_render_source_box( WP_Post $post ) {
	$url    = get_post_meta( $post->ID, '_cdf_source_url', true );
	$price  = get_post_meta( $post->ID, '_cdf_source_price', true );
	$status = get_post_meta( $post->ID, '_cdf_price_status', true );
	$date   = get_post_meta( $post->ID, '_cdf_imported_at', true );
	if ( ! $url ) {
		echo '<p>' . esc_html__( 'Produto criado manualmente.', 'cdf-core' ) . '</p>';
		return;
	}
	$labels = array(
		'source-unvalidated' => __( 'Importado — por validar', 'cdf-core' ),
		'validated'          => __( 'Validado', 'cdf-core' ),
		'none'               => __( 'Sem preço na origem (orçamento)', 'cdf-core' ),
	);
	echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver página original', 'cdf-core' ) . '</a></p>';
	echo '<p>' . esc_html__( 'Preço na origem (s/ IVA):', 'cdf-core' ) . ' <strong>' . ( '' !== $price ? esc_html( wc_format_localized_price( $price ) ) . ' €' : '—' ) . '</strong></p>';
	echo '<p>' . esc_html__( 'Estado do preço:', 'cdf-core' ) . ' <strong>' . esc_html( $labels[ $status ] ?? '—' ) . '</strong></p>';
	if ( $date ) {
		echo '<p>' . esc_html__( 'Importado em:', 'cdf-core' ) . ' ' . esc_html( $date ) . '</p>';
	}
	if ( 'source-unvalidated' === $status ) {
		wp_nonce_field( 'cdf_validate_price', 'cdf_validate_price_nonce' );
		echo '<label><input type="checkbox" name="cdf_price_validated" value="1"> ' . esc_html__( 'Confirmo que o preço e o IVA estão corretos', 'cdf-core' ) . '</label>';
	}
}

add_action( 'save_post_product', static function ( int $post_id ) {
	if ( ! isset( $_POST['cdf_validate_price_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cdf_validate_price_nonce'] ), 'cdf_validate_price' ) ) {
		return;
	}
	if ( ! empty( $_POST['cdf_price_validated'] ) && current_user_can( 'edit_post', $post_id ) ) {
		update_post_meta( $post_id, '_cdf_price_status', 'validated' );
	}
} );

/**
 * Coluna «Preço» com o estado de validação na lista de produtos.
 */
add_filter( 'manage_edit-product_columns', static function ( array $cols ) {
	$cols['cdf_price_status'] = __( 'Preço', 'cdf-core' );
	return $cols;
}, 20 );

add_action( 'manage_product_posts_custom_column', static function ( string $col, int $post_id ) {
	if ( 'cdf_price_status' !== $col ) {
		return;
	}
	$status = get_post_meta( $post_id, '_cdf_price_status', true );
	$map    = array(
		'source-unvalidated' => array( '#b26a00', __( 'por validar', 'cdf-core' ) ),
		'validated'          => array( '#1a7f37', __( 'validado', 'cdf-core' ) ),
		'none'               => array( '#555', __( 'orçamento', 'cdf-core' ) ),
	);
	if ( isset( $map[ $status ] ) ) {
		printf( '<span style="color:%s">%s</span>', esc_attr( $map[ $status ][0] ), esc_html( $map[ $status ][1] ) );
	}
}, 10, 2 );

/**
 * Ação em massa: marcar preços como validados.
 */
add_filter( 'bulk_actions-edit-product', static function ( array $actions ) {
	$actions['cdf_validate_prices'] = __( 'Marcar preço como validado', 'cdf-core' );
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-product', static function ( string $redirect, string $action, array $ids ) {
	if ( 'cdf_validate_prices' !== $action || ! current_user_can( 'edit_products' ) ) {
		return $redirect;
	}
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_cdf_source_price', true ) !== '' ) {
			update_post_meta( $id, '_cdf_price_status', 'validated' );
		}
	}
	return add_query_arg( 'cdf_validated', count( $ids ), $redirect );
}, 10, 3 );
