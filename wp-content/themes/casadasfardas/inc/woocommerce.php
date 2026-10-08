<?php
/**
 * Integração de apresentação com o WooCommerce (overrides mínimos; ver woocommerce/).
 */

defined( 'ABSPATH' ) || exit;

// Wrappers e barra lateral próprios.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

// Sem etiquetas de promoção, avaliações nem cross-sells não validados.
add_filter( 'woocommerce_sale_flash', '__return_empty_string' );
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

// Resumo do produto: título, preço, excerto e meta são renderizados pelo tema.
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

// Listagens: contagem e ordenação ficam na barra de ferramentas do tema.
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10 );

add_filter( 'loop_shop_per_page', static fn() => 24, 20 );
add_filter( 'loop_shop_columns', static fn() => 4 );

/**
 * Ordenações com dados reais (sem «popularidade» nem «avaliação»; preço só com preços visíveis).
 */
add_filter(
	'woocommerce_catalog_orderby',
	static function ( array $options ) {
		$out = array(
			'menu_order' => __( 'Relevância', 'casadasfardas' ),
			'date'       => __( 'Mais recentes', 'casadasfardas' ),
		);
		if ( cdf_theme_shop_mode() ) {
			$out['price']      = __( 'Preço: mais baixo primeiro', 'casadasfardas' );
			$out['price-desc'] = __( 'Preço: mais alto primeiro', 'casadasfardas' );
		}
		$out['title'] = __( 'Nome (A–Z)', 'casadasfardas' );
		return $out;
	}
);

add_filter(
	'woocommerce_get_catalog_ordering_args',
	static function ( array $args, string $orderby ) {
		if ( 'title' === $orderby ) {
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
		}
		return $args;
	},
	10,
	2
);

/**
 * Migalhas acessíveis.
 */
add_filter(
	'woocommerce_breadcrumb_defaults',
	static function () {
		return array(
			'delimiter'   => '',
			'wrap_before' => '<nav class="cdf-breadcrumbs" aria-label="' . esc_attr__( 'Localização', 'casadasfardas' ) . '"><ol>',
			'wrap_after'  => '</ol></nav>',
			'before'      => '<li>',
			'after'       => '</li>',
			'home'        => __( 'Início', 'casadasfardas' ),
		);
	}
);

add_filter(
	'woocommerce_get_breadcrumb',
	static function ( array $crumbs ) {
		// Na pesquisa e na loja, a migalha final não é uma ligação.
		return $crumbs;
	}
);

/**
 * Paginação com ligações rastreáveis.
 */
add_filter(
	'woocommerce_pagination_args',
	static function ( array $args ) {
		$args['prev_text'] = cdf_svg( 'back', 18 ) . '<span class="screen-reader-text">' . __( 'Página anterior', 'casadasfardas' ) . '</span>';
		$args['next_text'] = cdf_svg( 'chevron', 18 ) . '<span class="screen-reader-text">' . __( 'Página seguinte', 'casadasfardas' ) . '</span>';
		$args['end_size']  = 1;
		$args['mid_size']  = 1;
		return $args;
	}
);

/**
 * Preço formatado para cartões e fichas (sem IVA + com IVA, ou «sob orçamento»).
 */
function cdf_theme_price_block( WC_Product $product, string $context = 'card' ): string {
	$html = $product->get_price_html();
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '<p class="cdf-price cdf-price--quote">' . esc_html__( 'Preço sob orçamento', 'casadasfardas' ) . '</p>';
	}
	return '<div class="cdf-price cdf-price--' . esc_attr( $context ) . '">' . $html . '</div>';
}

/**
 * Número de cores (variações de cor) para os cartões.
 */
function cdf_theme_color_count( WC_Product $product ): int {
	if ( ! $product->is_type( 'variable' ) ) {
		return 0;
	}
	$attrs = $product->get_variation_attributes();
	return isset( $attrs['pa_cor'] ) ? count( $attrs['pa_cor'] ) : 0;
}

