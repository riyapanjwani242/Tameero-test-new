<?php
/**
 * WebDev Hub — Dashboard (product ka dil)
 * Saare WordPress projects ek jagah: list, create, start/stop,
 * site kholain, one-click admin, export, logs, delete.
 * Sirf 127.0.0.1 par serve hota hai (start-dashboard.sh dekho).
 */
session_start();
require_once dirname( __DIR__ ) . '/lib/hub.php';

// ---------- Card thumbnail serve karna (feature 1) ----------
if ( isset( $_GET['thumb'] ) && hub_slug_ok( $_GET['thumb'] ) ) {
	$f = hub_thumb_file( hub_sites_dir() . '/' . $_GET['thumb'] );
	if ( $f ) {
		header( 'Content-Type: ' . ( substr( $f, -4 ) === '.png' ? 'image/png' : 'image/jpeg' ) );
		header( 'Content-Length: ' . filesize( $f ) );
		header( 'Cache-Control: no-cache' );
		readfile( $f );
		exit;
	}
	http_response_code( 404 );
	exit;
}

// ---------- Local CA certificate download (feature 19) ----------
if ( isset( $_GET['ca'] ) ) {
	$f = hub_ca_dir() . '/ca.crt';
	if ( file_exists( $f ) ) {
		header( 'Content-Type: application/x-x509-ca-cert' );
		header( 'Content-Disposition: attachment; filename="webdevhub-ca.crt"' );
		header( 'Content-Length: ' . filesize( $f ) );
		readfile( $f );
		exit;
	}
	http_response_code( 404 );
	exit;
}

if ( empty( $_SESSION['csrf'] ) ) {
	$_SESSION['csrf'] = bin2hex( random_bytes( 16 ) );
}
$csrf = $_SESSION['csrf'];
$flash = $_SESSION['flash'] ?? '';
unset( $_SESSION['flash'] );

// Kuch session cheezein sirf ek makhsoos view ko chahiye — woh
// yahin nikaal kar rakh lo (neeche GET par session band ho jata hai).
$sessTokOnce = '';
if ( ( $_GET['view'] ?? '' ) === 'agent' && ! empty( $_SESSION['mcp_token_once'] ) ) {
	$sessTokOnce = (string) $_SESSION['mcp_token_once'];
	unset( $_SESSION['mcp_token_once'] );
}
$sessDb = null; $sessDbSql = '';
if ( ( $_GET['view'] ?? '' ) === 'db' ) {
	$sessDb = $_SESSION['db_result'] ?? null;
	$sessDbSql = (string) ( $_SESSION['db_sql'] ?? '' );
	unset( $_SESSION['db_result'], $_SESSION['db_sql'] );
}

// GET requests par session lock FORAN chhor do (v2.8 — import-hang
// fix ka hissa): dashboard single-thread server par chalta hai
// (Windows) aur session file par exclusive lock hota hai — koi ek
// lambi GET (health/update waghera) lock pakre rakhe to peeche
// anay wali har request, khaas taur par bari import wali POST,
// line mein phans jati thi aur dashboard murda lagta tha. POST
// wale actions apna close pehle hi karte hain (neeche dekho);
// jin chand jaghon ko session mein LIKHNA hota hai woh hub_back /
// hub_back_to se dobara khol leti hain.
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'GET' ) {
	session_write_close();
}

// ---------- AI chat endpoints (v2.8) — JSON, session-auth + CSRF ----------
if ( in_array( ( $_GET['view'] ?? '' ), [ 'chat_send', 'chat_poll', 'chat_ping', 'chat_clear', 'chat_history', 'chat_models', 'chat_test' ], true ) ) {
	require_once dirname( __DIR__ ) . '/lib/chat.php';
	header( 'Content-Type: application/json; charset=utf-8' );
	$chatView = (string) $_GET['view'];
	if ( $chatView !== 'chat_ping' ) {
		// POST endpoints par CSRF (GET poll sirf parhta hai).
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' ) {
			$rawBody = (string) file_get_contents( 'php://input' );
			$jbody = json_decode( $rawBody, true );
			$given = is_array( $jbody ) ? (string) ( $jbody['csrf'] ?? '' ) : (string) ( $_POST['csrf'] ?? '' );
			if ( ! hash_equals( $csrf, $given ) ) {
				http_response_code( 403 );
				echo json_encode( [ 'error' => 'Session token ghalat hai — page refresh karein.' ] );
				exit;
			}
		}
	}
	if ( session_status() === PHP_SESSION_ACTIVE ) { session_write_close(); }
	$sid = session_id();
	try {
		if ( $chatView === 'chat_ping' ) {
			$mst = hub_mcp_server_status();
			echo json_encode( [
				'gateway' => hub_chat_gateway_alive(),
				'mcp_enabled' => ! empty( hub_settings()['mcp_enabled'] ),
				'mcp_running' => $mst['running'],
				'pending' => hub_mcp_pending_count(),
				'brain' => hub_chat_brain_ok(),
			] );
			exit;
		}
		if ( $chatView === 'chat_clear' ) {
			hub_chat_clear( $sid );
			echo json_encode( [ 'ok' => true ] );
			exit;
		}
		if ( $chatView === 'chat_history' ) {
			echo json_encode( [ 'messages' => hub_chat_load( $sid ) ] );
			exit;
		}
		if ( $chatView === 'chat_poll' ) {
			if ( function_exists( 'mcp_pump' ) ) { mcp_pump(); }
			$out = [];
			foreach ( explode( ',', (string) ( $_GET['ids'] ?? '' ) ) as $aid ) {
				$aid = trim( $aid );
				if ( $aid === '' ) { continue; }
				$rec = hub_mcp_action_get( $aid );
				$out[] = $rec
					? [ 'id' => $aid, 'status' => (string) $rec['status'], 'tool' => (string) $rec['tool'], 'result' => (string) ( $rec['result'] ?? '' ), 'class' => (string) ( $rec['class'] ?? '' ), 'summary' => (string) ( $rec['summary'] ?? '' ), 'error' => (string) ( $rec['error'] ?? '' ) ]
					: [ 'id' => $aid, 'status' => 'missing', 'tool' => '', 'result' => '' ];
			}
			echo json_encode( [ 'actions' => $out, 'pending' => hub_mcp_pending_count() ] );
			exit;
		}
		if ( $chatView === 'chat_models' || $chatView === 'chat_test' ) {
			// Settings ke Models/Test buttons (v2.9.0): form ki MAUJOODA
			// (unsaved) values par kaam hota hai, kuch save NAHI hota.
			// Key khana khali ho to saved key server-side lagti hai —
			// key browser ko kabhi wapas nahi jati, sirf natija jata hai.
			$rawBody = (string) file_get_contents( 'php://input' );
			$jbody = json_decode( $rawBody, true );
			$jbody = is_array( $jbody ) ? $jbody : [];
			$g = hub_chat_gateway();
			$url = trim( (string) ( $jbody['url'] ?? '' ) );
			if ( $url === '' ) { $url = $g['url']; }
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				echo json_encode( [ 'error' => 'Endpoint URL theek nahi - http:// ya https:// se shuru ho.' ] );
				exit;
			}
			$key = substr( str_replace( [ "\r", "\n" ], '', trim( (string) ( $jbody['key'] ?? '' ) ) ), 0, 200 );
			if ( $key === '' ) { $key = $g['key']; }
			if ( $chatView === 'chat_models' ) {
				echo json_encode( [ 'models' => hub_chat_models( $url, $key ) ] );
				exit;
			}
			$model = substr( trim( (string) ( $jbody['model'] ?? '' ) ), 0, 120 );
			echo json_encode( hub_chat_test_connection( $url, $key, $model ) );
			exit;
		}
		// chat_send
		$rawBody = (string) file_get_contents( 'php://input' );
		$jbody = json_decode( $rawBody, true );
		$text = trim( (string) ( is_array( $jbody ) ? ( $jbody['text'] ?? '' ) : ( $_POST['text'] ?? '' ) ) );
		if ( $text === '' ) { echo json_encode( [ 'error' => 'Paigham khali hai.' ] ); exit; }
		$r = hub_chat_run( $sid, $text );
		echo json_encode( [ 'events' => $r['events'], 'text' => $r['text'] ] );
		exit;
	} catch ( Throwable $e ) {
		echo json_encode( [ 'error' => $e->getMessage() ] );
		exit;
	}
}

function hub_back( $msg ) {
	// Actions ke dauraan session band rehta hai (neeche dekho) —
	// flash likhne ke liye zaroorat par dobara kholo.
	if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
	$_SESSION['flash'] = $msg;
	// v2.10 Phase 2: cards Projects view par hain — wahan se aya
	// action wahi wapas jaye, Home par nahi (Code Reviewer finding).
	// v2.10 Phase 3: Details view (site + tab) se aya action bhi
	// wapas usi tab par jaye.
	$to = '/';
	$gv = (string) ( $_GET['view'] ?? '' );
	if ( $gv === 'projects' ) {
		$to = '/?view=projects';
	} elseif ( $gv === 'wizard' ) {
		// v2.10 Phase 5: wizard par topbar se zubaan badalne par
		// wahi step wapas khule (Reviewer finding) — step 1 par
		// bhi dobara shuru nahi hona chahiye.
		$to = '/?view=wizard' . ( in_array( (string) ( $_GET['step'] ?? '' ), [ '1', '2', '3' ], true ) ? '&step=' . (string) $_GET['step'] : '' );
	} elseif ( $gv === 'site' && hub_slug_ok( (string) ( $_GET['site'] ?? '' ) ) ) {
		$to = '/?view=site&site=' . urlencode( (string) $_GET['site'] );
		$tb = (string) ( $_GET['tab'] ?? '' );
		if ( in_array( $tb, [ 'overview', 'plugins', 'updates', 'logs', 'settings' ], true ) ) { $to .= '&tab=' . $tb; }
	}
	hub_back_xhr( $to );
	header( 'Location: ' . $to );
	exit;
}

/** XHR upload (import progress) par redirect ki jagah JSON do — taake
 *  browser khud us page par jaye aur session ka flash wahi dikhe
 *  (XHR ke khud-follow kiye redirect mein flash kha jata hai). */
function hub_back_xhr( $url ) {
	if ( ( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' ) === 'XMLHttpRequest' ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( [ 'redirect' => $url ] );
		exit;
	}
}

/** v2.10 Phase 3: Plugins table — standalone Plugins view aur
 *  Project Details ke Plugins tab dono ka mushtarka markup.
 *  $returnTo ghair-khali ho to toggle forms wohi URL return_to
 *  mein bhejte hain (dispatch sirf is site ke tab URL ko maanta hai). */
function wdh_plugins_table( $pslug, $csrf, $returnTo = '' ) {
	$S = hub_strings();
	$pdir  = hub_sites_dir() . '/' . $pslug;
	$pmeta = hub_slug_ok( $pslug ) ? hub_site_meta( $pdir ) : null;
	if ( ! $pmeta ) {
		echo '<p>' . htmlspecialchars( $S['site_not_found'] ) . '</p>';
		return;
	}
	$plist = hub_site_plugins( $pdir );
	$pactive = hub_site_active_plugins( $pdir );
	$ppin = hub_bundle_plugin_versions();
	?>
  <table>
    <tr><th><?php echo htmlspecialchars( $S['th_plugin'] ); ?></th><th><?php echo htmlspecialchars( $S['th_version'] ); ?></th><th><?php echo htmlspecialchars( $S['th_state'] ); ?></th><th></th></tr>
    <?php foreach ( $plist as $p ) : $isOn = in_array( $p['file'], $pactive, true ); ?>
    <tr>
      <td><?php echo htmlspecialchars( $p['name'] ); ?><?php if ( isset( $ppin[ $p['slug'] ] ) && $p['version'] !== '' && version_compare( $p['version'], $ppin[ $p['slug'] ], '<' ) ) : ?> <span class="warn">(bundle mein naya: <?php echo htmlspecialchars( $ppin[ $p['slug'] ] ); ?>)</span><?php endif; ?></td>
      <td><?php echo htmlspecialchars( $p['version'] ?: '—' ); ?></td>
      <td class="<?php echo $isOn ? 'ok' : 'off'; ?>"><?php echo $isOn ? htmlspecialchars( $S['plug_on'] ) : htmlspecialchars( $S['plug_off'] ); ?></td>
      <td>
        <form method="post" style="margin:0">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $pslug ); ?>">
          <input type="hidden" name="plugin" value="<?php echo htmlspecialchars( $p['file'] ); ?>">
          <input type="hidden" name="mode" value="<?php echo $isOn ? 'off' : 'on'; ?>">
          <?php if ( $returnTo !== '' ) : ?><input type="hidden" name="return_to" value="<?php echo htmlspecialchars( $returnTo ); ?>"><?php endif; ?>
          <button class="btn" name="action" value="plugin_toggle"><?php echo $isOn ? htmlspecialchars( $S['plug_turn_off'] ) : htmlspecialchars( $S['plug_turn_on'] ); ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p style="color:#9fb0c7;font-size:13px"><?php echo htmlspecialchars( $S['plugins_note'] ); ?></p>
	<?php
}

/** Kisi makhsoos view par flash ke sath wapas (plugins/db/mail). */
function hub_back_to( $msg, $url ) {
	if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
	$_SESSION['flash'] = $msg;
	hub_back_xhr( $url );
	header( 'Location: ' . $url );
	exit;
}

// ===== v2.8 SHELL (Mockup A) — har view isi mein khulta hai =====
// Topbar + sidebar + main + (aksar) dayein AI chat rail. Views ka
// andar ka markup aur form naam nahi badle — sirf bahari shakal.
function wdh_shell_open( $active, $title, $opts = [] ) {
	global $csrf, $flash;
	$S = hub_strings();
	$rail = $opts['rail'] ?? true;
	$pc = hub_mcp_pending_count();
	$diskFree = @disk_free_space( hub_sites_dir() );
	?>
<!DOCTYPE html>
<html lang="<?php echo hub_lang(); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TAMEERO — <?php echo htmlspecialchars( $title ); ?></title>
<?php if ( ! empty( $opts['refresh'] ) ) : ?><meta http-equiv="refresh" content="<?php echo (int) $opts['refresh']; ?>"><?php endif; ?>
<!--lang:<?php echo hub_lang(); ?>-->
<link rel="stylesheet" href="/assets/panel.css">
</head>
<body>
<div class="app<?php echo $rail ? '' : ' norail'; ?>">
  <div class="top">
    <a class="logo" href="/">TAMEERO<span>.</span><small><?php echo htmlspecialchars( $S['app_sub'] ); ?></small></a>
    <a class="newbtn" href="/#new">＋ <?php echo htmlspecialchars( $S['new_project'] ); ?></a>
    <?php if ( ! empty( $opts['search'] ) ) : ?>
    <span class="searchbox"><input type="search" id="q" placeholder="<?php echo htmlspecialchars( $S['search_ph'] ); ?>"></span>
    <?php else : ?><span style="flex:1"></span><?php endif; ?>
    <form method="post" class="langform">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf ); ?>">
      <input type="hidden" name="action" value="set_lang">
      <select name="lang" class="langph" onchange="this.form.submit()" title="<?php echo htmlspecialchars( $S['lang_label'] ); ?>">
        <option value="ur"<?php echo hub_lang() === 'ur' ? ' selected' : ''; ?>>Roman Urdu</option>
        <option value="en"<?php echo hub_lang() === 'en' ? ' selected' : ''; ?>>English</option>
      </select>
    </form>
    <a class="pill" id="agentPill" href="/?view=agent"><?php echo htmlspecialchars( $S['agent_word'] ); ?>: <b>…</b><?php if ( $pc ) : ?> · <span class="pend"><?php echo $pc; ?> <?php echo htmlspecialchars( $S['pending_word'] ); ?></span><?php endif; ?></a>
    <button class="iconbtn" id="railToggle" type="button">✦ AI</button>
  </div>
  <nav class="side">
    <a class="<?php echo $active === 'home' ? 'on' : ''; ?>" href="/">▦ <span class="lbl"><?php echo htmlspecialchars( $S['nav_home'] ); ?></span></a>
    <a class="<?php echo $active === 'projects' ? 'on' : ''; ?>" href="/?view=projects">▤ <span class="lbl"><?php echo htmlspecialchars( $S['nav_projects'] ); ?></span></a>
    <a class="<?php echo $active === 'themes' ? 'on' : ''; ?>" href="/?view=themes">◐ <span class="lbl"><?php echo htmlspecialchars( $S['nav_themes'] ); ?></span></a>
    <a class="<?php echo $active === 'agent' ? 'on' : ''; ?>" href="/?view=agent">✦ <span class="lbl"><?php echo htmlspecialchars( $S['agent_activity'] ); ?></span><?php if ( $pc ) : ?><span class="badge" id="agentBadge"><?php echo $pc; ?></span><?php else : ?><span class="badge" id="agentBadge" style="display:none"></span><?php endif; ?></a>
    <a class="<?php echo $active === 'health' ? 'on' : ''; ?>" href="/?view=health">♥ <span class="lbl"><?php echo htmlspecialchars( $S['health'] ); ?></span></a>
    <a class="<?php echo $active === 'settings' ? 'on' : ''; ?>" href="/?view=settings">⚙ <span class="lbl"><?php echo htmlspecialchars( $S['settings'] ); ?></span></a>
    <div class="sp"></div>
    <div class="sidehealth">
      <?php echo htmlspecialchars( $S['side_disk'] ); ?>: <b><?php echo $diskFree ? hub_human_size( $diskFree ) : '—'; ?> <?php echo htmlspecialchars( $S['side_free'] ); ?></b><br>
      PHP <?php echo htmlspecialchars( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?> · <?php echo htmlspecialchars( $S['side_ports_ok'] ); ?><br>
      <span class="ver">TAMEERO v<?php echo htmlspecialchars( hub_version() ); ?></span>
    </div>
  </nav>
  <main class="main">
  <?php if ( ! empty( $flash ) ) : ?><div class="flash"><?php echo htmlspecialchars( $flash ); ?></div><?php endif; ?>
	<?php
}

