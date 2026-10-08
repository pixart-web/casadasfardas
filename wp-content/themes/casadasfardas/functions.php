<?php
/**
 * Tema Casa das Fardas.
 *
 * Apresentação apenas: a lógica de negócio (preços, orçamento, pesquisa, SEO, importação)
 * vive no plugin cdf-core. O tema funciona sem o plugin, com menos funcionalidades.
 */

defined( 'ABSPATH' ) || exit;

define( 'CDF_THEME_VERSION', '1.0.0' );
define( 'CDF_THEME_DIR', get_template_directory() );
define( 'CDF_THEME_URI', get_template_directory_uri() );

require_once CDF_THEME_DIR . '/inc/setup.php';
require_once CDF_THEME_DIR . '/inc/assets.php';
require_once CDF_THEME_DIR . '/inc/template-tags.php';
require_once CDF_THEME_DIR . '/inc/navigation.php';
require_once CDF_THEME_DIR . '/inc/block-styles.php';

if ( class_exists( 'WooCommerce' ) ) {
	require_once CDF_THEME_DIR . '/inc/woocommerce.php';
}
