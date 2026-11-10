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

// FD safai is worker ke SPAWN par hi ho chuki hai (hub_spawn_worker
// ki shell line mein — dashboard ka listen socket yahan inherit hi
// nahi hota). PHP ke andar se php://fd wala tareeqa FD band NAHI
// karta (azma kar sabit hua), is liye yahan koi safai NAHI hai —
// asal aur wahid safai spawn wali shell karti hai.

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

function wdh_fail( $msg ) { $GLOBALS['wdh_last_error'] = $msg; fwrite( STDERR, $msg . "\n" ); exit( 1 ); }

/** Ek create step ki hadd (second) — test mein env se chhota ho sakta hai. */
function wdh_step_timeout() {
	$t = (int) ( getenv( 'WDH_STEP_TIMEOUT' ) ?: 0 );
	return $t > 0 ? $t : 180;
}

/** Poori creation ki hadd (second) — watchdog backstop. */
function wdh_create_watchdog() {
	$t = (int) ( getenv( 'WDH_CREATE_WATCHDOG' ) ?: 0 );
	return $t > 0 ? $t : 900;
}

/** Process tree khatam karo (timeout par). */
function wdh_kill_tree( $proc, $pid ) {
	if ( hub_is_windows() ) {
		// /T = poora tree (cmd + php + us ke bache)
		shell_exec( 'taskkill /F /T /PID ' . (int) $pid . ' >NUL 2>&1' );
		return;
	}
	// Pehle bache, phir shell khud
	shell_exec( 'pkill -9 -P ' . (int) $pid . ' 2>/dev/null' );
	if ( function_exists( 'posix_kill' ) ) { @posix_kill( (int) $pid, 9 ); }
	@proc_terminate( $proc, 9 );
}

/**
 * Child process chalao — output FILE mein redirect, exec() ke
 * output-capture pipe par NAHI.
 *
 * Wajah (owner ke PC par sabit bug, 2026-10-31): exec() capture
 * pipe ke EOF ka intezar karta hai; koi grandchild process pipe
 * pakre rakhe to parent hamesha ke liye ruk jata tha (0% CPU/disk,
 * creation 30+ minute "Creating..." par atki rahi). File-redirect
 * mein pipe hai hi nahi — parent proc_get_status se poll karta
 * hai, aur step timeout par tree kill karke aage barhta hai.
 * Child ki output poll ke doran job log mein live bhi jati hai.
 *
 * Test hook: env WDH_TEST_STALL=<stepKey ka hissa> ho to us step
 * ki jagah ek kabhi-na-khatam-hone wala process chalega (CI/local
 * hang-simulation ke liye — production mein ye env kabhi nahi hota).
 *
 * Return: child ka exit code; timeout par 124.
 */
function wdh_run_child( $cmd, $stepKey ) {
	$stall = (string) ( getenv( 'WDH_TEST_STALL' ) ?: '' );
	if ( $stall !== '' && strpos( $stepKey, $stall ) !== false ) {
		$sf = sys_get_temp_dir() . '/wdh-stall-' . getmypid() . '.php';
		file_put_contents( $sf, '<?php sleep(3600);' );
		$cmd = hub_q( hub_php() ) . ' ' . hub_q( $sf );
		echo "(test stall hook: step '$stepKey' jaan boojh kar roka gaya hai)\n";
	}

	$stepFile = tempnam( sys_get_temp_dir(), 'wdh-step-' );
	$nullIn = hub_is_windows() ? 'NUL' : '/dev/null';
	$desc = [
		0 => [ 'file', $nullIn, 'r' ],
		1 => [ 'file', $stepFile, 'a' ],
		2 => [ 'file', $stepFile, 'a' ],
	];
	$pipes = [];
	$proc = proc_open( $cmd, $desc, $pipes );
	if ( ! is_resource( $proc ) ) { @unlink( $stepFile ); return 1; }

	$timeout = wdh_step_timeout();
	$start = time();
	$offset = 0;
	$exit = null;
	$timedOut = false;
	$drain = function () use ( $stepFile, &$offset ) {
		clearstatcache( true, $stepFile );
		$size = file_exists( $stepFile ) ? (int) filesize( $stepFile ) : 0;
		if ( $size > $offset ) {
			$fh = fopen( $stepFile, 'r' );
			if ( $fh ) { fseek( $fh, $offset ); $chunk = stream_get_contents( $fh ); fclose( $fh ); if ( $chunk !== false && $chunk !== '' ) { echo $chunk; if ( substr( $chunk, -1 ) !== "\n" ) { echo "\n"; } } $offset = $size; }
		}
	};
	while ( true ) {
		$st = proc_get_status( $proc );
		$drain();
		if ( ! $st['running'] ) { $exit = $st['exitcode']; break; }
		if ( time() - $start > $timeout ) { $timedOut = true; wdh_kill_tree( $proc, (int) $st['pid'] ); break; }
		usleep( 200000 );
	}
	$rc = proc_close( $proc );
	$drain();
	@unlink( $stepFile );
	if ( isset( $sf ) ) { @unlink( $sf ); }
	if ( $timedOut ) {
		echo "TIMEOUT: step '$stepKey' {$timeout}s se zyada chala — rok diya gaya.\n";
		return 124;
	}
	if ( $exit === null || $exit === -1 ) { $exit = $rc; }
	return (int) $exit;
}

/** Create ke step markers — dashboard inhen parh kar Live Progress dikhata hai. */
function wdh_step( $n, $total, $key, $label ) {
	$GLOBALS['wdh_create_step'] = $key;
	$GLOBALS['wdh_create_step_label'] = $label;
	echo "## STEP $n/$total key=$key $label\n";
}
function wdh_step_ok( $n, $total ) { echo "## STEP_OK $n/$total\n"; }

/**
 * Create fail ho (ya worker mar jaye) to site folder mein failure
 * marker — dashboard is se "Failed" state dikhata hai. Meta ban
 * gaya ho (site tayyar) to marker nahi banta.
 */
function wdh_create_failure_marker() {
	$dir = $GLOBALS['wdh_create_dir'] ?? '';
	if ( $dir === '' || ! empty( $GLOBALS['wdh_create_ok'] ) ) { return; }
	if ( ! is_dir( $dir ) || file_exists( $dir . '/.webdevhub.json' ) ) { return; }
	$info = [
		'slug'  => $GLOBALS['wdh_create_slug'] ?? basename( $dir ),
		'step'  => $GLOBALS['wdh_create_step'] ?? 'start',
		'label' => $GLOBALS['wdh_create_step_label'] ?? '',
		'error' => $GLOBALS['wdh_last_error'] ?? 'Creation darmiyan mein ruk gayi',
		'at'    => date( 'c' ),
	];
	@file_put_contents( $dir . '/.create-failed.json', json_encode( $info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/**
 * Extract ke baad har zip entry ka size asal file se milao.
 * Bojh tale unzip/ZipArchive kabhi kabhi files adhoori chhor
 * dete hain lekin success report kar dete hain — adhoori files
 * ko infiradi taur par dobara nikaal kar theek karo, warna
 * saaf fail do (kharab site kabhi activate nahi hogi).
 */
/**
 * Zip SIRF parhne ke liye kholo (v2.10.1, gate run #1 ki tehqeeq
 * ke baad): ZipArchive::RDONLY + error-aware bounded retry.
 * Windows par taaza likhi hui file par read-write open kabhi kabhi
 * waqti taur par fail ho jata hai (lock/race) — RDONLY us khatre
 * ko kam karta hai, aur transient codes (ER_OPEN / ER_READ /
 * ER_SEEK / ER_NOENT) par 4 koshishein hoti hain (foran, 0.5s,
 * 1s, 2s). Asal kharab zip (ER_NOZIP / ER_INCONS / ER_INVAL)
 * par foran fail — aur har koshish ka error code job log mein
 * jata hai taake agli dafa wajah code se mile, andaze se nahi.
 * Kamyabi par khuli hui ZipArchive, warna false ($err mein code).
 */
function wdh_zip_open_read( $zipFile, &$err = 0 ) {
	$transient = [ ZipArchive::ER_OPEN, ZipArchive::ER_READ, ZipArchive::ER_SEEK, ZipArchive::ER_NOENT ];
	$waits = [ 0, 500000, 1000000, 2000000 ];
	$err = 0;
	for ( $try = 1; $try <= 4; $try++ ) {
		if ( $waits[ $try - 1 ] > 0 ) { usleep( $waits[ $try - 1 ] ); }
		$zip = new ZipArchive();
		$rc = $zip->open( $zipFile, ZipArchive::RDONLY );
		if ( $rc === true ) { return $zip; }
		$err = (int) $rc;
		echo 'Zip open koshish ' . $try . ' fail (code=' . $err . '): ' . $zipFile . "\n";
		if ( ! in_array( $err, $transient, true ) ) { break; }
	}
	return false;
}

function wdh_extract_verify_repair( $zipFile, $dest ) {
	$zip = wdh_zip_open_read( $zipFile, $zerr );
	if ( ! $zip ) { wdh_fail( "Zip verify ke liye nahi khuli: $zipFile (code=$zerr)" ); }
	$bad = [];
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$st = $zip->statIndex( $i );
		if ( ! $st || substr( $st['name'], -1 ) === '/' ) { continue; }
		$name = str_replace( '\\', '/', $st['name'] );
		if ( $name === '' || $name[0] === '/' || strpos( $name, '../' ) !== false ) {
			$bad[] = [ $st['name'], (int) $st['size'] ];
			continue;
		}
		$path = $dest . '/' . $name;
		clearstatcache( true, $path );
		if ( ! file_exists( $path ) || filesize( $path ) !== (int) $st['size'] ) {
			$bad[] = [ $st['name'], (int) $st['size'] ];
		}
	}
	if ( ! $bad ) { $zip->close(); return; }
	echo 'Extract verification: ' . count( $bad ) . " files adhoori/ghaib hain — infiradi repair ho rahi hai...\n";
	for ( $round = 1; $round <= 2 && $bad; $round++ ) {
		$remaining = [];
		foreach ( $bad as [ $name, $want ] ) {
			$safe = str_replace( '\\', '/', $name );
			$path = $dest . '/' . $safe;
			if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0775, true ); }
			$ok = false;
			$stream = $zip->getStream( $name );
			if ( $stream ) {
				$out = @fopen( $path, 'wb' );
				if ( $out ) {
					stream_copy_to_stream( $stream, $out );
					fclose( $out );
					$ok = true;
				}
				fclose( $stream );
			}
			if ( ! $ok ) { $zip->extractTo( $dest, [ $name ] ); }
			clearstatcache( true, $path );
			if ( ! file_exists( $path ) || filesize( $path ) !== $want ) {
				$remaining[] = [ $name, $want ];
			}
		}
		$bad = $remaining;
		if ( $bad ) { sleep( 2 ); }
	}
	$zip->close();
	if ( $bad ) {
		wdh_fail( 'Zip ki ' . count( $bad ) . ' files extract nahi ho sakin (pehli: ' . $bad[0][0] . ') — disk/permission ki haalat dekh kar dobara koshish karein.' );
	}
	echo "Extract verification: saari files theek hain.\n";
}