function wdh_shell_close( $rail = true ) {
	global $csrf;
	if ( $rail ) :
	?>
  </main>
  <aside class="chat" id="chatRail" data-csrf="<?php echo htmlspecialchars( $csrf ); ?>">
    <?php $SS = hub_strings(); ?>
    <h4>✦ <?php echo htmlspecialchars( $SS['chat_title'] ); ?> <button class="railx" id="railCollapse" type="button" title="<?php echo htmlspecialchars( $SS['chat_collapse'] ); ?>">›</button> <span class="st" id="chatStatus">…</span></h4>
    <div class="brainwarn" id="chatBrainWarn" hidden>⚠ <?php echo htmlspecialchars( $SS['chat_brain_warn'] ); ?></div>
    <div class="shortcuts">
      <button type="button" class="scbtn" data-prompt="<?php echo htmlspecialchars( $SS['sc_list_prompt'] ); ?>">📋 <?php echo htmlspecialchars( $SS['sc_list'] ); ?></button>
      <button type="button" class="scbtn" data-prompt="<?php echo htmlspecialchars( $SS['sc_start_prompt'] ); ?>">▶ <?php echo htmlspecialchars( $SS['sc_start'] ); ?></button>
      <button type="button" class="scbtn" data-prompt="<?php echo htmlspecialchars( $SS['sc_stop_prompt'] ); ?>">⏸ <?php echo htmlspecialchars( $SS['sc_stop'] ); ?></button>
      <button type="button" class="scbtn" data-prompt="<?php echo htmlspecialchars( $SS['sc_backup_prompt'] ); ?>">💾 <?php echo htmlspecialchars( $SS['sc_backup'] ); ?></button>
    </div>
    <div class="msgs" id="chatMsgs"></div>
    <div class="inp"><input id="chatInp" placeholder="<?php echo htmlspecialchars( $SS['chat_ph'] ); ?>" autocomplete="off"><button id="chatSend" type="button">➤</button></div>
  </aside>
  <button class="railtab" id="railTab" type="button" title="<?php echo htmlspecialchars( $SS['chat_expand'] ); ?>">✦<span>AI</span></button>
	<?php else : ?>
  </main>
	<?php endif; ?>
</div>
<script src="/assets/panel.js"></script>
</body>
</html>
	<?php
}

// ---------- Chunked import endpoints (v2.8.14) - raw octet-stream ----------
// POST / (companion ya main origin) + headers: X-WDH-Action:
// import_chunk | import_status | import_finalize, aur X-CSRF-Token.
// Legacy 'import' action hata di gayi hai - chunked hi raasta hai.
// Jawab hamesha JSON. Session lock chunk parhne se pehle chhor do
// (lambi POST ke dauraan baqi requests na rukein).
$chunkAction = (string) ( $_SERVER['HTTP_X_WDH_ACTION'] ?? '' );
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && in_array( $chunkAction, [ 'import_chunk', 'import_status', 'import_finalize' ], true ) ) {
	hub_cors_local();
	header( 'Content-Type: application/json; charset=utf-8' );
	if ( ! hash_equals( $csrf, (string) ( $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '' ) ) ) {
		http_response_code( 403 );
		echo json_encode( [ 'error' => 'Session token ghalat hai - page refresh karein.' ] );
		exit;
	}
	session_write_close();
	if ( $chunkAction === 'import_chunk' ) {
		$uid = (string) ( $_SERVER['HTTP_X_UPLOAD_ID'] ?? '' );
		$idx = (int) ( $_SERVER['HTTP_X_CHUNK_INDEX'] ?? -1 );
		$total = (int) ( $_SERVER['HTTP_X_CHUNK_TOTAL'] ?? 0 );
		$sha = strtolower( (string) ( $_SERVER['HTTP_X_CHUNK_SHA256'] ?? '' ) );
		hub_import_trace( 'CHUNK-RECV upload=' . $uid . ' index=' . $idx . ' total=' . $total );
		if ( ! hub_import_upload_id_ok( $uid ) || $idx < 0 || $total < 1 || $total > 1024 || $idx >= $total ) {
			http_response_code( 400 );
			echo json_encode( [ 'error' => 'Chunk ki tafseel ghalat hai.' ] );
			exit;
		}
		$res = hub_import_chunk_save( $uid, $idx, $sha );
		if ( $res === false ) {
			http_response_code( 500 );
			echo json_encode( [ 'error' => 'Chunk save nahi ho saka.' ] );
			exit;
		}
		if ( $res === 'hash' ) {
			http_response_code( 422 );
			echo json_encode( [ 'error' => 'Chunk ka hash match nahi karta - dobara bhejein.' ] );
			exit;
		}
		if ( $res === 'toobig' ) {
			http_response_code( 413 );
			echo json_encode( [ 'error' => 'Chunk 8 MiB se bara hai.' ] );
			exit;
		}
		hub_import_trace( 'CHUNK-OK upload=' . $uid . ' index=' . $idx . ' bytes=' . $res['bytes'] );
		echo json_encode( [ 'ok' => true, 'index' => $res['index'], 'bytes' => $res['bytes'] ] );
		exit;
	}
	if ( $chunkAction === 'import_status' ) {
		$jbody = json_decode( (string) file_get_contents( 'php://input' ), true );
		$uid = (string) ( is_array( $jbody ) ? ( $jbody['upload_id'] ?? '' ) : '' );
		if ( ! hub_import_upload_id_ok( $uid ) ) {
			http_response_code( 400 );
			echo json_encode( [ 'error' => 'upload_id ghalat hai.' ] );
			exit;
		}
		hub_import_cleanup_stale_parts();
		$dir = hub_import_parts_dir( $uid );
		$got = [];
		if ( is_dir( $dir ) ) {
			foreach ( glob( $dir . '/*.part' ) as $p ) { $got[] = (int) basename( $p, '.part' ); }
			sort( $got );
		}
		echo json_encode( [ 'ok' => true, 'received' => $got ] );
		exit;
	}
	// import_finalize
	$jbody = json_decode( (string) file_get_contents( 'php://input' ), true );
	$uid = (string) ( is_array( $jbody ) ? ( $jbody['upload_id'] ?? '' ) : '' );
	$name = strtolower( trim( (string) ( is_array( $jbody ) ? ( $jbody['name'] ?? '' ) : '' ) ) );
	$name = trim( preg_replace( '/[^a-z0-9\-]+/', '-', $name ), '-' );
	$total = (int) ( is_array( $jbody ) ? ( $jbody['total_chunks'] ?? 0 ) : 0 );
	$tsize = (int) ( is_array( $jbody ) ? ( $jbody['total_size'] ?? 0 ) : 0 );
	hub_import_trace( 'IMPORT-START name=' . $name . ' upload=' . $uid . ' chunks=' . $total . ' size=' . $tsize );
	if ( ! hub_import_upload_id_ok( $uid ) || ! hub_slug_ok( $name ) ) {
		http_response_code( 400 );
		echo json_encode( [ 'error' => 'Project ka naam sirf a-z, 0-9 aur - mein rakhein.' ] );
		exit;
	}
	if ( hub_site_meta( hub_sites_dir() . '/' . $name ) ) {
		http_response_code( 409 );
		echo json_encode( [ 'error' => 'Is naam ka project pehle se mojood hai.' ] );
		exit;
	}
	if ( $total < 1 || $total > 1024 || $tsize < 1 ) {
		http_response_code( 400 );
		echo json_encode( [ 'error' => 'Finalize ki tafseel ghalat hai.' ] );
		exit;
	}
	$res = hub_import_assemble( $uid, $name, $total, $tsize );
	if ( $res === 'missing' ) {
		http_response_code( 422 );
		echo json_encode( [ 'error' => 'Kuch chunks ghayab hain - pehle unhein bhejein.' ] );
		exit;
	}
	if ( $res === 'size' ) {
		http_response_code( 422 );
		echo json_encode( [ 'error' => 'Jora hua zip size match nahi karta.' ] );
		exit;
	}
	if ( $res === false ) {
		http_response_code( 500 );
		echo json_encode( [ 'error' => 'Zip assemble nahi ho saka.' ] );
		exit;
	}
	hub_import_trace( 'IMPORT-ASSEMBLED name=' . $name . ' size=' . $res['size'] . ' sha256=' . $res['sha256'] );
	hub_rrmdir( hub_import_parts_dir( $uid ) );
	hub_import_async( $name, $res['zip'] );
	hub_import_trace( 'IMPORT-AFTER-SPAWN name=' . $name );
	if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
	$_SESSION['flash'] = "Site '$name' import ho rahi hai - list mein Creating nazar aye gi. (Sirf SQLite wali TAMEERO-jaisi sites import hoti hain.)";
	echo json_encode( [ 'redirect' => '/?view=projects', 'sha256' => $res['sha256'], 'size' => $res['size'] ] );
	exit;
}

