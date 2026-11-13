<?php
/**
 * TAMEERO MCP server — khula MCP standard (kisi ek AI se lock nahi).
 *
 * Modes:
 *   php mcp.php gen-token          Token banao (ek dafa dikhe ga),
 *                                  hash settings mein save hoga
 *   php mcp.php stdio              stdio transport (local clients)
 *   php mcp.php serve [--bind=127.0.0.1] [--port=8971]
 *                                  HTTP transport — default sirf
 *                                  localhost; token lazmi
 *
 * Usool (owner faisle 2026-10-31): MCP sirf interface hai — andar
 * wahi machinery hai jo dashboard chalata hai (hub.php + wdh.php
 * worker). Write tools approval queue se guzarte hain (step 3+).
 */
// CLI se chalega; dashboard ka chat backend (v2.8) ise library ke
// tor par include karta hai — us ke liye TAMEERO_MCP_LIBRARY lazmi
// hai, warna koi web request ise seedha nahi utha sakti.
if ( php_sapi_name() !== 'cli' && ! defined( 'TAMEERO_MCP_LIBRARY' ) ) { exit( "CLI only\n" ); }

require_once dirname( __DIR__ ) . '/lib/hub.php';

/* ---------------- token ---------------- */

function mcp_token_hash() {
	$s = hub_settings();
	return (string) ( $s['mcp_token_hash'] ?? '' );
}

function mcp_check_token( $token ) {
	$h = mcp_token_hash();
	return $h !== '' && $token !== '' && hash_equals( $h, hash( 'sha256', (string) $token ) );
}

/* ---------------- audit ---------------- */

function mcp_audit( $tool, $summary, $decision, $result ) {
	$entry = [
		'at'       => date( 'c' ),
		'client'   => $GLOBALS['mcp_client'] ?? 'unknown',
		'tool'     => $tool,
		'summary'  => $summary,
		'decision' => $decision,
		'result'   => $result,
	];
	@file_put_contents( hub_repo() . '/panel/.mcp-audit.log', json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND | LOCK_EX );
}

/* ---------------- andar ki machinery ----------------
 * wdh.php wala hi usool: child process file-redirect par (pipe
 * kabhi nahi), timeout ke sath.
 */

function mcp_run_json( array $cmd, $timeout = 120 ) {
	$tmp = tempnam( sys_get_temp_dir(), 'mcp-run-' );
	// v2.14.1: array-form proc_open — hath ki banayi "cmd /c ... > file" line
	// Windows par quotes tor deti thi (cmd /c ke quote usool + PHP ka apna
	// cmd wrap = do dafa cmd /c; OS error: "The filename, directory name,
	// or volume label syntax is incorrect" — owner-PC par pakra gaya,
	// v2.7.1 se chhupa bug). Ab: koi shell string nahi, output descriptors
	// ki file se (wdh_run_child wala sabit pattern), Windows par
	// bypass_shell ke sath cmd.exe equation se hi bahar — is ke liye
	// pehla element (hub_php) hamesha absolute path hota hai.
	$descriptors = [
		0 => [ 'file', hub_is_windows() ? 'NUL' : '/dev/null', 'r' ],
		1 => [ 'file', $tmp, 'a' ],
		2 => [ 'file', $tmp, 'a' ],
	];
	$opts = hub_is_windows() ? [ 'bypass_shell' => true ] : [];
	$proc = proc_open( $cmd, $descriptors, $pipes, null, null, $opts );
	if ( ! is_resource( $proc ) ) { @unlink( $tmp ); throw new Exception( 'Tool start nahi hua' ); }
	$start = time();
	$exit = null;
	while ( true ) {
		$st = proc_get_status( $proc );
		if ( ! $st['running'] ) { $exit = $st['exitcode']; break; }
		if ( time() - $start > $timeout ) {
			if ( hub_is_windows() ) { shell_exec( 'taskkill /F /T /PID ' . (int) $st['pid'] . ' >NUL 2>&1' ); }
			else { @shell_exec( 'pkill -9 -P ' . (int) $st['pid'] . ' 2>/dev/null' ); proc_terminate( $proc, 9 ); }
			proc_close( $proc ); @unlink( $tmp );
			throw new Exception( "Tool timeout ($timeout s)" );
		}
		usleep( 200000 );
	}
	proc_close( $proc );
	$out = (string) @file_get_contents( $tmp );
	@unlink( $tmp );
	$a = strpos( $out, '{' );
	$b = strrpos( $out, '}' );
	if ( $a === false || $b === false ) { throw new Exception( 'Tool se JSON nahi aya: ' . substr( trim( $out ), 0, 200 ) ); }
	$data = json_decode( substr( $out, $a, $b - $a + 1 ), true );
	if ( ! is_array( $data ) ) { throw new Exception( 'Tool ka JSON parh nahi saka' ); }
	if ( ! empty( $data['error'] ) ) { throw new Exception( (string) $data['error'] ); }
	return $data;
}

/** Site argument ki tasdeeq — slug + dir + meta, warna exception. */
function mcp_site_arg( array $args ) {
	$slug = (string) ( $args['site'] ?? '' );
	if ( ! hub_slug_ok( $slug ) ) { throw new Exception( 'Site ka slug ghalat hai' ); }
	$dir = hub_sites_dir() . '/' . $slug;
	$meta = hub_site_meta( $dir );
	if ( ! $meta ) { throw new Exception( "Site nahi mili: $slug" ); }
	return [ $slug, $dir, $meta ];
}

/** wc-tool.php chalao (WP bootstrap wala WP-level kaam). */
function mcp_wc( $slug, array $modeArgs ) {
	list( , $dir, $meta ) = mcp_site_arg( [ 'site' => $slug ] );
	$url = 'http://127.0.0.1:' . (int) $meta['port'];
	$cmd = [ hub_php(), hub_tools_dir() . '/wc-tool.php', '--path=' . $dir, '--url=' . $url ];
	foreach ( $modeArgs as $k => $v ) { $cmd[] = $v === true ? '--' . $k : '--' . $k . '=' . $v; }
	return mcp_run_json( $cmd );
}

function mcp_verify( $slug ) {
	list( , $dir, $meta ) = mcp_site_arg( [ 'site' => $slug ] );
	$url = 'http://127.0.0.1:' . (int) $meta['port'];
	return mcp_run_json( [ hub_php(), hub_tools_dir() . '/setup-site.php', '--path=' . $dir, '--url=' . $url, '--verify' ] );
}


