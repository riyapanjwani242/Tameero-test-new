<?php
/**
 * WebDevHub Dashboard — core library.
 * Dashboard sirf 127.0.0.1 par chalta hai. Saare projects sites dir
 * mein rehte hain; har site ki pehchan us ke .webdevhub.json se hoti hai.
 */

function hub_repo() { return dirname( __DIR__, 2 ); }

function hub_is_windows() { return PHP_OS_FAMILY === 'Windows'; }

/** setup-site.php + router.php ki jagah — repo mein installer-linux/,
 * installed package mein tools/ (Inno wahi rakhta hai). */
function hub_tools_dir() {
	$app = hub_repo();
	if ( file_exists( $app . '/tools/setup-site.php' ) ) { return $app . '/tools'; }
	return $app . '/installer-linux';
}

/** Shell arg quoting — Windows par escapeshellarg ka rawaj mukhtalif
 * hai, is liye apna quoter: har arg dohre quotes mein. */
function hub_q( $s ) {
	if ( hub_is_windows() ) {
		return '"' . str_replace( '"', '\\"', (string) $s ) . '"';
	}
	return escapeshellarg( (string) $s );
}

function hub_sites_dir() {
	$d = getenv( 'WDH_SITES_DIR' );
	return $d ? rtrim( $d, '/' ) : hub_repo() . '/sites';
}

function hub_php() {
	$p = getenv( 'WDH_PHP' );
	if ( $p ) { return $p; }
	// Installed package: bundled runtime (Windows packaging pass)
	$rt = hub_repo() . '/runtime/php/php' . ( hub_is_windows() ? '.exe' : '' );
	if ( file_exists( $rt ) ) { return $rt; }
	if ( ! hub_is_windows() ) {
		$w = trim( (string) shell_exec( 'command -v php 2>/dev/null' ) );
		if ( $w ) { return $w; }
		$fallback = getenv( 'HOME' ) . '/workspace/webdevhub-test/php-common/php';
		if ( file_exists( $fallback ) ) { return $fallback; }
	}
	return 'php';
}

function hub_slug_ok( $slug ) {
	return (bool) preg_match( '/^[a-z0-9][a-z0-9\-]{0,60}$/', (string) $slug );
}

function hub_site_meta( $dir ) {
	$f = $dir . '/.webdevhub.json';
	if ( ! file_exists( $f ) ) { return null; }
	$m = json_decode( file_get_contents( $f ), true );
	return is_array( $m ) ? $m : null;
}

function hub_pid_alive( $pid ) {
	if ( ! $pid ) { return false; }
	if ( hub_is_windows() ) {
		$out = (string) shell_exec( 'tasklist /FI "PID eq ' . (int) $pid . '" /NH 2>NUL' );
		return strpos( $out, (string) (int) $pid ) !== false && stripos( $out, 'No tasks' ) === false;
	}
	if ( function_exists( 'posix_kill' ) ) { return @posix_kill( (int) $pid, 0 ); }
	return file_exists( '/proc/' . (int) $pid );
}

/** Kisi port par LISTEN karne wale process ki pid (Windows: netstat se;
 * Linux par pid file hi kafi hai, is liye null). */
function hub_port_pid( $port ) {
	if ( ! hub_is_windows() ) { return null; }
	$out = (string) shell_exec( 'netstat -ano -p TCP 2>NUL' );
	foreach ( explode( "\n", $out ) as $line ) {
		if ( preg_match( '/127\.0\.0\.1:' . (int) $port . '\s+\S+\s+LISTENING\s+(\d+)/', $line, $m ) ) {
			return (int) $m[1];
		}
	}
	return null;
}

/** Process band karo — pehle narmi se, phir sakhti se. */
function hub_kill( $pid ) {
	if ( ! $pid ) { return; }
	if ( hub_is_windows() ) {
		shell_exec( 'taskkill /PID ' . (int) $pid . ' /F 2>NUL' );
		return;
	}
	if ( function_exists( 'posix_kill' ) ) {
		@posix_kill( (int) $pid, 15 );
		for ( $i = 0; $i < 10; $i++ ) {
			if ( ! hub_pid_alive( $pid ) ) { return; }
			usleep( 500000 );
		}
		@posix_kill( (int) $pid, 9 );
	}
}

/** Folder poora delete (Windows par rm -rf nahi — PHP se, cross-platform). */
function hub_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) { return; }
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		$p = $f->getPathname();
		if ( $f->isDir() && ! $f->isLink() ) { @chmod( $p, 0777 ); @rmdir( $p ); }
		else { @chmod( $p, 0666 ); @unlink( $p ); }
	}
	@rmdir( $dir );
}

