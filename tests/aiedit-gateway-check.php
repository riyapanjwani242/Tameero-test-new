<?php
// AI Edit gateway call ka regression test (v2.20.1).
// Wajah: v2.20.0 mein hub_aiedit_gateway_call() body ko string bana kar
// hub_img_http() ko deta tha, jo use dobara encode kar deta tha — asal
// gateway ne HTTP 400 diya (field test). Ye test sakht stub ke khilaf:
//  1) naya tareeqa: call kamyab + jawab mein proposal text
//  2) capture se body ki jaanch: JSON object hai, model+messages maujood
//  3) NEGATIVE: purana tareeqa (string body) stub se 400 leta hai
// Chalana: php tests/aiedit-gateway-check.php   (repo root se)
error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );

$root = dirname( __DIR__ );
require $root . '/panel/lib/hub.php';
require $root . '/panel/lib/chat.php';
require $root . '/panel/lib/aiedit.php';

$fail = 0;
function chk( $n, $c ) { global $fail; if ( ! $c ) { $fail++; } echo ( $c ? 'PASS' : 'FAIL' ) . " $n\n"; }

$port    = 18933;
$capture = sys_get_temp_dir() . '/aiedit-stub-capture.json';
@unlink( $capture );

// Stub gateway khara karo (sakht wala).
$cmd = [ PHP_BINARY, '-S', '127.0.0.1:' . $port, $root . '/tests/aiedit-stub-gateway.php' ];
$env = [ 'WDH_STUB_CAPTURE' => $capture, 'PATH' => (string) ( $_SERVER['PATH'] ?? '/usr/bin:/bin' ) ];
$proc = proc_open( $cmd, [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', '/dev/null', 'a' ], 2 => [ 'file', '/dev/null', 'a' ] ], $pipes, null, $env );
if ( ! is_resource( $proc ) ) { echo "STUB SERVER NAHI CHALA\n"; exit( 1 ); }

// Settings ko stub ki taraf point karo — asal file shutdown par restore.
$sf       = hub_settings_file();
$origSet  = file_exists( $sf ) ? (string) file_get_contents( $sf ) : null;
register_shutdown_function( function () use ( $sf, $origSet, $proc ) {
	if ( $origSet === null ) { @unlink( $sf ); } else { file_put_contents( $sf, $origSet ); }
	if ( is_resource( $proc ) ) { proc_terminate( $proc ); proc_close( $proc ); }
} );
$cur = $origSet !== null ? (array) json_decode( $origSet, true ) : [];
$cur['chat_provider']    = 'custom';
$cur['chat_gateway_url'] = 'http://127.0.0.1:' . $port . '/v1';
$cur['chat_model']       = 'stub-vision-model';
$cur['chat_gateway_key'] = '';
file_put_contents( $sf, json_encode( $cur ) );

// Server tayyar hone ka intezaar.
$up = false;
for ( $i = 0; $i < 30; $i++ ) {
	$st = null;
	$r  = hub_img_http( 'GET', 'http://127.0.0.1:' . $port . '/v1/models', '', null, 3, $st );
	if ( $r !== null ) { $up = true; break; }
	usleep( 200000 );
}
chk( 'stub gateway chal gaya', $up );

// 1) Asal call — kamyab honi chahiye.
$messages = [
	[ 'role' => 'system', 'content' => 'Reply with ONE JSON object.' ],
	[ 'role' => 'user', 'content' => [ [ 'type' => 'text', 'text' => 'User request: video heading bohat bari karo' ] ] ],
];
$r = hub_aiedit_gateway_call( $messages, 30 );
chk( 'gateway call ok (koi error nahi)', ( $r['ok'] ?? false ) === true );
chk( 'jawab mein proposal text aya', strpos( (string) ( $r['text'] ?? '' ), 'urdu_news_sec_video_heading' ) !== false );

// 2) Capture: body JSON object thi (double-encoded string NAHI).
$capRaw = file_exists( $capture ) ? (string) file_get_contents( $capture ) : '';
$cap    = json_decode( $capRaw, true );
chk( 'gateway ko mili body JSON object hai', is_array( $cap ) );
chk( 'body mein model + messages maujood', ( $cap['model'] ?? '' ) === 'stub-vision-model' && isset( $cap['messages'] ) && is_array( $cap['messages'] ) );

// 3) NEGATIVE: purana tareeqa — string body — stub 400 de.
$oldBody = json_encode( [ 'model' => 'stub-vision-model', 'messages' => $messages, 'temperature' => 0.2 ] );
$st3 = null;
hub_img_http( 'POST', 'http://127.0.0.1:' . $port . '/v1/chat/completions', '', $oldBody, 15, $st3 );
chk( 'NEGATIVE: string body par stub 400 deta hai (purana bug pakra jata hai)', $st3 === 400 );

// 4) Extract helper ke unit cases (v2.21 M3 — field saboot: SSE stream).
$propJson = json_encode( [ 'changes' => [ [ 'key' => 'k', 'value' => 'v' ] ], 'gaps' => [], 'summary' => 's' ] );
chk( 'extract: non-stream JSON', hub_aiedit_extract_text( json_encode( [ 'choices' => [ [ 'message' => [ 'content' => $propJson ] ] ] ] ) ) === $propJson );
chk( 'extract: parts-array content', hub_aiedit_extract_text( json_encode( [ 'choices' => [ [ 'message' => [ 'content' => [ [ 'type' => 'text', 'text' => 'pehla ' ], [ 'type' => 'text', 'text' => 'doosra' ] ] ] ] ] ] ) ) === 'pehla doosra' );
$sseBody = "data: " . json_encode( [ 'choices' => [ [ 'delta' => [ 'reasoning_content' => 'soch...' ] ] ] ] ) . "\n\n"
	. "data: " . json_encode( [ 'choices' => [ [ 'delta' => [ 'content' => substr( $propJson, 0, 20 ) ] ] ] ] ) . "\n\n"
	. "data: " . json_encode( [ 'choices' => [ [ 'delta' => [ 'content' => substr( $propJson, 20 ) ] ] ] ] ) . "\n\n"
	. "data: [DONE]\n\n";
chk( 'extract: SSE (reasoning nazar-andaz, content tukre jore)', hub_aiedit_extract_text( $sseBody ) === $propJson );
chk( 'extract: sirf reasoning wala SSE khali', '' === hub_aiedit_extract_text( "data: " . json_encode( [ 'choices' => [ [ 'delta' => [ 'reasoning_content' => 'soch' ] ] ] ] ) . "\n\ndata: [DONE]\n\n" ) );
chk( 'extract: ghair-JSON kachra khali', '' === hub_aiedit_extract_text( 'ye koi JSON nahi hai' ) );

// 5) End-to-end SSE: settings URL badal kar SSE route par call.
$cur['chat_gateway_url'] = 'http://127.0.0.1:' . $port . '/v1-sse';
file_put_contents( $sf, json_encode( $cur ) );
$r5 = hub_aiedit_gateway_call( $messages, 30 );
chk( 'SSE route se gateway call ok', ( $r5['ok'] ?? false ) === true );
chk( 'SSE jawab se proposal text mila', strpos( (string) ( $r5['text'] ?? '' ), 'urdu_news_sec_video_heading' ) !== false );
// Body mein stream:false gaya (capture se jaanch).
$capRaw = file_exists( $capture ) ? (string) file_get_contents( $capture ) : '';
$cap5   = json_decode( $capRaw, true );
chk( 'body mein stream:false maujood', isset( $cap5['stream'] ) && $cap5['stream'] === false );

echo $fail === 0 ? "AIEDIT-GATEWAY SAB PASS\n" : "AIEDIT-GATEWAY FAIL=$fail\n";
exit( $fail === 0 ? 0 : 1 );
