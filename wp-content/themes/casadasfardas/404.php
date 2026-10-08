<?php
/**
 * Página não encontrada (HTTP 404) — útil: pesquisa, secções principais e contacto.
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="cdf-container cdf-empty-page">
	<p class="cdf-eyebrow"><?php esc_html_e( 'Erro 404', 'casadasfardas' ); ?></p>
	<h1><?php esc_html_e( 'Não encontrámos esta página', 'casadasfardas' ); ?></h1>
	<p class="cdf-empty-page__lead"><?php esc_html_e( 'O endereço pode ter mudado com a nova loja. Pesquise pelo nome ou pela referência do artigo, ou comece por uma das secções abaixo.', 'casadasfardas' ); ?></p>
	<div class="cdf-empty-page__search"><?php cdf_render_search( 'cdf-search-404' ); ?></div>
	<?php
	if ( function_exists( 'cdf_block_categories' ) ) {
		echo cdf_block_categories( array( 'parent' => '', 'limit' => 6, 'columns' => 3, 'style' => 'list' ) ); // phpcs:ignore
	}
	?>
	<p class="cdf-empty-page__help">
		<?php
		printf(
			/* translators: 1: contactos URL, 2: telefone */
			wp_kses_post( __( 'Precisa de ajuda? <a href="%1$s">Fale connosco</a> ou ligue %2$s.', 'casadasfardas' ) ),
			esc_url( function_exists( 'cdf_page_url' ) && cdf_page_url( 'contactos' ) ? cdf_page_url( 'contactos' ) : home_url( '/' ) ),
			esc_html( function_exists( 'cdf_phone_display' ) ? cdf_phone_display( cdf_theme_business( 'phone' ) ) : '' )
		);
		?>
	</p>
</div>
<?php
get_footer();
