<?php
/**
 * TAMEERO CI helper — Update System gate round ki tayyari (v2.15).
 *
 * Installed app ke mojooda version se teen farzi patches banata hai:
 *   A: base -> base+1  (VERSION + panel/updtest-marker.txt NAYI file +
 *      panel/lib/hub.php mein aakhir par comment marker)
 *   B: base+1 -> base+2 (VERSION + A ki marker file DELETE)
 *   BAD: base+2 -> base+3 (zip durust, lekin is ki manifest entry ka
 *      sha256 jaan boojh kar GHALAT — apply mustarad hona chahiye)
 * Channel files: stable.json (A+B), stable-bad.json (sirf BAD),
 * stable-good.json (stable.json ki copy — bad test ke baad wapas
 * rakhne ke liye).
 *
 * Usage: php tests/upd-round.php setup <appDir> <chanDir>
 * Output: aik line JSON {base, a, b, bad}
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }
if ( ( $argv[1] ?? '' ) !== 'setup' || empty( $argv[2] ) || empty( $argv[3] ) ) {
	fwrite( STDERR, "Usage: php upd-round.php setup <appDir> <chanDir>\n" );
	exit( 2 );
}
$appDir  = rtrim( (string) $argv[2], '/\\' );
$chanDir = rtrim( (string) $argv[3], '/\\' );
$verFile = $appDir . '/VERSION';
if ( ! file_exists( $verFile ) ) { fwrite( STDERR, "VERSION nahi mili: $verFile\n" ); exit( 2 ); }
$base = trim( (string) file_get_contents( $verFile ) );
$parts = explode( '.', $base );
$parts[2] = (string) ( (int) ( $parts[2] ?? 0 ) + 1 ); $verA = implode( '.', $parts );
$parts[2] = (string) ( (int) $parts[2] + 1 );           $verB = implode( '.', $parts );
$parts[2] = (string) ( (int) $parts[2] + 1 );           $verBad = implode( '.', $parts );

if ( ! is_dir( $chanDir . '/patches' ) ) { mkdir( $chanDir . '/patches', 0777, true ); }

function upd_patch_zip( $path, $meta, $files ) {
	$zip = new ZipArchive();
	if ( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
		fwrite( STDERR, "zip nahi bana: $path\n" ); exit( 2 );
	}
	$list = [];
	foreach ( $files as $p => $content ) {
		$zip->addFromString( $p, $content );
		$list[] = [ 'path' => $p, 'sha256' => hash( 'sha256', $content ) ];
	}
	$meta['files'] = $list;
	$zip->addFromString( 'patch.json', json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	$zip->close();
	return [ 'size' => filesize( $path ), 'sha256' => hash_file( 'sha256', $path ) ];
}

$hubSrc = (string) file_get_contents( $appDir . '/panel/lib/hub.php' );
$a = upd_patch_zip( $chanDir . '/patches/patch-a.zip',
	[ 'from' => $base, 'to' => $verA, 'date' => date( 'd M Y' ), 'notes' => [ 'gate test patch A' ], 'deleted' => [] ],
	[ 'VERSION' => $verA . "\n", 'panel/updtest-marker.txt' => "gate marker A\n", 'panel/lib/hub.php' => $hubSrc . "\n/* updtest A */\n" ] );
$b = upd_patch_zip( $chanDir . '/patches/patch-b.zip',
	[ 'from' => $verA, 'to' => $verB, 'date' => date( 'd M Y' ), 'notes' => [ 'gate test patch B' ], 'deleted' => [ 'panel/updtest-marker.txt' ] ],
	[ 'VERSION' => $verB . "\n" ] );
$bad = upd_patch_zip( $chanDir . '/patches/patch-bad.zip',
	[ 'from' => $verB, 'to' => $verBad, 'date' => date( 'd M Y' ), 'notes' => [ 'gate test patch BAD' ], 'deleted' => [] ],
	[ 'VERSION' => $verBad . "\n" ] );
$badSha = strrev( substr( $bad['sha256'], 0, 16 ) ) . substr( $bad['sha256'], 16 ); // jaan boojh kar ghalat

$good = [
	'latest' => $verB, 'full_installer_url' => '',
	'patches' => [
		[ 'from' => $base, 'to' => $verA, 'file' => 'patches/patch-a.zip', 'size' => $a['size'], 'sha256' => $a['sha256'], 'notes' => [ 'gate test patch A' ], 'date' => date( 'd M Y' ) ],
		[ 'from' => $verA, 'to' => $verB, 'file' => 'patches/patch-b.zip', 'size' => $b['size'], 'sha256' => $b['sha256'], 'notes' => [ 'gate test patch B' ], 'date' => date( 'd M Y' ) ],
	],
];
file_put_contents( $chanDir . '/stable.json', json_encode( $good, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
file_put_contents( $chanDir . '/stable-good.json', json_encode( $good, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
$badMf = [
	'latest' => $verBad, 'full_installer_url' => '',
	'patches' => [
		[ 'from' => $verB, 'to' => $verBad, 'file' => 'patches/patch-bad.zip', 'size' => $bad['size'], 'sha256' => $badSha, 'notes' => [ 'gate test patch BAD' ], 'date' => date( 'd M Y' ) ],
	],
];
file_put_contents( $chanDir . '/stable-bad.json', json_encode( $badMf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

echo json_encode( [ 'base' => $base, 'a' => $verA, 'b' => $verB, 'bad' => $verBad ] ) . "\n";
