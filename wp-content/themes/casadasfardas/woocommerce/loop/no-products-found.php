<?php
/**
 * Sem resultados (pesquisa ou filtros): caminhos úteis em vez de uma página vazia.
 *
 * Override do template do WooCommerce (versão de referência 7.8.0).
 *
 * @version 7.8.0
 */

defined( 'ABSPATH' ) || exit;

$cdf_filtered = function_exists( 'cdf_filters_active' ) && cdf_filters_active();
?>
<div class="cdf-empty woocommerce-no-products-found">
	<?php echo cdf_svg( 'search', 36 ); // phpcs:ignore ?>
	<?php if ( $cdf_filtered ) : ?>
		<h2><?php esc_html_e( 'Nenhum artigo com esta combinação de filtros', 'casadasfardas' ); ?></h2>
		<p><?php esc_html_e( 'Retire um dos filtros ou comece de novo.', 'casadasfardas' ); ?></p>
		<p><a class="cdf-btn cdf-btn--secondary" href="<?php echo esc_url( cdf_filter_clear_url() ); ?>"><?php esc_html_e( 'Limpar filtros', 'casadasfardas' ); ?></a></p>
	<?php elseif ( is_search() ) : ?>
		<h2><?php echo esc_html( sprintf( __( 'Não encontrámos artigos para «%s»', 'casadasfardas' ), get_search_query() ) ); ?></h2>
		<p><?php esc_html_e( 'Confirme a referência ou experimente uma palavra mais geral (por exemplo «pólo», «bota», «luvas»). Se não encontrar o que procura, descreva o artigo num pedido de orçamento e respondemos.', 'casadasfardas' ); ?></p>
		<p class="cdf-empty__actions">
			<a class="cdf-btn cdf-btn--primary" href="<?php echo esc_url( function_exists( 'cdf_quote_page_url' ) ? cdf_quote_page_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Pedir orçamento', 'casadasfardas' ); ?></a>
			<a class="cdf-btn cdf-btn--secondary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Ver todas as categorias', 'casadasfardas' ); ?></a>
		</p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Ainda não há artigos nesta categoria', 'casadasfardas' ); ?></h2>
		<p><?php esc_html_e( 'Fale connosco: podemos ter o que procura disponível por encomenda.', 'casadasfardas' ); ?></p>
		<p><a class="cdf-btn cdf-btn--primary" href="<?php echo esc_url( function_exists( 'cdf_quote_page_url' ) ? cdf_quote_page_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Pedir orçamento', 'casadasfardas' ); ?></a></p>
	<?php endif; ?>
</div>
