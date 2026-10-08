<?php
/**
 * Cabeçalho: barra informativa (vantagens confirmadas), marca, pesquisa, ações e navegação.
 */

defined( 'ABSPATH' ) || exit;

$cdf_topbar      = function_exists( 'cdf_business_lines' ) ? array_slice( cdf_business_lines( 'topbar' ), 0, 3 ) : array();
$cdf_phone       = cdf_theme_business( 'phone' );
$cdf_quote_count = cdf_theme_quote_count();
$cdf_cart_count  = cdf_theme_cart_count();
$cdf_quote_url   = function_exists( 'cdf_quote_page_url' ) ? cdf_quote_page_url() : home_url( '/pedido-de-orcamento/' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="cdf-skip" href="#conteudo"><?php esc_html_e( 'Saltar para o conteúdo', 'casadasfardas' ); ?></a>

<header class="cdf-header" data-cdf-header>
	<?php if ( $cdf_topbar ) : ?>
		<div class="cdf-topbar">
			<div class="cdf-topbar__inner">
				<ul class="cdf-topbar__list">
					<?php foreach ( $cdf_topbar as $i => $line ) : ?>
						<li<?php echo $i ? ' class="cdf-topbar__extra"' : ''; ?>><?php echo cdf_svg( 0 === $i ? 'needle' : ( 1 === $i ? 'truck' : 'store' ), 16 ); // phpcs:ignore ?><span><?php echo esc_html( $line ); ?></span></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $cdf_phone && function_exists( 'cdf_phone_href' ) ) : ?>
					<a class="cdf-topbar__phone" href="<?php echo esc_attr( cdf_phone_href( $cdf_phone ) ); ?>">
						<?php echo cdf_svg( 'phone', 16 ); // phpcs:ignore ?>
						<span><?php echo esc_html( cdf_phone_display( $cdf_phone ) ); ?></span>
						<span class="screen-reader-text">(<?php echo esc_html( cdf_theme_business( 'phone_note' ) ); ?>)</span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="cdf-masthead">
		<div class="cdf-masthead__inner">
			<button type="button" class="cdf-iconbtn cdf-masthead__menu" aria-expanded="false" aria-controls="cdf-drawer" data-cdf-drawer-open>
				<?php echo cdf_svg( 'menu', 24 ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( 'Abrir menu', 'casadasfardas' ); ?></span>
			</button>
			<?php echo cdf_theme_logo( 'cdf-logo' ); // phpcs:ignore ?>
			<div class="cdf-masthead__search"><?php cdf_render_search( 'cdf-search' ); ?></div>
			<nav class="cdf-actions" aria-label="<?php esc_attr_e( 'Conta e compras', 'casadasfardas' ); ?>">
				<a class="cdf-action cdf-action--quote" href="<?php echo esc_url( $cdf_quote_url ); ?>">
					<span class="cdf-action__icon"><?php echo cdf_svg( 'quote', 24 ); // phpcs:ignore ?>
						<?php if ( $cdf_quote_count ) : ?><span class="cdf-badge"><?php echo (int) $cdf_quote_count; ?></span><?php endif; ?>
					</span>
					<span class="cdf-action__label"><?php esc_html_e( 'Orçamento', 'casadasfardas' ); ?></span>
					<?php if ( $cdf_quote_count ) : ?><span class="screen-reader-text"><?php echo esc_html( sprintf( _n( '(%d artigo)', '(%d artigos)', $cdf_quote_count, 'casadasfardas' ), $cdf_quote_count ) ); ?></span><?php endif; ?>
				</a>
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<a class="cdf-action cdf-action--account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
						<span class="cdf-action__icon"><?php echo cdf_svg( 'user', 24 ); // phpcs:ignore ?></span>
						<span class="cdf-action__label"><?php echo is_user_logged_in() ? esc_html__( 'Conta', 'casadasfardas' ) : esc_html__( 'Entrar', 'casadasfardas' ); ?></span>
					</a>
					<?php if ( cdf_theme_shop_mode() ) : ?>
						<a class="cdf-action cdf-action--cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
							<span class="cdf-action__icon"><?php echo cdf_svg( 'cart', 24 ); // phpcs:ignore ?>
								<?php if ( $cdf_cart_count ) : ?><span class="cdf-badge cdf-badge--amber"><?php echo (int) $cdf_cart_count; ?></span><?php endif; ?>
							</span>
							<span class="cdf-action__label"><?php esc_html_e( 'Carrinho', 'casadasfardas' ); ?></span>
							<?php if ( $cdf_cart_count ) : ?><span class="screen-reader-text"><?php echo esc_html( sprintf( _n( '(%d artigo)', '(%d artigos)', $cdf_cart_count, 'casadasfardas' ), $cdf_cart_count ) ); ?></span><?php endif; ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</nav>
		</div>
		<div class="cdf-masthead__msearch"><?php cdf_render_search( 'cdf-msearch' ); ?></div>
	</div>

	<div class="cdf-navbar">
		<div class="cdf-navbar__inner">
			<?php cdf_render_primary_nav(); ?>
			<a class="cdf-navbar__cta" href="<?php echo esc_url( $cdf_quote_url ); ?>"><?php esc_html_e( 'Equipar a minha empresa', 'casadasfardas' ); ?></a>
		</div>
	</div>
</header>

<dialog class="cdf-drawer" id="cdf-drawer" aria-label="<?php esc_attr_e( 'Menu', 'casadasfardas' ); ?>" data-cdf-drawer>
	<div class="cdf-drawer__head">
		<?php echo cdf_theme_logo( 'cdf-logo cdf-logo--small' ); // phpcs:ignore ?>
		<button type="button" class="cdf-iconbtn" data-cdf-drawer-close><?php echo cdf_svg( 'close', 24 ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( 'Fechar menu', 'casadasfardas' ); ?></span></button>
	</div>
	<div class="cdf-drawer__body">
		<?php cdf_render_mobile_nav(); ?>
		<div class="cdf-drawer__extra">
			<a class="cdf-btn cdf-btn--primary cdf-btn--block" href="<?php echo esc_url( $cdf_quote_url ); ?>"><?php esc_html_e( 'Equipar a minha empresa', 'casadasfardas' ); ?></a>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<a class="cdf-drawer__contact" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php echo cdf_svg( 'user', 18 ); // phpcs:ignore ?> <?php echo is_user_logged_in() ? esc_html__( 'A minha conta', 'casadasfardas' ) : esc_html__( 'Entrar ou criar conta', 'casadasfardas' ); ?></a>
			<?php endif; ?>
			<?php if ( $cdf_phone && function_exists( 'cdf_phone_href' ) ) : ?>
				<a class="cdf-drawer__contact" href="<?php echo esc_attr( cdf_phone_href( $cdf_phone ) ); ?>"><?php echo cdf_svg( 'phone', 18 ); // phpcs:ignore ?> <?php echo esc_html( cdf_phone_display( $cdf_phone ) ); ?> <small><?php echo esc_html( cdf_theme_business( 'phone_note' ) ); ?></small></a>
			<?php endif; ?>
		</div>
	</div>
</dialog>

<main id="conteudo" class="cdf-main" tabindex="-1">
