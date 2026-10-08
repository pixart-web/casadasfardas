<?php
/**
 * SEO técnico — um único sistema responsável por títulos, descrição, canonical, robots,
 * Open Graph, dados estruturados da loja e sitemap (sitemaps nativos do WordPress).
 *
 * Os dados estruturados de produto e breadcrumbs são os do WooCommerce, ajustados em pricing.php
 * (sem ofertas quando não há preço visível; marca real; sem avaliações).
 * Se for instalado um plugin de SEO (Yoast, Rank Math, SEOPress), este módulo desliga-se
 * automaticamente para evitar duplicações.
 */

defined( 'ABSPATH' ) || exit;

function cdf_seo_enabled(): bool {
	return ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'RankMath' ) && ! defined( 'SEOPRESS_VERSION' );
}

add_action( 'plugins_loaded', static function () {
	if ( ! cdf_seo_enabled() ) {
		return;
	}
	remove_action( 'wp_head', 'rel_canonical' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	add_action( 'wp_head', 'cdf_seo_head', 1 );
	add_filter( 'document_title_parts', 'cdf_seo_title_parts' );
	add_filter( 'document_title_separator', static fn() => '|' );
	add_filter( 'wp_robots', 'cdf_seo_robots', 20 );
	add_filter( 'wp_sitemaps_add_provider', 'cdf_seo_sitemap_providers', 10, 2 );
	add_filter( 'wp_sitemaps_post_types', 'cdf_seo_sitemap_post_types' );
	add_filter( 'wp_sitemaps_taxonomies', 'cdf_seo_sitemap_taxonomies' );
	add_filter( 'wp_sitemaps_posts_query_args', 'cdf_seo_sitemap_posts_args', 10, 2 );
	add_filter( 'wp_sitemaps_taxonomies_query_args', static function ( array $args ) {
		$args['hide_empty'] = true;
		return $args;
	} );
}, 20 );

/**
 * Parâmetros que não geram páginas indexáveis (filtros, ordenação, ações).
 */
function cdf_seo_has_variant_params(): bool {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	foreach ( array_keys( $_GET ) as $key ) {
		$key = (string) $key;
		if ( str_starts_with( $key, 'filter_' ) || str_starts_with( $key, 'query_type_' ) || in_array( $key, array( 'marca', 'orderby', 'min_price', 'max_price', 'add-to-cart', 'rating_filter' ), true ) ) {
			return true;
		}
	}
	// phpcs:enable
	return false;
}

/**
 * URL canónico do pedido atual (ou '' quando a página não deve ter canonical).
 */
function cdf_seo_canonical(): string {
	if ( is_404() || is_search() ) {
		return '';
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$url = wc_get_page_permalink( 'shop' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$url = get_term_link( get_queried_object() );
		if ( is_wp_error( $url ) ) {
			return '';
		}
	} elseif ( is_singular() ) {
		$url = get_permalink( get_queried_object_id() );
		// Páginas de produto: a cor escolhida por parâmetro não cria uma página nova.
	} elseif ( is_home() ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' );
	} else {
		return '';
	}
	if ( $paged > 1 ) {
		$url = trailingslashit( $url ) . user_trailingslashit( 'page/' . $paged, 'paged' );
	}
	return (string) $url;
}

function cdf_seo_description(): string {
	$desc = '';
	if ( is_front_page() ) {
		$desc = get_option( 'cdf_home_description' ) ?: get_bloginfo( 'description' );
	} elseif ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product ) {
			$desc  = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
			$desc  = cdf_seo_trim( $desc, 120 );
			$extra = array();
			if ( $product->get_sku() ) {
				$extra[] = 'Ref. ' . $product->get_sku();
			}
			$brands = get_the_terms( $product->get_id(), 'product_brand' );
			if ( $brands && ! is_wp_error( $brands ) ) {
				$extra[] = $brands[0]->name;
			}
			$desc = trim( $desc . ( $extra ? ' ' . implode( ' · ', $extra ) . '.' : '' ) );
		}
	} elseif ( is_tax() || is_category() ) {
		$term = get_queried_object();
		$desc = (string) get_term_meta( $term->term_id, 'cdf_intro', true );
		if ( ! $desc ) {
			$desc = wp_strip_all_tags( term_description( $term ) );
		}
		if ( 'product_cat' === $term->taxonomy && mb_strlen( $desc ) < 90 ) {
			$count    = function_exists( 'cdf_term_product_count' ) ? cdf_term_product_count( $term ) : (int) $term->count;
			$children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true, 'fields' => 'names', 'menu_order' => 'ASC' ) );
			$extra    = sprintf( '%d artigos', $count );
			if ( $children && ! is_wp_error( $children ) ) {
				$names  = array_map( 'mb_strtolower', array_slice( $children, 0, 5 ) );
				$extra .= ': ' . implode( ', ', $names ) . ( count( $children ) > 5 ? ' e mais' : '' );
			}
			$desc = trim( ( $desc ? rtrim( $desc, '.' ) . '. ' : $term->name . ' — ' ) . $extra . '. Peça orçamento com bordado ou estampagem.' );
		}
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$desc = 'Vestuário profissional, calçado de segurança e equipamentos de proteção individual. Pesquise por nome ou referência e peça orçamento para a sua empresa.';
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) );
	}
	return cdf_seo_trim( (string) $desc, 158 );
}

