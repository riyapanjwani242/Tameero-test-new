<?php
/**
 * TAMEERO — patch builder (release tooling, v2.15).
 *
 * Do git refs ke darmiyan ka patch ZIP banata hai jo Update System
 * (hub_upd_apply_patch) seedha laga sakta hai. Files installed-layout
 * paths par jati hain: panel/... -> panel/..., installer-linux ki 10
 * listed files -> tools/..., VERSION -> VERSION.
 *
 * SAKHT USOOL: agar diff mein koi aisi file ho jo installed layout
 * mein patch se nahi ja sakti (bundle/, runtime/, themes/plugins jo
 * bundle se jate hain, installer-windows/, tests/ wagerah) to tool
 * RUK jata hai aur koi patch NAHI banta — aisi tabdeeli ke liye pura
 * installer chahiye. (--allow-skip sirf un files ke liye jo installed
 * layout mein jati hi nahi aur Windows user ke liye ghair-zaroori
 * hon — masalan docs/ ya tests/; bundle/runtime par ye flag bhi
 * kaam NAHI karta.)
 *
 * Usage:
 *   php tools/make-patch.php --from=<git ref> --to=<git ref> \
 *       --notes-file=<path> [--out=<dir>] [--date="20 Nov 2026"] \
 *       [--update-manifest] [--allow-skip]
 * Notes file: har line aik what's-new note.
 * Default out: updates-channel/patches/
 * --update-manifest: updates-channel/stable.json mein entry jama
 *   karta hai (latest + patches[]), zip wahi rakhta hai.
 */
if ( php_sapi_name() !== 'cli' ) { fwrite( STDERR, "CLI only\n" ); exit( 2 ); }

$opts = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--' ) !== 0 ) { continue; }
	$kv = substr( $a, 2 );
	if ( strpos( $kv, '=' ) !== false ) { [ $k, $v ] = explode( '=', $kv, 2 ); $opts[ $k ] = $v; }
	else { $opts[ $kv ] = true; }
}
function mp_fail( $msg ) { fwrite( STDERR, "NAKAM: $msg\n" ); exit( 1 ); }

$from = (string) ( $opts['from'] ?? '' );
$to   = (string) ( $opts['to'] ?? '' );
if ( $from === '' || $to === '' ) { mp_fail( '--from aur --to (git refs) lazmi hain' ); }
$root = dirname( __DIR__ );

function mp_git( $root, $args ) {
	$cmd = 'git -C ' . escapeshellarg( $root ) . ' ' . $args;
	$out = shell_exec( $cmd );
	return $out === null ? '' : $out;
}
function mp_ref_version( $root, $ref ) {
	$v = trim( (string) mp_git( $root, 'show ' . escapeshellarg( $ref ) . ':VERSION 2>/dev/null' ) );
	return $v;
}
$fromVer = mp_ref_version( $root, $from );
$toVer   = mp_ref_version( $root, $to );
if ( $fromVer === '' || $toVer === '' ) { mp_fail( "VERSION kisi ref mein nahi mili (from=$from, to=$to)" ); }
if ( $fromVer === $toVer ) { mp_fail( "Dono refs ka version aik hai ($fromVer) - patch ka koi matlab nahi" ); }

