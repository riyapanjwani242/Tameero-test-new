<?php
/**
 * TAMEERO MCP tool — WP-level kaam WordPress bootstrap ke sath.
 * Sirf JSON output (ek object). MCP server (mcp.php) isay bulata
 * hai; dashboard/worker is se bay-khabar hain.
 *
 * Usage:
 *   php wc-tool.php --path=<sitedir> --url=<siteurl> --products-list
 *   php wc-tool.php --path=... --url=... --product-get --id=N
 *   php wc-tool.php --path=... --url=... --pages-list | --posts-list
 *   php wc-tool.php --path=... --url=... --plugins-list | --themes-list
 *   php wc-tool.php --path=... --url=... --product-save [--id=N] [--name=..]
 *        [--regular-price=..] [--sale-price=..] [--sku=..] [--stock=N]
 *        [--description=..] [--status=publish|draft]
 *   php wc-tool.php --path=... --url=... --page-save | --post-save [--id=N]
 *        [--title=..] [--content=..] [--status=..] [--slug=..]
 *   php wc-tool.php --path=... --url=... --plugin-set --plugin=<file> --active=1|0
 *   php wc-tool.php --path=... --url=... --theme-activate --theme=<stylesheet>
 *   php wc-tool.php --path=... --url=... --media-import --file=<abs> [--title=..] [--name=..]
 *   php wc-tool.php --path=... --url=... --media-set-thumb --id=N --att=M
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$opts = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([\w-]+)(?:=(.*))?$/', $a, $m ) ) { $opts[ $m[1] ] = $m[2] ?? true; }
}
$path = $opts['path'] ?? '';
$url  = $opts['url'] ?? '';
if ( ! $path || ! file_exists( $path . '/wp-load.php' ) ) {
	echo json_encode( [ 'error' => 'Site path ghalat hai' ] ) . "\n";
	exit( 1 );
}

define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
if ( $url ) {
	define( 'WP_SITEURL', $url );
	define( 'WP_HOME', $url );
}
if ( ! defined( 'WP_USE_THEMES' ) ) { define( 'WP_USE_THEMES', false ); }
require_once $path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function wc_out( $data ) { echo json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"; }

/* ---------------- products ---------------- */

if ( isset( $opts['products-list'] ) || isset( $opts['product-get'] ) ) {
	if ( ! class_exists( 'WooCommerce' ) ) { wc_out( [ 'error' => 'WooCommerce active nahi hai is site par' ] ); exit( 0 ); }
}
if ( isset( $opts['products-list'] ) ) {
	$items = [];
	foreach ( wc_get_products( [ 'limit' => 200, 'status' => [ 'publish', 'draft', 'private' ], 'orderby' => 'ID' ] ) as $p ) {
		$imgId = $p->get_image_id();
		$items[] = [
			'id'             => $p->get_id(),
			'name'           => $p->get_name(),
			'slug'           => $p->get_slug(),
			'status'         => $p->get_status(),
			'price'          => $p->get_price(),
			'regular_price'  => $p->get_regular_price(),
			'sale_price'     => $p->get_sale_price(),
			'sku'            => $p->get_sku(),
			'stock_status'   => $p->get_stock_status(),
			'stock_quantity' => $p->get_stock_quantity(),
			'categories'     => wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'names' ] ),
			'image'          => $imgId ? wp_get_attachment_url( $imgId ) : null,
		];
	}
	wc_out( [ 'products' => $items, 'count' => count( $items ) ] );
	exit( 0 );
}
if ( isset( $opts['product-get'] ) ) {
	$p = wc_get_product( (int) ( $opts['id'] ?? 0 ) );
	if ( ! $p ) { wc_out( [ 'error' => 'Product nahi mila: ' . ( $opts['id'] ?? '' ) ] ); exit( 0 ); }
	$images = [];
	foreach ( array_merge( [ $p->get_image_id() ], $p->get_gallery_image_ids() ) as $iid ) {
		if ( $iid ) { $images[] = wp_get_attachment_url( $iid ); }
	}
	wc_out( [ 'product' => [
		'id'                => $p->get_id(),
		'name'              => $p->get_name(),
		'slug'              => $p->get_slug(),
		'status'            => $p->get_status(),
		'description'       => $p->get_description(),
		'short_description' => $p->get_short_description(),
		'price'             => $p->get_price(),
		'regular_price'     => $p->get_regular_price(),
		'sale_price'        => $p->get_sale_price(),
		'sku'               => $p->get_sku(),
		'stock_status'      => $p->get_stock_status(),
		'stock_quantity'    => $p->get_stock_quantity(),
		'categories'        => wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'names' ] ),
		'images'            => $images,
		'permalink'         => get_permalink( $p->get_id() ),
	] ] );
	exit( 0 );
}

