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

/**
 * One-click admin login — sirf local dashboard ke liye.
 * Dashboard ek short-lived (120s) HMAC token banata hai jis ki kunji
 * site ke secret (webdevhub_secret option) mein hai. Token theek ho to
 * pehle administrator ko login karke /wp-admin/ bhej deta hai.
 * Secret na ho (purani sites) to ye feature khud-ba-khud band hai.
 */
add_action( 'init', function () {
	if ( empty( $_GET['wdh_login'] ) || empty( $_GET['wdh_exp'] ) ) {
		return;
	}
	$secret = get_option( 'webdevhub_secret' );
	if ( ! $secret ) {
		return;
	}
	$exp = (int) $_GET['wdh_exp'];
	if ( $exp < time() || $exp > time() + 300 ) {
		return;
	}
	$expected = hash_hmac( 'sha256', 'login:' . $exp, $secret );
	if ( ! hash_equals( $expected, sanitize_text_field( wp_unslash( $_GET['wdh_login'] ) ) ) ) {
		return;
	}
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ] );
	if ( ! $admins ) {
		return;
	}
	wp_set_current_user( $admins[0]->ID );
	wp_set_auth_cookie( $admins[0]->ID, true );
	wp_safe_redirect( admin_url() );
	exit;
} );
