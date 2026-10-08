<?php
/**
 * Ficha de produto.
 *
 * Override do template do WooCommerce (versão de referência 3.6.0).
 * Galeria própria, resumo com referência/preço/IVA, compra ou orçamento, especificações confirmadas
 * (apenas dados da origem), personalização e produtos da mesma categoria.
 *
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );
if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$cdf_brand      = cdf_theme_brand_name( $product->get_id() );
$cdf_specs      = (array) $product->get_meta( '_cdf_specs' );
$cdf_sizes      = function_exists( 'cdf_product_sizes' ) ? cdf_product_sizes( $product ) : array();
$cdf_quote_only = function_exists( 'cdf_is_quote_only' ) && cdf_is_quote_only( $product );
$cdf_cats       = get_the_terms( $product->get_id(), 'product_cat' );
$cdf_cat        = ( $cdf_cats && ! is_wp_error( $cdf_cats ) ) ? $cdf_cats[0] : null;
$cdf_custom_url = function_exists( 'cdf_page_url' ) ? cdf_page_url( 'personalizacao' ) : '';
$cdf_quote_url  = function_exists( 'cdf_quote_page_url' ) ? cdf_quote_page_url() : '';
?>
<div class="cdf-container cdf-product-wrap">
	<?php woocommerce_breadcrumb(); ?>
	<?php wc_print_notices(); ?>

	<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'cdf-product', $product ); ?>>
		<div class="cdf-product__gallery">
			<?php cdf_theme_product_gallery( $product ); ?>
		</div>

		<div class="cdf-product__summary summary entry-summary" data-cdf-summary>
			<?php if ( $cdf_brand ) : ?>
				<p class="cdf-product__brand"><?php echo esc_html( $cdf_brand ); ?></p>
			<?php endif; ?>
			<h1 class="cdf-product__title product_title"><?php the_title(); ?></h1>
			<p class="cdf-product__ref">
				<?php if ( $product->get_sku() ) : ?>
					<?php esc_html_e( 'Referência', 'casadasfardas' ); ?> <strong data-cdf-sku><?php echo esc_html( $product->get_sku() ); ?></strong>
				<?php endif; ?>
				<?php if ( $cdf_cat ) : ?>
					<span class="cdf-product__cat"><a href="<?php echo esc_url( get_term_link( $cdf_cat ) ); ?>"><?php echo esc_html( $cdf_cat->name ); ?></a></span>
				<?php endif; ?>
			</p>

			<?php if ( $product->get_short_description() ) : ?>
				<div class="cdf-product__excerpt"><?php echo wp_kses_post( apply_filters( 'woocommerce_short_description', $product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<div class="cdf-product__pricebox">
				<?php echo cdf_theme_price_block( $product, 'single' ); // phpcs:ignore ?>
				<?php if ( ! $cdf_quote_only ) : ?>
					<p class="cdf-product__availability"><?php echo cdf_svg( 'clock', 16 ); // phpcs:ignore ?> <?php echo esc_html( $product->get_availability()['availability'] ?? '' ); ?></p>
				<?php else : ?>
					<p class="cdf-product__note"><?php esc_html_e( 'Peça orçamento com as quantidades e a personalização pretendidas: respondemos com preço e prazo.', 'casadasfardas' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="cdf-product__buy" id="comprar" data-cdf-buy>
				<?php do_action( 'woocommerce_single_product_summary' ); ?>
			</div>

			<ul class="cdf-product__assurance">
				<li><?php echo cdf_svg( 'needle', 20 ); // phpcs:ignore ?><span><?php echo wp_kses_post( sprintf( __( 'Personalização com bordado ou estampagem — <a href="%s">saiba como funciona</a>', 'casadasfardas' ), esc_url( $cdf_custom_url ?: $cdf_quote_url ) ) ); ?></span></li>
				<li><?php echo cdf_svg( 'truck', 20 ); // phpcs:ignore ?><span><?php esc_html_e( 'Fornecemos para todo o país', 'casadasfardas' ); ?></span></li>
				<li><?php echo cdf_svg( 'phone', 20 ); // phpcs:ignore ?><span>
					<?php
					if ( function_exists( 'cdf_phone_href' ) ) {
						printf(
							wp_kses_post( __( 'Dúvidas sobre tamanhos ou quantidades? <a href="%1$s">%2$s</a>', 'casadasfardas' ) ),
							esc_attr( cdf_phone_href( cdf_theme_business( 'phone' ) ) ),
							esc_html( cdf_phone_display( cdf_theme_business( 'phone' ) ) )
						);
						echo ' <small>(' . esc_html( cdf_theme_business( 'phone_note' ) ) . ')</small>';
					}
					?>
				</span></li>
			</ul>
		</div>
	</div>

	<div class="cdf-product__details">
		<nav class="cdf-product__tabs" aria-label="<?php esc_attr_e( 'Secções do artigo', 'casadasfardas' ); ?>">
			<?php if ( $product->get_description() ) : ?><a href="#descricao"><?php esc_html_e( 'Descrição', 'casadasfardas' ); ?></a><?php endif; ?>
			<a href="#especificacoes"><?php esc_html_e( 'Especificações', 'casadasfardas' ); ?></a>
			<a href="#personalizacao"><?php esc_html_e( 'Personalização', 'casadasfardas' ); ?></a>
		</nav>

		<?php if ( $product->get_description() ) : ?>
			<section class="cdf-product__section" id="descricao" aria-labelledby="descricao-title">
				<h2 id="descricao-title"><?php esc_html_e( 'Descrição', 'casadasfardas' ); ?></h2>
				<div class="cdf-prose"><?php echo wp_kses_post( wpautop( $product->get_description() ) ); ?></div>
			</section>
		<?php endif; ?>

		<section class="cdf-product__section" id="especificacoes" aria-labelledby="especificacoes-title">
			<h2 id="especificacoes-title"><?php esc_html_e( 'Especificações', 'casadasfardas' ); ?></h2>
			<table class="cdf-specs">
				<tbody>
					<?php if ( $product->get_sku() ) : ?>
						<tr><th scope="row"><?php esc_html_e( 'Referência', 'casadasfardas' ); ?></th><td><?php echo esc_html( $product->get_sku() ); ?></td></tr>
					<?php endif; ?>
					<?php if ( $cdf_brand ) : ?>
						<tr><th scope="row"><?php esc_html_e( 'Marca', 'casadasfardas' ); ?></th><td><?php echo esc_html( $cdf_brand ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $cdf_specs as $spec ) : ?>
						<?php
						if ( ! is_array( $spec ) || count( $spec ) < 2 ) {
							continue;
						}
						if ( 'cores' === strtolower( $spec[0] ) && cdf_theme_color_count( $product ) ) {
							continue;
						}
						?>
						<tr><th scope="row"><?php echo esc_html( $spec[0] ); ?></th><td><?php echo esc_html( $spec[1] ); ?></td></tr>
					<?php endforeach; ?>
					<?php if ( $product->is_type( 'variable' ) ) : ?>
						<?php
						foreach ( $product->get_variation_attributes() as $attr => $values ) {
							$names = array();
							foreach ( $values as $slug ) {
								$t       = get_term_by( 'slug', $slug, $attr );
								$names[] = $t ? $t->name : $slug;
							}
							echo '<tr><th scope="row">' . esc_html( wc_attribute_label( $attr ) ) . '</th><td>' . esc_html( implode( ', ', $names ) ) . '</td></tr>';
						}
						?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php if ( $cdf_sizes ) : ?>
				<p class="cdf-specs__note"><?php esc_html_e( 'Tamanhos indicados pelo fabricante. Não dispomos de tabela de medidas para este artigo: se tiver dúvidas, contacte-nos antes de encomendar.', 'casadasfardas' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="cdf-product__section cdf-product__custom" id="personalizacao" aria-labelledby="personalizacao-title">
			<div>
				<p class="cdf-eyebrow"><?php esc_html_e( 'Para empresas e equipas', 'casadasfardas' ); ?></p>
				<h2 id="personalizacao-title"><?php esc_html_e( 'Quer este artigo com o logótipo da sua empresa?', 'casadasfardas' ); ?></h2>
				<p><?php esc_html_e( 'Temos produção própria de bordados e estamparia. Adicione o artigo ao pedido de orçamento, indique quantidades e tamanhos e anexe o logótipo.', 'casadasfardas' ); ?></p>
			</div>
			<div class="cdf-product__custom-actions">
				<?php if ( $cdf_quote_url ) : ?>
					<a class="cdf-btn cdf-btn--primary" href="#comprar" data-cdf-scroll-buy><?php esc_html_e( 'Adicionar ao orçamento', 'casadasfardas' ); ?></a>
				<?php endif; ?>
				<?php if ( $cdf_custom_url ) : ?>
					<a class="cdf-btn cdf-btn--secondary" href="<?php echo esc_url( $cdf_custom_url ); ?>"><?php esc_html_e( 'Ver personalização', 'casadasfardas' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
</div>

<div class="cdf-buybar" data-cdf-buybar hidden>
	<div class="cdf-buybar__inner">
		<div class="cdf-buybar__info">
			<span class="cdf-buybar__name"><?php echo esc_html( $product->get_name() ); ?></span>
			<span class="cdf-buybar__price"><?php echo $cdf_quote_only ? esc_html__( 'Preço sob orçamento', 'casadasfardas' ) : wp_kses_post( wc_price( wc_get_price_excluding_tax( $product ) ) ) . ' <small>+ IVA</small>'; ?></span>
		</div>
		<a class="cdf-btn cdf-btn--primary" href="#comprar" data-cdf-scroll-buy><?php echo $cdf_quote_only ? esc_html__( 'Pedir orçamento', 'casadasfardas' ) : esc_html__( 'Escolher e comprar', 'casadasfardas' ); ?></a>
	</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