// ---------- Actions (POST + CSRF) ----------
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	if ( ! hash_equals( $csrf, $_POST['csrf'] ?? '' ) ) {
		hub_back( 'Session token ghalat hai — page refresh karke dobara koshish karein.' );
	}
	// Session lock foran chhor do: lambay actions (start/stop/export)
	// ke dauraan lock pakre rakhne se usi session ki baqi saari
	// requests ruk jati hain (smoke mein pakra gaya asal bug).
	session_write_close();
	$action = $_POST['action'] ?? '';
	$slug   = $_POST['site'] ?? '';

	if ( $action === 'create' ) {
		$name  = strtolower( trim( $_POST['name'] ?? '' ) );
		$name  = preg_replace( '/[^a-z0-9\-]+/', '-', $name );
		$name  = trim( $name, '-' );
		$bpRaw = $_POST['blueprint'] ?? 'business';
		$user  = trim( $_POST['admin_user'] ?? 'admin' ) ?: 'admin';
		$email = trim( $_POST['admin_email'] ?? 'admin@example.local' ) ?: 'admin@example.local';
		$pass  = $_POST['admin_pass'] ?? '';
		$demo  = ! empty( $_POST['demo_content'] );
		// Theme choice (Dukaan v1): sirf wahi theme jo chuni gayi
		// blueprint ne declare ki ho — warna khamoshi se default.
		$theme = trim( (string) ( $_POST['theme'] ?? '' ) );
		if ( $theme !== '' && $theme !== hub_blueprint_theme( $bpRaw ) ) { $theme = ''; }
		if ( ! hub_slug_ok( $name ) ) { hub_back( 'Project ka naam sirf a-z, 0-9 aur - mein rakhein.' ); }
		if ( hub_site_meta( hub_sites_dir() . '/' . $name ) ) { hub_back( 'Is naam ka project pehle se mojood hai.' ); }
		// Template se site (feature 11) — admin template wala rehta
		// hai, is liye password ki shart yahan nahi lagti.
		if ( strpos( $bpRaw, 'tpl:' ) === 0 ) {
			$tpl = substr( $bpRaw, 4 );
			if ( ! hub_slug_ok( $tpl ) || ! file_exists( hub_templates_dir() . '/' . $tpl . '/.webdevhub-template.json' ) ) {
				hub_back( 'Template nahi mili.' );
			}
			hub_create_async( $name, 'business', $user, $email, $pass, $tpl, false );
			hub_back( "Project '$name' template '$tpl' se bann raha hai — admin wahi chalega jo template wali site ka tha." );
		}
		$allowedBp = array_merge( array_keys( hub_blueprints() ), [ 'blank' ] );
		if ( ! in_array( $bpRaw, $allowedBp, true ) ) { hub_back( 'Blueprint ghalat hai.' ); }
		if ( strlen( $pass ) < 6 ) { hub_back( 'Admin password kam az kam 6 characters rakhein.' ); }
		hub_create_async( $name, $bpRaw, $user, $email, $pass, '', $demo, $theme );
		hub_back( "Project '$name' bann raha hai — list mein Creating nazar ayega, tayyar hone par Running/Band status ayega." );
	}

	// Clone (feature 2)
	if ( $action === 'clone' && hub_slug_ok( $slug ) ) {
		$new = strtolower( trim( $_POST['new_name'] ?? '' ) );
		$new = trim( preg_replace( '/[^a-z0-9\-]+/', '-', $new ), '-' );
		if ( ! hub_slug_ok( $new ) ) { hub_back( 'Clone ka naam sirf a-z, 0-9 aur - mein rakhein.' ); }
		if ( hub_site_meta( hub_sites_dir() . '/' . $new ) ) { hub_back( 'Is naam ka project pehle se mojood hai.' ); }
		hub_clone_async( $slug, $new );
		hub_back( "Clone '$new' bann raha hai — asal site agar chal rahi thi to copy ke dauraan chand minute band rahe gi. Admin clone mein wahi chalega jo asal site ka hai." );
	}

	// Template banana (feature 11)
	if ( $action === 'totemplate' && hub_slug_ok( $slug ) ) {
		$tpl = strtolower( trim( $_POST['tpl_name'] ?? '' ) );
		$tpl = trim( preg_replace( '/[^a-z0-9\-]+/', '-', $tpl ), '-' );
		if ( ! hub_slug_ok( $tpl ) ) { hub_back( 'Template ka naam sirf a-z, 0-9 aur - mein rakhein.' ); }
		if ( file_exists( hub_templates_dir() . '/' . $tpl ) ) { hub_back( 'Is naam ki template pehle se mojood hai.' ); }
		hub_totemplate_async( $slug, $tpl );
		hub_back( "Template '$tpl' bann rahi hai — tayyar hone par neeche Templates mein nazar aye gi. (Asal site chal rahi thi to chand minute band rahe gi.)" );
	}

	// Template delete
	if ( $action === 'tpl_delete' ) {
		$tpl = $_POST['tpl'] ?? '';
		if ( hub_slug_ok( $tpl ) ) {
			$base = realpath( hub_templates_dir() );
			$real = realpath( hub_templates_dir() . '/' . $tpl );
			if ( $base && $real && strpos( $real, $base ) === 0 && $real !== $base ) {
				hub_rrmdir( $real );
				@unlink( hub_templates_dir() . '/' . $tpl . '.job.log' );
				hub_back( "Template '$tpl' delete ho gayi." );
			}
		}
		hub_back( 'Template delete nahi ho saki.' );
	}

	// Zip import (feature 13) - v2.8.14: legacy multipart 'import'
	// action hata di gayi hai. Import ab sirf chunked protocol se
	// hota hai (X-WDH-Action headers, dispatch se upar). Worker
	// (hub_import_async) waisa hi hai; MCP site_import bhi usi ko
	// seedha bulata hai, is liye woh mutasir nahi hoga.

	// Backup abhi (feature 20)
	if ( $action === 'backup' && hub_slug_ok( $slug ) ) {
		hub_spawn_worker( 'backup --site ' . hub_q( $slug ), [], hub_sites_dir() . '/.' . $slug . '.backup.log' );
		hub_back( "'$slug' ka backup ban raha hai — chand minute mein card par 'Aakhri backup' taaza ho jayega. Backups is computer par sites ke sath wali .exports folder mein rehte hain." );
	}

	if ( in_array( $action, [ 'start', 'stop' ], true ) && hub_slug_ok( $slug ) ) {
		hub_run_manager( $action, $slug );
		if ( $action === 'start' ) {
			// UI polish #3: start par card ki photo khud taaza ho
			// (background worker — start kabhi is par nahi rukta).
			hub_spawn_worker( 'thumb-auto --site ' . hub_q( $slug ), [], hub_sites_dir() . '/.' . $slug . '.thumb.log' );
		}
		hub_back( $action === 'start' ? "'$slug' shuru kar diya." : "'$slug' band kar diya." );
	}

	// Sab projects ek sath (feature 3)
	if ( $action === 'start_all' || $action === 'stop_all' ) {
		$n = 0;
		foreach ( hub_sites() as $s ) {
			if ( ! empty( $s['creating'] ) || ! empty( $s['failed'] ) ) { continue; }
			if ( $action === 'start_all' && empty( $s['running'] ) ) { hub_run_manager( 'start', $s['site'] ); hub_spawn_worker( 'thumb-auto --site ' . hub_q( $s['site'] ), [], hub_sites_dir() . '/.' . $s['site'] . '.thumb.log' ); $n++; }
			if ( $action === 'stop_all' && ! empty( $s['running'] ) ) { hub_run_manager( 'stop', $s['site'] ); $n++; }
		}
		hub_back( $action === 'start_all' ? "$n projects shuru kar diye." : "$n projects band kar diye." );
	}

	// Admin password reset (feature 7) — password sirf env file se jata hai
	if ( $action === 'resetpass' && hub_slug_ok( $slug ) ) {
		$new = $_POST['new_pass'] ?? '';
		if ( strlen( $new ) < 6 ) { hub_back( 'Naya password kam az kam 6 characters rakhein.' ); }
		$out = hub_worker_wait( 'resetpass --site ' . hub_q( $slug ), [ 'WDH_NEW_PASS' => $new ], 90 );
		if ( strpos( $out, 'Password badal gaya' ) !== false ) {
			hub_back( "'$slug' ka admin password badal gaya. Ab one-click Admin ya wp-admin se naye password ke sath login hoga." );
		}
		hub_back( 'Password reset nahi ho saka. ' . $out );
	}

	// DB: ek cell ki value badalna (feature 8) — table/column dono
	// whitelist se guzarte hain, value prepared statement se jati hai.
	if ( ( $action === 'db_edit' || $action === 'db_query' ) && hub_slug_ok( $slug ) ) {
		$back = '/?view=db&site=' . urlencode( $slug ) . '&table=' . urlencode( $_POST['table'] ?? '' );
		$dfile2 = hub_sites_dir() . '/' . $slug . '/wp-content/database/.ht.sqlite';
		if ( ! file_exists( $dfile2 ) ) { hub_back_to( 'Database file nahi mili.', $back ); }
		try {
			$pdo2 = new PDO( 'sqlite:' . $dfile2, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
			$pdo2->exec( 'PRAGMA busy_timeout = 5000' );
			$tbls = [];
			foreach ( $pdo2->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'" ) as $r ) { $tbls[] = $r['name']; }
			$table2 = $_POST['table'] ?? '';
			if ( $action === 'db_edit' ) {
				if ( ! in_array( $table2, $tbls, true ) ) { hub_back_to( 'Table ghalat hai.', $back ); }
				$cols2 = [];
				foreach ( $pdo2->query( 'SELECT name FROM pragma_table_info(' . $pdo2->quote( $table2 ) . ')' ) as $c ) { $cols2[] = $c['name']; }
				$col2 = $_POST['col'] ?? '';
				if ( ! in_array( $col2, $cols2, true ) ) { hub_back_to( 'Column ghalat hai.', $back ); }
				$st = $pdo2->prepare( 'UPDATE "' . str_replace( '"', '""', $table2 ) . '" SET "' . str_replace( '"', '""', $col2 ) . '" = ? WHERE rowid = ?' );
				$st->execute( [ (string) ( $_POST['value'] ?? '' ), (int) ( $_POST['rowid'] ?? 0 ) ] );
				hub_back_to( "'$table2.$col2' ki value badal gayi (rows: " . $st->rowCount() . ').', $back );
			}
			// db_query — sirf parhne wale sawal
			$sql = trim( (string) ( $_POST['sql'] ?? '' ) );
			if ( ! preg_match( '/^(select|with|pragma|explain)\b/i', $sql ) ) { hub_back_to( 'Sirf SELECT wale sawal chal sakte hain.', $back ); }
			$sqlCore = rtrim( $sql, "; \t\n" );
			if ( strpos( $sqlCore, ';' ) !== false ) { hub_back_to( 'Ek waqt mein sirf ek sawal.', $back ); }
			$q2 = $pdo2->query( $sqlCore );
			$rows2 = $q2->fetchAll( PDO::FETCH_ASSOC );
			$colsQ = $rows2 ? array_keys( $rows2[0] ) : [];
			if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
			$_SESSION['db_sql'] = $sql;
			$_SESSION['db_result'] = [ 'cols' => $colsQ, 'rows' => array_slice( $rows2, 0, 200 ) ];
			hub_back_to( 'Sawal chal gaya — ' . count( $rows2 ) . ' rows milein.', $back );
		} catch ( Throwable $e ) {
			hub_back_to( 'Database masla: ' . $e->getMessage(), $back );
		}
	}

	// Mail: saari saaf ya ek delete (feature 6)
	if ( ( $action === 'mail_clear' || $action === 'mail_delete' ) && hub_slug_ok( $slug ) ) {
		$back = '/?view=mail&site=' . urlencode( $slug );
		$mdir = hub_mail_dir( hub_sites_dir() . '/' . $slug );
		if ( $action === 'mail_clear' ) {
			foreach ( glob( $mdir . '/*.json' ) ?: [] as $f ) { @unlink( $f ); }
			hub_back_to( "'$slug' ki saari pakri gayi mail saaf ho gayi.", $back );
		}
		$id = basename( (string) ( $_POST['id'] ?? '' ) );
		if ( $id === '' || substr( $id, -5 ) !== '.json' || ! file_exists( $mdir . '/' . $id ) ) { hub_back_to( 'Mail nahi mili.', $back ); }
		@unlink( $mdir . '/' . $id );
		hub_back_to( 'Mail delete ho gayi.', $back );
	}

	// Plugin chalu/band (feature 9) — khatam ho kar plugins view par wapas
	if ( $action === 'plugin_toggle' && hub_slug_ok( $slug ) ) {
		$plugin = $_POST['plugin'] ?? '';
		$mode = $_POST['mode'] ?? '';
		$back = '/?view=plugins&site=' . urlencode( $slug );
		// v2.10 Phase 3: Details ke Plugins tab se toggle aye to
		// wapas wahi tab par — return_to sirf is site ke apne tab
		// wale URL se match ho tab hi qabool hai.
		$wantTab = '/?view=site&site=' . urlencode( $slug ) . '&tab=plugins';
		if ( ( $_POST['return_to'] ?? '' ) === $wantTab ) { $back = $wantTab; }
		$out = hub_worker_wait( 'plugintoggle --site ' . hub_q( $slug ) . ' --plugin ' . hub_q( $plugin ) . ' --mode ' . hub_q( $mode ), [], 120 );
		if ( strpos( $out, 'Plugin chalu ho gaya' ) !== false || strpos( $out, 'Plugin band ho gaya' ) !== false ) {
			hub_back_to( "'$slug': " . trim( strtok( $out, "\n" ) ), $back );
		}
		hub_back_to( 'Plugin toggle nahi ho saka. ' . $out, $back );
	}

	// Thumbnail taaza karna (feature 1)
	if ( $action === 'thumb' && hub_slug_ok( $slug ) ) {
		$out = hub_worker_wait( 'thumb --site ' . hub_q( $slug ), [], 60 );
		hub_back( "'$slug' ki photo: " . ( $out ?: 'koshish ho gayi' ) );
	}

	// Deploy package (feature 16) — banta hai to download stream hota hai
	if ( $action === 'deploy' && hub_slug_ok( $slug ) ) {
		$out = hub_worker_wait( 'deploy --site ' . hub_q( $slug ), [], 900 );
		if ( preg_match( '/Deploy package tayyar hai: (\S+\.zip)/', $out, $dm ) && file_exists( $dm[1] ) ) {
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . basename( $dm[1] ) . '"' );
			header( 'Content-Length: ' . filesize( $dm[1] ) );
			readfile( $dm[1] );
			unlink( $dm[1] );
			exit;
		}
		hub_back( "'$slug' ka deploy package nahi bana. " . $out );
	}

	// Live preview link on/off (feature 15)
	if ( $action === 'preview_toggle' && hub_slug_ok( $slug ) ) {
		$dir = hub_sites_dir() . '/' . $slug;
		if ( hub_preview_running( $dir ) ) {
			hub_worker_wait( 'previewstop --site ' . hub_q( $slug ), [], 60 );
			hub_back( "'$slug' ka preview link band ho gaya." );
		}
		$out = hub_worker_wait( 'previewstart --site ' . hub_q( $slug ), [], 240 );
		if ( strpos( $out, 'Preview link tayyar hai' ) !== false ) {
			hub_back( "'$slug': " . trim( strtok( $out, "\n" ) ) );
		}
		hub_back( "'$slug' ka preview nahi bana. " . $out );
	}

	// Local HTTPS on/off (feature 19)
	if ( $action === 'https_toggle' && hub_slug_ok( $slug ) ) {
		$dir = hub_sites_dir() . '/' . $slug;
		if ( hub_https_running( $dir ) ) {
			$out = hub_worker_wait( 'httpsstop --site ' . hub_q( $slug ), [], 60 );
			hub_back( "'$slug' ka HTTPS band ho gaya." );
		}
		$out = hub_worker_wait( 'httpsstart --site ' . hub_q( $slug ), [], 90 );
		if ( strpos( $out, 'HTTPS tayyar hai' ) !== false ) {
			hub_back( "'$slug': " . trim( strtok( $out, "\n" ) ) );
		}
		hub_back( "'$slug' ka HTTPS start nahi hua. " . $out );
	}

	// Site ka PHP version badalna (feature 18) — chalti site pehle
	// band hoti hai; phir Shuru karne par nayi PHP se uthegi.
	if ( $action === 'set_php' && hub_slug_ok( $slug ) ) {
		$line = $_POST['php_line'] ?? '';
		$dir = hub_sites_dir() . '/' . $slug;
		$meta = hub_site_meta( $dir );
		if ( ! $meta ) { hub_back( 'Site nahi mili.' ); }
		if ( $line !== '' && ! hub_php_bin_for_line( $line ) ) { hub_back( "PHP $line install nahi hai — pehle Health page se download karein." ); }
		$wasRunning = hub_site_running( $dir );
		if ( $wasRunning ) { hub_run_manager( 'stop', $slug ); }
		if ( $line === '' ) { $meta['_remove'] = [ 'php_line' ]; } else { $meta['php_line'] = $line; }
		hub_save_meta( $dir, $meta );
		hub_back( "'$slug' ka PHP ab " . ( $line === '' ? 'default' : $line ) . ' hai.' . ( $wasRunning ? ' Site band hai — Shuru Karein dabayein.' : '' ) );
	}

	// WebDevHub update check (feature 22)
	if ( $action === 'update_check' ) {
		$out = hub_worker_wait( 'updatecheck', [], 90 );
		hub_back_to( trim( strtok( $out, "\n" ) ) ?: 'Update check ho gaya.', '/?view=health' );
	}

	// WebDevHub update lagana (feature 22)
	if ( $action === 'update_apply' ) {
		$out = hub_worker_wait( 'updateapply', [], 900 );
		if ( strpos( $out, 'Update lag gaya' ) !== false ) {
			hub_back_to( trim( strtok( $out, "\n" ) ) . ' Dashboard band karke dobara kholein.', '/?view=health' );
		}
		hub_back_to( 'Update nahi laga. ' . $out, '/?view=health' );
	}

	// Zuban badalna (feature 24)
	if ( $action === 'set_lang' ) {
		$lang = ( $_POST['lang'] ?? '' ) === 'en' ? 'en' : 'ur';
		hub_save_settings( [ 'lang' => $lang ] );
		hub_back( $lang === 'en' ? 'Language changed to English.' : 'Zuban Urdu par ho gayi.' );
	}

	// Lite mode on/off (feature 25)
	if ( $action === 'set_lite' ) {
		$on = ! empty( $_POST['lite_on'] );
		hub_save_settings( [ 'lite' => $on ] );
		$S0 = hub_strings();
		hub_back( $on ? ( hub_lang() === 'en' ? 'Lite mode is ON.' : 'Lite mode ON ho gaya.' ) : ( hub_lang() === 'en' ? 'Lite mode is OFF.' : 'Lite mode OFF ho gaya.' ) );
	}

	// Wizard step 1: zuban + lite (feature 23)
	if ( $action === 'wizard_step1' ) {
		$lang = ( $_POST['lang'] ?? '' ) === 'en' ? 'en' : 'ur';
		hub_save_settings( [ 'lang' => $lang, 'lite' => ! empty( $_POST['lite_on'] ) ] );
		hub_back_to( '', '/?view=wizard&step=2' );
	}

	// Wizard step 2: pehla project (feature 23)
	if ( $action === 'wizard_create' ) {
		$name = trim( (string) ( $_POST['name'] ?? '' ) );
		$bp = $_POST['blueprint'] ?? 'business';
		$pass = (string) ( $_POST['admin_pass'] ?? '' );
		$bps = hub_blueprints();
		if ( ! isset( $bps[ $bp ] ) ) { $bp = 'business'; }
		if ( ! hub_slug_ok( $name ) ) { hub_back_to( hub_lang() === 'en' ? 'Project name is not valid.' : 'Project ka naam theek nahi.', '/?view=wizard&step=2' ); }
		if ( strlen( $pass ) < 6 ) { hub_back_to( hub_lang() === 'en' ? 'Password must be at least 6 characters.' : 'Password kam az kam 6 characters ka ho.', '/?view=wizard&step=2' ); }
		$theme = trim( (string) ( $_POST['theme'] ?? '' ) );
		if ( $theme !== '' && $theme !== hub_blueprint_theme( $bp ) ) { $theme = ''; }
		hub_create_async( $name, $bp, 'admin', 'admin@example.local', $pass, '', false, $theme );
		hub_save_settings( [ 'wizard_done' => true ] );
		hub_back_to( '', '/?view=wizard&step=3' );
	}

	// Wizard chhorna (feature 23)
	if ( $action === 'wizard_skip' ) {
		hub_save_settings( [ 'wizard_done' => true ] );
		hub_back( hub_lang() === 'en' ? 'Wizard skipped. Welcome!' : 'Wizard chhor diya. Khush Aamdeed!' );
	}

	// Naya PHP version download (feature 18) — background worker,
	// haalat Health page par dikhti hai (log file se).
	if ( $action === 'php_download' ) {
		$line = $_POST['php_line'] ?? '';
		$man = hub_php_versions_manifest();
		$ok = false;
		foreach ( (array) ( $man['versions'] ?? [] ) as $v ) { if ( ( $v['line'] ?? '' ) === $line ) { $ok = true; break; } }
		if ( ! $ok ) { hub_back( 'PHP line ghalat hai.' ); }
		if ( hub_php_bin_for_line( $line ) ) { hub_back( "PHP $line pehle se install hai." ); }
		$log = hub_sites_dir() . '/.phpdl-' . $line . '.log';
		@unlink( $log );
		hub_spawn_worker( 'phpdownload --line ' . hub_q( $line ), [], $log );
		hub_back_to( "PHP $line download shuru ho gaya hai — is page par haalat refresh karke dekhein.", '/?view=health' );
	}

	// MCP agent action par owner ka faisla (v2.7)
	// Agent Connection: token banana/khatam + MCP on/off (owner hi
	// kar sakta hai — ye dashboard ke POST hain, CSRF mehfooz).
	if ( $action === 'mcp_token_generate' ) {
		$token = bin2hex( random_bytes( 24 ) );
		hub_save_settings( [ 'mcp_token_hash' => hash( 'sha256', $token ) ] );
		// Actions ke dauraan session band hota hai — token wali
		// key likhne se pehle dobara kholo (warna key ghayab ho
		// jati hai; hub_back_to bhi yahi karta hai).
		if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
		$_SESSION['mcp_token_once'] = $token; // sirf ek dafa dikhe ga
		hub_back_to( 'Naya token ban gaya hai.', '/?view=agent' );
	}
	if ( $action === 'mcp_token_revoke' ) {
		hub_save_settings( [ 'mcp_token_hash' => '' ] );
		hub_back_to( 'Token khatam kar diya — purana token ab nahi chalega.', '/?view=agent' );
	}
	if ( $action === 'mcp_toggle' ) {
		// v2.8: toggle ab asal server spawn/stop karta hai (v2.7 tak
		// sirf flag tha — field bug, pending-fixes #4).
		$want = ! empty( $_POST['want'] ) ? 1 : 0;
		hub_save_settings( [ 'mcp_enabled' => $want ] );
		if ( $want ) {
			$st = hub_mcp_server_start();
			hub_back_to( $st['running']
				? 'MCP server CHAL para hai — http://127.0.0.1:' . $st['port'] . ' (PID ' . $st['pid'] . ').'
				: 'MCP server start nahi ho saka — panel/.mcp-server.log dekhein.', '/?view=agent' );
		}
		hub_mcp_server_stop();
		hub_back_to( 'MCP server band kar diya gaya.', '/?view=agent' );
	}
	if ( $action === 'mcp_bind' ) {
		// MCP kis interface par sune: sirf ye PC (127.0.0.1) ya saara
		// local network/Tailscale (0.0.0.0). Token har surat lazmi hai.
		$bind = ( ( $_POST['bind'] ?? '' ) === '0.0.0.0' ) ? '0.0.0.0' : '127.0.0.1';
		hub_save_settings( [ 'mcp_bind' => $bind ] );
		$st = hub_mcp_server_status();
		if ( $st['running'] ) { hub_mcp_server_stop(); hub_mcp_server_start(); }
		hub_back_to( 'MCP ka access badal gaya: ' . $bind . ( $bind === '0.0.0.0' ? ' (LAN/Tailscale — token lazmi rahe ga)' : ' (sirf is PC par)' ), '/?view=agent' );
	}

	// AI Chat ki settings (v2.8): gateway ka pata + model. Default
	// 9Router (http://127.0.0.1:20128/v1); model khaali = gateway ki
	// apni pasand. MCP token yahan kabhi nahi - chat in-process hai.
	// Gateway Key (v2.8.15): owner ki 9Router key. Field khaali
	// chhorne par purani key mehfooz rehti hai (key page par kabhi
	// wapas print nahi hoti - screenshot-safe).
	if ( $action === 'chat_settings' ) {
		require_once dirname( __DIR__ ) . '/lib/chat.php';
		$url = trim( (string) ( $_POST['chat_gateway_url'] ?? '' ) );
		if ( $url === '' ) { $url = 'http://127.0.0.1:20128/v1'; }
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			hub_back_to( 'Chat gateway ka pata theek nahi — http:// ya https:// se shuru ho.', '/?view=agent' );
		}
		$model = substr( trim( (string) ( $_POST['chat_model'] ?? '' ) ), 0, 120 );
		// Provider preset (v2.9.0): whitelist se bahar ho to custom.
		$prov = (string) ( $_POST['chat_provider'] ?? '' );
		if ( ! isset( hub_chat_providers()[ $prov ] ) ) { $prov = 'custom'; }
		$patch = [ 'chat_gateway_url' => rtrim( $url, '/' ), 'chat_model' => $model, 'chat_provider' => $prov ];
		$gwkey = substr( trim( (string) ( $_POST['chat_gateway_key'] ?? '' ) ), 0, 200 );
		if ( $gwkey !== '' ) { $patch['chat_gateway_key'] = $gwkey; }
		hub_save_settings( $patch );
		hub_back_to( 'Chat settings save ho gayi.', '/?view=agent' );
	}

	if ( $action === 'mcp_approve' || $action === 'mcp_reject' ) {
		$r = hub_mcp_action_decide( $_POST['action_id'] ?? '', $action === 'mcp_approve', trim( (string) ( $_POST['confirm'] ?? '' ) ) );
		hub_back_to( $r === true ? 'Faisla darj ho gaya.' : $r, '/?view=agent' );
	}

	if ( $action === 'delete' && hub_slug_ok( $slug ) ) {
		hub_delete( $slug ) ? hub_back( "'$slug' delete ho gaya." ) : hub_back( 'Delete nahi ho saka.' );
	}

	if ( $action === 'export' && hub_slug_ok( $slug ) ) {
		$zip = hub_export( $slug );
		if ( $zip ) {
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . basename( $zip ) . '"' );
			header( 'Content-Length: ' . filesize( $zip ) );
			readfile( $zip );
			unlink( $zip );
			exit;
		}
		hub_back( 'Export nahi ho saka.' );
	}
	hub_back( 'Unknown action.' );
}

