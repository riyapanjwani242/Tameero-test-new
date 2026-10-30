<?php
/**
 * Dukaan — 404 page.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="dukaan-main">
	<div class="dukaan-container dukaan-narrow dukaan-404">
		<p class="dukaan-404-code" aria-hidden="true">404</p>
		<h1 class="dukaan-page-title"><?php esc_html_e( 'Ye page nahi mila', 'dukaan' ); ?></h1>
		<p><?php esc_html_e( 'Lagta hai ye link purana hai ya page hat gaya hai. Neeche se product dhoondh lein ya home page par wapas jayein.', 'dukaan' ); ?></p>

		<?php get_search_form(); ?>

		<p class="dukaan-404-home">
			<a class="dukaan-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home Page Par Jayein', 'dukaan' ); ?></a>
		</p>
	</div>
</main>
<?php
get_footer();
