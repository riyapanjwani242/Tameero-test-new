<?php
/**
 * WebDevHub Dashboard — core library.
 * Dashboard sirf 127.0.0.1 par chalta hai. Saare projects sites dir
 * mein rehte hain; har site ki pehchan us ke .webdevhub.json se hoti hai.
 */

function hub_repo() { return dirname( __DIR__, 2 ); }

function hub_sites_dir() {
	$d = getenv( 'WDH_SITES_DIR' );
	return $d ? rtrim( $d, '/' ) : hub_repo() . '/sites';
}

function hub_php() {
	$p = getenv( 'WDH_PHP' );
	if ( $p ) { return $p; }
	$w = trim( (string) shell_exec( 'command -v php 2>/dev/null' ) );
	if ( $w ) { return $w; }
	$fallback = getenv( 'HOME' ) . '/workspace/webdevhub-test/php-common/php';
	return file_exists( $fallback ) ? $fallback : 'php';
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
	if ( function_exists( 'posix_kill' ) ) { return @posix_kill( (int) $pid, 0 ); }
	return file_exists( '/proc/' . (int) $pid );
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

function hub_env_prefix() {
	return 'WDH_SITES_DIR=' . escapeshellarg( hub_sites_dir() )
		. ' WDH_PHP=' . escapeshellarg( hub_php() ) . ' ';
}

function hub_run_manager( $action, $slug ) {
	$cmd = hub_env_prefix() . 'bash ' . escapeshellarg( hub_repo() . '/installer-linux/webdevhub.sh' )
		. ' ' . $action . ' ' . escapeshellarg( $slug ) . ' 2>&1';
	return shell_exec( $cmd );
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
	$env = hub_env_prefix()
		. 'WDH_ADMIN_USER=' . escapeshellarg( $user ) . ' '
		. 'WDH_ADMIN_EMAIL=' . escapeshellarg( $email ) . ' '
		. 'WDH_ADMIN_PASS=' . escapeshellarg( $pass ) . ' ';
	// Env assignments nohup se PEHLE — warna nohup pehle assignment ko
	// hi program samajh kar fail ho jata hai.
	$cmd = $env . 'nohup bash ' . escapeshellarg( hub_repo() . '/installer-linux/install.sh' )
		. ' --site ' . escapeshellarg( $slug )
		. ' --blueprint ' . escapeshellarg( $blueprint )
		. ' --port ' . (int) $port
		. ' > ' . escapeshellarg( $log ) . ' 2>&1 &';
	shell_exec( $cmd );
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
	shell_exec( 'rm -rf ' . escapeshellarg( $real_dir ) );
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
