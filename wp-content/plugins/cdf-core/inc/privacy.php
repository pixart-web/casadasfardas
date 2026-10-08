<?php
/**
 * Privacidade: exportação e eliminação de pedidos de orçamento (ferramentas nativas do WordPress)
 * e texto sugerido para a política de privacidade.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_privacy_personal_data_exporters', static function ( array $exporters ) {
	$exporters['cdf-quotes'] = array(
		'exporter_friendly_name' => __( 'Pedidos de orçamento e contacto', 'cdf-core' ),
		'callback'               => 'cdf_privacy_export',
	);
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', static function ( array $erasers ) {
	$erasers['cdf-quotes'] = array(
		'eraser_friendly_name' => __( 'Pedidos de orçamento e contacto', 'cdf-core' ),
		'callback'             => 'cdf_privacy_erase',
	);
	return $erasers;
} );

function cdf_privacy_quotes_for( string $email ): array {
	return get_posts(
		array(
			'post_type'      => 'cdf_quote',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_key'       => '_cdf_email', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => strtolower( $email ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

function cdf_privacy_export( string $email ): array {
	$data = array();
	foreach ( cdf_privacy_quotes_for( $email ) as $id ) {
		$data[] = array(
			'group_id'    => 'cdf-quotes',
			'group_label' => __( 'Pedidos de orçamento e contacto', 'cdf-core' ),
			'item_id'     => 'cdf-quote-' . $id,
			'data'        => array(
				array( 'name' => __( 'Referência', 'cdf-core' ), 'value' => get_post_meta( $id, '_cdf_ref', true ) ),
				array( 'name' => __( 'Data', 'cdf-core' ), 'value' => get_the_date( 'Y-m-d H:i', $id ) ),
				array( 'name' => __( 'Conteúdo', 'cdf-core' ), 'value' => cdf_quote_summary_text( $id ) ),
			),
		);
	}
	return array( 'data' => $data, 'done' => true );
}

function cdf_privacy_erase( string $email ): array {
	$removed = 0;
	foreach ( cdf_privacy_quotes_for( $email ) as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			++$removed;
		}
	}
	return array( 'items_removed' => $removed, 'items_retained' => 0, 'messages' => array(), 'done' => true );
}

add_action( 'admin_init', static function () {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$days = (int) cdf_option( 'quote_retention_days', 365 );
	wp_add_privacy_policy_content(
		'Casa das Fardas',
		'<p>' . sprintf(
			/* translators: %d dias */
			esc_html__( 'Os pedidos de orçamento e de contacto enviados pelo site (nome, empresa, email, telefone, NIF se indicado, artigos, observações e o ficheiro de logótipo anexado) ficam guardados no sistema da loja para resposta e acompanhamento do pedido, e são apagados automaticamente ao fim de %d dias. O logótipo é guardado fora da área pública do site e só é acessível à equipa autorizada. Estes dados não são cedidos a terceiros nem usados para outros fins. [A VALIDAR pelo responsável pelo tratamento]', 'cdf-core' ),
			$days
		) . '</p>'
	);
} );
