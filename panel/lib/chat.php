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
 *
 * v2.9.0: AI Connection (provider presets + models load +
 * connection test, Settings se) aur Brain Folder (panel/ai-brain
 * ki .md files = FIXED system prompt, provider jo bhi ho; us ke
 * sath har send par projects ki live halat ka block).
 */

if ( ! defined( 'TAMEERO_MCP_LIBRARY' ) ) { define( 'TAMEERO_MCP_LIBRARY', true ); }
require_once __DIR__ . '/hub.php';
require_once dirname( __DIR__ ) . '/bin/mcp.php';

function hub_chat_dir() { return hub_repo() . '/panel/.chat'; }

/**
 * AI providers ke presets (v2.9.0) — aik hi jagah: Settings ka
 * dropdown, JS ka URL-fill aur server ki validation teeno yahi se
 * parhte hain. Sab OpenAI-compatible wire protocol bolte hain
 * (/chat/completions + /models) jo chat pehle se istemal karti hai.
 * Gemini bhi Google ka OpenAI-compatible endpoint hai — native
 * Gemini/Anthropic formats is version mein NAHI hain.
 */
function hub_chat_providers() {
	return [
		'9router'    => [ 'label' => '9Router', 'url' => 'http://127.0.0.1:20128/v1' ],
		'omniroute'  => [ 'label' => 'OmniRoute', 'url' => 'http://127.0.0.1:20128/v1' ],
		'openrouter' => [ 'label' => 'OpenRouter', 'url' => 'https://openrouter.ai/api/v1' ],
		'openai'     => [ 'label' => 'OpenAI', 'url' => 'https://api.openai.com/v1' ],
		'gemini'     => [ 'label' => 'Gemini', 'url' => 'https://generativelanguage.googleapis.com/v1beta/openai' ],
		'custom'     => [ 'label' => 'Custom', 'url' => '' ],
	];
}

