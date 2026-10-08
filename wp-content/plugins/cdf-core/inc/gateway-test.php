<?php
/**
 * Método de pagamento de TESTE — só existe fora de produção.
 *
 * Permite demonstrar o percurso completo com encomendas reais do WooCommerce (aprovada e recusada)
 * sem credenciais de um fornecedor de pagamentos e sem movimentar dinheiro.
 * Em produção substitui-se pelo(s) gateway(s) escolhido(s) pelo cliente (ver LAUNCH-CHECKLIST.md).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) || 'production' === wp_get_environment_type() ) {
		return;
	}

	class CDF_Gateway_Test extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'cdf_test';
			$this->method_title       = 'Pagamento de teste (staging)';
			$this->method_description = 'Simula pagamentos aprovados e recusados. Não existe em produção.';
			$this->has_fields         = true;
			$this->supports           = array( 'products' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title       = $this->get_option( 'title', 'Pagamento de teste' );
			$this->description = $this->get_option( 'description' );
			$this->enabled     = $this->get_option( 'enabled', 'yes' );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => 'Ativar',
					'type'    => 'checkbox',
					'label'   => 'Ativar pagamento de teste',
					'default' => 'yes',
				),
				'title'       => array(
					'title'   => 'Título',
					'type'    => 'text',
					'default' => 'Pagamento de teste (demonstração)',
				),
				'description' => array(
					'title'   => 'Descrição',
					'type'    => 'textarea',
					'default' => 'Ambiente de demonstração: nenhum valor é cobrado. Escolha o resultado a simular.',
				),
			);
		}

		public function payment_fields() {
			echo '<p>' . esc_html( $this->description ) . '</p>';
			echo '<fieldset class="cdf-test-gateway"><legend class="screen-reader-text">Resultado a simular</legend>';
			echo '<label><input type="radio" name="cdf_test_outcome" value="approve" checked> Simular pagamento aprovado</label><br>';
			echo '<label><input type="radio" name="cdf_test_outcome" value="decline"> Simular pagamento recusado</label>';
			echo '</fieldset>';
		}

		public function process_payment( $order_id ) {
			$order   = wc_get_order( $order_id );
			$outcome = isset( $_POST['cdf_test_outcome'] ) ? sanitize_key( wp_unslash( $_POST['cdf_test_outcome'] ) ) : 'approve'; // phpcs:ignore WordPress.Security.NonceVerification -- o checkout do WooCommerce já valida o nonce.
			if ( 'decline' === $outcome ) {
				$order->update_status( 'failed', 'Pagamento de teste recusado (simulação).' );
				wc_add_notice( 'O pagamento foi recusado (simulação). Escolha outro resultado ou tente novamente.', 'error' );
				return array( 'result' => 'failure' );
			}
			$order->payment_complete( 'TESTE-' . strtoupper( wp_generate_password( 8, false ) ) );
			$order->add_order_note( 'Pagamento de teste aprovado (simulação, sem cobrança).' );
			WC()->cart->empty_cart();
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
	}

	add_filter( 'woocommerce_payment_gateways', static function ( array $gateways ) {
		$gateways[] = 'CDF_Gateway_Test';
		return $gateways;
	} );
}, 11 );
