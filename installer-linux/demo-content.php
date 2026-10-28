<?php
/**
 * WebDevHub tool — Urdu demo content pack (feature 14).
 * Nayi site khaali na lage: 5 blog posts, 2 pages, aur store ho
 * to 5 products — sab par 'webdevhub-demo' nishan hai taake baad
 * mein asani se pehchana/mitaya ja sake.
 *
 * Usage: php demo-content.php --path=<site dir> --blueprint=business|store|blank
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$path = null; $blueprint = 'business';
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--path=' ) === 0 ) { $path = substr( $a, 7 ); }
	if ( strpos( $a, '--blueprint=' ) === 0 ) { $blueprint = substr( $a, 12 ); }
}
if ( ! $path || ! file_exists( $path . '/wp-load.php' ) ) {
	fwrite( STDERR, "Site ka path ghalat hai.\n" );
	exit( 1 );
}
require_once $path . '/wp-load.php';

// Pehle se pack laga hai to dobara nahi
$existing = get_posts( [ 'tag' => 'webdevhub-demo', 'post_type' => 'any', 'numberposts' => 1 ] );
if ( $existing ) {
	echo "Demo content pehle se mojood hai — dobara nahi lagaya.\n";
	exit( 0 );
}

$posts = [
	[ 'Khush Aamdeed — Hamari Website Tayyar Hai',
		"Assalam-o-Alaikum! Hamari nayi website ab live hai.\n\nYahan aap ko hamari tamam khidmat aur products ki maloomat milen gi. Hum ne khaas taur par koshish ki hai ke website ka istemal har kisi ke liye asaan ho — mobile par bhi utni hi tez aur saaf.\n\nKoi sawal ho to Rabta wale safhe se hum se baat karein, humein khushi ho gi." ],
	[ 'Apne Business Ko Online Lane Ke 5 Faide',
		"Aaj ke daur mein online mojoodgi sirf bari companies ke liye nahi — chhota business bhi website se bohat kuch hasil kar sakta hai.\n\n1. Grahak aap ko kahin se bhi dhoondh sakte hain.\n2. Aap ki dukaan 24 ghante khuli rehti hai.\n3. WhatsApp par orders lena asaan ho jata hai.\n4. Logo ka aitbaar barhta hai.\n5. Naye shehron tak pohanch mumkin hoti hai.\n\nShuruaat chhoti rakhein — ek saaf website aur sahi maloomat hi kaafi hai." ],
	[ 'Order Karne Ka Tareeqa — Mukammal Rehnumai',
		"Hamare yahan order karna bohat asaan hai.\n\nPehle apni pasand ki product chunein aur 'Order Karein' par click karein. Phir apna naam, mobile number aur address darj karein. Hamari team WhatsApp par tasdeeq ke liye rabta kare gi.\n\nPayment advance mein hoti hai (EasyPaisa / JazzCash / bank transfer), aur delivery charges order ke hisaab se pehle hi bata diye jate hain — koi chhupa kharcha nahi." ],
	[ 'Quality Par Koi Samjhota Nahi',
		"Hum har product ko bhejne se pehle khud check karte hain.\n\nKapra ho ya koi aur cheez — rang, size aur finishing ki jaanch hamari pehli tarjeeh hai. Agar kabhi cheez mein koi masla nikle to foran hum se rabta karein, masla hal karna hamari zimmedari hai.\n\nAap ka aitbaar hi hamari asal kamai hai." ],
	[ 'Nayi Collection Jald Aa Rahi Hai',
		"Khush khabri! Hamari nayi collection par kaam chal raha hai aur jald hi website par nazar aye gi.\n\nIs dafa hum ne grahakon ki pasand ko madde nazar rakh kar rangon aur designs ka intikhab kiya hai. Sab se pehle khabar pane ke liye hamari posts dekhte rahein.\n\nApni raye humein zaroor batayein — aap ki tajaveez hi hamari rehnuma hain." ],
];

foreach ( $posts as $p ) {
	$id = wp_insert_post( [
		'post_title'   => $p[0],
		'post_content' => $p[1],
		'post_status'  => 'publish',
		'post_type'    => 'post',
	] );
	if ( $id ) { wp_set_post_tags( $id, 'webdevhub-demo', true ); }
}

$pages = [
	[ 'Hamare Baare Mein',
		"Hum ek chhoti si mehnati team hain jo apne grahakon ko behtareen quality aur munasib qeemat par products pohanchane ke liye kaam karti hai.\n\nHamara waada saaf hai: jo dikhaya jaye, wahi bheja jaye. Isi liye hamari har product ki asal tasaveer aur mukammal tafseel website par di jati hai.\n\nAap ke aitbaar ka shukriya!" ],
	[ 'Rabta Karein',
		"Hum se rabta karne ke liye WhatsApp sab se tez tareeqa hai.\n\nKaam ke auqaat: Peer ta Hafta, subah 9 se raat 9 baje tak.\n\nAap ka paigham milte hi hamari team jawab de gi. Orders, sawalon ya tajaveez — sab ke liye hum hazir hain." ],
];
foreach ( $pages as $p ) {
	wp_insert_post( [
		'post_title'   => $p[0],
		'post_content' => $p[1],
		'post_status'  => 'publish',
		'post_type'    => 'page',
	] );
}
echo "Demo posts/pages lag gaye.\n";

// Store blueprint ho to demo products bhi
if ( $blueprint === 'store' && class_exists( 'WooCommerce' ) && class_exists( 'WC_Product_Simple' ) ) {
	$products = [
		[ 'Khaddar Suit — Gulabi (2 Piece)', 3500, 'Naram khaddar, sardiyon ke liye behtareen. Rang pakka aur kapra mazboot.' ],
		[ 'Lawn Suit — Neela (3 Piece)', 4200, 'Halki lawn ke sath khoobsurat dupatta. Garmiyon ki pehli pasand.' ],
		[ 'Cotton Kurta — Safed', 2200, 'Saada aur pur-waqar safed kurta, har season ke liye.' ],
		[ 'Shawl — Kashmiri Style', 2800, 'Garam aur naram shawl, sard raaton ka sathi.' ],
		[ 'Printed Dupatta — Phool Daar', 1200, 'Rang barang phoolon wala dupatta, har suit ke sath jachay.' ],
	];
	$i = 0;
	foreach ( $products as $pr ) {
		$i++;
		$product = new WC_Product_Simple();
		$product->set_name( $pr[0] );
		$product->set_regular_price( (string) $pr[1] );
		$product->set_short_description( $pr[2] );
		$product->set_sku( 'WDH-DEMO-' . $i );
		$product->set_status( 'publish' );
		$product->save();
	}
	echo "Demo products lag gaye.\n";
}

echo "Demo content pack tayyar hai.\n";
exit( 0 );
