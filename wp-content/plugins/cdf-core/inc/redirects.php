<?php
/**
 * Redirecionamentos 301 das URLs da loja antiga (plataforma PHP própria) para as novas.
 *
 * A resolução usa os identificadores de origem guardados na importação (_cdf_source_key):
 *   p:{id}   produto            /info/Nome-{id}.html, /m/info/Nome-{id}.html, ver_produtos_core.php?id=
 *   d:{id}   derivado (cor)     /info/Cor-{id}.html → produto + ?attribute_pa_cor=
 *   sub:{id} subcategoria       /info/...-{id}-subcategoria-lpc, listar_categoria.php?nivelm=subcategoria&id=
 *   cat:{id} categoria          /info/...-{id}-categoria-lpc,    listar_categoria.php?nivelm=categoria&id=
 * Sem cadeias (destino final direto) e sem enviar tudo para a página inicial.
 * URLs antigas sem equivalente ficam registadas (WooCommerce → Estado → URLs antigas) para revisão.
 */

defined( 'ABSPATH' ) || exit;

function cdf_redirects_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'cdf_legacy_misses';
}

function cdf_redirects_install_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = cdf_redirects_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			path VARCHAR(500) NOT NULL,
			hits INT UNSIGNED NOT NULL DEFAULT 1,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY path (path(191))
		) {$charset};"
	);
}

/**
 * Resolve um caminho antigo (com query string) para o destino novo.
 *
 * @return array{url:string,status:int,reason:string}|null
 */
function cdf_resolve_legacy( string $path, array $query = array() ): ?array {
	$path = '/' . ltrim( rawurldecode( $path ), '/' );
	$path = preg_replace( '#/+#', '/', $path );

	// Página inicial antiga e versão mobile.
	if ( preg_match( '#^/(m/?)?(index\.php)?$#i', $path ) && '/' !== $path ) {
		return array( 'url' => home_url( '/' ), 'status' => 301, 'reason' => 'homepage' );
	}
	if ( preg_match( '#^/(?:m/)?info/contactos\.php$#i', $path ) ) {
		$url = cdf_page_url( 'contactos' );
		return $url ? array( 'url' => $url, 'status' => 301, 'reason' => 'contactos' ) : null;
	}
	// Categorias e subcategorias.
	if ( preg_match( '#^/(?:m/)?info/.*-(\d+)-(subcategoria|categoria)-lpc/?$#i', $path, $m ) ) {
		return cdf_resolve_term( ( 'subcategoria' === strtolower( $m[2] ) ? 'sub:' : 'cat:' ) . $m[1] );
	}
	if ( preg_match( '#^/(?:m/)?info/listar_categoria(?:_corev2)?\.php$#i', $path ) && ! empty( $query['id'] ) ) {
		$level = strtolower( (string) ( $query['nivelm'] ?? 'categoria' ) );
		if ( 'tipo' === $level || 'marca' === $level ) {
			$key = 'type:' . absint( $query['id'] );
		} else {
			$key = ( 'subcategoria' === $level ? 'sub:' : 'cat:' ) . absint( $query['id'] );
		}
		return cdf_resolve_term( $key );
	}
	// Produtos e derivados de cor.
	if ( preg_match( '#^/(?:m/)?info/.+-(\d+)\.html$#i', $path, $m ) ) {
		return cdf_resolve_product( (int) $m[1] );
	}
	if ( preg_match( '#^/(?:m/)?info/(?:ver_produtos(?:_core)?|imprimir)\.php$#i', $path ) && ! empty( $query['id'] ) ) {
		return cdf_resolve_product( absint( $query['id'] ) );
	}
	// Pesquisa e listagens gerais.
	if ( preg_match( '#^/(?:m/)?info/listar(?:_marcas)?\.php$#i', $path ) ) {
		if ( ! empty( $query['procurar'] ) ) {
			return array(
				'url'    => add_query_arg( array( 's' => rawurlencode( sanitize_text_field( $query['procurar'] ) ), 'post_type' => 'product' ), home_url( '/' ) ),
				'status' => 301,
				'reason' => 'pesquisa',
			);
		}
		return array( 'url' => wc_get_page_permalink( 'shop' ), 'status' => 301, 'reason' => 'listagem geral' );
	}
	// Área de cliente antiga → nova área de cliente.
	if ( preg_match( '#^/(?:m/)?clientes/#i', $path ) ) {
		return array( 'url' => wc_get_page_permalink( 'myaccount' ), 'status' => 301, 'reason' => 'área de cliente' );
	}
	if ( preg_match( '#^/(?:m/)?info/(?:carrinho|compras_[a-z_]+)\.php$#i', $path ) ) {
		return array( 'url' => wc_get_cart_url(), 'status' => 301, 'reason' => 'carrinho' );
	}
	// Recursos sem equivalente: removidos definitivamente.
	if ( preg_match( '#^/(?:rss\.php|produtos\.xml|posts\.xml|sitemap\.txt)$#i', $path ) ) {
		return array( 'url' => '', 'status' => 410, 'reason' => 'recurso antigo sem equivalente' );
	}
	return null;
}

