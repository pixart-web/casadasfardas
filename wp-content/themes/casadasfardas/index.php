<?php
/**
 * Modelo genérico (arquivos e blog, se vier a existir).
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="cdf-container cdf-page">
	<header class="cdf-page__header">
		<h1 class="cdf-page__title"><?php echo is_home() ? esc_html__( 'Notícias', 'casadasfardas' ) : wp_kses_post( get_the_archive_title() ); ?></h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="cdf-postlist">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'cdf-postlist__item' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Ainda não há conteúdos.', 'casadasfardas' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
