<?php
// AI Image backend (hub_img_*) ka forced local test — mock gateway par.
// Chalana: php tests/img-gen-check.php [port]
// Settings file waqti tor par test values par jati hai aur aakhir mein
// (shutdown par bhi) asal halat par wapas ati hai. .ai-images test
// jobs safai ke sath khatam hote hain.
error_reporting( E_ALL & ~E_DEPRECATED );
$root = dirname( __DIR__ );
require $root . '/panel/lib/hub.php';

$port = (int) ( $argv[1] ?? 18899 );
$pass = 0; $fail = 0; $fails = [];
function chk( $cond, $label ) {
	global $pass, $fail, $fails;
	if ( $cond ) { $pass++; echo "PASS - $label\n"; }
	else { $fail++; $fails[] = $label; echo "FAIL - $label\n"; }
}

// Settings backup + test values
$sf = hub_settings_file();
$origSettings = file_exists( $sf ) ? (string) file_get_contents( $sf ) : null;
register_shutdown_function( function () use ( $sf, $origSettings ) {
	if ( $origSettings === null ) { if ( file_exists( $sf ) ) { unlink( $sf ); } }
	else { file_put_contents( $sf, $origSettings ); }
} );
hub_save_settings( [
	'img_provider' => 'gateway',
	'img_model' => 'mock/flash-image',
	'chat_gateway_url' => 'http://127.0.0.1:' . $port . '/v1',
	'chat_gateway_key' => 'test-key-kabhi-print-nahi',
] );

// Purani test cache saaf
@unlink( hub_img_dir() . '/models-cache.json' );

// 1) Models discovery (live)
$m = hub_img_models( true );
chk( $m['ok'] && count( $m['models'] ) === 2 && $m['source'] === 'live', 'models: live discovery 2 models' );
chk( in_array( 'mock/flash-image', $m['models'], true ), 'models: mock/flash-image maujood' );

// 2) Models cache
$m2 = hub_img_models();
chk( $m2['ok'] && $m2['source'] === 'cache', 'models: doosri call cache se' );

// 3) Generate kamyab (binary JPEG)
$r = hub_img_generate( 'aik laal seb safed background par', '1:1' );
chk( $r['ok'] === true, 'generate: ok' );
chk( ( $r['ext'] ?? '' ) === 'jpg', 'generate: ext jpg (magic bytes)' );
chk( ( $r['w'] ?? 0 ) === 120 && ( $r['h'] ?? 0 ) === 90, 'generate: dims 120x90 asal' );
$jobFile = hub_img_dir() . '/' . ( $r['job'] ?? 'x' ) . '/' . 'img.jpg';
chk( file_exists( $jobFile ) && filesize( $jobFile ) === ( $r['bytes'] ?? -1 ), 'generate: file asal size ke sath' );
$meta = hub_img_job( $r['job'] ?? '' );
chk( is_array( $meta ) && $meta['model'] === 'mock/flash-image', 'generate: meta.json durust' );

// 4) NEGATIVE: quota 429 -> 4004
$r = hub_img_generate( 'test', '1:1', 'mock/quota-x' );
chk( ! $r['ok'] && $r['code'] === 4004, 'negative: 429 -> code 4004 (had khatam)' );

// 5) NEGATIVE: auth 401 -> 4005
$r = hub_img_generate( 'test', '1:1', 'mock/auth-x' );
chk( ! $r['ok'] && $r['code'] === 4005, 'negative: 401 -> code 4005 (key)' );

// 6) NEGATIVE: safety 400 -> 4006
$r = hub_img_generate( 'test', '1:1', 'mock/safety-x' );
chk( ! $r['ok'] && $r['code'] === 4006, 'negative: safety block -> code 4006' );

// 7) NEGATIVE: bad magic -> 4003
$r = hub_img_generate( 'test', '1:1', 'mock/badmagic-x' );
chk( ! $r['ok'] && $r['code'] === 4003, 'negative: text jawab -> code 4003 (magic)' );

// 8) NEGATIVE: prompt khaali -> 4000
$r = hub_img_generate( '   ' );
chk( ! $r['ok'] && $r['code'] === 4000, 'negative: khaali prompt -> code 4000' );

// 9) NEGATIVE: gemini provider baghair key -> 4005
hub_save_settings( [ 'img_provider' => 'gemini', 'img_gemini_key' => '', 'img_model' => 'gemini-2.5-flash-image' ] );
$r = hub_img_generate( 'test' );
chk( ! $r['ok'] && $r['code'] === 4005, 'negative: gemini baghair key -> code 4005' );
hub_save_settings( [ 'img_provider' => 'gateway', 'img_model' => 'mock/flash-image' ] );

// 10) NEGATIVE: gateway down -> 4007 (generate)
hub_save_settings( [ 'chat_gateway_url' => 'http://127.0.0.1:18999/v1' ] );
$r = hub_img_generate( 'test' );
chk( ! $r['ok'] && $r['code'] === 4007, 'negative: gateway down -> code 4007' );
hub_save_settings( [ 'chat_gateway_url' => 'http://127.0.0.1:' . $port . '/v1' ] );

// 11) images.log mein codes darj hon, key kabhi na ho
$log = (string) @file_get_contents( hub_img_dir() . '/images.log' );
chk( strpos( $log, '[CODE:4004]' ) !== false && strpos( $log, '[CODE:4003]' ) !== false, 'log: failure codes darj hain' );
chk( strpos( $log, 'test-key-kabhi-print-nahi' ) === false, 'log: key kabhi print nahi hui' );

// Test jobs ki safai
foreach ( glob( hub_img_dir() . '/*', GLOB_ONLYDIR ) ?: [] as $d ) { hub_rrmdir( $d ); }
@unlink( hub_img_dir() . '/models-cache.json' );

echo "IMG-GEN CHECK: PASS=$pass FAIL=$fail\n";
if ( $fails ) { echo 'FAILED: ' . implode( ' | ', $fails ) . "\n"; }
exit( $fail > 0 ? 1 : 0 );