/* ---------------- pages / posts ---------------- */

if ( isset( $opts['pages-list'] ) || isset( $opts['posts-list'] ) ) {
	$type = isset( $opts['pages-list'] ) ? 'page' : 'post';
	$items = [];
	foreach ( get_posts( [ 'post_type' => $type, 'post_status' => [ 'publish', 'draft', 'private' ], 'numberposts' => 200, 'orderby' => 'ID' ] ) as $post ) {
		$items[] = [
			'id'     => $post->ID,
			'title'  => $post->post_title,
			'slug'   => $post->post_name,
			'status' => $post->post_status,
			'date'   => $post->post_date,
			'url'    => get_permalink( $post->ID ),
		];
	}
	wc_out( [ $type === 'page' ? 'pages' : 'posts' => $items, 'count' => count( $items ) ] );
	exit( 0 );
}

if ( isset( $opts['post-get'] ) ) {
	$post = get_post( (int) ( $opts['id'] ?? 0 ) );
	if ( ! $post ) { wc_out( [ 'error' => 'Post nahi mila: ' . ( $opts['id'] ?? '' ) ] ); exit( 0 ); }
	$thumbId = (int) get_post_thumbnail_id( $post->ID );
	wc_out( [ 'post' => [
		'id' => $post->ID, 'title' => $post->post_title, 'type' => $post->post_type,
		'status' => $post->post_status,
		'featured_image_id' => $thumbId,
		'featured_image' => $thumbId ? wp_get_attachment_url( $thumbId ) : null,
	] ] );
	exit( 0 );
}

/* ---------------- plugins / themes ---------------- */

