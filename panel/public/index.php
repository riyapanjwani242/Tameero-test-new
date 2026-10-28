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

if ( empty( $_SESSION['csrf'] ) ) {
	$_SESSION['csrf'] = bin2hex( random_bytes( 16 ) );
}
$csrf = $_SESSION['csrf'];
$flash = $_SESSION['flash'] ?? '';
unset( $_SESSION['flash'] );

function hub_back( $msg ) {
	// Actions ke dauraan session band rehta hai (neeche dekho) —
	// flash likhne ke liye zaroorat par dobara kholo.
	if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
	$_SESSION['flash'] = $msg;
	header( 'Location: /' );
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
		if ( ! in_array( $bpRaw, [ 'business', 'store', 'blank' ], true ) ) { hub_back( 'Blueprint ghalat hai.' ); }
		if ( strlen( $pass ) < 6 ) { hub_back( 'Admin password kam az kam 6 characters rakhein.' ); }
		hub_create_async( $name, $bpRaw, $user, $email, $pass, '', $demo );
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

	// Zip import (feature 13)
	if ( $action === 'import' ) {
		$name = strtolower( trim( $_POST['name'] ?? '' ) );
		$name = trim( preg_replace( '/[^a-z0-9\-]+/', '-', $name ), '-' );
		if ( ! hub_slug_ok( $name ) ) { hub_back( 'Project ka naam sirf a-z, 0-9 aur - mein rakhein.' ); }
		if ( hub_site_meta( hub_sites_dir() . '/' . $name ) ) { hub_back( 'Is naam ka project pehle se mojood hai.' ); }
		$f = $_FILES['zipfile'] ?? null;
		if ( ! $f || $f['error'] !== UPLOAD_ERR_OK ) {
			hub_back( 'Zip upload nahi ho saki (file bohat bari hai ya koi aur masla — error ' . ( $f['error'] ?? '?' ) . ').' );
		}
		$importDir = hub_sites_dir() . '/.imports';
		if ( ! is_dir( $importDir ) ) { mkdir( $importDir, 0775, true ); }
		$zipPath = $importDir . '/' . $name . '.zip';
		if ( ! move_uploaded_file( $f['tmp_name'], $zipPath ) ) { hub_back( 'Zip save nahi ho saki.' ); }
		hub_import_async( $name, $zipPath );
		hub_back( "Site '$name' import ho rahi hai — list mein Creating nazar aye gi. (Sirf SQLite wali WebDevHub-jaisi sites import hoti hain.)" );
	}

	// Backup abhi (feature 20)
	if ( $action === 'backup' && hub_slug_ok( $slug ) ) {
		hub_spawn_worker( 'backup --site ' . hub_q( $slug ), [], hub_sites_dir() . '/.' . $slug . '.backup.log' );
		hub_back( "'$slug' ka backup ban raha hai — chand minute mein card par 'Aakhri backup' taaza ho jayega. Backups is computer par sites ke sath wali .exports folder mein rehte hain." );
	}

	if ( in_array( $action, [ 'start', 'stop' ], true ) && hub_slug_ok( $slug ) ) {
		hub_run_manager( $action, $slug );
		hub_back( $action === 'start' ? "'$slug' shuru kar diya." : "'$slug' band kar diya." );
	}

	// Sab projects ek sath (feature 3)
	if ( $action === 'start_all' || $action === 'stop_all' ) {
		$n = 0;
		foreach ( hub_sites() as $s ) {
			if ( ! empty( $s['creating'] ) ) { continue; }
			if ( $action === 'start_all' && empty( $s['running'] ) ) { hub_run_manager( 'start', $s['site'] ); $n++; }
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

	// Thumbnail taaza karna (feature 1)
	if ( $action === 'thumb' && hub_slug_ok( $slug ) ) {
		$out = hub_worker_wait( 'thumb --site ' . hub_q( $slug ), [], 60 );
		hub_back( "'$slug' ki photo: " . ( $out ?: 'koshish ho gayi' ) );
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

// ---------- Health check page (feature 21) ----------
if ( ( $_GET['view'] ?? '' ) === 'health' ) {
	$h = hub_health();
	?>
<!DOCTYPE html>
<html lang="ur">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WebDev Hub — Health Check</title>
<style>
  body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#0f1420; color:#e8ecf3; }
  main { padding:24px 28px 40px; max-width:800px; margin:0 auto; }
  h1 { font-size:22px; } h1 span { color:#f0b64a; }
  table { width:100%; border-collapse:collapse; background:#171e2e; border:1px solid #26314a; border-radius:12px; overflow:hidden; }
  td { padding:10px 14px; border-bottom:1px solid #26314a; font-size:14px; }
  td.k { color:#9fb0c7; width:40%; }
  .ok { color:#39d353; } .bad { color:#e5534b; }
  a.btn { background:#233252; color:#e8ecf3; border:1px solid #33477a; padding:7px 12px; border-radius:9px; font-size:13px; text-decoration:none; }
</style>
</head>
<body>
<main>
  <h1>WebDev <span>Hub</span> — Health Check</h1>
  <p><a class="btn" href="/">← Dashboard par wapas</a></p>
  <table>
    <tr><td class="k">System</td><td><?php echo htmlspecialchars( $h['os'] ); ?></td></tr>
    <tr><td class="k">PHP version</td><td><?php echo htmlspecialchars( $h['php_version'] ); ?></td></tr>
    <tr><td class="k">PHP program</td><td><?php echo htmlspecialchars( $h['php_bin'] ); ?></td></tr>
    <tr><td class="k">Projects ki jagah</td><td><?php echo htmlspecialchars( $h['sites_dir'] ); ?></td></tr>
    <tr><td class="k">Disk khaali / kul</td><td><?php echo hub_human_size( $h['disk_free'] ); ?> / <?php echo hub_human_size( $h['disk_total'] ); ?></td></tr>
    <tr><td class="k">Projects</td><td><?php echo (int) $h['sites_count']; ?> (<?php echo (int) $h['running']; ?> chal rahe hain)</td></tr>
    <tr><td class="k">Bundle — WordPress</td><td><?php echo $h['bundle_wp'] ? '<span class="ok">✓</span> ' . htmlspecialchars( $h['bundle_wp'] ) : '<span class="bad">✗ nahi mila</span>'; ?></td></tr>
    <tr><td class="k">Bundle — Plugins</td><td><?php echo (int) $h['bundle_plugins']; ?> plugins tayyar</td></tr>
    <tr><td class="k">Tools ki jagah</td><td><?php echo htmlspecialchars( $h['tools_dir'] ); ?></td></tr>
    <tr><td class="k">PHP extensions</td><td>
      <?php foreach ( $h['extensions'] as $e => $has ) : ?>
        <span class="<?php echo $has ? 'ok' : 'bad'; ?>"><?php echo $has ? '✓' : '✗'; ?></span> <?php echo htmlspecialchars( $e ); ?>
      <?php endforeach; ?>
    </td></tr>
  </table>
  <p style="color:#9fb0c7;font-size:13px">Koi ✗ nazar aye to pehle usay theek karein — warna kuch features kaam nahi karein ge.</p>
</main>
</body>
</html>
	<?php
	exit;
}

$sites = hub_sites();
$any_creating = false;
foreach ( $sites as $s ) { if ( ! empty( $s['creating'] ) ) { $any_creating = true; } }

// Sizes purane hon to background mein taaza karwao (feature 4) —
// page load kabhi size ginne par nahi rukta.
$need_sizes = false;
foreach ( $sites as $s ) {
	if ( ! empty( $s['creating'] ) ) { continue; }
	if ( empty( $s['size_at'] ) || strtotime( $s['size_at'] ) < time() - 86400 ) { $need_sizes = true; }
}
if ( $need_sizes ) {
	hub_spawn_worker( 'sizes', [], hub_sites_dir() . '/.sizes.log' );
}

// Auto-backup (feature 20): jis site ka backup 7 din se purana ho,
// us ka backup background mein — ek waqt par sirf ek, taake
// dashboard khulna bojh na bane. Ye sirf tab chalta hai jab
// dashboard khula ho (koi alag service nahi hai).
foreach ( $sites as $s ) {
	if ( ! empty( $s['creating'] ) ) { continue; }
	if ( hub_backup_due( $s ) ) {
		hub_spawn_worker( 'backup --site ' . hub_q( $s['site'] ), [], hub_sites_dir() . '/.' . $s['site'] . '.backup.log' );
		break;
	}
}
$templates = hub_templates();
?>
<!DOCTYPE html>
<html lang="ur">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WebDev Hub — Dashboard</title>
<?php if ( $any_creating ) : ?><meta http-equiv="refresh" content="6"><?php endif; ?>
<style>
  * { box-sizing: border-box; }
  body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#0f1420; color:#e8ecf3; }
  header { padding:22px 28px 6px; }
  h1 { margin:0; font-size:24px; } h1 span { color:#f0b64a; }
  .sub { color:#9fb0c7; margin:4px 0 0; font-size:14px; }
  main { padding:18px 28px 40px; max-width:1100px; margin:0 auto; }
  .flash { background:#17351f; border:1px solid #2f7a44; padding:10px 14px; border-radius:10px; margin:12px 0; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; margin-top:14px; }
  .card { background:#171e2e; border:1px solid #26314a; border-radius:14px; padding:16px; }
  .card h3 { margin:0 0 2px; font-size:18px; }
  .meta { color:#9fb0c7; font-size:13px; word-break:break-all; }
  .dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:6px; }
  .on { background:#39d353; } .off { background:#e5534b; } .busy { background:#f0b64a; }
  .btns { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
  a.btn, button.btn { background:#233252; color:#e8ecf3; border:1px solid #33477a; padding:7px 12px;
    border-radius:9px; font-size:13px; cursor:pointer; text-decoration:none; }
  a.btn.primary, button.btn.primary { background:#b8860b; border-color:#f0b64a; color:#14100a; font-weight:600; }
  button.btn.danger { border-color:#7a3333; background:#3a1f24; }
  form.inline { display:inline; }
  .panel { background:#171e2e; border:1px solid #26314a; border-radius:14px; padding:18px; margin-top:22px; }
  .panel h2 { margin:0 0 10px; font-size:18px; }
  label { display:block; font-size:13px; color:#9fb0c7; margin:10px 0 4px; }
  input, select { width:100%; padding:9px 10px; border-radius:9px; border:1px solid #33477a;
    background:#0f1420; color:#e8ecf3; font-size:14px; }
  .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  details { margin-top:10px; } pre { background:#0b0f18; padding:10px; border-radius:9px; overflow:auto;
    max-height:220px; font-size:11px; color:#9fb0c7; }
  .tile { height:64px; border-radius:10px; background:linear-gradient(135deg,#233252,#b8860b);
    display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:700; color:#fff; margin-bottom:10px; }
  .thumb { width:100%; height:150px; object-fit:cover; object-position:top; border-radius:10px; margin-bottom:10px; display:block; }
  .toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:14px 0 4px; }
  .toolbar input[type="search"] { flex:1; min-width:200px; width:auto; }
  .toolbar form { display:inline; margin:0; }
  .empty { color:#9fb0c7; text-align:center; padding:28px 10px; border:1px dashed #33477a; border-radius:14px; margin-top:14px; }
</style>
</head>
<body>
<header>
  <h1>WebDev <span>Hub</span> — Dashboard</h1>
  <p class="sub">Saare WordPress projects ek jagah. Koi XAMPP nahi, koi htdocs nahi — direct yahin se kaam.</p>
</header>
<main>
  <?php if ( $flash ) : ?><div class="flash"><?php echo htmlspecialchars( $flash ); ?></div><?php endif; ?>

  <div class="toolbar">
    <input type="search" id="q" placeholder="Project dhoondhein… (naam likhein)">
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn" name="action" value="start_all">Sab Shuru Karein</button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <button class="btn" name="action" value="stop_all">Sab Band Karein</button>
    </form>
    <a class="btn" href="/?view=health">Health Check</a>
  </div>

  <?php if ( ! $sites ) : ?>
    <div class="empty">Abhi koi project nahi. Neeche wala form bhar kar pehla project banayein.</div>
  <?php else : ?>
  <div class="grid">
    <?php foreach ( $sites as $s ) : ?>
    <div class="card" data-name="<?php echo htmlspecialchars( strtolower( $s['site'] . ' ' . ( $s['blueprint'] ?? '' ) ) ); ?>">
      <?php if ( ! empty( $s['creating'] ) ) : ?>
        <div class="tile">…</div>
        <h3><?php echo htmlspecialchars( $s['site'] ); ?></h3>
        <p class="meta"><span class="dot busy"></span>Creating… (page khud refresh hoga)</p>
      <?php else : ?>
        <?php if ( hub_thumb_file( $s['dir'] ) ) : ?>
          <img class="thumb" src="/?thumb=<?php echo urlencode( $s['site'] ); ?>" alt="">
        <?php else : ?>
          <div class="tile"><?php echo strtoupper( substr( $s['site'], 0, 1 ) ); ?></div>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars( $s['site'] ); ?></h3>
        <p class="meta">
          <span class="dot <?php echo $s['running'] ? 'on' : 'off'; ?>"></span><?php echo $s['running'] ? 'Chal raha hai' : 'Band hai'; ?>
          · Blueprint: <?php echo htmlspecialchars( $s['blueprint'] ?? '' ); ?><br>
          <a href="<?php echo htmlspecialchars( $s['url'] ); ?>" target="_blank" style="color:#f0b64a"><?php echo htmlspecialchars( $s['url'] ); ?></a><br>
          Size: <?php echo ! empty( $s['size_bytes'] ) ? hub_human_size( $s['size_bytes'] ) : '—'; ?>
          · Aakhri dafa chala: <?php echo ! empty( $s['last_started'] ) ? date( 'd M, h:i A', strtotime( $s['last_started'] ) ) : 'kabhi nahi'; ?><br>
          Aakhri backup: <?php echo ! empty( $s['backup_at'] ) ? date( 'd M, h:i A', strtotime( $s['backup_at'] ) ) : 'abhi tak nahi (dashboard khulne par khud ban jata hai)'; ?>
        </p>
        <div class="btns">
          <a class="btn primary" href="<?php echo htmlspecialchars( $s['url'] ); ?>" target="_blank">Site Kholain</a>
          <a class="btn" href="<?php echo htmlspecialchars( hub_admin_url( $s ) ); ?>" target="_blank">Admin Kholain</a>
          <form class="inline" method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn" name="action" value="<?php echo $s['running'] ? 'stop' : 'start'; ?>"><?php echo $s['running'] ? 'Band Karein' : 'Shuru Karein'; ?></button>
          </form>
          <form class="inline" method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn" name="action" value="export">Export (.zip)</button>
          </form>
          <form class="inline" method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn" name="action" value="thumb">Photo Taaza</button>
          </form>
          <form class="inline" method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn" name="action" value="backup">Backup</button>
          </form>
          <form class="inline" method="post" onsubmit="return confirm('Project delete kar dein? Ye wapas nahi ayega.');">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <button class="btn danger" name="action" value="delete">Delete</button>
          </form>
        </div>
        <details>
          <summary style="cursor:pointer;font-size:13px;color:#9fb0c7">Clone banayein / Template banayein</summary>
          <form method="post" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <label>Clone ka naya naam (is site ki hूबहू copy, naya address)</label>
            <input name="new_name" placeholder="meri-site-copy">
            <div class="btns"><button class="btn" name="action" value="clone">Clone Banayein</button></div>
          </form>
          <form method="post" style="margin-top:6px">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <label>Template ka naam (phir is template se kitni bhi sites banengi)</label>
            <input name="tpl_name" placeholder="meri-template">
            <div class="btns"><button class="btn" name="action" value="totemplate">Template Banayein</button></div>
          </form>
        </details>
        <details>
          <summary style="cursor:pointer;font-size:13px;color:#9fb0c7">Admin password reset karein</summary>
          <form method="post" onsubmit="return confirm('Admin ka password badal dein? Purana password phir kaam nahi karega.');" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars( $s['site'] ); ?>">
            <label>Naya password (kam az kam 6 characters)</label>
            <input type="password" name="new_pass" required minlength="6" autocomplete="new-password">
            <div class="btns"><button class="btn" name="action" value="resetpass">Password Badlein</button></div>
          </form>
        </details>
        <details>
          <summary style="cursor:pointer;font-size:13px;color:#9fb0c7">Logs dekhein · Folder: <?php echo htmlspecialchars( $s['dir'] ); ?></summary>
          <pre><?php echo htmlspecialchars( hub_logs( $s['site'], 30 ) ); ?></pre>
        </details>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2>Naya Project Banayein</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action" value="create">
      <div class="row">
        <div>
          <label>Project ka naam (chhoti angrezi, dash chalega)</label>
          <input name="name" required placeholder="meri-dukan">
        </div>
        <div>
          <label>Blueprint</label>
          <select name="blueprint">
            <option value="business">Business Site</option>
            <option value="store">Online Store (WooCommerce + PKR)</option>
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
      <div class="row">
        <div><label>Admin username</label><input name="admin_user" value="admin"></div>
        <div><label>Admin email</label><input name="admin_email" value="admin@example.local"></div>
      </div>
      <label>Admin password (khud set karein — kahin save nahi hota; template ke liye zaroori nahi)</label>
      <input type="password" name="admin_pass" minlength="6">
      <label style="display:flex;gap:8px;align-items:center;margin-top:14px;color:#e8ecf3;font-size:14px">
        <input type="checkbox" name="demo_content" value="1" style="width:auto">
        Urdu demo content shamil karein (5 posts, 2 pages — store par 5 products bhi; site khaali nahi lage gi)
      </label>
      <div class="btns"><button class="btn primary" type="submit">Project Banayein</button></div>
    </form>
  </div>

  <?php if ( $templates ) : ?>
  <div class="panel">
    <h2>Mere Templates</h2>
    <?php foreach ( $templates as $t ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #26314a">
      <div>
        <strong><?php echo htmlspecialchars( $t['name'] ); ?></strong>
        <span class="meta"> — <?php echo htmlspecialchars( $t['from_site'] ?? '' ); ?> se bani · <?php echo ! empty( $t['created'] ) ? date( 'd M Y', strtotime( $t['created'] ) ) : ''; ?></span>
        <div class="meta">Is se site banane ke liye oopar wale form mein Blueprint ki jagah ye template chunein. Admin template wali site wala hi hoga.</div>
      </div>
      <form method="post" onsubmit="return confirm('Template delete kar dein?');">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="tpl" value="<?php echo htmlspecialchars( $t['name'] ); ?>">
        <button class="btn danger" name="action" value="tpl_delete">Delete</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2>Zip Se Site Import Karein</h2>
    <p class="meta" style="margin-top:0">WebDevHub se export ki hui zip (ya aisi hi SQLite wali WordPress site ki zip) yahan se purani/client ki site ke tor par wapas laein. MySQL wali zip yahan nahi chalegi.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action" value="import">
      <div class="row">
        <div>
          <label>Nayi site ka naam</label>
          <input name="name" required placeholder="purani-site">
        </div>
        <div>
          <label>Zip file chunein</label>
          <input type="file" name="zipfile" accept=".zip" required>
        </div>
      </div>
      <div class="btns"><button class="btn primary" type="submit">Import Shuru Karein</button></div>
    </form>
  </div>

  <p class="meta" style="margin-top:16px">WebDev Hub Studio v2.2 · Dashboard sirf is computer par (127.0.0.1) chalta hai.</p>
</main>
<script>
  // Project search (feature 5) — sirf cards chhupata/dikhata hai
  var q = document.getElementById('q');
  if (q) {
    q.addEventListener('input', function () {
      var v = q.value.toLowerCase();
      document.querySelectorAll('.card').forEach(function (c) {
        c.style.display = (c.getAttribute('data-name') || '').indexOf(v) >= 0 ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>
