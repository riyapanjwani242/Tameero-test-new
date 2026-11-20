<?php
/**
 * Plugin Name:       TAMEERO YouTube
 * Plugin URI:        https://tameero.example
 * Description:       Halka YouTube video block — page/post mein YouTube link paste karein, tez click-to-play video lag jata hai. Koi API key nahi, koi iframe page load par nahi (sirf thumbnail; iframe sirf click par).
 * Version:           1.0.0
 * Author:            TAMEERO
 * Text Domain:       tameero-youtube
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package TameeroYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAMEERO_YOUTUBE_VERSION', '1.0.0' );
define( 'TAMEERO_YOUTUBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAMEERO_YOUTUBE_URL', plugin_dir_url( __FILE__ ) );

/* ---------------------------------------------------------------------
 * URL parser — sakht allowlist. Sirf video ID + start seconds nikalta
 * hai; raw URL/HTML kabhi store ya render nahi hota.
 * ------------------------------------------------------------------- */

/** Video ID durust hai? (11 harf, [A-Za-z0-9_-]) */
function tameero_youtube_valid_id( $id ) {
	return is_string( $id ) && (bool) preg_match( '/^[A-Za-z0-9_-]{11}$/', $id );
}

/** t= / start= ki value ko seconds mein badlo (90, 90s, 1m30s, 1h2m3s). */
function tameero_youtube_parse_start( $raw ) {
	if ( ! is_scalar( $raw ) ) { return 0; }
	$raw = trim( (string) $raw );
	if ( $raw === '' ) { return 0; }
	if ( ctype_digit( $raw ) ) { $s = (int) $raw; }
	elseif ( preg_match( '/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $raw, $m ) ) {
		$s = ( (int) ( $m[1] ?? 0 ) ) * 3600 + ( (int) ( $m[2] ?? 0 ) ) * 60 + ( (int) ( $m[3] ?? 0 ) );
	} else { return 0; }
	return ( $s > 0 && $s < 86400 ) ? $s : 0;
}

/**
 * YouTube URL (ya seedhi ID) se ['id' => ..., 'start' => ...] nikalo.
 * Ghalat/nakli host ya ghalat shakal par null.
 * Hostname ALLOWLIST: sirf asal YouTube domains — youtube.com.
 * attacker.example jaisi chaal reject hoti hai.
 */
function tameero_youtube_parse_url( $url ) {
	// Editor/shortcode mein & kabhi &amp; ban jata hai — pehle seedha karo.
	$url = str_replace( [ '&amp;', '&#038;' ], '&', trim( (string) $url ) );
	if ( $url === '' ) { return null; }
	if ( tameero_youtube_valid_id( $url ) ) { return [ 'id' => $url, 'start' => 0, 'shorts' => false ]; }
	if ( strpos( $url, '://' ) === false ) { $url = 'https://' . $url; }
	$parts = parse_url( $url );
	if ( ! $parts || empty( $parts['host'] ) ) { return null; }
	$host = strtolower( rtrim( $parts['host'], '.' ) );
	$hosts = [ 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
		'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com' ];
	if ( ! in_array( $host, $hosts, true ) ) { return null; }
	$query = [];
	if ( ! empty( $parts['query'] ) ) { parse_str( $parts['query'], $query ); }
	$start = 0;
	if ( isset( $query['t'] ) ) { $start = tameero_youtube_parse_start( $query['t'] ); }
	if ( $start === 0 && isset( $query['start'] ) ) { $start = tameero_youtube_parse_start( $query['start'] ); }
	$path = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' );
	$id = null;
	$shorts = false;
	if ( $host === 'youtu.be' || $host === 'www.youtu.be' ) {
		$seg = explode( '/', $path );
		$id = $seg[1] ?? null;
	} elseif ( $path === '/watch' ) {
		$id = $query['v'] ?? null; // list= waghera jaan boojh kar ignore
	} elseif ( preg_match( '#^/(?:shorts|embed|live|v)/([^/?&]+)#', $path, $m ) ) {
		$id = $m[1];
		$shorts = strpos( $path, '/shorts/' ) === 0;
	}
	if ( ! is_string( $id ) || ! tameero_youtube_valid_id( $id ) ) { return null; }
	return [ 'id' => $id, 'start' => $start, 'shorts' => ! empty( $shorts ) ];
}

/* ---------------------------------------------------------------------
 * Shared renderer — block aur shortcode dono yahin se render hote
 * hain (do implementations NAHI). Page par sirf thumbnail + play
 * button jata hai; iframe facade JS click par banata hai.
 * ------------------------------------------------------------------- */

function tameero_youtube_enqueue_assets() {
	wp_enqueue_style( 'tameero-youtube', TAMEERO_YOUTUBE_URL . 'assets/facade.css', [], TAMEERO_YOUTUBE_VERSION );
	wp_enqueue_script( 'tameero-youtube', TAMEERO_YOUTUBE_URL . 'assets/facade.js', [], TAMEERO_YOUTUBE_VERSION, true );
}

/**
 * @param array $attrs videoId|id, url, aspect ('16-9'|'9-16'), title, start
 */
function tameero_youtube_render( $attrs ) {
	$id = '';
	$start = 0;
	if ( ! empty( $attrs['videoId'] ) && tameero_youtube_valid_id( (string) $attrs['videoId'] ) ) {
		$id = (string) $attrs['videoId'];
	} elseif ( ! empty( $attrs['id'] ) && tameero_youtube_valid_id( (string) $attrs['id'] ) ) {
		$id = (string) $attrs['id'];
	} elseif ( ! empty( $attrs['url'] ) ) {
		$p = tameero_youtube_parse_url( (string) $attrs['url'] );
		if ( $p ) {
			$id = $p['id']; $start = $p['start'];
			// Shorts URL ho aur aspect wazeh na diya gaya ho to
			// vertical shakal khud chun lo.
			if ( ! empty( $p['shorts'] ) && empty( $attrs['aspect'] ) ) { $attrs['aspect'] = '9-16'; }
		}
	}
	if ( isset( $attrs['start'] ) && is_scalar( $attrs['start'] ) ) {
		$sv = (int) $attrs['start'];
		if ( $sv > 0 && $sv < 86400 ) { $start = $sv; }
	}
	if ( $id === '' ) {
		// Saaf fallback box — page kabhi nahi tootti, PHP error kabhi nahi.
		return '<div class="tameero-youtube tameero-youtube--invalid" role="note">'
			. '<span class="tameero-youtube__invalid-icon" aria-hidden="true">&#9654;</span> '
			. esc_html__( 'Video dastiyab nahi — link durust nahi hai.', 'tameero-youtube' )
			. '</div>';
	}
	$aspect = ( isset( $attrs['aspect'] ) && $attrs['aspect'] === '9-16' ) ? '9-16' : '16-9';
	$title = isset( $attrs['title'] ) ? trim( (string) $attrs['title'] ) : '';
	tameero_youtube_enqueue_assets();
	$thumb = 'https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg';
	$html = '<div class="tameero-youtube is-' . esc_attr( $aspect ) . '" data-video-id="' . esc_attr( $id ) . '"'
		. ( $start > 0 ? ' data-start="' . (int) $start . '"' : '' ) . '>'
		. '<div class="tameero-youtube__facade">'
		. '<img class="tameero-youtube__thumb" src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async">'
		. ( $aspect === '9-16' ? '<span class="tameero-youtube__badge">Shorts</span>' : '' )
		. '<button type="button" class="tameero-youtube__play" aria-label="'
		. esc_attr( $title !== '' ? $title : __( 'Video chalao', 'tameero-youtube' ) ) . '">'
		. '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
		. '</button></div>';
	if ( $title !== '' ) {
		$html .= '<div class="tameero-youtube__title">' . esc_html( $title ) . '</div>';
	}
	$html .= '</div>';
	return $html;
}

/* ---------------------------------------------------------------------
 * Gutenberg block (dynamic — render PHP se) + shortcode
 * ------------------------------------------------------------------- */

function tameero_youtube_register() {
	wp_register_script( 'tameero-youtube-editor', TAMEERO_YOUTUBE_URL . 'assets/editor.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ],
		TAMEERO_YOUTUBE_VERSION, true );
	wp_register_style( 'tameero-youtube-editor-style', TAMEERO_YOUTUBE_URL . 'assets/editor.css', [], TAMEERO_YOUTUBE_VERSION );
	wp_register_style( 'tameero-youtube', TAMEERO_YOUTUBE_URL . 'assets/facade.css', [], TAMEERO_YOUTUBE_VERSION );
	register_block_type( 'tameero/youtube', [
		'editor_script' => 'tameero-youtube-editor',
		'editor_style'  => 'tameero-youtube-editor-style',
		'style'         => 'tameero-youtube',
		'render_callback' => function ( $attrs ) { return tameero_youtube_render( (array) $attrs ); },
		'attributes' => [
			'videoId' => [ 'type' => 'string', 'default' => '' ],
			'url'     => [ 'type' => 'string', 'default' => '' ],
			'aspect'  => [ 'type' => 'string', 'default' => '16-9' ],
			'title'   => [ 'type' => 'string', 'default' => '' ],
			'start'   => [ 'type' => 'number', 'default' => 0 ],
		],
	] );
	add_shortcode( 'tameero_youtube', function ( $atts ) {
		$atts = is_array( $atts ) ? $atts : [];
		return tameero_youtube_render( [
			'id'     => $atts['id'] ?? '',
			'url'    => $atts['url'] ?? '',
			// aspect sirf tab bhejo jab user ne diya ho — warna
			// Shorts URL ki khud-kar pehchan (renderer) maregi.
			'aspect' => $atts['aspect'] ?? '',
			'title'  => $atts['title'] ?? '',
			'start'  => isset( $atts['start'] ) ? (int) $atts['start'] : 0,
		] );
	} );
}
add_action( 'init', 'tameero_youtube_register' );
