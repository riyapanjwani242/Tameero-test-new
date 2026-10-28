<?php
/**
 * Plugin Name: WebDevHub Core (Must-Use)
 * Description: WebDevHub ki pehchan: pehle admin run par default settings
 *              (Urdu, Asia/Karachi, permalinks) ek dafa apply karta hai.
 *              setup-site.php ye kaam pehle hi kar chuka ho to ye no-op hai.
 * Version: 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local HTTPS proxy ke peeche se ane wali requests (feature 19):
 * proxy X-Forwarded-Proto: https + X-WDH-HTTPS-Port bhejta hai —
 * siteurl/home usi https origin par kar do taake WP http par
 * redirect na kare aur saare URLs https ke banein.
 */
if ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' && ! empty( $_SERVER['HTTP_X_WDH_HTTPS_PORT'] ) ) {
	$wdhHttpsOrigin = 'https://127.0.0.1:' . (int) $_SERVER['HTTP_X_WDH_HTTPS_PORT'];
	$_SERVER['HTTPS'] = 'on';
	add_filter( 'pre_option_siteurl', function () use ( $wdhHttpsOrigin ) { return $wdhHttpsOrigin; } );
	add_filter( 'pre_option_home', function () use ( $wdhHttpsOrigin ) { return $wdhHttpsOrigin; } );
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
/**
 * Mail catcher (feature 6) — local site ki HAR outgoing email
 * asal mein kahin NAHI jati; wo wp-content/webdevhub-mail/ mein
 * JSON file ban kar mehfooz ho jati hai aur dashboard ke Mail
 * page par parhi ja sakti hai. Ye local development ke liye hain:
 * password-reset, order aur form ki emails test karna baghair
 * kisi asli inbox ke.
 */
add_filter( 'pre_wp_mail', function ( $shortCircuit, $atts ) {
	if ( $shortCircuit !== null ) {
		return $shortCircuit;
	}
	$dir = WP_CONTENT_DIR . '/webdevhub-mail';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.html', '' );
	}
	$mail = [
		'at'          => current_time( 'mysql' ),
		'to'          => $atts['to'] ?? '',
		'subject'     => $atts['subject'] ?? '',
		'message'     => $atts['message'] ?? '',
		'headers'     => $atts['headers'] ?? '',
		'attachments' => $atts['attachments'] ?? [],
	];
	$name = date( 'Ymd-His' ) . '-' . substr( md5( uniqid( '', true ) ), 0, 8 ) . '.json';
	@file_put_contents( $dir . '/' . $name, wp_json_encode( $mail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	return true; // "bhej di gayi" — asal mein catcher mein hai
}, 1, 2 );

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