// ---------- Creation job log (v2.6.2) ----------
if ( ( $_GET['view'] ?? '' ) === 'joblog' ) {
	$lslug = $_GET['site'] ?? '';
	$lok = hub_slug_ok( $lslug );
	$ltail = $lok ? hub_job_log_tail( $lslug, 80 ) : '';
	wdh_shell_open( 'projects', 'Creation Log: ' . $lslug, [ 'rail' => false ] );
	?>
  <h1>Creation Log: <span><?php echo htmlspecialchars( $lslug ); ?></span></h1>
  <p><a class="btn" href="/"><?php echo htmlspecialchars( $S['h_back'] ); ?></a></p>
  <?php if ( ! $lok || $ltail === '' ) : ?>
  <p>Is site ka job log nahi mila.</p>
  <?php else : ?>
  <pre><?php echo htmlspecialchars( $ltail ); ?></pre>
  <?php endif; ?>
	<?php
	wdh_shell_close( false );
	exit;
}

// ---------- Agent Activity (MCP approval queue, v2.7) ----------
if ( ( $_GET['view'] ?? '' ) === 'agent' ) {
	$S = hub_strings();
	$actions = hub_mcp_actions_list( 30 );
	$pending = array_values( array_filter( $actions, function ( $r ) { return ( $r['status'] ?? '' ) === 'pending'; } ) );
	$recent = array_values( array_filter( $actions, function ( $r ) { return ( $r['status'] ?? '' ) !== 'pending'; } ) );
	$audit = hub_mcp_audit_tail( 15 );
	// $flash upar (file ke shuru mein) session se nikaal liya gaya
	// tha — yahan wahi dikhe ga (v2.8: GET par session band rehta hai).
	wdh_shell_open( 'agent', 'Agent Activity' );
	?>
  <h1><?php echo htmlspecialchars( $S['agent_activity'] ); ?></h1>
  <p><a class="btn" href="/"><?php echo htmlspecialchars( $S['h_back'] ); ?></a></p>
  <?php $mset = hub_settings(); $mcpOn = ! empty( $mset['mcp_enabled'] ); $tokSet = ! empty( $mset['mcp_token_hash'] );
	$tokOnce = $sessTokOnce;
	// v2.8: status ab ASAL process ki haalat hai. MCP on ho lekin
	// server band para ho (masalan PC restart ke baad) to yahin
	// khud start kar do — owner ko haath se kuch nahi chalana.
	$mst = hub_mcp_server_status();
	if ( $mcpOn && ! $mst['running'] ) { $mst = hub_mcp_server_start(); }
	$mbind = (string) ( $mset['mcp_bind'] ?? '127.0.0.1' ); ?>
  <h2><?php echo htmlspecialchars( $S['agent_conn'] ); ?></h2>
  <div class="card">
    <div class="meta"><?php echo htmlspecialchars( $S['agent_conn_state'] ); ?>: <b><?php echo $mst['running'] ? 'CHAL raha hai (PID ' . $mst['pid'] . ')' : ( $mcpOn ? 'BAND hai — start nahi ho saka, .mcp-server.log dekhein' : 'OFF hai' ); ?></b>
    · <?php echo htmlspecialchars( $tokSet ? $S['agent_token_set'] : $S['agent_token_none'] ); ?> · http://<?php echo htmlspecialchars( $mbind === '0.0.0.0' ? '127.0.0.1' : $mbind ); ?>:8971</div>
    <?php if ( $tokOnce ) : ?>
    <div style="margin-top:10px"><?php echo htmlspecialchars( $S['agent_token_once'] ); ?></div>
    <pre style="background:#0f1420;border:1px solid #f0b64a;border-radius:9px;padding:10px 12px;overflow:auto;font-size:13px"><?php echo htmlspecialchars( $tokOnce ); ?></pre>
    <?php endif; ?>
    <form class="inline" method="post" action="/">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="want" value="<?php echo $mcpOn ? '0' : '1'; ?>">
      <button class="btn" name="action" value="mcp_toggle"><?php echo htmlspecialchars( $mcpOn ? $S['agent_turn_off'] : $S['agent_turn_on'] ); ?></button>
    </form>
    <form class="inline" method="post" action="/">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn" name="action" value="mcp_token_generate"><?php echo htmlspecialchars( $S['agent_token_gen'] ); ?></button>
    </form>
    <?php if ( $tokSet ) : ?>
    <form class="inline" method="post" action="/">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn stop" name="action" value="mcp_token_revoke"><?php echo htmlspecialchars( $S['agent_token_revoke'] ); ?></button>
    </form>
    <?php endif; ?>
    <form class="inline" method="post" action="/">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <select name="bind" style="background:#0f1420;border:1px solid #33477a;color:#e8ecf3;padding:7px 10px;border-radius:9px;font-size:13px">
        <option value="127.0.0.1"<?php echo $mbind !== '0.0.0.0' ? ' selected' : ''; ?>>Sirf is PC par</option>
        <option value="0.0.0.0"<?php echo $mbind === '0.0.0.0' ? ' selected' : ''; ?>>LAN / Tailscale par bhi</option>
      </select>
      <button class="btn" name="action" value="mcp_bind">Access Badlein</button>
    </form>
    <div class="meta" style="margin-top:10px"><?php echo htmlspecialchars( $S['agent_conn_how'] ); ?></div>
    <pre style="background:#0f1420;border:1px solid #26314a;border-radius:9px;padding:10px 12px;overflow:auto;font-size:12.5px">{
  "mcpServers": {
    "tameero": {
      "type": "http",
      "url": "http://127.0.0.1:8971",
      "headers": { "Authorization": "Bearer &lt;TOKEN&gt;" }
    }
  }
}</pre>
  </div>

  <h2><?php echo htmlspecialchars( $S['agent_pending'] ); ?> (<?php echo count( $pending ); ?>)</h2>
  <?php if ( ! $pending ) : ?>
  <p><?php echo htmlspecialchars( $S['agent_none_pending'] ); ?></p>
  <?php endif; ?>
  <?php foreach ( $pending as $r ) : $mins = max( 0, (int) ceil( ( (int) $r['expires_at'] - time() ) / 60 ) ); ?>
  <div class="card">
    <div class="sum"><?php echo htmlspecialchars( (string) $r['summary'] ); ?><?php if ( ( $r['class'] ?? '' ) === 'destructive' ) : ?> <span class="warn">⚠ DESTRUCTIVE</span><?php endif; ?></div>
    <div class="meta"><?php echo htmlspecialchars( $S['agent_client'] ); ?>: <?php echo htmlspecialchars( (string) $r['client'] ); ?> · <?php echo htmlspecialchars( (string) $r['created_at'] ); ?> · <span class="warn"><?php echo htmlspecialchars( $S['agent_expires'] ?? 'Expires' ); ?> ~<?php echo $mins; ?> min</span></div>
    <form class="inline" method="post" action="/">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action_id" value="<?php echo htmlspecialchars( (string) $r['id'] ); ?>">
      <?php if ( ( $r['class'] ?? '' ) === 'destructive' ) : ?>
      <input class="confirm" type="text" name="confirm" placeholder="<?php echo htmlspecialchars( $S['agent_confirm_ph'] ); ?>" autocomplete="off">
      <?php endif; ?>
      <button class="btn go" name="action" value="mcp_approve"><?php echo htmlspecialchars( $S['agent_approve'] ); ?></button>
    </form>
    <form class="inline" method="post" action="/">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action_id" value="<?php echo htmlspecialchars( (string) $r['id'] ); ?>">
      <button class="btn stop" name="action" value="mcp_reject"><?php echo htmlspecialchars( $S['agent_reject'] ); ?></button>
    </form>
  </div>
  <?php endforeach; ?>

  <h2><?php echo htmlspecialchars( $S['agent_recent'] ); ?></h2>
  <?php if ( ! $recent && ! $audit ) : ?>
  <p><?php echo htmlspecialchars( $S['agent_none_recent'] ); ?></p>
  <?php endif; ?>
  <?php if ( $recent ) : ?>
  <table>
    <?php foreach ( $recent as $r ) : ?>
    <tr>
      <td class="k"><?php echo htmlspecialchars( (string) ( $r['finished_at'] ?? $r['decided_at'] ?? $r['created_at'] ?? '' ) ); ?></td>
      <td><?php echo htmlspecialchars( (string) $r['summary'] ); ?><?php if ( ! empty( $r['error'] ) ) : ?> — <span style="color:#e5534b"><?php echo htmlspecialchars( (string) $r['error'] ); ?></span><?php endif; ?></td>
      <td><span class="chip <?php echo htmlspecialchars( (string) $r['status'] ); ?>"><?php echo htmlspecialchars( (string) $r['status'] ); ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if ( $audit ) : ?>
  <table style="margin-top:12px">
    <?php foreach ( $audit as $e ) : ?>
    <tr>
      <td class="k"><?php echo htmlspecialchars( (string) ( $e['at'] ?? '' ) ); ?></td>
      <td><b><?php echo htmlspecialchars( (string) ( $e['tool'] ?? '' ) ); ?></b> · <?php echo htmlspecialchars( (string) ( $e['client'] ?? '' ) ); ?> · <?php echo htmlspecialchars( (string) ( $e['summary'] ?? '' ) ); ?></td>
      <td><?php echo htmlspecialchars( (string) ( $e['decision'] ?? '' ) ); ?> → <?php echo htmlspecialchars( (string) ( $e['result'] ?? '' ) ); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
	<?php
	wdh_shell_close( true );
	exit;
}

// ---------- Health check page (feature 21) ----------
if ( ( $_GET['view'] ?? '' ) === 'health' ) {
	$h = hub_health();
	$S = hub_strings();
	wdh_shell_open( 'health', $S['h_title'], [ 'rail' => true ] );
	?>
  <h1>TAMEERO — <?php echo htmlspecialchars( $S['h_title'] ); ?></h1>
  <p><a class="btn" href="/"><?php echo htmlspecialchars( $S['h_back'] ); ?></a></p>
  <table>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_version'] ); ?></td><td>
      <?php echo htmlspecialchars( hub_version() ); ?>
      <?php $uc = hub_repo() . '/panel/.update-check.json'; $uci = file_exists( $uc ) ? json_decode( (string) file_get_contents( $uc ), true ) : null; ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <button class="btn" name="action" value="update_check"><?php echo htmlspecialchars( $S['h_update_btn'] ); ?></button>
      </form>
      <?php if ( is_array( $uci ) && ! empty( $uci['ok'] ) ) : ?>
        <br>Last check: <?php echo htmlspecialchars( date( 'd M, h:i A', strtotime( $uci['at'] ) ) ); ?> —
        <?php if ( ! empty( $uci['newer'] ) ) : ?>
          <span style="color:#f5c86b"><?php echo htmlspecialchars( $S['h_update_new'] ); ?>: <?php echo htmlspecialchars( $uci['latest'] ); ?></span>
          <form method="post" style="display:inline" onsubmit="return confirm('TAMEERO update kar dein? Pehle backup banta hai; sites ko kuch nahi hota.');">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <button class="btn" name="action" value="update_apply"><?php echo htmlspecialchars( $S['h_update_apply'] ); ?> (<?php echo htmlspecialchars( $uci['latest'] ); ?>)</button>
          </form>
        <?php else : ?>
          <span class="ok"><?php echo htmlspecialchars( $S['h_update_latest'] ); ?></span>
        <?php endif; ?>
      <?php elseif ( is_array( $uci ) ) : ?>
        <br><span style="color:#f5c86b"><?php echo htmlspecialchars( $S['h_update_fail'] ); ?></span>
      <?php endif; ?>
      <br><span style="color:#9fb0c7"><?php echo htmlspecialchars( $S['h_update_note'] ); ?></span>
    </td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_system'] ); ?></td><td><?php echo htmlspecialchars( $h['os'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_phpver'] ); ?></td><td><?php echo htmlspecialchars( $h['php_version'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_phpbin'] ); ?></td><td><?php echo htmlspecialchars( $h['php_bin'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_sitesdir'] ); ?></td><td><?php echo htmlspecialchars( $h['sites_dir'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_disk'] ); ?></td><td><?php echo hub_human_size( $h['disk_free'] ); ?> / <?php echo hub_human_size( $h['disk_total'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_projects'] ); ?></td><td><?php echo (int) $h['sites_count']; ?> (<?php echo (int) $h['running']; ?> <?php echo htmlspecialchars( $S['h_running_n'] ); ?>)</td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_bundle_wp'] ); ?></td><td><?php echo $h['bundle_wp'] ? '<span class="ok">✓</span> ' . htmlspecialchars( $h['bundle_wp'] ) : '<span class="bad">✗ ' . htmlspecialchars( $S['h_notfound'] ) . '</span>'; ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_bundle_pl'] ); ?></td><td><?php echo (int) $h['bundle_plugins']; ?> <?php echo htmlspecialchars( $S['h_plugins_ready'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_tools'] ); ?></td><td><?php echo htmlspecialchars( $h['tools_dir'] ); ?></td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_ext'] ); ?></td><td>
      <?php foreach ( $h['extensions'] as $e => $has ) : ?>
        <span class="<?php echo $has ? 'ok' : 'bad'; ?>"><?php echo $has ? '✓' : '✗'; ?></span> <?php echo htmlspecialchars( $e ); ?>
      <?php endforeach; ?>
    </td></tr>
    <?php $x = hub_xampp_scan(); ?>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_xampp'] ); ?></td><td>
      <?php if ( $x['installed'] ) : ?>
        <?php echo htmlspecialchars( $S['h_xampp_found'] ); ?>: <?php echo htmlspecialchars( $x['installed'] ); ?><br>
      <?php else : ?>
        <?php echo htmlspecialchars( $S['h_xampp_none'] ); ?><br>
      <?php endif; ?>
      <?php if ( $x['apache_running'] || $x['mysql_running'] ) : ?>
        <span style="color:#f5c86b">⚠ <?php echo htmlspecialchars( $S['h_xampp_running'] ); ?>: <?php echo htmlspecialchars( implode( ', ', $x['procs'] ) ); ?></span><br>
        <?php echo htmlspecialchars( $S['h_xampp_calm'] ); ?>
      <?php else : ?>
        <?php echo htmlspecialchars( $S['h_xampp_idle'] ); ?>
      <?php endif; ?>
    </td></tr>
    <tr><td class="k"><?php echo htmlspecialchars( $S['h_phpvers'] ); ?></td><td>
      <?php foreach ( hub_php_installed() as $pv ) : ?>
        PHP <?php echo htmlspecialchars( $pv['version'] ); ?><?php echo $pv['bundled'] ? ' (default)' : ''; ?> — <?php echo htmlspecialchars( $pv['bin'] ); ?><br>
      <?php endforeach; ?>
      <?php foreach ( (array) ( hub_php_versions_manifest()['versions'] ?? [] ) as $mv ) : ?>
        <?php if ( ! hub_php_bin_for_line( $mv['line'] ) ) : ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="php_line" value="<?php echo htmlspecialchars( $mv['line'] ); ?>">
            <button class="btn" name="action" value="php_download">PHP <?php echo htmlspecialchars( $mv['line'] ); ?> <?php echo htmlspecialchars( $S['h_php_dl'] ); ?> (<?php echo htmlspecialchars( $mv['version'] ); ?>)</button>
          </form>
          <?php $dl = hub_sites_dir() . '/.phpdl-' . $mv['line'] . '.log'; if ( file_exists( $dl ) ) : ?>
            <pre style="white-space:pre-wrap;font-size:12px"><?php echo htmlspecialchars( trim( (string) file_get_contents( $dl ) ) ); ?></pre>
          <?php endif; ?>
        <?php endif; ?>
      <?php endforeach; ?>
      <br><span style="color:#9fb0c7"><?php echo htmlspecialchars( $S['h_php_note'] ); ?></span>
    </td></tr>
  </table>
  <p style="color:#9fb0c7;font-size:13px"><?php echo htmlspecialchars( $S['h_ext_note'] ); ?></p>
	<?php
	wdh_shell_close( true );
	exit;
}

