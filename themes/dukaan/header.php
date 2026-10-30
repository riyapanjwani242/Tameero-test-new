<?php
/**
 * Dukaan — header: announcement bar, branding, nav, search, cart.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dukaan_announcement_on   = (bool) get_theme_mod( 'dukaan_announcement_on', true );
$dukaan_announcement_text = trim( (string) get_theme_mod( 'dukaan_announcement_text', '' ) );
$dukaan_has_woo           = class_exists( 'WooCommerce' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'dukaan' ); ?></a>

<?php if ( $dukaan_announcement_on && '' !== $dukaan_announcement_text ) : ?>
<div class="dukaan-announcement" id="dukaan-announcement">
	<p class="dukaan-announcement-text"><?php echo esc_html( $dukaan_announcement_text ); ?></p>
	<button type="button" class="dukaan-announcement-close" id="dukaan-announcement-close" aria-label="<?php esc_attr_e( 'Band karein', 'dukaan' ); ?>">&times;</button>
</div>
<?php endif; ?>

<header class="dukaan-header">
	<div class="dukaan-container dukaan-header-inner">
		<div class="dukaan-branding">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				?>
				<a class="dukaan-site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php
				$dukaan_desc = get_bloginfo( 'description', 'display' );
				if ( $dukaan_desc ) {
					?>
					<p class="dukaan-site-desc"><?php echo esc_html( $dukaan_desc ); ?></p>
					<?php
				}
			}
			?>
		</div>

		<nav class="dukaan-nav" aria-label="<?php esc_attr_e( 'Primary', 'dukaan' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'menu_class'     => 'dukaan-nav-list',
				'container'      => false,
				'fallback_cb'    => false,
				'depth'          => 2,
			) );
			?>
		</nav>

		<div class="dukaan-header-actions">
			<button type="button" class="dukaan-icon-btn" id="dukaan-search-toggle" aria-expanded="false" aria-controls="dukaan-searchbar" aria-label="<?php esc_attr_e( 'Search', 'dukaan' ); ?>">&#128269;</button>
			<?php if ( $dukaan_has_woo && function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="dukaan-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Cart', 'dukaan' ); ?>">
					<span class="dukaan-cart-icon" aria-hidden="true">&#128722;</span>
					<span class="dukaan-cart-count"><?php echo esc_html( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
				</a>
			<?php endif; ?>
			<button type="button" class="dukaan-icon-btn dukaan-menu-btn" id="dukaan-menu-toggle" aria-expanded="false" aria-controls="dukaan-mobile-nav" aria-label="<?php esc_attr_e( 'Menu', 'dukaan' ); ?>">&#9776;</button>
		</div>
	</div>

	<div class="dukaan-searchbar" id="dukaan-searchbar" hidden>
		<div class="dukaan-container">
			<form role="search" method="get" class="dukaan-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="dukaan-search-field"><?php esc_html_e( 'Search', 'dukaan' ); ?></label>
				<input type="search" id="dukaan-search-field" name="s" placeholder="<?php esc_attr_e( 'Product dhoondhein…', 'dukaan' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
				<?php if ( $dukaan_has_woo ) : ?>
					<input type="hidden" name="post_type" value="product">
				<?php endif; ?>
				<button type="submit"><?php esc_html_e( 'Search', 'dukaan' ); ?></button>
			</form>
		</div>
	</div>

	<nav class="dukaan-mobile-nav" id="dukaan-mobile-nav" hidden aria-label="<?php esc_attr_e( 'Mobile', 'dukaan' ); ?>">
		<?php
		wp_nav_menu( array(
			'theme_location' => 'primary',
			'menu_class'     => 'dukaan-mobile-nav-list',
			'container'      => false,
			'fallback_cb'    => false,
			'depth'          => 1,
		) );
		?>
	</nav>
</header>
