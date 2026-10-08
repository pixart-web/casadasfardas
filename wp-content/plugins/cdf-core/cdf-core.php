<?php
/**
 * Plugin Name:       Casa das Fardas — Núcleo
 * Description:       Funcionalidades de negócio da loja Casa das Fardas: dados da empresa, política de preços e IVA, pedidos de orçamento com anexos privados, pesquisa por referência, filtros, blocos dinâmicos, SEO técnico, redirecionamentos da loja antiga e importador do catálogo.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Casa das Fardas
 * Text Domain:       cdf-core
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'CDF_CORE_VERSION', '1.0.0' );
define( 'CDF_CORE_FILE', __FILE__ );
define( 'CDF_CORE_DIR', __DIR__ );
define( 'CDF_CORE_URL', plugin_dir_url( __FILE__ ) );

// Compatibilidade declarada com o armazenamento de encomendas de alto desempenho (HPOS)
// e com os blocos de carrinho/checkout (o tema usa o checkout clássico, ver docs/ARQUITETURA).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);

require_once CDF_CORE_DIR . '/inc/helpers.php';
require_once CDF_CORE_DIR . '/inc/business.php';
require_once CDF_CORE_DIR . '/inc/environment.php';
require_once CDF_CORE_DIR . '/inc/catalog.php';
require_once CDF_CORE_DIR . '/inc/pricing.php';
require_once CDF_CORE_DIR . '/inc/sizes.php';
require_once CDF_CORE_DIR . '/inc/checkout.php';
require_once CDF_CORE_DIR . '/inc/search.php';
require_once CDF_CORE_DIR . '/inc/filters.php';
require_once CDF_CORE_DIR . '/inc/quote.php';
require_once CDF_CORE_DIR . '/inc/blocks.php';
require_once CDF_CORE_DIR . '/inc/seo.php';
require_once CDF_CORE_DIR . '/inc/redirects.php';
require_once CDF_CORE_DIR . '/inc/privacy.php';
require_once CDF_CORE_DIR . '/inc/gateway-test.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CDF_CORE_DIR . '/inc/importer/class-importer.php';
	require_once CDF_CORE_DIR . '/inc/importer/class-pages.php';
	require_once CDF_CORE_DIR . '/inc/cli.php';
}

register_activation_hook( __FILE__, 'cdf_activate' );
register_deactivation_hook( __FILE__, 'cdf_deactivate' );

function cdf_activate() {
	cdf_quote_register_post_type();
	cdf_redirects_install_table();
	if ( ! wp_next_scheduled( 'cdf_daily_maintenance' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cdf_daily_maintenance' );
	}
	flush_rewrite_rules();
}

function cdf_deactivate() {
	wp_clear_scheduled_hook( 'cdf_daily_maintenance' );
}
