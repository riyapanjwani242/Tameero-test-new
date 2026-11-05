<?php
/**
 * TAMEERO — Dashboard AI chat (v2.8, owner ki manzoor-shuda tajveez).
 *
 * Rasta: dashboard ka backend → owner ka AI gateway (default
 * 9Router: http://127.0.0.1:20128/v1 — OpenAI-compatible
 * /chat/completions) → model jo tool mange, woh MCP layer se
 * chalta hai (panel/bin/mcp.php ko library ke tor par include
 * karke, mcp_handle() se). Is ka matlab:
 *   - Parhne wale tools foran chalte hain.
 *   - Likhne wale tools MAUJOODA approval queue mein jate hain —
 *     chat mein Approve/Reject card ata hai; destructive par
 *     type-confirm. Sab kuch Agent Activity / audit mein darj.
 *   - MCP token browser ko kabhi nahi jata: chat dashboard ke
 *     andar hai, tools in-process chalte hain. Bahari AI clients
 *     ke liye token wala HTTP rasta (8971) alag maujood hai.
 * Gateway band ho to chat saaf "AI band hai" batati hai —
 * dashboard ka baqi kaam bilkul chalta rehta hai.
 */

if ( ! defined( 'TAMEERO_MCP_LIBRARY' ) ) { define( 'TAMEERO_MCP_LIBRARY', true ); }
require_once __DIR__ . '/hub.php';
require_once dirname( __DIR__ ) . '/bin/mcp.php';

function hub_chat_dir() { return hub_repo() . '/panel/.chat'; }

function hub_chat_gateway() {
	$s = hub_settings();
	return [
		'url'   => rtrim( (string) ( $s['chat_gateway_url'] ?? 'http://127.0.0.1:20128/v1' ), '/' ),
		'model' => trim( (string) ( $s['chat_model'] ?? '' ) ),
		'key'   => str_replace( [ "\r", "\n" ], '', trim( (string) ( $s['chat_gateway_key'] ?? '' ) ) ),
	];
}

/** Transcript file (session ke hisab se) — sirf save par banti hai. */
function hub_chat_file( $sid ) {
	return hub_chat_dir() . '/' . preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $sid ) . '.json';
}

function hub_chat_load( $sid ) {
	$f = hub_chat_file( $sid );
	if ( ! file_exists( $f ) ) { return []; }
	$d = json_decode( (string) file_get_contents( $f ), true );
	return is_array( $d ) ? $d : [];
}

function hub_chat_save( $sid, array $messages ) {
	$dir = hub_chat_dir();
	if ( ! is_dir( $dir ) ) { mkdir( $dir, 0775, true ); }
	$messages = array_slice( $messages, -60 );
	file_put_contents( hub_chat_file( $sid ), json_encode( $messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), LOCK_EX );
}

function hub_chat_clear( $sid ) {
	$f = hub_chat_file( $sid );
	if ( file_exists( $f ) ) { @unlink( $f ); }
}

/** MCP tools ko OpenAI function-calling ki shakal do. */
function hub_chat_tools_openai() {
	$out = [];
	foreach ( mcp_tools() as $name => $t ) {
		$out[] = [
			'type'     => 'function',
			'function' => [
				'name'        => $name,
				'description' => (string) $t['description'],
				'parameters'  => $t['inputSchema'],
			],
		];
	}
	return $out;
}

/** Ek MCP tool chalao — bilkul waisa natija jaisa bahari client ko milta hai. */
function hub_chat_tool_call( $name, array $args ) {
	$resp = mcp_handle( [
		'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
		'params'  => [ 'name' => $name, 'arguments' => $args ],
	] );
	$text = (string) ( $resp['result']['content'][0]['text'] ?? '' );
	if ( $text === '' && isset( $resp['error'] ) ) { $text = 'Error: ' . ( $resp['error']['message'] ?? 'tool error' ); }
	$isErr = ! empty( $resp['result']['isError'] );
	$decoded = json_decode( $text, true );
	return [
		'text'      => $text,
		'is_error'  => $isErr,
		'pending'   => is_array( $decoded ) && ! empty( $decoded['pending'] ),
		'action_id' => is_array( $decoded ) ? (string) ( $decoded['action_id'] ?? '' ) : '',
	];
}

/** Gateway ko ek chat round bhejo. Kharabi par Exception (dostana paigham). */
function hub_chat_gateway_round( array $messages, array $tools ) {
	$g = hub_chat_gateway();
	$body = [ 'messages' => $messages ];
	if ( $tools ) { $body['tools'] = $tools; $body['tool_choice'] = 'auto'; }
	if ( $g['model'] !== '' ) { $body['model'] = $g['model']; }
	// Gateway key (v2.8.15): set ho to Authorization header jata hai;
	// khaali ho to header NAHI jata (purana bartao barqarar).
	$hdr = "Content-Type: application/json\r\n";
	if ( $g['key'] !== '' ) { $hdr .= 'Authorization: Bearer ' . $g['key'] . "\r\n"; }
	$ctx = stream_context_create( [ 'http' => [
		'method'        => 'POST',
		'header'        => $hdr,
		'content'       => json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		'timeout'       => 120,
		'ignore_errors' => true,
	] ] );
	$raw = @file_get_contents( $g['url'] . '/chat/completions', false, $ctx );
	if ( $raw === false ) {
		throw new Exception( 'AI gateway jawab nahi de raha (' . $g['url'] . ') — gateway chalu hai? Settings mein Chat ka pata check karein.' );
	}
	$d = json_decode( $raw, true );
	if ( ! is_array( $d ) || ! isset( $d['choices'][0]['message'] ) ) {
		$msg = is_array( $d ) ? (string) ( $d['error']['message'] ?? '' ) : '';
		$hint = $g['key'] === ''
			? ' Settings mein Gateway Key khali hai - apni gateway (9Router) key dal kar dobara koshish karein.'
			: ' Settings mein Gateway Key check karein - ghalat key par gateway aisa hi jawab deta hai.';
		throw new Exception( 'AI gateway se ajeeb jawab aya.' . ( $msg !== '' ? ' (' . $msg . ')' : '' ) . $hint );
	}
	return $d['choices'][0]['message'];
}

