<?php
/**
 * Blocos dinâmicos (renderizados no servidor), editáveis no editor de blocos.
 *
 * - cdf/products       seleção de produtos reais (por categoria ou por referências)
 * - cdf/categories     grelha de categorias com imagem real e contagem
 * - cdf/form           formulário de orçamento ou de contacto
 * - cdf/business-info  morada, contactos e horário (dados de WooCommerce → Casa das Fardas)
 * - cdf/quote-count    ligação para a lista de orçamento com contador
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		wp_register_script(
			'cdf-blocks-editor',
			CDF_CORE_URL . 'assets/blocks-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			CDF_CORE_VERSION,
			true
		);

		register_block_type(
			'cdf/products',
			array(
				'api_version'     => 3,
				'title'           => __( 'Produtos da loja', 'cdf-core' ),
				'category'        => 'widgets',
				'editor_script'   => 'cdf-blocks-editor',
				'attributes'      => array(
					'category' => array( 'type' => 'string', 'default' => '' ),
					'skus'     => array( 'type' => 'string', 'default' => '' ),
					'limit'    => array( 'type' => 'number', 'default' => 8 ),
					'columns'  => array( 'type' => 'number', 'default' => 4 ),
					'orderby'  => array( 'type' => 'string', 'default' => 'menu_order' ),
				),
				'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
				'render_callback' => 'cdf_block_products',
			)
		);

		register_block_type(
			'cdf/categories',
			array(
				'api_version'     => 3,
				'title'           => __( 'Categorias da loja', 'cdf-core' ),
				'category'        => 'widgets',
				'editor_script'   => 'cdf-blocks-editor',
				'attributes'      => array(
					'parent'  => array( 'type' => 'string', 'default' => '' ),
					'slugs'   => array( 'type' => 'string', 'default' => '' ),
					'limit'   => array( 'type' => 'number', 'default' => 12 ),
					'columns' => array( 'type' => 'number', 'default' => 4 ),
					'style'   => array( 'type' => 'string', 'default' => 'tiles' ),
				),
				'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
				'render_callback' => 'cdf_block_categories',
			)
		);

		register_block_type(
			'cdf/form',
			array(
				'api_version'     => 3,
				'title'           => __( 'Formulário de orçamento / contacto', 'cdf-core' ),
				'category'        => 'widgets',
				'editor_script'   => 'cdf-blocks-editor',
				'attributes'      => array(
					'mode' => array( 'type' => 'string', 'default' => 'quote' ),
				),
				'supports'        => array( 'html' => false, 'multiple' => false ),
				'render_callback' => static function ( array $atts ) {
					wp_enqueue_script( 'cdf-forms' );
					return cdf_render_form( $atts['mode'] ?? 'quote' );
				},
			)
		);

		register_block_type(
			'cdf/business-info',
			array(
				'api_version'     => 3,
				'title'           => __( 'Dados de contacto da loja', 'cdf-core' ),
				'category'        => 'widgets',
				'editor_script'   => 'cdf-blocks-editor',
				'attributes'      => array(
					'show' => array( 'type' => 'string', 'default' => 'all' ),
				),
				'supports'        => array( 'html' => false ),
				'render_callback' => 'cdf_block_business_info',
			)
		);
	}
);

function cdf_block_wrapper( string $class, string $inner, array $atts = array() ): string {
	$align = ! empty( $atts['align'] ) ? ' align' . sanitize_html_class( $atts['align'] ) : '';
	return '<div class="' . esc_attr( $class . $align ) . '">' . $inner . '</div>';
}

/**
 * Produtos reais por categoria ou referências. Sem «mais vendidos» nem promoções inventadas.
 */
function cdf_block_products( array $atts ): string {
	$limit   = max( 1, min( 24, (int) ( $atts['limit'] ?? 8 ) ) );
	$columns = max( 2, min( 6, (int) ( $atts['columns'] ?? 4 ) ) );
	$args    = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		),
	);
	$skus = array_filter( array_map( 'trim', explode( ',', (string) ( $atts['skus'] ?? '' ) ) ) );
	if ( $skus ) {
		$ids = array();
		foreach ( $skus as $sku ) {
			$id = wc_get_product_id_by_sku( $sku );
			if ( $id ) {
				$p     = wc_get_product( $id );
				$ids[] = $p && $p->is_type( 'variation' ) ? $p->get_parent_id() : $id;
			}
		}
		if ( ! $ids ) {
			return '';
		}
		$args['post__in'] = array_values( array_unique( $ids ) );
		$args['orderby']  = 'post__in';
	} else {
		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $atts['category'] ) ),
			);
		}
		$orderby = $atts['orderby'] ?? 'menu_order';
		if ( 'date' === $orderby ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} elseif ( 'rand' === $orderby ) {
			$args['orderby'] = 'rand';
		} else {
			$args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
		}
		$args['meta_query'] = array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) );
	}
	$query = new WP_Query( $args );
	if ( ! $query->have_posts() ) {
		return '';
	}
	ob_start();
	wc_set_loop_prop( 'columns', $columns );
	wc_set_loop_prop( 'name', 'cdf-products' );
	woocommerce_product_loop_start();
	while ( $query->have_posts() ) {
		$query->the_post();
		wc_get_template_part( 'content', 'product' );
	}
	woocommerce_product_loop_end();
	wp_reset_postdata();
	wc_reset_loop();
	return cdf_block_wrapper( 'cdf-products-block woocommerce columns-' . $columns, (string) ob_get_clean(), $atts );
}

