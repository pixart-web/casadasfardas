<?php
/**
 * Loja, categorias, marcas e resultados de pesquisa.
 *
 * Override do template do WooCommerce (versão de referência 8.6.0).
 * Filtros com ligações GET rastreáveis (noindex + canonical tratados no cdf-core), paginação com URLs próprias.
 *
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$cdf_term     = is_product_taxonomy() ? get_queried_object() : null;
$cdf_facets   = function_exists( 'cdf_get_facets' ) ? cdf_get_facets() : array();
$cdf_active   = function_exists( 'cdf_filters_active' ) && cdf_filters_active();
$cdf_total    = (int) wc_get_loop_prop( 'total' );
$cdf_intro    = $cdf_term ? (string) get_term_meta( $cdf_term->term_id, 'cdf_intro', true ) : '';
$cdf_children = array();
if ( $cdf_term && 'product_cat' === $cdf_term->taxonomy ) {
	$cdf_children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $cdf_term->term_id, 'hide_empty' => true, 'menu_order' => 'ASC' ) );
	if ( ! $cdf_children && $cdf_term->parent ) {
		// Folha: mostra as irmãs para navegação lateral rápida.
		$cdf_children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $cdf_term->parent, 'hide_empty' => true, 'menu_order' => 'ASC' ) );
	}
} elseif ( is_shop() && ! is_search() ) {
	$cdf_children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'menu_order' => 'ASC' ) );
}
$cdf_children = is_wp_error( $cdf_children ) ? array() : $cdf_children;
?>
<div class="cdf-container cdf-archive">
	<?php woocommerce_breadcrumb(); ?>

	<header class="cdf-archive__header">
		<h1 class="cdf-archive__title"><?php woocommerce_page_title(); ?></h1>
		<?php if ( $cdf_intro ) : ?>
			<p class="cdf-archive__intro"><?php echo esc_html( $cdf_intro ); ?></p>
		<?php elseif ( is_shop() && ! is_search() ) : ?>
			<p class="cdf-archive__intro"><?php esc_html_e( 'Vestuário profissional, calçado de segurança e equipamentos de proteção individual. Pesquise por nome ou referência, filtre por marca, cor e tamanho.', 'casadasfardas' ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( $cdf_children ) : ?>
		<nav class="cdf-subcats" aria-label="<?php esc_attr_e( 'Subcategorias', 'casadasfardas' ); ?>">
			<ul class="cdf-subcats__list">
				<?php foreach ( $cdf_children as $child ) : ?>
					<?php $is_current = $cdf_term && $child->term_id === $cdf_term->term_id; ?>
					<li><a class="cdf-chip<?php echo $is_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( get_term_link( $child ) ); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $child->name ); ?> <span class="cdf-chip__count"><?php echo (int) ( function_exists( 'cdf_term_product_count' ) ? cdf_term_product_count( $child ) : $child->count ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php wc_print_notices(); ?>
	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<div class="cdf-archive__layout<?php echo $cdf_facets ? ' has-filters' : ''; ?>">
		<?php if ( $cdf_facets ) : ?>
			<aside class="cdf-filters" id="cdf-filters" aria-label="<?php esc_attr_e( 'Filtros', 'casadasfardas' ); ?>" data-cdf-filters>
				<div class="cdf-filters__head">
					<h2 class="cdf-filters__title"><?php esc_html_e( 'Filtrar', 'casadasfardas' ); ?></h2>
					<button type="button" class="cdf-iconbtn cdf-filters__close" data-cdf-filters-close><?php echo cdf_svg( 'close', 22 ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( 'Fechar filtros', 'casadasfardas' ); ?></span></button>
				</div>
				<?php foreach ( $cdf_facets as $param => $facet ) : ?>
					<?php $has_selected = (bool) wp_list_filter( $facet['options'], array( 'selected' => true ) ); ?>
					<details class="cdf-facet" <?php echo ( $has_selected || 'marca' === $param || count( $cdf_facets ) < 3 ) ? 'open' : ''; ?>>
						<summary class="cdf-facet__title"><?php echo esc_html( $facet['label'] ); ?><?php echo cdf_svg( 'down', 18 ); // phpcs:ignore ?></summary>
						<ul class="cdf-facet__list<?php echo 'filter_tamanho' === $param ? ' cdf-facet__list--sizes' : ''; ?>">
							<?php foreach ( $facet['options'] as $opt ) : ?>
								<li>
									<a class="cdf-facet__opt<?php echo $opt['selected'] ? ' is-selected' : ''; ?><?php echo ! $opt['count'] ? ' is-empty' : ''; ?>" href="<?php echo esc_url( cdf_filter_toggle_url( $param, $opt['slug'] ) ); ?>" rel="nofollow">
										<span class="cdf-facet__box" aria-hidden="true"><?php echo $opt['selected'] ? cdf_svg( 'check', 14 ) : ''; // phpcs:ignore ?></span>
										<span class="cdf-facet__name"><?php echo esc_html( $opt['name'] ); ?></span>
										<span class="cdf-facet__count"><?php echo (int) $opt['count']; ?></span>
										<span class="screen-reader-text"><?php echo $opt['selected'] ? esc_html__( '(selecionado — clique para remover)', 'casadasfardas' ) : ''; ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endforeach; ?>
				<?php if ( $cdf_active ) : ?>
					<a class="cdf-filters__clear" href="<?php echo esc_url( cdf_filter_clear_url() ); ?>"><?php esc_html_e( 'Limpar filtros', 'casadasfardas' ); ?></a>
				<?php endif; ?>
				<div class="cdf-filters__apply">
					<button type="button" class="cdf-btn cdf-btn--primary cdf-btn--block" data-cdf-filters-close>
						<?php echo esc_html( sprintf( _n( 'Ver %d artigo', 'Ver %d artigos', $cdf_total, 'casadasfardas' ), $cdf_total ) ); ?>
					</button>
				</div>
			</aside>
		<?php endif; ?>

		<div class="cdf-archive__results">
			<div class="cdf-toolbar">
				<p class="cdf-toolbar__count" role="status">
					<?php echo esc_html( sprintf( _n( '%d artigo', '%d artigos', $cdf_total, 'casadasfardas' ), $cdf_total ) ); ?>
				</p>
				<?php if ( $cdf_facets ) : ?>
					<button type="button" class="cdf-btn cdf-btn--secondary cdf-toolbar__filter" aria-expanded="false" aria-controls="cdf-filters" data-cdf-filters-open>
						<?php echo cdf_svg( 'filter', 18 ); // phpcs:ignore ?> <?php esc_html_e( 'Filtrar', 'casadasfardas' ); ?>
					</button>
				<?php endif; ?>
				<?php if ( $cdf_total > 1 ) : ?>
					<form class="cdf-sort" method="get" data-cdf-sort>
						<label for="cdf-orderby"><?php esc_html_e( 'Ordenar', 'casadasfardas' ); ?></label>
						<select id="cdf-orderby" name="orderby">
							<?php
							$cdf_current_order = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : 'menu_order'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							foreach ( apply_filters( 'woocommerce_catalog_orderby', array() ) as $key => $label ) :
								?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cdf_current_order, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
						<noscript><button type="submit" class="cdf-btn cdf-btn--secondary"><?php esc_html_e( 'Aplicar', 'casadasfardas' ); ?></button></noscript>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( $cdf_active ) : ?>
				<ul class="cdf-active-filters" aria-label="<?php esc_attr_e( 'Filtros ativos', 'casadasfardas' ); ?>">
					<?php foreach ( $cdf_facets as $param => $facet ) : ?>
						<?php foreach ( $facet['options'] as $opt ) : ?>
							<?php if ( $opt['selected'] ) : ?>
								<li><a class="cdf-chip cdf-chip--removable" href="<?php echo esc_url( cdf_filter_toggle_url( $param, $opt['slug'] ) ); ?>" rel="nofollow"><?php echo esc_html( $facet['label'] . ': ' . $opt['name'] ); ?> <?php echo cdf_svg( 'close', 14 ); // phpcs:ignore ?><span class="screen-reader-text"><?php esc_html_e( '(remover filtro)', 'casadasfardas' ); ?></span></a></li>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<li><a class="cdf-link" href="<?php echo esc_url( cdf_filter_clear_url() ); ?>"><?php esc_html_e( 'Limpar tudo', 'casadasfardas' ); ?></a></li>
				</ul>
			<?php endif; ?>

			<?php if ( woocommerce_product_loop() ) : ?>
				<?php
				woocommerce_product_loop_start();
				if ( wc_get_loop_prop( 'total' ) ) {
					while ( have_posts() ) {
						the_post();
						do_action( 'woocommerce_shop_loop' );
						wc_get_template_part( 'content', 'product' );
					}
				}
				woocommerce_product_loop_end();
				do_action( 'woocommerce_after_shop_loop' );
				?>
			<?php else : ?>
				<?php do_action( 'woocommerce_no_products_found' ); ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $cdf_term && term_description( $cdf_term ) && 1 === max( 1, (int) get_query_var( 'paged' ) ) ) : ?>
		<section class="cdf-archive__about">
			<?php echo wp_kses_post( wpautop( term_description( $cdf_term ) ) ); ?>
		</section>
	<?php endif; ?>
</div>
<?php
do_action( 'woocommerce_after_main_content' );
get_footer( 'shop' );
