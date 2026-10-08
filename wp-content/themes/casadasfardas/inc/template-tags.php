<?php
/**
 * Funções de apresentação reutilizáveis.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dados da empresa (do plugin, com recurso mínimo se o plugin estiver inativo).
 */
function cdf_theme_business( string $key ): string {
	if ( function_exists( 'cdf_business' ) ) {
		return (string) cdf_business( $key );
	}
	$fallback = array( 'brand' => get_bloginfo( 'name' ) );
	return $fallback[ $key ] ?? '';
}

function cdf_theme_logo_url(): string {
	return CDF_THEME_URI . '/assets/img/logo-casadasfardas.png';
}

/**
 * Logótipo original (PNG 155×100 da loja antiga, sem alterações).
 */
function cdf_theme_logo( string $class = 'cdf-logo', bool $link = true ): string {
	$img = sprintf(
		'<img src="%1$s" width="155" height="100" alt="%2$s" class="cdf-logo__img" decoding="async">',
		esc_url( cdf_theme_logo_url() ),
		esc_attr( cdf_theme_business( 'brand' ) )
	);
	if ( ! $link ) {
		return '<span class="' . esc_attr( $class ) . '">' . $img . '</span>';
	}
	return sprintf( '<a class="%1$s" href="%2$s" rel="home">%3$s</a>', esc_attr( $class ), esc_url( home_url( '/' ) ), $img );
}

/**
 * Ícones SVG inline (traço 1.75, 24×24), sem fontes de ícones.
 */
function cdf_svg( string $name, int $size = 22, string $class = '' ): string {
	static $paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'cart'     => '<path d="M3 4h2l2.2 10.2a2 2 0 002 1.6h7.7a2 2 0 002-1.5L20.5 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.2-4 4.3-6 8-6s6.8 2 8 6"/>',
		'quote'    => '<path d="M8 3h8l4 4v12a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h2z"/><path d="M15 3v5h5M8 12h8M8 16h5"/>',
		'phone'    => '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 005 5L14 13l5 2v3a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0114 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
		'chevron'  => '<path d="M9 6l6 6-6 6"/>',
		'down'     => '<path d="M6 9l6 6 6-6"/>',
		'back'     => '<path d="M15 6l-6 6 6 6"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
		'needle'   => '<path d="M5 19L19 5M15 5h4v4"/><path d="M4 14c2 0 3 1 3 3"/>',
		'truck'    => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
		'store'    => '<path d="M4 9l1.5-5h13L20 9M4 9v11h16V9M4 9h16M9 20v-6h6v6"/>',
		'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
		'check'    => '<path d="M5 12.5l4.2 4.2L19 7"/>',
		'zoom'     => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M11 8v6M8 11h6"/>',
		'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
		'shirt'    => '<path d="M8 3l-5 3 2 4 2-1v12h10V9l2 1 2-4-5-3c0 2-1.8 3-4 3S8 5 8 3z"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="cdf-svg %4$s" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>%3$s',
		$size,
		$paths[ $name ],
		'',
		esc_attr( $class )
	);
}

/**
 * Migalhas para páginas de conteúdo (o WooCommerce trata das suas, com dados estruturados).
 */
function cdf_page_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}
	$items = array( array( home_url( '/' ), __( 'Início', 'casadasfardas' ) ) );
	if ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $ancestor ) {
			$items[] = array( get_permalink( $ancestor ), get_the_title( $ancestor ) );
		}
	}
	$items[] = array( '', is_404() ? __( 'Página não encontrada', 'casadasfardas' ) : get_the_title() );
	echo '<nav class="cdf-breadcrumbs" aria-label="' . esc_attr__( 'Localização', 'casadasfardas' ) . '"><ol>';
	foreach ( $items as $i => $item ) {
		$last = $i === count( $items ) - 1;
		echo '<li>' . ( $last ? '<span aria-current="page">' . esc_html( $item[1] ) . '</span>' : '<a href="' . esc_url( $item[0] ) . '">' . esc_html( $item[1] ) . '</a>' ) . '</li>';
	}
	echo '</ol></nav>';
}

/**
 * Número de artigos no pedido de orçamento e no carrinho (renderizado no servidor).
 */
function cdf_theme_quote_count(): int {
	return function_exists( 'cdf_quote_count' ) ? cdf_quote_count() : 0;
}

function cdf_theme_cart_count(): int {
	return ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function cdf_theme_shop_mode(): bool {
	return function_exists( 'cdf_price_mode' ) ? 'shop' === cdf_price_mode() : true;
}

/**
 * Ligações de menus do rodapé (só páginas publicadas).
 */
function cdf_footer_menu( string $location, string $title ) {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}
	echo '<nav class="cdf-footer__col" aria-label="' . esc_attr( $title ) . '"><h2 class="cdf-footer__title">' . esc_html( $title ) . '</h2>';
	wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'menu_class'     => 'cdf-footer__list',
			'depth'          => 1,
			'fallback_cb'    => false,
		)
	);
	echo '</nav>';
}

/**
 * Esconde do menu ligações para páginas não publicadas (ex.: páginas legais pendentes de validação).
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( array $items ) {
		return array_values(
			array_filter(
				$items,
				static function ( $item ) {
					if ( 'post_type' === $item->type && $item->object_id ) {
						return 'publish' === get_post_status( (int) $item->object_id );
					}
					if ( 'taxonomy' === $item->type && $item->object_id ) {
						$term = get_term( (int) $item->object_id, $item->object );
						if ( ! $term || is_wp_error( $term ) ) {
							return false;
						}
						// Categorias sem artigos (8 vinham vazias da loja antiga) não aparecem na navegação.
						return 'product_cat' !== $term->taxonomy || ! function_exists( 'cdf_term_product_count' ) || cdf_term_product_count( $term ) > 0;
					}
					return true;
				}
			)
		);
	},
	5
);
