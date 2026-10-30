<?php
/**
 * Dukaan — static page.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="dukaan-main">
	<div class="dukaan-container dukaan-narrow">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'dukaan-page' ); ?>>
				<h1 class="dukaan-page-title"><?php the_title(); ?></h1>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="dukaan-page-thumb">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>

				<div class="dukaan-entry-content">
					<?php
					the_content();
					wp_link_pages( array(
						'before' => '<div class="dukaan-page-links">' . esc_html__( 'Pages:', 'dukaan' ),
						'after'  => '</div>',
					) );
					?>
				</div>
			</article>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
