<?php
/**
 * WebDevHub — programmatic site setup (CLI only).
 *
 * Modes:
 *   --install          wp_install + default settings + cleanup
 *   --activate=<slug>  ek plugin activate (alag process — ek plugin ki
 *                      kharabi baqi setup ko nahi girati)
 *   --apply-blueprint  blueprint ke pages/menu + WooCommerce setup
 *   --verify           JSON report: options, pages, plugins, woo state
 *
 * Lazmi args: --path=<site dir> --url=<http://127.0.0.1:PORT>
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$opts = getopt( '', [
	'path:', 'url:', 'title::', 'admin-user::', 'admin-pass::', 'admin-email::',
	'blueprint::', 'install', 'activate::', 'apply-blueprint', 'verify',
] );
$path      = rtrim( $opts['path'] ?? '', '/' );
$url       = $opts['url'] ?? 'http://127.0.0.1:8080';
$blueprint = $opts['blueprint'] ?? 'business';
$repo      = dirname( __DIR__ );
$bundle    = $repo . '/bundle';

if ( ! $path || ! file_exists( $path . '/wp-load.php' ) ) {
	fwrite( STDERR, "Site path ghalat hai: $path\n" );
	exit( 1 );
}

// WP_INSTALLING sirf --install mode mein: is mode mein WP active plugins
// load NAHI karta — install ke liye theek hai, lekin apply-blueprint ko
// plugins (khas taur par WooCommerce) LIVE loaded chahiye hote hain.
if ( isset( $opts['install'] ) || ! empty( $opts['activate'] ) ) {
	define( 'WP_INSTALLING', true );
}
if ( getenv( 'WDH_DEBUG' ) ) {
	// Debug mode: WP ka fatal-error handler band — asal PHP error seedha dikhega.
	define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
}
define( 'WP_SITEURL', $url );
define( 'WP_HOME', $url );
if ( ! defined( 'WP_USE_THEMES' ) ) { define( 'WP_USE_THEMES', false ); }

chdir( $path );
require_once $path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function wdh_find_plugin_file( $slug ) {
	$dir = WP_PLUGIN_DIR . '/' . $slug;
	if ( ! is_dir( $dir ) ) { return null; }
	foreach ( glob( $dir . '/*.php' ) as $f ) {
		$data = get_plugin_data( $f, false, false );
		if ( ! empty( $data['Name'] ) ) { return $slug . '/' . basename( $f ); }
	}
	return null;
}

function wdh_load_json( $file ) {
	return json_decode( file_get_contents( $file ), true );
}

/* ---------------- --install ---------------- */
if ( isset( $opts['install'] ) ) {
	$title = $opts['title'] ?? 'WebDevHub Site';
	$user  = $opts['admin-user'] ?? 'admin';
	$email = $opts['admin-email'] ?? 'admin@example.local';
	$pass  = $opts['admin-pass'] ?? '';
	if ( $pass === '' ) { fwrite( STDERR, "admin-pass lazmi hai\n" ); exit( 1 ); }

	if ( ! is_blog_installed() ) {
		$res = wp_install( $title, $user, $email, false, '', $pass );
		if ( is_wp_error( $res ) ) { fwrite( STDERR, $res->get_error_message() . "\n" ); exit( 1 ); }
		echo "WP install ho gaya. Admin user id: {$res['user_id']}\n";
	} else {
		echo "WP pehle se installed hai — install step skip.\n";
	}

	// Urdu language pack PEHLE — WP core ka sanitize_option('WPLANG')
	// sirf wohi language allow karta hai jis ki files wp-content/languages
	// mein mojood hon. Pack ke baghair WPLANG kabhi save nahi hota.
	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	if ( in_array( 'ur', get_available_languages(), true ) ) {
		echo "Urdu language pack mojood hai (bundle se).\n";
	} elseif ( function_exists( 'wp_download_language_pack' ) ) {
		$pack = wp_download_language_pack( 'ur' );
		echo $pack ? "Urdu language pack download ho gaya: $pack\n"
		           : "WARN: Urdu language pack nahi mila — language en_US rahegi.\n";
	}

	$settings = wdh_load_json( $bundle . '/config/default-settings.json' );
	foreach ( ( $settings['defaults'] ?? [] ) as $k => $v ) { update_option( $k, $v ); }
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( $settings['defaults']['permalink_structure'] );
	$wp_rewrite->flush_rules( true );

	if ( ! empty( $settings['cleanup']['delete_sample_post'] ) && get_post( 1 ) ) { wp_delete_post( 1, true ); }
	if ( ! empty( $settings['cleanup']['delete_sample_page'] ) && get_post( 2 ) ) { wp_delete_post( 2, true ); }
	if ( ! empty( $settings['cleanup']['delete_sample_comment'] ) ) { wp_delete_comment( 1, true ); }
	update_option( 'webdevhub_bundle_version', '2.0.0-dev' );
	echo "Default settings apply ho gayin (Urdu, Asia/Karachi, permalinks).\n";
	exit( 0 );
}

/* ---------------- --activate=<slug> ---------------- */
if ( ! empty( $opts['activate'] ) ) {
	$slug = $opts['activate'];
	$file = wdh_find_plugin_file( $slug );
	if ( ! $file ) { echo "WARN plugin nahi mila: $slug\n"; exit( 0 ); }
	if ( is_plugin_active( $file ) ) { echo "OK pehle se active: $slug\n"; exit( 0 ); }
	try {
		$r = activate_plugin( $file, '', false, true );
		if ( is_wp_error( $r ) ) { echo "WARN activate fail $slug: " . $r->get_error_message() . "\n"; exit( 0 ); }
		echo "OK activated: $slug\n";
	} catch ( Throwable $e ) {
		echo "WARN activate exception $slug: " . $e->getMessage() . "\n";
	}
	exit( 0 );
}

