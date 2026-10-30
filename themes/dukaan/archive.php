<?php
/**
 * Dukaan — archive (category/tag/date/author).
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="dukaan-main">
	<div class="dukaan-container">
		<header class="dukaan-archive-head">
			<?php
			the_archive_title( '<h1 class="dukaan-page-title">', '</h1>' );
			the_archive_description( '<div class="dukaan-archive-desc">', '</div>' );
			?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="dukaan-post-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination( array(
				'mid_size'  => 1,
				'prev_text' => __( '&larr; Pichhla', 'dukaan' ),
				'next_text' => __( 'Agla &rarr;', 'dukaan' ),
			) );
			?>
		<?php else : ?>
			<p><?php esc_html_e( 'Is hise mein abhi kuch mojood nahi hai.', 'dukaan' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