/** WP + Woo versions baghair bootstrap ke (file headers se). */
function mcp_versions( $dir ) {
	$wp = '';
	$vf = $dir . '/wp-includes/version.php';
	if ( file_exists( $vf ) && preg_match( "/\\\$wp_version = '([^']+)'/", (string) file_get_contents( $vf ), $m ) ) { $wp = $m[1]; }
	$woo = '';
	foreach ( hub_site_plugins( $dir ) as $p ) {
		if ( ( $p['slug'] ?? '' ) === 'woocommerce' ) { $woo = $p['version']; break; }
	}
	return [ 'wordpress' => $wp, 'woocommerce' => $woo ];
}

function mcp_site_schema() {
	return [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string', 'description' => 'Site ka slug (masalan dukaan)' ] ], 'required' => [ 'site' ] ];
}

/* ---------------- tools registry ----------------
 * Har tool: description, inputSchema, class (read|write|destructive),
 * handler. WRITE/DESTRUCTIVE step 3 (approval queue) ke baad active
 * honge — yahan sirf registry ka dhancha hai.
 */

function mcp_tools() {
	$site = mcp_site_schema();
	return [
		'server_info' => [
			'description' => 'TAMEERO MCP server ki maloomat: version, PHP, OS, sites folder, kitni sites hain.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			'class'       => 'read',
			'handler'     => 'mcp_tool_server_info',
		],
		'sites_list' => [
			'description' => 'Saari sites ki list: slug, blueprint, port, URL, state (running/stopped/creating/failed), title.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			'class'       => 'read',
			'handler'     => 'mcp_tool_sites_list',
		],
		'site_status' => [
			'description' => 'Ek site ki tafseel: meta, chal rahi hai ya nahi, WP/Woo versions, pages, products ki ginati, store Live hai ya Coming Soon.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_site_status',
		],
		'site_health' => [
			'description' => 'System health (PHP extensions, disk) + site ka verify khulasa.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_site_health',
		],
		'site_logs' => [
			'description' => 'Site ke aakhri logs: creation job log aur server log ke aakhri hisse.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_site_logs',
		],
		'products_list' => [
			'description' => 'Store ke products: naam, price, sale price, SKU, stock, categories, image.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_products_list',
		],
		'product_get' => [
			'description' => 'Ek product ki poori tafseel: description, images, permalink.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string' ], 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'site', 'id' ] ],
			'class'       => 'read',
			'handler'     => 'mcp_tool_product_get',
		],
		'pages_list' => [
			'description' => 'Site ke pages: title, slug, status, URL.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_pages_list',
		],
		'posts_list' => [
			'description' => 'Site ke blog posts: title, slug, status, tareekh, URL.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_posts_list',
		],
		'plugins_list' => [
			'description' => 'Site ke plugins: naam, version, active hai ya nahi.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_plugins_list',
		],
		'themes_list' => [
			'description' => 'Site ki themes: naam, version, active theme kaunsi hai.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_themes_list',
		],
		'backups_list' => [
			'description' => 'Site ke backups (aakhri 2 rakhe jate hain): file, size, tareekh.',
			'inputSchema' => $site,
			'class'       => 'read',
			'handler'     => 'mcp_tool_backups_list',
		],
		'action_status' => [
			'description' => 'Ek pending/approved action ki haalat: pending, approved, rejected, done, error, expired, cancelled + natija.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'action_id' => [ 'type' => 'string' ] ], 'required' => [ 'action_id' ] ],
			'class'       => 'read',
			'handler'     => 'mcp_tool_action_status',
		],
		'action_cancel' => [
			'description' => 'Ek pending action ko cancel karna (sirf pending par asar hota hai).',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'action_id' => [ 'type' => 'string' ] ], 'required' => [ 'action_id' ] ],
			'class'       => 'read',
			'handler'     => 'mcp_tool_action_cancel',
		],
		'site_start' => [
			'description' => 'Site ko chalana (start). Owner ki manzoori ke baad chalta hai.',
			'inputSchema' => $site,
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_start',
		],
		'site_stop' => [
			'description' => 'Site ko band karna (stop). Owner ki manzoori ke baad chalta hai.',
			'inputSchema' => $site,
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_stop',
		],
		'site_create' => [
			'description' => 'Nayi site banana (blueprint ya template se). Manzoori ke baad creation shuru hoti hai — asal haal sites_list/site_status se milega.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string', 'description' => 'Nayi site ka naam (slug)' ],
				'blueprint' => [ 'type' => 'string', 'description' => 'business, store, blog, restaurant waghera (default business)' ],
				'template' => [ 'type' => 'string', 'description' => 'Apni template ka naam (ho to blueprint ki jagah yahi lagegi)' ],
				'demo_content' => [ 'type' => 'boolean', 'description' => 'Store ke liye Urdu demo products (default false)' ],
				'admin_user' => [ 'type' => 'string' ],
				'admin_email' => [ 'type' => 'string' ],
				'admin_password' => [ 'type' => 'string', 'description' => 'Na diya jaye to bana kar natije mein diya jayega' ],
			], 'required' => [ 'site' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_create',
		],
		'site_clone' => [
			'description' => 'Site ki copy naye naam se banana. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string' ], 'name' => [ 'type' => 'string', 'description' => 'Clone ka naam' ] ], 'required' => [ 'site', 'name' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_clone',
		],
		'template_create' => [
			'description' => 'Site se reusable template banana. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string' ], 'name' => [ 'type' => 'string', 'description' => 'Template ka naam' ] ], 'required' => [ 'site', 'name' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_template_create',
		],
		'site_backup' => [
			'description' => 'Site ka backup zip banana (aakhri 2 backups rakhe jate hain). Manzoori ke baad chalta hai.',
			'inputSchema' => $site,
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_backup',
		],
		'site_import' => [
			'description' => 'Ek export/backup zip se nayi site banana. Zip ka path is machine par hona chahiye. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string', 'description' => 'Nayi site ka naam' ], 'zip' => [ 'type' => 'string', 'description' => 'Zip file ka poora path' ] ], 'required' => [ 'site', 'zip' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_import',
		],
		'admin_password_reset' => [
			'description' => 'Site ke admin ka password reset karna. Password na diya jaye to bana kar SIRF is action ke natije mein diya jayega. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string' ], 'password' => [ 'type' => 'string' ] ], 'required' => [ 'site' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_admin_password_reset',
		],
		'deploy_package' => [
			'description' => 'Site ka deploy package (zip + database.sql + checklist) banana — live hosting par le jane ke liye. Manzoori ke baad chalta hai.',
			'inputSchema' => $site,
			'class'       => 'write',
			'handler'     => 'mcp_tool_deploy_package',
		],
		'site_php_switch' => [
			'description' => 'Site ki PHP line badalna (masalan 8.3). Khaali value = default. Chalti site band ho jayegi — phir site_start se chalegi. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string' ], 'php_line' => [ 'type' => 'string', 'description' => 'Masalan "8.3" — khaali = default' ] ], 'required' => [ 'site', 'php_line' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_site_php_switch',
		],
		'product_add' => [
			'description' => 'Store mein naya product dalna. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'name' => [ 'type' => 'string' ],
				'price' => [ 'type' => 'string', 'description' => 'Regular price, masalan "2500"' ],
				'sale_price' => [ 'type' => 'string' ], 'sku' => [ 'type' => 'string' ],
				'stock' => [ 'type' => 'integer' ], 'description' => [ 'type' => 'string' ],
				'status' => [ 'type' => 'string', 'description' => 'publish ya draft' ],
			], 'required' => [ 'site', 'name' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_product_add',
		],
		'product_edit' => [
			'description' => 'Product ki tafseel badalna (jo field diya, sirf wahi badlega). Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'id' => [ 'type' => 'integer' ],
				'name' => [ 'type' => 'string' ], 'price' => [ 'type' => 'string' ],
				'sale_price' => [ 'type' => 'string' ], 'sku' => [ 'type' => 'string' ],
				'stock' => [ 'type' => 'integer' ], 'description' => [ 'type' => 'string' ],
				'status' => [ 'type' => 'string' ],
			], 'required' => [ 'site', 'id' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_product_edit',
		],
		'page_create' => [
			'description' => 'Site par naya page banana. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ],
				'content' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string', 'description' => 'publish ya draft' ],
			], 'required' => [ 'site', 'title' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_page_create',
		],
		'page_edit' => [
			'description' => 'Page badalna (jo field diya, sirf wahi badlega). Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'id' => [ 'type' => 'integer' ],
				'title' => [ 'type' => 'string' ], 'content' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string' ],
			], 'required' => [ 'site', 'id' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_page_edit',
		],
		'post_create' => [
			'description' => 'Site par nayi blog post likhna. Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ],
				'content' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string', 'description' => 'publish ya draft' ],
			], 'required' => [ 'site', 'title' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_post_create',
		],
		'post_edit' => [
			'description' => 'Blog post badalna (jo field diya, sirf wahi badlega). Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'id' => [ 'type' => 'integer' ],
				'title' => [ 'type' => 'string' ], 'content' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string' ],
			], 'required' => [ 'site', 'id' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_post_edit',
		],
		'plugin_set' => [
			'description' => 'Plugin on/off karna (plugins_list wali file value dein). Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'plugin' => [ 'type' => 'string', 'description' => 'Masalan woocommerce/woocommerce.php' ],
				'active' => [ 'type' => 'boolean' ],
			], 'required' => [ 'site', 'plugin', 'active' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_plugin_set',
		],
		'theme_activate' => [
			'description' => 'Site ki theme badalna (themes_list wali slug value dein). Manzoori ke baad chalta hai.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [
				'site' => [ 'type' => 'string' ], 'theme' => [ 'type' => 'string' ],
			], 'required' => [ 'site', 'theme' ] ],
			'class'       => 'write',
			'handler'     => 'mcp_tool_theme_activate',
		],
		'site_delete' => [
			'description' => 'SITE DELETE — pakka khatma, wapas nahi ati. Owner ko dashboard par site ka exact naam type karke confirm karna hoga.',
			'inputSchema' => $site,
			'class'       => 'destructive',
			'handler'     => 'mcp_tool_site_delete',
		],
		'product_delete' => [
			'description' => 'PRODUCT DELETE — pakka khatma (trash nahi). Owner ko dashboard par site ka exact naam type karke confirm karna hoga.',
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'site' => [ 'type' => 'string' ], 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'site', 'id' ] ],
			'class'       => 'destructive',
			'handler'     => 'mcp_tool_product_delete',
		],
		'tameero_update_apply' => [
			'description' => 'TAMEERO ko naye version par UPDATE karna (pehle backup banta hai). Owner ko dashboard par TAMEERO likh kar confirm karna hoga.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			'class'       => 'destructive',
			'handler'     => 'mcp_tool_tameero_update_apply',
		],
		'updates_check' => [
			'description' => 'TAMEERO update ki haalat: mojooda version, update channel, aakhri check ka natija.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			'class'       => 'read',
			'handler'     => 'mcp_tool_updates_check',
		],
	];
}

