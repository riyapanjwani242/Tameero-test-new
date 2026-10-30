<?php
/**
 * Dukaan — footer: 3 widget columns + footer menu + note.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dukaan_footer_note = trim( (string) get_theme_mod( 'dukaan_footer_note', '' ) );
$dukaan_wa_link     = dukaan_whatsapp_link();
?>
<footer class="dukaan-footer">
	<div class="dukaan-container">
		<div class="dukaan-footer-cols">
			<div class="dukaan-footer-col">
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-1' ); ?>
				<?php else : ?>
					<section class="widget">
						<h3 class="widget-title"><?php bloginfo( 'name' ); ?></h3>
						<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
						<?php if ( '' !== $dukaan_wa_link ) : ?>
							<p><a class="dukaan-footer-wa" href="<?php echo esc_url( $dukaan_wa_link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WhatsApp par rabta karein', 'dukaan' ); ?></a></p>
						<?php endif; ?>
					</section>
				<?php endif; ?>
			</div>
			<div class="dukaan-footer-col">
				<?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
					<?php dynamic_sidebar( 'footer-2' ); ?>
				<?php else : ?>
					<section class="widget">
						<h3 class="widget-title"><?php esc_html_e( 'Quick Links', 'dukaan' ); ?></h3>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer',
							'menu_class'     => 'dukaan-footer-menu',
							'container'      => false,
							'fallback_cb'    => 'wp_page_menu',
							'depth'          => 1,
						) );
						?>
					</section>
				<?php endif; ?>
			</div>
			<div class="dukaan-footer-col">
				<?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
					<?php dynamic_sidebar( 'footer-3' ); ?>
				<?php else : ?>
					<section class="widget">
						<h3 class="widget-title"><?php esc_html_e( 'Madad', 'dukaan' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Delivery pure Pakistan mein.', 'dukaan' ); ?></li>
							<li><?php esc_html_e( 'Advance payment par order confirm hota hai.', 'dukaan' ); ?></li>
							<li><?php esc_html_e( 'Sawal ho to WhatsApp par message karein.', 'dukaan' ); ?></li>
						</ul>
					</section>
				<?php endif; ?>
			</div>
		</div>

		<div class="dukaan-footer-bottom">
			<p>
				&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				<span class="dukaan-footer-sep">·</span>
				<?php esc_html_e( 'Powered by WebDev Hub — Dukaan Theme', 'dukaan' ); ?>
			</p>
			<?php if ( '' !== $dukaan_footer_note ) : ?>
				<p class="dukaan-footer-note"><?php echo esc_html( $dukaan_footer_note ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
