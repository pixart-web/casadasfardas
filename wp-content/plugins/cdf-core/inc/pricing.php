<?php
/**
 * Política de preços, IVA e disponibilidade.
 *
 * - Modo «quote»: nenhum preço visível nem compra; todos os artigos seguem para orçamento.
 * - Modo «shop»: artigos com preço podem ser comprados; artigos sem preço nunca são vendidos por zero.
 * Preços introduzidos SEM IVA (como na loja antiga: «A estes valores deverá acrescer IVA»),
 * IVA calculado pelo WooCommerce com a taxa configurada.
 * Stock desconhecido não equivale a stock disponível: os artigos importados ficam «sob confirmação».
 */

defined( 'ABSPATH' ) || exit;

function cdf_price_mode(): string {
	$mode = get_option( 'cdf_price_mode', 'quote' );
	return 'shop' === $mode ? 'shop' : 'quote';
}

/**
 * O artigo tem preço válido (> 0)?
 */
function cdf_product_has_price( WC_Product $product ): bool {
	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices();
		return ! empty( $prices['price'] ) && max( array_map( 'floatval', $prices['price'] ) ) > 0;
	}
	return (float) $product->get_price() > 0;
}

/**
 * O artigo deve ser pedido por orçamento (sem compra direta)?
 */
function cdf_is_quote_only( WC_Product $product ): bool {
	return 'quote' === cdf_price_mode() || ! cdf_product_has_price( $product );
}

add_filter(
	'woocommerce_is_purchasable',
	static function ( bool $purchasable, WC_Product $product ) {
		if ( 'quote' === cdf_price_mode() ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) || $product->is_type( 'simple' ) ) {
			return $purchasable && (float) $product->get_price() > 0;
		}
		return $purchasable;
	},
	20,
	2
);

add_filter( 'woocommerce_variation_is_purchasable', static function ( bool $p, WC_Product $v ) {
	return 'quote' === cdf_price_mode() ? false : ( $p && (float) $v->get_price() > 0 );
}, 20, 2 );

add_filter(
	'woocommerce_get_price_html',
	static function ( string $html, WC_Product $product ) {
		if ( 'quote' === cdf_price_mode() || ! cdf_product_has_price( $product ) ) {
			return '';
		}
		// O WooCommerce não acrescenta o sufixo (s/ IVA · c/ IVA) aos produtos variáveis: usa-se o da variação mais barata.
		if ( $product->is_type( 'variable' ) && ! str_contains( $html, 'woocommerce-price-suffix' ) ) {
			$prices = $product->get_variation_prices( true );
			$ids    = array_keys( $prices['price'] ?? array() );
			$min    = $ids ? wc_get_product( $ids[0] ) : null;
			if ( $min ) {
				$single = count( array_unique( $prices['price'] ) ) === 1;
				$html  .= $single ? $min->get_price_suffix() : ' <small class="woocommerce-price-suffix"><span class="cdf-vat">+ IVA</span></small>';
			}
		}
		return $html;
	},
	20,
	2
);

/**
 * Sem preço visível também não há preço nos dados estruturados.
 */
add_filter(
	'woocommerce_structured_data_product',
	static function ( array $markup, WC_Product $product ) {
		if ( cdf_is_quote_only( $product ) ) {
			unset( $markup['offers'] );
		}
		// Sem avaliações fabricadas.
		unset( $markup['aggregateRating'], $markup['review'] );
		$brands = get_the_terms( $product->get_id(), 'product_brand' );
		if ( $brands && ! is_wp_error( $brands ) ) {
			$markup['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brands[0]->name,
			);
		}
		return $markup;
	},
	20,
	2
);

/**
 * Disponibilidade: texto honesto para artigos sem stock confirmado.
 */
add_filter(
	'woocommerce_get_availability_text',
	static function ( string $text, WC_Product $product ) {
		if ( 'onbackorder' === $product->get_stock_status() ) {
			return __( 'Disponibilidade confirmada após a encomenda', 'cdf-core' );
		}
		return $text;
	},
	20,
	2
);

add_filter(
	'woocommerce_get_availability_class',
	static function ( string $class, WC_Product $product ) {
		return 'onbackorder' === $product->get_stock_status() ? $class . ' cdf-availability-confirm' : $class;
	},
	20,
	2
);

/**
 * No carrinho, artigos «sob confirmação» não mostram o aviso genérico de backorder repetido.
 */
add_filter( 'woocommerce_cart_item_backorder_notification', '__return_empty_string' );

/**
 * Nota fiscal clara junto ao total do carrinho e checkout.
 */
add_action(
	'woocommerce_review_order_before_order_total',
	'cdf_vat_note_row'
);
add_action(
	'woocommerce_cart_totals_before_order_total',
	'cdf_vat_note_row'
);

function cdf_vat_note_row() {
	echo '<tr class="cdf-vat-note"><td colspan="2">' . esc_html__( 'Preços dos artigos sem IVA; o IVA é calculado à taxa legal em vigor e somado ao total.', 'cdf-core' ) . '</td></tr>';
}

/**
 * Termos e condições: o checkout só exige a aceitação quando a página estiver publicada
 * (enquanto estiver «pendente de validação», não há ligação para uma página inexistente).
 */
add_filter(
	'woocommerce_get_terms_page_id',
	static function ( $page_id ) {
		return ( $page_id && 'publish' === get_post_status( (int) $page_id ) ) ? $page_id : 0;
	}
);

/**
 * Política de privacidade: só há ligação quando a página estiver publicada (evita ligações para 404).
 */
add_filter(
	'woocommerce_privacy_policy_page_id',
	static function ( $page_id ) {
		return ( $page_id && 'publish' === get_post_status( (int) $page_id ) ) ? $page_id : 0;
	}
);
