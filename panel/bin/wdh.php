<?php
/**
 * WebDevHub worker — cross-platform site operations (CLI only).
 *
 * Ye wahi kaam hai jo pehle bash scripts (install.sh / webdevhub.sh)
 * karte thay, lekin PHP mein taake Windows aur Linux par EK hi code
 * path chale. Dashboard (panel/lib/hub.php) isay hi bulata hai.
 *
 * Commands:
 *   create --site S --blueprint B --port P [--env-file F]
 *   start  --site S
 *   stop   --site S
 *   list
 *
 * Admin credentials: WDH_ADMIN_USER / WDH_ADMIN_EMAIL / WDH_ADMIN_PASS
 * env se, ya --env-file JSON se (dashboard yehi bhejta hai).
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

require dirname( __DIR__ ) . '/lib/hub.php';

// Dashboard ke hub_worker_wait() ko completion ki pehchan:
// har run ke aakhir mein (exit kahin se bhi ho) ye marker log mein aata hai.
register_shutdown_function( function () { echo "\n##WDH_END##\n"; } );

$cmd  = $argv[1] ?? '';
// Manual parse — getopt() pehle positional arg (command) par ruk jata hai.
$opts = [];
$rest = array_slice( $argv, 2 );
for ( $i = 0; $i < count( $rest ); $i++ ) {
	if ( strpos( $rest[ $i ], '--' ) !== 0 ) { continue; }
	$kv = substr( $rest[ $i ], 2 );
	if ( strpos( $kv, '=' ) !== false ) {
		[ $k, $v ] = explode( '=', $kv, 2 );
		$opts[ $k ] = $v;
	} elseif ( isset( $rest[ $i + 1 ] ) && strpos( $rest[ $i + 1 ], '--' ) !== 0 ) {
		$opts[ $kv ] = $rest[ ++$i ];
	} else {
		$opts[ $kv ] = true;
	}
}
$slug = $opts['site'] ?? '';

function wdh_fail( $msg ) { fwrite( STDERR, $msg . "\n" ); exit( 1 ); }

function wdh_extract( $zipFile, $dest ) {
	$zip = new ZipArchive();
	if ( $zip->open( $zipFile ) !== true ) { wdh_fail( "Zip nahi khuli: $zipFile" ); }
	if ( ! is_dir( $dest ) ) { mkdir( $dest, 0775, true ); }
	$zip->extractTo( $dest );
	$zip->close();
}

function wdh_copy_dir( $src, $dst ) {
	if ( ! is_dir( $dst ) ) { mkdir( $dst, 0775, true ); }
	foreach ( scandir( $src ) as $name ) {
		if ( $name === '.' || $name === '..' ) { continue; }
		$s = $src . '/' . $name;
		$d = $dst . '/' . $name;
		if ( is_dir( $s ) ) { wdh_copy_dir( $s, $d ); }
		else { copy( $s, $d ); }
	}
}

function wdh_run_setup( array $args ) {
	$setup = hub_tools_dir() . '/setup-site.php';
	$cmd = hub_q( hub_php() ) . ' ' . hub_q( $setup );
	foreach ( $args as $a ) { $cmd .= ' ' . $a; }
	$out = [];
	$rc = 0;
	exec( $cmd . ' 2>&1', $out, $rc );
	foreach ( $out as $line ) { echo $line . "\n"; }
	return $rc;
}

function wdh_salts() {
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_[]{}<>~`+=,.;:/?|';
	$out = '';
	foreach ( [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ] as $k ) {
		$s = '';
		for ( $i = 0; $i < 64; $i++ ) { $s .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ]; }
		$out .= "define( '$k', '" . addslashes( $s ) . "' );\n";
	}
	return $out;
}

/** Screenshot ke liye browser binary — Windows par Edge hamesha
 * hota hai; Linux par jo mil jaye. Na mile to placeholder banta hai. */
function wdh_browser_bin() {
	if ( hub_is_windows() ) {
		$candidates = [
			'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
			'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
			'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
		];
		foreach ( $candidates as $c ) { if ( file_exists( $c ) ) { return $c; } }
		return null;
	}
	foreach ( [ 'chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable' ] as $b ) {
		$w = trim( (string) shell_exec( 'command -v ' . $b . ' 2>/dev/null' ) );
		if ( $w ) { return $w; }
	}
	if ( file_exists( '/opt/meta-chromium/chrome' ) ) { return '/opt/meta-chromium/chrome'; }
	return null;
}

