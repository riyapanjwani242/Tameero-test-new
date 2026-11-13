<?php
/**
 * TAMEERO tool — Demo data saaf karna (v2.14).
 *
 * Sirf wahi items delete hote hain jo site ki 'tameero_demo_registry'
 * option mein darj hain (demo-content.php --pack wale nishan) — user ke
 * asal data ko ye tool kabhi nahi chhoota. Registry mein content hash
 * hota hai; jis item ko user ne khud badal diya ho wo "modified" kehlata
 * hai aur panel usay pehle se selected nahi rakhta (hifazat).
 *
 * Registry na ho to purana feature-14 wala demo bhi pehchana jata hai:
 * posts par 'webdevhub-demo' tag, products par 'WDH-DEMO-' SKU prefix.
 * (Purane pack ki pages par koi nishan nahi tha — wo is tool se nahi miltin.)
 *
 * Usage:
 *   php demo-delete.php --path=<site dir> --list
 *   php demo-delete.php --path=<site dir> --apply --items=1,2,3 --terms=4,5 --attachments=6,7
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$path = null; $mode = ''; $selItems = []; $selTerms = []; $selAtt = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--path=' ) === 0 ) { $path = substr( $a, 7 ); }
	if ( $a === '--list' ) { $mode = 'list'; }
	if ( $a === '--apply' ) { $mode = 'apply'; }
	if ( strpos( $a, '--items=' ) === 0 ) { $selItems = array_filter( array_map( 'intval', explode( ',', substr( $a, 8 ) ) ) ); }
	if ( strpos( $a, '--terms=' ) === 0 ) { $selTerms = array_filter( array_map( 'intval', explode( ',', substr( $a, 8 ) ) ) ); }
	if ( strpos( $a, '--attachments=' ) === 0 ) { $selAtt = array_filter( array_map( 'intval', explode( ',', substr( $a, 14 ) ) ) ); }
}
if ( ! $path || ! file_exists( $path . '/wp-load.php' ) || $mode === '' ) {
	fwrite( STDERR, "Usage: demo-delete.php --path=<site> --list | --apply --items=.. --terms=.. --attachments=..\n" );
	exit( 1 );
}
require_once $path . '/wp-load.php';

// v2.14 gate diagnosis: DB aur registry ki asal halat hamesha STDERR par
// (stdout sirf JSON rehta hai; koi braces nahi taake CI ka parse na toote).
global $wpdb;
{
	$dbInfo = defined( 'FQDB' ) ? FQDB : ( defined( 'DB_NAME' ) ? DB_NAME : '?' );
	fwrite( STDERR, '##DEBUG## demo-delete db=' . $dbInfo . ' prefix=' . ( isset( $wpdb->prefix ) ? $wpdb->prefix : '?' ) . ' path=' . $path . "\n" );
}

function td_item_hash( $id, $type ) {
	$p = get_post( $id );
	if ( ! $p ) { return ''; }
	if ( $type === 'product' && class_exists( 'WC_Product_Simple' ) ) {
		$w = wc_get_product( $id );
		if ( $w ) { return md5( $w->get_name() . '|' . (int) $w->get_regular_price() . '|' . (int) $w->get_sale_price() ); }
	}
	return md5( $p->post_title . '|' . $p->post_content );
}

// Registry jama karo (na ho to legacy nishan se fehrist banao)
$reg = get_option( 'tameero_demo_registry' );
$regFound = is_array( $reg ) && ! empty( $reg['pack'] );
fwrite( STDERR, '##DEBUG## registry found=' . ( $regFound ? '1' : '0' ) . ' pack=' . ( $regFound ? (string) $reg['pack'] : 'none' ) . ' items=' . ( is_array( $reg ) ? count( (array) ( $reg['items'] ?? [] ) ) : 0 ) . ' terms=' . ( is_array( $reg ) ? count( (array) ( $reg['terms'] ?? [] ) ) : 0 ) . ' attachments=' . ( is_array( $reg ) ? count( (array) ( $reg['attachments'] ?? [] ) ) : 0 ) . "\n" );
$legacy = false;
if ( ! $regFound ) {
	fwrite( STDERR, '##DEBUG## legacy mode: tag=webdevhub-demo aur sku_prefix=WDH-DEMO- dhoondha jayega (pack items ka SKU DEMO-PACKID-NN hota hai — legacy scan unhen NAHI dhoondhta)' . "\n" );
	$legacy = true;
	$reg = [ 'pack' => 'legacy', 'items' => [], 'terms' => [], 'attachments' => [] ];
	$tagged = get_posts( [ 'tag' => 'webdevhub-demo', 'post_type' => 'any', 'numberposts' => -1, 'post_status' => 'any' ] );
	foreach ( $tagged as $p ) {
		$type = $p->post_type === 'product' ? 'product' : $p->post_type;
		$reg['items'][] = [ 'id' => (int) $p->ID, 'type' => $type, 'hash' => '' ];
	}
	if ( class_exists( 'WooCommerce' ) ) {
		$prods = get_posts( [ 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'any',
			'meta_query' => [ [ 'key' => '_sku', 'value' => 'WDH-DEMO-', 'compare' => 'LIKE' ] ] ] );
		$seen = array_column( $reg['items'], 'id' );
		foreach ( $prods as $p ) {
			if ( ! in_array( (int) $p->ID, $seen, true ) ) { $reg['items'][] = [ 'id' => (int) $p->ID, 'type' => 'product', 'hash' => '' ]; }
		}
	}
}

if ( $mode === 'list' ) {
	$out = [ 'pack' => (string) $reg['pack'], 'legacy' => $legacy, 'items' => [], 'terms' => [], 'attachments' => [] ];
	foreach ( (array) $reg['items'] as $it ) {
		$p = get_post( (int) $it['id'] );
		if ( ! $p ) { fwrite( STDERR, '##DEBUG## item id=' . (int) $it['id'] . ' get_post=NULL' . "\n" ); continue; }
		$modified = false;
		if ( ! empty( $it['hash'] ) ) { $modified = td_item_hash( (int) $it['id'], (string) $it['type'] ) !== $it['hash']; }
		$out['items'][] = [ 'id' => (int) $it['id'], 'type' => (string) $it['type'], 'title' => $p->post_title, 'modified' => $modified ];
	}
	foreach ( (array) $reg['terms'] as $t ) {
		$term = get_term( (int) $t['id'], (string) $t['taxonomy'] );
		if ( ! $term || is_wp_error( $term ) ) { continue; }
		$modified = ! empty( $t['hash'] ) && md5( $term->name ) !== $t['hash'];
		// Term tab hi delete ho sakta hai jab us mein registry se bahar ka item na ho
		$others = 0;
		$regIds = array_column( (array) $reg['items'], 'id' );
		if ( $t['taxonomy'] === 'product_cat' ) {
			$ids = get_objects_in_term( (int) $t['id'], 'product_cat' );
			foreach ( (array) $ids as $oid ) { if ( ! in_array( (int) $oid, $regIds, true ) ) { $others++; } }
		} elseif ( $t['taxonomy'] === 'category' ) {
			$ids = get_objects_in_term( (int) $t['id'], 'category' );
			foreach ( (array) $ids as $oid ) { if ( ! in_array( (int) $oid, $regIds, true ) ) { $others++; } }
		}
		$out['terms'][] = [ 'id' => (int) $t['id'], 'name' => $term->name, 'taxonomy' => (string) $t['taxonomy'], 'modified' => $modified, 'others' => $others ];
	}
	$thumbOf = [];
	foreach ( (array) $reg['items'] as $it ) { $th = get_post_thumbnail_id( (int) $it['id'] ); if ( $th ) { $thumbOf[ (int) $th ] = (int) $it['id']; } }
	foreach ( (array) $reg['attachments'] as $aid ) {
		$p = get_post( (int) $aid );
		if ( $p ) { $out['attachments'][] = [ 'id' => (int) $aid, 'title' => $p->post_title, 'used_by' => (int) ( $thumbOf[ (int) $aid ] ?? 0 ) ]; }
	}
	echo json_encode( $out, JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}

// ---- apply: sirf registry ke andar ke IDs hi delete honge ----
$regItemIds = array_column( (array) $reg['items'], 'id' );
$regTermIds = array_column( (array) $reg['terms'], 'id' );
$regAttIds  = array_map( 'intval', (array) $reg['attachments'] );
$done = [ 'items' => 0, 'terms' => 0, 'attachments' => 0, 'skipped' => 0 ];

foreach ( $selItems as $id ) {
	if ( ! in_array( $id, $regItemIds, true ) ) { $done['skipped']++; continue; }
	if ( wp_delete_post( $id, true ) ) {
		$done['items']++;
		$reg['items'] = array_values( array_filter( $reg['items'], function ( $it ) use ( $id ) { return (int) $it['id'] !== $id; } ) );
	} else { $done['skipped']++; }
}
$aliveThumb = [];
foreach ( (array) $reg['items'] as $it ) { $th = get_post_thumbnail_id( (int) $it['id'] ); if ( $th ) { $aliveThumb[ (int) $th ] = (int) $it['id']; } }
foreach ( $selAtt as $id ) {
	if ( ! in_array( $id, $regAttIds, true ) ) { $done['skipped']++; continue; }
	if ( isset( $aliveThumb[ $id ] ) ) { $done['skipped']++; continue; }
	if ( wp_delete_attachment( $id, true ) ) {
		$done['attachments']++;
		$reg['attachments'] = array_values( array_filter( $reg['attachments'], function ( $a ) use ( $id ) { return (int) $a !== $id; } ) );
	} else { $done['skipped']++; }
}
foreach ( $selTerms as $tid ) {
	if ( ! in_array( $tid, $regTermIds, true ) ) { $done['skipped']++; continue; }
	$entry = null;
	foreach ( (array) $reg['terms'] as $t ) { if ( (int) $t['id'] === $tid ) { $entry = $t; break; } }
	if ( ! $entry ) { $done['skipped']++; continue; }
	// Aakhri hifazat: term SIRF tab delete ho jab wo bilkul khaali ho —
	// chahe reh gaya item user ka asal ho ya bach gaya modified demo item,
	// category ke sath koi item juda ho to category mehfooz rahe gi.
	$ids = get_objects_in_term( $tid, (string) $entry['taxonomy'] );
	if ( ! empty( $ids ) ) { $done['skipped']++; continue; }
	$r = wp_delete_term( $tid, (string) $entry['taxonomy'] );
	if ( $r && ! is_wp_error( $r ) ) {
		$done['terms']++;
		$reg['terms'] = array_values( array_filter( $reg['terms'], function ( $t ) use ( $tid ) { return (int) $t['id'] !== $tid; } ) );
	} else { $done['skipped']++; }
}

if ( empty( $reg['items'] ) && empty( $reg['terms'] ) && empty( $reg['attachments'] ) ) {
	delete_option( 'tameero_demo_registry' );
} else {
	update_option( 'tameero_demo_registry', $reg, false );
}
echo json_encode( $done ) . "\n";
exit( 0 );
