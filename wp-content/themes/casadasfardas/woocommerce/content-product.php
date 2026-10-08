<?php
/**
 * Cartão de produto nas listagens.
 *
 * Override do template do WooCommerce (versão de referência 9.4.0).
 * O cartão inteiro é uma ligação; a compra faz-se na ficha (tamanho/cor obrigatórios na maioria dos artigos).
 *
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

$cdf_brand  = cdf_theme_brand_name( $product->get_id() );
$cdf_colors = cdf_theme_color_count( $product );
$cdf_link   = $product->get_permalink();
$cdf_img    = $product->get_image_id();
// As primeiras imagens da grelha estão acima da dobra: carregam logo (a primeira com prioridade).
$cdf_index  = (int) wc_get_loop_prop( 'loop' );
$cdf_eager  = $cdf_index < 4 && ( is_shop() || is_product_taxonomy() || is_search() );
?>
<li <?php wc_product_class( 'cdf-card', $product ); ?>>
	<a class="cdf-card__link" href="<?php echo esc_url( $cdf_link ); ?>">
		<span class="cdf-card__media">
			<?php
			if ( $cdf_img ) {
				echo wp_get_attachment_image(
					$cdf_img,
					'woocommerce_thumbnail',
					false,
					array(
						'class'   => 'cdf-card__img',
						'alt'     => '',
						'loading'       => $cdf_eager ? 'eager' : 'lazy',
						'fetchpriority' => 0 === $cdf_index && $cdf_eager ? 'high' : 'auto',
						'sizes'         => '(min-width: 1360px) 300px, (min-width: 1024px) 22vw, (min-width: 640px) 30vw, 46vw',
					)
				);
			} else {
				echo '<span class="cdf-card__noimg">' . cdf_svg( 'shirt', 40 ) . '<span>' . esc_html__( 'Sem fotografia', 'casadasfardas' ) . '</span></span>'; // phpcs:ignore
			}
			?>
		</span>
		<span class="cdf-card__body">
			<?php if ( $cdf_brand ) : ?>
				<span class="cdf-card__brand"><?php echo esc_html( $cdf_brand ); ?></span>
			<?php endif; ?>
			<span class="cdf-card__title"><?php echo esc_html( $product->get_name() ); ?></span>
			<span class="cdf-card__meta">
				<?php if ( $product->get_sku() ) : ?>
					<span class="cdf-card__ref"><?php echo esc_html( sprintf( __( 'Ref. %s', 'casadasfardas' ), $product->get_sku() ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cdf_colors > 1 ) : ?>
					<span class="cdf-card__colors"><?php echo esc_html( sprintf( _n( '%d cor', '%d cores', $cdf_colors, 'casadasfardas' ), $cdf_colors ) ); ?></span>
				<?php endif; ?>
			</span>
		</span>
	</a>
	<div class="cdf-card__price"><?php echo cdf_theme_price_block( $product, 'card' ); // phpcs:ignore ?></div>
</li>
