<?php
/**
 * Template Name: Página larga (sem título automático)
 *
 * O conteúdo de blocos controla toda a composição (o H1 está no próprio conteúdo).
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'cdf-page cdf-page--wide' ); ?>>
		<div class="cdf-container cdf-page__crumbs"><?php cdf_page_breadcrumbs(); ?></div>
		<div class="cdf-content entry-content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;
get_footer();
