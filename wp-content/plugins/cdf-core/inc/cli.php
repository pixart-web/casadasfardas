<?php
/**
 * Comandos WP-CLI: wp cdf setup | import | pages | status | redirects
 */

defined( 'ABSPATH' ) || exit;

class CDF_CLI {

	/**
	 * Configura a loja (idempotente): idioma, moeda, IVA, envios, pagamentos, permalinks e opções.
	 *
	 * ## EXAMPLES
	 *     wp cdf setup
	 */
	public function setup() {
		$env = wp_get_environment_type();
		WP_CLI::log( "Ambiente: $env" );

		// Geral.
		update_option( 'blogname', 'Casa das Fardas' );
		update_option( 'blogdescription', 'Fardas, vestuário de trabalho, calçado de segurança e equipamentos de proteção individual.' );
		update_option( 'timezone_string', 'Europe/Lisbon' );
		update_option( 'date_format', 'd/m/Y' );
		update_option( 'time_format', 'H:i' );
		update_option( 'start_of_week', 1 );
		update_option( 'WPLANG', 'pt_PT' );
		// O noindex de staging é feito por cabeçalho/meta (environment.php), não pelo robots.txt.
		update_option( 'blog_public', 1 );
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'admin_email', 'admin@casadasfardas.test' );

		// WooCommerce: loja, moeda e formatos portugueses.
		$opts = array(
			'woocommerce_store_address'               => 'Retail Park Vale de Lousas, EN 125 Vale de Lousas',
			'woocommerce_store_city'                  => 'Alcantarilha',
			'woocommerce_store_postcode'              => '8365-306',
			'woocommerce_default_country'             => 'PT',
			'woocommerce_allowed_countries'           => 'specific',
			'woocommerce_specific_allowed_countries'  => array( 'PT' ),
			'woocommerce_ship_to_countries'           => 'specific',
			'woocommerce_specific_ship_to_countries'  => array( 'PT' ),
			'woocommerce_currency'                    => 'EUR',
			'woocommerce_currency_pos'                => 'right_space',
			'woocommerce_price_thousand_sep'          => '.',
			'woocommerce_price_decimal_sep'           => ',',
			'woocommerce_price_num_decimals'          => 2,
			'woocommerce_weight_unit'                 => 'kg',
			'woocommerce_dimension_unit'              => 'cm',
			// IVA: preços introduzidos sem IVA (como na origem), mostrados sem IVA com o valor c/ IVA ao lado.
			'woocommerce_calc_taxes'                  => 'yes',
			'woocommerce_prices_include_tax'          => 'no',
			'woocommerce_tax_based_on'                => 'billing',
			'woocommerce_tax_display_shop'            => 'excl',
			'woocommerce_tax_display_cart'            => 'excl',
			'woocommerce_tax_total_display'           => 'itemized',
			'woocommerce_tax_round_at_subtotal'       => 'yes',
			'woocommerce_price_display_suffix'        => '<span class="cdf-vat">+ IVA</span> <span class="cdf-vat-incl">{price_including_tax} c/ IVA</span>',
			// Conta e checkout.
			'woocommerce_enable_guest_checkout'       => 'yes',
			'woocommerce_enable_checkout_login_reminder' => 'yes',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_enable_myaccount_registration' => 'yes',
			'woocommerce_registration_generate_username' => 'yes',
			'woocommerce_registration_generate_password' => 'yes',
			'woocommerce_enable_reviews'              => 'no',
			'woocommerce_enable_ajax_add_to_cart'     => 'yes',
			'woocommerce_cart_redirect_after_add'     => 'no',
			'woocommerce_manage_stock'                => 'no',
			'woocommerce_hide_out_of_stock_items'     => 'no',
			'woocommerce_coming_soon'                 => 'no',
			'woocommerce_store_pages_only'            => 'no',
			'woocommerce_allow_tracking'              => 'no',
			'woocommerce_show_marketplace_suggestions' => 'no',
			'woocommerce_analytics_enabled'           => 'no',
			'woocommerce_feature_order_attribution_enabled' => 'no',
			'woocommerce_feature_site_visibility_badge_enabled' => 'no',
			'woocommerce_custom_orders_table_enabled' => 'yes',
			'woocommerce_custom_orders_table_data_sync_enabled' => 'no',
			'woocommerce_email_from_name'             => 'Casa das Fardas',
			'woocommerce_email_from_address'          => 'loja@casadasfardas.test',
			'woocommerce_email_base_color'            => '#003B71',
			'woocommerce_email_footer_text'           => 'Casa das Fardas · Amazonas Indústria LDA. · Retail Park Vale de Lousas, EN 125, 8365-306 Alcantarilha',
			'woocommerce_thumbnail_image_width'       => 420,
			'woocommerce_single_image_width'          => 600,
			'woocommerce_thumbnail_cropping'          => '1:1',
			'woocommerce_catalog_columns'             => 4,
			'woocommerce_catalog_rows'                => 6,
			'woocommerce_default_catalog_orderby'     => 'menu_order',
			'woocommerce_permalinks'                  => array(
				'product_base'           => '/produto',
				'category_base'          => 'categoria',
				'tag_base'               => 'etiqueta',
				'attribute_base'         => '',
				'use_verbose_page_rules' => false,
			),
			'woocommerce_checkout_phone_field'        => 'optional',
			'woocommerce_checkout_company_field'      => 'optional',
			'woocommerce_checkout_address_2_field'    => 'optional',
			'woocommerce_checkout_highlight_required_fields' => 'yes',
			'cdf_price_mode'                          => 'shop',
		);
		foreach ( $opts as $key => $value ) {
			update_option( $key, $value );
		}
		WP_CLI::log( 'Opções da loja aplicadas.' );

