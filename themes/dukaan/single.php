<?php
/**
 * Dukaan — single post.
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
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'dukaan-single' ); ?>>
				<h1 class="dukaan-page-title"><?php the_title(); ?></h1>

				<p class="dukaan-post-meta">
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php
					$dukaan_cats = get_the_category_list( ', ' );
					if ( $dukaan_cats ) {
						echo '<span class="dukaan-meta-sep">·</span> ' . wp_kses_post( $dukaan_cats );
					}
					?>
				</p>

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

				<?php
				$dukaan_tags = get_the_tag_list( '<div class="dukaan-tags"><span>' . esc_html__( 'Tags:', 'dukaan' ) . '</span> ', ', ', '</div>' );
				if ( $dukaan_tags ) {
					echo wp_kses_post( $dukaan_tags );
				}
				?>
			</article>

			<nav class="dukaan-post-nav" aria-label="<?php esc_attr_e( 'Post navigation', 'dukaan' ); ?>">
				<?php
				the_post_navigation( array(
					'prev_text' => '&larr; %title',
					'next_text' => '%title &rarr;',
				) );
				?>
			</nav>

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
