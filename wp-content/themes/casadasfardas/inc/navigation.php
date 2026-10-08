<?php
/**
 * Navegação principal: mega menu (desktop) e acordeão (mobile) a partir do menu «primary»,
 * editável em Aspeto → Menus. Nível 1 = secções; nível 2 = grupos; nível 3 = ligações.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Árvore do menu principal.
 */
function cdf_menu_tree( string $location = 'primary' ): array {
	static $cache = array();
	if ( isset( $cache[ $location ] ) ) {
		return $cache[ $location ];
	}
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return $cache[ $location ] = array();
	}
	$items = wp_get_nav_menu_items( $locations[ $location ], array( 'update_post_term_cache' => false ) );
	if ( ! $items ) {
		return $cache[ $location ] = array();
	}
	_wp_menu_item_classes_by_context( $items );
	$items = apply_filters( 'wp_nav_menu_objects', $items, (object) array( 'theme_location' => $location ) );
	$by_id = array();
	foreach ( $items as $item ) {
		$by_id[ $item->ID ] = array( 'item' => $item, 'children' => array() );
	}
	$tree = array();
	foreach ( $items as $item ) {
		$parent = (int) $item->menu_item_parent;
		if ( $parent && isset( $by_id[ $parent ] ) ) {
			$by_id[ $parent ]['children'][] = &$by_id[ $item->ID ];
		} elseif ( ! $parent ) {
			$tree[] = &$by_id[ $item->ID ];
		}
	}
	return $cache[ $location ] = $tree;
}

/**
 * O item corresponde à página atual (ou é antecessor)?
 */
function cdf_menu_is_current( WP_Post $item ): bool {
	$classes = (array) $item->classes;
	return (bool) array_intersect( $classes, array( 'current-menu-item', 'current-menu-ancestor', 'current-menu-parent', 'current-product_cat-ancestor' ) );
}

/**
 * Imagem de destaque de um item (categoria → imagem da categoria; página → imagem destacada).
 */
function cdf_menu_item_image( WP_Post $item, string $size = 'woocommerce_thumbnail' ): string {
	$id = 0;
	if ( 'taxonomy' === $item->type && function_exists( 'cdf_term_image_id' ) ) {
		$term = get_term( (int) $item->object_id, $item->object );
		if ( $term && ! is_wp_error( $term ) ) {
			$id = cdf_term_image_id( $term );
		}
	} elseif ( 'post_type' === $item->type ) {
		$id = (int) get_post_thumbnail_id( (int) $item->object_id );
	}
	return $id ? wp_get_attachment_image( $id, $size, false, array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '240px' ) ) : '';
}

function cdf_menu_link_attrs( WP_Post $item ): string {
	$attrs = ' href="' . esc_url( $item->url ) . '"';
	if ( '_blank' === $item->target ) {
		$attrs .= ' target="_blank" rel="noopener"';
	}
	if ( cdf_menu_is_current( $item ) && in_array( 'current-menu-item', (array) $item->classes, true ) ) {
		$attrs .= ' aria-current="page"';
	}
	return $attrs;
}

function cdf_menu_external_mark( WP_Post $item ): string {
	return '_blank' === $item->target ? ' ' . cdf_svg( 'external', 14, 'cdf-svg--inline' ) . '<span class="screen-reader-text">' . esc_html__( '(abre noutra janela)', 'casadasfardas' ) . '</span>' : '';
}

/**
 * Navegação desktop com painéis.
 */
