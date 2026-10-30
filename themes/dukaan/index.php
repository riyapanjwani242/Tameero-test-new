<?php
/**
 * Dukaan — main index (blog/posts listing fallback).
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
		<?php if ( have_posts() ) : ?>
			<?php if ( is_home() && ! is_front_page() ) : ?>
				<h1 class="dukaan-page-title"><?php single_post_title(); ?></h1>
			<?php endif; ?>

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
			<h1 class="dukaan-page-title"><?php esc_html_e( 'Kuch nahi mila', 'dukaan' ); ?></h1>
			<p><?php esc_html_e( 'Abhi koi post mojood nahi hai.', 'dukaan' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