/**
 * Worker (panel/bin/wdh.php) ko bilkul alag background process mein
 * chalao. Admin credentials waghera shell quoting se bachane ke liye
 * env JSON file ke zariye jati hai — worker parh kar file delete kar
 * deta hai.
 */
function hub_spawn_worker( $args, array $env, $logFile ) {
	$sitesDir = hub_sites_dir();
	$envFile = null;
	if ( $env ) {
		$envFile = $sitesDir . '/.jobenv-' . bin2hex( random_bytes( 6 ) ) . '.json';
		file_put_contents( $envFile, json_encode( $env ) );
		@chmod( $envFile, 0600 );
		$args .= ' --env-file ' . $envFile;
	}
	$worker = hub_repo() . '/panel/bin/wdh.php';
	if ( hub_is_windows() ) {
		$runDir = hub_repo() . '/panel/.run';
		if ( ! is_dir( $runDir ) ) { mkdir( $runDir, 0775, true ); }
		$cmdFile = $runDir . '/run-' . bin2hex( random_bytes( 6 ) ) . '.cmd';
		$line = hub_q( hub_php() ) . ' ' . hub_q( $worker ) . ' ' . $args
			. ' > ' . hub_q( $logFile ) . ' 2>&1';
		file_put_contents( $cmdFile, "@echo off\r\n" . $line . "\r\n" );
		shell_exec( 'wscript.exe ' . hub_q( hub_repo() . '/panel/bin/run-hidden.vbs' ) . ' ' . hub_q( $cmdFile ) );
		return;
	}
	$cmd = 'nohup ' . hub_q( hub_php() ) . ' ' . hub_q( $worker ) . ' ' . $args
		. ' > ' . hub_q( $logFile ) . ' 2>&1 &';
	shell_exec( $cmd );
}

function hub_sites() {
	$base = hub_sites_dir();
	$out  = [];
	if ( ! is_dir( $base ) ) { return $out; }
	foreach ( scandir( $base ) as $name ) {
		if ( $name === '.' || $name === '..' ) { continue; }
		$dir = $base . '/' . $name;
		if ( ! is_dir( $dir ) ) { continue; }
		$meta = hub_site_meta( $dir );
		if ( ! $meta ) {
			// Job chal raha ho to creating state
			if ( file_exists( $dir . '.job.log' ) || file_exists( $base . '/' . $name . '.job.log' ) ) {
				$out[] = [ 'site' => $name, 'creating' => true, 'dir' => $dir ];
			}
			continue;
		}
		$pid = file_exists( $dir . '/.server.pid' ) ? (int) file_get_contents( $dir . '/.server.pid' ) : 0;
		$meta['running'] = hub_pid_alive( $pid );
		$meta['dir']     = $dir;
		$out[] = $meta;
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['site'], $b['site'] ) );
	return $out;
}

function hub_run_manager( $action, $slug ) {
	// Cross-platform PHP worker — yehi code path Linux aur Windows
	// dono par chalta hai (pehle ye bash script thi).
	//
	// Worker DETACHED chalti hai, shell_exec se NAHI — wajah (smoke
	// mein pakra gaya asal bug): shell_exec ka capture pipe site
	// server (jo kabhi band nahi hota) inherit kar leta tha, pipe kabhi
	// band na hone se dashboard ki request hamesha ke liye latak jati
	// thi, aur usi request ke session lock ki wajah se baad wali har
	// request (stop/delete) bhi ruk jati thi.
	$dir = hub_sites_dir() . '/' . $slug;
	$log = hub_sites_dir() . '/.' . $slug . '.' . $action . '.log';
	hub_spawn_worker( $action . ' --site ' . hub_q( $slug ), [], $log );

	if ( $action === 'start' ) {
		// Server ke jawab ka mukhtasar intezar (request band nahi hoti)
		for ( $i = 0; $i < 30; $i++ ) {
			$pid = file_exists( $dir . '/.server.pid' ) ? (int) file_get_contents( $dir . '/.server.pid' ) : 0;
			if ( $pid && hub_pid_alive( $pid ) ) { break; }
			usleep( 500000 );
		}
	} elseif ( $action === 'stop' ) {
		// Delete is ke baad hota hai — server asal mein band hona lazmi hai
		for ( $i = 0; $i < 30; $i++ ) {
			$pid = file_exists( $dir . '/.server.pid' ) ? (int) file_get_contents( $dir . '/.server.pid' ) : 0;
			if ( ! $pid || ! hub_pid_alive( $pid ) ) { break; }
			usleep( 500000 );
		}
	}
	$out = file_exists( $log ) ? trim( (string) file_get_contents( $log ) ) : '';
	@unlink( $log );
	return $out;
}

