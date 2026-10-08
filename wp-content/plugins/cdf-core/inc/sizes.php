<?php
/**
 * Tamanhos.
 *
 * A loja antiga publica os tamanhos como texto nas especificações (ex.: «XS, S, M, L, XL, XXL» ou «35 - 48»)
 * e não tem combinações cor × tamanho com stock. Para não gerar combinações não confirmadas:
 * - as cores são variações reais (derivados da origem, com referência e foto próprias);
 * - o tamanho é uma escolha do cliente validada contra a lista publicada (_cdf_sizes),
 *   guardada na linha do carrinho/encomenda e confirmada com a disponibilidade.
 */

defined( 'ABSPATH' ) || exit;

function cdf_product_sizes( WC_Product $product ): array {
	$id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	$sizes = get_post_meta( $id, '_cdf_sizes', true );
	return is_array( $sizes ) ? array_values( array_filter( array_map( 'strval', $sizes ) ) ) : array();
}

/**
 * Campo de tamanho no formulário de compra (antes do botão).
 */
add_action(
	'woocommerce_before_add_to_cart_button',
	static function () {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		cdf_render_size_field( $product );
	}
);

function cdf_render_size_field( WC_Product $product, string $context = 'cart' ) {
	$sizes = cdf_product_sizes( $product );
	if ( ! $sizes ) {
		return;
	}
	$id       = 'cdf-size-' . $context . '-' . $product->get_id();
	$selected = isset( $_REQUEST['cdf_size'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cdf_size'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="cdf-size-field">
		<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Tamanho', 'cdf-core' ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" name="cdf_size" <?php echo 'cart' === $context ? 'required' : ''; ?>>
			<option value=""><?php esc_html_e( 'Escolha o tamanho', 'cdf-core' ); ?></option>
			<?php foreach ( $sizes as $size ) : ?>
				<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $selected, $size ); ?>><?php echo esc_html( $size ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}

add_filter(
	'woocommerce_add_to_cart_validation',
	static function ( bool $passed, int $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $passed;
		}
		$sizes = cdf_product_sizes( $product );
		if ( ! $sizes ) {
			return $passed;
		}
		$size = isset( $_REQUEST['cdf_size'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cdf_size'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $size ) {
			wc_add_notice( __( 'Escolha um tamanho antes de adicionar ao carrinho.', 'cdf-core' ), 'error' );
			return false;
		}
		if ( ! in_array( $size, $sizes, true ) ) {
			wc_add_notice( __( 'O tamanho escolhido não está disponível para este artigo.', 'cdf-core' ), 'error' );
			return false;
		}
		return $passed;
	},
	10,
	2
);

add_filter(
	'woocommerce_add_cart_item_data',
	static function ( array $data, int $product_id ) {
		if ( isset( $_REQUEST['cdf_size'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$size = sanitize_text_field( wp_unslash( $_REQUEST['cdf_size'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( '' !== $size && in_array( $size, cdf_product_sizes( wc_get_product( $product_id ) ), true ) ) {
				$data['cdf_size'] = $size;
			}
		}
		return $data;
	},
	10,
	2
);

add_filter(
	'woocommerce_get_item_data',
	static function ( array $item_data, array $cart_item ) {
		if ( ! empty( $cart_item['cdf_size'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Tamanho', 'cdf-core' ),
				'value' => esc_html( $cart_item['cdf_size'] ),
			);
		}
		return $item_data;
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_create_order_line_item',
	static function ( WC_Order_Item_Product $item, string $key, array $values ) {
		if ( ! empty( $values['cdf_size'] ) ) {
			$item->add_meta_data( __( 'Tamanho', 'cdf-core' ), $values['cdf_size'], true );
		}
	},
	10,
	3
);

/**
 * Nas listagens, artigos com tamanho obrigatório abrem a ficha em vez de «adicionar» diretamente.
 */
add_filter(
	'woocommerce_product_supports',
	static function ( bool $supports, string $feature, WC_Product $product ) {
		if ( 'ajax_add_to_cart' === $feature && cdf_product_sizes( $product ) ) {
			return false;
		}
		return $supports;
	},
	10,
	3
);

add_filter(
	'woocommerce_product_add_to_cart_url',
	static function ( string $url, WC_Product $product ) {
		return ( $product->is_type( 'simple' ) && cdf_product_sizes( $product ) ) ? $product->get_permalink() : $url;
	},
	10,
	2
);

add_filter(
	'woocommerce_product_add_to_cart_text',
	static function ( string $text, WC_Product $product ) {
		if ( cdf_is_quote_only( $product ) ) {
			return __( 'Ver e pedir orçamento', 'cdf-core' );
		}
		if ( $product->is_type( 'simple' ) && cdf_product_sizes( $product ) ) {
			return __( 'Escolher tamanho', 'cdf-core' );
		}
		return $text;
	},
	10,
	2
);
