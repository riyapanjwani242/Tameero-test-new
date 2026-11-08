<?php
/**
 * WebDevHub Dashboard — core library.
 * Dashboard sirf 127.0.0.1 par chalta hai. Saare projects sites dir
 * mein rehte hain; har site ki pehchan us ke .webdevhub.json se hoti hai.
 */

function hub_repo() { return dirname( __DIR__, 2 ); }

/** Shared bundle ki jagah (WP + plugins + blueprints). */
function hub_bundle_dir() { return hub_repo() . '/bundle'; }

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

function hub_save_meta( $dir, array $meta ) {
	unset( $meta['dir'], $meta['running'], $meta['creating'] );
	// Merge-on-write: do workers ek sath meta likhein (start ->
	// last_started, sizes -> size_bytes) to ek doosre ki tabdeeli
	// na mitaye — likhne se pehle disk ki taza haalat par apni
	// tabdeeli lagao. (Smoke mein pakri gayi race.)
	// Key HATANE ke liye '_remove' mein naam do — merge warna
	// purani key wapas le ata hai (unset be-asar rehta tha;
	// preview/php_line ki safai mein pakra gaya).
	$remove = $meta['_remove'] ?? [];
	unset( $meta['_remove'] );
	$fresh = hub_site_meta( $dir );
	if ( $fresh ) { $meta = array_merge( $fresh, $meta ); }
	foreach ( (array) $remove as $rk ) { unset( $meta[ $rk ] ); }
	file_put_contents( $dir . '/.webdevhub.json', json_encode( $meta, JSON_UNESCAPED_SLASHES ) );
	@chmod( $dir . '/.webdevhub.json', 0600 );
}

/** Bytes ko parhne ke qabil size (KB/MB/GB) */
function hub_human_size( $bytes ) {
	$bytes = (float) $bytes;
	if ( $bytes >= 1073741824 ) { return number_format( $bytes / 1073741824, 2 ) . ' GB'; }
	if ( $bytes >= 1048576 ) { return number_format( $bytes / 1048576, 1 ) . ' MB'; }
	if ( $bytes >= 1024 ) { return number_format( $bytes / 1024, 0 ) . ' KB'; }
	return (int) $bytes . ' B';
}

/** Poore folder ka size (bytes) — worker 'sizes' command yehi istemal
 * karta hai; dashboard khud kabhi page load par nahi ginata. */
function hub_dir_size( $dir ) {
	$total = 0;
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $it as $f ) { if ( $f->isFile() ) { $total += $f->getSize(); } }
	return $total;
}

/** Thumbnail file — JPEG jahan mumkin (Windows ke official PHP
 * builds), warna PNG (kuch static PHP builds mein GD ke pas JPEG
 * support nahi hota). Jo file maujood ho wahi wapas hoti hai. */
function hub_thumb_file( $dir ) {
	if ( file_exists( $dir . '/.webdevhub-thumb.jpg' ) ) { return $dir . '/.webdevhub-thumb.jpg'; }
	if ( file_exists( $dir . '/.webdevhub-thumb.png' ) ) { return $dir . '/.webdevhub-thumb.png'; }
	return null;
}

/**
 * Worker ko detached chala kar us ke KHATAM hone ka intezar karo
 * (mukhtasar kaamon ke liye: password reset, thumbnail).
 * Worker apni har run ke aakhir mein "##WDH_END##" likhta hai
 * (wdh.php mein shutdown hook) — yehi completion ki pehchan hai.
 * Wapas: log ka text (marker baghair).
 */
function hub_worker_wait( $args, array $env, $timeoutSec = 90 ) {
	$log = hub_sites_dir() . '/.worker-' . bin2hex( random_bytes( 6 ) ) . '.log';
	hub_spawn_worker( $args, $env, $log );
	$deadline = time() + $timeoutSec;
	$txt = '';
	while ( time() < $deadline ) {
		if ( file_exists( $log ) ) {
			$txt = (string) file_get_contents( $log );
			if ( strpos( $txt, '##WDH_END##' ) !== false ) { break; }
		}
		usleep( 400000 );
	}
	@unlink( $log );
	return trim( str_replace( '##WDH_END##', '', $txt ) );
}

/**
 * Poora folder copy karo (copy engine ki buniyad — clone/template).
 * Kuch runtime files copy NAHI hoti (server pid/log, aadhi bani
 * thumbnail koshishein).
 * Wapas: kharab reh gayi files ki tadaad (0 = copy saaf hai).
 */
function hub_copy_dir( $src, $dst ) {
	// .webdevhub.json bhi copy NAHI hoti: wo site ki PEHCHAN hai —
	// copy ke darmiyan dashboard ke background workers (sizes waghera)
	// usay parh kar nayi dir ko purani site samajh lete thay aur
	// purani meta wapas likh dete thay (smoke mein pakri gayi race).
	// Nayi pehchan activation hamesha khud likhta hai.
	$skip = [ '.server.pid', '.server.log', '.thumb-shot.png', '.thumb-browser.log', '.thumb.log', '.webdevhub.json' ];
	if ( ! is_dir( $dst ) ) { mkdir( $dst, 0775, true ); }
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	$badFiles = [];
	foreach ( $it as $f ) {
		if ( in_array( $f->getFilename(), $skip, true ) ) { continue; }
		$rel = substr( $f->getPathname(), strlen( $src ) + 1 );
		$to = $dst . '/' . $rel;
		if ( $f->isDir() ) { if ( ! is_dir( $to ) ) { mkdir( $to, 0775, true ); } }
		else {
			copy( $f->getPathname(), $to );
			// Khaamosh copy nakami ki pakar (asal mein dekhi gayi:
			// bojh tale copy() bhool kar 0-byte destination bana
			// deta hai, koi warning bhi nahi ati). Size mismatch
			// par ek dafa dobara koshish; phir bhi kharab ho to
			// list mein rakho — poori copy ke baad ek aakhri
			// repair pass chalega.
			$want = $f->getSize();
			if ( $want > 0 && ( ! file_exists( $to ) || filesize( $to ) !== $want ) ) {
				usleep( 500000 );
				copy( $f->getPathname(), $to );
				clearstatcache( true, $to );
				if ( ! file_exists( $to ) || filesize( $to ) !== $want ) {
					$badFiles[] = [ $f->getPathname(), $to, $want ];
				}
			}
		}
	}
	// Aakhri repair pass: bojh aksar chand seconds ka hota hai,
	// is liye thora ruk kar sirf kharab files dobara copy karo.
	if ( $badFiles ) {
		sleep( 2 );
		$stillBad = 0;
		foreach ( $badFiles as [ $from, $to, $want ] ) {
			copy( $from, $to );
			clearstatcache( true, $to );
			if ( ! file_exists( $to ) || filesize( $to ) !== $want ) { $stillBad++; }
		}
		return $stillBad;
	}
	return 0;
}

/** Kisi site ka server chal raha ho to band karo (copy se pehle). */
function hub_site_running( $dir ) {
	$pid = file_exists( $dir . '/.server.pid' ) ? (int) file_get_contents( $dir . '/.server.pid' ) : 0;
	return hub_pid_alive( $pid );
}

/** Templates ki jagah — sites se alag, uninstall par bachi rehti hai. */
/** Bundle mein mojood blueprints (id => name) — dashboard ke create form ke liye. */
function hub_blueprints() {
	$dir = hub_bundle_dir() . '/blueprints';
	$out = [];
	if ( ! is_dir( $dir ) ) { return $out; }
	$entries = scandir( $dir ) ?: [];
	sort( $entries );
	// Pehle business, store — baqi alphabetical
	$order = [ 'business', 'store' ];
	usort( $entries, function ( $a, $b ) use ( $order ) {
		$ia = array_search( $a, $order, true ); $ib = array_search( $b, $order, true );
		$ia = $ia === false ? 99 : $ia; $ib = $ib === false ? 99 : $ib;
		return $ia === $ib ? strcmp( $a, $b ) : ( $ia - $ib );
	} );
	foreach ( $entries as $e ) {
		if ( $e === '.' || $e === '..' ) { continue; }
		$f = $dir . '/' . $e . '/blueprint.json';
		if ( ! file_exists( $f ) ) { continue; }
		$bp = json_decode( (string) file_get_contents( $f ), true );
		if ( is_array( $bp ) ) { $out[ $e ] = $bp['name'] ?? $e; }
	}
	return $out;
}