if ( isset( $opts['plugins-list'] ) ) {
	$items = [];
	foreach ( get_plugins() as $file => $info ) {
		$items[] = [
			'file'    => $file,
			'name'    => $info['Name'],
			'version' => $info['Version'],
			'active'  => is_plugin_active( $file ),
		];
	}
	usort( $items, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
	wc_out( [ 'plugins' => $items, 'count' => count( $items ) ] );
	exit( 0 );
}
if ( isset( $opts['themes-list'] ) ) {
	$items = [];
	$active = get_stylesheet();
	foreach ( wp_get_themes() as $slug => $theme ) {
		$items[] = [
			'slug'    => $slug,
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'active'  => $slug === $active,
		];
	}
	wc_out( [ 'themes' => $items, 'count' => count( $items ) ] );
	exit( 0 );
}


/* ---------------- write: product save ---------------- */

if ( isset( $opts['product-save'] ) ) {
	if ( ! class_exists( 'WooCommerce' ) ) { wc_out( [ 'error' => 'WooCommerce active nahi hai is site par' ] ); exit( 0 ); }
	$id = (int) ( $opts['id'] ?? 0 );
	if ( $id > 0 ) {
		$p = wc_get_product( $id );
		if ( ! $p ) { wc_out( [ 'error' => 'Product nahi mila: ' . $id ] ); exit( 0 ); }
	} else {
		$p = new WC_Product_Simple();
	}
	if ( array_key_exists( 'name', $opts ) ) { $p->set_name( (string) $opts['name'] ); }
	if ( array_key_exists( 'regular-price', $opts ) ) { $p->set_regular_price( (string) $opts['regular-price'] ); }
	if ( array_key_exists( 'sale-price', $opts ) ) { $p->set_sale_price( (string) $opts['sale-price'] ); }
	if ( array_key_exists( 'sku', $opts ) ) { $p->set_sku( (string) $opts['sku'] ); }
	if ( array_key_exists( 'description', $opts ) ) { $p->set_description( (string) $opts['description'] ); }
	if ( array_key_exists( 'short-description', $opts ) ) { $p->set_short_description( (string) $opts['short-description'] ); }
	if ( array_key_exists( 'status', $opts ) ) { $p->set_status( (string) $opts['status'] ); }
	if ( array_key_exists( 'stock', $opts ) ) {
		$p->set_manage_stock( true );
		$p->set_stock_quantity( (int) $opts['stock'] );
		$p->set_stock_status( (int) $opts['stock'] > 0 ? 'instock' : 'outofstock' );
	}
	try { $newId = $p->save(); } catch ( Throwable $e ) { wc_out( [ 'error' => 'Product save fail: ' . $e->getMessage() ] ); exit( 0 ); }
	if ( ! $newId ) { wc_out( [ 'error' => 'Product save fail ho gaya' ] ); exit( 0 ); }
	wc_out( [ 'product' => [ 'id' => $newId, 'name' => $p->get_name(), 'status' => $p->get_status(),
		'price' => $p->get_price(), 'permalink' => get_permalink( $newId ) ], 'created' => $id === 0 ] );
	exit( 0 );
}

/* ---------------- destructive: product delete ---------------- */

if ( isset( $opts['product-delete'] ) ) {
	if ( ! class_exists( 'WooCommerce' ) ) { wc_out( [ 'error' => 'WooCommerce active nahi hai is site par' ] ); exit( 0 ); }
	$id = (int) ( $opts['id'] ?? 0 );
	$p = $id > 0 ? wc_get_product( $id ) : null;
	if ( ! $p ) { wc_out( [ 'error' => 'Product nahi mila: ' . $id ] ); exit( 0 ); }
	$name = $p->get_name();
	$p->delete( true ); // force — trash nahi, pakka delete (type-confirm MCP par hai)
	wc_out( [ 'deleted' => true, 'id' => $id, 'name' => $name, 'still_exists' => (bool) wc_get_product( $id ) ] );
	exit( 0 );
}

/* ---------------- write: page / post save ---------------- */

foreach ( [ 'page-save' => 'page', 'post-save' => 'post' ] as $mode => $type ) {
	if ( ! isset( $opts[ $mode ] ) ) { continue; }
	$id = (int) ( $opts['id'] ?? 0 );
	if ( $id > 0 ) {
		$existing = get_post( $id );
		if ( ! $existing || $existing->post_type !== $type ) { wc_out( [ 'error' => ucfirst( $type ) . ' nahi mila: ' . $id ] ); exit( 0 ); }
	}
	$data = [ 'post_type' => $type ];
	if ( $id > 0 ) {
		$data['ID'] = $id;
		// Partial edit: maujooda values pehle bharo — warna
		// wp_insert_post "content/title/excerpt khaali hain" ka
		// error deta hai jab sirf ek field (masalan status) badle.
		$data['post_title'] = $existing->post_title;
		$data['post_content'] = $existing->post_content;
		$data['post_status'] = $existing->post_status;
		$data['post_excerpt'] = $existing->post_excerpt;
		$data['post_name'] = $existing->post_name;
	}
	if ( array_key_exists( 'title', $opts ) ) { $data['post_title'] = (string) $opts['title']; }
	if ( array_key_exists( 'content', $opts ) ) { $data['post_content'] = (string) $opts['content']; }
	if ( array_key_exists( 'status', $opts ) ) { $data['post_status'] = (string) $opts['status']; }
	if ( array_key_exists( 'slug', $opts ) ) { $data['post_name'] = (string) $opts['slug']; }
	if ( array_key_exists( 'excerpt', $opts ) ) { $data['post_excerpt'] = (string) $opts['excerpt']; }
	if ( $id === 0 && empty( $data['post_title'] ) ) { wc_out( [ 'error' => 'Title lazmi hai' ] ); exit( 0 ); }
	if ( $id === 0 && ! isset( $data['post_status'] ) ) { $data['post_status'] = 'publish'; }
	$newId = wp_insert_post( $data, true );
	if ( is_wp_error( $newId ) ) { wc_out( [ 'error' => $newId->get_error_message() ] ); exit( 0 ); }
	$saved = get_post( $newId );
	wc_out( [ $type => [ 'id' => $newId, 'title' => $saved->post_title, 'status' => $saved->post_status,
		'url' => get_permalink( $newId ) ], 'created' => $id === 0 ] );
	exit( 0 );
}

/* ---------------- write: plugin set / theme activate ---------------- */

if ( isset( $opts['plugin-set'] ) ) {
	$file = (string) ( $opts['plugin'] ?? '' );
	$all = get_plugins();
	if ( $file === '' || ! isset( $all[ $file ] ) ) { wc_out( [ 'error' => 'Plugin nahi mila: ' . $file ] ); exit( 0 ); }
	$want = ( (string) ( $opts['active'] ?? '1' ) ) === '1';
	if ( $want ) {
		$r = activate_plugin( $file );
		if ( is_wp_error( $r ) ) { wc_out( [ 'error' => 'Activate fail: ' . $r->get_error_message() ] ); exit( 0 ); }
	} else {
		deactivate_plugins( $file );
	}
	wc_out( [ 'plugin' => $file, 'name' => $all[ $file ]['Name'], 'active' => is_plugin_active( $file ) ] );
	exit( 0 );
}
if ( isset( $opts['theme-activate'] ) ) {
	$slug = (string) ( $opts['theme'] ?? '' );
	$theme = wp_get_theme( $slug );
	if ( $slug === '' || ! $theme->exists() ) { wc_out( [ 'error' => 'Theme nahi mili: ' . $slug ] ); exit( 0 ); }
	switch_theme( $slug );
	wc_out( [ 'theme' => $slug, 'name' => $theme->get( 'Name' ), 'active_theme' => get_stylesheet() ] );
	exit( 0 );
}

/* ---------------- media: AI image import / featured set (v2.16) ---------------- */

if ( isset( $opts['media-import'] ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$src = (string) ( $opts['file'] ?? '' );
	$title = (string) ( $opts['title'] ?? 'AI Image' );
	$slugPart = sanitize_file_name( (string) ( $opts['name'] ?? 'image' ) );
	if ( $slugPart === '' ) { $slugPart = 'image'; }
	if ( ! file_exists( $src ) ) { wc_out( [ 'error' => 'Image file nahi mili: ' . $src ] ); exit( 0 ); }
	// WP ke liye WebP (GD ho to) — warna asal format hi import hoga.
	$workFile = $src;
	$converted = false;
	$ext0 = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
	if ( $ext0 !== 'webp' && function_exists( 'imagewebp' ) ) {
		$im = null;
		if ( $ext0 === 'jpg' || $ext0 === 'jpeg' ) { $im = @imagecreatefromjpeg( $src ); }
		elseif ( $ext0 === 'png' ) { $im = @imagecreatefrompng( $src ); }
		if ( $im ) {
			if ( @imagewebp( $im, $src . '.webp', 85 ) && file_exists( $src . '.webp' ) && filesize( $src . '.webp' ) > 0 ) {
				$workFile = $src . '.webp';
				$converted = true;
			}
			imagedestroy( $im );
		}
	}
	$finalExt = strtolower( pathinfo( $workFile, PATHINFO_EXTENSION ) );
	$mimes = [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' ];
	$mime = $mimes[ $finalExt ] ?? 'image/jpeg';
	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) { wc_out( [ 'error' => 'Upload dir nahi mila: ' . $up['error'] ] ); exit( 0 ); }
	$sub = 'tameero-ai/' . date( 'Y' ) . '/' . date( 'm' );
	$dir = $up['basedir'] . '/' . $sub;
	if ( ! wp_mkdir_p( $dir ) ) { wc_out( [ 'error' => 'Upload folder nahi bana: ' . $sub ] ); exit( 0 ); }
	$name = 'ai-' . $slugPart . '-' . date( 'Ymd' ) . '.' . $finalExt;
	$dst = $dir . '/' . $name;
	$i = 1;
	while ( file_exists( $dst ) ) { $i++; $dst = $dir . '/' . pathinfo( $name, PATHINFO_FILENAME ) . '-' . $i . '.' . $finalExt; }
	if ( ! @copy( $workFile, $dst ) ) { wc_out( [ 'error' => 'Image uploads mein copy nahi ho saki' ] ); exit( 0 ); }
	$attId = wp_insert_attachment( [ 'post_title' => $title, 'post_status' => 'inherit', 'post_mime_type' => $mime ], $dst );
	if ( ! $attId || is_wp_error( $attId ) ) { wc_out( [ 'error' => 'Attachment nahi bana' ] ); exit( 0 ); }
	$ameta = wp_generate_attachment_metadata( $attId, $dst );
	if ( is_array( $ameta ) ) { wp_update_attachment_metadata( $attId, $ameta ); }
	update_post_meta( $attId, '_tameero_ai', 1 );
	wc_out( [ 'attachment_id' => (int) $attId, 'url' => wp_get_attachment_url( $attId ), 'file' => $sub . '/' . basename( $dst ), 'webp' => $converted ] );
	exit( 0 );
}

if ( isset( $opts['media-set-thumb'] ) ) {
	$postId = (int) ( $opts['id'] ?? 0 );
	$attId  = (int) ( $opts['att'] ?? 0 );
	$post = get_post( $postId );
	if ( ! $post ) { wc_out( [ 'error' => 'Post/product nahi mila: ' . $postId ] ); exit( 0 ); }
	if ( get_post_type( $attId ) !== 'attachment' ) { wc_out( [ 'error' => 'Attachment nahi mila: ' . $attId ] ); exit( 0 ); }
	if ( ! set_post_thumbnail( $postId, $attId ) ) { wc_out( [ 'error' => 'Featured image set nahi hui' ] ); exit( 0 ); }
	wc_out( [ 'ok' => true, 'id' => $postId, 'type' => $post->post_type, 'attachment_id' => $attId, 'image' => wp_get_attachment_url( $attId ) ] );
	exit( 0 );
}

wc_out( [ 'error' => 'Koi mode nahi diya (products-list, product-get, pages-list, posts-list, plugins-list, themes-list, product-save, page-save, post-save, plugin-set, theme-activate)' ] );
exit( 1 );
