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

function wdh_fail( $msg ) { fwrite( STDERR, $msg . "\n" ); exit( 1 ); }

/**
 * Extract ke baad har zip entry ka size asal file se milao.
 * Bojh tale unzip/ZipArchive kabhi kabhi files adhoori chhor
 * dete hain lekin success report kar dete hain — adhoori files
 * ko infiradi taur par dobara nikaal kar theek karo, warna
 * saaf fail do (kharab site kabhi activate nahi hogi).
 */
function wdh_extract_verify_repair( $zipFile, $dest ) {
	$zip = new ZipArchive();
	if ( $zip->open( $zipFile ) !== true ) { wdh_fail( "Zip verify ke liye nahi khuli: $zipFile" ); }
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
	$zip = new ZipArchive();
	if ( $zip->open( $zipFile ) !== true ) { wdh_fail( "Zip nahi khuli: $zipFile" ); }
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
	$out = [];
	$rc = 0;
	exec( $cmd . ' 2>&1', $out, $rc );
	foreach ( $out as $line ) { echo $line . "\n"; }
	return $rc;
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
		$badFiles = hub_copy_dir( $tplDir, $dir );
		if ( $badFiles > 0 ) { wdh_fail( "Template copy mein $badFiles files kharab reh gayin — dobara koshish karein." ); }
		@unlink( $dir . '/.webdevhub-template.json' );
		// wp-config mein template banane wali asal site ka rasta bake
		// hota hai — fix ko wahi chahiye, template wala dir nahi.
		wdh_activate_copied_site( $slug, $dir, $tplMeta['source_dir'] ?? $tplDir, $tplMeta['blueprint'] ?? 'business', $port );
		exit( 0 );
	}

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

	// Urdu demo content pack (feature 14) — checkbox se aya ho to
	if ( ! empty( $opts['demo-content'] ) ) {
		$rc = wdh_run_tool( 'demo-content.php', [ $quotedDir, hub_q( '--blueprint=' . $blueprint ) ] );
		if ( $rc !== 0 ) { echo "Note: demo content nahi lag saka (site theek hai).\n"; }
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
		wdh_fail( 'Is site ka database SQLite wala nahi hai — ye import sirf WebDevHub jaisi (SQLite) sites ke liye hai. MySQL wali site ke liye pehle usay WebDevHub mein chalana hoga.' );
	}
	// Purana rasta wp-config ke DB_DIR se nikaalo (fix ke liye)
	$cfgTxt = (string) file_get_contents( $srcRoot . '/wp-config.php' );
	$oldDir = '';
	if ( preg_match( "/define\\(\\s*'DB_DIR',\\s*'([^']+)'\\s*\\)/", $cfgTxt, $m ) ) {
		$oldDir = preg_replace( '#/wp-content/database/?$#', '', str_replace( '\\', '/', $m[1] ) );
	}
	if ( ! $oldDir ) { $oldDir = $srcRoot; }

	if ( $srcRoot === $tmp ) {
		rename( $tmp, $dir );
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

echo "Usage: wdh.php create|start|stop|list|sizes|resetpass|thumb|clone|totemplate|import|backup [--site S] [--blueprint B] [--port P]\n";
exit( 1 );
