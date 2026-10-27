<?php
/**
 * WebDevHub Dashboard ka router — PHP built-in server ke liye.
 * Saari requests dashboard (public/index.php) ko jati hain.
 */
if ( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) === '/favicon.ico' ) {
	http_response_code( 204 );
	return true;
}
require __DIR__ . '/public/index.php';
return true;