// ---------- Plugin manager (feature 9) ----------
if ( ( $_GET['view'] ?? '' ) === 'plugins' ) {
	$pslug = $_GET['site'] ?? '';
	$S = hub_strings();
	wdh_shell_open( 'projects', 'Plugins: ' . $pslug, [ 'rail' => false ] );
	?>
  <h1>TAMEERO — Plugins: <?php echo htmlspecialchars( $pslug ); ?></h1>
  <p><a class="btn" href="/"><?php echo htmlspecialchars( $S['h_back'] ); ?></a></p>
  <?php wdh_plugins_table( $pslug, $csrf ); ?>
	<?php
	wdh_shell_close( false );
	exit;
}

// ---------- DB tool (feature 8) ----------
if ( ( $_GET['view'] ?? '' ) === 'db' ) {
	$dslug = $_GET['site'] ?? '';
	$S = hub_strings();
	$ddir  = hub_sites_dir() . '/' . $dslug;
	$dmeta = hub_slug_ok( $dslug ) ? hub_site_meta( $ddir ) : null;
	$dfile = $ddir . '/wp-content/database/.ht.sqlite';
	$dtables = [];
	$dtable = $_GET['table'] ?? '';
	$dpage = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$dcols = []; $drows = []; $dtotal = 0; $derr = '';
	if ( $dmeta && file_exists( $dfile ) ) {
		try {
			$dpdo = new PDO( 'sqlite:' . $dfile, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
			$dpdo->exec( 'PRAGMA busy_timeout = 5000' );
			foreach ( $dpdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name" ) as $r ) {
				$dtables[] = $r['name'];
			}
			if ( $dtable === '' && $dtables ) { $dtable = in_array( 'wp_options', $dtables, true ) ? 'wp_options' : $dtables[0]; }
			if ( $dtable !== '' && in_array( $dtable, $dtables, true ) ) {
				$stmt = $dpdo->query( "SELECT name FROM pragma_table_info(" . $dpdo->quote( $dtable ) . ")" );
				foreach ( $stmt as $c ) { $dcols[] = $c['name']; }
				$dtotal = (int) $dpdo->query( 'SELECT COUNT(*) FROM "' . str_replace( '"', '""', $dtable ) . '"' )->fetchColumn();
				$off = ( $dpage - 1 ) * 50;
				$q = $dpdo->query( 'SELECT rowid AS __rowid, * FROM "' . str_replace( '"', '""', $dtable ) . '" LIMIT 50 OFFSET ' . $off );
				foreach ( $q as $row ) { $drows[] = $row; }
			}
			$dpdo = null;
		} catch ( Throwable $e ) {
			$derr = $e->getMessage();
		}
	}
	wdh_shell_open( 'projects', 'Database: ' . $dslug, [ 'rail' => false ] );
	?>
  <h1>TAMEERO — Database: <?php echo htmlspecialchars( $dslug ); ?></h1>
  <p><a class="btn" href="/"><?php echo htmlspecialchars( $S['h_back'] ); ?></a></p>
  <?php if ( ! $dmeta ) : ?>
  <p><?php echo htmlspecialchars( $S['site_not_found'] ); ?></p>
  <?php elseif ( $derr ) : ?>
  <div class="err"><?php echo htmlspecialchars( $S['db_err_open'] ); ?> <?php echo htmlspecialchars( $derr ); ?></div>
  <?php else : ?>
  <div class="cols">
    <div class="dbtables">
      <strong style="font-size:13px"><?php echo htmlspecialchars( $S['db_tables'] ); ?> (<?php echo count( $dtables ); ?>)</strong>
      <?php foreach ( $dtables as $t ) : ?>
      <a class="<?php echo $t === $dtable ? 'on' : ''; ?>" href="/?view=db&amp;site=<?php echo urlencode( $dslug ); ?>&amp;table=<?php echo urlencode( $t ); ?>"><?php echo htmlspecialchars( $t ); ?></a>
      <?php endforeach; ?>
    </div>
    <div class="tblwrap">
      <p class="note"><?php echo sprintf( $S['db_note_fmt'], htmlspecialchars( $dtable ), $dtotal, $dpage ); ?></p>
      <table>
        <tr><?php foreach ( $dcols as $c ) : ?><th><?php echo htmlspecialchars( $c ); ?></th><?php endforeach; ?><th></th></tr>
        <?php foreach ( $drows as $row ) : ?>
        <tr>
          <?php foreach ( $dcols as $c ) : $v = $row[ $c ] ?? null; ?>
          <td title="<?php echo htmlspecialchars( mb_strimwidth( (string) $v, 0, 300 ) ); ?>"><?php echo htmlspecialchars( mb_strimwidth( (string) $v, 0, 60 ) ); ?></td>
          <?php endforeach; ?>
          <td>
            <form method="post" style="display:flex;gap:5px;margin:0" onsubmit="return confirm('Value badal dein? Serialized data kharab ho sakta hai — yaqeen hai?');">
              <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
              <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
              <input type="hidden" name="table" value="<?php echo htmlspecialchars( $dtable ); ?>">
              <input type="hidden" name="rowid" value="<?php echo (int) $row['__rowid']; ?>">
              <select name="col" style="background:#0f1420;color:#e8ecf3;border:1px solid #33477a;border-radius:6px;max-width:110px">
                <?php foreach ( $dcols as $c ) : ?><option value="<?php echo htmlspecialchars( $c ); ?>"><?php echo htmlspecialchars( $c ); ?></option><?php endforeach; ?>
              </select>
              <input class="cell" name="value" placeholder="nayi value">
              <button class="btn" name="action" value="db_edit"><?php echo htmlspecialchars( $S['db_edit_btn'] ); ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <p>
        <?php if ( $dpage > 1 ) : ?><a class="btn" href="/?view=db&amp;site=<?php echo urlencode( $dslug ); ?>&amp;table=<?php echo urlencode( $dtable ); ?>&amp;page=<?php echo $dpage - 1; ?>"><?php echo htmlspecialchars( $S['pg_prev'] ); ?></a><?php endif; ?>
        <?php if ( $dtotal > $dpage * 50 ) : ?><a class="btn" href="/?view=db&amp;site=<?php echo urlencode( $dslug ); ?>&amp;table=<?php echo urlencode( $dtable ); ?>&amp;page=<?php echo $dpage + 1; ?>"><?php echo htmlspecialchars( $S['pg_next'] ); ?></a><?php endif; ?>
      </p>
      <h3 style="font-size:16px"><?php echo htmlspecialchars( $S['db_sql_title'] ); ?></h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
        <input type="hidden" name="table" value="<?php echo htmlspecialchars( $dtable ); ?>">
        <textarea class="sql" name="sql" rows="3" placeholder="SELECT * FROM wp_options WHERE option_name LIKE 'site%'"><?php echo htmlspecialchars( $sessDbSql ); ?></textarea>
        <p><button class="btn" name="action" value="db_query"><?php echo htmlspecialchars( $S['db_run_btn'] ); ?></button></p>
      </form>
      <?php if ( ! empty( $sessDb ) ) : $qr = $sessDb; ?>
      <div class="tblwrap">
        <table>
          <tr><?php foreach ( $qr['cols'] as $c ) : ?><th><?php echo htmlspecialchars( $c ); ?></th><?php endforeach; ?></tr>
          <?php foreach ( $qr['rows'] as $r ) : ?>
          <tr><?php foreach ( $qr['cols'] as $c ) : ?><td title="<?php echo htmlspecialchars( mb_strimwidth( (string) ( $r[ $c ] ?? '' ), 0, 300 ) ); ?>"><?php echo htmlspecialchars( mb_strimwidth( (string) ( $r[ $c ] ?? '' ), 0, 60 ) ); ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </table>
        <p class="note"><?php echo count( $qr['rows'] ); ?> rows (had 200).</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
	<?php
	// db_result/db_result session se upar hi nikaal liye gaye thay.
	wdh_shell_close( false );
	exit;
}

// ---------- Mail catcher view (feature 6) ----------
if ( ( $_GET['view'] ?? '' ) === 'mail' ) {
	$mslug = $_GET['site'] ?? '';
	$S = hub_strings();
	$mdir2 = hub_sites_dir() . '/' . $mslug;
	$mmeta = hub_slug_ok( $mslug ) ? hub_site_meta( $mdir2 ) : null;
	$mails = $mmeta ? hub_mail_list( $mdir2 ) : [];
	$openId = $_GET['id'] ?? '';
	$open = null;
	foreach ( $mails as $m ) { if ( $m['id'] === $openId ) { $open = $m; break; } }
	wdh_shell_open( 'projects', 'Mail: ' . $mslug, [ 'rail' => false ] );
	?>
  <h1>TAMEERO — Mail: <?php echo htmlspecialchars( $mslug ); ?></h1>
  <p><a class="btn" href="/">← Dashboard par wapas</a>
  <?php if ( $mails ) : ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Saari pakri gayi mails delete kar dein?');">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="site" value="<?php echo htmlspecialchars( $mslug ); ?>">
      <button class="btn" name="action" value="mail_clear">Saari Mail Saaf Karein</button>
    </form>
  <?php endif; ?>
  </p>
  <p class="note"><?php echo htmlspecialchars( $S['mail_note'] ); ?></p>
  <?php if ( ! $mmeta ) : ?>
  <p><?php echo htmlspecialchars( $S['site_not_found'] ); ?></p>
  <?php elseif ( ! $mails ) : ?>
  <p><?php echo htmlspecialchars( $S['mail_none'] ); ?></p>
  <?php else : ?>
  <table>
    <tr><th><?php echo htmlspecialchars( $S['th_time'] ); ?></th><th><?php echo htmlspecialchars( $S['th_to'] ); ?></th><th><?php echo htmlspecialchars( $S['th_subject'] ); ?></th><th></th></tr>
    <?php foreach ( $mails as $m ) : ?>
    <tr>
      <td style="white-space:nowrap"><?php echo htmlspecialchars( $m['at'] ?? '' ); ?></td>
      <td><?php echo htmlspecialchars( is_array( $m['to'] ?? '' ) ? implode( ', ', $m['to'] ) : (string) ( $m['to'] ?? '' ) ); ?></td>
      <td><a href="/?view=mail&amp;site=<?php echo urlencode( $mslug ); ?>&amp;id=<?php echo urlencode( $m['id'] ); ?>"><?php echo htmlspecialchars( (string) ( $m['subject'] ?? $S['mail_no_subject'] ) ); ?></a></td>
      <td>
        <form method="post" style="margin:0">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $mslug ); ?>">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars( $m['id'] ); ?>">
          <button class="btn" name="action" value="mail_delete"><?php echo htmlspecialchars( $S['delete'] ); ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ( $open ) : $body = (string) ( $open['message'] ?? '' ); $isHtml = ( strpos( $body, '<' ) !== false && preg_match( '/<(html|body|div|p|table|h[1-6]|a)\b/i', $body ) ); ?>
  <h3 style="font-size:16px"><?php echo htmlspecialchars( (string) ( $open['subject'] ?? '' ) ); ?></h3>
  <p class="note"><?php echo htmlspecialchars( $S['mail_to'] ); ?>: <?php echo htmlspecialchars( is_array( $open['to'] ?? '' ) ? implode( ', ', $open['to'] ) : (string) ( $open['to'] ?? '' ) ); ?> · <?php echo htmlspecialchars( $open['at'] ?? '' ); ?></p>
  <div class="bodybox">
    <?php if ( $isHtml ) : ?>
    <iframe class="mailhtml" sandbox="" srcdoc="<?php echo htmlspecialchars( $body, ENT_QUOTES ); ?>"></iframe>
    <?php else : ?>
    <pre><?php echo htmlspecialchars( $body ); ?></pre>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
	<?php
	wdh_shell_close( false );
	exit;
}

// ---------- First-run wizard (feature 23) ----------
// Pehli dafa (koi project nahi + wizard nahi hua) dashboard ki
// jagah wizard khulta hai. Sites hon to kabhi nahi dikhta.
$wdh_settings = hub_settings();
if ( empty( $_GET['view'] ) && empty( $wdh_settings['wizard_done'] ) && ! hub_sites() ) {
	header( 'Location: /?view=wizard' );
	exit;
}
if ( ( $_GET['view'] ?? '' ) === 'wizard' ) {
	$S = hub_strings();
	$wstep = $_GET['step'] ?? '1';
	wdh_shell_open( '', $S['w_title'], [ 'rail' => false ] );
	?>
  <h1>TAMEERO</h1>
  <div class="card">
  <?php if ( $wstep === '1' ) : ?>
    <h2 style="margin-top:0"><?php echo htmlspecialchars( $S['w_title'] ); ?></h2>
    <p class="hint"><?php echo htmlspecialchars( $S['w_sub'] ); ?></p>
    <p class="steps"><?php echo htmlspecialchars( $S['w_step'] ); ?> 1 / 3</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <label><?php echo htmlspecialchars( $S['w_lang'] ); ?></label>
      <label class="radio"><input type="radio" name="lang" value="ur"<?php echo hub_lang() === 'ur' ? ' checked' : ''; ?>> Roman Urdu (اردو)</label>
      <label class="radio"><input type="radio" name="lang" value="en"<?php echo hub_lang() === 'en' ? ' checked' : ''; ?>> English</label>
      <label><?php echo htmlspecialchars( $S['w_lite_q'] ); ?></label>
      <p class="hint"><?php echo htmlspecialchars( $S['w_lite_hint'] ); ?></p>
      <label class="radio"><input type="radio" name="lite_on" value="1"> <?php echo htmlspecialchars( $S['w_yes'] ); ?></label>
      <label class="radio"><input type="radio" name="lite_on" value="0" checked> <?php echo htmlspecialchars( $S['w_no'] ); ?></label>
      <button class="btn" name="action" value="wizard_step1"><?php echo htmlspecialchars( $S['w_next'] ); ?></button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn ghost" name="action" value="wizard_skip"><?php echo htmlspecialchars( $S['w_skip'] ); ?></button>
    </form>
  <?php elseif ( $wstep === '2' ) : ?>
    <h2 style="margin-top:0"><?php echo htmlspecialchars( $S['w_p_title'] ); ?></h2>
    <p class="steps"><?php echo htmlspecialchars( $S['w_step'] ); ?> 2 / 3</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <label><?php echo htmlspecialchars( $S['w_p_name'] ); ?></label>
      <input type="text" name="name" required placeholder="meri-website">
      <label><?php echo htmlspecialchars( $S['blueprint'] ); ?></label>
      <select name="blueprint">
        <?php foreach ( hub_blueprints() as $bpId => $bpName ) : ?>
        <option value="<?php echo htmlspecialchars( $bpId ); ?>"><?php echo htmlspecialchars( $bpName ); ?></option>
        <?php endforeach; ?>
      </select>
      <?php $wThemeOpts = []; foreach ( hub_blueprints() as $tBpId => $tBpName ) { $tSlug = hub_blueprint_theme( $tBpId ); if ( $tSlug !== '' ) { $wThemeOpts[ $tSlug ] = true; } } ?>
      <?php if ( $wThemeOpts ) : ?>
      <label><?php echo htmlspecialchars( $S['np_theme'] ); ?></label>
      <select name="theme">
        <option value=""><?php echo htmlspecialchars( $S['np_theme_default'] ); ?></option>
        <?php foreach ( array_keys( $wThemeOpts ) as $tSlug ) : ?>
        <option value="<?php echo htmlspecialchars( $tSlug ); ?>"><?php echo htmlspecialchars( $tSlug === 'dukaan-fse' ? $S['np_theme_dukaan'] : ucfirst( $tSlug ) ); ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <label><?php echo htmlspecialchars( $S['w_p_pass'] ); ?></label>
      <input type="password" name="admin_pass" required minlength="6" autocomplete="new-password">
      <button class="btn" name="action" value="wizard_create"><?php echo htmlspecialchars( $S['w_create'] ); ?></button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn ghost" name="action" value="wizard_skip"><?php echo htmlspecialchars( $S['w_skip'] ); ?></button>
    </form>
  <?php else : ?>
    <h2 style="margin-top:0"><?php echo htmlspecialchars( $S['w_done_t'] ); ?></h2>
    <p><?php echo htmlspecialchars( $S['w_done'] ); ?></p>
    <p class="steps"><?php echo htmlspecialchars( $S['w_step'] ); ?> 3 / 3</p>
    <a href="/"><button class="btn"><?php echo htmlspecialchars( $S['w_go'] ); ?></button></a>
  <?php endif; ?>
  </div>
	<?php
	wdh_shell_close( false );
	exit;
}

