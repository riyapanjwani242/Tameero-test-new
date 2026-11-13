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

$tools = [ 'site_status', 'site_health', 'pages_list', 'themes_list' ];
$out = [];
foreach ( $tools as $t ) {
	$resp = mcp_handle( [
		'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
		'params'  => [ 'name' => $t, 'arguments' => [ 'site' => $slug ] ],
	] );
	$text = (string) ( $resp['result']['content'][0]['text'] ?? '' );
	if ( $text === '' && isset( $resp['error'] ) ) { $text = 'Error: ' . ( $resp['error']['message'] ?? 'tool error' ); }
	$out[ $t ] = [
		'isError' => ! empty( $resp['result']['isError'] ) || isset( $resp['error'] ),
		'text'    => substr( $text, 0, 160 ),
	];
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
