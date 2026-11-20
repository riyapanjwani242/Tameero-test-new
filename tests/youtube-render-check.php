<?php
/**
 * v2.19: YouTube block asal render check — kisi bani hui site ke
 * andar page bana kar the_content render ginti deta hai (JSON).
 * Istemal (CLI): php youtube-render-check.php <site-path> <site-url>
 * Zarurat: site mein tameero-youtube plugin ACTIVE ho.
 */
error_reporting( E_ALL & ~E_DEPRECATED );
if ( php_sapi_name() !== 'cli' || $argc < 3 ) { fwrite( STDERR, "usage: youtube-render-check.php <site-path> <site-url>\n" ); exit( 2 ); }
$sitePath = rtrim( $argv[1], '/' );
$urlParts = parse_url( $argv[2] );
$_SERVER['HTTP_HOST'] = $urlParts['host'] . ( isset( $urlParts['port'] ) ? ':' . $urlParts['port'] : '' );
$_SERVER['REQUEST_URI'] = '/';
require $sitePath . '/wp-load.php';

$out = [ 'plugin_active' => false, 'page_id' => 0, 'facade' => 0, 'play' => 0, 'invalid' => 0, 'shorts' => 0, 'start30' => 0, 'iframe' => 0 ];
$out['plugin_active'] = is_plugin_active( 'tameero-youtube/tameero-youtube.php' );
if ( ! $out['plugin_active'] ) { echo json_encode( $out ) . "\n"; exit( 0 ); }

$content = '<!-- wp:tameero/youtube {"videoId":"dQw4w9WgXcQ","title":"Gate Wide"} /-->'
	. '[tameero_youtube url="https://www.youtube.com/watch?v=jNQXAC9IVRw&t=30" title="Gate HQ"]'
	. '[tameero_youtube url="https://www.youtube.com/shorts/dQw4w9WgXcQ"]'
	. '[tameero_youtube url="https://example.com/ghalat"]';
$existing = get_page_by_path( 'tameero-youtube-gate', OBJECT, 'page' );
if ( $existing ) { wp_delete_post( $existing->ID, true ); }
$pageId = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'TAMEERO YouTube Gate', 'post_name' => 'tameero-youtube-gate', 'post_content' => $content ] );
$out['page_id'] = (int) $pageId;
$post = get_post( $pageId );
$rendered = apply_filters( 'the_content', $post->post_content );
$out['facade'] = substr_count( $rendered, 'tameero-youtube__facade' );
$out['play'] = substr_count( $rendered, 'tameero-youtube__play' );
$out['invalid'] = substr_count( $rendered, 'tameero-youtube--invalid' );
$out['shorts'] = substr_count( $rendered, 'is-9-16' );
$out['start30'] = substr_count( $rendered, 'data-start="30"' );
$out['iframe'] = substr_count( $rendered, '<iframe' );
echo json_encode( $out ) . "\n";
