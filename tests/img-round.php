<?php
/**
 * TAMEERO v2.16 — AI Image ASAL round (mock gateway par):
 *   product banao -> image_generate -> Media Library file verify ->
 *   image_attach -> product featured asal lagi (content check) ->
 *   post banao -> doosri image (16:9) -> attach -> post featured verify.
 * Handlers seedha bulaye jate hain (library mode) — queue ka rasta
 * mcp-tools-check 'images' mode se alag azmaya jata hai.
 *
 * Usage: php tests/img-round.php <mcp.php path> <site slug> <gateway port>
 * Env: WDH_SITES_DIR (sites folder), WDH_PHP.
 * Settings file waqti tor par mock gateway par jati hai, aakhir mein
 * (shutdown par bhi) asal halat par wapas.
 * Output: aik line JSON {pass, fail, steps{...}}.
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$mcpFile = (string) ( $argv[1] ?? '' );
$slug    = (string) ( $argv[2] ?? '' );
$gwPort  = (int) ( $argv[3] ?? 18899 );
if ( $mcpFile === '' || ! file_exists( $mcpFile ) || $slug === '' ) {
	fwrite( STDERR, "Usage: php img-round.php <mcp.php path> <site slug> <gateway port>\n" );
	exit( 2 );
}

define( 'TAMEERO_MCP_LIBRARY', true );
require_once $mcpFile;

$steps = [];
$pass = 0; $fail = 0;
function stp( $name, $cond, $detail = '' ) {
	global $steps, $pass, $fail;
	$steps[ $name ] = $cond ? 'PASS' : 'FAIL';
	if ( $detail !== '' ) { $steps[ $name . '_detail' ] = $detail; }
	if ( $cond ) { $pass++; } else { $fail++; }
}

// Settings backup + mock gateway
$sf = hub_settings_file();
$origSettings = file_exists( $sf ) ? (string) file_get_contents( $sf ) : null;
register_shutdown_function( function () use ( $sf, $origSettings ) {
	if ( $origSettings === null ) { if ( file_exists( $sf ) ) { unlink( $sf ); } }
	else { file_put_contents( $sf, $origSettings ); }
} );
hub_save_settings( [
	'img_provider' => 'gateway', 'img_model' => 'mock/flash-image',
	'chat_gateway_url' => 'http://127.0.0.1:' . $gwPort . '/v1',
	'chat_gateway_key' => 'round-test-key',
] );
@unlink( hub_img_dir() . '/models-cache.json' );

try {
	// 1) Product banao
	$prod = mcp_tool_product_add( [ 'site' => $slug, 'name' => 'IMG Round Product', 'price' => '250' ] );
	$prodId = (int) ( $prod['product']['id'] ?? $prod['id'] ?? 0 );
	stp( 'product_create', $prodId > 0, 'id=' . $prodId );

	// 2) Image generate (product, 1:1)
	$gen = mcp_tool_image_generate( [ 'site' => $slug, 'prompt' => 'aik laal seb safed background par', 'ratio' => '1:1', 'purpose' => 'product' ] );
	$attId = (int) ( $gen['attachment_id'] ?? 0 );
	stp( 'generate', ! empty( $gen['generated'] ) && ! empty( $gen['imported'] ) && $attId > 0, 'att=' . $attId . ' job=' . ( $gen['job'] ?? '' ) );
	stp( 'generate_meta', ( $gen['size'] ?? '' ) === '120x90', 'size=' . ( $gen['size'] ?? '?' ) );

	// 3) Media Library file asal maujood + magic durust
	$siteDir = hub_sites_dir() . '/' . $slug;
	$found = glob( $siteDir . '/wp-content/uploads/tameero-ai/*/*/ai-image-*.*' ) ?: [];
	$magicOk = false; $foundSize = 0;
	foreach ( $found as $f ) {
		$head = (string) @file_get_contents( $f, false, null, 0, 12 );
		if ( hub_img_magic_ext( $head ) !== '' ) { $magicOk = true; $foundSize = filesize( $f ); break; }
	}
	stp( 'media_file', $found && $magicOk && $foundSize > 0, count( $found ) . ' files, size=' . $foundSize );

	// 4) Product par attach + featured asal lagi (content check)
	$att = mcp_tool_image_attach( [ 'site' => $slug, 'image_id' => $attId, 'target_id' => $prodId ] );
	stp( 'attach_product', ! empty( $att['attached'] ), '' );
	$pg = mcp_tool_product_get( [ 'site' => $slug, 'id' => $prodId ] );
	$img0 = (string) ( $pg['product']['images'][0] ?? '' );
	stp( 'product_featured', strpos( $img0, 'tameero-ai' ) !== false, $img0 );

	// 5) Post banao + featured image (16:9)
	$post = mcp_tool_post_create( [ 'site' => $slug, 'title' => 'IMG Round Post', 'content' => 'test content', 'status' => 'draft' ] );
	$postId = (int) ( $post['id'] ?? $post['post']['id'] ?? 0 );
	if ( ! $postId ) {
		$lst = mcp_tool_posts_list( [ 'site' => $slug ] );
		foreach ( (array) ( $lst['posts'] ?? [] ) as $p ) {
			if ( ( $p['title'] ?? '' ) === 'IMG Round Post' ) { $postId = (int) $p['id']; break; }
		}
	}
	stp( 'post_create', $postId > 0, 'id=' . $postId );
	$gen2 = mcp_tool_image_generate( [ 'site' => $slug, 'prompt' => 'paharon par suraj ki tasveer', 'ratio' => '16:9', 'purpose' => 'post' ] );
	$att2 = (int) ( $gen2['attachment_id'] ?? 0 );
	stp( 'generate_post', $att2 > 0, 'att=' . $att2 );
	$attp = mcp_tool_image_attach( [ 'site' => $slug, 'image_id' => $att2, 'target_id' => $postId ] );
	stp( 'attach_post', ! empty( $attp['attached'] ), '' );
	$pg2 = mcp_wc( $slug, [ 'post-get' => true, 'id' => $postId ] );
	stp( 'post_featured', (int) ( $pg2['post']['featured_image_id'] ?? 0 ) === $att2, 'featured=' . ( $pg2['post']['featured_image_id'] ?? '?' ) );

	// 6) NEGATIVE: ghalat attachment id par attach fail hona chahiye
	$negOk = false;
	try {
		mcp_tool_image_attach( [ 'site' => $slug, 'image_id' => 999999, 'target_id' => $prodId ] );
	} catch ( Exception $e ) { $negOk = true; }
	stp( 'negative_bad_attachment', $negOk, '' );
} catch ( Exception $e ) {
	stp( 'round_exception', false, $e->getMessage() );
}

echo json_encode( [ 'pass' => $pass, 'fail' => $fail, 'steps' => $steps ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
exit( $fail > 0 ? 1 : 0 );