// ---------- Settings page (v2.8: zuban, lite, chat, maloomat) ----------
if ( ( $_GET['view'] ?? '' ) === 'settings' ) {
	$S = hub_strings();
	$mset = hub_settings();
	require_once dirname( __DIR__ ) . '/lib/chat.php';
	$gw = hub_chat_gateway();
	wdh_shell_open( 'settings', $S['settings'] );
	?>
  <h1><span><?php echo htmlspecialchars( $S['settings'] ); ?></span></h1>
  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['w_lang'] ); ?></h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <label class="radio"><input type="radio" name="lang" value="ur"<?php echo hub_lang() === 'ur' ? ' checked' : ''; ?>> Roman Urdu (اردو)</label>
      <label class="radio"><input type="radio" name="lang" value="en"<?php echo hub_lang() === 'en' ? ' checked' : ''; ?>> English</label>
      <div class="btns"><button class="btn primary" name="action" value="set_lang"><?php echo htmlspecialchars( $S['w_next'] ); ?></button></div>
    </form>
  </div>
  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['set_lite_title'] ); ?></h2>
    <p class="meta"><?php echo htmlspecialchars( $S['set_lite_text'] ); ?></p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="lite_on" value="<?php echo hub_lite() ? '' : '1'; ?>">
      <div class="btns"><button class="btn" name="action" value="set_lite"><?php echo hub_lite() ? '✓ ' . htmlspecialchars( $S['lite_on'] ) : htmlspecialchars( $S['lite_off'] ); ?></button></div>
    </form>
  </div>
  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['set_chat_title'] ); ?></h2>
    <p class="meta"><?php echo $S['set_chat_text']; ?></p>
    <form method="post" id="chatSettingsForm">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <label><?php echo htmlspecialchars( $S['set_provider_label'] ); ?></label>
      <select name="chat_provider" id="chatProvider">
        <?php foreach ( hub_chat_providers() as $pid => $p ) : ?>
        <option value="<?php echo htmlspecialchars( $pid ); ?>" data-url="<?php echo htmlspecialchars( $p['url'] ); ?>"<?php echo $gw['provider'] === $pid ? ' selected' : ''; ?>><?php echo htmlspecialchars( $p['label'] ); ?></option>
        <?php endforeach; ?>
      </select>
      <label><?php echo htmlspecialchars( $S['set_gw_url'] ); ?></label>
      <input type="text" name="chat_gateway_url" id="chatGwUrl" value="<?php echo htmlspecialchars( $gw['url'] ); ?>" autocomplete="off" spellcheck="false">
      <label><?php echo htmlspecialchars( $S['set_gw_key_label'] ); ?></label>
      <input type="password" name="chat_gateway_key" id="chatGwKey" value="" placeholder="<?php echo trim( (string) ( $mset['chat_gateway_key'] ?? '' ) ) !== '' ? 'Key save hai - nayi key dalne par badle gi' : 'Apni API key yahan dalein'; ?>" autocomplete="off" spellcheck="false">
      <label><?php echo htmlspecialchars( $S['set_model_label'] ); ?></label>
      <input type="text" name="chat_model" id="chatModel" list="chatModelList" value="<?php echo htmlspecialchars( (string) ( $mset['chat_model'] ?? '' ) ); ?>" autocomplete="off" spellcheck="false">
      <datalist id="chatModelList"></datalist>
      <div class="btns"><button class="btn" type="button" id="chatModelsBtn"><?php echo htmlspecialchars( $S['set_models_btn'] ); ?></button><button class="btn" type="button" id="chatTestBtn"><?php echo htmlspecialchars( $S['set_test_btn'] ); ?></button><button class="btn primary" name="action" value="chat_settings"><?php echo htmlspecialchars( $S['set_chat_save'] ); ?></button></div>
      <p class="meta" id="chatConnResult"></p>
      <p class="meta"><?php echo htmlspecialchars( $S['set_brain_note'] ); ?></p>
    </form>
  </div>
  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['set_info_title'] ); ?></h2>
    <p class="meta"><?php echo sprintf( $S['set_info_text_fmt'], htmlspecialchars( hub_version() ), htmlspecialchars( hub_sites_dir() ) ); ?></p>
    <?php if ( file_exists( hub_ca_dir() . '/ca.crt' ) ) : ?>
    <p><a class="btn" href="/?ca=1"><?php echo htmlspecialchars( $S['ca_cert'] ); ?></a></p>
    <?php endif; ?>
  </div>
	<?php
	wdh_shell_close( true );
	exit;
}

$S = hub_strings();
$lite = hub_lite();
$sites = hub_sites();
$any_creating = false;
foreach ( $sites as $s ) { if ( ! empty( $s['creating'] ) ) { $any_creating = true; } }

// Sizes purane hon to background mein taaza karwao (feature 4) —
// page load kabhi size ginne par nahi rukta.
$need_sizes = false;
foreach ( $sites as $s ) {
	if ( ! empty( $s['creating'] ) || ! empty( $s['failed'] ) ) { continue; }
	if ( empty( $s['size_at'] ) || strtotime( $s['size_at'] ) < time() - 86400 ) { $need_sizes = true; }
}
if ( $need_sizes && ! $lite ) {
	// Lite mode: background kaam kam se kam — sizes khud nahi
	// bhadakte, card par purani value chalti rehti hai.
	hub_spawn_worker( 'sizes', [], hub_sites_dir() . '/.sizes.log' );
}

// Auto-backup (feature 20): jis site ka backup 7 din se purana ho,
// us ka backup background mein — ek waqt par sirf ek, taake
// dashboard khulna bojh na bane. Ye sirf tab chalta hai jab
// dashboard khula ho (koi alag service nahi hai).
foreach ( $sites as $s ) {
	if ( ! empty( $s['creating'] ) || ! empty( $s['failed'] ) ) { continue; }
	if ( hub_backup_due( $s ) ) {
		hub_spawn_worker( 'backup --site ' . hub_q( $s['site'] ), [], hub_sites_dir() . '/.' . $s['site'] . '.backup.log' );
		break;
	}
}
// Update khabar (feature 10): pehli stale site ka check background
// mein — ek page-load par sirf ek site, bhojh nahi parta.
foreach ( $sites as $s ) {
	if ( ! empty( $s['creating'] ) || ! empty( $s['failed'] ) ) { continue; }
	if ( hub_updates_stale( $s ) ) {
		hub_spawn_worker( 'updates --site ' . hub_q( $s['site'] ), [], hub_sites_dir() . '/.' . $s['site'] . '.updates.log' );
		break;
	}
}
$templates = hub_templates();
$stTotal = count( $sites ); $stRun = 0; $stSize = 0; $stUpd = 0;
foreach ( $sites as $ss ) {
	if ( ! empty( $ss['running'] ) ) { $stRun++; }
	$stSize += (int) ( $ss['size_bytes'] ?? 0 );
	if ( ! empty( $ss['upd_wp'] ) || ! empty( $ss['upd_plugins'] ) ) { $stUpd++; }
}
// ---------- Project Details (v2.10 Phase 3: tabs) ----------
if ( ( $_GET['view'] ?? '' ) === 'site' ) {
	$dslug = (string) ( $_GET['site'] ?? '' );
	$ds = null;
	foreach ( $sites as $cand ) {
		if ( ( $cand['site'] ?? '' ) === $dslug && isset( $cand['url'] ) ) { $ds = $cand; break; }
	}
	$tab = (string) ( $_GET['tab'] ?? 'overview' );
	if ( ! in_array( $tab, [ 'overview', 'plugins', 'updates', 'logs', 'settings' ], true ) ) { $tab = 'overview'; }
	wdh_shell_open( 'projects', $dslug !== '' ? $dslug : $S['details'], [ 'rail' => true ] );
	if ( ! $ds ) {
		?>
  <p><a href="/?view=projects"><?php echo htmlspecialchars( $S['det_back'] ); ?></a></p>
  <p><?php echo htmlspecialchars( $S['site_not_found'] ); ?></p>
		<?php
		wdh_shell_close( true );
		exit;
	}
	$updCount = ( ! empty( $ds['upd_wp'] ) ? 1 : 0 ) + ( ! empty( $ds['upd_plugins'] ) ? count( $ds['upd_plugins'] ) : 0 );
	$lsTs = ! empty( $ds['last_started'] ) ? strtotime( $ds['last_started'] ) : false;
	if ( $lsTs ) {
		$lsToday = date( 'Y-m-d', $lsTs ) === date( 'Y-m-d' );
		$statusLine = $ds['running']
			? sprintf( $S['status_running_fmt'], date( $lsToday ? 'h:i A' : 'd M, h:i A', $lsTs ) )
			: sprintf( $S['status_stopped_fmt'], date( 'd M, h:i A', $lsTs ) );
	} else {
		$statusLine = $ds['running'] ? $S['running'] : sprintf( $S['status_stopped_fmt'], $S['never'] );
	}
	$tabUrl = '/?view=site&site=' . urlencode( $dslug ) . '&tab=';
	?>
  <p style="margin-top:0"><a href="/?view=projects"><?php echo htmlspecialchars( $S['det_back'] ); ?></a></p>
  <div class="btns" style="align-items:center">
    <h1 style="margin:0"><?php echo htmlspecialchars( $dslug ); ?><?php if ( $updCount ) : ?> <span class="ubadge">🔄 <?php echo htmlspecialchars( sprintf( $S['upd_badge_fmt'], $updCount ) ); ?></span><?php endif; ?></h1>
  </div>
  <p class="meta"><span class="dot <?php echo $ds['running'] ? 'on' : 'off'; ?>"></span><?php echo htmlspecialchars( $statusLine ); ?> · <?php echo htmlspecialchars( $S['blueprint'] ); ?>: <?php echo htmlspecialchars( $ds['blueprint'] ?? '' ); ?></p>
  <div class="btns">
    <a class="btn primary" href="<?php echo htmlspecialchars( hub_admin_url( $ds ) ); ?>" target="_blank"><?php echo htmlspecialchars( $S['open_admin'] ); ?></a>
    <a class="btn" href="<?php echo htmlspecialchars( $ds['url'] ); ?>" target="_blank"><?php echo htmlspecialchars( $S['open_site'] ); ?></a>
    <form class="inline" method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
      <button class="btn" name="action" value="<?php echo $ds['running'] ? 'stop' : 'start'; ?>"><?php echo $ds['running'] ? htmlspecialchars( $S['stop'] ) : htmlspecialchars( $S['start'] ); ?></button>
    </form>
  </div>
  <div class="tabs">
    <a class="<?php echo $tab === 'overview' ? 'on' : ''; ?>" href="<?php echo $tabUrl; ?>overview"><?php echo htmlspecialchars( $S['tab_overview'] ); ?></a>
    <a class="<?php echo $tab === 'plugins' ? 'on' : ''; ?>" href="<?php echo $tabUrl; ?>plugins"><?php echo htmlspecialchars( $S['tab_plugins'] ); ?></a>
    <a class="<?php echo $tab === 'updates' ? 'on' : ''; ?>" href="<?php echo $tabUrl; ?>updates"><?php echo htmlspecialchars( $S['tab_updates'] ); ?><?php echo $updCount ? ' (' . $updCount . ')' : ''; ?></a>
    <a class="<?php echo $tab === 'logs' ? 'on' : ''; ?>" href="<?php echo $tabUrl; ?>logs"><?php echo htmlspecialchars( $S['tab_logs'] ); ?></a>
    <a class="<?php echo $tab === 'settings' ? 'on' : ''; ?>" href="<?php echo $tabUrl; ?>settings"><?php echo htmlspecialchars( $S['tab_settings'] ); ?></a>
  </div>
  <?php if ( $tab === 'overview' ) : ?>
    <?php if ( hub_thumb_file( $ds['dir'] ) ) : ?>
      <img class="thumb" style="height:160px" src="/?thumb=<?php echo urlencode( $dslug ); ?>" alt="">
    <?php endif; ?>
    <div class="panel">
      <p class="meta" style="margin-top:0">
        <a href="<?php echo htmlspecialchars( $ds['url'] ); ?>" target="_blank" style="color:#f0b64a"><?php echo htmlspecialchars( $ds['url'] ); ?></a>
        <?php if ( hub_https_running( $ds['dir'] ) && ! empty( $ds['https_port'] ) ) : ?>
        <br>🔒 <a href="https://127.0.0.1:<?php echo (int) $ds['https_port']; ?>" target="_blank" style="color:#39d353">https://127.0.0.1:<?php echo (int) $ds['https_port']; ?></a>
        <?php endif; ?>
        <?php if ( ! empty( $ds['preview_url'] ) && hub_preview_running( $ds['dir'] ) ) : ?>
        <br>🌍 <?php echo htmlspecialchars( $S['preview'] ); ?>: <a href="<?php echo htmlspecialchars( $ds['preview_url'] ); ?>" target="_blank" style="color:#39d353"><?php echo htmlspecialchars( $ds['preview_url'] ); ?></a> <span style="color:#9fb0c7"><?php echo htmlspecialchars( $S['preview_temp'] ); ?></span>
        <?php endif; ?><br>
        <?php echo htmlspecialchars( $S['size'] ); ?>: <?php echo ! empty( $ds['size_bytes'] ) ? hub_human_size( $ds['size_bytes'] ) : '—'; ?><br>
        <?php echo htmlspecialchars( $S['last_backup'] ); ?>: <?php echo ! empty( $ds['backup_at'] ) ? date( 'd M, h:i A', strtotime( $ds['backup_at'] ) ) : htmlspecialchars( $S['no_backup'] ); ?><br>
        <?php echo htmlspecialchars( $S['php_version'] ); ?>: <?php echo ! empty( $ds['php_line'] ) ? htmlspecialchars( $ds['php_line'] ) : htmlspecialchars( $S['php_default'] ); ?>
      </p>
      <div class="pathrow"><span class="path" title="<?php echo htmlspecialchars( $ds['dir'] ); ?>"><?php echo htmlspecialchars( $ds['dir'] ); ?></span><button type="button" class="copybtn" data-copy="<?php echo htmlspecialchars( $ds['dir'] ); ?>" title="<?php echo htmlspecialchars( $S['copy_path'] ); ?>">⧉</button></div>
      <div class="btns" style="margin-top:12px">
        <a class="btn" href="/?view=db&amp;site=<?php echo urlencode( $dslug ); ?>"><?php echo htmlspecialchars( $S['btn_database'] ); ?></a>
        <a class="btn" href="/?view=mail&amp;site=<?php echo urlencode( $dslug ); ?>"><?php echo htmlspecialchars( $S['btn_mail'] ); ?><?php $mc = hub_mail_count( $ds['dir'] ); echo $mc ? ' (' . $mc . ')' : ''; ?></a>
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
          <button class="btn" name="action" value="export"><?php echo htmlspecialchars( $S['export'] ); ?></button>
        </form>
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
          <button class="btn" name="action" value="backup"><?php echo htmlspecialchars( $S['backup'] ); ?></button>
        </form>
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
          <button class="btn" name="action" value="deploy" title="<?php echo htmlspecialchars( $S['deploy_title'] ); ?>"><?php echo htmlspecialchars( $S['deploy'] ); ?></button>
        </form>
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
          <button class="btn" name="action" value="thumb"><?php echo htmlspecialchars( $S['photo'] ); ?></button>
        </form>
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
          <button class="btn" name="action" value="https_toggle"><?php echo hub_https_running( $ds['dir'] ) ? htmlspecialchars( $S['https_stop'] ) : htmlspecialchars( $S['https_start'] ); ?></button>
        </form>
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
          <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
          <button class="btn" name="action" value="preview_toggle"><?php echo hub_preview_running( $ds['dir'] ) ? htmlspecialchars( $S['preview_stop'] ) : htmlspecialchars( $S['preview'] ); ?></button>
        </form>
      </div>
    </div>
  <?php elseif ( $tab === 'plugins' ) : ?>
    <?php wdh_plugins_table( $dslug, $csrf, '/?view=site&site=' . urlencode( $dslug ) . '&tab=plugins' ); ?>
  <?php elseif ( $tab === 'updates' ) : ?>
    <div class="panel">
      <?php if ( ! $updCount ) : ?>
      <p style="margin:0">✓ <?php echo htmlspecialchars( $S['upd_none'] ); ?></p>
      <?php else : ?>
      <?php if ( ! empty( $ds['upd_wp'] ) ) : ?><p style="margin-top:0">🔄 <?php echo htmlspecialchars( $S['upd_wp_avail'] ); ?></p><?php endif; ?>
      <?php if ( ! empty( $ds['upd_plugins'] ) ) : ?>
      <p><?php echo htmlspecialchars( $S['upd_plug_avail'] ); ?></p>
      <ul>
        <?php foreach ( $ds['upd_plugins'] as $up ) : ?><li><?php echo htmlspecialchars( (string) $up ); ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php endif; ?>
      <p class="meta"><?php echo htmlspecialchars( $S['upd_note'] ); ?></p>
      <div class="btns"><a class="btn" href="/?view=health"><?php echo htmlspecialchars( $S['nav_health'] ); ?></a></div>
    </div>
  <?php elseif ( $tab === 'logs' ) : ?>
    <div class="btns"><a class="btn" href="/?view=joblog&amp;site=<?php echo urlencode( $dslug ); ?>"><?php echo htmlspecialchars( $S['view_log'] ); ?></a></div>
    <pre><?php echo htmlspecialchars( hub_logs( $dslug, 100 ) ); ?></pre>
  <?php elseif ( $tab === 'settings' ) : ?>
    <div class="panel">
      <h2 style="margin-top:0"><?php echo htmlspecialchars( $S['clone_tpl'] ); ?></h2>
      <form method="post" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
        <label><?php echo htmlspecialchars( $S['clone_label'] ); ?></label>
        <input name="new_name" placeholder="meri-site-copy">
        <div class="btns"><button class="btn" name="action" value="clone"><?php echo htmlspecialchars( $S['clone_btn'] ); ?></button></div>
      </form>
      <form method="post" style="margin-top:6px">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
        <label><?php echo htmlspecialchars( $S['tpl_label'] ); ?></label>
        <input name="tpl_name" placeholder="meri-template">
        <div class="btns"><button class="btn" name="action" value="totemplate"><?php echo htmlspecialchars( $S['tpl_btn'] ); ?></button></div>
      </form>
    </div>
    <div class="panel">
      <h2 style="margin-top:0"><?php echo htmlspecialchars( $S['php_version'] ); ?><?php echo ! empty( $ds['php_line'] ) ? ': ' . htmlspecialchars( $ds['php_line'] ) : ' (' . htmlspecialchars( $S['php_default'] ) . ')'; ?></h2>
      <form method="post" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
        <label><?php echo htmlspecialchars( $S['php_label'] ); ?></label>
        <select name="php_line">
          <option value=""><?php echo htmlspecialchars( $S['php_default'] ); ?> (<?php $di = hub_php_installed(); echo htmlspecialchars( $di[0]['version'] ?? '8.4' ); ?>)</option>
          <?php foreach ( hub_php_installed() as $pv ) : if ( ! empty( $pv['bundled'] ) ) { continue; } ?>
          <option value="<?php echo htmlspecialchars( $pv['line'] ); ?>"<?php echo ( $ds['php_line'] ?? '' ) === $pv['line'] ? ' selected' : ''; ?>>PHP <?php echo htmlspecialchars( $pv['line'] ); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="btns"><button class="btn" name="action" value="set_php"><?php echo htmlspecialchars( $S['php_btn'] ); ?></button></div>
        <p style="color:#9fb0c7;font-size:12px;margin:6px 0 0"><?php echo htmlspecialchars( $S['php_note'] ); ?></p>
      </form>
    </div>
    <div class="panel">
      <h2 style="margin-top:0"><?php echo htmlspecialchars( $S['reset'] ); ?></h2>
      <form method="post" onsubmit="return confirm('<?php echo htmlspecialchars( $S['reset_confirm'] ); ?>');" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="site" value="<?php echo htmlspecialchars( $dslug ); ?>">
        <label><?php echo htmlspecialchars( $S['reset_label'] ); ?></label>
        <input type="password" name="new_pass" required minlength="6" autocomplete="new-password">
        <div class="btns"><button class="btn" name="action" value="resetpass"><?php echo htmlspecialchars( $S['reset_btn'] ); ?></button></div>
      </form>
    </div>
  <?php endif; ?>
  <script>
  document.querySelectorAll('.copybtn').forEach(function(b){ b.addEventListener('click', function(){ var t=b.getAttribute('data-copy')||''; function done(){ var o=b.textContent; b.textContent='✓'; setTimeout(function(){ b.textContent=o; },1200); } if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).then(done, done); } else { var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch(e){} ta.remove(); done(); } }); });
  </script>
	<?php
	wdh_shell_close( true );
	exit;
}
// ---------- Themes (v2.10 Phase 3: gallery — asal previews) ----------
if ( ( $_GET['view'] ?? '' ) === 'themes' ) {
	wdh_shell_open( 'themes', $S['themes_title'], [ 'rail' => true ] );
	// Kis theme par kon se projects — asal active theme har site
	// ki DB se parha jata hai (meta mein theme record nahi hota).
	$inUse = [];
	foreach ( $sites as $sx ) {
		if ( ! isset( $sx['url'] ) ) { continue; }
		$at = hub_site_active_theme( $sx['dir'] );
		if ( $at !== '' ) { $inUse[ $at ][] = $sx['site']; }
	}
	$dukaanSlug = hub_blueprint_theme( 'store' );
	if ( $dukaanSlug === '' ) { $dukaanSlug = 'dukaan-fse'; }
	$themeCards = [
		[ 'slug' => $dukaanSlug, 'name' => $S['np_theme_dukaan'], 'img' => '/assets/theme-dukaan.jpg', 'desc' => $S['theme_dukaan_desc'], 'use' => '/?theme=' . urlencode( $dukaanSlug ) . '#new' ],
		[ 'slug' => 'twentytwentyfive', 'name' => $S['np_theme_default'], 'img' => '/assets/theme-default.jpg', 'desc' => $S['theme_default_desc'], 'use' => '/#new' ],
	];
	?>
  <h1><?php echo htmlspecialchars( $S['themes_title'] ); ?></h1>
  <div class="tgrid">
    <?php foreach ( $themeCards as $tc ) : ?>
    <div class="tcard">
      <img class="timg" src="<?php echo htmlspecialchars( $tc['img'] ); ?>" alt="">
      <h3><?php echo htmlspecialchars( $tc['name'] ); ?></h3>
      <p class="meta"><?php echo htmlspecialchars( $tc['desc'] ); ?></p>
      <?php if ( ! empty( $inUse[ $tc['slug'] ] ) ) : ?>
      <p class="meta">✓ <?php echo htmlspecialchars( $S['theme_in_use'] ); ?> <strong><?php echo htmlspecialchars( implode( ', ', $inUse[ $tc['slug'] ] ) ); ?></strong></p>
      <?php else : ?>
      <p class="meta"><?php echo htmlspecialchars( $S['theme_none_use'] ); ?></p>
      <?php endif; ?>
      <div class="btns">
        <a class="btn" href="<?php echo htmlspecialchars( $tc['img'] ); ?>" target="_blank"><?php echo htmlspecialchars( $S['theme_preview'] ); ?></a>
        <a class="btn primary" href="<?php echo htmlspecialchars( $tc['use'] ); ?>"><?php echo htmlspecialchars( $S['theme_use'] ); ?></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="meta"><?php echo htmlspecialchars( $S['theme_swap_soon'] ); ?></p>
  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['themes_coming'] ); ?></h2>
    <p class="meta" style="margin:0"><?php echo htmlspecialchars( $S['themes_soon'] ); ?></p>
  </div>
	<?php
	wdh_shell_close( true );
	exit;
}