function cdf_render_primary_nav() {
	$tree = cdf_menu_tree();
	if ( ! $tree ) {
		return;
	}
	echo '<nav class="cdf-nav" aria-label="' . esc_attr__( 'Principal', 'casadasfardas' ) . '"><ul class="cdf-nav__list">';
	foreach ( $tree as $index => $node ) {
		$item    = $node['item'];
		$current = cdf_menu_is_current( $item ) ? ' is-current' : '';
		if ( ! $node['children'] ) {
			printf( '<li class="cdf-nav__item%s"><a class="cdf-nav__link"%s>%s%s</a></li>', esc_attr( $current ), cdf_menu_link_attrs( $item ), esc_html( $item->title ), cdf_menu_external_mark( $item ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			continue;
		}
		$panel_id = 'cdf-mega-' . $index;
		$cards    = ! array_filter( $node['children'], static fn( $c ) => (bool) $c['children'] );
		echo '<li class="cdf-nav__item has-panel' . esc_attr( $current ) . '" data-cdf-mega>';
		printf(
			'<button type="button" class="cdf-nav__link cdf-nav__trigger" aria-expanded="false" aria-controls="%1$s">%2$s%3$s</button>',
			esc_attr( $panel_id ),
			esc_html( $item->title ),
			cdf_svg( 'down', 16, 'cdf-nav__chev' ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
		echo '<div class="cdf-mega' . ( $cards ? ' cdf-mega--cards' : '' ) . '" id="' . esc_attr( $panel_id ) . '" hidden><div class="cdf-mega__inner">';
		echo '<div class="cdf-mega__head"><a class="cdf-mega__all"' . cdf_menu_link_attrs( $item ) . '>' . esc_html( sprintf( __( 'Ver tudo em %s', 'casadasfardas' ), $item->title ) ) . cdf_svg( 'arrow', 18 ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="cdf-mega__body">';
		if ( $cards ) {
			echo '<ul class="cdf-mega__cards">';
			foreach ( $node['children'] as $child ) {
				$c = $child['item'];
				printf(
					'<li><a class="cdf-mega__card"%1$s><span class="cdf-mega__card-media">%2$s</span><span class="cdf-mega__card-title">%3$s%4$s</span>%5$s</a></li>',
					cdf_menu_link_attrs( $c ), // phpcs:ignore WordPress.Security.EscapeOutput
					cdf_menu_item_image( $c ), // phpcs:ignore WordPress.Security.EscapeOutput
					esc_html( $c->title ),
					cdf_menu_external_mark( $c ), // phpcs:ignore WordPress.Security.EscapeOutput
					$c->description ? '<span class="cdf-mega__card-text">' . esc_html( $c->description ) . '</span>' : ''
				);
			}
			echo '</ul>';
		} else {
			echo '<div class="cdf-mega__groups">';
			foreach ( $node['children'] as $child ) {
				$c = $child['item'];
				echo '<div class="cdf-mega__group">';
				printf( '<a class="cdf-mega__heading"%s>%s%s</a>', cdf_menu_link_attrs( $c ), esc_html( $c->title ), cdf_menu_external_mark( $c ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( $child['children'] ) {
					$max = 7;
					echo '<ul class="cdf-mega__links">';
					foreach ( array_slice( $child['children'], 0, $max ) as $leaf ) {
						printf( '<li><a%s>%s%s</a></li>', cdf_menu_link_attrs( $leaf['item'] ), esc_html( $leaf['item']->title ), cdf_menu_external_mark( $leaf['item'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					if ( count( $child['children'] ) > $max ) {
						printf( '<li><a class="cdf-mega__more" href="%s">%s</a></li>', esc_url( $c->url ), esc_html( sprintf( __( 'Mais %d em %s', 'casadasfardas' ), count( $child['children'] ) - $max, $c->title ) ) );
					}
					echo '</ul>';
				}
				echo '</div>';
			}
			echo '</div>';
			$img = cdf_menu_item_image( $item, 'woocommerce_thumbnail' );
			if ( $img ) {
				echo '<a class="cdf-mega__feature"' . cdf_menu_link_attrs( $item ) . '><span class="cdf-mega__feature-media">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<span class="cdf-mega__feature-text">' . ( $item->description ? esc_html( $item->description ) : esc_html( $item->title ) ) . '</span>';
				echo '<span class="cdf-mega__feature-cta">' . esc_html__( 'Explorar', 'casadasfardas' ) . cdf_svg( 'arrow', 16 ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div></div></div></li>';
	}
	echo '</ul></nav>';
}

/**
 * Menu móvel em acordeão (details/summary nativos).
 */
function cdf_render_mobile_nav() {
	$tree = cdf_menu_tree();
	echo '<nav class="cdf-mnav" aria-label="' . esc_attr__( 'Principal (móvel)', 'casadasfardas' ) . '"><ul class="cdf-mnav__list">';
	foreach ( $tree as $node ) {
		$item = $node['item'];
		if ( ! $node['children'] ) {
			printf( '<li><a class="cdf-mnav__link"%s>%s%s</a></li>', cdf_menu_link_attrs( $item ), esc_html( $item->title ), cdf_menu_external_mark( $item ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			continue;
		}
		echo '<li><details class="cdf-mnav__group"><summary class="cdf-mnav__link">' . esc_html( $item->title ) . cdf_svg( 'down', 18, 'cdf-mnav__chev' ) . '</summary><ul class="cdf-mnav__sub">'; // phpcs:ignore WordPress.Security.EscapeOutput
		printf( '<li><a class="cdf-mnav__all"%s>%s</a></li>', cdf_menu_link_attrs( $item ), esc_html( sprintf( __( 'Ver tudo em %s', 'casadasfardas' ), $item->title ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $node['children'] as $child ) {
			$c = $child['item'];
			if ( ! $child['children'] ) {
				printf( '<li><a%s>%s%s</a></li>', cdf_menu_link_attrs( $c ), esc_html( $c->title ), cdf_menu_external_mark( $c ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				continue;
			}
			echo '<li><details class="cdf-mnav__group cdf-mnav__group--l2"><summary>' . esc_html( $c->title ) . cdf_svg( 'down', 16, 'cdf-mnav__chev' ) . '</summary><ul class="cdf-mnav__sub">'; // phpcs:ignore WordPress.Security.EscapeOutput
			printf( '<li><a class="cdf-mnav__all"%s>%s</a></li>', cdf_menu_link_attrs( $c ), esc_html( sprintf( __( 'Todos: %s', 'casadasfardas' ), $c->title ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( $child['children'] as $leaf ) {
				printf( '<li><a%s>%s</a></li>', cdf_menu_link_attrs( $leaf['item'] ), esc_html( $leaf['item']->title ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul></details></li>';
		}
		echo '</ul></details></li>';
	}
	echo '</ul></nav>';
}

/**
 * Formulário de pesquisa com sugestões (combobox ARIA; funciona sem JavaScript).
 */
function cdf_render_search( string $id = 'cdf-search' ) {
	$value = get_search_query();
	?>
	<form class="cdf-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-cdf-search>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Pesquisar artigos por nome ou referência', 'casadasfardas' ); ?></label>
		<input class="cdf-search__input" id="<?php echo esc_attr( $id ); ?>" type="search" name="s" value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php esc_attr_e( 'Pesquisar artigo, categoria ou referência', 'casadasfardas' ); ?>"
			autocomplete="off" spellcheck="false" enterkeyhint="search"
			role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-list">
		<input type="hidden" name="post_type" value="product">
		<button class="cdf-search__submit" type="submit"><?php echo cdf_svg( 'search', 20 ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( 'Pesquisar', 'casadasfardas' ); ?></span></button>
		<div class="cdf-search__panel" id="<?php echo esc_attr( $id ); ?>-list" role="listbox" aria-label="<?php esc_attr_e( 'Sugestões', 'casadasfardas' ); ?>" hidden></div>
		<p class="screen-reader-text" aria-live="polite" data-cdf-search-status></p>
	</form>
	<?php
}
