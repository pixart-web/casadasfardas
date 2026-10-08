<?php
/**
 * Suportes do tema, menus e tamanhos de imagem.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'casadasfardas', CDF_THEME_DIR . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'align-wide' );
		remove_theme_support( 'core-block-patterns' );
		add_editor_style( array( 'assets/css/base.css', 'assets/css/content.css', 'assets/css/editor.css' ) );

		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 420,
				'single_image_width'    => 600,
				'product_grid'          => array(
					'default_columns' => 4,
					'min_columns'     => 2,
					'max_columns'     => 4,
				),
			)
		);
		// Galeria própria (sem zoom/slider do WooCommerce, que carregam jQuery plugins pesados).
		remove_theme_support( 'wc-product-gallery-zoom' );
		remove_theme_support( 'wc-product-gallery-lightbox' );
		remove_theme_support( 'wc-product-gallery-slider' );

		register_nav_menus(
			array(
				'primary'        => __( 'Menu principal (mega menu)', 'casadasfardas' ),
				'footer_shop'    => __( 'Rodapé — Loja', 'casadasfardas' ),
				'footer_help'    => __( 'Rodapé — Apoio ao cliente', 'casadasfardas' ),
				'footer_company' => __( 'Rodapé — Empresa', 'casadasfardas' ),
				'footer_legal'   => __( 'Rodapé — Informação legal', 'casadasfardas' ),
			)
		);

		add_image_size( 'cdf-card', 420, 420, true );
		add_image_size( 'cdf-hero', 900, 900, false );
	}
);

add_filter( 'excerpt_length', static fn() => 28 );
add_filter( 'excerpt_more', static fn() => '…' );

// Excertos editáveis nas páginas (usados na meta descrição).
add_action( 'init', static fn() => add_post_type_support( 'page', 'excerpt' ) );

// Sem emojis nem scripts desnecessários no front-end.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
add_filter( 'emoji_svg_url', '__return_false' );

/**
 * Página com template «Página larga»: conteúdo de blocos a toda a largura, sem título automático.
 */
add_filter(
	'theme_page_templates',
	static function ( array $templates ) {
		$templates['page-wide.php'] = __( 'Página larga (sem título automático)', 'casadasfardas' );
		return $templates;
	}
);
