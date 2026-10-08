<?php
/**
 * Rodapé: chamada para empresas, contactos, menus, informação legal.
 */

defined( 'ABSPATH' ) || exit;

$cdf_phone     = cdf_theme_business( 'phone' );
$cdf_emails    = function_exists( 'cdf_business_lines' ) ? cdf_business_lines( 'emails' ) : array();
$cdf_quote_url = function_exists( 'cdf_quote_page_url' ) ? cdf_quote_page_url() : home_url( '/pedido-de-orcamento/' );
$cdf_hide_cta  = is_page( 'pedido-de-orcamento' ) || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) );
?>
</main>

<?php if ( ! $cdf_hide_cta ) : ?>
	<section class="cdf-precta" aria-labelledby="cdf-precta-title">
		<div class="cdf-precta__inner">
			<div class="cdf-precta__text">
				<h2 id="cdf-precta-title"><?php esc_html_e( 'Vai equipar uma equipa?', 'casadasfardas' ); ?></h2>
				<p><?php esc_html_e( 'Envie a lista de artigos, quantidades e o logótipo. Preparamos uma proposta com bordado ou estampagem.', 'casadasfardas' ); ?></p>
			</div>
			<div class="cdf-precta__actions">
				<a class="cdf-btn cdf-btn--primary" href="<?php echo esc_url( $cdf_quote_url ); ?>"><?php esc_html_e( 'Pedir orçamento', 'casadasfardas' ); ?></a>
				<?php if ( $cdf_phone && function_exists( 'cdf_phone_href' ) ) : ?>
					<a class="cdf-btn cdf-btn--ghost-light" href="<?php echo esc_attr( cdf_phone_href( $cdf_phone ) ); ?>"><?php echo cdf_svg( 'phone', 18 ); // phpcs:ignore ?> <?php echo esc_html( cdf_phone_display( $cdf_phone ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<footer class="cdf-footer">
	<div class="cdf-footer__inner">
		<div class="cdf-footer__brand">
			<?php echo cdf_theme_logo( 'cdf-logo cdf-logo--footer' ); // phpcs:ignore ?>
			<p class="cdf-footer__about"><?php esc_html_e( 'Vestuário de trabalho, calçado de segurança e equipamentos de proteção individual, com bordados e estamparia de produção própria.', 'casadasfardas' ); ?></p>
			<address class="cdf-footer__address">
				<span class="cdf-footer__line"><?php echo cdf_svg( 'pin', 18 ); // phpcs:ignore ?><span><?php echo esc_html( cdf_theme_business( 'street' ) ); ?><br><?php echo esc_html( cdf_theme_business( 'postcode' ) . ' ' . cdf_theme_business( 'locality' ) ); ?></span></span>
				<?php if ( $cdf_phone && function_exists( 'cdf_phone_href' ) ) : ?>
					<span class="cdf-footer__line"><?php echo cdf_svg( 'phone', 18 ); // phpcs:ignore ?><span><a href="<?php echo esc_attr( cdf_phone_href( $cdf_phone ) ); ?>"><?php echo esc_html( cdf_phone_display( $cdf_phone ) ); ?></a><br><small><?php echo esc_html( cdf_theme_business( 'phone_note' ) ); ?></small></span></span>
				<?php endif; ?>
				<?php if ( $cdf_emails ) : ?>
					<span class="cdf-footer__line"><?php echo cdf_svg( 'mail', 18 ); // phpcs:ignore ?><span><?php foreach ( $cdf_emails as $i => $email ) { echo ( $i ? '<br>' : '' ) . '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>'; } ?></span></span>
				<?php endif; ?>
				<span class="cdf-footer__line"><?php echo cdf_svg( 'clock', 18 ); // phpcs:ignore ?><span><?php echo nl2br( esc_html( cdf_theme_business( 'hours' ) ) ); ?></span></span>
			</address>
		</div>
		<div class="cdf-footer__menus">
			<?php
			cdf_footer_menu( 'footer_shop', __( 'Loja', 'casadasfardas' ) );
			cdf_footer_menu( 'footer_help', __( 'Apoio ao cliente', 'casadasfardas' ) );
			cdf_footer_menu( 'footer_company', __( 'Empresa', 'casadasfardas' ) );
			?>
		</div>
	</div>
	<div class="cdf-footer__bottom">
		<div class="cdf-footer__bottom-inner">
			<p class="cdf-footer__legal">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( cdf_theme_business( 'legal_name' ) ); ?><?php echo cdf_theme_business( 'nif' ) ? ' · NIF ' . esc_html( cdf_theme_business( 'nif' ) ) : ''; ?></p>
			<?php if ( has_nav_menu( 'footer_legal' ) ) : ?>
				<nav aria-label="<?php esc_attr_e( 'Informação legal', 'casadasfardas' ); ?>">
					<?php wp_nav_menu( array( 'theme_location' => 'footer_legal', 'container' => false, 'menu_class' => 'cdf-footer__legal-list', 'depth' => 1, 'fallback_cb' => false ) ); ?>
				</nav>
			<?php endif; ?>
			<p class="cdf-footer__vat"><?php echo cdf_theme_shop_mode() ? esc_html__( 'Preços indicados sem IVA; o valor com IVA à taxa legal é mostrado em cada artigo e no carrinho.', 'casadasfardas' ) : esc_html__( 'Valores sem IVA; acresce IVA à taxa legal em vigor.', 'casadasfardas' ); ?></p>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
