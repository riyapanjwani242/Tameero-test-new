<?php
/**
 * TAMEERO AI Edit helper (v2.20) — panel isay CLI se bulata hai.
 * Sirf JSON output (ek object). Kaam: Edit Mode plugin ke TE_AI /
 * TE_Config ko site ke andar chala kar panel ko state, preview,
 * apply, history aur undo dena. Validation hamesha plugin ke
 * andar hoti hai (allowlist) — ye helper koi setting khud nahi
 * badalta.
 *
 * Usage:
 *   php ai-edit.php --path=<sitedir> --url=<siteurl> --state
 *   php ai-edit.php --path=... --url=... --preview --values-file=<json>
 *   php ai-edit.php --path=... --url=... --apply --values-file=<json> [--prompt=...]
 *   php ai-edit.php --path=... --url=... --history
 *   php ai-edit.php --path=... --url=... --undo --tx=<tx_id>
 *
 * values-file: JSON object { "<control-key>": <value>, ... }
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$opts = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([\w-]+)(?:=(.*))?$/', $a, $m ) ) { $opts[ $m[1] ] = $m[2] ?? true; }
}
$path = $opts['path'] ?? '';
$url  = $opts['url'] ?? '';
if ( ! $path || ! file_exists( $path . '/wp-load.php' ) ) {
	echo json_encode( [ 'error' => 'Site path ghalat hai' ] ) . "\n";
	exit( 1 );
}

define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
// CLI render mein SERVER_NAME nahi hota — WP us ki warning render
// HTML mein daal deta hai; URL se bhar do (render/preview dono ke liye).
if ( $url ) {
	$aeHost = parse_url( $url, PHP_URL_HOST );
	$aePort = parse_url( $url, PHP_URL_PORT );
	if ( $aeHost ) { $_SERVER['SERVER_NAME'] = $aeHost; }
	if ( $aePort ) { $_SERVER['SERVER_PORT'] = (string) $aePort; }
	$_SERVER['HTTP_HOST'] = $aeHost . ( $aePort ? ':' . $aePort : '' );
}
if ( $url ) {
	define( 'WP_SITEURL', $url );
	define( 'WP_HOME', $url );
}
if ( ! defined( 'WP_USE_THEMES' ) ) { define( 'WP_USE_THEMES', false ); }
require_once $path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function ae_out( $data ) { echo json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"; }
function ae_err( $msg, $extra = [] ) { ae_out( array_merge( [ 'error' => $msg ], $extra ) ); exit( 0 ); }

// Admin context: pehla administrator (Edit Mode ki capability check ke liye).
$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
if ( empty( $admins ) ) { ae_err( 'Site par koi administrator nahi mila' ); }
wp_set_current_user( (int) $admins[0] );

if ( ! class_exists( 'TE_AI' ) || ! function_exists( 'tameero_edit_available' ) ) {
	ae_err( 'Is site par Edit Mode plugin active nahi hai ya theme ke pas edit config nahi hai' );
}
if ( ! tameero_edit_available() ) {
	ae_err( 'Edit Mode is site par dastiyab nahi hai' );
}

function ae_request( $params ) {
	$r = new WP_REST_Request( 'POST' );
	foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); }
	return $r;
}
function ae_result( $res ) {
	if ( is_wp_error( $res ) ) {
		$d = $res->get_error_data();
		ae_err( $res->get_error_message(), [ 'code' => $res->get_error_code(), 'fields' => $d['fields'] ?? null ] );
	}
	ae_out( $res->get_data() );
	exit( 0 );
}
function ae_values() {
	global $opts;
	$file = $opts['values-file'] ?? '';
	if ( ! $file || ! file_exists( $file ) ) { ae_err( 'values-file nahi mili' ); }
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) { ae_err( 'values-file ka JSON durust nahi hai' ); }
	return $data;
}

/* ---------------- state: manifest ka AI khulasa ---------------- */
if ( isset( $opts['state'] ) ) {
	$config  = TE_Config::load();
	$current = TE_Config::current_values();
	$blocks  = [];
	$mk_block = static function ( $block, $is_global ) use ( $current ) {
		$controls = [];
		foreach ( (array) ( $block['controls'] ?? [] ) as $control ) {
			$key = TE_Config::control_key( $control );
			if ( null === $key || empty( $control['type'] ) ) { continue; }
			$c = [
				'key'     => $key,
				'label'   => (string) ( $control['label'] ?? $key ),
				'type'    => (string) $control['type'],
				'current' => $current[ $key ] ?? ( $control['default'] ?? null ),
			];
			if ( isset( $control['options'] ) ) {
				$c['options'] = array_map(
					static function ( $o ) { return [ 'key' => (string) ( $o['key'] ?? '' ), 'label' => (string) ( $o['label'] ?? ( $o['key'] ?? '' ) ) ]; },
					(array) $control['options']
				);
			}
			if ( isset( $control['presets'] ) ) { $c['presets'] = array_values( (array) $control['presets'] ); }
			if ( isset( $control['min'] ) ) { $c['min'] = $control['min']; }
			if ( isset( $control['max'] ) ) { $c['max'] = $control['max']; }
			$controls[] = $c;
		}
		return [
			'id'       => (string) ( $block['id'] ?? ( $is_global ? 'global' : '' ) ),
			'title'    => (string) ( $block['title'] ?? ( $is_global ? 'Global' : '' ) ),
			'global'   => $is_global,
			'controls' => $controls,
		];
	};
	foreach ( (array) ( $config['sections'] ?? [] ) as $section ) { $blocks[] = $mk_block( $section, false ); }
	$global = TE_Config::global_block();
	if ( $global && ! empty( $global['controls'] ) ) { $blocks[] = $mk_block( $global, true ); }
	ae_out( [ 'ok' => true, 'theme' => (string) ( $config['theme'] ?? '' ), 'blocks' => $blocks ] );
	exit( 0 );
}

/* ---------------- render: current homepage, baghair tabdeeli ke (v2.21) ----------------
   Edit Mode v2 ke preview canvas ke liye: asal theme rendering,
   sirf <base> tag ke sath taake assets site ke URL se load hon
   (TE_AI::preview wala hi tareeqa). Kuch save nahi hota. */
if ( isset( $opts['render'] ) ) {
	$html = TE_REST::render_homepage_html();
	if ( is_wp_error( $html ) ) { ae_err( $html->get_error_message() ); }
	$base = '<base href="' . esc_url( home_url( '/' ) ) . '">';
	if ( false !== strpos( $html, '<head>' ) ) {
		$html = preg_replace( '/<head>/', '<head>' . $base, $html, 1 );
	}
	ae_out( [ 'ok' => true, 'html' => $html ] );
	exit( 0 );
}

/* ---------------- preview / apply / history / undo ---------------- */
if ( isset( $opts['preview'] ) ) { ae_result( TE_AI::preview( ae_request( [ 'values' => ae_values() ] ) ) ); }
if ( isset( $opts['apply'] ) ) {
	ae_result( TE_AI::apply( ae_request( [ 'values' => ae_values(), 'prompt' => (string) ( $opts['prompt'] ?? '' ) ] ) ) );
}
if ( isset( $opts['history'] ) ) { ae_result( TE_AI::history( ae_request( [] ) ) ); }
if ( isset( $opts['undo'] ) ) { ae_result( TE_AI::undo( ae_request( [ 'tx' => (string) ( $opts['tx'] ?? '' ) ] ) ) ); }

ae_err( 'Mode nahi diya gaya (--state | --preview | --apply | --history | --undo)' );