function cdf_resolve_term( string $source_key ): ?array {
	$term = cdf_term_by_source_key( $source_key );
	if ( ! $term ) {
		return null;
	}
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? null : array( 'url' => $link, 'status' => 301, 'reason' => 'categoria ' . $source_key );
}

function cdf_resolve_product( int $source_id ): ?array {
	$post_id = cdf_post_by_source_id( 'p:' . $source_id );
	if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
		return array( 'url' => get_permalink( $post_id ), 'status' => 301, 'reason' => 'produto p:' . $source_id );
	}
	$variation_id = cdf_post_by_source_id( 'd:' . $source_id );
	if ( $variation_id ) {
		$variation = wc_get_product( $variation_id );
		// Cor de um produto que ficou em rascunho: segue o destino do produto-pai.
		if ( $variation && 'publish' !== get_post_status( $variation->get_parent_id() ) ) {
			$parent_key = (string) get_post_meta( $variation->get_parent_id(), '_cdf_source_key', true );
			if ( str_starts_with( $parent_key, 'p:' ) ) {
				return cdf_resolve_product( (int) substr( $parent_key, 2 ) );
			}
		}
		if ( $variation && 'publish' === get_post_status( $variation->get_parent_id() ) ) {
			$color = $variation->get_attributes()['pa_cor'] ?? '';
			$url   = get_permalink( $variation->get_parent_id() );
			return array(
				'url'    => $color ? add_query_arg( 'attribute_pa_cor', $color, $url ) : $url,
				'status' => 301,
				'reason' => 'cor d:' . $source_id,
			);
		}
	}
	// Artigo removido na própria loja antiga («Produto não existe»): 410.
	if ( in_array( 'p:' . $source_id, (array) get_option( 'cdf_legacy_removed', array() ), true ) ) {
		return array( 'url' => '', 'status' => 410, 'reason' => 'removido na origem' );
	}
	// Produto antigo sem equivalente publicado: categoria de destino registada na importação.
	$fallback = get_option( 'cdf_legacy_fallbacks', array() );
	if ( isset( $fallback[ 'p:' . $source_id ] ) ) {
		$term = cdf_term_by_source_key( $fallback[ 'p:' . $source_id ] );
		if ( $term ) {
			return array( 'url' => get_term_link( $term ), 'status' => 301, 'reason' => 'produto removido → categoria' );
		}
	}
	return null;
}

add_action(
	'template_redirect',
	static function () {
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path  = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$query = array();
		wp_parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $query );

		$legacy_shape = (bool) preg_match( '#^/(m(/|$)|info/|clientes/|index\.php|rss\.php|produtos\.xml|posts\.xml|sitemap\.txt)#i', $path );
		if ( ! $legacy_shape ) {
			return;
		}
		$target = cdf_resolve_legacy( $path, $query );
		if ( $target ) {
			if ( 410 === $target['status'] ) {
				status_header( 410 );
				nocache_headers();
				include get_query_template( '404' );
				exit;
			}
			wp_redirect( $target['url'], 301, 'Casa das Fardas' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- destino interno.
			exit;
		}
		if ( is_404() ) {
			cdf_log_legacy_miss( $uri );
		}
	},
	0
);

function cdf_log_legacy_miss( string $uri ) {
	global $wpdb;
	$path = mb_substr( $uri, 0, 500 );
	$now  = current_time( 'mysql', true );
	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO ' . cdf_redirects_table() . ' (path, hits, first_seen, last_seen) VALUES (%s, 1, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$path,
			$now,
			$now
		)
	);
}

/**
 * Relatório simples no backoffice: URLs antigas pedidas sem destino.
 */
add_action( 'admin_menu', static function () {
	add_submenu_page( 'woocommerce', __( 'URLs antigas sem destino', 'cdf-core' ), __( 'URLs antigas', 'cdf-core' ), 'manage_woocommerce', 'cdf-legacy', 'cdf_render_legacy_page' );
}, 70 );

function cdf_render_legacy_page() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT path, hits, first_seen, last_seen FROM ' . cdf_redirects_table() . ' ORDER BY hits DESC LIMIT 200' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	echo '<div class="wrap"><h1>' . esc_html__( 'URLs antigas sem destino', 'cdf-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'Pedidos a endereços da loja antiga que não encontraram equivalente. Reveja aos 7, 14 e 30 dias após o lançamento.', 'cdf-core' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>URL</th><th>Pedidos</th><th>Primeira vez</th><th>Última vez</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="4">' . esc_html__( 'Sem registos.', 'cdf-core' ) . '</td></tr>';
	}
	foreach ( $rows as $row ) {
		printf( '<tr><td><code>%s</code></td><td>%d</td><td>%s</td><td>%s</td></tr>', esc_html( $row->path ), (int) $row->hits, esc_html( $row->first_seen ), esc_html( $row->last_seen ) );
	}
	echo '</tbody></table></div>';
}