function mcp_tool_server_info( $args ) {
	$sites = hub_sites();
	return [
		'name'       => 'TAMEERO',
		'version'    => hub_version(),
		'php'        => PHP_VERSION,
		'os'         => PHP_OS_FAMILY,
		'sites_dir'  => hub_sites_dir(),
		'sites'      => count( $sites ),
		'transport'  => $GLOBALS['mcp_transport'] ?? 'stdio',
		'protocol'   => 'MCP (open standard)',
	];
}

function mcp_tool_sites_list( $args ) {
	$out = [];
	foreach ( hub_sites() as $site ) {
		$out[] = [
			'site'      => $site['site'],
			'title'     => $site['title'] ?? '',
			'blueprint' => $site['blueprint'] ?? '',
			'port'      => $site['port'] ?? null,
			'url'       => $site['url'] ?? '',
			'state'     => $site['failed'] ?? null ? 'failed' : ( ! empty( $site['creating'] ) ? 'creating' : ( ! empty( $site['running'] ) ? 'running' : 'stopped' ) ),
			'lang'      => $site['lang'] ?? '',
		];
	}
	return [ 'sites' => $out, 'count' => count( $out ) ];
}

function mcp_tool_site_status( $args ) {
	list( $slug, $dir, $meta ) = mcp_site_arg( $args );
	$sites = hub_sites();
	$state = 'stopped';
	foreach ( $sites as $s2 ) {
		if ( $s2['site'] === $slug ) {
			$state = ! empty( $s2['failed'] ) ? 'failed' : ( ! empty( $s2['creating'] ) ? 'creating' : ( ! empty( $s2['running'] ) ? 'running' : 'stopped' ) );
			break;
		}
	}
	$v = mcp_verify( $slug );
	$wooActive = false;
	foreach ( (array) ( $v['active_plugins'] ?? [] ) as $pf ) {
		if ( strpos( (string) $pf, 'woocommerce/' ) === 0 ) { $wooActive = true; break; }
	}
	return [
		'site'      => $slug,
		'state'     => $state,
		'dir'       => $dir,
		'meta'      => $meta,
		'installed' => $v['installed'] ?? false,
		'versions'  => mcp_versions( $dir ),
		'pages'     => $v['pages'] ?? [],
		'woo'       => [
			'active'       => $wooActive,
			'products'     => $v['woo_products'] ?? 0,
			'currency'     => $v['woo_currency'] ?? '',
			'coming_soon'  => $v['woo_coming_soon'] ?? '',
		],
	];
}