/** Site ke wp-content/plugins mein install shuda plugins ki list. */
function hub_site_plugins( $dir ) {
	$pdir = $dir . '/wp-content/plugins';
	$out = [];
	if ( ! is_dir( $pdir ) ) { return $out; }
	foreach ( scandir( $pdir ) ?: [] as $e ) {
		if ( $e === '.' || $e === '..' || $e === 'index.php' ) { continue; }
		$full = $pdir . '/' . $e;
		if ( is_file( $full ) && substr( $e, -4 ) === '.php' ) {
			$info = hub_plugin_header( $full );
			if ( $info ) { $out[] = [ 'file' => $e, 'slug' => substr( $e, 0, -4 ) ] + $info; }
		} elseif ( is_dir( $full ) ) {
			foreach ( glob( $full . '/*.php' ) ?: [] as $f ) {
				$info = hub_plugin_header( $f );
				if ( $info ) { $out[] = [ 'file' => $e . '/' . basename( $f ), 'slug' => $e ] + $info; break; }
			}
		}
	}
	usort( $out, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
	return $out;
}

/** Plugin ki main file se Name/Version header parhna (WP ke pattern par). */
function hub_plugin_header( $file ) {
	$head = (string) file_get_contents( $file, false, null, 0, 8192 );
	if ( ! preg_match( '/Plugin Name:\s*(.+)/i', $head, $m ) ) { return null; }
	$ver = '';
	if ( preg_match( '/Version:\s*([0-9][^\s]*)/i', $head, $vm ) ) { $ver = trim( $vm[1] ); }
	return [ 'name' => trim( $m[1] ), 'version' => $ver ];
}

/** Site ke active plugins ki file list (DB options se, bootstrap ke baghair). */
function hub_site_active_plugins( $dir ) {
	$db = $dir . '/wp-content/database/.ht.sqlite';
	if ( ! file_exists( $db ) || ! class_exists( 'PDO' ) ) { return []; }
	try {
		$pdo = new PDO( 'sqlite:' . $db, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
		$row = $pdo->query( "SELECT option_value FROM wp_options WHERE option_name = 'active_plugins'" )->fetch( PDO::FETCH_ASSOC );
		if ( ! $row ) { return []; }
		$val = @unserialize( (string) $row['option_value'], [ 'allowed_classes' => false ] );
		return is_array( $val ) ? array_values( $val ) : [];
	} catch ( Throwable $e ) {
		return [];
	}
}

/** Site ki ACTIVE theme ka slug (stylesheet option) — na mile to ''.
 *  v2.10 Phase 3: Themes gallery ke "kis project par laga hua" badge
 *  aur Details ke liye. Sirf parhna hai; DB na ho to khamosh ''. */
function hub_site_active_theme( $dir ) {
	$db = $dir . '/wp-content/database/.ht.sqlite';
	if ( ! file_exists( $db ) || ! class_exists( 'PDO' ) ) { return ''; }
	try {
		$pdo = new PDO( 'sqlite:' . $db, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
		$v = $pdo->query( "SELECT option_value FROM wp_options WHERE option_name = 'stylesheet'" )->fetchColumn();
		return is_string( $v ) ? $v : '';
	} catch ( Throwable $e ) {
		return '';
	}
}

/** Bundle manifest (poora) — wordpress block samet. */
function hub_bundle_manifest() {
	$f = hub_bundle_dir() . '/manifest.json';
	$m = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null;
	return is_array( $m ) ? $m : [];
}

/**
 * Dist mein WP ki SAHI zip (v2.6.3): pehle manifest ke pinned
 * version wali exact file; wo na ho to sab se UNCHI version wali.
 * Alphabetical pehli file KABHI nahi — wahi 6.8.3 wala bug tha
 * (upgraded PC par purani zip pari rehti thi aur har creation
 * usi se banti thi).
 */
function hub_wp_zip() {
	$dist = hub_bundle_dir() . '/wordpress/dist';
	$m = hub_bundle_manifest();
	$pin = (string) ( $m['wordpress']['version'] ?? '' );
	if ( $pin !== '' && file_exists( $dist . '/wordpress-' . $pin . '.zip' ) ) {
		return $dist . '/wordpress-' . $pin . '.zip';
	}
	$best = null; $bestVer = null;
	foreach ( glob( $dist . '/wordpress-*.zip' ) ?: [] as $z ) {
		if ( preg_match( '/wordpress-(\d+\.\d+(?:\.\d+)?)\.zip$/', basename( $z ), $mm )
			&& ( $bestVer === null || version_compare( $mm[1], $bestVer, '>' ) ) ) {
			$best = $z; $bestVer = $mm[1];
		}
	}
	return $best;
}

/**
 * Site mein extract hone wali plugin zips (v2.6.3): SIRF manifest
 * mein pin ki hui files (exact naam) + sqlite wali. Dist mein pari
 * koi aur zip (purani leftover) site mein NAHI jati.
 */
function hub_plugin_dist_zips() {
	$dist = hub_bundle_dir() . '/plugins/dist';
	$out = [];
	$mf = hub_bundle_dir() . '/plugins/manifest.json';
	$m = file_exists( $mf ) ? json_decode( (string) file_get_contents( $mf ), true ) : null;
	foreach ( (array) ( $m['plugins'] ?? [] ) as $p ) {
		$url = (string) ( $p['url'] ?? '' );
		if ( strpos( $url, 'https://downloads.wordpress.org' ) === 0 ) {
			$f = $dist . '/' . basename( $url );
			if ( file_exists( $f ) ) { $out[] = $f; }
		}
	}
	$sqlite = $dist . '/sqlite-database-integration.zip';
	if ( file_exists( $sqlite ) ) { $out[] = $sqlite; }
	return $out;
}

/** Bundle ke pinned versions (slug => version). */
function hub_bundle_plugin_versions() {
	$f = hub_bundle_dir() . '/plugins/manifest.json';
	if ( ! file_exists( $f ) ) { return []; }
	$m = json_decode( (string) file_get_contents( $f ), true );
	$out = [];
	foreach ( (array) ( $m['plugins'] ?? [] ) as $p ) {
		if ( ! empty( $p['slug'] ) && ! empty( $p['version'] ) ) { $out[ $p['slug'] ] = (string) $p['version']; }
	}
	return $out;
}

/** Bundle ka WordPress version (manifest se). */
function hub_bundle_wp_version() {
	$f = hub_bundle_dir() . '/manifest.json';
	if ( ! file_exists( $f ) ) { return ''; }
	$m = json_decode( (string) file_get_contents( $f ), true );
	return (string) ( $m['wordpress']['version'] ?? '' );
}

/** Site ka WordPress version (version.php se). */
function hub_site_wp_version( $dir ) {
	$f = $dir . '/wp-includes/version.php';
	if ( ! file_exists( $f ) ) { return ''; }
	$c = (string) file_get_contents( $f );
	return preg_match( "/\\\$wp_version = '([^']+)'/", $c, $m ) ? $m[1] : '';
}

function hub_templates_dir() {
	$d = getenv( 'WDH_TEMPLATES_DIR' );
	return $d ? rtrim( $d, '/' ) : hub_repo() . '/templates';
}

function hub_templates() {
	$base = hub_templates_dir();
	$out = [];
	if ( ! is_dir( $base ) ) { return $out; }
	foreach ( scandir( $base ) as $name ) {
		if ( $name === '.' || $name === '..' ) { continue; }
		$dir = $base . '/' . $name;
		$f = $dir . '/.webdevhub-template.json';
		if ( is_dir( $dir ) && file_exists( $f ) ) {
			$m = json_decode( (string) file_get_contents( $f ), true );
			if ( is_array( $m ) ) { $m['dir'] = $dir; $out[] = $m; }
		}
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	return $out;
}

/** Auto-backup due hai? (feature 20 — 7 din ka waqfa) */
function hub_backup_due( $site ) {
	if ( empty( $site['backup_at'] ) ) { return true; }
	return strtotime( $site['backup_at'] ) < time() - 7 * 86400;
}

/** Health check ke liye system ki haalat (feature 21) */
function hub_health() {
	$extNeed = [ 'curl', 'gd', 'intl', 'mbstring', 'mysqli', 'openssl', 'pdo_sqlite', 'sqlite3', 'zip', 'fileinfo', 'exif' ];
	$extHave = get_loaded_extensions();
	$ext = [];
	foreach ( $extNeed as $e ) { $ext[ $e ] = in_array( $e, $extHave, true ); }

	$sitesDir = hub_sites_dir();
	$sites = hub_sites();
	$running = 0;
	foreach ( $sites as $s ) { if ( ! empty( $s['running'] ) ) { $running++; } }

	$bundle = hub_repo() . '/bundle';
	$wpZipSel = hub_wp_zip();
	$pluginZips = hub_plugin_dist_zips();

	return [
		'os'          => PHP_OS_FAMILY . ' (' . php_uname( 's' ) . ' ' . php_uname( 'r' ) . ')',
		'php_version' => PHP_VERSION,
		'php_bin'     => hub_php(),
		'extensions'  => $ext,
		'sites_dir'   => $sitesDir,
		'disk_free'   => @disk_free_space( $sitesDir ) ?: @disk_free_space( hub_repo() ),
		'disk_total'  => @disk_total_space( $sitesDir ) ?: @disk_total_space( hub_repo() ),
		'sites_count' => count( $sites ),
		'running'     => $running,
		'bundle_wp'   => $wpZipSel ? basename( $wpZipSel ) : null,
		'bundle_plugins' => count( $pluginZips ),
		'tools_dir'   => hub_tools_dir(),
	];
}

/**
 * XAMPP scan (feature 17 — class checklist ka item #2).
 * Hamara design XAMPP istemal nahi karta (ports bhi alag range
 * se ate hain), lekin user ke PC par XAMPP ho/chal raha ho to
 * dashboard par saaf maloomat honi chahiye — haath nahi lagana,
 * sirf batana hai.
 */
function hub_xampp_scan() {
	$out = [ 'installed' => null, 'apache_running' => false, 'mysql_running' => false, 'procs' => [] ];
	if ( hub_is_windows() ) {
		$candidates = [];
		foreach ( [ 'C', 'D', 'E', 'F' ] as $drv ) { $candidates[] = $drv . ':\\xampp'; }
		if ( getenv( 'USERPROFILE' ) ) { $candidates[] = getenv( 'USERPROFILE' ) . '\\xampp'; }
		foreach ( $candidates as $c ) {
			if ( file_exists( $c . '\\apache\\bin\\httpd.exe' ) ) { $out['installed'] = $c; break; }
		}
		$tl = strtolower( (string) shell_exec( 'tasklist /NH 2>NUL' ) );
		if ( strpos( $tl, 'httpd.exe' ) !== false ) { $out['apache_running'] = true; $out['procs'][] = 'httpd.exe'; }
		if ( strpos( $tl, 'mysqld.exe' ) !== false ) { $out['mysql_running'] = true; $out['procs'][] = 'mysqld.exe'; }
		return $out;
	}
	foreach ( [ '/opt/lampp', '/opt/xampp', getenv( 'HOME' ) . '/xampp', getenv( 'HOME' ) . '/lampp' ] as $c ) {
		if ( $c && file_exists( $c . '/lampp' ) ) { $out['installed'] = $c; break; }
	}
	foreach ( glob( '/proc/[0-9]*/comm' ) ?: [] as $f ) {
		$name = trim( (string) @file_get_contents( $f ) );
		if ( in_array( $name, [ 'httpd', 'apache2' ], true ) ) { $out['apache_running'] = true; $out['procs'][] = $name; }
		if ( in_array( $name, [ 'mysqld', 'mariadbd' ], true ) ) { $out['mysql_running'] = true; $out['procs'][] = $name; }
	}
	$out['procs'] = array_values( array_unique( $out['procs'] ) );
	return $out;
}

/** Port par koi sun raha hai? (connect test — dono platforms) */
function hub_port_listening( $port ) {
	$fp = @fsockopen( '127.0.0.1', (int) $port, $errno, $errstr, 1 );
	if ( $fp ) { fclose( $fp ); return true; }
	return false;
}

/** PHP versions ki downloadable list (bundle/config/php-versions.json). */
function hub_php_versions_manifest() {
	$f = hub_bundle_dir() . '/config/php-versions.json';
	if ( ! file_exists( $f ) ) { return [ 'default' => '8.4', 'versions' => [] ]; }
	$m = json_decode( (string) file_get_contents( $f ), true );
	return is_array( $m ) ? $m : [ 'default' => '8.4', 'versions' => [] ];
}

/** Ek PHP line (misal "8.3") ki binary — pehle runtime/php-<line>, phir default. */
function hub_php_bin_for_line( $line ) {
	if ( ! preg_match( '/^8\.\d+$/', (string) $line ) ) { return null; }
	$bin = hub_repo() . '/runtime/php-' . $line . '/php' . ( hub_is_windows() ? '.exe' : '' );
	return file_exists( $bin ) ? $bin : null;
}

/** Install shuda PHP versions: default (bundled/system) + runtime wali lines. */
function hub_php_installed() {
	$out = [];
	$defBin = hub_php();
	$defVer = '';
	if ( $defBin && file_exists( $defBin ) ) {
		$v = (string) shell_exec( hub_q( $defBin ) . ' -r "echo PHP_VERSION;" 2>' . ( hub_is_windows() ? 'NUL' : '/dev/null' ) );
		if ( preg_match( '/^8\.\d+\.\d+/', trim( $v ), $m ) ) { $defVer = $m[0]; }
	}
	$out[] = [ 'line' => $defVer ? substr( $defVer, 0, 3 ) : '8.4', 'version' => $defVer ?: PHP_VERSION, 'bin' => $defBin, 'bundled' => true ];
	foreach ( glob( hub_repo() . '/runtime/php-8.*' ) ?: [] as $d ) {
		if ( ! is_dir( $d ) ) { continue; }
		$line = substr( basename( $d ), 4 );
		$bin = hub_php_bin_for_line( $line );
		if ( $bin ) { $out[] = [ 'line' => $line, 'version' => $line, 'bin' => $bin, 'bundled' => false ]; }
	}
	return $out;
}

/** Site ke liye PHP binary — meta mein php_line ho aur wo install ho to wahi, warna default. */
function hub_php_for_site( $meta ) {
	if ( ! empty( $meta['php_line'] ) ) {
		$bin = hub_php_bin_for_line( $meta['php_line'] );
		if ( $bin ) { return $bin; }
	}
	return hub_php();
}

/** WebDevHub ki apni version (repo root VERSION file). */
function hub_version() {
	$f = hub_repo() . '/VERSION';
	return file_exists( $f ) ? trim( (string) file_get_contents( $f ) ) : 'dev';
}

/** Auto-update ke sources (bundle/config/update.json; test ke liye env override). */
function hub_update_config() {
	$cfg = [ 'version_url' => '', 'zip_url' => '' ];
	$f = hub_bundle_dir() . '/config/update.json';
	if ( file_exists( $f ) ) {
		$m = json_decode( (string) file_get_contents( $f ), true );
		if ( is_array( $m ) ) { $cfg = array_merge( $cfg, $m ); }
	}
	if ( getenv( 'WDH_UPDATE_VERSION_URL' ) ) { $cfg['version_url'] = getenv( 'WDH_UPDATE_VERSION_URL' ); }
	if ( getenv( 'WDH_UPDATE_ZIP_URL' ) ) { $cfg['zip_url'] = getenv( 'WDH_UPDATE_ZIP_URL' ); }
	return $cfg;
}

/**
 * Dashboard settings (feature 23/24/25) — panel/.settings.json:
 * zuban (ur/en), lite mode, wizard ho gaya ya nahi. Ek hi jagah
 * taake wizard, toggle aur worker sab ek hi sach parhein.
 */
function hub_settings_file() { return hub_repo() . '/panel/.settings.json'; }

function hub_settings() {
	$def = [ 'lang' => 'ur', 'lite' => false, 'wizard_done' => false ];
	$f = hub_settings_file();
	if ( file_exists( $f ) ) {
		$m = json_decode( (string) file_get_contents( $f ), true );
		if ( is_array( $m ) ) { $def = array_merge( $def, $m ); }
	}
	// Test/automation ke liye official escape hatch: wizard na dikhao
	if ( getenv( 'WDH_NO_WIZARD' ) ) { $def['wizard_done'] = true; }
	return $def;
}

function hub_save_settings( array $patch ) {
	$cur = hub_settings();
	$cur = array_merge( $cur, $patch );
	file_put_contents( hub_settings_file(), json_encode( $cur, JSON_UNESCAPED_SLASHES ) );
	return $cur;
}

function hub_lang() { return hub_settings()['lang'] === 'en' ? 'en' : 'ur'; }
function hub_lite() { return ! empty( hub_settings()['lite'] ); }

/**
 * Dashboard ki zubanein (feature 24). Default Roman Urdu hai
 * (product ki pehchan); English mukammal doosri zuban. Sirf
 * dashboard ke chrome (main page, health, wizard) ka tarjuma
 * hai — worker/flash paighamat aur tool views (plugins/db/mail)
 * abhi Roman Urdu mein hain.
 */
function hub_strings( $lang = null ) {
	$lang = $lang ?: hub_lang();
	$ur = [
		'app_sub' => 'Your Website. One Click. Done.',
		'footer_note' => 'Dashboard sirf is computer par (127.0.0.1) chalta hai.',
		'search_ph' => 'Project dhoondhein… (naam likhein)',
		'start_all' => 'Sab Shuru Karein',
		'stop_all' => 'Sab Band Karein',
		'health' => 'Health Check',
		'ca_cert' => 'Local CA Certificate',
		'settings' => 'Settings',
		'empty' => 'Abhi koi project nahi. Home par setup card se pehla project banayein.',
		'creating' => 'Creating… (page khud refresh hoga)',
		'failed' => 'Fail ho gayi',
		'view_log' => 'Log Dekho',
		'step_word' => 'Step',
		'running' => 'Chal raha hai',
		'stopped' => 'Band hai',
		'blueprint' => 'Blueprint',
		'size' => 'Size',
		'last_run' => 'Aakhri dafa chala',
		'never' => 'kabhi nahi',
		'last_backup' => 'Aakhri backup',
		'no_backup' => 'abhi tak nahi (dashboard khulne par khud ban jata hai)',
		'upd_avail' => 'Update dastiyab (bundle mein naya version)',
		'upd_note' => 'asal update WP-Admin se lagayein.',
		'open_site' => 'Site Kholain',
		'open_admin' => 'Admin Kholain',
		'start' => 'Shuru Karein',
		'stop' => 'Band Karein',
		'export' => 'Export (.zip)',
		'deploy' => 'Deploy Package',
		'photo' => 'Photo Taaza',
		'backup' => 'Backup',
		'delete' => 'Delete Karein',
		'delete_confirm' => 'Project delete kar dein? Ye wapas nahi ayega.',
		'https_start' => 'HTTPS Shuru',
		'https_stop' => 'HTTPS Band',
		'preview' => 'Preview Link',
		'preview_stop' => 'Preview Band',
		'preview_temp' => '(aarzi — site chalti rahe to link zinda hai)',
		'clone_tpl' => 'Clone banayein / Template banayein',
		'clone_label' => 'Clone ka naya naam (is site ki copy, naya address)',
		'clone_btn' => 'Clone Banayein',
		'tpl_label' => 'Template ka naam (phir is template se kitni bhi sites banengi)',
		'tpl_btn' => 'Template Banayein',
		'php_version' => 'PHP version',
		'php_label' => 'Is project ke liye PHP (site band ho to lagega; chalti ho to band karke lagta hai)',
		'php_default' => 'Default',
		'php_btn' => 'PHP Badlein',
		'php_note' => 'Naye versions Health page se download hote hain.',
		'reset' => 'Admin password reset karein',
		'reset_label' => 'Naya password (kam az kam 6 characters)',
		'reset_btn' => 'Password Badlein',
		'reset_confirm' => 'Admin ka password badal dein? Purana password phir kaam nahi karega.',
		'logs' => 'Logs dekhein · Folder',
		'new_project' => 'Naya Project Banayein',
		'np_name' => 'Project ka naam (chhoti angrezi, dash chalega)',
		'np_admin_user' => 'Admin username',
		'np_admin_email' => 'Admin email',
		'np_admin_pass' => 'Admin password',
		'np_demo' => 'Urdu demo content bhi dal dein (posts, pages, products)',
		'np_theme' => 'Theme (sirf Online Store ke liye)',
		'np_theme_default' => 'Default — Twenty Twenty-Five',
		'np_theme_dukaan' => 'Dukaan — Urdu RTL Store Theme',
		'np_create' => 'Project Banayein',
		'np_template' => 'Template se banayein',
		'import' => 'Zip se Import Karein',
		'import_label' => 'TAMEERO export zip chunein (sirf SQLite wali sites)',
		'import_btn' => 'Import Karein',
		'lang_name' => 'اردو',
		'lite_on' => 'Lite mode ON hai (halka mode)',
		'lite_off' => 'Lite mode',
		'agent_activity' => 'Agent Activity',
		'agent_pending' => 'Manzoori ke muntazir kaam',
		'agent_recent' => 'Haal ki activity',
		'agent_none_pending' => 'Koi kaam manzoori ka muntazir nahi hai.',
		'agent_none_recent' => 'Abhi tak koi agent activity nahi.',
		'agent_approve' => 'Manzoor Karein',
		'agent_reject' => 'Radd Karein',
		'agent_confirm_ph' => 'Confirm: site ka naam likhein',
		'agent_conn' => 'Agent Connection (MCP)',
		'agent_conn_state' => 'MCP HTTP server',
		'agent_on' => 'ON hai',
		'agent_off' => 'OFF hai',
		'agent_token_set' => 'Token set hai',
		'agent_token_none' => 'Token nahi bana',
		'agent_token_gen' => 'Naya Token Banayein',
		'agent_token_revoke' => 'Token Khatam Karein',
		'agent_turn_on' => 'MCP On Karein',
		'agent_turn_off' => 'MCP Off Karein',
		'agent_token_once' => 'Apna token copy kar lein — ye dobara NAHI dikhe ga:',
		'agent_conn_how' => 'Server chalane ke liye TAMEERO folder se: php panel/bin/mcp.php serve (sirf is machine par) ya php panel/bin/mcp.php serve --bind=0.0.0.0 (apne private Tailscale network ke liye). Phir apne AI client (Muse, Cursor waghera) mein ye config dalen:',
		'agent_client' => 'Client',
		// Health page
		'h_title' => 'Health Check',
		'h_back' => '← Dashboard par wapas',
		'h_version' => 'TAMEERO version',
		'h_update_btn' => 'Update Check Karein',
		'h_update_apply' => 'Update Lagayein',
		'h_update_new' => 'naya version dastiyab hai',
		'h_update_latest' => 'sab se naya version chal raha hai.',
		'h_update_fail' => 'Aakhri check nakam raha (internet?) — dobara koshish karein.',
		'h_update_note' => 'Note: update ka source public GitHub repo hai — repo public hone ke baad ye khud kaam karega.',
		'h_system' => 'System',
		'h_phpver' => 'PHP version',
		'h_phpbin' => 'PHP program',
		'h_sitesdir' => 'Projects ki jagah',
		'h_disk' => 'Disk khaali / kul',
		'h_projects' => 'Projects',
		'h_running_n' => 'chal rahe hain',
		'h_bundle_wp' => 'Bundle — WordPress',
		'h_bundle_pl' => 'Bundle — Plugins',
		'h_plugins_ready' => 'plugins tayyar',
		'h_notfound' => 'nahi mila',
		'h_tools' => 'Tools ki jagah',
		'h_ext' => 'PHP extensions',
		'h_xampp' => 'XAMPP scan',
		'h_xampp_found' => 'XAMPP mila',
		'h_xampp_none' => 'XAMPP install nahi mila.',
		'h_xampp_running' => 'XAMPP ke ye hisse abhi chal rahe hain',
		'h_xampp_calm' => 'Fikr nahi — TAMEERO apni alag ports (8080+) aur apna SQLite istemal karta hai, XAMPP se takrao nahi hota aur hum XAMPP ko haath bhi nahi lagate.',
		'h_xampp_idle' => 'XAMPP ka koi hissa abhi chal nahi raha.',
		'h_phpvers' => 'PHP versions',
		'h_php_dl' => 'Download Karein',
		'h_php_note' => 'Har project ke card par us ka PHP alag chuna ja sakta hai.',
		'h_ext_note' => 'Koi ✗ nazar aye to pehle usay theek karein — warna kuch features kaam nahi karein ge.',
		// Wizard
		'w_title' => 'Khush Aamdeed!',
		'w_sub' => 'TAMEERO tayyar hai. Teen aasan steps mein pehla project bana lein.',
		'w_step' => 'Step',
		'w_lang' => 'Apni zuban chunein',
		'w_lite_q' => 'Kya aap ka computer purana/halka hai?',
		'w_lite_hint' => 'Lite mode mein dashboard halka chalta hai (kam background kaam, site server ek worker par). Baad mein bhi badal sakte hain.',
		'w_yes' => 'Haan, lite mode on karein',
		'w_no' => 'Nahi, aam mode',
		'w_next' => 'Agla Step →',
		'w_skip' => 'Wizard chhor dein',
		'w_p_title' => 'Pehla Project Banayein',
		'w_p_name' => 'Project ka naam (chhoti angrezi, dash chalega)',
		'w_p_pass' => 'WordPress admin password (kam az kam 6 characters)',
		'w_create' => 'Project Banayein →',
		'w_done_t' => 'Mubarak ho! 🎉',
		'w_done' => 'Aap ka project ban raha hai — dashboard par us ki haalat nazar ayegi. Ban jaye to "Admin Kholain" se WordPress mein dakhil ho jayein.',
		'w_go' => 'Dashboard Par Jayein →',
		'nav_home' => 'Home',
		'nav_projects' => 'Projects',
		'nav_themes' => 'Themes',
		'home_welcome' => 'Khush aamdeed!',
		'np_more' => 'Mazeed (admin user / email)',
		'recent_projects' => 'Recent Projects',
		'recent_activity' => 'Recent Activity',
		'view_all_projects' => 'Tamam Projects Dekhein →',
		'projects_title' => 'Mere Projects',
		'projects_count_fmt' => '%d Projects',
		'themes_title' => 'Themes',
		'themes_current' => 'Mojooda Themes',
		'themes_soon' => 'Jald Aa Raha Hai: Urdu News · Urdu Blog — aik waqt mein aik theme, apni bari par.',
		'themes_coming' => 'Jald Aa Raha Hai',
		'details' => 'Tafseel',
		'more' => 'Mazeed',
		'copy_path' => 'Path copy karein',
		'copied' => 'Copy ho gaya ✓',
		'cat_open' => 'Kholna',
		'cat_data' => 'Data',
		'cat_tools' => 'Tools',
		'cat_danger' => 'Khatarnak',
		'f_all' => 'Sab',
		'f_running' => 'Chalti Hui',
		'f_stopped' => 'Band',
		'f_updates' => 'Update Wale',
		'upd_badge_fmt' => '%d Update',
		'tab_overview' => 'Khulasa',
		'tab_plugins' => 'Plugins',
		'tab_updates' => 'Updates',
		'tab_logs' => 'Logs',
		'tab_settings' => 'Settings',
		'det_back' => '← Projects par wapas',
		'upd_none' => 'Sab kuch up to date hai.',
		'upd_wp_avail' => 'WordPress update dastiyab hai',
		'upd_plug_avail' => 'Plugin updates dastiyab hain:',
		'theme_in_use' => 'Is waqt in projects par laga hua:',
		'theme_none_use' => 'Abhi kisi project par nahi laga',
		'theme_preview' => 'Preview',
		'theme_use' => 'Use Karein',
		'theme_swap_soon' => 'Mojooda project par theme badalna jald aa raha hai — abhi Use Karein sirf naye project ke liye hai.',
		'theme_default_desc' => 'WordPress ki apni saaf-suthri default theme — har tarah ki site ke liye bharosemand buniyad.',
		'theme_dukaan_desc' => 'Urdu RTL online store theme — maroon + gold, WhatsApp order button, dukaan ke liye khaas banayi gayi.',
		'act_backup_fmt' => '%s ka backup bana',
		'act_started_fmt' => '%s shuru hui',
		'activity_none' => 'Abhi koi activity record nahi hai.',
		'st_running_short' => 'Chal raha hai',
		'st_stopped_short' => 'Band hai',
		'lang_label' => 'Zubaan badlein',
		'side_disk' => 'Disk',
		'side_free' => 'khali',
		'side_ports_ok' => 'Ports theek',
		'agent_word' => 'Agent',
		'pending_word' => 'manzoori baqi',
		'chat_title' => 'AI Assistant',
		'chat_ph' => 'AI se kuch bhi kahen…',
		'chat_collapse' => 'Panel band karein',
		'chat_expand' => 'AI panel kholein',
		'sc_list' => 'Projects ki List',
		'sc_list_prompt' => 'Mere saare projects ki list un ke status ke sath dikhao.',
		'sc_start' => 'Sab Shuru Karo',
		'sc_start_prompt' => 'Saari band sites shuru kar do.',
		'sc_stop' => 'Sab Band Karo',
		'sc_stop_prompt' => 'Saari chalti sites band kar do.',
		'sc_backup' => 'Backup Banao',
		'sc_backup_prompt' => 'Sab sites ka backup banao.',
		'stat_projects' => 'Kul Projects',
		'stat_running' => 'Chalti Websites',
		'stat_disk' => 'Disk Istemal',
		'stat_updates' => 'Updates Baqi',
		'btn_plugins' => 'Plugins',
		'btn_database' => 'Database',
		'btn_mail' => 'Mail',
		'deploy_title' => 'Live hosting ke liye package: files + MySQL dump + checklist',
		'upd_plugins_word' => 'plugins',
		'status_running_fmt' => 'Chal raha hai · %s se',
		'status_stopped_fmt' => 'Band hai · aakhri dafa %s',
		'site_not_found' => 'Site nahi mili.',
		'th_plugin' => 'Plugin',
		'th_version' => 'Version',
		'th_state' => 'Halat',
		'plug_on' => '● Chalu',
		'plug_off' => '○ Band',
		'plug_turn_on' => 'Chalu Karein',
		'plug_turn_off' => 'Band Karein',
		'plugins_note' => 'Bundled plugins saaray install hote hain — yahan se jo chahiye chalu karein, jo nahi chahiye band rakhein. Naya plugin lagana ho to WP-Admin ke Plugins page se.',
		'db_err_open' => 'Database kholne mein masla:',
		'db_tables' => 'Tables',
		'db_note_fmt' => 'Table: <strong>%s</strong> — kul %d rows · page %d · Kisi value par click karke badal sakte hain (serialized values ahtiyaat se — ghalat edit site tor sakta hai, pehle backup lein).',
		'pg_prev' => '← Pichla',
		'pg_next' => 'Agla →',
		'db_sql_title' => 'SQL Sawal (sirf parhna — SELECT)',
		'mail_note' => 'Local site ki koi email bahir NAHI jati — sab yahan pakri jati hain (password reset, order, form waghera test karne ke liye).',
		'mail_none' => 'Abhi tak koi mail nahi pakri gayi.',
		'th_time' => 'Waqt',
		'th_to' => 'Kise (To)',
		'th_subject' => 'Subject',
		'mail_no_subject' => '(bina subject)',
		'set_lite_title' => 'Lite Mode',
		'set_lite_text' => 'Lite mode — low-power PCs ke liye halka mode (sizes/background kaam kam).',
		'set_chat_title' => 'AI Connection',
		'set_chat_text' => 'AI kis provider se jude gi. Provider chunein — Endpoint URL khud bhar jaye ga. <b>Models Load</b> se model list ajati hai, <b>Connection Test</b> se jaanch ho jati hai. API Key page par kabhi wapas nahi dikhti; khali chhorein to purani key mehfooz rehti hai. Chat ke tools MCP approval se hi chalte hain; MCP token browser tak kabhi nahi jata.',
		'set_provider_label' => 'AI Provider',
		'set_gw_url' => 'Endpoint URL',
		'set_model_label' => 'Model (khali = gateway ki pasand)',
		'set_gw_key_label' => 'API Key (khali chhorein to purani key mehfooz)',
		'set_models_btn' => 'Models Load Karein',
		'set_test_btn' => 'Connection Test',
		'set_brain_note' => 'AI ki hidayat (Brain): panel/ai-brain folder ki .md files mein hain — aap inhein badal sakte hain; upgrade mein aap ki tabdeeli overwrite nahi hogi.',
		'chat_brain_warn' => 'Brain folder nahi mila — default hidayat chal rahi hai (panel/ai-brain).',
		'set_chat_save' => 'Chat Settings Save Karein',
		'set_info_title' => 'Maloomat',
		'set_info_text_fmt' => 'TAMEERO v%s · Sites: %s<br>Dashboard sirf is computer par (127.0.0.1) chalta hai.',
		'my_templates' => 'Mere Templates',
		'tpl_meta_fmt' => ' — %s se bani · %s',
		'tpl_desc' => 'Is se site banane ke liye oopar wale form mein Blueprint ki jagah ye template chunein. Admin template wali site wala hi hoga.',
		'tpl_delete_confirm' => 'Template delete kar dein?',
		'import_note' => 'TAMEERO se export ki hui zip (ya aisi hi SQLite wali WordPress site ki zip) yahan se purani/client ki site ke tor par wapas laein. MySQL wali zip yahan nahi chalegi.',
		'imp_uploading' => 'Upload ho raha hai…',
		'db_edit_btn' => 'Badlein',
		'db_run_btn' => 'Chalayein',
		'mail_to' => 'Kise',
	];
	if ( $lang !== 'en' ) { return $ur; }
	$en = [
		'app_sub' => 'Your Website. One Click. Done.',
		'footer_note' => 'The dashboard runs only on this computer (127.0.0.1).',
		'search_ph' => 'Search projects… (type a name)',
		'start_all' => 'Start All',
		'stop_all' => 'Stop All',
		'health' => 'Health Check',
		'ca_cert' => 'Local CA Certificate',
		'settings' => 'Settings',
		'empty' => 'No projects yet. Create your first project from the setup card on Home.',
		'creating' => 'Creating… (page refreshes itself)',
		'failed' => 'Failed',
		'view_log' => 'View Log',
		'step_word' => 'Step',
		'running' => 'Running',
		'stopped' => 'Stopped',
		'blueprint' => 'Blueprint',
		'size' => 'Size',
		'last_run' => 'Last started',
		'never' => 'never',
		'last_backup' => 'Last backup',
		'no_backup' => 'not yet (created automatically when the dashboard opens)',
		'upd_avail' => 'Update available (newer version in bundle)',
		'upd_note' => 'apply the actual update from WP-Admin.',
		'open_site' => 'Open Site',
		'open_admin' => 'Open Admin',
		'start' => 'Start',
		'stop' => 'Stop',
		'export' => 'Export (.zip)',
		'deploy' => 'Deploy Package',
		'photo' => 'Refresh Photo',
		'backup' => 'Backup',
		'delete' => 'Delete',
		'delete_confirm' => 'Delete this project? This cannot be undone.',
		'https_start' => 'Start HTTPS',
		'https_stop' => 'Stop HTTPS',
		'preview' => 'Preview Link',
		'preview_stop' => 'Stop Preview',
		'preview_temp' => '(temporary — alive while the site runs)',
		'clone_tpl' => 'Clone / Make Template',
		'clone_label' => 'New name for the clone (a copy of this site, new address)',
		'clone_btn' => 'Create Clone',
		'tpl_label' => 'Template name (then make as many sites as you like from it)',
		'tpl_btn' => 'Create Template',
		'php_version' => 'PHP version',
		'php_label' => 'PHP for this project (applies when stopped; a running site is stopped first)',
		'php_default' => 'Default',
		'php_btn' => 'Change PHP',
		'php_note' => 'New versions can be downloaded from the Health page.',
		'reset' => 'Reset admin password',
		'reset_label' => 'New password (at least 6 characters)',
		'reset_btn' => 'Change Password',
		'reset_confirm' => 'Change the admin password? The old password will stop working.',
		'logs' => 'View logs · Folder',
		'new_project' => 'Create New Project',
		'np_name' => 'Project name (lowercase letters, dashes allowed)',
		'np_admin_user' => 'Admin username',
		'np_admin_email' => 'Admin email',
		'np_admin_pass' => 'Admin password',
		'np_demo' => 'Also add Urdu demo content (posts, pages, products)',
		'np_theme' => 'Theme (Online Store only)',
		'np_theme_default' => 'Default — Twenty Twenty-Five',
		'np_theme_dukaan' => 'Dukaan — Urdu RTL Store Theme',
		'np_create' => 'Create Project',
		'np_template' => 'Create from template',
		'import' => 'Import from Zip',
		'import_label' => 'Choose a TAMEERO export zip (SQLite sites only)',
		'import_btn' => 'Import',
		'lang_name' => 'English',
		'lite_on' => 'Lite mode is ON',
		'lite_off' => 'Lite mode',
		'agent_activity' => 'Agent Activity',
		'agent_pending' => 'Awaiting your approval',
		'agent_recent' => 'Recent activity',
		'agent_none_pending' => 'Nothing is waiting for approval.',
		'agent_none_recent' => 'No agent activity yet.',
		'agent_approve' => 'Approve',
		'agent_reject' => 'Reject',
		'agent_confirm_ph' => 'Confirm: type the site name',
		'agent_conn' => 'Agent Connection (MCP)',
		'agent_conn_state' => 'MCP HTTP server',
		'agent_on' => 'is ON',
		'agent_off' => 'is OFF',
		'agent_token_set' => 'Token is set',
		'agent_token_none' => 'No token yet',
		'agent_token_gen' => 'Generate New Token',
		'agent_token_revoke' => 'Revoke Token',
		'agent_turn_on' => 'Turn MCP On',
		'agent_turn_off' => 'Turn MCP Off',
		'agent_token_once' => 'Copy your token now — it will NOT be shown again:',
		'agent_conn_how' => 'To run the server, from the TAMEERO folder: php panel/bin/mcp.php serve (this machine only) or php panel/bin/mcp.php serve --bind=0.0.0.0 (for your private Tailscale network). Then put this config in your AI client (Muse, Cursor, etc.):',
		'agent_client' => 'Client',
		'h_title' => 'Health Check',
		'h_back' => '← Back to Dashboard',
		'h_version' => 'TAMEERO version',
		'h_update_btn' => 'Check for Updates',
		'h_update_apply' => 'Install Update',
		'h_update_new' => 'a new version is available',
		'h_update_latest' => 'you are on the latest version.',
		'h_update_fail' => 'The last check failed (internet?) — try again.',
		'h_update_note' => 'Note: updates come from the public GitHub repo — this works once the repo is public.',
		'h_system' => 'System',
		'h_phpver' => 'PHP version',
		'h_phpbin' => 'PHP binary',
		'h_sitesdir' => 'Projects folder',
		'h_disk' => 'Disk free / total',
		'h_projects' => 'Projects',
		'h_running_n' => 'running',
		'h_bundle_wp' => 'Bundle — WordPress',
		'h_bundle_pl' => 'Bundle — Plugins',
		'h_plugins_ready' => 'plugins ready',
		'h_notfound' => 'not found',
		'h_tools' => 'Tools folder',
		'h_ext' => 'PHP extensions',
		'h_xampp' => 'XAMPP scan',
		'h_xampp_found' => 'XAMPP found',
		'h_xampp_none' => 'XAMPP is not installed.',
		'h_xampp_running' => 'These XAMPP parts are currently running',
		'h_xampp_calm' => 'No worries — TAMEERO uses its own ports (8080+) and its own SQLite, so it never clashes with XAMPP and never touches it.',
		'h_xampp_idle' => 'No part of XAMPP is currently running.',
		'h_phpvers' => 'PHP versions',
		'h_php_dl' => 'Download',
		'h_php_note' => 'Each project card lets you pick its own PHP.',
		'h_ext_note' => 'If you see any ✗, fix that first — otherwise some features will not work.',
		'w_title' => 'Welcome!',
		'w_sub' => 'TAMEERO is ready. Create your first project in three easy steps.',
		'w_step' => 'Step',
		'w_lang' => 'Choose your language',
		'w_lite_q' => 'Is your computer old or low-powered?',
		'w_lite_hint' => 'In lite mode the dashboard runs lighter (less background work, one server worker per site). You can change this later.',
		'w_yes' => 'Yes, turn lite mode on',
		'w_no' => 'No, normal mode',
		'w_next' => 'Next Step →',
		'w_skip' => 'Skip wizard',
		'w_p_title' => 'Create Your First Project',
		'w_p_name' => 'Project name (lowercase letters, dashes allowed)',
		'w_p_pass' => 'WordPress admin password (at least 6 characters)',
		'w_create' => 'Create Project →',
		'w_done_t' => 'Congratulations! 🎉',
		'w_done' => 'Your project is being created — watch its progress on the dashboard. When it is ready, use "Open Admin" to enter WordPress.',
		'w_go' => 'Go to Dashboard →',
		'nav_home' => 'Home',
		'nav_projects' => 'Projects',
		'nav_themes' => 'Themes',
		'home_welcome' => 'Welcome!',
		'np_more' => 'More (admin user / email)',
		'recent_projects' => 'Recent Projects',
		'recent_activity' => 'Recent Activity',
		'view_all_projects' => 'View All Projects →',
		'projects_title' => 'My Projects',
		'projects_count_fmt' => '%d Projects',
		'themes_title' => 'Themes',
		'themes_current' => 'Current Themes',
		'themes_soon' => 'Coming soon: Urdu News · Urdu Blog — one theme at a time, in its turn.',
		'themes_coming' => 'Coming Soon',
		'details' => 'Details',
		'more' => 'More',
		'copy_path' => 'Copy path',
		'copied' => 'Copied ✓',
		'cat_open' => 'Open',
		'cat_data' => 'Data',
		'cat_tools' => 'Tools',
		'cat_danger' => 'Danger',
		'f_all' => 'All',
		'f_running' => 'Running',
		'f_stopped' => 'Stopped',
		'f_updates' => 'Updates Available',
		'upd_badge_fmt' => '%d Update',
		'tab_overview' => 'Overview',
		'tab_plugins' => 'Plugins',
		'tab_updates' => 'Updates',
		'tab_logs' => 'Logs',
		'tab_settings' => 'Settings',
		'det_back' => '← Back to Projects',
		'upd_none' => 'Everything is up to date.',
		'upd_wp_avail' => 'WordPress update available',
		'upd_plug_avail' => 'Plugin updates available:',
		'theme_in_use' => 'Currently used on:',
		'theme_none_use' => 'Not used on any project yet',
		'theme_preview' => 'Preview',
		'theme_use' => 'Use Theme',
		'theme_swap_soon' => 'Switching the theme of an existing project is coming soon — Use Theme currently starts a new project.',
		'theme_default_desc' => 'WordPress’s own clean default theme — a dependable base for any kind of site.',
		'theme_dukaan_desc' => 'Urdu RTL online store theme — maroon + gold, WhatsApp order button, made specially for shops.',
		'act_backup_fmt' => 'Backup created for %s',
		'act_started_fmt' => '%s started',
		'activity_none' => 'No activity recorded yet.',
		'st_running_short' => 'Running',
		'st_stopped_short' => 'Stopped',
		'lang_label' => 'Change language',
		'side_disk' => 'Disk',
		'side_free' => 'free',
		'side_ports_ok' => 'Ports OK',
		'agent_word' => 'Agent',
		'pending_word' => 'pending approval',
		'chat_title' => 'AI Assistant',
		'chat_ph' => 'Ask the AI anything…',
		'chat_collapse' => 'Collapse panel',
		'chat_expand' => 'Open AI panel',
		'sc_list' => 'List Projects',
		'sc_list_prompt' => 'Show me a list of all my projects with their status.',
		'sc_start' => 'Start All',
		'sc_start_prompt' => 'Start all stopped sites.',
		'sc_stop' => 'Stop All',
		'sc_stop_prompt' => 'Stop all running sites.',
		'sc_backup' => 'Back Up',
		'sc_backup_prompt' => 'Back up all my sites.',
		'stat_projects' => 'Total Projects',
		'stat_running' => 'Running Websites',
		'stat_disk' => 'Disk Use',
		'stat_updates' => 'Updates Pending',
		'btn_plugins' => 'Plugins',
		'btn_database' => 'Database',
		'btn_mail' => 'Mail',
		'deploy_title' => 'Live hosting package: files + MySQL dump + checklist',
		'upd_plugins_word' => 'plugins',
		'status_running_fmt' => 'Running · since %s',
		'status_stopped_fmt' => 'Stopped · last run %s',
		'site_not_found' => 'Site not found.',
		'th_plugin' => 'Plugin',
		'th_version' => 'Version',
		'th_state' => 'State',
		'plug_on' => '● On',
		'plug_off' => '○ Off',
		'plug_turn_on' => 'Turn On',
		'plug_turn_off' => 'Turn Off',
		'plugins_note' => 'All bundled plugins are installed — turn on what you need here and keep the rest off. To add a new plugin, use the WP-Admin Plugins page.',
		'db_err_open' => 'Problem opening the database:',
		'db_tables' => 'Tables',
		'db_note_fmt' => 'Table: <strong>%s</strong> — %d rows total · page %d · Click any value to edit it (be careful with serialized values — a wrong edit can break the site; take a backup first).',
		'pg_prev' => '← Previous',
		'pg_next' => 'Next →',
		'db_sql_title' => 'SQL Query (read-only — SELECT)',
		'mail_note' => 'No email from a local site goes out — everything is captured here (for testing password resets, orders, forms, etc.).',
		'mail_none' => 'No mail captured yet.',
		'th_time' => 'Time',
		'th_to' => 'To',
		'th_subject' => 'Subject',
		'mail_no_subject' => '(no subject)',
		'set_lite_title' => 'Lite Mode',
		'set_lite_text' => 'Lite mode — a lighter mode for low-power PCs (fewer sizes/background jobs).',
		'set_chat_title' => 'AI Connection',
		'set_chat_text' => 'Which AI provider the chat connects to. Pick a provider and the Endpoint URL fills in by itself. <b>Load Models</b> fetches the model list, <b>Connection Test</b> verifies the setup. The API Key is never shown back on this page; leave it blank to keep the saved key. Chat tools only run through MCP approval; the MCP token never reaches the browser.',
		'set_provider_label' => 'AI Provider',
		'set_gw_url' => 'Endpoint URL',
		'set_model_label' => 'Model (blank = gateway choice)',
		'set_gw_key_label' => 'API Key (leave blank to keep the saved key)',
		'set_models_btn' => 'Load Models',
		'set_test_btn' => 'Connection Test',
		'set_brain_note' => 'AI instructions (Brain): stored as .md files in the panel/ai-brain folder — you can edit them; upgrades will not overwrite your changes.',
		'chat_brain_warn' => 'Brain folder not found — running on default instructions (panel/ai-brain).',
		'set_chat_save' => 'Save Chat Settings',
		'set_info_title' => 'Information',
		'set_info_text_fmt' => 'TAMEERO v%s · Sites: %s<br>The dashboard only runs on this computer (127.0.0.1).',
		'my_templates' => 'My Templates',
		'tpl_meta_fmt' => ' — made from %s · %s',
		'tpl_desc' => 'To create a site from this, choose this template instead of a Blueprint in the form above. The admin will be the same as on the template site.',
		'tpl_delete_confirm' => 'Delete this template?',
		'import_note' => 'Bring back a TAMEERO export zip (or any SQLite-based WordPress site zip) here as an old/client site. MySQL zips will not work here.',
		'imp_uploading' => 'Uploading…',
		'db_edit_btn' => 'Update',
		'db_run_btn' => 'Run',
		'mail_to' => 'To',
	];
	return $en + $ur; // koi key reh jaye to Urdu fallback
}

/** Local HTTPS ki CA + certs ki jagah (feature 19). */
function hub_ca_dir() { return hub_repo() . '/panel/.ca'; }

/** Site ka HTTPS proxy chal raha hai? (.https.pid se) */
function hub_https_running( $dir ) {
	$pid = file_exists( $dir . '/.https.pid' ) ? (int) file_get_contents( $dir . '/.https.pid' ) : 0;
	return hub_pid_alive( $pid );
}

/** Site ka preview tunnel chal raha hai? (.preview.pid se — feature 15) */
function hub_preview_running( $dir ) {
	$pid = file_exists( $dir . '/.preview.pid' ) ? (int) file_get_contents( $dir . '/.preview.pid' ) : 0;
	return hub_pid_alive( $pid );
}

/** Site ke liye HTTPS port — meta mein ho to wahi, warna 8443+ se pehla khaali. */
function hub_https_port( $slug, $meta ) {
	if ( ! empty( $meta['https_port'] ) ) { return (int) $meta['https_port']; }
	$used = [];
	foreach ( hub_sites() as $s ) { if ( ! empty( $s['https_port'] ) ) { $used[] = (int) $s['https_port']; } }
	$p = 8443;
	while ( in_array( $p, $used, true ) || hub_port_listening( $p ) ) { $p++; }
	return $p;
}

/** Version muqabla: $a > $b ho to 1 (sirf pehle 3 hisse, -dev suffix nazarandaz). */
function hub_version_newer( $a, $b ) {
	$pa = array_map( 'intval', explode( '.', preg_replace( '/[^0-9.].*$/', '', (string) $a ) ) );
	$pb = array_map( 'intval', explode( '.', preg_replace( '/[^0-9.].*$/', '', (string) $b ) ) );
	for ( $i = 0; $i < 3; $i++ ) {
		$xa = $pa[ $i ] ?? 0; $xb = $pb[ $i ] ?? 0;
		if ( $xa !== $xb ) { return $xa > $xb ? 1 : -1; }
	}
	return 0;
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

/**
 * Folder poora delete (Windows par rm -rf nahi — PHP se, cross-platform).
 * Windows par kisi file ka handle kisi process (background worker,
 * antivirus, indexer) ke paas ho to unlink khamoshi se nakam hota
 * hai aur aadha folder reh jata hai (Windows CI Run #7 mein pakra
 * gaya: sab checks pass, sirf aakhri delete fail). Is liye pass
 * bounded retries ke sath dohrao — handle aam taur par chand
 * second mein chhoot jata hai. (Run #10: background workers ki
 * wajah se 4s ka window kam para — ab ~22s ka badhta window hai.)
 */
function hub_rrmdir( $dir ) {
	// Passes ke darmiyan badhti hui neend — kul ~22 second ka
	// window. Windows CI Run #10 ne dikhaya ke 4s kam par jata
	// hai: delete se pehle dashboard ke background workers (sizes
	// / backup) site ki files parh rahe hon to un ke handle kai
	// seconds tak nahi chhootte. Aam haalat mein pehla pass hi
	// kaafi hota hai; window sirf band files par kharch hota hai.
	$sleeps = [ 0, 1, 1, 2, 3, 4, 5, 6 ];
	foreach ( $sleeps as $nap ) {
		if ( $nap ) { sleep( $nap ); }
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
		clearstatcache( true, $dir );
		if ( ! is_dir( $dir ) ) { return; }
	}
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
		hub_import_trace( 'SPAWN-ENTER cmd=' . $line );
		file_put_contents( $cmdFile, "@echo off\r\n" . $line . "\r\n" );
		hub_import_trace( 'SPAWN-CMD-WRITTEN exists=' . ( file_exists( $cmdFile ) ? '1' : '0' ) . ' size=' . (int) @filesize( $cmdFile ) );
		shell_exec( 'wscript.exe ' . hub_q( hub_repo() . '/panel/bin/run-hidden.vbs' ) . ' ' . hub_q( $cmdFile ) );
		hub_import_trace( 'SPAWN-SHELLEXEC-RETURNED' );
		return;
	}
	// FD safai SAB SE PEHLE (Linux): spawn hone wali shell apne
	// tamam inherited descriptors (3+) band kare — khaas taur par
	// dashboard ka listen socket. Warna worker aur us ke tamam
	// bache us socket ko pakre rakhte hain aur dashboard band
	// hone ke baad bhi us ki port phasi rehti hai (dobara bind
	// "Address in use", connections murda queue mein latakti
	// hain — site servers AUR lambay backup/zip workers dono par
	// pakra gaya). Band FD par error shell ko nahi marta — LEKIN
	// dash 2-digit fd par fatal hota hai, us ki tafseel neeche hai.
	// (PHP ke
	// php://fd tareeqe se FD band NAHI hota — azma kar dekha gaya.)
	$clean = 'for f in /proc/$$/fd/*; do n=${f##*/}; case $n in *[!0-9]*) continue;; esac; '
		. 'if [ "$n" -gt 2 ]; then eval "exec $n>&-" 2>/dev/null; fi; done; ';
	$spawn = 'nohup ' . hub_q( hub_php() ) . ' ' . hub_q( $worker ) . ' ' . $args
		. ' > ' . hub_q( $logFile ) . ' 2>&1 &';
	// dash (aksar /bin/sh) 2-digit fd number ko redirection mein
	// NAHI manta: 'exec 10>&-' par "exec: 10: not found" keh kar
	// shell hi mar jata hai aur spawn khamoshi se gayab ho jata
	// hai. Ye tab hota hai jab bulane wali process ke paas 11+
	// fds hon (MCP server jaisi lambi process par pakra gaya,
	// 2026-11-02). bash ho to spawn usi se chalao — wo multi-digit
	// fd theek band karta hai. Na ho to dash ke liye sirf 1-digit
	// fds band karo; spawn kam az kam hamesha chale ga.
	$bash = trim( (string) shell_exec( 'command -v bash 2>/dev/null' ) );
	if ( $bash !== '' ) {
		shell_exec( hub_q( $bash ) . ' -c ' . hub_q( $clean . $spawn ) );
	} else {
		$cleanSafe = str_replace( 'if [ "$n" -gt 2 ]', 'if [ ${#n} -eq 1 ] && [ "$n" -gt 2 ]', $clean );
		shell_exec( $cleanSafe . $spawn );
	}
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
		if ( $meta && ( $meta['site'] ?? '' ) !== $name ) {
			// Folder ke naam aur meta ki pehchan mein farq — ye kisi
			// copy ke darmiyan wali aadhi haalat hai; isay asal site
			// mat samjho (warna workers ghalat meta likh dete hain).
			continue;
		}
		if ( ! $meta ) {
			// Meta nahi: ya creation chal rahi hai, ya fail/ruk gayi hai.
			$jobLog = $base . '/' . $name . '.job.log';
			$failFile = $dir . '/.create-failed.json';
			if ( file_exists( $failFile ) ) {
				// Worker ne khud failure likha hai (v2.6.2)
				$fj = (array) json_decode( (string) @file_get_contents( $failFile ), true );
				$out[] = [ 'site' => $name, 'failed' => true, 'dir' => $dir,
					'fail_step' => (string) ( $fj['step'] ?? '' ), 'fail_label' => (string) ( $fj['label'] ?? '' ),
					'fail_error' => (string) ( $fj['error'] ?? 'Creation fail ho gayi' ), 'fail_at' => (string) ( $fj['at'] ?? '' ) ];
			} elseif ( file_exists( $jobLog ) ) {
				if ( time() - (int) filemtime( $jobLog ) > 1800 ) {
					// 30 minute se koi progress nahi — worker mar gaya
					// lagta hai (power cut waghera); hamesha wali
					// "Creating..." ki bajaye saaf Failed state.
					$out[] = [ 'site' => $name, 'failed' => true, 'dir' => $dir,
						'fail_step' => 'stale', 'fail_label' => '',
						'fail_error' => 'Creation ruk gayi lagti hai — 30 minute se koi progress nahi hui', 'fail_at' => '' ];
				} else {
					$out[] = [ 'site' => $name, 'creating' => true, 'dir' => $dir ];
				}
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

/** Blueprint ki declared optional theme (blueprint.json ka "theme"
 * field) — na ho to ''. Create form isi se choice validate karta hai. */
function hub_blueprint_theme( $bpId ) {
	$f = hub_bundle_dir() . '/blueprints/' . $bpId . '/blueprint.json';
	if ( ! file_exists( $f ) ) { return ''; }
	$bp = json_decode( (string) file_get_contents( $f ), true );
	return is_array( $bp ) ? (string) ( $bp['theme'] ?? '' ) : '';
}

function hub_create_async( $slug, $blueprint, $user, $email, $pass, $template = '', $demo = false, $theme = '' ) {
	$base = hub_sites_dir();
	if ( ! is_dir( $base ) ) { mkdir( $base, 0775, true ); }
	$log = $base . '/' . $slug . '.job.log';
	$port = hub_next_port();
	$args = 'create --site ' . hub_q( $slug )
		. ' --blueprint ' . hub_q( $blueprint )
		. ' --port ' . (int) $port;
	if ( $template !== '' ) { $args .= ' --template ' . hub_q( $template ); }
	if ( $demo ) { $args .= ' --demo-content 1'; }
	if ( $theme !== '' ) { $args .= ' --theme ' . hub_q( $theme ); }
	hub_spawn_worker(
		$args,
		[
			'WDH_ADMIN_USER'  => $user,
			'WDH_ADMIN_EMAIL' => $email,
			'WDH_ADMIN_PASS'  => $pass,
		],
		$log
	);
	return $log;
}

/** Clone background mein (create ki tarah job log + Creating card) */
function hub_clone_async( $src, $new ) {
	$base = hub_sites_dir();
	$log = $base . '/' . $new . '.job.log';
	hub_spawn_worker( 'clone --site ' . hub_q( $src ) . ' --name ' . hub_q( $new ), [], $log );
	return $log;
}

function hub_totemplate_async( $src, $name ) {
	$base = hub_templates_dir();
	if ( ! is_dir( $base ) ) { mkdir( $base, 0775, true ); }
	$log = $base . '/' . $name . '.job.log';
	hub_spawn_worker( 'totemplate --site ' . hub_q( $src ) . ' --name ' . hub_q( $name ), [], $log );
	return $log;
}

/** v2.8.8: import trace - handler/spawn ka har qadam file mein likho,
 *  har line ke baad file band (file_put_contents khud kholta-band karta
 *  hai). Agar response kabhi na aaye (jaise run #30 ka 300s timeout) to
 *  bhi likha hua mehfooz rehta hai; aakhri marker batata hai kaunsa
 *  qadam ruka. Warning suppress (@) is liye ke trace ki kharabi kabhi
 *  asal response (JSON) ko kharab na kare. */
function hub_import_trace( $msg ) {
	@file_put_contents( hub_repo() . '/panel/.import-trace.log',
		date( 'Y-m-d H:i:s' ) . ' ' . $msg . "\n", FILE_APPEND );
}

function hub_import_async( $slug, $zipPath ) {
	$base = hub_sites_dir();
	$log = $base . '/' . $slug . '.job.log';
	hub_spawn_worker( 'import --site ' . hub_q( $slug ) . ' --zip ' . hub_q( $zipPath ), [], $log );
	return $log;
}

/** Chunked import (v2.8.14) - bari zip 8 MiB tukron mein aati hai.
 * Har chunk apni .part file mein; finalize par tartib se jor kar
 * <name>.zip banta hai, phir hub_import_async() (worker unchanged).
 * upload_id sirf [A-Za-z0-9-] hota hai, is liye parts dir ka naam
 * kabhi path traversal nahi ban sakta. */
define( 'HUB_CHUNK_BYTES', 8 * 1024 * 1024 );

function hub_import_parts_dir( $uploadId ) {
	return hub_sites_dir() . '/.imports/' . $uploadId . '.parts';
}

function hub_import_upload_id_ok( $uploadId ) {
	return (bool) preg_match( '/^[A-Za-z0-9][A-Za-z0-9\-]{0,63}$/', (string) $uploadId );
}

/** Chunk CORS - dashboard origin (7777) se companion origin (7827)
 * par POST: sirf 127.0.0.1/localhost origin reflect karo. */
function hub_cors_local() {
	$origin = (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' );
	if ( preg_match( '#^https?://(127\.0\.0\.1|localhost)(:\d+)?$#', $origin ) ) {
		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Vary: Origin' );
		return true;
	}
	return false;
}

/** Aik chunk save karo: php://input se 1MB blocks mein parho,
 * likhte waqt SHA-256 gino, header wale hash se milao. Galat hash
 * ya 8 MiB se bara chunk ho to .part delete karke wajah batao. */
function hub_import_chunk_save( $uploadId, $index, $sha256 ) {
	$dir = hub_import_parts_dir( $uploadId );
	if ( ! is_dir( $dir ) ) { mkdir( $dir, 0775, true ); }
	$part = $dir . '/' . (int) $index . '.part';
	$in = fopen( 'php://input', 'rb' );
	$out = fopen( $part, 'wb' );
	if ( ! $in || ! $out ) {
		if ( $in ) { fclose( $in ); }
		if ( $out ) { fclose( $out ); }
		@unlink( $part );
		return false;
	}
	$ctx = hash_init( 'sha256' );
	$bytes = 0;
	while ( ! feof( $in ) ) {
		$buf = fread( $in, 1048576 );
		if ( $buf === false ) { break; }
		$bytes += strlen( $buf );
		if ( $bytes > HUB_CHUNK_BYTES ) { break; }
		hash_update( $ctx, $buf );
		fwrite( $out, $buf );
	}
	fclose( $in );
	fclose( $out );
	if ( $bytes <= 0 ) { @unlink( $part ); return false; }
	if ( $bytes > HUB_CHUNK_BYTES ) { @unlink( $part ); return 'toobig'; }
	$got = hash_final( $ctx );
	if ( $sha256 !== '' && ( strlen( $sha256 ) !== 64 || ! hash_equals( strtolower( $sha256 ), $got ) ) ) {
		@unlink( $part );
		return 'hash';
	}
	return [ 'index' => (int) $index, 'bytes' => $bytes, 'sha256' => $got ];
}

/** Saare parts tartib se joro: size verify, SHA-256 gino, atomic
 * rename se <name>.zip banao. Koi part ghayab ya size ghalat ho
 * to tmp delete karke wajah batao. */
function hub_import_assemble( $uploadId, $name, $totalChunks, $totalSize ) {
	$dir = hub_import_parts_dir( $uploadId );
	$importDir = hub_sites_dir() . '/.imports';
	if ( ! is_dir( $importDir ) ) { mkdir( $importDir, 0775, true ); }
	$tmp = $importDir . '/' . $name . '.zip.tmp';
	$out = fopen( $tmp, 'wb' );
	if ( ! $out ) { return false; }
	$ctx = hash_init( 'sha256' );
	$size = 0;
	for ( $i = 0; $i < $totalChunks; $i++ ) {
		$part = $dir . '/' . $i . '.part';
		if ( ! is_file( $part ) ) {
			fclose( $out );
			@unlink( $tmp );
			return 'missing';
		}
		$in = fopen( $part, 'rb' );
		if ( ! $in ) {
			fclose( $out );
			@unlink( $tmp );
			return false;
		}
		while ( ! feof( $in ) ) {
			$buf = fread( $in, 1048576 );
			if ( $buf === false ) { break; }
			$size += strlen( $buf );
			hash_update( $ctx, $buf );
			fwrite( $out, $buf );
		}
		fclose( $in );
	}
	fclose( $out );
	if ( $size !== (int) $totalSize ) {
		@unlink( $tmp );
		return 'size';
	}
	$zipPath = $importDir . '/' . $name . '.zip';
	// Windows par rename mojood file ke upar nahi hota (Linux par
	// hota hai) - pehle purani zip hatao, phir rename ka natija dekho.
	// Nakam rename par kamyabi ka jawab dena ghalat purani zip ko
	// worker tak pahuncha deta (dobara finalize ki surat mein).
	if ( file_exists( $zipPath ) ) { @unlink( $zipPath ); }
	if ( ! rename( $tmp, $zipPath ) ) {
		@unlink( $tmp );
		return false;
	}
	return [ 'zip' => $zipPath, 'size' => $size, 'sha256' => hash_final( $ctx ) ];
}

/** 24 ghante se purani .parts dirs saf karo (adhoore uploads). */
function hub_import_cleanup_stale_parts() {
	$importDir = hub_sites_dir() . '/.imports';
	if ( ! is_dir( $importDir ) ) { return; }
	$found = glob( $importDir . '/*.parts' );
	if ( ! is_array( $found ) ) { return; }
	foreach ( $found as $d ) {
		if ( is_dir( $d ) && ( time() - (int) filemtime( $d ) ) > 86400 ) {
			hub_rrmdir( $d );
		}
	}
}

/** Site ke mail catcher ki jagah (feature 6). */
function hub_mail_dir( $dir ) {
	return $dir . '/wp-content/webdevhub-mail';
}

/** Pakri gayi mails ki tadaad (card ke badge ke liye). */
function hub_mail_count( $dir ) {
	$md = hub_mail_dir( $dir );
	if ( ! is_dir( $md ) ) { return 0; }
	return count( glob( $md . '/*.json' ) ?: [] );
}

/** Pakri gayi mails — nayi pehle. Har item: id (file naam), at, to, subject, message, headers. */
function hub_mail_list( $dir ) {
	$md = hub_mail_dir( $dir );
	if ( ! is_dir( $md ) ) { return []; }
	$files = glob( $md . '/*.json' ) ?: [];
	rsort( $files );
	$out = [];
	foreach ( $files as $f ) {
		$m = json_decode( (string) file_get_contents( $f ), true );
		if ( ! is_array( $m ) ) { continue; }
		$m['id'] = basename( $f );
		$out[] = $m;
	}
	return $out;
}

/** Update khabar purani to nahi (ya kabhi check hi nahi hui)? (feature 10) */
function hub_updates_stale( $meta ) {
	if ( empty( $meta['upd_at'] ) ) { return true; }
	$t = strtotime( (string) $meta['upd_at'] );
	return $t === false || ( time() - $t ) > 86400;
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
	// Asal natija wapas karo — folder reh gaya ho to dashboard
	// "delete ho gaya" ka jhoota paigham nahi dega.
	clearstatcache( true, $real_dir );
	return ! is_dir( $real_dir );
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

/**
 * Creating site ki live progress — job log ke STEP markers se.
 * Return: ['step'=>int,'total'=>int,'label'=>string,'done'=>int] ya null.
 */
function hub_create_progress( $slug ) {
	$log = hub_sites_dir() . '/' . $slug . '.job.log';
	if ( ! file_exists( $log ) ) { return null; }
	$tail = @file_get_contents( $log );
	if ( $tail === false ) { return null; }
	// Aakhri 64KB hi kafi hai
	if ( strlen( $tail ) > 65536 ) { $tail = substr( $tail, -65536 ); }
	$step = 0; $total = 0; $label = ''; $done = 0;
	foreach ( explode( "\n", $tail ) as $line ) {
		if ( preg_match( '/^## STEP (\d+)\/(\d+) key=\S+ (.*)$/', trim( $line ), $m ) ) {
			$step = (int) $m[1]; $total = (int) $m[2]; $label = trim( $m[3] );
		} elseif ( preg_match( '/^## STEP_OK (\d+)\//', trim( $line ), $m ) ) {
			$done = max( $done, (int) $m[1] );
		}
	}
	if ( $total === 0 ) { return null; }
	return [ 'step' => $step, 'total' => $total, 'label' => $label, 'done' => $done ];
}

/** Kisi site ke job log ki aakhri lines (dashboard Log view ke liye). */
function hub_job_log_tail( $slug, $lines = 60 ) {
	$log = hub_sites_dir() . '/' . $slug . '.job.log';
	if ( ! file_exists( $log ) ) { return ''; }
	$all = @file( $log, FILE_IGNORE_NEW_LINES );
	if ( $all === false ) { return ''; }
	return implode( "\n", array_slice( $all, -$lines ) );
}

/* ---------------- MCP agent actions (v2.7) ----------------
 * Approval queue ka shared hissa — MCP server (mcp.php) action
 * banata hai, dashboard yahan se parh/kar faisla karta hai.
 * Ek action = ek JSON file panel/.mcp-actions/act_*.json mein.
 */

function hub_mcp_actions_dir() {
	// Sirf rasta — folder NAHI banta. Sirf parhna (list/count)
	// disk par kuch na banaye: warna dashboard khulte hi ye
	// folder ban jata tha aur uninstall ke baad {app}\panel
	// reh jata tha (v2.7.0, Windows Build #21 mein pakra gaya).
	// Folder sirf hub_mcp_action_save() banata hai.
	return hub_repo() . '/panel/.mcp-actions';
}

function hub_mcp_action_save( array $rec ) {
	$d = hub_mcp_actions_dir();
	if ( ! is_dir( $d ) ) { @mkdir( $d, 0777, true ); }
	file_put_contents( $d . '/' . $rec['id'] . '.json', json_encode( $rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
}

/** Pending action ki miyad khatam ho to status expired kar do. */
function hub_mcp_action_reconcile( array $rec ) {
	if ( ( $rec['status'] ?? '' ) === 'pending' && time() > (int) ( $rec['expires_at'] ?? 0 ) ) {
		$rec['status'] = 'expired';
		hub_mcp_action_save( $rec );
	}
	return $rec;
}

function hub_mcp_action_get( $id ) {
	if ( ! preg_match( '/^act_[a-f0-9]{12}$/', (string) $id ) ) { return null; }
	$f = hub_mcp_actions_dir() . '/' . $id . '.json';
	if ( ! file_exists( $f ) ) { return null; }
	$rec = json_decode( (string) file_get_contents( $f ), true );
	return is_array( $rec ) ? hub_mcp_action_reconcile( $rec ) : null;
}

function hub_mcp_actions_list( $limit = 30 ) {
	$out = [];
	foreach ( glob( hub_mcp_actions_dir() . '/act_*.json' ) ?: [] as $f ) {
		$rec = json_decode( (string) file_get_contents( $f ), true );
		if ( is_array( $rec ) ) { $out[] = hub_mcp_action_reconcile( $rec ); }
	}
	usort( $out, function ( $a, $b ) { return ( $b['created_ts'] ?? 0 ) <=> ( $a['created_ts'] ?? 0 ); } );
	return array_slice( $out, 0, $limit );
}

function hub_mcp_pending_count() {
	$n = 0;
	foreach ( hub_mcp_actions_list( 100 ) as $r ) { if ( ( $r['status'] ?? '' ) === 'pending' ) { $n++; } }
	return $n;
}

/** Owner ka faisla darj karo. true ya error string return hota hai. */
function hub_mcp_action_decide( $id, $approve, $confirm = '' ) {
	$rec = hub_mcp_action_get( $id );
	if ( ! $rec ) { return 'Action nahi mila.'; }
	if ( ( $rec['status'] ?? '' ) !== 'pending' ) { return 'Ye action ab pending nahi hai (haal: ' . $rec['status'] . ').'; }
	if ( ( $rec['class'] ?? '' ) === 'destructive' && $confirm !== (string) ( $rec['site'] ?? '' ) ) {
		return 'Confirm ke liye site ka exact naam likhein: ' . ( $rec['site'] ?? '' );
	}
	$rec['status'] = $approve ? 'approved' : 'rejected';
	$rec['decided_at'] = date( 'c' );
	$rec['decided_by'] = 'owner-dashboard';
	hub_mcp_action_save( $rec );
	hub_mcp_audit_append( 'dashboard', (string) $rec['tool'], (string) ( $rec['summary'] ?? '' ), $approve ? 'owner-approved' : 'owner-rejected' );
	return true;
}

/** Audit log (mcp.php ke sath shared) — JSONL append. */
function hub_mcp_audit_append( $client, $tool, $summary, $result ) {
	$entry = [ 'at' => date( 'c' ), 'client' => $client, 'tool' => $tool, 'summary' => $summary, 'decision' => 'owner', 'result' => $result ];
	@file_put_contents( hub_repo() . '/panel/.mcp-audit.log', json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND | LOCK_EX );
}

function hub_mcp_audit_tail( $n = 15 ) {
	$f = hub_repo() . '/panel/.mcp-audit.log';
	if ( ! file_exists( $f ) ) { return []; }
	$lines = file( $f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: [];
	$out = [];
	foreach ( array_slice( $lines, -$n ) as $l ) {
		$e = json_decode( $l, true );
		if ( is_array( $e ) ) { $out[] = $e; }
	}
	return array_reverse( $out );
}

/* ---------------- MCP server process (v2.8) ----------------
 * v2.7 tak "Turn MCP On" sirf ek flag tha — server haath se chalana
 * parta tha (pending-fixes #4, field mein pakra gaya). Ab dashboard
 * asal process spawn/stop karta hai. PID file SIRF spawn ke waqt
 * (mcp.php serve khud) aur stop par likhi/mitti hai — status sirf
 * PARHTA hai taake aam dashboard istemal par koi file na bane
 * (v2.7.1 wali uninstall-safai wali ghalti na dohraye). */

function hub_mcp_pidfile() { return hub_repo() . '/panel/.mcp-server.pid'; }
function hub_mcp_server_log() { return hub_repo() . '/panel/.mcp-server.log'; }
function hub_mcp_port() { return 8971; }

/** Sirf parhta hai — koi file nahi banata. (hub_pid_alive upar mojood hai.) */
function hub_mcp_server_status() {
	$pid = 0;
	$f = hub_mcp_pidfile();
	if ( file_exists( $f ) ) { $pid = (int) trim( (string) file_get_contents( $f ) ); }
	$running = $pid > 0 && hub_pid_alive( $pid );
	return [ 'running' => $running, 'pid' => $running ? $pid : 0, 'port' => hub_mcp_port() ];
}

/**
 * Lambi process ko dashboard se bilkul alag (detached) chalao —
 * hub_spawn_worker wala hi azmaya hua tareeqa (Windows: hidden
 * .cmd via wscript; Linux: fd-safai ke sath nohup, bash route).
 * $tail poori command line hai (pehle se quoted).
 */
function hub_spawn_long( $tail, $logFile ) {
	if ( hub_is_windows() ) {
		$runDir = hub_repo() . '/panel/.run';
		if ( ! is_dir( $runDir ) ) { mkdir( $runDir, 0775, true ); }
		$cmdFile = $runDir . '/long-' . bin2hex( random_bytes( 6 ) ) . '.cmd';
		file_put_contents( $cmdFile, "@echo off\r\n" . $tail . ' > ' . hub_q( $logFile ) . " 2>&1\r\n" );
		shell_exec( 'wscript.exe ' . hub_q( hub_repo() . '/panel/bin/run-hidden.vbs' ) . ' ' . hub_q( $cmdFile ) );
		return;
	}
	$clean = 'for f in /proc/$$/fd/*; do n=${f##*/}; case $n in *[!0-9]*) continue;; esac; '
		. 'if [ "$n" -gt 2 ]; then eval "exec $n>&-" 2>/dev/null; fi; done; ';
	$spawn = 'nohup ' . $tail . ' > ' . hub_q( $logFile ) . ' 2>&1 &';
	$bash = trim( (string) shell_exec( 'command -v bash 2>/dev/null' ) );
	if ( $bash !== '' ) {
		shell_exec( hub_q( $bash ) . ' -c ' . hub_q( $clean . $spawn ) );
	} else {
		$cleanSafe = str_replace( 'if [ "$n" -gt 2 ]', 'if [ ${#n} -eq 1 ] && [ "$n" -gt 2 ]', $clean );
		shell_exec( $cleanSafe . $spawn );
	}
}

/** MCP HTTP server start karo (pehle se chal raha ho to wahi status). */
function hub_mcp_server_start() {
	$st = hub_mcp_server_status();
	if ( $st['running'] ) { return $st; }
	$bind = (string) ( hub_settings()['mcp_bind'] ?? '127.0.0.1' );
	if ( ! preg_match( '/^[0-9a-zA-Z\.\-]+$/', $bind ) ) { $bind = '127.0.0.1'; }
	$cmd = hub_q( hub_php() ) . ' ' . hub_q( hub_repo() . '/panel/bin/mcp.php' )
		. ' serve --bind=' . $bind . ' --port=' . hub_mcp_port();
	hub_spawn_long( $cmd, hub_mcp_server_log() );
	// mcp.php serve apna PID khud likhta hai — thoda intezar karo.
	for ( $i = 0; $i < 24; $i++ ) {
		$st = hub_mcp_server_status();
		if ( $st['running'] ) { return $st; }
		usleep( 150000 );
	}
	return hub_mcp_server_status();
}

/**
 * Import ke liye sathi (companion) upload server ka origin —
 * Windows par dashboard single-thread hota hai, is liye launcher
 * ek dusra php -S (upload_port) bhi chalata hai jo sirf bari
 * uploads sambhalta hai; dashboard responsive rehta hai. Linux
 * par PHP_CLI_SERVER_WORKERS pehle hi ye kaam karta hai — wahan
 * state file mein upload_port nahi hota aur ye khali ('') deta
 * hai (form aam tareeqe se usi port par post kare ga).
 * Sirf tab origin deta hai jab companion asal mein jawab de
 * (probe 20 second cache ke sath, .run ke andar). */
function hub_upload_base() {
	$stateFile = hub_repo() . '/panel/.dashboard-state.json';
	if ( ! file_exists( $stateFile ) ) { return ''; }
	$state = json_decode( (string) file_get_contents( $stateFile ), true );
	$port = (int) ( $state['upload_port'] ?? 0 );
	if ( $port <= 0 ) { return ''; }
	$cacheDir = hub_repo() . '/panel/.run';
	$cache = $cacheDir . '/upload-probe.json';
	if ( file_exists( $cache ) && ( time() - filemtime( $cache ) ) < 20 ) {
		$c = json_decode( (string) file_get_contents( $cache ), true );
		if ( is_array( $c ) && ( $c['port'] ?? 0 ) === $port ) { return ! empty( $c['ok'] ) ? 'http://127.0.0.1:' . $port : ''; }
	}
	$ok = false;
	$fp = @fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.25 );
	if ( $fp ) { $ok = true; fclose( $fp ); }
	if ( ! is_dir( $cacheDir ) ) { mkdir( $cacheDir, 0775, true ); }
	@file_put_contents( $cache, json_encode( [ 'port' => $port, 'ok' => $ok ] ) );
	return $ok ? 'http://127.0.0.1:' . $port : '';
}

/** MCP HTTP server band karo (PID file wali process hi mare gi). */
function hub_mcp_server_stop() {
	$f = hub_mcp_pidfile();
	$pid = file_exists( $f ) ? (int) trim( (string) file_get_contents( $f ) ) : 0;
	if ( $pid > 0 && hub_pid_alive( $pid ) ) {
		if ( hub_is_windows() ) { shell_exec( 'taskkill /PID ' . $pid . ' /F 2>nul' ); }
		else { shell_exec( 'kill ' . $pid . ' 2>/dev/null' ); }
		for ( $i = 0; $i < 10; $i++ ) { if ( ! hub_pid_alive( $pid ) ) { break; } usleep( 100000 ); }
	}
	if ( file_exists( $f ) ) { @unlink( $f ); }
	return hub_mcp_server_status();
}

