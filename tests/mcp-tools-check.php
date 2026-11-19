<?php
/**
 * TAMEERO CI helper — MCP per-site read tools asal mein chalao (v2.14.1).
 *
 * Dashboard ka chat backend MCP ko library mode mein mcp_handle() se
 * call karta hai — ye helper bhi bilkul waisa hi karta hai, taake gate
 * par wahi rasta test ho jo asal AI Assistant istemal karta hai.
 * (v2.7.1 se chhupa bug: ye tools gate par kabhi chalaye hi nahi gaye
 * thay — sirf MCP toggle/port/401 check hota tha.)
 *
 * Usage: php tests/mcp-tools-check.php <mcp.php ka path> <site slug>
 *        php tests/mcp-tools-check.php <mcp.php ka path> - updates
 * Dusra mode: v2.15 updates tools (updates_check, tameero_update_apply)
 * Teesra mode: images - v2.16 image tools ki maujoodgi + queue shakal
 * (write-class: asal generation nahi hoti, pending action banta hai).
 * site ke baghair asal call - gate block 6l2 part B isay istemal karta hai.
 * Output: aik line JSON — har tool ke liye isError + text ki jhalak.
 * WDH_SITES_DIR env se sites ka folder milta hai (hub.php ka usool).
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$mcpFile = (string) ( $argv[1] ?? '' );
$slug    = (string) ( $argv[2] ?? '' );
if ( $mcpFile === '' || ! file_exists( $mcpFile ) || $slug === '' ) {
	fwrite( STDERR, "Usage: php mcp-tools-check.php <mcp.php path> <site slug>\n" );
	exit( 2 );
}

define( 'TAMEERO_MCP_LIBRARY', true );
require_once $mcpFile;

function wdh_mcp_call( $tool, $args ) {
	$resp = mcp_handle( [
		'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
		'params'  => [ 'name' => $tool, 'arguments' => $args ],
	] );
	$text = (string) ( $resp['result']['content'][0]['text'] ?? '' );
	if ( $text === '' && isset( $resp['error'] ) ) { $text = 'Error: ' . ( $resp['error']['message'] ?? 'tool error' ); }
	return [
		'isError' => ! empty( $resp['result']['isError'] ) || isset( $resp['error'] ),
		'text'    => $text,
	];
}

function wdh_mcp_list() {
	$resp = mcp_handle( [
		'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => new stdClass(),
	] );
	$names = [];
	foreach ( (array) ( $resp['result']['tools'] ?? [] ) as $t ) {
		if ( ! empty( $t['name'] ) ) { $names[] = (string) $t['name']; }
	}
	return $names;
}

$mode = (string) ( $argv[3] ?? '' );
$out = [];
if ( $mode === 'updates' ) {
	// v2.15: updates_check hamesha; tameero_update_apply SIRF jab pending
	// na ho (koi asal patch gate ke is marhale par nahi lagna chahiye -
	// asal apply round gate block 6m2 azmata hai). Skip out mein wazeh hai.
	$chk = wdh_mcp_call( 'updates_check', new stdClass() );
	$chk['text'] = substr( $chk['text'], 0, 700 );
	$out['updates_check'] = $chk;
	$pending = null;
	$dec = json_decode( $chk['text'], true );
	if ( is_array( $dec ) && isset( $dec['pending_patches'] ) ) { $pending = (int) $dec['pending_patches']; }
	if ( $pending === 0 ) {
		$app2 = wdh_mcp_call( 'tameero_update_apply', new stdClass() );
		$app2['text'] = substr( $app2['text'], 0, 700 );
		$out['tameero_update_apply'] = $app2;
	} else {
		$out['tameero_update_apply'] = [ 'isError' => false, 'skipped' => true, 'text' => 'pending patches maujood - asal apply 6m2 mein azmaya jata hai' ];
	}
} elseif ( $mode === 'fetch' ) {
	// v2.18: web_fetch tool ki maujoodgi + kul ginti. Asal fetch
	// rounds tests/fetch-check.php se hote hain (SSRF battery).
	$names = wdh_mcp_list();
	$out['tools_count'] = count( $names );
	$out['has_web_fetch'] = in_array( 'web_fetch', $names, true );
	$f = wdh_mcp_call( 'web_fetch', [ 'url' => 'http://127.0.0.1/admin' ] );
	$f['text'] = substr( $f['text'], 0, 300 );
	$out['web_fetch_ssrf_probe'] = $f;
} elseif ( $mode === 'images' ) {
	// v2.16: tools ki kul ginti + image tools ki maujoodgi; write-class
	// honay ki tasdeeq queue jawab (action_id) se — asal generation
	// gate block 6m4 ke img-round helper se hoti hai.
	$names = wdh_mcp_list();
	$out['tools_count'] = count( $names );
	$out['has_image_generate'] = in_array( 'image_generate', $names, true );
	$out['has_image_attach'] = in_array( 'image_attach', $names, true );
	$g = wdh_mcp_call( 'image_generate', [ 'site' => $slug, 'prompt' => 'gate check - sirf queue shakal' ] );
	$g['text'] = substr( $g['text'], 0, 300 );
	$out['image_generate_queue'] = $g;
} else {
	foreach ( [ 'site_status', 'site_health', 'pages_list', 'themes_list' ] as $t ) {
		$r = wdh_mcp_call( $t, [ 'site' => $slug ] );
		$r['text'] = substr( $r['text'], 0, 160 );
		$out[ $t ] = $r;
	}
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
