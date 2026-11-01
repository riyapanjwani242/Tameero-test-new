<?php
/**
 * TAMEERO build-time compatibility guard (v2.6.3, Phase A-4).
 *
 * Usool: bundle ke jore ki compatibility CONSTANTS se nahi, ASAL
 * payload files se parhi jati hai:
 *   - WordPress ka version: chuni gayi WP zip ke andar
 *     wordpress/wp-includes/version.php ka $wp_version
 *   - Har plugin ki shart: us ki zip ke readme.txt (ya main file
 *     ke header) ki "Requires at least" line
 * Kisi bhi plugin ki shart bundled WP se unchi ho to build FAIL
 * (exit 1) — incompatible jora kabhi ship nahi hoga.
 *
 * Usage: php tools/compat-check.php [--bundle=<path>]
 * Default bundle: is repo ki bundle/ folder.
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$bundle = dirname( __DIR__ ) . '/bundle';
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--bundle=(.+)$/', $a, $m ) ) { $bundle = $m[1]; }
}
if ( ! is_dir( $bundle ) ) { fwrite( STDERR, "Bundle nahi mila: $bundle\n" ); exit( 2 ); }

function cc_fail( $msg ) { fwrite( STDERR, "COMPAT FAIL: $msg\n" ); exit( 1 ); }

/* ---- WP zip ka intekhaab: manifest pin, warna sab se unchi ---- */
$manifest = json_decode( (string) @file_get_contents( $bundle . '/manifest.json' ), true );
$wpPin = (string) ( $manifest['wordpress']['version'] ?? '' );
$wpZip = null;
if ( $wpPin !== '' && file_exists( "$bundle/wordpress/dist/wordpress-$wpPin.zip" ) ) {
	$wpZip = "$bundle/wordpress/dist/wordpress-$wpPin.zip";
} else {
	$bestVer = null;
	foreach ( glob( "$bundle/wordpress/dist/wordpress-*.zip" ) ?: [] as $z ) {
		if ( preg_match( '/wordpress-(\d+\.\d+(?:\.\d+)?)\.zip$/', basename( $z ), $mm )
			&& ( $bestVer === null || version_compare( $mm[1], $bestVer, '>' ) ) ) {
			$wpZip = $z; $bestVer = $mm[1];
		}
	}
}
if ( ! $wpZip ) { cc_fail( 'WP dist zip nahi mili' ); }

$zip = new ZipArchive();
if ( $zip->open( $wpZip ) !== true ) { cc_fail( "WP zip nahi khuli: $wpZip" ); }
$verPhp = $zip->getFromName( 'wordpress/wp-includes/version.php' );
$zip->close();
if ( $verPhp === false || ! preg_match( "/\\\$wp_version = '([^']+)'/", $verPhp, $m ) ) {
	cc_fail( 'WP zip ke andar $wp_version nahi mila' );
}
$wpVersion = $m[1];
echo "WordPress (payload): $wpVersion  [" . basename( $wpZip ) . "]\n";

/* ---- Plugin zips: manifest wali + sqlite ---- */
$pluginFiles = [];
$pm = json_decode( (string) @file_get_contents( $bundle . '/plugins/manifest.json' ), true );
foreach ( (array) ( $pm['plugins'] ?? [] ) as $p ) {
	$url = (string) ( $p['url'] ?? '' );
	if ( strpos( $url, 'https://downloads.wordpress.org' ) === 0 ) {
		$pluginFiles[ (string) ( $p['slug'] ?? basename( $url ) ) ] = "$bundle/plugins/dist/" . basename( $url );
	}
}
$pluginFiles['sqlite-database-integration'] = "$bundle/plugins/dist/sqlite-database-integration.zip";

$bad = 0;
foreach ( $pluginFiles as $slug => $file ) {
	if ( ! file_exists( $file ) ) { echo str_pad( $slug, 34 ) . "— zip mojood nahi (skip)\n"; continue; }
	$z = new ZipArchive();
	if ( $z->open( $file ) !== true ) { cc_fail( "Plugin zip nahi khuli: $file" ); }
	$requires = '';
	$version = '';
	for ( $i = 0; $i < $z->numFiles; $i++ ) {
		$name = $z->getNameIndex( $i );
		if ( substr( $name, -10 ) === 'readme.txt' ) {
			$txt = (string) $z->getFromIndex( $i );
			if ( preg_match( '/Requires at least:\s*([0-9.]+)/i', $txt, $rm )
				&& ( $requires === '' || version_compare( $rm[1], $requires, '>' ) ) ) { $requires = $rm[1]; }
		}
		if ( substr( $name, -4 ) === '.php' && substr_count( rtrim( $name, '/' ), '/' ) <= 1 && $version === '' ) {
			$head = (string) $z->getFromIndex( $i, 8192, ZipArchive::FL_UNCHANGED );
			if ( preg_match( '/Plugin Name:\s*(.+)/i', $head ) && preg_match( '/Version:\s*([0-9][^\s*]*)/i', $head, $vm ) ) {
				$version = trim( $vm[1] );
				// Header ki shart readme se sakht ho sakti hai (Woo:
				// readme 6.8, header 7.0 — activation header wali
				// dekhta hai). Dono mein se UNCHI shart laagu hai.
				if ( preg_match( '/Requires at least:\s*([0-9.]+)/i', $head, $rm )
					&& ( $requires === '' || version_compare( $rm[1], $requires, '>' ) ) ) { $requires = $rm[1]; }
			}
		}
	}
	$z->close();
	$status = 'OK';
	if ( $requires !== '' && version_compare( $requires, $wpVersion, '>' ) ) { $status = 'INCOMPATIBLE'; $bad++; }
	echo str_pad( $slug, 34 ) . str_pad( $version ?: '?', 12 )
		. 'requires WP >= ' . str_pad( $requires ?: '(koi shart nahi)', 18 ) . $status . "\n";
}

if ( $bad > 0 ) { cc_fail( "$bad plugin(s) bundled WP $wpVersion se incompatible hain" ); }
echo "COMPAT PASS — saare plugins WP $wpVersion ke sath compatible hain.\n";
exit( 0 );