function mcp_tool_site_health( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$v = mcp_verify( $slug );
	return [
		'system' => hub_health(),
		'site'   => [
			'site'      => $slug,
			'installed' => $v['installed'] ?? false,
			'versions'  => mcp_versions( hub_sites_dir() . '/' . $slug ),
		],
	];
}

function mcp_tool_site_logs( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$server = '';
	$logFile = $dir . '/.server.log';
	if ( file_exists( $logFile ) ) {
		$lines = file( $logFile, FILE_IGNORE_NEW_LINES ) ?: [];
		$server = implode( "\n", array_slice( $lines, -40 ) );
	}
	return [
		'site'       => $slug,
		'job_log'    => hub_job_log_tail( $slug, 80 ),
		'server_log' => $server,
	];
}

function mcp_tool_products_list( $args ) { list( $slug ) = mcp_site_arg( $args ); return mcp_wc( $slug, [ 'products-list' => true ] ); }
function mcp_tool_pages_list( $args )    { list( $slug ) = mcp_site_arg( $args ); return mcp_wc( $slug, [ 'pages-list' => true ] ); }
function mcp_tool_posts_list( $args )    { list( $slug ) = mcp_site_arg( $args ); return mcp_wc( $slug, [ 'posts-list' => true ] ); }
function mcp_tool_plugins_list( $args )  { list( $slug ) = mcp_site_arg( $args ); return mcp_wc( $slug, [ 'plugins-list' => true ] ); }
function mcp_tool_themes_list( $args )   { list( $slug ) = mcp_site_arg( $args ); return mcp_wc( $slug, [ 'themes-list' => true ] ); }

function mcp_tool_product_get( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$id = (int) ( $args['id'] ?? 0 );
	if ( $id <= 0 ) { throw new Exception( 'Product id lazmi hai' ); }
	return mcp_wc( $slug, [ 'product-get' => true, 'id' => $id ] );
}

function mcp_tool_backups_list( $args ) {
	list( $slug, , $meta ) = mcp_site_arg( $args );
	$out = [];
	foreach ( glob( hub_sites_dir() . '/.exports/backup-' . $slug . '-*.zip' ) ?: [] as $f ) {
		$out[] = [ 'file' => basename( $f ), 'size' => hub_human_size( filesize( $f ) ), 'at' => date( 'c', filemtime( $f ) ) ];
	}
	rsort( $out );
	return [ 'site' => $slug, 'backups' => $out, 'last_backup_at' => $meta['backup_at'] ?? null ];
}

function mcp_tool_action_status( $args ) {
	mcp_pump();
	$rec = hub_mcp_action_get( (string) ( $args['action_id'] ?? '' ) );
	if ( ! $rec ) { throw new Exception( 'Action nahi mila: ' . ( $args['action_id'] ?? '' ) ); }
	return [
		'action_id' => $rec['id'],
		'tool'      => $rec['tool'],
		'status'    => $rec['status'],
		'summary'   => $rec['summary'] ?? '',
		'result'    => ( isset( $rec['result'] ) && is_string( $rec['result'] ) )
			? ( json_decode( $rec['result'], true ) ?: $rec['result'] )
			: ( $rec['result'] ?? null ),
		'error'     => $rec['error'] ?? null,
		'created_at' => $rec['created_at'] ?? '',
	];
}

function mcp_tool_action_cancel( $args ) {
	$rec = hub_mcp_action_get( (string) ( $args['action_id'] ?? '' ) );
	if ( ! $rec ) { throw new Exception( 'Action nahi mila' ); }
	if ( ( $rec['status'] ?? '' ) !== 'pending' ) { throw new Exception( 'Sirf pending action cancel ho sakta hai (haal: ' . $rec['status'] . ')' ); }
	$rec['status'] = 'cancelled';
	hub_mcp_action_save( $rec );
	mcp_audit( (string) $rec['tool'], (string) ( $rec['summary'] ?? '' ), 'cancelled-by-client', $rec['id'] );
	return [ 'action_id' => $rec['id'], 'status' => 'cancelled' ];
}

function mcp_tool_site_start( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$out = hub_run_manager( 'start', $slug );
	return [ 'site' => $slug, 'running' => hub_site_running( $dir ), 'output' => trim( (string) $out ) ];
}

function mcp_tool_site_stop( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$out = hub_run_manager( 'stop', $slug );
	return [ 'site' => $slug, 'running' => hub_site_running( $dir ), 'output' => trim( (string) $out ) ];
}

/* ---------------- Step 4: WRITE lifecycle ---------------- */

function mcp_tool_site_create( $args ) {
	$slug = trim( (string) ( $args['site'] ?? '' ) );
	if ( ! hub_slug_ok( $slug ) ) { throw new Exception( 'Site naam ghalat hai (chhote haroof, number, dash)' ); }
	if ( file_exists( hub_sites_dir() . '/' . $slug ) ) { throw new Exception( 'Is naam ki site pehle se hai' ); }
	$bp = trim( (string) ( $args['blueprint'] ?? 'business' ) );
	$tpl = trim( (string) ( $args['template'] ?? '' ) );
	if ( $tpl !== '' ) {
		if ( ! is_dir( hub_templates_dir() . '/' . $tpl ) ) { throw new Exception( "Template nahi mili: $tpl" ); }
	} elseif ( ! is_dir( hub_bundle_dir() . '/blueprints/' . $bp ) ) {
		throw new Exception( "Blueprint nahi mili: $bp" );
	}
	$user = trim( (string) ( $args['admin_user'] ?? 'admin' ) ) ?: 'admin';
	$email = trim( (string) ( $args['admin_email'] ?? 'admin@example.local' ) ) ?: 'admin@example.local';
	$pass = (string) ( $args['admin_password'] ?? '' );
	$generated = false;
	if ( strlen( $pass ) < 6 ) { $pass = 'tp-' . bin2hex( random_bytes( 6 ) ); $generated = true; }
	hub_create_async( $slug, $bp, $user, $email, $pass, $tpl, ! empty( $args['demo_content'] ) );
	$out = [ 'site' => $slug, 'launched' => true,
		'note' => 'Creation shuru ho gayi hai — haal site_status se milega (creating, phir tayyar ya failed).' ];
	if ( $generated ) { $out['admin_password'] = $pass; $out['admin_user'] = $user; }
	return $out;
}