/** GD image ko thumbnail file mein save karo — JPEG ho to .jpg,
 * warna .png (static PHP builds mein imagejpeg nahi hota). */
function wdh_save_thumb_img( $img, $dir ) {
	@unlink( $dir . '/.webdevhub-thumb.jpg' );
	@unlink( $dir . '/.webdevhub-thumb.png' );
	if ( function_exists( 'imagejpeg' ) ) {
		imagejpeg( $img, $dir . '/.webdevhub-thumb.jpg', 85 );
		return file_exists( $dir . '/.webdevhub-thumb.jpg' );
	}
	if ( function_exists( 'imagepng' ) ) {
		imagepng( $img, $dir . '/.webdevhub-thumb.png' );
		return file_exists( $dir . '/.webdevhub-thumb.png' );
	}
	return false;
}

/** Card thumbnail: pehle asal screenshot ki koshish (30s ki had ke
 * sath), warna GD ka placeholder — file hamesha banti hai. */
function wdh_make_thumb( $dir, $meta ) {
	$bin = wdh_browser_bin();
	if ( $bin ) {
		$tmpPng = $dir . '/.thumb-shot.png';
		@unlink( $tmpPng );
		$nullIn = hub_is_windows() ? 'NUL' : '/dev/null';
		$proc = proc_open(
			[ $bin, '--headless', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
				'--hide-scrollbars', '--window-size=1280,800', '--virtual-time-budget=10000',
				'--screenshot=' . $tmpPng, $meta['url'] . '/' ],
			[ 0 => [ 'file', $nullIn, 'r' ], 1 => [ 'file', $dir . '/.thumb-browser.log', 'a' ], 2 => [ 'file', $dir . '/.thumb-browser.log', 'a' ] ],
			$pipes
		);
		if ( is_resource( $proc ) ) {
			$deadline = time() + 30;
			while ( time() < $deadline ) {
				if ( ! proc_get_status( $proc )['running'] ) { break; }
				usleep( 300000 );
			}
			if ( proc_get_status( $proc )['running'] ) { proc_terminate( $proc, 9 ); }
			proc_close( $proc );
		}
		if ( file_exists( $tmpPng ) && filesize( $tmpPng ) > 5000 && extension_loaded( 'gd' ) ) {
			$img = @imagecreatefrompng( $tmpPng );
			if ( $img ) {
				$w = imagesx( $img ); $h = imagesy( $img );
				$nw = 640; $nh = max( 1, (int) ( $h * $nw / $w ) );
				$t = imagecreatetruecolor( $nw, $nh );
				imagecopyresampled( $t, $img, 0, 0, 0, 0, $nw, $nh, $w, $h );
				wdh_save_thumb_img( $t, $dir );
				imagedestroy( $img ); imagedestroy( $t );
				@unlink( $tmpPng );
				echo "Thumbnail tayyar hai (asal screenshot).\n";
				return;
			}
		}
		@unlink( $tmpPng );
	}
	// Fallback: GD placeholder (brand rang, site naam)
	if ( extension_loaded( 'gd' ) ) {
		$img = imagecreatetruecolor( 640, 400 );
		$top = [ 23, 30, 46 ]; $bot = [ 138, 100, 22 ];
		for ( $y = 0; $y < 400; $y++ ) {
			$r = (int) ( $top[0] + ( $bot[0] - $top[0] ) * $y / 400 );
			$g = (int) ( $top[1] + ( $bot[1] - $top[1] ) * $y / 400 );
			$b = (int) ( $top[2] + ( $bot[2] - $top[2] ) * $y / 400 );
			imageline( $img, 0, $y, 640, $y, imagecolorallocate( $img, $r, $g, $b ) );
		}
		$white = imagecolorallocate( $img, 255, 255, 255 );
		$name = $meta['site'];
		$x = max( 10, (int) ( ( 640 - imagefontwidth( 5 ) * strlen( $name ) ) / 2 ) );
		imagestring( $img, 5, $x, 178, $name, $white );
		$bp = 'WebDev Hub — ' . ( $meta['blueprint'] ?? '' );
		$x2 = max( 10, (int) ( ( 640 - imagefontwidth( 3 ) * strlen( $bp ) ) / 2 ) );
		imagestring( $img, 3, $x2, 208, $bp, $white );
		wdh_save_thumb_img( $img, $dir );
		imagedestroy( $img );
	}
	echo "Thumbnail tayyar hai (placeholder).\n";
}