/* ---------------- --apply-blueprint ---------------- */
if ( isset( $opts['apply-blueprint'] ) ) {
	$bp_file = $bundle . '/blueprints/' . $blueprint . '/blueprint.json';
	if ( ! file_exists( $bp_file ) ) { echo "Blueprint nahi mila: $blueprint (skip)\n"; exit( 0 ); }
	$bp = wdh_load_json( $bp_file );

	$ids = [];
	foreach ( ( $bp['pages'] ?? [] ) as $page ) {
		$existing = get_page_by_path( $page['slug'] );
		if ( $existing ) { $ids[ $page['slug'] ] = $existing->ID; continue; }
		$id = wp_insert_post( [
			'post_title'   => $page['title'],
			'post_name'    => $page['slug'],
			'post_content' => $page['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		] );
		if ( $id && ! is_wp_error( $id ) ) { $ids[ $page['slug'] ] = $id; }
	}

	// Store blueprint ke liye WooCommerce LAZMI hai — na ho to saaf fail.
	// Note: WC_Install class sirf admin context mein load hoti hai, is liye
	// pehle is_plugin_active check, phir class file khud include karte hain.
	if ( $blueprint === 'store' ) {
		$woo_file = wdh_find_plugin_file( 'woocommerce' );
		if ( ! $woo_file || ! is_plugin_active( $woo_file ) ) {
			fwrite( STDERR, "ERROR: store blueprint ke liye WooCommerce active nahi hai.\n" );
			exit( 2 );
		}
		if ( ! class_exists( 'WC_Install' ) ) {
			require_once WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-install.php';
		}
	}

	// WooCommerce pages (store blueprint): Woo khud banata hai agar install ho
	if ( $blueprint === 'store' && class_exists( 'WC_Install' ) ) {
		WC_Install::install();
		$woo = wdh_load_json( $bundle . '/config/default-settings.json' )['woocommerce'];
		foreach ( $woo as $k => $v ) {
			if ( $k === 'payment_gateways_enabled' ) { continue; }
			update_option( 'woocommerce_' . $k, $v );
		}
		update_option( 'woocommerce_cod_settings', [ 'enabled' => 'yes', 'title' => 'Cash on Delivery', 'description' => 'Order milne par payment dein.', 'instructions' => '' ] );
		foreach ( get_posts( [ 'post_type' => 'page', 'posts_per_page' => -1 ] ) as $p ) {
			$ids[ $p->post_name ] = $p->ID;
		}
		foreach ( ( $bp['woocommerce']['sample_products'] ?? [] ) as $prod ) {
			if ( ! class_exists( 'WC_Product_Simple' ) ) { break; }
			$found = get_posts( [ 'post_type' => 'product', 'meta_key' => '_sku', 'meta_value' => $prod['sku'], 'posts_per_page' => 1 ] );
			if ( $found ) { continue; }
			$obj = new WC_Product_Simple();
			$obj->set_name( $prod['name'] );
			$obj->set_regular_price( (string) $prod['price'] );
			$obj->set_sku( $prod['sku'] );
			$obj->set_description( $prod['description'] );
			$obj->set_status( 'publish' );
			$obj->save();
		}
		echo "WooCommerce setup ho gaya (PKR, COD, sample products).\n";
	}

	if ( ! empty( $bp['front_page'] ) && isset( $ids[ $bp['front_page'] ] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids[ $bp['front_page'] ] );
	}

	foreach ( ( $bp['menus'] ?? [] ) as $menu ) {
		$menu_id = wp_create_nav_menu( $menu['name'] );
		if ( is_wp_error( $menu_id ) ) {
			$m = wp_get_nav_menu_object( $menu['name'] );
			$menu_id = $m ? $m->term_id : 0;
		}
		if ( $menu_id ) {
			foreach ( $menu['items'] as $slug ) {
				if ( isset( $ids[ $slug ] ) ) {
					wp_update_nav_menu_item( $menu_id, 0, [
						'menu-item-title'     => get_the_title( $ids[ $slug ] ),
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $ids[ $slug ],
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					] );
				}
			}
			$locs = get_theme_mod( 'nav_menu_locations', [] );
			$locs[ $menu['location'] ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locs );
		}
	}
	echo "Blueprint apply ho gaya: $blueprint\n";
	exit( 0 );
}

/* ---------------- --verify ---------------- */
if ( isset( $opts['verify'] ) ) {
	$out = [
		'installed'        => is_blog_installed(),
		'timezone'         => get_option( 'timezone_string' ),
		'language_option'  => get_option( 'WPLANG' ),
		'permalink'        => get_option( 'permalink_structure' ),
		'show_on_front'    => get_option( 'show_on_front' ),
		'front_page_id'    => (int) get_option( 'page_on_front' ),
		'pages'            => [],
		'active_plugins'   => get_option( 'active_plugins' ),
		'woo_currency'     => get_option( 'woocommerce_currency' ),
		'woo_products'     => 0,
	];
	foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $p ) {
		$out['pages'][] = $p->post_name;
	}
	if ( post_type_exists( 'product' ) ) {
		$out['woo_products'] = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	}
	echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}

echo "Koi mode nahi diya: --install | --activate=<slug> | --apply-blueprint | --verify\n";
