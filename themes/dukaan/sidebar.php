<?php
/**
 * Dukaan — shop sidebar (sidebar-1).
 * WooCommerce apne archive pages par get_sidebar() se ye bulata hai.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<aside id="secondary" class="dukaan-sidebar widget-area">
	<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	<?php else : ?>
		<section class="widget">
			<h3 class="widget-title"><?php esc_html_e( 'Search', 'dukaan' ); ?></h3>
			<?php get_search_form(); ?>
		</section>
		<section class="widget">
			<h3 class="widget-title"><?php esc_html_e( 'Categories', 'dukaan' ); ?></h3>
			<ul>
				<?php wp_list_categories( array( 'title_li' => '' ) ); ?>
			</ul>
		</section>
		<section class="widget">
			<h3 class="widget-title"><?php esc_html_e( 'Taza Posts', 'dukaan' ); ?></h3>
			<ul>
				<?php
				wp_get_archives( array(
					'type'  => 'postbypost',
					'limit' => 5,
				) );
				?>
			</ul>
		</section>
	<?php endif; ?>
</aside>