function mcp_tool_site_clone( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$new = trim( (string) ( $args['name'] ?? '' ) );
	if ( ! hub_slug_ok( $new ) ) { throw new Exception( 'Clone ka naam ghalat hai' ); }
	if ( file_exists( hub_sites_dir() . '/' . $new ) ) { throw new Exception( 'Is naam ki site pehle se hai' ); }
	hub_worker_wait( 'clone --site ' . hub_q( $slug ) . ' --name ' . hub_q( $new ), [], 900 );
	$meta = hub_site_meta( hub_sites_dir() . '/' . $new );
	if ( ! $meta ) { throw new Exception( 'Clone nahi ban saka — site logs dekhein' ); }
	return [ 'site' => $new, 'url' => $meta['url'] ?? '', 'cloned_from' => $slug ];
}

function mcp_tool_template_create( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$name = trim( (string) ( $args['name'] ?? '' ) );
	if ( ! hub_slug_ok( $name ) ) { throw new Exception( 'Template ka naam ghalat hai' ); }
	hub_worker_wait( 'totemplate --site ' . hub_q( $slug ) . ' --name ' . hub_q( $name ), [], 600 );
	if ( ! is_dir( hub_templates_dir() . '/' . $name ) ) { throw new Exception( 'Template nahi ban saka — site logs dekhein' ); }
	return [ 'template' => $name, 'from_site' => $slug ];
}

function mcp_tool_site_backup( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$before = time() - 2;
	hub_worker_wait( 'backup --site ' . hub_q( $slug ), [], 1200 );
	$files = glob( hub_sites_dir() . '/.exports/backup-' . $slug . '-*.zip' ) ?: [];
	// Sirf IS run ka bana hua zip — purana backup ghalat saboot ban jata hai
	$files = array_values( array_filter( $files, function ( $f ) use ( $before ) { return filemtime( $f ) >= $before; } ) );
	if ( ! $files ) { throw new Exception( 'Backup nahi ban saka — site logs dekhein' ); }
	rsort( $files );
	return [ 'site' => $slug, 'backup' => basename( $files[0] ), 'size_mb' => round( filesize( $files[0] ) / 1048576, 1 ) ];
}

function mcp_tool_site_import( $args ) {
	$slug = trim( (string) ( $args['site'] ?? '' ) );
	$zip = trim( (string) ( $args['zip'] ?? '' ) );
	if ( ! hub_slug_ok( $slug ) ) { throw new Exception( 'Site naam ghalat hai' ); }
	if ( file_exists( hub_sites_dir() . '/' . $slug ) ) { throw new Exception( 'Is naam ki site pehle se hai' ); }
	if ( $zip === '' || ! file_exists( $zip ) || substr( $zip, -4 ) !== '.zip' ) { throw new Exception( 'Zip file nahi mili (poora path dein)' ); }
	hub_worker_wait( 'import --site ' . hub_q( $slug ) . ' --zip ' . hub_q( $zip ), [], 1200 );
	$meta = hub_site_meta( hub_sites_dir() . '/' . $slug );
	if ( ! $meta ) { throw new Exception( 'Import nahi ho saka — site logs dekhein' ); }
	return [ 'site' => $slug, 'url' => $meta['url'] ?? '', 'imported_from' => basename( $zip ) ];
}

function mcp_tool_admin_password_reset( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$pass = (string) ( $args['password'] ?? '' );
	if ( strlen( $pass ) < 6 ) { $pass = 'tp-' . bin2hex( random_bytes( 6 ) ); }
	// Password argv/log mein NAHI jata — env-file (0600) ke zariye
	// worker tak jata hai aur worker use parh kar delete kar deta hai.
	hub_worker_wait( 'resetpass --site ' . hub_q( $slug ), [ 'WDH_NEW_PASS' => $pass ], 180 );
	return [ 'site' => $slug, 'new_password' => $pass,
		'note' => 'Ye password sirf is natije mein hai — owner ko de kar save karwana hai. Audit log mein darj NAHI hota.' ];
}

function mcp_tool_deploy_package( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$out = hub_worker_wait( 'deploy --site ' . hub_q( $slug ), [], 1200 );
	if ( preg_match( '/Deploy package tayyar hai:\s*(\S+\.zip)/', (string) $out, $m ) && file_exists( $m[1] ) ) {
		return [ 'site' => $slug, 'deploy_zip' => $m[1], 'size_mb' => round( filesize( $m[1] ) / 1048576, 1 ) ];
	}
	throw new Exception( 'Deploy package nahi ban saka — site logs dekhein' );
}

function mcp_tool_site_php_switch( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	$line = trim( (string) ( $args['php_line'] ?? '' ) );
	if ( $line !== '' && ! hub_php_bin_for_line( $line ) ) {
		throw new Exception( "PHP $line install nahi hai — pehle Health se download karwana hoga" );
	}
	$meta = hub_site_meta( $dir );
	$wasRunning = hub_site_running( $dir );
	if ( $wasRunning ) { hub_run_manager( 'stop', $slug ); }
	if ( $line === '' ) { $meta['_remove'] = [ 'php_line' ]; } else { $meta['php_line'] = $line; }
	hub_save_meta( $dir, $meta );
	return [ 'site' => $slug, 'php_line' => $line === '' ? 'default' : $line, 'was_running' => $wasRunning,
		'note' => $wasRunning ? 'Site band hai — site_start se nayi PHP par chalegi.' : 'Aglay start par nayi PHP lagegi.' ];
}

/* ---------------- Step 5: WRITE content ---------------- */

