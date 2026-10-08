<?php
/**
 * Funções utilitárias partilhadas.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lê uma opção do plugin com valor por omissão.
 */
function cdf_option( string $key, $fallback = null ) {
	$value = get_option( 'cdf_' . $key, null );
	return null === $value ? $fallback : $value;
}

/**
 * Hash anónimo do IP para limites de abuso (não guarda o IP em claro).
 */
function cdf_client_fingerprint(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
}

/**
 * Limite simples por janela temporal. Devolve false quando o limite foi excedido.
 */
function cdf_rate_limit( string $bucket, int $max, int $window ): bool {
	$key   = 'cdf_rl_' . md5( $bucket . '|' . cdf_client_fingerprint() );
	$count = (int) get_transient( $key );
	if ( $count >= $max ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/**
 * Procura um objeto importado pelo identificador de origem.
 *
 * @return int ID do post (produto/variação) ou 0.
 */
function cdf_post_by_source_id( string $source_key ): int {
	global $wpdb;
	$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_cdf_source_key' AND meta_value = %s LIMIT 1",
			$source_key
		)
	);
	return (int) $id;
}

/**
 * Procura um termo importado pelo identificador de origem.
 */
function cdf_term_by_source_key( string $source_key, string $taxonomy = 'product_cat' ): ?WP_Term {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 1,
			'meta_query' => array(
				array(
					'key'   => '_cdf_source_key',
					'value' => $source_key,
				),
			),
		)
	);
	return ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : null;
}

/**
 * Página por caminho com cache estático.
 */
function cdf_page_url( string $path ): string {
	static $cache = array();
	if ( ! isset( $cache[ $path ] ) ) {
		$page           = get_page_by_path( $path );
		$cache[ $path ] = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
	}
	return $cache[ $path ];
}

/**
 * Ícones SVG inline usados pelo plugin (formulários, estados).
 */
function cdf_icon( string $name, int $size = 20 ): string {
	$paths = array(
		'check' => '<path d="M5 12.5l4.2 4.2L19 7" />',
		'alert' => '<path d="M12 8v5m0 3.5v.01M10.3 3.9L2.6 17.5A2 2 0 004.3 20.5h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />',
		'file'  => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z" /><path d="M14 3v5h5" />',
		'x'     => '<path d="M6 6l12 12M18 6L6 18" />',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="cdf-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		$size,
		$paths[ $name ]
	);
}
