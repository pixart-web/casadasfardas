<?php
/**
 * Página: título + conteúdo de blocos.
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'cdf-page' ); ?>>
		<header class="cdf-page__header cdf-container">
			<?php cdf_page_breadcrumbs(); ?>
			<h1 class="cdf-page__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="cdf-page__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>
		<div class="cdf-content entry-content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;
get_footer();