function mcp_product_fields( $args, $id = 0 ) {
	$m = [ 'product-save' => true ];
	if ( $id > 0 ) { $m['id'] = $id; }
	$map = [ 'name' => 'name', 'price' => 'regular-price', 'sale_price' => 'sale-price',
		'sku' => 'sku', 'description' => 'description', 'status' => 'status' ];
	foreach ( $map as $from => $to ) {
		if ( isset( $args[ $from ] ) && $args[ $from ] !== '' ) { $m[ $to ] = (string) $args[ $from ]; }
	}
	if ( isset( $args['stock'] ) ) { $m['stock'] = (int) $args['stock']; }
	return $m;
}

function mcp_tool_product_add( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$r = mcp_wc( $slug, mcp_product_fields( $args ) );
	if ( ! empty( $r['error'] ) ) { throw new Exception( $r['error'] ); }
	return $r;
}

function mcp_tool_product_edit( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$id = (int) ( $args['id'] ?? 0 );
	if ( $id <= 0 ) { throw new Exception( 'Product id lazmi hai' ); }
	$r = mcp_wc( $slug, mcp_product_fields( $args, $id ) );
	if ( ! empty( $r['error'] ) ) { throw new Exception( $r['error'] ); }
	return $r;
}

function mcp_post_fields( $mode, $args, $id = 0 ) {
	$m = [ $mode => true ];
	if ( $id > 0 ) { $m['id'] = $id; }
	foreach ( [ 'title', 'content', 'status' ] as $k ) {
		if ( isset( $args[ $k ] ) && $args[ $k ] !== '' ) { $m[ $k ] = (string) $args[ $k ]; }
	}
	return $m;
}

function mcp_wc_write( $slug, $modeArgs ) {
	$r = mcp_wc( $slug, $modeArgs );
	if ( ! empty( $r['error'] ) ) { throw new Exception( $r['error'] ); }
	return $r;
}

function mcp_tool_page_create( $args ) { list( $slug ) = mcp_site_arg( $args ); return mcp_wc_write( $slug, mcp_post_fields( 'page-save', $args ) ); }
function mcp_tool_post_create( $args ) { list( $slug ) = mcp_site_arg( $args ); return mcp_wc_write( $slug, mcp_post_fields( 'post-save', $args ) ); }

function mcp_tool_page_edit( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$id = (int) ( $args['id'] ?? 0 );
	if ( $id <= 0 ) { throw new Exception( 'Page id lazmi hai' ); }
	return mcp_wc_write( $slug, mcp_post_fields( 'page-save', $args, $id ) );
}

function mcp_tool_post_edit( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$id = (int) ( $args['id'] ?? 0 );
	if ( $id <= 0 ) { throw new Exception( 'Post id lazmi hai' ); }
	return mcp_wc_write( $slug, mcp_post_fields( 'post-save', $args, $id ) );
}

function mcp_tool_plugin_set( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	return mcp_wc_write( $slug, [ 'plugin-set' => true,
		'plugin' => (string) ( $args['plugin'] ?? '' ),
		'active' => ! empty( $args['active'] ) ? '1' : '0' ] );
}

function mcp_tool_theme_activate( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	return mcp_wc_write( $slug, [ 'theme-activate' => true, 'theme' => (string) ( $args['theme'] ?? '' ) ] );
}

/* ---------------- Step 6: DESTRUCTIVE ---------------- */

function mcp_tool_site_delete( $args ) {
	list( $slug, $dir ) = mcp_site_arg( $args );
	if ( hub_site_running( $dir ) ) { hub_run_manager( 'stop', $slug ); }
	if ( ! hub_delete( $slug ) ) { throw new Exception( 'Delete nahi ho saka: ' . $slug ); }
	return [ 'site' => $slug, 'deleted' => true ];
}

function mcp_tool_product_delete( $args ) {
	list( $slug ) = mcp_site_arg( $args );
	$id = (int) ( $args['id'] ?? 0 );
	if ( $id <= 0 ) { throw new Exception( 'Product id lazmi hai' ); }
	$r = mcp_wc( $slug, [ 'product-delete' => true, 'id' => $id ] );
	if ( ! empty( $r['error'] ) ) { throw new Exception( $r['error'] ); }
	if ( ! empty( $r['still_exists'] ) ) { throw new Exception( 'Product delete ke baad bhi mojood hai' ); }
	return $r;
}

function mcp_tool_tameero_update_apply( $args ) {
	$out = (string) hub_worker_wait( 'updateapply', [], 900 );
	if ( preg_match( '/Update lag gaya: (\S+)/', $out, $m ) ) {
		return [ 'updated_to' => $m[1], 'note' => 'Dashboard band karke dobara kholna hoga taake naya code chale.' ];
	}
	$tail = trim( implode( ' ', array_slice( array_filter( array_map( 'trim', explode( "\n", $out ) ) ), -3 ) ) );
	throw new Exception( 'Update nahi lag saka — ' . ( $tail !== '' ? $tail : 'worker se jawab nahi aya' ) );
}

function mcp_tool_updates_check( $args ) {
	$cfg = hub_update_config();
	$last = null;
	$f = hub_repo() . '/panel/.update-check.json';
	if ( file_exists( $f ) ) { $last = json_decode( (string) file_get_contents( $f ), true ); }
	return [
		'current_version' => hub_version(),
		'channel'         => $cfg['channel'] ?? '',
		'configured'      => ! empty( $cfg['version_url'] ),
		'last_check'      => $last,
	];
}

/* ---------------- approval queue (step 3) ----------------
 * WRITE/DESTRUCTIVE tools seedha nahi chalte: pehle ek pending
 * action banta hai (10 minute ki miyad), owner dashboard ke
 * Agent Activity panel se manzoor/radd karta hai, phir mcp_pump
 * usay chalata hai. Shared storage hub.php mein hai taake
 * dashboard aur server ek hi queue dekhein.
 */

function mcp_action_summary( $tool, array $args ) {
	$bits = [ $tool ];
	if ( ! empty( $args['site'] ) ) { $bits[] = 'site: ' . $args['site']; }
	foreach ( [ 'name', 'title', 'blueprint', 'id' ] as $k ) {
		if ( isset( $args[ $k ] ) && is_scalar( $args[ $k ] ) && $args[ $k ] !== '' ) { $bits[] = $k . ': ' . $args[ $k ]; }
	}
	return implode( ' · ', $bits );
}