function hub_next_port() {
	$used = [];
	foreach ( hub_sites() as $s ) { if ( ! empty( $s['port'] ) ) { $used[] = (int) $s['port']; } }
	$p = 8080;
	while ( in_array( $p, $used, true ) ) { $p++; }
	return $p;
}

function hub_create_async( $slug, $blueprint, $user, $email, $pass ) {
	$base = hub_sites_dir();
	if ( ! is_dir( $base ) ) { mkdir( $base, 0775, true ); }
	$log = $base . '/' . $slug . '.job.log';
	$port = hub_next_port();
	hub_spawn_worker(
		'create --site ' . hub_q( $slug )
			. ' --blueprint ' . hub_q( $blueprint )
			. ' --port ' . (int) $port,
		[
			'WDH_ADMIN_USER'  => $user,
			'WDH_ADMIN_EMAIL' => $email,
			'WDH_ADMIN_PASS'  => $pass,
		],
		$log
	);
	return $log;
}

function hub_job_state( $slug ) {
	$base = hub_sites_dir();
	if ( hub_site_meta( $base . '/' . $slug ) ) { return 'done'; }
	$log = $base . '/' . $slug . '.job.log';
	if ( file_exists( $log ) ) {
		$txt = (string) file_get_contents( $log );
		if ( strpos( $txt, 'Site tayyar hai' ) !== false ) { return 'done'; }
		return 'running';
	}
	return 'none';
}

function hub_delete( $slug ) {
	$base = hub_sites_dir();
	$dir  = $base . '/' . $slug;
	$real_base = realpath( $base );
	$real_dir  = realpath( $dir );
	if ( ! $real_base || ! $real_dir || strpos( $real_dir, $real_base ) !== 0 || $real_dir === $real_base ) {
		return false;
	}
	hub_run_manager( 'stop', $slug );
	hub_rrmdir( $real_dir );
	@unlink( $base . '/' . $slug . '.job.log' );
	return true;
}

function hub_export( $slug ) {
	$dir = hub_sites_dir() . '/' . $slug;
	if ( ! is_dir( $dir ) ) { return null; }
	// Zip /tmp mein NAHI banta — wahan aksar jagah kam hoti hai (512MB
	// tmpfs par full site ka zip close hi nahi hota tha; smoke mein
	// pakra gaya). Exports sites dir ke sath disk par bante hain.
	$export_dir = hub_sites_dir() . '/.exports';
	if ( ! is_dir( $export_dir ) ) { mkdir( $export_dir, 0775, true ); }
	$zip_path = $export_dir . '/webdevhub-' . $slug . '-' . date( 'Ymd-His' ) . '.zip';

	// Pehla tareeqa: system zip CLI — ek site ~24,000 files / 400MB+ ki
	// hoti hai; ZipArchive un par close() par fail ho jata tha (files
	// lazy-read hoti hain, chalte server ki badalti files se). zip CLI
	// streaming hai aur missing file par sirf warning deta hai.
	$zip_bin = trim( (string) shell_exec( 'command -v zip 2>/dev/null' ) );
	if ( $zip_bin ) {
		$cmd = 'cd ' . escapeshellarg( dirname( $dir ) )
			. ' && ' . escapeshellarg( $zip_bin ) . ' -0 -r -q ' . escapeshellarg( $zip_path )
			. ' ' . escapeshellarg( $slug )
			. ' -x ' . escapeshellarg( $slug . '/.server.pid' ) . ' 2>/dev/null';
		shell_exec( $cmd );
		if ( file_exists( $zip_path ) && filesize( $zip_path ) > 0 ) { return $zip_path; }
		@unlink( $zip_path );
	}

	// Fallback: ZipArchive (jahan zip CLI na ho — masalan Windows;
	// wahan ye had note hai, docs mein darj hai)
	$zip = new ZipArchive();
	if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) { return null; }
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	$i = 0;
	foreach ( $it as $file ) {
		$zip->addFile( $file->getPathname(), $slug . '/' . $it->getSubPathName() );
		$zip->setCompressionIndex( $i, ZipArchive::CM_STORE );
		$i++;
	}
	if ( $zip->close() !== true ) { return null; }
	return $zip_path;
}

function hub_logs( $slug, $lines = 40 ) {
	$f = hub_sites_dir() . '/' . $slug . '/.server.log';
	if ( ! file_exists( $f ) ) { return '(koi log nahi)'; }
	$all = file( $f );
	return implode( '', array_slice( $all, -$lines ) );
}

function hub_admin_url( $site ) {
	if ( empty( $site['secret'] ) ) {
		return $site['url'] . '/wp-admin/';
	}
	$exp   = time() + 120;
	$token = hash_hmac( 'sha256', 'login:' . $exp, $site['secret'] );
	return $site['url'] . '/?wdh_login=' . $token . '&wdh_exp=' . $exp;
}