/** HTTP jawab ka intezar (server warm-up) */
function wdh_wait_http( $url, $tries = 12, $timeout = 30 ) {
	for ( $i = 0; $i < $tries; $i++ ) {
		$ctx = stream_context_create( [ 'http' => [ 'timeout' => $timeout, 'ignore_errors' => true ] ] );
		$r = @file_get_contents( $url, false, $ctx );
		if ( $r !== false ) { return true; }
		sleep( 1 );
	}
	return false;
}

/* ---------------- create ---------------- */
if ( $cmd === 'create' ) {
	if ( ! hub_slug_ok( $slug ) ) { wdh_fail( "Site naam ghalat hai: $slug" ); }
	$blueprint = $opts['blueprint'] ?? 'business';
	$port = (int) ( $opts['port'] ?? 8080 );

	$env = [];
	if ( ! empty( $opts['env-file'] ) && file_exists( $opts['env-file'] ) ) {
		$env = (array) json_decode( (string) file_get_contents( $opts['env-file'] ), true );
		@unlink( $opts['env-file'] );
	}
	$user  = $env['WDH_ADMIN_USER']  ?? ( getenv( 'WDH_ADMIN_USER' )  ?: 'admin' );
	$email = $env['WDH_ADMIN_EMAIL'] ?? ( getenv( 'WDH_ADMIN_EMAIL' ) ?: 'admin@example.local' );
	$pass  = $env['WDH_ADMIN_PASS']  ?? ( getenv( 'WDH_ADMIN_PASS' )  ?: '' );

	$repo = hub_repo();
	$bundle = $repo . '/bundle';
	$base = hub_sites_dir();
	$dir = $base . '/' . $slug;
	$url = 'http://127.0.0.1:' . $port;

	if ( file_exists( $dir ) ) { wdh_fail( "Site pehle se mojood hai: $dir" ); }
	if ( ! is_dir( $base ) ) { mkdir( $base, 0775, true ); }

	$wpZips = glob( $bundle . '/wordpress/dist/wordpress-*.zip' ) ?: [];
	if ( ! $wpZips ) { wdh_fail( "Bundle tayyar nahi hai — WordPress dist zip nahi mili (pehle bundle fetch karo)." ); }
	$wpZip = $wpZips[0];

	echo "PHP: " . hub_php() . " (" . PHP_VERSION . ")\n";
	mkdir( $dir, 0775, true );

	// WP core: temp mein extract karke contents copy
	$tmp = $base . '/.tmp-' . $slug;
	wdh_extract( $wpZip, $tmp );
	wdh_copy_dir( $tmp . '/wordpress', $dir );
	hub_rrmdir( $tmp );

	// Plugins
	if ( ! is_dir( $dir . '/wp-content/plugins' ) ) { mkdir( $dir . '/wp-content/plugins', 0775, true ); }
	foreach ( glob( $bundle . '/plugins/dist/*.zip' ) ?: [] as $z ) {
		wdh_extract( $z, $dir . '/wp-content/plugins' );
	}

	// SQLite drop-in
	$sqliteDir = $dir . '/wp-content/plugins/sqlite-database-integration';
	if ( file_exists( $sqliteDir . '/db.copy' ) ) { copy( $sqliteDir . '/db.copy', $dir . '/wp-content/db.php' ); }
	if ( ! is_dir( $dir . '/wp-content/database' ) ) { mkdir( $dir . '/wp-content/database', 0775, true ); }

	// Must-use plugin
	if ( ! is_dir( $dir . '/wp-content/mu-plugins' ) ) { mkdir( $dir . '/wp-content/mu-plugins', 0775, true ); }
	copy( $bundle . '/mu-plugins/webdevhub-core.php', $dir . '/wp-content/mu-plugins/webdevhub-core.php' );

	// Urdu language pack (WPLANG=ur ke liye lazmi — bundle se)
	if ( file_exists( $bundle . '/languages/ur-core.zip' ) ) {
		wdh_extract( $bundle . '/languages/ur-core.zip', $dir . '/wp-content/languages' );
	}

	// wp-config
	$tpl = (string) file_get_contents( $bundle . '/config/wp-config-template.php' );
	$dbName = preg_replace( '/[^a-zA-Z0-9_]/', '', $slug );
	$cfg = str_replace(
		[ '%%SITE_DIR%%', '%%DB_NAME%%', '%%SALTS%%' ],
		[ str_replace( '\\', '/', $dir ), $dbName, wdh_salts() ],
		$tpl
	);
	file_put_contents( $dir . '/wp-config.php', $cfg );

	if ( $pass === '' ) {
		$pass = bin2hex( random_bytes( 9 ) );
		file_put_contents( $dir . '/.webdevhub-admin.txt', $pass );
		@chmod( $dir . '/.webdevhub-admin.txt', 0600 );
		echo "Note: admin password .webdevhub-admin.txt mein hai.\n";
	}

	$secret = bin2hex( random_bytes( 24 ) );
	$quotedDir = hub_q( '--path=' . $dir );
	$quotedUrl = hub_q( '--url=' . $url );

	$rc = wdh_run_setup( [
		$quotedDir, $quotedUrl, '--install',
		hub_q( '--title=WebDevHub — ' . $slug ),
		hub_q( '--admin-user=' . $user ),
		hub_q( '--admin-email=' . $email ),
		hub_q( '--admin-pass=' . $pass ),
		hub_q( '--hub-secret=' . $secret ),
	] );
	if ( $rc !== 0 ) { wdh_fail( "WP install fail ho gaya (setup rc=$rc) — aadhi bani site waisi hi chhor di hai taake log/masla dekha ja sake; dashboard se delete kar sakte ho." ); }

	// Plugins activate (har plugin alag process mein) + blueprint
	if ( $blueprint !== 'blank' ) {
		$bpFile = $bundle . '/blueprints/' . $blueprint . '/blueprint.json';
		$bp = file_exists( $bpFile ) ? (array) json_decode( (string) file_get_contents( $bpFile ), true ) : [];
		foreach ( (array) ( $bp['required_plugins'] ?? [] ) as $p ) {
			wdh_run_setup( [ $quotedDir, $quotedUrl, hub_q( '--activate=' . $p ) ] );
		}
		wdh_run_setup( [ $quotedDir, $quotedUrl, '--apply-blueprint', hub_q( '--blueprint=' . $blueprint ) ] );
	}

	$meta = [
		'site' => $slug, 'blueprint' => $blueprint, 'port' => $port,
		'url' => $url, 'created' => date( 'c' ), 'secret' => $secret,
	];
	file_put_contents( $dir . '/.webdevhub.json', json_encode( $meta, JSON_UNESCAPED_SLASHES ) );
	@chmod( $dir . '/.webdevhub.json', 0600 );

	echo "\nSite tayyar hai: $url\n";
	echo "Admin: $url/wp-admin/  (user: $user)\n";
	exit( 0 );
}

