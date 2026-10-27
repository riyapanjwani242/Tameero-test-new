<?php
/**
 * Plugin Name: WebDevHub Core (Must-Use)
 * Description: WebDevHub ki pehchan: pehle admin run par default settings
 *              (Urdu, Asia/Karachi, permalinks) ek dafa apply karta hai.
 *              setup-site.php ye kaam pehle hi kar chuka ho to ye no-op hai.
 * Version: 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', function () {
	if ( get_option( 'webdevhub_configured_v2' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	update_option( 'WPLANG', 'ur' );
	update_option( 'timezone_string', 'Asia/Karachi' );
	update_option( 'gmt_offset', 5 );
	update_option( 'date_format', 'd/m/Y' );
	update_option( 'time_format', 'h:i a' );

	global $wp_rewrite;
	if ( $wp_rewrite ) {
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( true );
	}

	update_option( 'webdevhub_configured_v2', time() );
}, 1 );