function cdf_theme_brand_name( int $product_id ): string {
	$terms = get_the_terms( $product_id, 'product_brand' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
}

/**
 * Galeria da ficha: imagem principal com zoom ao passar o rato e miniaturas.
 */
function cdf_theme_product_gallery( WC_Product $product ) {
	$ids = array_values( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );
	echo '<div class="cdf-gallery" data-cdf-gallery>';
	if ( ! $ids ) {
		echo '<div class="cdf-gallery__main cdf-gallery__main--empty">' . wc_placeholder_img( 'woocommerce_single' ) . '<p>' . esc_html__( 'Fotografia não disponível', 'casadasfardas' ) . '</p></div></div>'; // phpcs:ignore
		return;
	}
	$main = $ids[0];
	$full = wp_get_attachment_image_url( $main, 'full' );
	echo '<figure class="cdf-gallery__main">';
	echo '<a class="cdf-gallery__zoom" href="' . esc_url( $full ) . '" data-cdf-zoom aria-label="' . esc_attr__( 'Ampliar imagem', 'casadasfardas' ) . '">';
	echo wp_get_attachment_image(
		$main,
		'woocommerce_single',
		false,
		array(
			'class'         => 'cdf-gallery__img',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'sizes'         => '(min-width: 1100px) 600px, (min-width: 768px) 50vw, 100vw',
			'data-full'     => $full,
		)
	);
	echo '<span class="cdf-gallery__zoom-hint">' . cdf_svg( 'zoom', 18 ) . '<span>' . esc_html__( 'Ampliar', 'casadasfardas' ) . '</span></span>'; // phpcs:ignore
	echo '</a></figure>';
	if ( count( $ids ) > 1 ) {
		echo '<ul class="cdf-gallery__thumbs" aria-label="' . esc_attr__( 'Mais imagens', 'casadasfardas' ) . '">';
		foreach ( $ids as $i => $id ) {
			$src_single = wp_get_attachment_image_url( $id, 'woocommerce_single' );
			printf(
				'<li><button type="button" class="cdf-gallery__thumb" aria-pressed="%1$s" data-src="%2$s" data-srcset="%3$s" data-full="%4$s"><span class="screen-reader-text">%5$s</span>%6$s</button></li>',
				0 === $i ? 'true' : 'false',
				esc_url( $src_single ),
				esc_attr( (string) wp_get_attachment_image_srcset( $id, 'woocommerce_single' ) ),
				esc_url( wp_get_attachment_image_url( $id, 'full' ) ),
				esc_html( sprintf( __( 'Ver imagem %d', 'casadasfardas' ), $i + 1 ) ),
				wp_get_attachment_image( $id, 'woocommerce_gallery_thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) )
			);
		}
		echo '</ul>';
	}
	echo '</div>';
}

/**
 * Atributo de variação mostrado como amostras (o select nativo continua a existir por baixo).
 */
add_filter(
	'woocommerce_dropdown_variation_attribute_options_html',
	static function ( string $html, array $args ) {
		$attribute = $args['attribute'] ?? '';
		if ( ! in_array( $attribute, array( 'pa_cor', 'pa_medida' ), true ) ) {
			return $html;
		}
		return '<div class="cdf-variation-select" data-cdf-swatch-select>' . $html . '</div>';
	},
	10,
	2
);

/**
 * Mensagens do carrinho: botão «Ver carrinho» e texto em português claro.
 */
add_filter(
	'wc_add_to_cart_message_html',
	static function ( string $message ) {
		return $message;
	}
);

/**
 * Título da página de resultados.
 */
add_filter(
	'woocommerce_page_title',
	static function ( string $title ) {
		if ( is_search() ) {
			/* translators: %s termo pesquisado */
			return sprintf( __( 'Resultados para «%s»', 'casadasfardas' ), get_search_query() );
		}
		return $title;
	}
);

/**
 * Templates de email e conta: manter os do WooCommerce (traduzidos), com cor da marca nas opções.
 * Página de produto sem stock definido: nunca mostrar «Em stock» por defeito.
 */
add_filter( 'woocommerce_get_stock_html', static function ( string $html, WC_Product $product ) {
	return 'instock' === $product->get_stock_status() && ! $product->managing_stock() ? '' : $html;
}, 10, 2 );

/**
 * «Limpar escolha» como botão (não uma ligação para «#») e em português.
 */
add_filter(
	'woocommerce_reset_variations_link',
	static fn() => '<button type="button" class="reset_variations cdf-link-button">' . esc_html__( 'Limpar escolha', 'casadasfardas' ) . '</button>'
);

/**
 * Grafia do Acordo Ortográfico nas traduções do WooCommerce que ainda usam a forma antiga.
 */
add_filter(
	'gettext_woocommerce',
	static function ( string $translation ) {
		static $map = array(
			'facturação' => 'faturação',
			'Facturação' => 'Faturação',
			'factura'    => 'fatura',
			'Factura'    => 'Fatura',
			'actual'     => 'atual',
			'Actual'     => 'Atual',
			'óptimo'     => 'ótimo',
			'acção'      => 'ação',
			'Acção'      => 'Ação',
			'colecção'   => 'coleção',
		);
		return strtr( $translation, $map );
	}
);
