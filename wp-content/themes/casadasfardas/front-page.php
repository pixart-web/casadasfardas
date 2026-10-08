<?php
/**
 * Página inicial: composta em blocos (editável em Páginas → Início).
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<div class="cdf-content cdf-home entry-content">
		<?php the_content(); ?>
	</div>
	<?php
endwhile;
get_footer();
