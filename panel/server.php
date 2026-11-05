<?php
/**
 * WebDevHub Dashboard ka router — PHP built-in server ke liye.
 * Saari requests dashboard (public/index.php) ko jati hain.
 */
if ( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) === '/favicon.ico' ) {
	http_response_code( 204 );
	return true;
}
// v2.8: dashboard ki static assets (panel.css/panel.js) seedha serve
// hon — built-in server ki file (return false) inhein khud de deta
// hai; baqi sab kuch index.php sambhalta hai.
$__asset = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
// '..' kabhi nahi — is_file() traversal resolve kar deta hai
// (panel/.settings.json tak pahunch sakta hai); built-in server
// aaj dot-segment normalize kar deta hai, lekin is par bharosa
// nahi — ye check us se azad hifazat hai.
if ( strpos( (string) $__asset, '/assets/' ) === 0 && strpos( (string) $__asset, '..' ) === false && is_file( __DIR__ . '/public' . $__asset ) ) {
	return false;
}
// v2.8.14: chunked import CORS preflight - dashboard origin (7777)
// se companion origin (7827) par POST + custom headers. Sirf
// 127.0.0.1/localhost origin reflect karo; baqi ko khaali 204 do.
if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'OPTIONS' ) {
	$__o = (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' );
	if ( preg_match( '#^https?://(127\.0\.0\.1|localhost)(:\d+)?$#', $__o ) ) {
		header( 'Access-Control-Allow-Origin: ' . $__o );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: ' . ( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, X-WDH-Action, X-CSRF-Token, X-Upload-Id, X-Chunk-Index, X-Chunk-Total, X-Chunk-Sha256' ) );
		header( 'Access-Control-Max-Age: 600' );
	}
	http_response_code( 204 );
	return true;
}
require __DIR__ . '/public/index.php';
return true;
