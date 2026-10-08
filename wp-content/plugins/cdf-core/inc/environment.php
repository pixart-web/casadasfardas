<?php
/**
 * Ambiente: email, staging (noindex e acesso restrito), imagens e endurecimento básico.
 */

defined( 'ABSPATH' ) || exit;

function cdf_is_production(): bool {
	return 'production' === wp_get_environment_type();
}

// ---------------------------------------------------------------- email

/**
 * SMTP definido por variáveis de ambiente (em local: Mailpit, que captura tudo).
 * Em produção configurar CDF_SMTP_* com o servidor real; credenciais nunca no repositório.
 */
add_action(
	'phpmailer_init',
	static function ( $mailer ) {
		$host = getenv( 'CDF_SMTP_HOST' );
		if ( ! $host ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host        = $host;
		$mailer->Port        = (int) ( getenv( 'CDF_SMTP_PORT' ) ?: 25 );
		$mailer->SMTPAutoTLS = false;
		$user                = getenv( 'CDF_SMTP_USER' );
		if ( $user ) {
			$mailer->SMTPAuth   = true;
			$mailer->Username   = $user;
			$mailer->Password   = (string) getenv( 'CDF_SMTP_PASS' );
			$mailer->SMTPSecure = getenv( 'CDF_SMTP_SECURE' ) ?: 'tls';
			$mailer->SMTPAutoTLS = true;
		}
	}
);

add_filter(
	'wp_mail_from_name',
	static fn() => cdf_business( 'brand' )
);

// ---------------------------------------------------------------- staging

/**
 * Fora de produção: noindex em todas as respostas (meta e cabeçalho), sem bloquear o robots.txt,
 * para que os motores de pesquisa consigam ler a diretiva.
 */
add_filter(
	'wp_robots',
	static function ( array $robots ) {
		if ( ! cdf_is_production() ) {
			return array(
				'noindex'  => true,
				'nofollow' => true,
			);
		}
		return $robots;
	},
	999
);

add_action(
	'send_headers',
	static function () {
		if ( ! cdf_is_production() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}
);

/**
 * Restrição opcional de acesso ao staging: definir CDF_STAGING_KEY (constante ou variável de ambiente).
 * O acesso concede-se uma vez com ?acesso=CHAVE e fica num cookie assinado.
 */
add_action(
	'init',
	static function () {
		$key = defined( 'CDF_STAGING_KEY' ) ? CDF_STAGING_KEY : getenv( 'CDF_STAGING_KEY' );
		if ( ! $key || cdf_is_production() || is_user_logged_in() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( str_starts_with( $uri, '/wp-login.php' ) || str_starts_with( $uri, '/wp-admin' ) ) {
			return;
		}
		$token = hash_hmac( 'sha256', 'cdf-staging', $key . wp_salt( 'auth' ) );
		if ( isset( $_COOKIE['cdf_staging'] ) && hash_equals( $token, (string) $_COOKIE['cdf_staging'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chave de acesso partilhada, não é uma ação.
		if ( isset( $_GET['acesso'] ) && hash_equals( (string) $key, (string) wp_unslash( $_GET['acesso'] ) ) ) {
			setcookie( 'cdf_staging', $token, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', '', is_ssl(), true );
			wp_safe_redirect( remove_query_arg( 'acesso' ) );
			exit;
		}
		status_header( 401 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Acesso reservado</title><p style="font:16px/1.5 system-ui;margin:4rem auto;max-width:32rem">Ambiente de demonstração com acesso reservado.</p>';
		exit;
	},
	0
);

// ---------------------------------------------------------------- imagens

/**
 * Tamanhos intermédios em WebP (o original JPEG mantém-se como alternativa).
 */
add_filter(
	'image_editor_output_format',
	static function ( array $formats ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
);

add_filter( 'wp_editor_set_quality', static fn( $q, $mime ) => 'image/webp' === $mime ? 78 : $q, 10, 2 );

// Sem tamanhos que o tema não usa.
add_filter(
	'intermediate_image_sizes_advanced',
	static function ( array $sizes ) {
		unset( $sizes['1536x1536'], $sizes['2048x2048'], $sizes['medium_large'] );
		return $sizes;
	}
);
add_filter( 'big_image_size_threshold', static fn() => 2400 );

// ---------------------------------------------------------------- endurecimento

add_filter( 'xmlrpc_enabled', '__return_false' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'the_generator', '__return_empty_string' );

// Sem enumeração de autores pela API REST para visitantes.
add_filter(
	'rest_endpoints',
	static function ( array $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);

add_action(
	'template_redirect',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['author'] ) && ! is_admin() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

add_action(
	'send_headers',
	static function () {
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}
);

/**
 * Páginas pessoais e de sessão nunca devem ir para cache partilhada.
 * O WooCommerce já envia cabeçalhos no-cache para carrinho/checkout/conta; reforçamos para o orçamento.
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_page( array( 'pedido-de-orcamento', 'contactos' ) ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			nocache_headers();
		}
	},
	5
);