function cdf_seo_trim( string $text, int $max ): string {
	$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $max - 1 );
	$cut = preg_replace( '/\s+\S*$/u', '', $cut );
	return rtrim( $cut, ',;:.- ' ) . '…';
}

function cdf_seo_title_parts( array $parts ): array {
	if ( is_front_page() ) {
		$parts['title']   = cdf_business( 'brand' );
		$parts['tagline'] = get_option( 'cdf_home_title_tagline' ) ?: 'Fardas, vestuário de trabalho e EPI';
		return $parts;
	}
	if ( is_tax( 'product_cat' ) ) {
		$term = get_queried_object();
		if ( $term->parent ) {
			$parent = get_term( $term->parent, 'product_cat' );
			if ( $parent && ! is_wp_error( $parent ) && 0 !== strcasecmp( $parent->name, $term->name ) ) {
				$parts['title'] = $term->name . ' — ' . $parent->name;
			}
		}
	}
	if ( is_search() ) {
		$parts['title'] = sprintf( 'Pesquisa: %s', get_search_query() );
	}
	$parts['site'] = cdf_business( 'brand' );
	return $parts;
}

function cdf_seo_robots( array $robots ): array {
	$noindex = is_search()
		|| cdf_seo_has_variant_params()
		|| ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) )
		|| is_page( array( 'pedido-de-orcamento' ) ) && ! empty( $_GET['enviado'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $noindex ) {
		unset( $robots['index'], $robots['max-image-preview'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
	} else {
		$robots['max-image-preview'] = 'large';
	}
	return $robots;
}

function cdf_seo_image(): array {
	$id = 0;
	if ( is_singular() ) {
		$id = (int) get_post_thumbnail_id( get_queried_object_id() );
	} elseif ( is_tax( 'product_cat' ) && function_exists( 'cdf_term_image_id' ) ) {
		$id = cdf_term_image_id( get_queried_object() );
	}
	if ( ! $id ) {
		$id = (int) get_option( 'cdf_default_share_image' );
	}
	if ( $id ) {
		$src = wp_get_attachment_image_src( $id, 'woocommerce_single' ) ?: wp_get_attachment_image_src( $id, 'full' );
		if ( $src ) {
			return array( 'url' => $src[0], 'width' => $src[1], 'height' => $src[2], 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		}
	}
	return array();
}

function cdf_seo_head() {
	$canonical = cdf_seo_canonical();
	$desc      = cdf_seo_description();
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	// Páginas com filtros/ordenação apontam para a versão sem parâmetros.
	if ( $canonical ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}
	$is_product = function_exists( 'is_product' ) && is_product();
	$og         = array(
		'og:locale'    => 'pt_PT',
		'og:site_name' => cdf_business( 'brand' ),
		'og:type'      => $is_product ? 'product' : ( is_singular() && ! is_front_page() ? 'article' : 'website' ),
		'og:title'     => wp_get_document_title(),
		'og:url'       => $canonical ?: home_url( add_query_arg( array() ) ),
	);
	if ( $desc ) {
		$og['og:description'] = $desc;
	}
	$img = cdf_seo_image();
	if ( $img ) {
		$og['og:image']        = $img['url'];
		$og['og:image:width']  = (string) $img['width'];
		$og['og:image:height'] = (string) $img['height'];
		if ( $img['alt'] ) {
			$og['og:image:alt'] = $img['alt'];
		}
	}
	foreach ( $og as $property => $content ) {
		echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";

	// Paginação: ligações para página anterior/seguinte com URL próprio.
	global $wp_query;
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( ( is_tax() || ( function_exists( 'is_shop' ) && is_shop() ) ) && ! cdf_seo_has_variant_params() && $wp_query->max_num_pages > 1 ) {
		if ( $paged > 1 ) {
			echo '<link rel="prev" href="' . esc_url( get_pagenum_link( $paged - 1 ) ) . '">' . "\n";
		}
		if ( $paged < $wp_query->max_num_pages ) {
			echo '<link rel="next" href="' . esc_url( get_pagenum_link( $paged + 1 ) ) . '">' . "\n";
		}
	}

	if ( is_front_page() || is_page( array( 'contactos', 'sobre' ) ) ) {
		cdf_seo_print_jsonld( cdf_seo_store_schema() );
	}
}

/**
 * Loja física e organização, apenas com dados confirmados.
 */
function cdf_seo_store_schema(): array {
	$b      = cdf_business();
	$logo   = function_exists( 'cdf_theme_logo_url' ) ? cdf_theme_logo_url() : '';
	$emails = cdf_business_lines( 'emails' );
	$schema = array(
		'@context'  => 'https://schema.org',
		'@type'     => array( 'ClothingStore' ),
		'@id'       => home_url( '/#loja' ),
		'name'      => $b['brand'],
		'legalName' => $b['legal_name'],
		'url'       => home_url( '/' ),
		'telephone' => '+351' . preg_replace( '/\D+/', '', $b['phone'] ),
		'email'     => $emails[0] ?? '',
		'address'   => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $b['street'],
			'postalCode'      => $b['postcode'],
			'addressLocality' => $b['locality'],
			'addressRegion'   => $b['region'],
			'addressCountry'  => $b['country'],
		),
		'areaServed' => array( '@type' => 'Country', 'name' => 'Portugal' ),
		'currenciesAccepted' => 'EUR',
	);
	if ( $logo ) {
		$schema['logo']  = $logo;
		$schema['image'] = $logo;
	}
	if ( $b['hours_spec'] ) {
		$schema['openingHours'] = array_map( 'trim', explode( ',', preg_replace( '/^(\S+)\s+/', '', $b['hours_spec'] ) ) );
		$days                   = strtok( $b['hours_spec'], ' ' );
		$schema['openingHours'] = array_map( static fn( $range ) => $days . ' ' . $range, $schema['openingHours'] );
	}
	if ( $b['nif'] ) {
		$schema['vatID'] = 'PT' . $b['nif'];
	}
	$same = array_values( array_filter( array( $b['facebook'], $b['instagram'] ) ) );
	if ( $same ) {
		$schema['sameAs'] = $same;
	}
	return array_filter( $schema );
}

function cdf_seo_print_jsonld( array $data ) {
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
}

// ---------------------------------------------------------------- sitemap

function cdf_seo_sitemap_providers( $provider, string $name ) {
	return 'users' === $name ? false : $provider;
}

function cdf_seo_sitemap_post_types( array $types ): array {
	unset( $types['attachment'] );
	if ( ! wp_count_posts( 'post' )->publish ) {
		unset( $types['post'] );
	}
	return $types;
}

function cdf_seo_sitemap_taxonomies( array $taxonomies ): array {
	foreach ( array_keys( $taxonomies ) as $tax ) {
		if ( ! in_array( $tax, array( 'product_cat', 'product_brand' ), true ) ) {
			unset( $taxonomies[ $tax ] );
		}
	}
	return $taxonomies;
}

/**
 * Exclui páginas de sessão (carrinho, checkout, conta) e produtos ocultos do sitemap.
 */
function cdf_seo_sitemap_posts_args( array $args, string $post_type ): array {
	if ( 'page' === $post_type && function_exists( 'wc_get_page_id' ) ) {
		$exclude = array_filter( array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ) ) );
		$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), $exclude );
	}
	if ( 'product' === $post_type ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-search', 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		);
	}
	return $args;
}