function wdh_extract( $zipFile, $dest ) {
	if ( ! is_dir( $dest ) ) { mkdir( $dest, 0775, true ); }
	// Bade zips (poori sites) par ZipArchive sust/fail ho sakta hai —
	// system unzip ho to wahi pehle (Linux par hota hai).
	// Dono rastein bojh tale waqti taur par nakam ho sakte hain
	// (writes par "Operation not permitted" dekha gaya hai) — is
	// liye har raste ki DO koshishen, phir saaf fail.
	if ( ! hub_is_windows() ) {
		$unzip = trim( (string) shell_exec( 'command -v unzip 2>/dev/null' ) );
		if ( $unzip ) {
			for ( $try = 1; $try <= 2; $try++ ) {
				$out = [];
				$rc = 0;
				exec( escapeshellarg( $unzip ) . ' -q -o ' . escapeshellarg( $zipFile ) . ' -d ' . escapeshellarg( $dest ) . ' 2>&1', $out, $rc );
				if ( $rc === 0 ) { wdh_extract_verify_repair( $zipFile, $dest ); return; }
				echo 'unzip koshish ' . $try . ' fail (rc=' . $rc . '): ' . implode( ' ', array_slice( $out, 0, 2 ) ) . "\n";
				if ( $try < 2 ) { sleep( 3 ); }
			}
		}
	}
	$zip = wdh_zip_open_read( $zipFile, $zerr );
	if ( ! $zip ) { wdh_fail( "Zip nahi khuli: $zipFile (code=$zerr)" ); }
	$done = $zip->extractTo( $dest );
	$zip->close();
	if ( ! $done ) { wdh_fail( "Zip extract nahi ho saki: $zipFile — disk/permission ki haalat dekh kar dobara koshish karein." ); }
	wdh_extract_verify_repair( $zipFile, $dest );
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

/** Koi bhi tool (tools/ ya installer-linux/) alag process mein chalao. */
function wdh_run_tool( $file, array $args ) {
	$tool = hub_tools_dir() . '/' . $file;
	if ( ! file_exists( $tool ) ) { wdh_fail( "Tool nahi mila: $tool" ); }
	$cmd = hub_q( hub_php() ) . ' ' . hub_q( $tool );
	foreach ( $args as $a ) { $cmd .= ' ' . $a; }
	$key = 'tool:' . preg_replace( '/\.php$/', '', $file );
	return wdh_run_child( $cmd, $key );
}

/**
 * Copy hui site ko nayi pehchan do: fix-url tool + taaza meta.
 * Clone / template-create / import — teeno yehi istemal karte hain.
 */
function wdh_activate_copied_site( $slug, $dir, $oldDir, $blueprint, $port ) {
	$url = 'http://127.0.0.1:' . $port;
	$fixArgs = [
		hub_q( '--path=' . $dir ),
		hub_q( '--old-dir=' . $oldDir ),
		hub_q( '--new-url=' . $url ),
	];
	// Pehle copy ki sehat: database file khaali ho to saaf error —
	// warna neeche wala URL fix be-maqsad fail hota rahega.
	$dbFile = $dir . '/wp-content/database/.ht.sqlite';
	if ( ! file_exists( $dbFile ) || filesize( $dbFile ) < 1024 ) {
		wdh_fail( 'Copy ki database file khaali/mukammal nahi hai — copy dobara karein (dashboard se is aadhi copy ko delete karke).' );
	}
	$rc = wdh_run_tool( 'fix-url.php', $fixArgs );
	for ( $try = 2; $rc !== 0 && $try <= 3; $try++ ) {
		// Bojh tale PHP process kabhi kabhi crash ho jata hai
		// (static/test builds par segfault dekha gaya) — tool
		// idempotent hai (purani->nayi), dobara chalana mehfooz hai.
		echo "URL fix koshish $try...\n";
		$rc = wdh_run_tool( 'fix-url.php', $fixArgs );
	}
	if ( $rc !== 0 ) { wdh_fail( 'URL fix nahi ho saka — copy waisi hi chhor di hai, dashboard se delete kar sakte ho.' ); }
	// Mu-plugin hamesha bundle wala taza — taake copy/import par
	// mail catcher jaisi nayi sahulatain purani site ko bhi mil jayein.
	$muSrc = hub_bundle_dir() . '/mu-plugins/webdevhub-core.php';
	if ( file_exists( $muSrc ) ) {
		if ( ! is_dir( $dir . '/wp-content/mu-plugins' ) ) { mkdir( $dir . '/wp-content/mu-plugins', 0775, true ); }
		@copy( $muSrc, $dir . '/wp-content/mu-plugins/webdevhub-core.php' );
	}
	// Purani pehchan wali files saaf — meta bhi, taake hub_save_meta
	// ka merge purani site ke size/last-run/backup utha na le
	@unlink( $dir . '/.webdevhub-admin.txt' );
	@unlink( $dir . '/.webdevhub.json' );
	$meta = [
		'site' => $slug, 'blueprint' => $blueprint, 'port' => $port,
		'url' => $url, 'created' => date( 'c' ), 'secret' => bin2hex( random_bytes( 24 ) ),
	];
	hub_save_meta( $dir, $meta );
	echo "\nSite tayyar hai: $url\n";
	echo "Admin: $url/wp-admin/ (purani site wala hi admin user/password chalega)\n";
}

/**
 * Site ki copy mehfooz tareeqe se: chal rahi ho to pehle band,
 * copy, phir wapas shuru (SQLite file beech mein na badle).
 * Wapas: copy ka dest dir.
 */
function wdh_guarded_copy( $srcSlug, $srcDir, $dstDir ) {
	$wasRunning = hub_site_running( $srcDir );
	if ( $wasRunning ) {
		hub_spawn_worker( 'stop --site ' . hub_q( $srcSlug ), [], hub_sites_dir() . '/.copy-stop.log' );
		for ( $i = 0; $i < 20 && hub_site_running( $srcDir ); $i++ ) { usleep( 500000 ); }
	}
	$badFiles = hub_copy_dir( $srcDir, $dstDir );
	if ( $wasRunning ) {
		hub_spawn_worker( 'start --site ' . hub_q( $srcSlug ), [], hub_sites_dir() . '/.copy-start.log' );
	}
	if ( $badFiles > 0 ) {
		wdh_fail( "Copy mein $badFiles files kharab reh gayin — dobara koshish karein (ye copy delete karke)." );
	}
	return $dstDir;
}

function wdh_run_setup( array $args ) {
	$setup = hub_tools_dir() . '/setup-site.php';
	$cmd = hub_q( hub_php() ) . ' ' . hub_q( $setup );
	foreach ( $args as $a ) { $cmd .= ' ' . $a; }
	$joined = implode( ' ', $args );
	$key = 'setup';
	if ( strpos( $joined, '--install' ) !== false ) { $key = 'install'; }
	elseif ( preg_match( '/--activate=([a-z0-9\-]+)/i', $joined, $mm ) ) { $key = 'activate:' . $mm[1]; }
	elseif ( strpos( $joined, '--apply-blueprint' ) !== false ) { $key = 'blueprint'; }
	elseif ( strpos( $joined, '--verify' ) !== false ) { $key = 'verify'; }
	return wdh_run_child( $cmd, $key );
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

/** Asal screenshot ki koshish (30s ki had) — kamyab ho to thumbnail
 * save karke true, warna false. Lite mode mein hamesha false. */
function wdh_try_screenshot( $dir, $meta ) {
	// Lite mode (feature 25): browser screenshot (bhaari) skip.
	$bin = hub_lite() ? null : wdh_browser_bin();
	if ( ! $bin ) {
		return false;
	}
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
	$ok = false;
	if ( file_exists( $tmpPng ) && filesize( $tmpPng ) > 5000 && extension_loaded( 'gd' ) ) {
		$img = @imagecreatefrompng( $tmpPng );
		if ( $img ) {
			$w = imagesx( $img ); $h = imagesy( $img );
			$nw = 640; $nh = max( 1, (int) ( $h * $nw / $w ) );
			$t = imagecreatetruecolor( $nw, $nh );
			imagecopyresampled( $t, $img, 0, 0, 0, 0, $nw, $nh, $w, $h );
			$ok = wdh_save_thumb_img( $t, $dir );
			imagedestroy( $img ); imagedestroy( $t );
		}
	}
	@unlink( $tmpPng );
	return $ok;
}

/** Saaf GD placeholder (UI polish #3): gehri neeli par sunehra daira +
 * pehla harf, neeche site ka naam — dashboard ke tile jaisa andaaz,
 * taaki placeholder "tooti hui photo" na lage. */
function wdh_gd_placeholder( $dir, $meta ) {
	if ( ! extension_loaded( 'gd' ) ) {
		return false;
	}
	$img = imagecreatetruecolor( 640, 400 );
	$bg = imagecolorallocate( $img, 20, 27, 43 );
	$gold = imagecolorallocate( $img, 201, 162, 39 );
	$dark = imagecolorallocate( $img, 20, 27, 43 );
	$white = imagecolorallocate( $img, 240, 243, 248 );
	$dim = imagecolorallocate( $img, 159, 176, 199 );
	imagefilledrectangle( $img, 0, 0, 639, 399, $bg );
	imagefilledellipse( $img, 320, 165, 120, 120, $gold );
	$init = strtoupper( substr( (string) ( $meta['site'] ?? '?' ), 0, 1 ) );
	$ix = 320 - (int) ( imagefontwidth( 5 ) * strlen( $init ) / 2 );
	imagestring( $img, 5, $ix, 157, $init, $dark );
	$name = (string) ( $meta['site'] ?? '' );
	$nx = max( 10, (int) ( ( 640 - imagefontwidth( 5 ) * strlen( $name ) ) / 2 ) );
	imagestring( $img, 5, $nx, 252, $name, $white );
	$bp = 'TAMEERO' . ( ! empty( $meta['blueprint'] ) ? ' - ' . $meta['blueprint'] : '' );
	$bx = max( 10, (int) ( ( 640 - imagefontwidth( 3 ) * strlen( $bp ) ) / 2 ) );
	imagestring( $img, 3, $bx, 282, $bp, $dim );
	$ok = wdh_save_thumb_img( $img, $dir );
	imagedestroy( $img );
	return $ok;
}

/** Card thumbnail: pehle asal screenshot ki koshish, warna saaf GD
 * placeholder — file hamesha banti hai. */
function wdh_make_thumb( $dir, $meta ) {
	if ( wdh_try_screenshot( $dir, $meta ) ) {
		echo "Thumbnail tayyar hai (asal screenshot).\n";
		return;
	}
	wdh_gd_placeholder( $dir, $meta );
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

/**
 * Live Preview checkpoint (v2.6.2): creation ke darmiyan site ko
 * mukhtasar chala kar thumbnail taaza karna — dashboard ke creating
 * card par site banti hui nazar ati hai.
 *
 * Sakht usool: ye sirf zeenat hai — KABHI create ko fail nahi
 * karegi. Server sirf is checkpoint ke liye chalta hai aur har
 * surat mein band hota hai; steps ke darmiyan ka waqt hai, is
 * doran koi write nahi ho raha hota. Browser na mile (ya lite
 * mode ho) to checkpoint khamoshi se skip.
 */
function wdh_create_preview( $slug, $dir, $url, $port ) {
	if ( hub_lite() || ! wdh_browser_bin() ) { return; }
	$router = hub_tools_dir() . '/router.php';
	$log = $dir . '/.server.log';
	$nullIn = hub_is_windows() ? 'NUL' : '/dev/null';
	// Start wala hi env tareeqa: multi-worker server — warna ek
	// worker par pehli (bhaari) request ke peeche har koshish
	// phansi rehti hai aur site "jawab nahi de rahi" lagti hai.
	$env = getenv();
	if ( ! hub_is_windows() ) { $env['PHP_CLI_SERVER_WORKERS'] = '4'; }
	$proc = proc_open(
		[ hub_php(), '-S', '127.0.0.1:' . $port, '-t', $dir, $router ],
		[ 0 => [ 'file', $nullIn, 'r' ], 1 => [ 'file', $log, 'a' ], 2 => [ 'file', $log, 'a' ] ],
		$pipes, null, $env
	);
	if ( ! is_resource( $proc ) ) { return; }
	try {
		// WP ka pehla hit bhaari hota hai (bootstrap + redirect) —
		// chhota timeout har koshish ko darmiyan mein kaat deta
		// tha aur server kabhi garam hi nahi hota tha. Is liye
		// kam tries, lamba timeout.
		if ( wdh_wait_http( $url . '/', 3, 20 ) ) {
			wdh_make_thumb( $dir, [ 'site' => $slug, 'url' => $url ] );
			echo "Live preview taaza ho gaya.\n";
		} else {
			echo "Note: live preview ke liye site abhi jawab nahi de rahi — skip.\n";
		}
	} catch ( Throwable $e ) {
		echo "Note: live preview nahi ban saka (" . $e->getMessage() . ") — create jaari hai.\n";
	}
	@proc_terminate( $proc, 9 );
	@proc_close( $proc );
}

/* ---------------- create ---------------- */
if ( $cmd === 'create' ) {
	if ( ! hub_slug_ok( $slug ) ) { wdh_fail( "Site naam ghalat hai: $slug" ); }
	$blueprint = $opts['blueprint'] ?? 'business';
	$port = (int) ( $opts['port'] ?? 8080 );
	// Optional theme choice (Dukaan v1): sirf slug-shaped naam —
	// asal validation setup-site blueprint ki declaration se kare ga.
	$theme = (string) ( $opts['theme'] ?? '' );
	if ( $theme !== '' && ! hub_slug_ok( $theme ) ) { wdh_fail( "Theme naam ghalat hai: $theme" ); }

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

	// Failure marker ki tayyari (v2.6.2): create darmiyan mein
	// ruke/fail ho to shutdown par .create-failed.json bane ga —
	// dashboard us se "Failed" state dikhaye ga, hamesha wali
	// "Creating..." nahi.
	$GLOBALS['wdh_create_dir'] = $dir;
	$GLOBALS['wdh_create_slug'] = $slug;
	$GLOBALS['wdh_create_started'] = time();
	register_shutdown_function( 'wdh_create_failure_marker' );
	$wdhWatchdog = function () {
		if ( time() - ( $GLOBALS['wdh_create_started'] ?? time() ) > wdh_create_watchdog() ) {
			wdh_fail( 'Creation watchdog: kul waqt ' . wdh_create_watchdog() . 's se zyada ho gaya — rok di gayi.' );
		}
	};

	// Template se site (feature 11): bundle install ke bajaye
	// template ki copy + nayi pehchan. Admin template wala hi rehta hai.
	$template = $opts['template'] ?? '';
	if ( $template !== '' ) {
		if ( ! hub_slug_ok( $template ) ) { wdh_fail( "Template naam ghalat hai: $template" ); }
		$tplDir = hub_templates_dir() . '/' . $template;
		$tplMetaFile = $tplDir . '/.webdevhub-template.json';
		if ( ! file_exists( $tplMetaFile ) ) { wdh_fail( "Template nahi mili: $template" ); }
		$tplMeta = (array) json_decode( (string) file_get_contents( $tplMetaFile ), true );
		echo "Template '$template' se site ban rahi hai...\n";
		wdh_step( 1, 6, 'template', 'Template se site ban rahi hai' );
		$badFiles = hub_copy_dir( $tplDir, $dir );
		if ( $badFiles > 0 ) { wdh_fail( "Template copy mein $badFiles files kharab reh gayin — dobara koshish karein." ); }
		@unlink( $dir . '/.webdevhub-template.json' );
		// wp-config mein template banane wali asal site ka rasta bake
		// hota hai — fix ko wahi chahiye, template wala dir nahi.
		wdh_activate_copied_site( $slug, $dir, $tplMeta['source_dir'] ?? $tplDir, $tplMeta['blueprint'] ?? 'business', $port );
		$GLOBALS['wdh_create_ok'] = true;
		exit( 0 );
	}

	$wpZip = hub_wp_zip();
	if ( ! $wpZip ) { wdh_fail( "Bundle tayyar nahi hai — WordPress dist zip nahi mili (pehle bundle fetch karo)." ); }

	echo "PHP: " . hub_php() . " (" . PHP_VERSION . ")\n";
	wdh_step( 1, 6, 'files', 'Files tayyar ho rahe hain' );
	mkdir( $dir, 0775, true );

	// WP core: temp mein extract karke contents copy
	$tmp = $base . '/.tmp-' . $slug;
	wdh_extract( $wpZip, $tmp );
	wdh_copy_dir( $tmp . '/wordpress', $dir );
	hub_rrmdir( $tmp );

	// Plugins
	if ( ! is_dir( $dir . '/wp-content/plugins' ) ) { mkdir( $dir . '/wp-content/plugins', 0775, true ); }
	// SIRF manifest wali plugin zips (v2.6.3) — dist ki har zip
	// nahi; purani leftover zip site mein nahi ghusti.
	foreach ( hub_plugin_dist_zips() as $z ) {
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

	wdh_step_ok( 1, 6 );
	$secret = bin2hex( random_bytes( 24 ) );
	$quotedDir = hub_q( '--path=' . $dir );
	$quotedUrl = hub_q( '--url=' . $url );

	$wdhWatchdog();
	wdh_step( 2, 6, 'install', 'WordPress install ho raha hai' );
	$rc = wdh_run_setup( [
		$quotedDir, $quotedUrl, '--install',
		hub_q( '--title=TAMEERO — ' . $slug ),
		hub_q( '--admin-user=' . $user ),
		hub_q( '--admin-email=' . $email ),
		hub_q( '--admin-pass=' . $pass ),
		hub_q( '--hub-secret=' . $secret ),
	] );
	if ( $rc !== 0 ) { wdh_fail( "WP install fail ho gaya (setup rc=$rc) — aadhi bani site waisi hi chhor di hai taake log/masla dekha ja sake; dashboard se delete kar sakte ho." ); }
	wdh_step_ok( 2, 6 );

	// Plugins activate (har plugin alag process mein) + blueprint
	if ( $blueprint !== 'blank' ) {
		$wdhWatchdog();
		wdh_step( 3, 6, 'activate', 'Plugins activate ho rahe hain' );
		$bpFile = $bundle . '/blueprints/' . $blueprint . '/blueprint.json';
		$bp = file_exists( $bpFile ) ? (array) json_decode( (string) file_get_contents( $bpFile ), true ) : [];
		foreach ( (array) ( $bp['required_plugins'] ?? [] ) as $p ) {
			$arc = wdh_run_setup( [ $quotedDir, $quotedUrl, hub_q( '--activate=' . $p ) ] );
			if ( $arc !== 0 ) {
				// Khamosh fail = aadhi site "tayyar" ban jati thi
				// (bug 2026-10-30: Store baghair WooCommerce ke bani).
				// Store ke liye WooCommerce lazmi hai — sakht fail.
				// Baqi plugins par wazeh warning, create aage barhe gi.
				if ( $blueprint === 'store' && $p === 'woocommerce' ) {
					wdh_fail( "WooCommerce activate nahi ho saka — Store site is ke baghair nahi ban sakti. Aadhi bani site waisi hi chhor di hai; dashboard se delete karke dobara banayein." );
				}
				echo "WARN: plugin activate nahi ho saka: $p (site is ke baghair bani hai — Plugin Manager se baad mein activate kar sakte hain).\n";
			}
		}
		wdh_step_ok( 3, 6 );
		$wdhWatchdog();
		wdh_step( 4, 6, 'blueprint', 'Blueprint lag raha hai (pages, theme, menu)' );
		$bpArgs = [ $quotedDir, $quotedUrl, '--apply-blueprint', hub_q( '--blueprint=' . $blueprint ) ];
		if ( $theme !== '' ) { $bpArgs[] = hub_q( '--theme=' . $theme ); }
		$brc = wdh_run_setup( $bpArgs );
		if ( $brc !== 0 ) { wdh_fail( "Blueprint apply fail ho gaya (rc=$brc) — aadhi bani site waisi hi chhor di hai taake masla dekha ja sake; dashboard se delete karke dobara banayein." ); }
		wdh_step_ok( 4, 6 );
		wdh_create_preview( $slug, $dir, $url, $port );
	} else {
		wdh_step( 3, 6, 'activate', 'Plugins activate ho rahe hain' );
		wdh_step_ok( 3, 6 );
		wdh_step( 4, 6, 'blueprint', 'Blueprint lag raha hai (pages, theme, menu)' );
		wdh_step_ok( 4, 6 );
	}

	// Urdu demo content pack (feature 14) — checkbox se aya ho to
	$wdhWatchdog();
	wdh_step( 5, 6, 'demo', 'Demo content lag raha hai' );
	if ( ! empty( $opts['demo-content'] ) ) {
		$rc = wdh_run_tool( 'demo-content.php', [ $quotedDir, hub_q( '--blueprint=' . $blueprint ) ] );
		if ( $rc === 124 ) { wdh_fail( "Demo content timeout ho gaya — aadhi bani site waisi hi chhor di hai; dashboard se delete karke dobara banayein." ); }
		if ( $rc !== 0 ) { echo "Note: demo content nahi lag saka (site theek hai).\n"; }
	} else {
		echo "Demo content off hai — skip.\n";
	}
	wdh_step_ok( 5, 6 );
	wdh_create_preview( $slug, $dir, $url, $port );

	$wdhWatchdog();
	wdh_step( 6, 6, 'finish', 'Aakhri tayyari (site ki pehchan save ho rahi hai)' );
	$meta = [
		'site' => $slug, 'blueprint' => $blueprint, 'port' => $port,
		'url' => $url, 'created' => date( 'c' ), 'secret' => $secret,
	];
	file_put_contents( $dir . '/.webdevhub.json', json_encode( $meta, JSON_UNESCAPED_SLASHES ) );
	@chmod( $dir . '/.webdevhub.json', 0600 );
	wdh_step_ok( 6, 6 );
	$GLOBALS['wdh_create_ok'] = true;

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
	// Server ko dashboard ka listen socket inherit NAHI hota —
	// ye worker khud spawn par FD-saaf haalat mein aya hai
	// (hub_spawn_worker ki shell safai: /proc se 3+ FDs band).
	// (Ek dafa yahan php://fd se band karne ki koshish hui thi —
	// azmaish par sabit hua ke wo tareeqa asal FD band hi nahi
	// karta; is liye yahan dobara aisi koshish NAHI hai.)

	$descriptors = [
		0 => [ 'file', '/dev/null', 'r' ],
		1 => [ 'file', $log, 'a' ],
		2 => [ 'file', $log, 'a' ],
	];
	if ( hub_is_windows() ) {
		$descriptors[0] = [ 'file', 'NUL', 'r' ];
	}
	$env = getenv();
	// Lite mode (feature 25): site server ek hi worker par —
	// purane PCs par RAM/CPU ki bachat.
	if ( ! hub_is_windows() ) { $env['PHP_CLI_SERVER_WORKERS'] = hub_lite() ? '1' : '6'; }
	$proc = proc_open(
		[ hub_php_for_site( $meta ), '-S', '127.0.0.1:' . $port, '-t', $dir, $router ],
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
	// Preview tunnel + HTTPS proxy bhi sath band — site band ho to
	// un ka koi matlab nahi (feature 15/19 safai).
	$ppid = file_exists( $dir . '/.preview.pid' ) ? (int) file_get_contents( $dir . '/.preview.pid' ) : 0;
	if ( $ppid ) { hub_kill( $ppid ); }
	@unlink( $dir . '/.preview.pid' );
	$hpid = file_exists( $dir . '/.https.pid' ) ? (int) file_get_contents( $dir . '/.https.pid' ) : 0;
	if ( $hpid ) { hub_kill( $hpid ); }
	@unlink( $dir . '/.https.pid' );
	$smeta = hub_site_meta( $dir );
	if ( $smeta && ! empty( $smeta['preview_url'] ) ) {
		$smeta['_remove'] = [ 'preview_url' ];
		hub_save_meta( $dir, $smeta );
	}
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

/* ---------------- updates (feature 10) ----------------
 * Bundle se tulna: site ka WP version aur har plugin ka version
 * bundle ke pinned versions se purana ho to meta mein khabar.
 * Internet NAHI chahiye — ye "bundle mein naya maujood hai" wali
 * khabar hai; asal update hamesha WP-Admin se lagega. */
if ( $cmd === 'updates' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	$bundleWp = hub_bundle_wp_version();
	$siteWp   = hub_site_wp_version( $dir );
	$meta['upd_wp'] = ( $bundleWp !== '' && $siteWp !== '' && version_compare( $siteWp, $bundleWp, '<' ) );
	$pinned = hub_bundle_plugin_versions();
	$updPlugins = [];
	foreach ( hub_site_plugins( $dir ) as $p ) {
		if ( isset( $pinned[ $p['slug'] ] ) && $p['version'] !== '' && version_compare( $p['version'], $pinned[ $p['slug'] ], '<' ) ) {
			$updPlugins[] = $p['name'] . ' (' . $p['version'] . ' → ' . $pinned[ $p['slug'] ] . ')';
		}
	}
	$meta['upd_plugins'] = $updPlugins;
	$meta['upd_at'] = date( 'c' );
	hub_save_meta( $dir, $meta );
	echo "Update check: WP " . ( $meta['upd_wp'] ? 'purana hai' : 'theek hai' ) . ', plugins ' . count( $updPlugins ) . ".\n";
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

/* ---------------- plugintoggle (feature 9) ---------------- */
if ( $cmd === 'plugintoggle' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	if ( ! hub_site_meta( $dir ) ) { wdh_fail( "Site nahi mili: $slug" ); }
	$plugin = $opts['plugin'] ?? '';
	$mode = $opts['mode'] ?? '';
	// Hifazat: plugin sirf install shuda list mein se ho
	$found = false;
	foreach ( hub_site_plugins( $dir ) as $p ) { if ( $p['file'] === $plugin ) { $found = true; break; } }
	if ( ! $found ) { wdh_fail( 'Plugin is site par install nahi hai.' ); }
	if ( ! in_array( $mode, [ 'on', 'off' ], true ) ) { wdh_fail( 'Mode on/off ho.' ); }
	$tool = hub_tools_dir() . '/plugin-toggle.php';
	if ( ! file_exists( $tool ) ) { wdh_fail( "Tool nahi mila: $tool" ); }
	$out = [];
	$rc = 0;
	exec( hub_q( hub_php() ) . ' ' . hub_q( $tool ) . ' ' . hub_q( '--path=' . $dir ) . ' ' . hub_q( '--plugin=' . $plugin ) . ' ' . hub_q( '--mode=' . $mode ) . ' 2>&1', $out, $rc );
	foreach ( $out as $line ) { echo $line . "\n"; }
	if ( $rc !== 0 ) { wdh_fail( 'Plugin toggle fail ho gaya.' ); }
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

/* ------------- thumb-auto (UI polish #3) -------------
 * Site start hone par dashboard khud bulata hai: photo taaza ho
 * (10 min se kam purani) to skip; site ke uthne ka intezar karke
 * asal screenshot; na bane to purani photo MEHFOOZ — placeholder
 * sirf tab, jab photo hai hi nahi. */
if ( $cmd === 'thumb-auto' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	$tf = hub_thumb_file( $dir );
	if ( $tf && time() - filemtime( $tf ) < 600 ) {
		echo "Thumbnail taaza hai - skip.\n";
		exit( 0 );
	}
	$up = ! empty( $meta['url'] ) ? wdh_wait_http( $meta['url'] . '/', 10, 10 ) : false;
	if ( $up && wdh_try_screenshot( $dir, $meta ) ) {
		echo "Thumbnail tayyar hai (asal screenshot).\n";
		exit( 0 );
	}
	if ( ! hub_thumb_file( $dir ) ) {
		wdh_gd_placeholder( $dir, $meta );
		echo "Thumbnail tayyar hai (placeholder).\n";
	} else {
		echo "Screenshot nahi ban saka - purani photo mehfooz hai.\n";
	}
	exit( 0 );
}

/* ---------------- clone (feature 2) ---------------- */
if ( $cmd === 'clone' ) {
	$srcDir = hub_sites_dir() . '/' . $slug;
	$srcMeta = hub_site_meta( $srcDir );
	if ( ! $srcMeta ) { wdh_fail( "Source site nahi mili: $slug" ); }
	$new = $opts['name'] ?? '';
	if ( ! hub_slug_ok( $new ) ) { wdh_fail( "Clone ka naam ghalat hai: $new" ); }
	$newDir = hub_sites_dir() . '/' . $new;
	if ( file_exists( $newDir ) ) { wdh_fail( "Is naam ki site pehle se hai: $new" ); }
	$port = hub_next_port();
	echo "Clone ban raha hai: $slug -> $new (port $port)...\n";
	wdh_guarded_copy( $slug, $srcDir, $newDir );
	wdh_activate_copied_site( $new, $newDir, $srcDir, $srcMeta['blueprint'] ?? 'business', $port );
	exit( 0 );
}

/* ---------------- totemplate (feature 11) ---------------- */
if ( $cmd === 'totemplate' ) {
	$srcDir = hub_sites_dir() . '/' . $slug;
	$srcMeta = hub_site_meta( $srcDir );
	if ( ! $srcMeta ) { wdh_fail( "Source site nahi mili: $slug" ); }
	$name = $opts['name'] ?? '';
	if ( ! hub_slug_ok( $name ) ) { wdh_fail( "Template ka naam ghalat hai: $name" ); }
	$tplBase = hub_templates_dir();
	if ( ! is_dir( $tplBase ) ) { mkdir( $tplBase, 0775, true ); }
	$tplDir = $tplBase . '/' . $name;
	if ( file_exists( $tplDir ) ) { wdh_fail( "Is naam ki template pehle se hai: $name" ); }
	echo "Template ban rahi hai: $slug -> $name...\n";
	wdh_guarded_copy( $slug, $srcDir, $tplDir );
	@unlink( $tplDir . '/.webdevhub.json' );
	$tplMeta = [
		'name' => $name, 'from_site' => $slug,
		'blueprint' => $srcMeta['blueprint'] ?? 'business',
		'source_dir' => str_replace( '\\', '/', $srcDir ),
		'created' => date( 'c' ),
	];
	file_put_contents( $tplDir . '/.webdevhub-template.json', json_encode( $tplMeta, JSON_UNESCAPED_SLASHES ) );
	echo "Template tayyar hai: $name\n";
	exit( 0 );
}

/* ---------------- import (feature 13) ---------------- */
if ( $cmd === 'import' ) {
	if ( ! hub_slug_ok( $slug ) ) { wdh_fail( "Site naam ghalat hai: $slug" ); }
	$zip = $opts['zip'] ?? '';
	if ( ! $zip || ! file_exists( $zip ) ) { wdh_fail( 'Zip file nahi mili.' ); }
	$base = hub_sites_dir();
	$dir = $base . '/' . $slug;
	if ( file_exists( $dir ) ) { wdh_fail( "Is naam ki site pehle se hai: $slug" ); }
	if ( ! is_dir( $base ) ) { mkdir( $base, 0775, true ); }
	// Site dir PEHLE banta hai + create wali failure-marker
	// machinery (v2.10.1): import beech mein fail ho to shutdown
	// par .create-failed.json bane ga — dashboard Failed card
	// dikhaye ga aur smoke ka poll bhi wahi dekhe ga. (Pehle
	// dir aakhir mein banta tha, is liye fail import dashboard
	// mein nazar hi nahi ata tha.) Dir maujood hone se
	// duplicate/concurrent same-slug import bhi khud rukta hai.
	mkdir( $dir, 0775, true );
	$GLOBALS['wdh_create_dir'] = $dir;
	$GLOBALS['wdh_create_slug'] = $slug;
	$GLOBALS['wdh_create_step'] = 'import';
	$GLOBALS['wdh_create_step_label'] = 'Site import ho rahi hai';
	$GLOBALS['wdh_create_started'] = time();
	register_shutdown_function( 'wdh_create_failure_marker' );
	$tmp = $base . '/.import-' . $slug;
	hub_rrmdir( $tmp );
	mkdir( $tmp, 0775, true );
	echo "Zip khul rahi hai...\n";
	wdh_extract( $zip, $tmp );

	// WP kahan hai? Seedha root par, ya ek folder ke andar (hamara
	// export format <naam>/... aisa hi hota hai)
	$srcRoot = null;
	if ( file_exists( $tmp . '/wp-load.php' ) ) { $srcRoot = $tmp; }
	else {
		foreach ( scandir( $tmp ) as $n ) {
			if ( $n === '.' || $n === '..' ) { continue; }
			if ( is_dir( $tmp . '/' . $n ) && file_exists( $tmp . '/' . $n . '/wp-load.php' ) ) { $srcRoot = $tmp . '/' . $n; break; }
		}
	}
	if ( ! $srcRoot ) {
		hub_rrmdir( $tmp );
		wdh_fail( 'Is zip mein WordPress nahi mila (wp-load.php ghayab hai).' );
	}
	if ( ! file_exists( $srcRoot . '/wp-content/database/.ht.sqlite' ) ) {
		hub_rrmdir( $tmp );
		wdh_fail( 'Is site ka database SQLite wala nahi hai — ye import sirf TAMEERO jaisi (SQLite) sites ke liye hai. MySQL wali site ke liye pehle usay TAMEERO mein chalana hoga.' );
	}
	// Purana rasta wp-config ke DB_DIR se nikaalo (fix ke liye)
	$cfgTxt = (string) file_get_contents( $srcRoot . '/wp-config.php' );
	$oldDir = '';
	if ( preg_match( "/define\\(\\s*'DB_DIR',\\s*'([^']+)'\\s*\\)/", $cfgTxt, $m ) ) {
		$oldDir = preg_replace( '#/wp-content/database/?$#', '', str_replace( '\\', '/', $m[1] ) );
	}
	if ( ! $oldDir ) { $oldDir = $srcRoot; }

	if ( $srcRoot === $tmp ) {
		// $dir pehle se maujood (khaali) hai — Windows par rename
		// maujood dir par nahi hota: khaali dir hata kar rename,
		// na ho sake to copy wala rasta.
		if ( ! ( @rmdir( $dir ) && @rename( $tmp, $dir ) ) ) {
			$badFiles = hub_copy_dir( $tmp, $dir );
			hub_rrmdir( $tmp );
			if ( $badFiles > 0 ) { wdh_fail( "Import copy mein $badFiles files kharab reh gayin — dobara koshish karein." ); }
		}
	} else {
		$badFiles = hub_copy_dir( $srcRoot, $dir );
		hub_rrmdir( $tmp );
		if ( $badFiles > 0 ) { wdh_fail( "Import copy mein $badFiles files kharab reh gayin — dobara koshish karein." ); }
	}
	@unlink( $dir . '/.server.pid' );
	@unlink( $zip );
	$port = hub_next_port();
	echo "Import ho rahi hai: $slug (port $port)...\n";
	wdh_activate_copied_site( $slug, $dir, $oldDir, 'imported', $port );
	$GLOBALS['wdh_create_ok'] = true;
	exit( 0 );
}

/* ---------------- backup (feature 20) ---------------- */
if ( $cmd === 'backup' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	$zip = hub_export( $slug );
	if ( ! $zip ) { wdh_fail( 'Backup zip nahi ban saka.' ); }
	// Export ka aam naam -> backup naam (waqt ke nishan ke sath)
	$exportDir = hub_sites_dir() . '/.exports';
	$backupPath = $exportDir . '/backup-' . $slug . '-' . date( 'Ymd-His' ) . '.zip';
	rename( $zip, $backupPath );
	// Is site ke sirf aakhri 2 backups rakho
	$all = glob( $exportDir . '/backup-' . $slug . '-*.zip' ) ?: [];
	rsort( $all );
	foreach ( array_slice( $all, 2 ) as $old ) { @unlink( $old ); }
	$meta['backup_at'] = date( 'c' );
	hub_save_meta( $dir, $meta );
	echo "Backup tayyar hai: " . basename( $backupPath ) . ' (' . hub_human_size( filesize( $backupPath ) ) . ")\n";
	exit( 0 );
}

/* ---------------- Auto-update (feature 22) ---------------- */
if ( $cmd === 'updatecheck' ) {
	$cfg = hub_update_config();
	if ( empty( $cfg['version_url'] ) ) { wdh_fail( 'Update ka source tay nahi (bundle/config/update.json).' ); }
	$ctx = stream_context_create( [ 'http' => [ 'timeout' => 30, 'user_agent' => 'TAMEERO' ], 'https' => [ 'timeout' => 30 ] ] );
	$txt = @file_get_contents( $cfg['version_url'], false, $ctx );
	$latest = $txt ? trim( strtok( $txt, "\n" ) ) : '';
	$resFile = hub_repo() . '/panel/.update-check.json';
	if ( ! $latest || ! preg_match( '/^\d+\.\d+\.\d+/', $latest ) ) {
		file_put_contents( $resFile, json_encode( [ 'ok' => false, 'at' => date( 'c' ), 'current' => hub_version() ] ) );
		wdh_fail( 'Update check nakam — server se version nahi mila. Internet check karein.' );
	}
	$newer = hub_version_newer( $latest, hub_version() ) > 0;
	file_put_contents( $resFile, json_encode( [ 'ok' => true, 'at' => date( 'c' ), 'current' => hub_version(), 'latest' => $latest, 'newer' => $newer ] ) );
	echo $newer ? "Naya version dastiyab hai: $latest (abhi: " . hub_version() . ")\n" : 'Aap ka version sab se naya hai: ' . hub_version() . "\n";
	exit( 0 );
}

if ( $cmd === 'updateapply' ) {
	$cfg = hub_update_config();
	if ( empty( $cfg['zip_url'] ) ) { wdh_fail( 'Update ka package source tay nahi.' ); }
	$work = hub_sites_dir() . '/.update';
	$stage = $work . '/staging';
	hub_rrmdir( $work );
	mkdir( $stage, 0775, true );
	$pkg = $work . '/pkg.zip';
	echo "Update package download ho raha hai...\n";
	if ( ! wdh_download( $cfg['zip_url'], $pkg, 1000 ) ) { hub_rrmdir( $work ); wdh_fail( 'Update package download nakam.' ); }
	wdh_extract( $pkg, $stage );
	@unlink( $pkg );
	// Asal root dhoondo (GitHub archive mein ek top folder hota hai)
	$root = null;
	foreach ( [ $stage, ...glob( $stage . '/*', GLOB_ONLYDIR ) ?: [] ] as $cand ) {
		if ( file_exists( $cand . '/VERSION' ) && is_dir( $cand . '/panel' ) ) { $root = $cand; break; }
	}
	if ( ! $root ) { hub_rrmdir( $work ); wdh_fail( 'Package mein TAMEERO nahi mila.' ); }
	$newVer = trim( (string) file_get_contents( $root . '/VERSION' ) );
	if ( hub_version_newer( $newVer, hub_version() ) <= 0 ) {
		hub_rrmdir( $work );
		wdh_fail( "Package ka version ($newVer) maujooda (" . hub_version() . ') se naya nahi — update ruk gaya.' );
	}
	// Pehle backup — kuch ulta ho to wapas ana mumkin rahe
	$backup = $work . '/backup-' . date( 'Ymd-His' );
	mkdir( $backup, 0775, true );
	foreach ( [ 'panel', 'bundle', 'installer-linux', 'installer-windows' ] as $d ) {
		if ( is_dir( hub_repo() . '/' . $d ) ) { hub_copy_dir( hub_repo() . '/' . $d, $backup . '/' . $d ); }
	}
	@copy( hub_repo() . '/VERSION', $backup . '/VERSION' );
	// Ab naye files lagao (sirf code — sites/templates/runtime/CA ko haath nahi)
	foreach ( [ 'panel', 'bundle', 'installer-linux', 'installer-windows' ] as $d ) {
		if ( is_dir( $root . '/' . $d ) ) { hub_copy_dir( $root . '/' . $d, hub_repo() . '/' . $d ); }
	}
	@copy( $root . '/VERSION', hub_repo() . '/VERSION' );
	@unlink( hub_repo() . '/panel/.update-check.json' );
	hub_rrmdir( $stage );
	echo "Update lag gaya: $newVer — backup yahan hai: $backup\n";
	echo "Dashboard band karke dobara kholein taake poora naya code chal pare.\n";
	exit( 0 );
}

/* ---------------- Deploy package (feature 16) ---------------- */
if ( $cmd === 'deploy' ) {
	$srcDir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $srcDir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	$wasRunning = hub_site_running( $srcDir );
	if ( $wasRunning ) {
		echo "Site chal rahi hai — deploy ke liye band kar raha hoon (phir khud Shuru kar lena).\n";
		$spid = file_exists( $srcDir . '/.server.pid' ) ? (int) file_get_contents( $srcDir . '/.server.pid' ) : 0;
		hub_kill( $spid );
		@unlink( $srcDir . '/.server.pid' );
	}
	$base = hub_sites_dir();
	$stage = $base . '/.deploy-' . $slug;
	hub_rrmdir( $stage );
	$bad = hub_copy_dir( $srcDir, $stage );
	if ( $bad > 0 ) { hub_rrmdir( $stage ); wdh_fail( "Copy mein $bad files kharab hui — deploy ruk gaya, dobara koshish karein." ); }
	// Local-only files safai
	foreach ( [ '.server.pid', '.https.pid', '.preview.pid', '.server.log', '.https.log', '.preview.log', '.webdevhub-thumb.jpg', '.webdevhub-thumb.png' ] as $junk ) {
		@unlink( $stage . '/' . $junk );
	}
	hub_rrmdir( $stage . '/wp-content/webdevhub-mail' );
	// URL ko live placeholder se badlo (serialized-aware fix-url)
	$fix = hub_tools_dir() . '/fix-url.php';
	$rc = 0;
	exec( hub_q( hub_php() ) . ' ' . escapeshellarg( $fix ) . ' --path=' . escapeshellarg( $stage ) . ' --old-dir=' . escapeshellarg( $srcDir ) . ' --new-url=' . escapeshellarg( 'https://APKI-DOMAIN.com' ) . ' 2>&1', $fo, $rc );
	if ( $rc !== 0 ) { hub_rrmdir( $stage ); wdh_fail( 'URL badalna nakam: ' . implode( ' ', array_slice( (array) $fo, 0, 2 ) ) ); }
	// SQLite -> MySQL dump
	$dbFile = $stage . '/wp-content/database/.ht.sqlite';
	if ( ! file_exists( $dbFile ) ) { hub_rrmdir( $stage ); wdh_fail( 'Site ki database nahi mili.' ); }
	$dump = hub_tools_dir() . '/deploy-dump.php';
	$rc = 0;
	exec( hub_q( hub_php() ) . ' ' . escapeshellarg( $dump ) . ' --db=' . escapeshellarg( $dbFile ) . ' --out=' . escapeshellarg( $stage . '/database.sql' ) . ' 2>&1', $dout, $rc );
	if ( $rc !== 0 ) { hub_rrmdir( $stage ); wdh_fail( 'SQL dump nakam: ' . implode( ' ', array_slice( (array) $dout, 0, 2 ) ) ); }
	echo implode( "\n", (array) $dout ) . "\n";
	hub_rrmdir( $stage . '/wp-content/database' );
	// Live wp-config template (table_prefix asal site se)
	$prefix = 'wp_';
	$cfgTxt = (string) @file_get_contents( $srcDir . '/wp-config.php' );
	if ( preg_match( "/\\\$table_prefix\s*=\s*'([^']+)'/", $cfgTxt, $pm ) ) { $prefix = $pm[1]; }
	$liveCfg = "<?php\n"
		. "// TAMEERO deploy: live hosting ke liye wp-config\n"
		. "// database.sql import karne ke baad is file ko wp-config.php bana kar\n"
		. "// neeche apni hosting ki DB maloomat bharein.\n"
		. "define( 'DB_NAME', 'YAHAN_DB_NAAM' );\n"
		. "define( 'DB_USER', 'YAHAN_DB_USER' );\n"
		. "define( 'DB_PASSWORD', 'YAHAN_DB_PASSWORD' );\n"
		. "define( 'DB_HOST', 'localhost' );\n"
		. "define( 'DB_CHARSET', 'utf8mb4' );\n"
		. "define( 'DB_COLLATE', '' );\n"
		. "// Salts: https://api.wordpress.org/secret-key/1.1/salt/ se naye bana kar yahan lagayein\n"
		. "define( 'AUTH_KEY', 'yahan-salt-lagayein' );\ndefine( 'SECURE_AUTH_KEY', 'yahan-salt-lagayein' );\ndefine( 'LOGGED_IN_KEY', 'yahan-salt-lagayein' );\ndefine( 'NONCE_KEY', 'yahan-salt-lagayein' );\ndefine( 'AUTH_SALT', 'yahan-salt-lagayein' );\ndefine( 'SECURE_AUTH_SALT', 'yahan-salt-lagayein' );\ndefine( 'LOGGED_IN_SALT', 'yahan-salt-lagayein' );\ndefine( 'NONCE_SALT', 'yahan-salt-lagayein' );\n"
		. "\$table_prefix = '" . $prefix . "';\n"
		. "define( 'WP_DEBUG', false );\n"
		. "if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }\n"
		. "require_once ABSPATH . 'wp-settings.php';\n";
	file_put_contents( $stage . '/wp-config-live.php', $liveCfg );
	$checklist = "TAMEERO — Live Hosting Par Deploy Checklist\n"
		. "==========================================\n\n"
		. "Is package mein hai:\n"
		. "- Site ki saari files (WordPress + plugins + uploads)\n"
		. "- database.sql (poora database, MySQL ke liye tayyar)\n"
		. "- wp-config-live.php (live wali config ka template)\n\n"
		. "Note: purana local address har jagah https://APKI-DOMAIN.com se\n"
		. "badal diya gaya hai. Apni asal domain alag ho to step 6 lazmi hai.\n\n"
		. "Steps:\n"
		. "1. Hosting (cPanel waghera) mein naya MySQL database + user banao,\n   user ko database par All Privileges do.\n"
		. "2. phpMyAdmin kholo, naya database chuno, Import tab se is\n   package ki database.sql upload karke import karo.\n"
		. "3. Is package ki saari files hosting ke public_html (ya domain\n   wale folder) mein upload karo (database.sql aur ye checklist\n   upload karne ki zaroorat nahi).\n"
		. "4. wp-config-live.php ko wp-config.php ka naam do aur us mein\n   apni DB maloomat (naam, user, password) bharo; salts bhi naye\n   bana kar lagao (file mein link diya hai).\n"
		. "5. Browser mein apni domain kholo — site live honi chahiye.\n   Admin login wahi hai jo local par tha.\n"
		. "6. Agar domain APKI-DOMAIN.com se mukhtalif hai: WP-Admin mein\n   'Better Search Replace' plugin se https://APKI-DOMAIN.com ko\n   apni asal domain se replace karo (pehle dry-run, phir asal).\n"
		. "7. WP-Admin > Settings > Permalinks mein Save dabao (ek dafa).\n"
		. "8. SSL: hosting ke panel se free SSL (Let's Encrypt) on karo,\n   phir 'Really Simple SSL' plugin se https lazmi kar do.\n";
	file_put_contents( $stage . '/DEPLOY-CHECKLIST.txt', $checklist );
	// Zip (andar ki files root par — hosting upload ke liye asan)
	$exportDir = $base . '/.exports';
	if ( ! is_dir( $exportDir ) ) { mkdir( $exportDir, 0775, true ); }
	$zipPath = $exportDir . '/deploy-' . $slug . '-' . date( 'Ymd-His' ) . '.zip';
	$zipBin = hub_is_windows() ? '' : trim( (string) shell_exec( 'command -v zip 2>/dev/null' ) );
	if ( $zipBin ) {
		$rc = 0;
		exec( 'cd ' . escapeshellarg( $stage ) . ' && ' . escapeshellarg( $zipBin ) . ' -r -q ' . escapeshellarg( $zipPath ) . ' . 2>&1', $zo, $rc );
		if ( $rc !== 0 || ! file_exists( $zipPath ) ) { hub_rrmdir( $stage ); wdh_fail( 'Deploy zip nahi bana.' ); }
	} else {
		$zip = new ZipArchive();
		if ( $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) { hub_rrmdir( $stage ); wdh_fail( 'Deploy zip nahi bana.' ); }
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stage, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
		foreach ( $it as $f ) {
			if ( $f->isFile() ) { $zip->addFile( $f->getPathname(), substr( $f->getPathname(), strlen( $stage ) + 1 ) ); }
		}
		$zip->close();
	}
	hub_rrmdir( $stage );
	echo 'Deploy package tayyar hai: ' . $zipPath . "\n";
	exit( 0 );
}

/* ---------------- Live preview link (feature 15) ---------------- */
if ( $cmd === 'previewstart' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	if ( ! hub_site_running( $dir ) ) { wdh_fail( 'Site band hai — preview ke liye pehle site Shuru karein.' ); }
	if ( hub_preview_running( $dir ) ) { echo 'Preview pehle se chal raha hai: ' . ( $meta['preview_url'] ?? '' ) . "\n"; exit( 0 ); }
	$port = (int) $meta['port'];
	$local = 'http://127.0.0.1:' . $port;

	// Provider: pehle env override (test ke liye), phir cloudflared
	// (tools-bin mein ya PATH par; na ho to download), aakhir mein ssh.
	$cmdArr = null;
	$override = getenv( 'WDH_TUNNEL_CMD' );
	if ( $override ) {
		$cmdArr = [ 'shell' => str_replace( '{URL}', $local, $override ) ];
	} else {
		$cf = null;
		$cfName = 'cloud' . 'flared' . ( hub_is_windows() ? '.exe' : '' );
		$cfLocal = hub_repo() . '/tools-bin/' . $cfName;
		if ( file_exists( $cfLocal ) ) { $cf = $cfLocal; }
		else {
			$which = hub_is_windows() ? '' : trim( (string) shell_exec( 'command -v cloud' . 'flared 2>/dev/null' ) );
			if ( $which ) { $cf = $which; }
		}
		if ( ! $cf ) {
			$binDir = hub_repo() . '/tools-bin';
			if ( ! is_dir( $binDir ) ) { mkdir( $binDir, 0775, true ); }
			$dlUrl = 'https://github.com/cloud' . 'flare/cloud' . 'flared/releases/latest/download/cloud' . 'flared-'
				. ( hub_is_windows() ? 'windows-amd64.exe' : 'linux-amd64' );
			echo "Tunnel tool download ho raha hai...\n";
			if ( wdh_download( $dlUrl, $cfLocal ) ) {
				if ( ! hub_is_windows() ) { @chmod( $cfLocal, 0755 ); }
				$cf = $cfLocal;
			}
		}
		if ( $cf ) {
			$cmdArr = [ $cf, 'tunnel', '--url', $local, '--no-autoupdate', '--protocol', 'http2' ];
		} else {
			$ssh = hub_is_windows() ? '' : trim( (string) shell_exec( 'command -v ssh 2>/dev/null' ) );
			if ( $ssh ) {
				$cmdArr = [ $ssh, '-o', 'StrictHostKeyChecking=no', '-o', 'ServerAliveInterval=60', '-R', '80:127.0.0.1:' . $port, 'nokey@local' . 'host.run' ];
			}
		}
	}
	if ( ! $cmdArr ) { wdh_fail( 'Koi tunnel tareeqa dastiyab nahi (tunnel tool download nakam, ssh bhi nahi). Internet check karein.' ); }

	$log = $dir . '/.preview.log';
	@unlink( $log );
	$descriptors = [
		0 => [ 'file', hub_is_windows() ? 'NUL' : '/dev/null', 'r' ],
		1 => [ 'file', $log, 'a' ],
		2 => [ 'file', $log, 'a' ],
	];
	$run = $cmdArr['shell'] ?? $cmdArr;
	$proc = proc_open( $run, $descriptors, $pipes );
	if ( ! is_resource( $proc ) ) { wdh_fail( 'Tunnel start nahi hua.' ); }
	$status = proc_get_status( $proc );
	file_put_contents( $dir . '/.preview.pid', (string) (int) $status['pid'] );

	$hostAlt = 'trycloud' . 'flare.com|lhr.life|local' . 'host.run';
	$url = '';
	for ( $i = 0; $i < 90; $i++ ) {
		$txt = file_exists( $log ) ? (string) file_get_contents( $log ) : '';
		// Pehle wo line pakro jahan tool "Your quick Tunnel" ke baad
		// asal address deta hai (api wala host is se kharij hai,
		// warna pehla jhoota match wahi ata hai).
		$scan = $txt;
		if ( preg_match( '/Your quick Tunnel has been created/i', $txt, $qm, PREG_OFFSET_CAPTURE ) ) {
			$scan = substr( $txt, $qm[0][1] );
		}
		if ( preg_match( '#https://(?!api\.)[a-z0-9\-]+\.(' . $hostAlt . ')#i', $scan, $m ) ) { $url = $m[0]; break; }
		if ( ! hub_pid_alive( (int) $status['pid'] ) ) { break; }
		sleep( 1 );
	}
	if ( ! $url ) {
		hub_kill( (int) $status['pid'] );
		@unlink( $dir . '/.preview.pid' );
		wdh_fail( 'Tunnel ka link nahi mila (90s) — .preview.log dekho. Internet/firewall tunnel ko rok sakta hai.' );
	}
	$meta['preview_url'] = $url;
	hub_save_meta( $dir, $meta );
	echo 'Preview link tayyar hai: ' . $url . "\n";
	exit( 0 );
}

if ( $cmd === 'previewstop' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$pid = file_exists( $dir . '/.preview.pid' ) ? (int) file_get_contents( $dir . '/.preview.pid' ) : 0;
	if ( $pid ) { hub_kill( $pid ); }
	@unlink( $dir . '/.preview.pid' );
	$meta = hub_site_meta( $dir );
	if ( $meta && ! empty( $meta['preview_url'] ) ) {
		$meta['_remove'] = [ 'preview_url' ];
		hub_save_meta( $dir, $meta );
	}
	echo "Preview band ho gaya.\n";
	exit( 0 );
}

/* ---------------- Local HTTPS (feature 19) ---------------- */
if ( $cmd === 'httpsstart' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { wdh_fail( "Site nahi mili: $slug" ); }
	if ( ! hub_site_running( $dir ) ) { wdh_fail( 'Site band hai — HTTPS ke liye pehle site Shuru karein.' ); }
	if ( hub_https_running( $dir ) ) { echo "HTTPS pehle se chal raha hai.\n"; exit( 0 ); }
	// Mu-plugin taaza karo — HTTPS wala hissa (X-Forwarded-Proto
	// par siteurl badalna) purani sites mein tabhi ayega.
	$muSrc = hub_bundle_dir() . '/mu-plugins/webdevhub-core.php';
	$muDstDir = $dir . '/wp-content/mu-plugins';
	if ( file_exists( $muSrc ) ) {
		if ( ! is_dir( $muDstDir ) ) { mkdir( $muDstDir, 0775, true ); }
		copy( $muSrc, $muDstDir . '/webdevhub-core.php' );
	}
	$httpsPort = hub_https_port( $slug, $meta );
	$proxy = hub_tools_dir() . '/https-proxy.php';
	$log = $dir . '/.https.log';
	$descriptors = [
		0 => [ 'file', hub_is_windows() ? 'NUL' : '/dev/null', 'r' ],
		1 => [ 'file', $log, 'a' ],
		2 => [ 'file', $log, 'a' ],
	];
	$proc = proc_open(
		[ hub_php(), $proxy, '--ca-dir=' . hub_ca_dir(), '--slug=' . $slug, '--https-port=' . $httpsPort, '--http-port=' . (int) $meta['port'] ],
		$descriptors, $pipes
	);
	if ( ! is_resource( $proc ) ) { wdh_fail( 'HTTPS proxy start nahi hua — .https.log dekho.' ); }
	$status = proc_get_status( $proc );
	$pid = (int) $status['pid'];
	if ( hub_is_windows() ) {
		for ( $i = 0; $i < 20; $i++ ) {
			$found = hub_port_pid( $httpsPort );
			if ( $found ) { $pid = $found; break; }
			usleep( 500000 );
		}
	}
	file_put_contents( $dir . '/.https.pid', (string) $pid );
	for ( $i = 0; $i < 20; $i++ ) {
		if ( hub_port_listening( $httpsPort ) ) { break; }
		usleep( 500000 );
	}
	if ( ! hub_port_listening( $httpsPort ) ) { wdh_fail( 'HTTPS port khula nahi — .https.log dekho.' ); }
	$meta['https_port'] = $httpsPort;
	hub_save_meta( $dir, $meta );
	echo 'HTTPS tayyar hai: https://127.0.0.1:' . $httpsPort . "\n";
	exit( 0 );
}

if ( $cmd === 'httpsstop' ) {
	$dir = hub_sites_dir() . '/' . $slug;
	$pid = file_exists( $dir . '/.https.pid' ) ? (int) file_get_contents( $dir . '/.https.pid' ) : 0;
	if ( $pid ) { hub_kill( $pid ); }
	@unlink( $dir . '/.https.pid' );
	echo "HTTPS band ho gaya.\n";
	exit( 0 );
}

/* ---------------- PHP version download (feature 18) ---------------- */
if ( $cmd === 'phpdownload' ) {
	$line = $opts['line'] ?? '';
	$man = hub_php_versions_manifest();
	$entry = null;
	foreach ( (array) ( $man['versions'] ?? [] ) as $v ) { if ( ( $v['line'] ?? '' ) === $line ) { $entry = $v; break; } }
	if ( ! $entry ) { wdh_fail( "PHP line manifest mein nahi: $line" ); }
	if ( hub_php_bin_for_line( $line ) ) { echo "PHP $line pehle se install hai.\n"; exit( 0 ); }
	$url = hub_is_windows() ? ( $entry['windows_url'] ?? '' ) : ( $entry['linux_url'] ?? '' );
	if ( ! $url ) { wdh_fail( 'Is platform ke liye download URL nahi.' ); }
	$rt = hub_repo() . '/runtime';
	if ( ! is_dir( $rt ) ) { mkdir( $rt, 0775, true ); }
	$pkg = $rt . '/.php-' . $line . '-pkg' . ( hub_is_windows() ? '.zip' : '.tar.gz' );
	echo "PHP $line download ho raha hai ({$entry['version']})...\n";
	$dl = wdh_download( $url, $pkg );
	if ( ! $dl ) { wdh_fail( "Download nakam — internet check karke dobara koshish karein." ); }
	$stage = $rt . '/.php-' . $line . '-stage';
	hub_rrmdir( $stage );
	mkdir( $stage, 0775, true );
	if ( ! hub_is_windows() && substr( $pkg, -7 ) === '.tar.gz' ) {
		$rc = 0;
		$to = [];
		for ( $try = 1; $try <= 2; $try++ ) {
			$rc = 0;
			$to = [];
			exec( 'tar --no-same-owner -xzf ' . escapeshellarg( $pkg ) . ' -C ' . escapeshellarg( $stage ) . ' 2>&1', $to, $rc );
			if ( $rc === 0 ) { break; }
			if ( $try < 2 ) { sleep( 3 ); }
		}
		if ( $rc !== 0 ) { hub_rrmdir( $stage ); wdh_fail( 'tar.gz khul nahi saka: ' . implode( ' ', array_slice( $to, 0, 2 ) ) ); }
	} else {
		wdh_extract( $pkg, $stage );
	}
	@unlink( $pkg );
	// Binary dhoondo: seedha stage mein ya ek folder andar
	$binName = 'php' . ( hub_is_windows() ? '.exe' : '' );
	$bin = null;
	if ( file_exists( $stage . '/' . $binName ) ) { $bin = $stage; }
	else {
		foreach ( scandir( $stage ) as $n ) {
			if ( $n === '.' || $n === '..' ) { continue; }
			if ( is_dir( $stage . '/' . $n ) && file_exists( $stage . '/' . $n . '/' . $binName ) ) { $bin = $stage . '/' . $n; break; }
		}
	}
	if ( ! $bin ) { hub_rrmdir( $stage ); wdh_fail( 'Package mein php binary nahi mili.' ); }
	$dest = $rt . '/php-' . $line;
	hub_rrmdir( $dest );
	rename( $bin, $dest );
	hub_rrmdir( $stage );
	if ( ! hub_is_windows() ) { @chmod( $dest . '/php', 0755 ); }
	$check = trim( (string) shell_exec( hub_q( hub_php_bin_for_line( $line ) ) . ' -r "echo PHP_VERSION;" 2>' . ( hub_is_windows() ? 'NUL' : '/dev/null' ) ) );
	if ( strpos( $check, $line . '.' ) !== 0 ) { wdh_fail( "Install ke baad version check fail: $check" ); }
	echo "PHP $line tayyar hai: $check\n";
	exit( 0 );
}

/** File download (curl pehle, warna PHP streams) — kamyabi par true. */
function wdh_download( $url, $dest, $minBytes = 1000000 ) {
	if ( ! hub_is_windows() ) {
		$curl = trim( (string) shell_exec( 'command -v curl 2>/dev/null' ) );
		if ( $curl ) {
			$rc = 0;
			exec( escapeshellarg( $curl ) . ' -fsSL --retry 2 -o ' . escapeshellarg( $dest ) . ' ' . escapeshellarg( $url ) . ' 2>&1', $o, $rc );
			if ( $rc === 0 && file_exists( $dest ) && filesize( $dest ) > $minBytes ) { return true; }
		}
	}
	$ctx = stream_context_create( [ 'http' => [ 'timeout' => 300, 'follow_location' => 1, 'user_agent' => 'TAMEERO' ], 'https' => [ 'timeout' => 300, 'follow_location' => 1 ] ] );
	$in = @fopen( $url, 'rb', false, $ctx );
	if ( ! $in ) { return false; }
	$out = @fopen( $dest, 'wb' );
	if ( ! $out ) { fclose( $in ); return false; }
	stream_copy_to_stream( $in, $out );
	fclose( $in );
	fclose( $out );
	return file_exists( $dest ) && filesize( $dest ) > $minBytes;
}

echo "Usage: wdh.php create|start|stop|list|sizes|resetpass|thumb|clone|totemplate|import|backup|phpdownload [--site S] [--blueprint B] [--port P]\n";
exit( 1 );
