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
require __DIR__ . '/public/index.php';
return true;