// ---------- Projects (v2.10 Phase 2: cards waisay ke waisay, apni jagah) ----------
if ( ( $_GET['view'] ?? '' ) === 'projects' ) {
	wdh_shell_open( 'projects', $S['projects_title'], [ 'rail' => true, 'search' => true, 'refresh' => $any_creating ? ( $lite ? 30 : 6 ) : 0 ] );
	?>
  <h1><?php echo htmlspecialchars( $S['projects_title'] ); ?> <span class="meta"><?php echo htmlspecialchars( sprintf( $S['projects_count_fmt'], $stTotal ) ); ?></span></h1>
  <div class="toolbar">
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn" name="action" value="start_all"><?php echo htmlspecialchars( $S['start_all'] ); ?></button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn" name="action" value="stop_all"><?php echo htmlspecialchars( $S['stop_all'] ); ?></button>
    </form>
    <?php if ( file_exists( hub_ca_dir() . '/ca.crt' ) ) : ?>
    <a class="btn" href="/?ca=1" title="Trust this in your browser/OS to remove the local HTTPS warning"><?php echo htmlspecialchars( $S['ca_cert'] ); ?></a>
    <?php endif; ?>
  </div>

  <?php if ( $sites ) : ?>
  <?php $nRun = 0; $nStop = 0; $nUpd = 0; foreach ( $sites as $sx ) { if ( ! empty( $sx['creating'] ) || ! empty( $sx['failed'] ) ) { continue; } if ( ! empty( $sx['running'] ) ) { $nRun++; } else { $nStop++; } if ( ! empty( $sx['upd_wp'] ) || ! empty( $sx['upd_plugins'] ) ) { $nUpd++; } } ?>
  <div class="filters">
    <button type="button" class="fchip on" data-f="all"><?php echo htmlspecialchars( $S['f_all'] ); ?> (<?php echo $stTotal; ?>)</button>
    <button type="button" class="fchip" data-f="running"><?php echo htmlspecialchars( $S['f_running'] ); ?> (<?php echo $nRun; ?>)</button>
    <button type="button" class="fchip" data-f="stopped"><?php echo htmlspecialchars( $S['f_stopped'] ); ?> (<?php echo $nStop; ?>)</button>
    <button type="button" class="fchip" data-f="updates"><?php echo htmlspecialchars( $S['f_updates'] ); ?> (<?php echo $nUpd; ?>)</button>
  </div>
  <?php endif; ?>
  <?php if ( ! $sites ) : ?>
    <div class="empty"><?php echo htmlspecialchars( $S['empty'] ); ?></div>
  <?php else : ?>
  <div class="grid">
    <?php foreach ( $sites as $s ) : ?>
    <div class="card" data-name="<?php echo htmlspecialchars( strtolower( $s['site'] . ' ' . ( $s['blueprint'] ?? '' ) ) ); ?>" data-status="<?php echo ! empty( $s['creating'] ) ? 'creating' : ( ! empty( $s['failed'] ) ? 'failed' : ( $s['running'] ? 'running' : 'stopped' ) ); ?>" data-updates="<?php echo ( ! empty( $s['upd_wp'] ) || ! empty( $s['upd_plugins'] ) ) ? '1' : '0'; ?>">
      <?php if ( ! empty( $s['creating'] ) ) : ?>
        <?php $prog = hub_create_progress( $s['site'] ); ?>
        <?php if ( hub_thumb_file( $s['dir'] ) ) : ?>
          <img class="thumb" src="/?thumb=<?php echo urlencode( $s['site'] ); ?>" alt="">
        <?php else : ?>
          <div class="tile">…</div>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars( $s['site'] ); ?></h3>
        <p class="meta"><span class="dot busy"></span><?php echo htmlspecialchars( $S['creating'] ); ?></p>
        <?php if ( $prog ) : ?>
        <p class="meta" style="margin-top:2px"><?php echo htmlspecialchars( $S['step_word'] ); ?> <?php echo (int) $prog['step']; ?>/<?php echo (int) $prog['total']; ?> — <?php echo htmlspecialchars( $prog['label'] ); ?><?php echo $prog['done'] ? ' · ✓ ' . (int) $prog['done'] : ''; ?></p>
        <div style="background:#26314a;border-radius:6px;height:8px;overflow:hidden;margin:6px 0 10px"><div style="background:#f0b64a;height:100%;width:<?php echo $prog['total'] ? (int) round( $prog['done'] / $prog['total'] * 100 ) : 0; ?>%"></div></div>
        <?php endif; ?>
        <div class="btns">
          <a class="btn" href="/?view=joblog&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['view_log'] ); ?></a>
          <form class="inline" method="post" onsubmit="return confirm('<?php echo htmlspecialchars( $S['delete_confirm'] ); ?>');">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn danger" name="action" value="delete"><?php echo htmlspecialchars( $S['delete'] ); ?></button>
          </form>
        </div>
      <?php elseif ( ! empty( $s['failed'] ) ) : ?>
        <div class="tile" style="background:#5c2323">✕</div>
        <h3><?php echo htmlspecialchars( $s['site'] ); ?></h3>
        <p class="meta"><span class="dot" style="background:#e5534b"></span><b><?php echo htmlspecialchars( $S['failed'] ); ?></b><?php if ( ! empty( $s['fail_label'] ) ) : ?> — <?php echo htmlspecialchars( $s['fail_label'] ); ?><?php endif; ?></p>
        <?php if ( ! empty( $s['fail_error'] ) ) : ?>
        <p class="meta" style="color:#f0a8a2"><?php echo htmlspecialchars( $s['fail_error'] ); ?></p>
        <?php endif; ?>
        <div class="btns">
          <a class="btn" href="/?view=joblog&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['view_log'] ); ?></a>
          <form class="inline" method="post" onsubmit="return confirm('<?php echo htmlspecialchars( $S['delete_confirm'] ); ?>');">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn danger" name="action" value="delete"><?php echo htmlspecialchars( $S['delete'] ); ?></button>
          </form>
        </div>
      <?php else : ?>
        <?php $updCount = ( ! empty( $s['upd_wp'] ) ? 1 : 0 ) + ( ! empty( $s['upd_plugins'] ) ? count( $s['upd_plugins'] ) : 0 ); ?>
        <?php if ( hub_thumb_file( $s['dir'] ) ) : ?>
          <img class="thumb" src="/?thumb=<?php echo urlencode( $s['site'] ); ?>" alt="">
        <?php else : ?>
          <div class="tile"><?php echo strtoupper( substr( $s['site'], 0, 1 ) ); ?></div>
        <?php endif; ?>
        <h3><a href="/?view=site&amp;site=<?php echo urlencode( $s['site'] ); ?>" style="color:inherit"><?php echo htmlspecialchars( $s['site'] ); ?></a><?php if ( $updCount ) : ?> <span class="ubadge">🔄 <?php echo htmlspecialchars( sprintf( $S['upd_badge_fmt'], $updCount ) ); ?></span><?php endif; ?></h3>
        <?php
        // UI polish #7: status line ka saaf matlab — chalti site
        // "Chal raha hai · {waqt} se", band site "Band hai · aakhri
        // dafa {tarikh}". last_started aaj ka ho to sirf waqt.
        $lsTs = ! empty( $s['last_started'] ) ? strtotime( $s['last_started'] ) : false;
        if ( $lsTs ) {
          $lsToday = date( 'Y-m-d', $lsTs ) === date( 'Y-m-d' );
          if ( $s['running'] ) {
            $statusLine = sprintf( $S['status_running_fmt'], date( $lsToday ? 'h:i A' : 'd M, h:i A', $lsTs ) );
          } else {
            $statusLine = sprintf( $S['status_stopped_fmt'], date( 'd M, h:i A', $lsTs ) );
          }
        } else {
          $statusLine = $s['running'] ? $S['running'] : sprintf( $S['status_stopped_fmt'], $S['never'] );
        }
        ?>
        <p class="meta">
          <span class="dot <?php echo $s['running'] ? 'on' : 'off'; ?>"></span><?php echo htmlspecialchars( $statusLine ); ?>
          · <?php echo htmlspecialchars( $S['blueprint'] ); ?>: <?php echo htmlspecialchars( $s['blueprint'] ?? '' ); ?><br>
          <a href="<?php echo htmlspecialchars( $s['url'] ); ?>" target="_blank" style="color:#f0b64a"><?php echo htmlspecialchars( $s['url'] ); ?></a>
          <?php if ( hub_https_running( $s['dir'] ) && ! empty( $s['https_port'] ) ) : ?>
          <br>🔒 <a href="https://127.0.0.1:<?php echo (int) $s['https_port']; ?>" target="_blank" style="color:#39d353">https://127.0.0.1:<?php echo (int) $s['https_port']; ?></a>
          <?php endif; ?>
          <?php if ( ! empty( $s['preview_url'] ) && hub_preview_running( $s['dir'] ) ) : ?>
          <br>🌍 <?php echo htmlspecialchars( $S['preview'] ); ?>: <a href="<?php echo htmlspecialchars( $s['preview_url'] ); ?>" target="_blank" style="color:#39d353"><?php echo htmlspecialchars( $s['preview_url'] ); ?></a> <span style="color:#9fb0c7"><?php echo htmlspecialchars( $S['preview_temp'] ); ?></span>
          <?php endif; ?><br>
          <?php echo htmlspecialchars( $S['size'] ); ?>: <?php echo ! empty( $s['size_bytes'] ) ? hub_human_size( $s['size_bytes'] ) : '—'; ?><br>
          <?php echo htmlspecialchars( $S['last_backup'] ); ?>: <?php echo ! empty( $s['backup_at'] ) ? date( 'd M, h:i A', strtotime( $s['backup_at'] ) ) : htmlspecialchars( $S['no_backup'] ); ?>
        </p>
        <div class="pathrow"><span class="path" title="<?php echo htmlspecialchars( $s['dir'] ); ?>"><?php echo htmlspecialchars( $s['dir'] ); ?></span><button type="button" class="copybtn" data-copy="<?php echo htmlspecialchars( $s['dir'] ); ?>" title="<?php echo htmlspecialchars( $S['copy_path'] ); ?>">⧉</button></div>
        <div class="btns">
          <a class="btn primary" href="<?php echo htmlspecialchars( $s['url'] ); ?>" target="_blank"><?php echo htmlspecialchars( $S['open_site'] ); ?></a>
          <a class="btn" href="<?php echo htmlspecialchars( hub_admin_url( $s ) ); ?>" target="_blank"><?php echo htmlspecialchars( $S['open_admin'] ); ?></a>
          <form class="inline" method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn" name="action" value="<?php echo $s['running'] ? 'stop' : 'start'; ?>"><?php echo $s['running'] ? htmlspecialchars( $S['stop'] ) : htmlspecialchars( $S['start'] ); ?></button>
          </form>
          <details class="moremenu">
            <summary class="btn"><?php echo htmlspecialchars( $S['more'] ); ?> ▾</summary>
            <div class="menupanel">
              <div class="menucat"><?php echo htmlspecialchars( $S['cat_open'] ); ?></div>
              <a class="mitem" href="/?view=site&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['details'] ); ?></a>
              <a class="mitem" href="/?view=plugins&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['btn_plugins'] ); ?></a>
              <a class="mitem" href="/?view=db&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['btn_database'] ); ?></a>
              <a class="mitem" href="/?view=mail&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['btn_mail'] ); ?><?php $mc = hub_mail_count( $s['dir'] ); echo $mc ? ' (' . $mc . ')' : ''; ?></a>
              <div class="menucat"><?php echo htmlspecialchars( $S['cat_data'] ); ?></div>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem" name="action" value="export"><?php echo htmlspecialchars( $S['export'] ); ?></button>
              </form>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem" name="action" value="backup"><?php echo htmlspecialchars( $S['backup'] ); ?></button>
              </form>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem" name="action" value="deploy" title="<?php echo htmlspecialchars( $S['deploy_title'] ); ?>"><?php echo htmlspecialchars( $S['deploy'] ); ?></button>
              </form>
              <div class="menucat"><?php echo htmlspecialchars( $S['cat_tools'] ); ?></div>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem" name="action" value="thumb"><?php echo htmlspecialchars( $S['photo'] ); ?></button>
              </form>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem" name="action" value="https_toggle"><?php echo hub_https_running( $s['dir'] ) ? htmlspecialchars( $S['https_stop'] ) : htmlspecialchars( $S['https_start'] ); ?></button>
              </form>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem" name="action" value="preview_toggle"><?php echo hub_preview_running( $s['dir'] ) ? htmlspecialchars( $S['preview_stop'] ) : htmlspecialchars( $S['preview'] ); ?></button>
              </form>
              <a class="mitem" href="/?view=joblog&amp;site=<?php echo urlencode( $s['site'] ); ?>"><?php echo htmlspecialchars( $S['view_log'] ); ?></a>
              <div class="menucat danger"><?php echo htmlspecialchars( $S['cat_danger'] ); ?></div>
              <form method="post" onsubmit="return confirm('<?php echo htmlspecialchars( $S['delete_confirm'] ); ?>');">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
                <button class="mitem danger" name="action" value="delete"><?php echo htmlspecialchars( $S['delete'] ); ?></button>
              </form>
            </div>
          </details>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ( $templates ) : ?>
  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['my_templates'] ); ?></h2>
    <?php foreach ( $templates as $t ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #26314a">
      <div>
        <strong><?php echo htmlspecialchars( $t['name'] ); ?></strong>
        <span class="meta"><?php echo htmlspecialchars( sprintf( $S['tpl_meta_fmt'], (string) ( $t['from_site'] ?? '' ), ! empty( $t['created'] ) ? date( 'd M Y', strtotime( $t['created'] ) ) : '' ) ); ?></span>
        <div class="meta"><?php echo htmlspecialchars( $S['tpl_desc'] ); ?></div>
      </div>
      <form method="post" onsubmit="return confirm('<?php echo htmlspecialchars( $S['tpl_delete_confirm'] ); ?>');">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="tpl" value="<?php echo htmlspecialchars( $t['name'] ); ?>">
        <button class="btn danger" name="action" value="tpl_delete"><?php echo htmlspecialchars( $S['delete'] ); ?></button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['import'] ); ?></h2>
    <p class="meta" style="margin-top:0"><?php echo htmlspecialchars( $S['import_note'] ); ?></p>
    <form method="post" enctype="multipart/form-data" id="importForm" data-upload-base="<?php echo htmlspecialchars( hub_upload_base() ); ?>">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action" value="import">
      <div class="row">
        <div>
          <label><?php echo htmlspecialchars( $S['np_name'] ); ?></label>
          <input name="name" required placeholder="purani-site">
        </div>
        <div>
          <label><?php echo htmlspecialchars( $S['import_label'] ); ?></label>
          <input type="file" name="zipfile" id="importFile" accept=".zip" required>
        </div>
      </div>
      <div id="importProgress" style="display:none;margin-top:10px">
        <div style="height:8px;background:#0d1523;border-radius:9px;overflow:hidden"><i id="importBar" style="display:block;height:100%;width:0%;background:#C9A227"></i></div>
        <div class="meta" id="importProgressText" style="margin-top:5px"><?php echo htmlspecialchars( $S['imp_uploading'] ); ?></div>
      </div>
      <div class="btns"><button class="btn primary" type="submit"><?php echo htmlspecialchars( $S['import_btn'] ); ?></button></div>
    </form>
  </div>

  <script>
  document.querySelectorAll('.copybtn').forEach(function(b){ b.addEventListener('click', function(){ var t=b.getAttribute('data-copy')||''; function done(){ var o=b.textContent; b.textContent='\u2713'; setTimeout(function(){ b.textContent=o; },1200); } if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).then(done, done); } else { var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch(e){} ta.remove(); done(); } }); });
  document.querySelectorAll('.fchip').forEach(function(ch){ ch.addEventListener('click', function(){ document.querySelectorAll('.fchip').forEach(function(c){ c.classList.remove('on'); }); ch.classList.add('on'); var f=ch.getAttribute('data-f'); document.querySelectorAll('.grid .card').forEach(function(card){ var show = f==='all' || (f==='updates' ? card.getAttribute('data-updates')==='1' : card.getAttribute('data-status')===f); card.classList.toggle('fhide', !show); }); }); });
  </script>
  <p class="meta" style="margin-top:16px">TAMEERO v<?php echo htmlspecialchars( hub_version() ); ?> · <?php echo htmlspecialchars( $S['footer_note'] ); ?></p>
	<?php
	wdh_shell_close( true );

	exit;
}

