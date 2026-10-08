<?php
/**
 * Artigo.
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
			<p class="cdf-page__meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
		</header>
		<div class="cdf-content entry-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;
get_footer();