		// Conteúdo de exemplo criado pela instalação do WordPress/WooCommerce (não é conteúdo da loja).
		foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page', 'privacy-policy' => 'page', 'refund_returns' => 'page', 'shop' => 'page', 'cart' => 'page', 'checkout' => 'page', 'my-account' => 'page' ) as $slug => $type ) {
			$p = get_page_by_path( $slug, OBJECT, $type );
			if ( $p && ! get_post_meta( $p->ID, '_cdf_seeded', true ) ) {
				wp_delete_post( $p->ID, true );
			}
		}

		$this->setup_tax();
		$this->setup_shipping();
		$this->setup_payments( $env );
		$this->setup_emails();
		flush_rewrite_rules( false );
		WP_CLI::success( 'Loja configurada.' );
	}

	private function setup_tax() {
		global $wpdb;
		$exists = $wpdb->get_var( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'PT' AND tax_rate_class = '' LIMIT 1" );
		$rate   = array(
			'tax_rate_country'  => 'PT',
			'tax_rate_state'    => '',
			'tax_rate'          => '23.0000',
			'tax_rate_name'     => 'IVA',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => '',
		);
		if ( $exists ) {
			WC_Tax::_update_tax_rate( (int) $exists, $rate );
		} else {
			WC_Tax::_insert_tax_rate( $rate );
		}
		WP_CLI::log( 'IVA 23% (taxa normal, Portugal continental) configurado. Taxas das regiões autónomas: a validar.' );
	}

	private function setup_shipping() {
		$zones = WC_Shipping_Zones::get_zones();
		$zone  = null;
		foreach ( $zones as $z ) {
			if ( 'Portugal' === $z['zone_name'] ) {
				$zone = new WC_Shipping_Zone( $z['id'] );
			}
		}
		if ( ! $zone ) {
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( 'Portugal' );
			$zone->set_zone_order( 1 );
			$zone->add_location( 'PT', 'country' );
			$zone->save();
		}
		$methods = array();
		foreach ( $zone->get_shipping_methods() as $m ) {
			$methods[] = $m->id;
		}
		// Sem transportadora, tarifas nem portes grátis confirmados: os portes são comunicados antes do envio.
		if ( ! in_array( 'flat_rate', $methods, true ) ) {
			$id = $zone->add_shipping_method( 'flat_rate' );
			update_option(
				'woocommerce_flat_rate_' . $id . '_settings',
				array(
					'enabled'    => 'yes',
					'title'      => 'Envio para morada (portes confirmados antes do envio)',
					'tax_status' => 'none',
					'cost'       => '0',
				)
			);
		}
		if ( ! in_array( 'local_pickup', $methods, true ) ) {
			$id = $zone->add_shipping_method( 'local_pickup' );
			update_option(
				'woocommerce_local_pickup_' . $id . '_settings',
				array(
					'enabled'    => 'yes',
					'title'      => 'Levantamento na loja (Alcantarilha)',
					'tax_status' => 'none',
					'cost'       => '0',
				)
			);
		}
		WP_CLI::log( 'Zona Portugal: envio (portes a confirmar) e levantamento na loja — ambos A VALIDAR com o cliente.' );
	}

	private function setup_payments( string $env ) {
		$test = 'production' !== $env ? 'yes' : 'no';
		update_option(
			'woocommerce_cdf_test_settings',
			array(
				'enabled'     => $test,
				'title'       => 'Pagamento de teste (demonstração)',
				'description' => 'Ambiente de demonstração: nenhum valor é cobrado. Escolha o resultado a simular.',
			)
		);
		foreach ( array( 'bacs', 'cheque', 'cod' ) as $gw ) {
			$settings            = (array) get_option( "woocommerce_{$gw}_settings", array() );
			$settings['enabled'] = 'no';
			update_option( "woocommerce_{$gw}_settings", $settings );
		}
		update_option( 'woocommerce_gateway_order', array( 'cdf_test' => 0 ) );
		WP_CLI::log( 'production' !== $env ? 'Pagamentos: só o gateway de teste (staging).' : 'Pagamentos: nenhum ativo — configurar o gateway real antes do lançamento.' );
	}

	private function setup_emails() {
		$recipient = 'production' === wp_get_environment_type() ? cdf_quote_recipient() : 'encomendas@casadasfardas.test';
		foreach ( array( 'new_order', 'cancelled_order', 'failed_order' ) as $email ) {
			$settings              = (array) get_option( "woocommerce_{$email}_settings", array() );
			$settings['enabled']   = 'yes';
			$settings['recipient'] = $recipient;
			update_option( "woocommerce_{$email}_settings", $settings );
		}
	}

	/**
	 * Importa o catálogo normalizado.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Caminho do catalog.json. Por omissão: CDF_IMPORT_DIR/catalog/catalog.json
	 *
	 * [--dry-run]
	 * : Valida sem escrever nada.
	 *
	 * [--force]
	 * : Reaplica todos os registos mesmo sem alterações.
	 *
	 * [--skip-images]
	 * : Não importa imagens (útil para testar a estrutura).
	 *
	 * [--offset=<n>]
	 * : Começa no produto n (retoma por lotes).
	 *
	 * [--limit=<n>]
	 * : Número máximo de produtos neste lote.
	 *
	 * [--report=<file>]
	 * : Escreve o relatório em JSON.
	 *
	 * ## EXAMPLES
	 *     wp cdf import --dry-run
	 *     wp cdf import --offset=0 --limit=200 --report=/exports/import-report.json
	 */
	public function import( $args, $assoc ) {
		$file = $assoc['file'] ?? ( ( defined( 'CDF_IMPORT_DIR' ) ? CDF_IMPORT_DIR : ABSPATH ) . '/catalog/catalog.json' );
		if ( ! defined( 'CDF_IMPORTING' ) ) {
			define( 'CDF_IMPORTING', true );
		}
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );
		$start    = microtime( true );
		$importer = new CDF_Importer( $file, $assoc );
		$importer->ensure_attributes();
		$importer->import_categories();
		$importer->import_products( (int) ( $assoc['offset'] ?? 0 ), (int) ( $assoc['limit'] ?? 0 ) );
		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );
		$importer->finish();
		$report             = $importer->report();
		$report['seconds']  = round( microtime( true ) - $start, 1 );
		$report['dry_run']  = ! empty( $assoc['dry-run'] );
		$report['file']     = $file;
		$report['finished'] = gmdate( 'c' );
		if ( ! empty( $assoc['report'] ) ) {
			file_put_contents( $assoc['report'], wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		}
		foreach ( array( 'categories', 'brands', 'products', 'variations', 'images' ) as $k ) {
			WP_CLI::log( str_pad( $k, 12 ) . wp_json_encode( $report[ $k ] ) );
		}
		WP_CLI::log( 'avisos: ' . count( $report['warnings'] ) . ' · erros: ' . count( $report['errors'] ) . ' · ' . $report['seconds'] . ' s' );
		if ( $report['errors'] ) {
			WP_CLI::warning( 'Importação concluída com erros (ver relatório).' );
		} else {
			WP_CLI::success( $report['dry_run'] ? 'Validação concluída sem erros.' : 'Importação concluída.' );
		}
	}

	/**
	 * Cria/atualiza páginas, menus e conteúdos editoriais (sem sobrepor edições, salvo --force).
	 *
	 * [--force]
	 * : Repõe o conteúdo original das páginas criadas pelo importador.
	 */
	public function pages( $args, $assoc ) {
		$pages = new CDF_Pages( ! empty( $assoc['force'] ) );
		$pages->run();
		WP_CLI::success( 'Páginas e menus prontos.' );
	}

	/**
	 * Resumo da loja.
	 */
	public function status() {
		global $wpdb;
		$count = static fn( $type, $status = 'publish' ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", $type, $status ) );
		$rows  = array(
			array( 'item' => 'Produtos publicados', 'n' => $count( 'product' ) ),
			array( 'item' => 'Produtos em rascunho', 'n' => $count( 'product', 'draft' ) ),
			array( 'item' => 'Variações', 'n' => $count( 'product_variation' ) ),
			array( 'item' => 'Categorias', 'n' => (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) ),
			array( 'item' => 'Marcas', 'n' => (int) wp_count_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) ) ),
			array( 'item' => 'Imagens', 'n' => $count( 'attachment', 'inherit' ) ),
			array( 'item' => 'Produtos com preço', 'n' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->wc_product_meta_lookup} l ON l.product_id = p.ID WHERE p.post_type = 'product' AND p.post_status = 'publish' AND l.max_price > 0" ) ),
			array( 'item' => 'Pedidos de orçamento', 'n' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'cdf_quote'" ) ),
			array( 'item' => 'Modo de preços', 'n' => cdf_price_mode() ),
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'item', 'n' ) );
	}

	/**
	 * Gera o mapa de redirecionamentos a partir do inventário e testa cada URL antiga.
	 *
	 * ## OPTIONS
	 *
	 * <inventory>
	 * : content-inventory.csv
	 *
	 * <output>
	 * : redirect-map.csv a escrever
	 *
	 * [--base-url=<base>]
	 * : Testa os pedidos HTTP contra este endereço (ex.: http://wordpress dentro do Docker).
	 */
	public function redirects( $args, $assoc ) {
		list( $inventory, $output ) = $args;
		$in = fopen( $inventory, 'r' );
		if ( ! $in ) {
			WP_CLI::error( "Não foi possível ler $inventory" );
		}
		$header = fgetcsv( $in );
		$out    = fopen( $output, 'w' );
		fputcsv( $out, array( 'url_antiga', 'caminho', 'url_nova', 'estado_http', 'motivo', 'teste' ) );
		$stats = array( 'ok' => 0, 'falha' => 0, 'sem_destino' => 0, 'ignorado' => 0 );
		$http  = $assoc['base-url'] ?? '';
		while ( ( $row = fgetcsv( $in ) ) !== false ) {
			$rec = array_combine( $header, $row );
			$url = $rec['url_antiga'];
			if ( ! $url || ! str_starts_with( $url, 'https://www.casadasfardas.pt' ) || str_contains( $url, '/back/fotos/' ) ) {
				$stats['ignorado']++;
				continue;
			}
			$parts  = wp_parse_url( $url );
			$path   = $parts['path'] ?? '/';
			$query  = array();
			wp_parse_str( $parts['query'] ?? '', $query );
			if ( '/' === $path && ! $query ) {
				fputcsv( $out, array( $url, '/', home_url( '/' ), 200, 'página inicial (mesmo caminho)', 'n/a' ) );
				continue;
			}
			$target = cdf_resolve_legacy( $path, $query );
			$test   = 'n/a';
			if ( ! $target ) {
				$stats['sem_destino']++;
				fputcsv( $out, array( $url, $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ), '', 404, 'sem destino', 'n/a' ) );
				continue;
			}
			if ( $http ) {
				$req  = wp_remote_get( rtrim( $http, '/' ) . $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ), array( 'redirection' => 0, 'timeout' => 15, 'headers' => array( 'Host' => wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' ) ) ) );
				$code = (int) wp_remote_retrieve_response_code( $req );
				$loc  = wp_remote_retrieve_header( $req, 'location' );
				$good = $code === $target['status'] && ( 410 === $code || $loc === $target['url'] );
				if ( $good && 301 === $code ) {
					// Sem cadeias: o destino tem de responder 200.
					$dest = wp_remote_get( str_replace( home_url(), rtrim( $http, '/' ), $loc ), array( 'redirection' => 0, 'timeout' => 15, 'headers' => array( 'Host' => wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . wp_parse_url( home_url(), PHP_URL_PORT ) ) ) );
					$good = 200 === (int) wp_remote_retrieve_response_code( $dest );
				}
				$test = $good ? 'ok' : "falha ($code)";
				$stats[ $good ? 'ok' : 'falha' ]++;
			}
			fputcsv( $out, array( $url, $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ), $target['url'], $target['status'], $target['reason'], $test ) );
		}
		fclose( $in );
		fclose( $out );
		WP_CLI::log( wp_json_encode( $stats ) );
		WP_CLI::success( "Mapa escrito em $output" );
	}
}

WP_CLI::add_command( 'cdf', 'CDF_CLI' );