// ---------- Home (v2.10 Phase 2: setup card upar, stats, recent) ----------
wdh_shell_open( 'home', 'Dashboard', [ 'rail' => true, 'refresh' => $any_creating ? ( $lite ? 30 : 6 ) : 0 ] );
?>
  <h1><?php echo htmlspecialchars( $S['home_welcome'] ); ?></h1>

  <div class="panel" id="new" style="margin-top:0">
    <h2><?php echo htmlspecialchars( $S['new_project'] ); ?></h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action" value="create">
      <div class="row">
        <div>
          <label><?php echo htmlspecialchars( $S['np_name'] ); ?></label>
          <input name="name" required placeholder="meri-dukan">
        </div>
        <div>
          <label><?php echo htmlspecialchars( $S['blueprint'] ); ?></label>
          <select name="blueprint">
            <?php foreach ( hub_blueprints() as $bpId => $bpName ) : ?>
            <option value="<?php echo htmlspecialchars( $bpId ); ?>"<?php echo $bpId === 'store' ? ' data-note="woo"' : ''; ?><?php echo ( (string) ( $_GET['theme'] ?? '' ) !== '' && hub_blueprint_theme( $bpId ) === (string) $_GET['theme'] ) ? ' selected' : ''; ?>><?php echo htmlspecialchars( $bpName ); ?><?php echo $bpId === 'store' ? ' (WooCommerce + PKR)' : ''; ?></option>
            <?php endforeach; ?>
            <option value="blank">Blank WordPress</option>
            <?php if ( $templates ) : ?>
            <optgroup label="Mere Templates">
              <?php foreach ( $templates as $t ) : ?>
              <option value="tpl:<?php echo htmlspecialchars( $t['name'] ); ?>">Template: <?php echo htmlspecialchars( $t['name'] ); ?> (<?php echo htmlspecialchars( $t['from_site'] ?? '' ); ?> se bani)</option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>
      </div>
      <?php $themeOpts = []; foreach ( hub_blueprints() as $tBpId => $tBpName ) { $tSlug = hub_blueprint_theme( $tBpId ); if ( $tSlug !== '' ) { $themeOpts[ $tSlug ] = true; } } ?>
      <div class="row">
        <?php if ( $themeOpts ) : ?>
        <div>
          <label><?php echo htmlspecialchars( $S['np_theme'] ); ?></label>
          <select name="theme">
            <option value=""><?php echo htmlspecialchars( $S['np_theme_default'] ); ?></option>
            <?php foreach ( array_keys( $themeOpts ) as $tSlug ) : ?>
            <option value="<?php echo htmlspecialchars( $tSlug ); ?>"<?php echo (string) ( $_GET['theme'] ?? '' ) === $tSlug ? ' selected' : ''; ?>><?php echo htmlspecialchars( $tSlug === 'dukaan-fse' ? $S['np_theme_dukaan'] : ucfirst( $tSlug ) ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label><?php echo htmlspecialchars( $S['np_admin_pass'] ); ?></label>
          <input type="password" name="admin_pass" minlength="6">
        </div>
      </div>
      <details>
        <summary style="cursor:pointer;font-size:13px;color:#9fb0c7"><?php echo htmlspecialchars( $S['np_more'] ); ?></summary>
        <div class="row" style="margin-top:8px">
          <div><label><?php echo htmlspecialchars( $S['np_admin_user'] ); ?></label><input name="admin_user" value="admin"></div>
          <div><label><?php echo htmlspecialchars( $S['np_admin_email'] ); ?></label><input name="admin_email" value="admin@example.local"></div>
        </div>
      </details>
      <label style="display:flex;gap:8px;align-items:center;margin-top:14px;color:#e8ecf3;font-size:14px">
        <input type="checkbox" name="demo_content" value="1" style="width:auto">
        <?php echo htmlspecialchars( $S['np_demo'] ); ?>
      </label>
      <div class="btns"><button class="btn primary" type="submit"><?php echo htmlspecialchars( $S['np_create'] ); ?></button></div>
    </form>
  </div>

  <div class="stats" style="margin-top:16px">
    <div class="stat"><div class="n"><?php echo $stTotal; ?></div><div class="l"><?php echo htmlspecialchars( $S['stat_projects'] ); ?></div></div>
    <div class="stat"><div class="n" style="color:#4ade80"><?php echo $stRun; ?></div><div class="l"><?php echo htmlspecialchars( $S['stat_running'] ); ?></div></div>
    <div class="stat"><div class="n" style="color:#C9A227"><?php echo $stUpd; ?></div><div class="l"><?php echo htmlspecialchars( $S['stat_updates'] ); ?></div></div>
  </div>

  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['recent_projects'] ); ?> <a style="float:right;font-size:13px" href="/?view=projects"><?php echo htmlspecialchars( $S['view_all_projects'] ); ?></a></h2>
    <?php if ( ! $sites ) : ?>
    <p class="meta"><?php echo htmlspecialchars( $S['empty'] ); ?></p>
    <?php else : ?>
    <?php
    $recentSites = $sites;
    usort( $recentSites, function ( $a, $b ) {
      $ca = ! empty( $a['creating'] ) || ! empty( $a['failed'] ) ? 1 : 0;
      $cb = ! empty( $b['creating'] ) || ! empty( $b['failed'] ) ? 1 : 0;
      if ( $ca !== $cb ) { return $cb - $ca; }
      $ta = max( (int) ( ! empty( $a['last_started'] ) ? strtotime( (string) $a['last_started'] ) : 0 ), (int) ( ! empty( $a['backup_at'] ) ? strtotime( (string) $a['backup_at'] ) : 0 ) );
      $tb = max( (int) ( ! empty( $b['last_started'] ) ? strtotime( (string) $b['last_started'] ) : 0 ), (int) ( ! empty( $b['backup_at'] ) ? strtotime( (string) $b['backup_at'] ) : 0 ) );
      if ( $ta !== $tb ) { return $tb - $ta; }
      return strcmp( (string) $a['site'], (string) $b['site'] );
    } );
    $recentSites = array_slice( $recentSites, 0, 3 );
    ?>
    <?php foreach ( $recentSites as $s ) : ?>
    <div class="rrow">
      <?php if ( ! empty( $s['creating'] ) ) : ?>
      <span class="dot busy"></span><span class="nm"><?php echo htmlspecialchars( $s['site'] ); ?></span><span class="st"><?php echo htmlspecialchars( $S['creating'] ); ?></span>
      <a class="btn" href="/?view=projects"><?php echo htmlspecialchars( $S['nav_projects'] ); ?></a>
      <?php elseif ( ! empty( $s['failed'] ) ) : ?>
      <span class="dot" style="background:#e5534b"></span><span class="nm"><?php echo htmlspecialchars( $s['site'] ); ?></span><span class="st"><?php echo htmlspecialchars( $S['failed'] ); ?></span>
      <a class="btn" href="/?view=projects"><?php echo htmlspecialchars( $S['nav_projects'] ); ?></a>
      <?php else : ?>
      <span class="dot <?php echo $s['running'] ? 'on' : 'off'; ?>"></span><span class="nm"><?php echo htmlspecialchars( $s['site'] ); ?></span><span class="st"><?php echo htmlspecialchars( $s['running'] ? $S['st_running_short'] : $S['st_stopped_short'] ); ?></span>
      <a class="btn primary" href="<?php echo htmlspecialchars( $s['url'] ); ?>" target="_blank"><?php echo htmlspecialchars( $S['open_site'] ); ?></a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2><?php echo htmlspecialchars( $S['recent_activity'] ); ?></h2>
    <?php
    $actItems = [];
    foreach ( hub_mcp_audit_tail( 10 ) as $e ) {
      $txt = trim( (string) ( $e['summary'] ?? '' ) ) !== '' ? (string) $e['summary'] : (string) ( $e['tool'] ?? '' );
      if ( $txt === '' ) { continue; }
      $actItems[] = [ 'ts' => (int) ( ! empty( $e['at'] ) ? strtotime( (string) $e['at'] ) : 0 ), 'text' => $txt ];
    }
    foreach ( hub_mcp_actions_list( 20 ) as $r ) {
      if ( ( $r['status'] ?? '' ) === 'pending' ) { continue; }
      $when = (string) ( $r['finished_at'] ?? $r['decided_at'] ?? $r['created_at'] ?? '' );
      $txt = trim( (string) ( $r['summary'] ?? '' ) );
      if ( $txt === '' || $when === '' ) { continue; }
      $actItems[] = [ 'ts' => (int) strtotime( $when ), 'text' => $txt ];
    }
    foreach ( $sites as $s ) {
      if ( ! empty( $s['backup_at'] ) ) { $actItems[] = [ 'ts' => (int) strtotime( (string) $s['backup_at'] ), 'text' => sprintf( $S['act_backup_fmt'], $s['site'] ) ]; }
      if ( ! empty( $s['last_started'] ) ) { $actItems[] = [ 'ts' => (int) strtotime( (string) $s['last_started'] ), 'text' => sprintf( $S['act_started_fmt'], $s['site'] ) ]; }
    }
    usort( $actItems, function ( $a, $b ) { return $b['ts'] - $a['ts']; } );
    $actItems = array_slice( $actItems, 0, 5 );
    ?>
    <?php if ( ! $actItems ) : ?>
    <p class="meta"><?php echo htmlspecialchars( $S['activity_none'] ); ?></p>
    <?php else : ?>
    <?php foreach ( $actItems as $it ) : ?>
    <div class="actrow"><span class="t"><?php echo $it['ts'] ? htmlspecialchars( date( 'd M, h:i A', $it['ts'] ) ) : ''; ?></span><span><?php echo htmlspecialchars( $it['text'] ); ?></span></div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <p class="meta" style="margin-top:16px">TAMEERO v<?php echo htmlspecialchars( hub_version() ); ?> · <?php echo htmlspecialchars( $S['footer_note'] ); ?></p>
	<?php
	wdh_shell_close( true );