function hub_chat_gateway() {
	$s = hub_settings();
	$prov = (string) ( $s['chat_provider'] ?? '' );
	if ( ! isset( hub_chat_providers()[ $prov ] ) ) { $prov = 'custom'; }
	return [
		'url'      => rtrim( (string) ( $s['chat_gateway_url'] ?? 'http://127.0.0.1:20128/v1' ), '/' ),
		'model'    => trim( (string) ( $s['chat_model'] ?? '' ) ),
		'key'      => str_replace( [ "\r", "\n" ], '', trim( (string) ( $s['chat_gateway_key'] ?? '' ) ) ),
		'provider' => $prov,
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

/** $http_response_header se aakhri HTTP status code nikalo (0 agar na mile). */
function hub_chat_http_status( array $headers ) {
	$code = 0;
	foreach ( $headers as $h ) {
		if ( preg_match( '/^HTTP\/\S+\s+(\d{3})/', (string) $h, $m ) ) { $code = (int) $m[1]; }
	}
	return $code;
}

/** Gateway ke error body se asal paigham nikalo (JSON error.message, warna snippet).
 *  SSE body mein error chunk bhi dhoondta hai. */
function hub_chat_gateway_error_text( $raw ) {
	$d = json_decode( (string) $raw, true );
	if ( is_array( $d ) && isset( $d['error'] ) ) {
		$e = $d['error'];
		if ( is_array( $e ) ) { return (string) ( $e['message'] ?? json_encode( $e ) ); }
		return (string) $e;
	}
	if ( strpos( (string) $raw, 'data:' ) !== false ) {
		foreach ( explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", (string) $raw ) ) as $line ) {
			$line = trim( $line );
			if ( strpos( $line, 'data:' ) !== 0 ) { continue; }
			$c = json_decode( trim( substr( $line, 5 ) ), true );
			if ( is_array( $c ) && isset( $c['error'] ) ) {
				$e = $c['error'];
				if ( is_array( $e ) ) { return (string) ( $e['message'] ?? json_encode( $e ) ); }
				return (string) $e;
			}
		}
	}
	$t = trim( strip_tags( (string) $raw ) );
	return $t !== '' ? substr( $t, 0, 500 ) : '';
}

/** SSE (data: ...) body se assistant ka paigham joro.
 *  Content ke tukre + tool_calls (index-wise) jodta hai; na mile to null.
 *  Stream ke andar error chunk ho to Exception (asal paigham ke sath). */
function hub_chat_parse_sse( $raw ) {
	$body = str_replace( [ "\r\n", "\r" ], "\n", (string) $raw );
	if ( substr( $body, 0, 3 ) === "\xEF\xBB\xBF" ) { $body = substr( $body, 3 ); } // UTF-8 BOM
	$content = '';
	$calls = [];
	$seen = false;
	foreach ( explode( "\n", $body ) as $line ) {
		$line = trim( $line );
		if ( $line === '' || $line[0] === ':' ) { continue; } // khaali line / SSE comment
		if ( strpos( $line, 'data:' ) !== 0 ) { continue; }
		$data = trim( substr( $line, 5 ) );
		if ( $data === '[DONE]' ) { break; }
		$c = json_decode( $data, true );
		if ( ! is_array( $c ) ) { continue; }
		if ( isset( $c['error'] ) ) {
			$e = $c['error'];
			$m = is_array( $e ) ? (string) ( $e['message'] ?? json_encode( $e ) ) : (string) $e;
			throw new Exception( 'Gateway se stream mein ghalti ayi. (' . $m . ')' );
		}
		$choice = $c['choices'][0] ?? null;
		if ( ! is_array( $choice ) ) { continue; } // usage-only chunk waghera
		$seen = true;
		$delta = $choice['delta'] ?? [];
		if ( ! is_array( $delta ) ) { continue; }
		if ( isset( $delta['content'] ) && is_string( $delta['content'] ) ) { $content .= $delta['content']; }
		if ( isset( $delta['tool_calls'] ) && is_array( $delta['tool_calls'] ) ) {
			foreach ( $delta['tool_calls'] as $tc ) {
				$idx = (int) ( $tc['index'] ?? 0 );
				if ( ! isset( $calls[ $idx ] ) ) {
					$calls[ $idx ] = [ 'id' => '', 'type' => 'function', 'function' => [ 'name' => '', 'arguments' => '' ] ];
				}
				if ( ! empty( $tc['id'] ) && $calls[ $idx ]['id'] === '' ) { $calls[ $idx ]['id'] = (string) $tc['id']; }
				if ( ! empty( $tc['type'] ) ) { $calls[ $idx ]['type'] = (string) $tc['type']; }
				if ( isset( $tc['function']['name'] ) ) { $calls[ $idx ]['function']['name'] .= (string) $tc['function']['name']; }
				if ( isset( $tc['function']['arguments'] ) ) { $calls[ $idx ]['function']['arguments'] .= (string) $tc['function']['arguments']; }
			}
		}
	}
	if ( ! $seen && $content === '' && ! $calls ) { return null; }
	// Adhoora tool call (stream beech mein kata) kabhi execute na ho:
	// arguments ka mukammal JSON hona lazmi hai.
	foreach ( $calls as $c ) {
		$a = (string) ( $c['function']['arguments'] ?? '' );
		if ( $a !== '' ) {
			json_decode( $a, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) { throw new Exception( 'Gateway se adhoora tool call aya (arguments mukammal nahi) — dobara koshish karein.' ); }
		}
	}
	$msg = [ 'role' => 'assistant', 'content' => $content ];
	if ( $calls ) { ksort( $calls ); $msg['tool_calls'] = array_values( $calls ); }
	return $msg;
}

/** Chat completion ka jawab (saada JSON ya SSE) parse karke assistant ka paigham do.
 *  $status HTTP status code hai (0 agar maloom na ho).
 *  Kharabi par Exception — asal wajah ke sath; key hint SIRF asal 401/403 par. */
function hub_chat_parse_completion( $raw, $status ) {
	$raw = (string) $raw;
	if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) { $raw = substr( $raw, 3 ); } // UTF-8 BOM
	// 1. HTTP ghalti: pehle status, phir body ka asal paigham.
	if ( $status >= 400 ) {
		$err = hub_chat_gateway_error_text( $raw );
		if ( $status === 401 || $status === 403 ) {
			throw new Exception( 'Gateway ne key qabool nahi ki (HTTP ' . $status . ').' . ( $err !== '' ? ' (' . $err . ')' : '' ) . ' Settings mein Gateway Key check karein.' );
		}
		throw new Exception( 'Gateway se ghalti ayi (HTTP ' . $status . ').' . ( $err !== '' ? ' (' . $err . ')' : '' ) );
	}
	// 2. Saada JSON (purana raasta).
	$d = json_decode( $raw, true );
	if ( is_array( $d ) ) {
		if ( isset( $d['error'] ) ) {
			throw new Exception( 'Gateway se ghalti ayi. (' . hub_chat_gateway_error_text( $raw ) . ')' );
		}
		if ( isset( $d['choices'][0]['message'] ) && is_array( $d['choices'][0]['message'] ) ) {
			return $d['choices'][0]['message'];
		}
	}
	// 3. SSE — kuch gateways stream mange baghair bhi chunks mein jawab dete hain.
	$trim = ltrim( $raw );
	if ( $trim !== '' && ( strpos( $trim, 'data:' ) === 0 || strpos( $raw, "\ndata:" ) !== false ) ) {
		$msg = hub_chat_parse_sse( $raw );
		if ( $msg !== null ) { return $msg; }
	}
	throw new Exception( 'AI gateway ka jawab samajh nahi aya — jawab na JSON hai, na SSE. Gateway ya model check karein.' );
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
	$status = hub_chat_http_status( $http_response_header ?? [] );
	return hub_chat_parse_completion( $raw, $status );
}

/** Gateway/provider ko halki HTTP call (models + test ke liye).
 *  Key ho to Authorization header jata hai, na ho to nahi.
 *  Wapas: raw body string, ya null agar jawab hi na aya ho. */
function hub_chat_http( $method, $url, $key, $body = null, $timeout = 15, &$status = null ) {
	$hdr = '';
	if ( $body !== null ) { $hdr .= "Content-Type: application/json\r\n"; }
	if ( $key !== '' ) { $hdr .= 'Authorization: Bearer ' . $key . "\r\n"; }
	$opts = [ 'method' => $method, 'header' => $hdr, 'timeout' => $timeout, 'ignore_errors' => true ];
	if ( $body !== null ) { $opts['content'] = json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	$ctx = stream_context_create( [ 'http' => $opts ] );
	$raw = @file_get_contents( $url, false, $ctx );
	$status = hub_chat_http_status( $http_response_header ?? [] );
	return $raw === false ? null : $raw;
}

/** Key ki haalat ke hisab se dostana hint (models/test ke liye). */
function hub_chat_key_hint( $key ) {
	return $key === ''
		? ' API Key khali hai - apni provider key dal kar dobara koshish karein.'
		: ' API Key check karein - ghalat key par provider aisa hi jawab deta hai.';
}

/**
 * Provider ki model list lao (Settings ka "Models load karein").
 * Kharabi par Exception (dostana paigham) — model naam haath se
 * likhna hamesha mumkin rehta hai, ye sirf sahulat hai.
 */
function hub_chat_models( $url, $key ) {
	$url = rtrim( trim( (string) $url ), '/' );
	$key = str_replace( [ "\r", "\n" ], '', trim( (string) $key ) );
	$raw = hub_chat_http( 'GET', $url . '/models', $key, null, 15 );
	if ( $raw === null ) {
		throw new Exception( 'Gateway jawab nahi de raha (' . $url . ') - gateway chalu hai? Endpoint URL check karein.' );
	}
	$d = json_decode( $raw, true );
	if ( ! is_array( $d ) || ! isset( $d['data'] ) || ! is_array( $d['data'] ) ) {
		$msg = is_array( $d ) ? (string) ( $d['error']['message'] ?? '' ) : '';
		throw new Exception( 'Model list nahi mili.' . ( $msg !== '' ? ' (' . $msg . ')' : '' ) . hub_chat_key_hint( $key ) . ' Model naam haath se bhi likha ja sakta hai.' );
	}
	$ids = [];
	foreach ( $d['data'] as $m ) {
		$id = (string) ( $m['id'] ?? '' );
		if ( $id !== '' ) { $ids[] = $id; }
	}
	$ids = array_values( array_unique( $ids ) );
	if ( ! $ids ) { throw new Exception( 'Model list khali ayi - model naam haath se likh lein.' ); }
	return $ids;
}

/**
 * Connection test (Settings) — kuch save NAHI hota.
 * Marhala 1: /models (pata + key). Marhala 2: model ho to aik
 * chhota asli chat round. Model khali ho to natija "partial" hai —
 * adhoora pass jhoota pass nahi banta.
 * Return: [ 'ok' => bool, 'partial' => bool, 'message' => string ]
 */
function hub_chat_test_connection( $url, $key, $model ) {
	$url = rtrim( trim( (string) $url ), '/' );
	$key = str_replace( [ "\r", "\n" ], '', trim( (string) $key ) );
	$model = trim( (string) $model );
	$status = 0;
	$raw = hub_chat_http( 'GET', $url . '/models', $key, null, 15, $status );
	if ( $raw === null ) {
		return [ 'ok' => false, 'partial' => false, 'message' => 'Connection fail - gateway jawab nahi de raha (' . $url . '). Gateway chalu hai? Endpoint URL check karein.' ];
	}
	$d = json_decode( $raw, true );
	if ( ! is_array( $d ) || isset( $d['error'] ) ) {
		$msg = is_array( $d ) ? (string) ( $d['error']['message'] ?? '' ) : '';
		// Key hint SIRF asal auth fail (401/403) par — baqi ghaltiyon par asal paigham hi kaafi hai.
		$hint = ( $status === 401 || $status === 403 ) ? hub_chat_key_hint( $key ) : '';
		return [ 'ok' => false, 'partial' => false, 'message' => 'Connection fail.' . ( $msg !== '' ? ' (' . $msg . ')' : '' ) . $hint ];
	}
	if ( $model === '' ) {
		return [ 'ok' => true, 'partial' => true, 'message' => 'Connection hai, lekin Model select nahi - pehle model chunein ya Model khane mein likhein, phir test karein.' ];
	}
	$status = 0;
	$raw = hub_chat_http( 'POST', $url . '/chat/completions', $key, [ 'model' => $model, 'messages' => [ [ 'role' => 'user', 'content' => 'ping' ] ] ], 20, $status );
	if ( $raw === null ) {
		return [ 'ok' => false, 'partial' => false, 'message' => 'Connection fail - chat request par gateway jawab nahi de raha (' . $url . ').' ];
	}
	try {
		hub_chat_parse_completion( $raw, $status );
	} catch ( Exception $e ) {
		return [ 'ok' => false, 'partial' => false, 'message' => 'Connection fail. (' . $e->getMessage() . ') Model naam bhi check karein.' ];
	}
	return [ 'ok' => true, 'partial' => false, 'message' => 'Connection ho gaya - model "' . $model . '" tayyar hai.' ];
}

/**
 * Gateway zinda hai? (halki probe — /models, 3s)
 * v2.9.0: saved key ho to probe us ke sath jati hai — warna cloud
 * providers (OpenAI/OpenRouter) par key ke baghair /models hamesha
 * 401 dega aur dashboard sahi connection ko bhi "AI band" dikhaye ga.
 * Key khali ho to header NAHI jata (purana bartao barqarar).
 */
function hub_chat_gateway_alive() {
	$g = hub_chat_gateway();
	$hdr = $g['key'] !== '' ? 'Authorization: Bearer ' . $g['key'] . "\r\n" : '';
	$ctx = stream_context_create( [ 'http' => [ 'method' => 'GET', 'header' => $hdr, 'timeout' => 3, 'ignore_errors' => true ] ] );
	$raw = @file_get_contents( $g['url'] . '/models', false, $ctx );
	if ( $raw === false ) { return false; }
	$d = json_decode( $raw, true );
	// Error JSON bhi array hota hai — usay zinda na samjho.
	return is_array( $d ) && ! isset( $d['error'] );
}

/**
 * Brain Folder (v2.9.0) — panel/ai-brain ki .md files AI ki FIXED
 * hidayat hain: provider/model jo bhi ho, yehi system prompt jata
 * hai. Owner files badal sakta hai; installer upgrade par unhein
 * overwrite NAHI karta (.iss mein onlyifdoesntexist). Test ke liye
 * WDH_BRAIN_DIR se rasta badla ja sakta hai (WDH_SITES_DIR ki tarz).
 */
function hub_chat_brain_dir() {
	$env = getenv( 'WDH_BRAIN_DIR' );
	if ( is_string( $env ) && $env !== '' ) { return $env; }
	return hub_repo() . '/panel/ai-brain';
}

/** Brain files ke ghair-khali hisse, naam ke order mein. */
function hub_chat_brain_parts() {
	$dir = hub_chat_brain_dir();
	if ( ! is_dir( $dir ) ) { return []; }
	$files = glob( $dir . '/*.md' ) ?: [];
	sort( $files, SORT_STRING );
	$parts = [];
	foreach ( $files as $f ) {
		$c = trim( (string) @file_get_contents( $f ) );
		if ( $c !== '' ) { $parts[] = $c; }
	}
	return $parts;
}

/** Brain folder theek hai? (chat_ping isay dashboard ko batata hai) */
function hub_chat_brain_ok() { return count( hub_chat_brain_parts() ) > 0; }

/**
 * Brain text budget ke andar joro. Kata hamesha file ki boundary
 * par hota hai (aadhi file nahi kat-ti); koi file reh jaye to
 * aakhir mein note jud jata hai. Aik bhi file fit na ho to '' —
 * caller fallback prompt use karta hai.
 */
function hub_chat_brain_prompt( $budget ) {
	$out = '';
	$dropped = false;
	foreach ( hub_chat_brain_parts() as $p ) {
		$add = ( $out === '' ? $p : "\n\n---\n\n" . $p );
		if ( strlen( $out ) + strlen( $add ) > $budget ) { $dropped = true; break; }
		$out .= $add;
	}
	if ( $out !== '' && $dropped ) {
		$out .= "\n\n(Note: kuch brain files size cap ki wajah se shamil nahi ho sakin.)";
	}
	return $out;
}

/** Projects ki LIVE halat — har send par hub_sites() se taaza. */
function hub_chat_projects_context() {
	$lines = [ 'Projects ki live halat (is waqt):' ];
	$sites = hub_sites();
	if ( ! $sites ) { $lines[] = '- Abhi koi project nahi hai.'; }
	foreach ( $sites as $s ) {
		$name = (string) ( $s['site'] ?? '' );
		if ( $name === '' ) { continue; }
		if ( ! empty( $s['failed'] ) ) { $lines[] = '- ' . $name . ': banana fail hua tha'; continue; }
		if ( ! empty( $s['creating'] ) ) { $lines[] = '- ' . $name . ': abhi ban raha hai'; continue; }
		$line = '- ' . $name . ': ' . ( ! empty( $s['running'] ) ? 'Chal raha hai' : 'Band hai' )
			. ', port ' . (int) ( $s['port'] ?? 0 )
			. ', blueprint ' . (string) ( $s['blueprint'] ?? '-' );
		if ( ! empty( $s['theme'] ) ) { $line .= ', theme ' . (string) $s['theme']; }
		$lines[] = $line;
	}
	return implode( "\n", $lines );
}

/** Brain folder na mile to ye built-in prompt (v2.8 wala asal text). */
function hub_chat_fallback_prompt() {
	return 'Tum TAMEERO dashboard ke AI assistant ho — ek local WordPress development tool. '
		. 'Owner se usi zuban mein baat karo jis mein woh likhe (aksar Roman Urdu ya English). Jawab mukhtasar aur amli rakho. '
		. 'Tumhare paas tools hain: projects ki list/status/health, start/stop, create/clone/backup/import, products/pages waghera. '
		. 'Parhne wale tools khud chala lo. Likhne wala tool chale to woh owner ki manzoori ke liye ruk jata hai (pending) — '
		. 'owner ko saaf batao ke chat mein ya Agent Activity panel mein Approve karna hai; khud se ho gaya mat kaho jab tak action_status done na bata de. '
		. 'Nayi site banane se pehle naam aur blueprint (store/business/blog/portfolio/restaurant/school/agency) confirm kar lo agar wazeh na ho. '
		. 'Dukaan theme sirf store blueprint ke sath create par theme="dukaan-fse" se lagti hai.';
}

function hub_chat_system_prompt() {
	$projects = hub_chat_projects_context();
	// Size cap: poora system prompt 16000 characters se zyada na ho
	// (jawab slow/mehanga na ho). Brain ka budget projects block
	// nikal kar bachta hai.
	$budget = 16000 - strlen( $projects ) - 80;
	$brain = $budget > 0 ? hub_chat_brain_prompt( $budget ) : '';
	if ( $brain === '' ) {
		return hub_chat_fallback_prompt() . "\n\n" . $projects;
	}
	return $brain . "\n\n---\n\n" . $projects;
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
