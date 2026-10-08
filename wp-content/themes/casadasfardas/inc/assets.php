<?php
/**
 * CSS e JS por componente, carregados só onde são necessários.
 */

defined( 'ABSPATH' ) || exit;

function cdf_asset_version( string $rel ): string {
	$file = CDF_THEME_DIR . '/' . $rel;
	return file_exists( $file ) ? CDF_THEME_VERSION . '.' . filemtime( $file ) : CDF_THEME_VERSION;
}

function cdf_enqueue_style( string $handle, string $rel, array $deps = array() ) {
	wp_enqueue_style( $handle, CDF_THEME_URI . '/' . $rel, $deps, cdf_asset_version( $rel ) );
}

function cdf_enqueue_script( string $handle, string $rel, array $deps = array() ) {
	wp_enqueue_script( $handle, CDF_THEME_URI . '/' . $rel, $deps, cdf_asset_version( $rel ), array( 'strategy' => 'defer', 'in_footer' => true ) );
}

/**
 * A página atual mostra blocos de produtos/categorias?
 */
function cdf_page_has_shop_blocks(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post && ( has_block( 'cdf/products', $post ) || has_block( 'cdf/categories', $post ) );
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		$is_wc       = function_exists( 'is_woocommerce' );
		$is_shop     = $is_wc && ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) );
		$is_product  = $is_wc && is_product();
		$is_checkout = $is_wc && ( is_cart() || is_checkout() || is_account_page() );

		cdf_enqueue_style( 'cdf-base', 'assets/css/base.css' );
		cdf_enqueue_script( 'cdf-header', 'assets/js/header.js' );
		wp_localize_script(
			'cdf-header',
			'cdfHeader',
			array(
				'suggestUrl' => esc_url_raw( rest_url( 'cdf/v1/suggest' ) ),
				'searchUrl'  => esc_url_raw( home_url( '/' ) ),
			)
		);

		if ( is_page() || is_front_page() || is_404() || is_singular( 'post' ) ) {
			cdf_enqueue_style( 'cdf-content', 'assets/css/content.css', array( 'cdf-base' ) );
		}
		if ( $is_shop || $is_product || cdf_page_has_shop_blocks() || is_404() || ( is_search() ) ) {
			cdf_enqueue_style( 'cdf-shop', 'assets/css/shop.css', array( 'cdf-base' ) );
		}
		if ( $is_shop ) {
			cdf_enqueue_script( 'cdf-shop', 'assets/js/shop.js' );
		}
		if ( $is_product ) {
			cdf_enqueue_style( 'cdf-product', 'assets/css/product.css', array( 'cdf-shop' ) );
			cdf_enqueue_script( 'cdf-product', 'assets/js/product.js' );
		}
		if ( $is_checkout ) {
			cdf_enqueue_style( 'cdf-checkout', 'assets/css/checkout.css', array( 'cdf-base' ) );
		}
		if ( is_page( array( 'pedido-de-orcamento', 'contactos' ) ) || has_block( 'cdf/form' ) ) {
			cdf_enqueue_style( 'cdf-forms', 'assets/css/forms.css', array( 'cdf-base' ) );
		}
		if ( $is_product ) {
			cdf_enqueue_style( 'cdf-forms', 'assets/css/forms.css', array( 'cdf-base' ) );
		}
	},
	20
);

/**
 * Só o CSS dos blocos presentes em cada página (em vez da biblioteca completa, que bloqueia a renderização).
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Remove estilos que o tema substitui por completo.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		// Estilos do WooCommerce substituídos pelos do tema.
		wp_dequeue_style( 'woocommerce-general' );
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
		wp_dequeue_style( 'wc-blocks-style' );
		wp_dequeue_style( 'brands-styles' );
		// Sem fragmentos de carrinho por AJAX em todas as páginas: o contador é renderizado no servidor.
		if ( ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
		// CSS global de blocos só nas páginas de conteúdo.
		if ( function_exists( 'is_woocommerce' ) && ( is_product() || is_shop() || is_product_taxonomy() ) ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}
	},
	100
);
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Pré-carrega as fontes principais (evita FOUT nos títulos e reduz CLS).
 */
add_action(
	'wp_head',
	static function () {
		echo "<script>document.documentElement.classList.add('js')</script>\n";
		foreach ( array( 'bricolage-latin-wght.woff2', 'inter-latin-wght.woff2' ) as $font ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( CDF_THEME_URI . '/assets/fonts/' . $font ) );
		}
		printf( '<link rel="icon" href="%s" sizes="any">' . "\n", esc_url( CDF_THEME_URI . '/assets/img/favicon.ico' ) );
		echo '<meta name="theme-color" content="#003B71">' . "\n";
	},
	2
);
