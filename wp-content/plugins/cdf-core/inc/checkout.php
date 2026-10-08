<?php
/**
 * Checkout: NIF para faturação (opcional), validado pelo dígito de controlo português.
 * Guardado como meta da encomenda pelas APIs do WooCommerce (compatível com HPOS).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valida um NIF/NIPC português (9 dígitos, dígito de controlo módulo 11).
 */
function cdf_valid_pt_nif( string $nif ): bool {
	$nif = preg_replace( '/\D+/', '', $nif );
	if ( 9 !== strlen( $nif ) || ! in_array( $nif[0], array( '1', '2', '3', '5', '6', '7', '8', '9' ), true ) ) {
		return false;
	}
	$sum = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$sum += (int) $nif[ $i ] * ( 9 - $i );
	}
	$check = 11 - ( $sum % 11 );
	$check = $check >= 10 ? 0 : $check;
	return (int) $nif[8] === $check;
}

add_filter(
	'woocommerce_billing_fields',
	static function ( array $fields ) {
		$fields['billing_nif'] = array(
			'label'        => __( 'NIF (para fatura com contribuinte)', 'cdf-core' ),
			'required'     => false,
			'class'        => array( 'form-row-wide' ),
			'priority'     => 32,
			'autocomplete' => 'off',
			'input_class'  => array( 'input-text' ),
			'custom_attributes' => array(
				'inputmode' => 'numeric',
				'maxlength' => '11',
			),
		);
		return $fields;
	}
);

add_action(
	'woocommerce_after_checkout_validation',
	static function ( array $data, WP_Error $errors ) {
		$nif = trim( (string) ( $data['billing_nif'] ?? '' ) );
		if ( '' !== $nif && ! cdf_valid_pt_nif( $nif ) ) {
			$errors->add( 'billing_nif', __( 'O NIF indicado não é válido. Confirme os 9 dígitos ou deixe o campo vazio.', 'cdf-core' ), array( 'id' => 'billing_nif' ) );
		}
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_create_order',
	static function ( WC_Order $order, array $data ) {
		if ( ! empty( $data['billing_nif'] ) ) {
			$order->update_meta_data( '_billing_nif', preg_replace( '/\D+/', '', (string) $data['billing_nif'] ) );
		}
	},
	10,
	2
);

add_action(
	'woocommerce_admin_order_data_after_billing_address',
	static function ( WC_Order $order ) {
		$nif = $order->get_meta( '_billing_nif' );
		if ( $nif ) {
			echo '<p><strong>' . esc_html__( 'NIF', 'cdf-core' ) . ':</strong> ' . esc_html( $nif ) . '</p>';
		}
	}
);

add_filter(
	'woocommerce_email_order_meta_fields',
	static function ( array $fields, bool $sent_to_admin, WC_Order $order ) {
		$nif = $order->get_meta( '_billing_nif' );
		if ( $nif ) {
			$fields['billing_nif'] = array(
				'label' => __( 'NIF', 'cdf-core' ),
				'value' => $nif,
			);
		}
		return $fields;
	},
	10,
	3
);

add_action(
	'woocommerce_order_details_after_customer_details',
	static function ( WC_Order $order ) {
		$nif = $order->get_meta( '_billing_nif' );
		if ( $nif ) {
			echo '<p class="cdf-order-nif">' . esc_html__( 'NIF', 'cdf-core' ) . ': ' . esc_html( $nif ) . '</p>';
		}
	}
);