/**
 * Grelha de categorias com imagem (própria ou do primeiro produto) e número de artigos.
 */
function cdf_block_categories( array $atts ): string {
	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'number'     => max( 1, min( 40, (int) ( $atts['limit'] ?? 12 ) ) ),
		'menu_order' => 'ASC',
	);
	$slugs = array_filter( array_map( 'trim', explode( ',', (string) ( $atts['slugs'] ?? '' ) ) ) );
	if ( $slugs ) {
		$args['slug']       = $slugs;
		$args['orderby']    = 'slug__in';
		$args['menu_order'] = false;
	} elseif ( ! empty( $atts['parent'] ) ) {
		$parent = get_term_by( 'slug', $atts['parent'], 'product_cat' );
		if ( ! $parent ) {
			return '';
		}
		$args['parent'] = $parent->term_id;
	} else {
		$args['parent'] = 0;
	}
	$terms = get_terms( $args );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$columns = max( 2, min( 6, (int) ( $atts['columns'] ?? 4 ) ) );
	$style   = 'list' === ( $atts['style'] ?? '' ) ? 'list' : 'tiles';
	$html    = '<ul class="cdf-cat-grid cdf-cat-grid--' . esc_attr( $style ) . '" style="--cols:' . (int) $columns . '">';
	foreach ( $terms as $term ) {
		$count = cdf_term_product_count( $term );
		if ( ! $count ) {
			continue;
		}
		$img   = cdf_term_image_id( $term );
		$html .= '<li class="cdf-cat-card"><a href="' . esc_url( get_term_link( $term ) ) . '">';
		if ( 'tiles' === $style ) {
			$html .= '<span class="cdf-cat-card__media">' . ( $img ? wp_get_attachment_image( $img, 'woocommerce_thumbnail', false, array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 1200px) 300px, (min-width: 700px) 30vw, 45vw' ) ) : '' ) . '</span>';
		}
		$html .= '<span class="cdf-cat-card__body"><span class="cdf-cat-card__name">' . esc_html( $term->name ) . '</span>';
		/* translators: %d número de artigos */
		$html .= '<span class="cdf-cat-card__count">' . esc_html( sprintf( _n( '%d artigo', '%d artigos', $count, 'cdf-core' ), $count ) ) . '</span></span>';
		$html .= '</a></li>';
	}
	$html .= '</ul>';
	return cdf_block_wrapper( 'cdf-categories-block', $html, $atts );
}

/**
 * Número de produtos publicados num termo, incluindo subcategorias.
 */
function cdf_term_product_count( WP_Term $term ): int {
	$count = (int) get_term_meta( $term->term_id, 'product_count_product_cat', true );
	if ( $count ) {
		return $count;
	}
	$children = get_term_children( $term->term_id, $term->taxonomy );
	$count    = (int) $term->count;
	if ( $children && ! is_wp_error( $children ) ) {
		$ids   = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id, 'include_children' => true ) ),
			)
		);
		$count = count( $ids );
	}
	return $count;
}

function cdf_block_business_info( array $atts ): string {
	$show  = $atts['show'] ?? 'all';
	$b     = cdf_business();
	$parts = array();
	if ( in_array( $show, array( 'all', 'address' ), true ) ) {
		$parts[] = '<div class="cdf-info__item"><h3>' . esc_html__( 'Loja', 'cdf-core' ) . '</h3><address>' .
			esc_html( $b['legal_name'] ) . '<br>' . esc_html( $b['street'] ) . '<br>' . esc_html( $b['postcode'] . ' ' . $b['locality'] ) . '</address>' .
			( $b['maps_url'] ? '<p><a class="cdf-link-arrow" href="' . esc_url( $b['maps_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver no mapa', 'cdf-core' ) . '<span class="screen-reader-text"> ' . esc_html__( '(abre numa nova janela)', 'cdf-core' ) . '</span></a></p>' : '' ) . '</div>';
	}
	if ( in_array( $show, array( 'all', 'contacts' ), true ) ) {
		$emails = '';
		foreach ( cdf_business_lines( 'emails' ) as $email ) {
			$emails .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a><br>';
		}
		$parts[] = '<div class="cdf-info__item"><h3>' . esc_html__( 'Contactos', 'cdf-core' ) . '</h3><p><a class="cdf-info__phone" href="' . esc_attr( cdf_phone_href( $b['phone'] ) ) . '">' . esc_html( cdf_phone_display( $b['phone'] ) ) . '</a><br><small>' . esc_html( $b['phone_note'] ) . '</small></p><p>' . $emails . '</p></div>';
	}
	if ( in_array( $show, array( 'all', 'hours' ), true ) ) {
		$parts[] = '<div class="cdf-info__item"><h3>' . esc_html__( 'Horário', 'cdf-core' ) . '</h3><p>' . nl2br( esc_html( $b['hours'] ) ) . '</p></div>';
	}
	return cdf_block_wrapper( 'cdf-info cdf-info--' . sanitize_html_class( $show ), implode( '', $parts ), $atts );
}