function mcp_queue_action( $tool, array $args, $class ) {
	$id = 'act_' . bin2hex( random_bytes( 6 ) );
	$rec = [
		'id'         => $id,
		'tool'       => $tool,
		'args'       => $args,
		'class'      => $class,
		'client'     => $GLOBALS['mcp_client'] ?? 'unknown',
		// Destructive confirm ka lafz: aam tor par site ka naam;
		// update_apply ke liye 'TAMEERO' (us ki koi site nahi hoti).
		'site'       => isset( $args['site'] ) ? (string) $args['site'] : ( $tool === 'tameero_update_apply' ? 'TAMEERO' : null ),
		'summary'    => mcp_action_summary( $tool, $args ),
		'status'     => 'pending',
		'created_at' => date( 'c' ),
		'created_ts' => time(),
		'expires_at' => time() + 600,
		'result'     => null,
		'error'      => null,
	];
	hub_mcp_action_save( $rec );
	mcp_audit( $tool, $rec['summary'], 'pending-approval', 'queued:' . $id );
	return $rec;
}

/** Approved actions ko chalao (har request se pehle bulaya jata hai). */
function mcp_pump() {
	$recs = hub_mcp_actions_list( 50 );
	// Amal hamesha purani-se-nayi tartib mein (FIFO): ek sath
	// manzoor hue do ulte faisle (masalan php_line 8.4 phir
	// default) ulta chalne se ghalat aakhri haalat ban jati thi.
	usort( $recs, function ( $a, $b ) { return ( $a['created_ts'] ?? 0 ) <=> ( $b['created_ts'] ?? 0 ); } );
	foreach ( $recs as $rec ) {
		if ( ( $rec['status'] ?? '' ) !== 'approved' ) { continue; }
		$tools = mcp_tools();
		$tool = $tools[ $rec['tool'] ] ?? null;
		$rec['status'] = 'running';
		hub_mcp_action_save( $rec );
		if ( ! $tool ) {
			$rec['status'] = 'error';
			$rec['error'] = 'Tool ab registry mein nahi: ' . $rec['tool'];
			hub_mcp_action_save( $rec );
			continue;
		}
		try {
			$out = call_user_func( $tool['handler'], (array) $rec['args'] );
			$rec['status'] = 'done';
			$rec['result'] = substr( json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 0, 2000 );
			mcp_audit( $rec['tool'], (string) $rec['summary'], 'executed', 'ok' );
		} catch ( Throwable $e ) {
			$rec['status'] = 'error';
			$rec['error'] = $e->getMessage();
			mcp_audit( $rec['tool'], (string) $rec['summary'], 'executed', 'error: ' . $e->getMessage() );
		}
		$rec['finished_at'] = date( 'c' );
		hub_mcp_action_save( $rec );
	}
}

/* ---------------- JSON-RPC core ---------------- */

function mcp_result( $id, $result ) {
	return [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ];
}
function mcp_error( $id, $code, $message ) {
	return [ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $code, 'message' => $message ] ];
}

/** Ek JSON-RPC request handle karo; notification par null. */
function mcp_handle( array $req ) {
	$method = (string) ( $req['method'] ?? '' );
	$id = $req['id'] ?? null;
	$params = (array) ( $req['params'] ?? [] );
	$isNotification = ! array_key_exists( 'id', $req );

	if ( $method === 'initialize' ) {
		$ci = (array) ( $params['clientInfo'] ?? [] );
		if ( ! empty( $ci['name'] ) ) { $GLOBALS['mcp_client'] = (string) $ci['name']; }
		return mcp_result( $id, [
			'protocolVersion' => (string) ( $params['protocolVersion'] ?? '2025-03-26' ),
			'capabilities'    => [ 'tools' => new stdClass() ],
			'serverInfo'      => [ 'name' => 'TAMEERO MCP', 'version' => hub_version() ],
		] );
	}
	if ( strpos( $method, 'notifications/' ) === 0 ) { return null; }
	if ( $method === 'ping' ) { return mcp_result( $id, new stdClass() ); }

	if ( $method === 'tools/list' ) {
		$list = [];
		foreach ( mcp_tools() as $name => $t ) {
			$list[] = [ 'name' => $name, 'description' => $t['description'], 'inputSchema' => $t['inputSchema'] ];
		}
		return mcp_result( $id, [ 'tools' => $list ] );
	}

	if ( $method === 'tools/call' ) {
		mcp_pump();
		$name = (string) ( $params['name'] ?? '' );
		$args = (array) ( $params['arguments'] ?? [] );
		$tools = mcp_tools();
		if ( ! isset( $tools[ $name ] ) ) {
			mcp_audit( $name, 'unknown tool', 'n/a', 'error' );
			return mcp_error( $id, -32602, "Tool nahi mila: $name" );
		}
		$tool = $tools[ $name ];
		if ( $tool['class'] !== 'read' ) {
			$rec = mcp_queue_action( $name, $args, $tool['class'] );
			$payload = [
				'pending'    => true,
				'action_id'  => $rec['id'],
				'status'     => 'pending',
				'expires_in' => 600,
				'note'       => 'Owner ki manzoori chahiye. Dashboard ke Agent Activity panel mein pending hai. action_status se haal check karo.',
			];
			return mcp_result( $id, [
				'content' => [ [ 'type' => 'text', 'text' => json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ] ],
				'isError' => false,
			] );
		}
		try {
			$out = call_user_func( $tool['handler'], $args );
			mcp_audit( $name, mcp_summarize( $args ), $tool['class'] === 'read' ? 'auto-read' : 'pending', 'ok' );
			return mcp_result( $id, [
				'content' => [ [ 'type' => 'text', 'text' => json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ] ],
				'isError' => false,
			] );
		} catch ( Throwable $e ) {
			mcp_audit( $name, mcp_summarize( $args ), $tool['class'] === 'read' ? 'auto-read' : 'pending', 'error: ' . $e->getMessage() );
			return mcp_result( $id, [
				'content' => [ [ 'type' => 'text', 'text' => 'Error: ' . $e->getMessage() ] ],
				'isError' => true,
			] );
		}
	}

	if ( $isNotification ) { return null; }
	return mcp_error( $id, -32601, "Method nahi mila: $method" );
}