/* ---------------- start ---------------- */
if ( $cmd === 'start' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	$port = (int) $meta['port'];
	$url = $meta['url'];
	$pidFile = $dir . '/.server.pid';
	$pid = file_exists( $pidFile ) ? (int) file_get_contents( $pidFile ) : 0;
	if ( hub_pid_alive( $pid ) ) { echo "Pehle se chal raha hai: $url\n"; exit( 0 ); }

	$router = hub_tools_dir() . '/router.php';
	$log = $dir . '/.server.log';

	// Server proc_open se chalti hai — shell pipe ke zariye NAHI.
	// Wajah (asal bug, smoke + live test mein pakra gaya): shell_exec
	// ke capture pipe ke dono siray site server inherit kar leta tha;
	// pipe kabhi band na hone se use pakre hue dashboard request
	// hamesha ke liye latak jati thi. proc_open mein stdio seedha
	// files par hai — koi pipe hai hi nahi jo latkaye.
	$descriptors = [
		0 => [ 'file', '/dev/null', 'r' ],
		1 => [ 'file', $log, 'a' ],
		2 => [ 'file', $log, 'a' ],
	];
	if ( hub_is_windows() ) {
		$descriptors[0] = [ 'file', 'NUL', 'r' ];
	}
	$env = getenv();
	if ( ! hub_is_windows() ) { $env['PHP_CLI_SERVER_WORKERS'] = '6'; }
	$proc = proc_open(
		[ hub_php(), '-S', '127.0.0.1:' . $port, '-t', $dir, $router ],
		$descriptors, $pipes, null, $env
	);
	if ( ! is_resource( $proc ) ) { wdh_fail( "Server start nahi hua — log dekho: $log" ); }
	$status = proc_get_status( $proc );
	$pid = (int) $status['pid'];
	if ( hub_is_windows() ) {
		// Windows par proc ka pid wrapper ka ho sakta hai — asal
		// listener ki pid port se nikaalo (thoda intezar karke).
		for ( $i = 0; $i < 20; $i++ ) {
			$found = hub_port_pid( $port );
			if ( $found ) { $pid = $found; break; }
			usleep( 500000 );
		}
	}
	// proc_close NAHI karte — wo process ke khatam hone ka intezar
	// karega. Handle yun hi chhor do; worker ke exit par server
	// apni zindagi jita rehta hai.
	file_put_contents( $pidFile, (string) $pid );
	wdh_wait_http( $url . '/' );

	// Card ke liye: aakhri dafa kab chala (feature 4)
	$meta['last_started'] = date( 'c' );
	hub_save_meta( $dir, $meta );

	// Thumbnail alag worker mein (start slow na ho) — feature 1
	hub_spawn_worker( 'thumb --site ' . hub_q( $slug ), [], $dir . '/.thumb.log' );

	echo "Chal gaya: $url  (admin: /wp-admin/)\n";
	exit( 0 );
}

