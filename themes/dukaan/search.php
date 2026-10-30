<?php
/**
 * Dukaan — search results.
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
		<h1 class="dukaan-page-title">
			<?php
			printf(
				/* translators: %s: search query */
				esc_html__( 'Search nataij: %s', 'dukaan' ),
				'<span>' . esc_html( get_search_query() ) . '</span>'
			);
			?>
		</h1>

		<?php if ( have_posts() ) : ?>
			<div class="dukaan-post-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					if ( 'product' === get_post_type() && class_exists( 'WooCommerce' ) ) {
						// Product results Woo ke apne card mein achhe lagte hain.
						?>
						<div class="dukaan-search-product">
							<?php wc_get_template_part( 'content', 'product' ); ?>
						</div>
						<?php
					} else {
						get_template_part( 'template-parts/content', 'card' );
					}
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
			<p><?php esc_html_e( 'Kuch nahi mila. Koi aur lafz likh kar dobara koshish karein.', 'dukaan' ); ?></p>
			<?php get_search_form(); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