/** Audit summary — chhota, passwords/tokens kabhi nahi. */
function mcp_summarize( array $args ) {
	$keep = [];
	foreach ( $args as $k => $v ) {
		if ( preg_match( '/pass|token|secret/i', (string) $k ) ) { continue; }
		$keep[ $k ] = is_scalar( $v ) ? $v : gettype( $v );
	}
	$s = json_encode( $keep, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return strlen( (string) $s ) > 200 ? substr( (string) $s, 0, 200 ) . '…' : (string) $s;
}

/* ---------------- transports ---------------- */

function mcp_serve_stdio() {
	$GLOBALS['mcp_transport'] = 'stdio';
	while ( ( $line = fgets( STDIN ) ) !== false ) {
		$line = trim( $line );
		if ( $line === '' ) { continue; }
		$req = json_decode( $line, true );
		if ( ! is_array( $req ) ) { continue; }
		$resp = mcp_handle( $req );
		if ( $resp !== null ) { fwrite( STDOUT, json_encode( $resp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ); fflush( STDOUT ); }
	}
}

function mcp_serve_http( $bind, $port ) {
	$GLOBALS['mcp_transport'] = 'http';
	$server = stream_socket_server( "tcp://$bind:$port", $errno, $errstr );
	if ( ! $server ) { fwrite( STDERR, "Server nahi khula: $errstr\n" ); exit( 1 ); }
	// Dashboard ko asal haalat batane ke liye apna PID likho (v2.8);
	// band hone par saaf karo — sirf apna hi PID mitaye ga.
	@file_put_contents( hub_mcp_pidfile(), (string) getmypid() );
	register_shutdown_function( function () {
		$f = hub_mcp_pidfile();
		if ( file_exists( $f ) && (int) trim( (string) file_get_contents( $f ) ) === getmypid() ) { @unlink( $f ); }
	} );
	fwrite( STDERR, "TAMEERO MCP server: http://$bind:$port (token lazmi)\n" );
	while ( true ) {
		$conn = @stream_socket_accept( $server, -1 );
		if ( ! $conn ) { continue; }
		stream_set_timeout( $conn, 30 );
		try { mcp_http_conn( $conn ); } catch ( Throwable $e ) { /* ek kharab request server nahi giraye gi */ }
		@fclose( $conn );
	}
}

function mcp_http_reply( $conn, $code, $body, $type = 'application/json' ) {
	$status = [ 200 => 'OK', 202 => 'Accepted', 401 => 'Unauthorized', 403 => 'Forbidden', 405 => 'Method Not Allowed' ][ $code ] ?? 'OK';
	fwrite( $conn, "HTTP/1.1 $code $status\r\nContent-Type: $type\r\nContent-Length: " . strlen( $body ) . "\r\nConnection: close\r\n\r\n" . $body );
}

function mcp_http_conn( $conn ) {
	$head = '';
	while ( strpos( $head, "\r\n\r\n" ) === false && strlen( $head ) < 16384 ) {
		$chunk = fread( $conn, 4096 );
		if ( $chunk === false || $chunk === '' ) { return; }
		$head .= $chunk;
	}
	$parts = explode( "\r\n\r\n", $head, 2 );
	$headers = $parts[0];
	$body = $parts[1] ?? '';
	if ( ! preg_match( '/^(\w+)\s+(\S+)/', $headers, $mm ) ) { return; }
	$method = strtoupper( $mm[1] );
	if ( $method !== 'POST' ) { mcp_http_reply( $conn, 405, '{"error":"Sirf POST"}' ); return; }
	$len = 0;
	if ( preg_match( '/Content-Length:\s*(\d+)/i', $headers, $lm ) ) { $len = (int) $lm[1]; }
	while ( strlen( $body ) < $len ) {
		$chunk = fread( $conn, 8192 );
		if ( $chunk === false || $chunk === '' ) { break; }
		$body .= $chunk;
	}
	// Token auth (har HTTP request par lazmi)
	$token = '';
	if ( preg_match( '/Authorization:\s*Bearer\s+(\S+)/i', $headers, $am ) ) { $token = $am[1]; }
	if ( empty( hub_settings()['mcp_enabled'] ) ) {
		// Connection serve loop band karta hai — yahan fclose
		// karna double-close hai (PHP 8 mein TypeError, server
		// gir jata hai; test mein pakra gaya).
		mcp_http_reply( $conn, 403, '{"error":"MCP HTTP dashboard se band hai — Agent page par ja kar on karein"}' );
		return;
	}
	if ( ! mcp_check_token( $token ) ) {
		mcp_http_reply( $conn, 401, '{"error":"Token ghalat ya missing hai"}' );
		return;
	}
	$req = json_decode( $body, true );
	if ( ! is_array( $req ) ) { mcp_http_reply( $conn, 200, json_encode( mcp_error( null, -32700, 'JSON parse error' ) ) ); return; }
	$resp = mcp_handle( $req );
	if ( $resp === null ) { mcp_http_reply( $conn, 202, '' ); return; }
	mcp_http_reply( $conn, 200, json_encode( $resp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/* ---------------- CLI ----------------
 * Sirf tab jab ye file khud main script ho — dashboard chat ke
 * library-include par ye block bilkul nahi chalega. */

if ( php_sapi_name() === 'cli' && realpath( (string) ( $argv[0] ?? '' ) ) === __FILE__ ) {

$mode = $argv[1] ?? '';
$opts = [];
foreach ( array_slice( $argv, 2 ) as $a ) {
	if ( preg_match( '/^--(\w+)=(.*)$/', $a, $m ) ) { $opts[ $m[1] ] = $m[2]; }
}

if ( $mode === 'gen-token' ) {
	$token = bin2hex( random_bytes( 24 ) );
	hub_save_settings( [ 'mcp_token_hash' => hash( 'sha256', $token ) ] );
	echo "MCP token (SIRF ek dafa dikh raha hai — sambhal kar rakhein):\n$token\n";
	echo "Hash settings mein save ho gaya. Ye token MCP client ki config mein lage ga.\n";
	exit( 0 );
}
if ( $mode === 'stdio' ) { mcp_serve_stdio(); exit( 0 ); }
if ( $mode === 'serve' ) {
	$bind = $opts['bind'] ?? '127.0.0.1';
	$port = (int) ( $opts['port'] ?? 8971 );
	mcp_serve_http( $bind, $port );
	exit( 0 );
}
echo "Usage: php mcp.php gen-token | stdio | serve [--bind=127.0.0.1] [--port=8971]\n";

} // main-script guard khatam