/* ---------------- stop ---------------- */
if ( $cmd === 'stop' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$pidFile = $dir . '/.server.pid';
	$pid = file_exists( $pidFile ) ? (int) file_get_contents( $pidFile ) : 0;
	if ( ! $pid && hub_is_windows() ) {
		$meta = hub_site_meta( $dir );
		if ( $meta ) { $pid = hub_port_pid( (int) $meta['port'] ) ?? 0; }
	}
	if ( $pid ) { hub_kill( $pid ); }
	@unlink( $pidFile );
	echo "Band ho gaya: $slug\n";
	exit( 0 );
}

/* ---------------- list ---------------- */
if ( $cmd === 'list' ) {
	foreach ( hub_sites() as $s ) {
		if ( ! empty( $s['creating'] ) ) { echo $s['site'] . ": (ban raha hai)\n"; continue; }
		echo $s['site'] . ': ' . $s['url'] . ' | blueprint: ' . $s['blueprint']
			. ' | ' . ( ! empty( $s['running'] ) ? 'chal raha hai' : 'band hai' ) . "\n";
	}
	exit( 0 );
}

/* ---------------- sizes (feature 4) ---------------- */
if ( $cmd === 'sizes' ) {
	foreach ( hub_sites() as $s ) {
		if ( ! empty( $s['creating'] ) ) { continue; }
		$meta = hub_site_meta( $s['dir'] );
		if ( ! $meta ) { continue; }
		$meta['size_bytes'] = hub_dir_size( $s['dir'] );
		$meta['size_at'] = date( 'c' );
		hub_save_meta( $s['dir'], $meta );
		echo $s['site'] . ': ' . hub_human_size( $meta['size_bytes'] ) . "\n";
	}
	exit( 0 );
}

/* ---------------- resetpass (feature 7) ---------------- */
if ( $cmd === 'resetpass' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	if ( ! hub_site_meta( $dir ) ) { wdh_fail( "Site nahi mili: $slug" ); }
	$env = [];
	if ( ! empty( $opts['env-file'] ) && file_exists( $opts['env-file'] ) ) {
		$env = (array) json_decode( (string) file_get_contents( $opts['env-file'] ), true );
		@unlink( $opts['env-file'] );
	}
	$pass = $env['WDH_NEW_PASS'] ?? ( getenv( 'WDH_NEW_PASS' ) ?: '' );
	if ( strlen( (string) $pass ) < 6 ) { wdh_fail( 'Naya password kam az kam 6 characters ho.' ); }
	// Password argv mein NAHI jata — tool env se parhta hai.
	putenv( 'WDH_NEW_PASS=' . $pass );
	$tool = hub_tools_dir() . '/reset-admin.php';
	if ( ! file_exists( $tool ) ) { wdh_fail( "Tool nahi mila: $tool" ); }
	$out = [];
	$rc = 0;
	exec( hub_q( hub_php() ) . ' ' . hub_q( $tool ) . ' ' . hub_q( '--path=' . $dir ) . ' 2>&1', $out, $rc );
	foreach ( $out as $line ) { echo $line . "\n"; }
	if ( $rc !== 0 ) { wdh_fail( 'Password reset fail ho gaya.' ); }
	exit( 0 );
}

/* ---------------- thumb (feature 1) ---------------- */
if ( $cmd === 'thumb' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	wdh_make_thumb( $dir, $meta );
	exit( 0 );
}

echo "Usage: wdh.php create|start|stop|list|sizes|resetpass|thumb [--site S] [--blueprint B] [--port P]\n";
exit( 1 );
