<?php
/**
 * WebDevHub router — PHP built-in server ke liye.
 * Static file mojood ho to wahi serve hoti hai, warna saari
 * requests WordPress (index.php) ko jati hain — pretty permalinks
 * (/%postname%/) is ke baghair php -S par kaam nahi karte.
 */
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ( $path !== '/' && is_file( $file ) ) {
	return false; // static file — server khud serve kare
}
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