/** Gateway zinda hai? (halki probe — /models, 3s) */
function hub_chat_gateway_alive() {
	$g = hub_chat_gateway();
	$ctx = stream_context_create( [ 'http' => [ 'method' => 'GET', 'timeout' => 3, 'ignore_errors' => true ] ] );
	$raw = @file_get_contents( $g['url'] . '/models', false, $ctx );
	if ( $raw === false ) { return false; }
	$d = json_decode( $raw, true );
	// Error JSON bhi array hota hai — usay zinda na samjho.
	return is_array( $d ) && ! isset( $d['error'] );
}

function hub_chat_system_prompt() {
	return 'Tum TAMEERO dashboard ke AI assistant ho — ek local WordPress development tool. '
		. 'Owner se usi zuban mein baat karo jis mein woh likhe (aksar Roman Urdu ya English). Jawab mukhtasar aur amli rakho. '
		. 'Tumhare paas tools hain: projects ki list/status/health, start/stop, create/clone/backup/import, products/pages waghera. '
		. 'Parhne wale tools khud chala lo. Likhne wala tool chale to woh owner ki manzoori ke liye ruk jata hai (pending) — '
		. 'owner ko saaf batao ke chat mein ya Agent Activity panel mein Approve karna hai; khud se ho gaya mat kaho jab tak action_status done na bata de. '
		. 'Nayi site banane se pehle naam aur blueprint (store/business/blog/portfolio/restaurant/school/agency) confirm kar lo agar wazeh na ho. '
		. 'Dukaan theme sirf store blueprint ke sath create par theme="dukaan-fse" se lagti hai.';
}

/**
 * Ek user paigham par poora agent loop chalao.
 * Return: [ 'events' => [...], 'messages' => poora transcript ]
 * Event shaklein:
 *   ['type'=>'tool','name'=>..,'summary'=>..,'status'=>'done|pending|error','action_id'=>..]
 *   ['type'=>'text','text'=>..]  (aakhri jawab)
 */
function hub_chat_run( $sid, $userText ) {
	$messages = hub_chat_load( $sid );
	$api = [];
	$api[] = [ 'role' => 'system', 'content' => hub_chat_system_prompt() ];
	foreach ( $messages as $m ) {
		if ( ( $m['role'] ?? '' ) === 'user' || ( $m['role'] ?? '' ) === 'assistant' ) {
			$api[] = [ 'role' => $m['role'], 'content' => (string) ( $m['content'] ?? '' ) ];
		}
	}
	$api[] = [ 'role' => 'user', 'content' => $userText ];
	$messages[] = [ 'role' => 'user', 'content' => $userText, 'ts' => time() ];

	$mcpOn = ! empty( hub_settings()['mcp_enabled'] );
	$tools = $mcpOn ? hub_chat_tools_openai() : [];
	$events = [];
	$finalText = '';

	for ( $round = 0; $round < 6; $round++ ) {
		$msg = hub_chat_gateway_round( $api, $tools );
		$calls = (array) ( $msg['tool_calls'] ?? [] );
		if ( ! $calls ) {
			$finalText = trim( (string) ( $msg['content'] ?? '' ) );
			break;
		}
		$api[] = $msg; // assistant ka tool_calls wala paigham as-is
		foreach ( $calls as $c ) {
			$fn = (string) ( $c['function']['name'] ?? '' );
			$args = json_decode( (string) ( $c['function']['arguments'] ?? '{}' ), true );
			if ( ! is_array( $args ) ) { $args = []; }
			$r = hub_chat_tool_call( $fn, $args );
			$status = $r['pending'] ? 'pending' : ( $r['is_error'] ? 'error' : 'done' );
			$events[] = [
				'type' => 'tool', 'name' => $fn,
				'summary' => mcp_summarize( $args ),
				'status' => $status, 'action_id' => $r['action_id'],
			];
			$api[] = [
				'role' => 'tool',
				'tool_call_id' => (string) ( $c['id'] ?? '' ),
				'content' => $r['text'],
			];
			if ( $round === 5 ) { $finalText = 'Tool ke kai round ho gaye — jo hua woh oopar hai. Aage ke liye dobara likhein.'; }
		}
	}
	if ( $finalText === '' && $events ) {
		$finalText = 'Ho gaya — tafseel oopar wale steps mein hai.';
	}
	if ( $finalText === '' ) {
		$finalText = 'AI se khaali jawab aya — zara dobara likhein.';
	}
	if ( ! $mcpOn ) {
		$finalText = trim( $finalText . "\n\n(Note: MCP abhi OFF hai — tools istemal nahi ho sakte. Agent page se MCP On karein to main projects par kaam kar sakoon ga.)" );
	}
	$messages[] = [ 'role' => 'assistant', 'content' => $finalText, 'events' => $events, 'ts' => time() ];
	hub_chat_save( $sid, $messages );
	return [ 'events' => $events, 'text' => $finalText, 'messages' => $messages ];
}