$notes = [];
if ( ! empty( $opts['notes-file'] ) ) {
	if ( ! file_exists( (string) $opts['notes-file'] ) ) { mp_fail( 'notes file nahi mili: ' . $opts['notes-file'] ); }
	foreach ( file( (string) $opts['notes-file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( $line ); if ( $line !== '' ) { $notes[] = $line; }
	}
}
if ( ! $notes ) { mp_fail( '--notes-file se kam az kam aik note lazmi hai' ); }
$date = ! empty( $opts['date'] ) ? (string) $opts['date'] : date( 'd M Y' );

// Installed layout mein tools/ par sirf ye 10 files jati hain (.iss se)
$TOOL_FILES = [ 'setup-site.php', 'router.php', 'reset-admin.php', 'fix-url.php',
	'demo-content.php', 'demo-delete.php', 'plugin-toggle.php', 'wc-tool.php',
	'https-proxy.php', 'deploy-dump.php' ];
// Ye hamesha rukawat hain (full installer ki tabdeeliyan) - flag se bhi nahi
$HARD_BLOCK = [ 'bundle/', 'runtime/', 'installer-windows/' ];

$diff = mp_git( $root, 'diff --name-status ' . escapeshellarg( $from ) . ' ' . escapeshellarg( $to ) );
if ( trim( $diff ) === '' ) { mp_fail( 'Diff khaali hai - koi tabdeeli nahi' ); }

$files = [];    // [installed path => repo path at <to>]
$deleted = [];  // installed paths
$blocked = [];  // rukawat wali files
$skipped = [];  // --allow-skip par chhori gayi (installed nahi jatin)
foreach ( explode( "\n", trim( $diff ) ) as $line ) {
	$parts = explode( "\t", $line );
	$st = $parts[0];
	// rename: R100 old new -> delete old + add new
	$paths = ( $st[0] === 'R' || $st[0] === 'C' ) ? [ [ 'D', $parts[1] ], [ 'A', $parts[2] ] ] : [ [ $st[0], $parts[1] ] ];
	foreach ( $paths as [ $op, $path ] ) {
		$inst = null; $hard = false;
		foreach ( $HARD_BLOCK as $hb ) { if ( strpos( $path, $hb ) === 0 ) { $hard = true; break; } }
		if ( strpos( $path, 'panel/' ) === 0 ) {
			// runtime state patch mein kabhi nahi (seed history bhi nahi)
			if ( strpos( $path, 'panel/.updates/' ) === 0 || strpos( $path, 'panel/ai-brain/' ) === 0 ) { $skipped[] = $path . ' (runtime/user state)'; continue; }
			$inst = $path;
		} elseif ( $path === 'VERSION' ) { $inst = 'VERSION'; }
		elseif ( strpos( $path, 'installer-linux/' ) === 0 ) {
			$base = basename( $path );
			if ( in_array( $base, $TOOL_FILES, true ) ) { $inst = 'tools/' . $base; }
		}
		if ( $hard ) { $blocked[] = $path . ' (HARD: full installer chahiye)'; continue; }
		if ( $inst === null ) {
			if ( ! empty( $opts['allow-skip'] ) ) { $skipped[] = $path; }
			else { $blocked[] = $path . ' (installed layout mein nahi jati)'; }
			continue;
		}
		if ( $op === 'D' ) { $deleted[] = $inst; }
		else { $files[ $inst ] = $path; }
	}
}
if ( $blocked ) {
	echo "RUKAWAT - ye files patch mein nahi ja saktein:\n";
	foreach ( $blocked as $b ) { echo "  - $b\n"; }
	echo "Patch NAHI bana. Baray badlav ke liye pura installer banao.\n";
	exit( 1 );
}
if ( $skipped ) {
	echo "Note: ye files patch se bahar rakhi gayin:\n";
	foreach ( $skipped as $s ) { echo "  - $s\n"; }
}

$zipName = 'patch-' . $fromVer . '-to-' . $toVer . '.zip';
$outDir = ! empty( $opts['out'] ) ? (string) $opts['out'] : ( $root . '/updates-channel/patches' );
if ( ! is_dir( $outDir ) ) { mkdir( $outDir, 0777, true ); }
$zipPath = $outDir . '/' . $zipName;

$patchFiles = [];
$zip = new ZipArchive();
if ( $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) { mp_fail( "Zip nahi ban saka: $zipPath" ); }
foreach ( $files as $inst => $repoPath ) {
	$content = mp_git( $root, 'show ' . escapeshellarg( $to . ':' . $repoPath ) );
	$zip->addFromString( $inst, $content );
	$patchFiles[] = [ 'path' => $inst, 'sha256' => hash( 'sha256', $content ) ];
}
$patchMeta = [
	'from' => $fromVer, 'to' => $toVer, 'date' => $date, 'notes' => $notes,
	'files' => $patchFiles, 'deleted' => $deleted,
];
$zip->addFromString( 'patch.json', json_encode( $patchMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
$zip->close();

// Self-check: zip dobara khol kar har file ka hash milao
$zipSize = filesize( $zipPath );
$zipHash = hash_file( 'sha256', $zipPath );
$chk = new ZipArchive();
if ( $chk->open( $zipPath ) !== true ) { mp_fail( 'Self-check: zip dobara nahi khula' ); }
$metaBack = json_decode( (string) $chk->getFromName( 'patch.json' ), true );
foreach ( $metaBack['files'] as $f ) {
	$c = $chk->getFromName( $f['path'] );
	if ( $c === false || hash( 'sha256', $c ) !== $f['sha256'] ) { $chk->close(); mp_fail( 'Self-check FAIL: ' . $f['path'] ); }
}
$chk->close();

echo "PATCH TAYYAR\n";
echo "  from $fromVer -> to $toVer  ($date)\n";
echo "  files: " . count( $patchFiles ) . ', deleted: ' . count( $deleted ) . "\n";
echo "  zip: $zipPath\n";
echo '  size: ' . $zipSize . ' bytes, sha256: ' . $zipHash . "\n";

if ( ! empty( $opts['update-manifest'] ) ) {
	$mfPath = $root . '/updates-channel/stable.json';
	$mf = file_exists( $mfPath ) ? json_decode( (string) file_get_contents( $mfPath ), true ) : [ 'latest' => $toVer, 'patches' => [], 'full_installer_url' => '' ];
	if ( ! is_array( $mf ) ) { mp_fail( 'stable.json kharab hai' ); }
	foreach ( (array) ( $mf['patches'] ?? [] ) as $p ) {
		if ( ( $p['from'] ?? '' ) === $fromVer && ( $p['to'] ?? '' ) === $toVer ) { mp_fail( "Manifest mein ye entry pehle se hai ($fromVer -> $toVer)" ); }
	}
	$mf['latest'] = $toVer;
	$mf['patches'][] = [
		'from' => $fromVer, 'to' => $toVer, 'file' => 'patches/' . $zipName,
		'size' => $zipSize, 'sha256' => $zipHash, 'notes' => $notes, 'date' => $date,
	];
	file_put_contents( $mfPath, json_encode( $mf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	echo "  manifest: entry jama ho gayi (latest=$toVer)\n";
}
echo "Agla qadam: updates-channel ko Cloudflare Pages (tameero-updates) par deploy karo.\n";
