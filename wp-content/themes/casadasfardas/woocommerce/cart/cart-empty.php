<?php
/**
 * Carrinho vazio.
 *
 * Override do template do WooCommerce (versão de referência 7.0.1).
 *
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_cart_is_empty' );
?>
<div class="cdf-empty cdf-empty--cart">
	<?php echo cdf_svg( 'cart', 40 ); // phpcs:ignore ?>
	<h2><?php esc_html_e( 'O seu carrinho está vazio', 'casadasfardas' ); ?></h2>
	<p><?php esc_html_e( 'Explore o catálogo ou, se vai equipar uma equipa, envie-nos um pedido de orçamento com vários artigos.', 'casadasfardas' ); ?></p>
	<p class="cdf-empty__actions">
		<a class="cdf-btn cdf-btn--primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Explorar a loja', 'casadasfardas' ); ?></a>
		<?php if ( function_exists( 'cdf_quote_page_url' ) ) : ?>
			<a class="cdf-btn cdf-btn--secondary" href="<?php echo esc_url( cdf_quote_page_url() ); ?>"><?php esc_html_e( 'Pedido de orçamento', 'casadasfardas' ); ?></a>
		<?php endif; ?>
	</p>
</div>
