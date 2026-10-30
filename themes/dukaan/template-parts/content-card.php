<?php
/**
 * Dukaan — post card (home fallback, archive, search grids).
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'dukaan-post-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="dukaan-post-thumb" href="<?php the_permalink(); ?>">
			<?php the_post_thumbnail( 'medium_large' ); ?>
		</a>
	<?php endif; ?>
	<div class="dukaan-post-body">
		<h3 class="dukaan-post-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h3>
		<p class="dukaan-post-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		</p>
		<div class="dukaan-post-excerpt">
			<?php the_excerpt(); ?>
		</div>
		<a class="dukaan-read-more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Mazeed Parhein', 'dukaan' ); ?> &rarr;</a>
	</div>
</article>
