<?php
/**
 * WebDevHub tool — Urdu demo content pack (feature 14).
 * Nayi site khaali na lage: 5 blog posts, 2 pages, aur store ho
 * to 5 products — sab par 'webdevhub-demo' nishan hai taake baad
 * mein asani se pehchana/mitaya ja sake.
 *
 * Usage: php demo-content.php --path=<site dir> --blueprint=business|store|blank
 *   v2.14: --pack=<id> do to bundle/demo-packs wala pack-driven mode chalega
 *   (registry + _tameero_demo nishan ke sath, demo-delete.php se saaf hota hai).
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$path = null; $blueprint = 'business'; $packId = '';
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--path=' ) === 0 ) { $path = substr( $a, 7 ); }
	if ( strpos( $a, '--blueprint=' ) === 0 ) { $blueprint = substr( $a, 12 ); }
	if ( strpos( $a, '--pack=' ) === 0 ) { $packId = preg_replace( '/[^a-z0-9\-]/', '', substr( $a, 7 ) ); }
}
if ( ! $path || ! file_exists( $path . '/wp-load.php' ) ) {
	fwrite( STDERR, "Site ka path ghalat hai.\n" );
	exit( 1 );
}
require_once $path . '/wp-load.php';
// =====================================================================
// v2.14 PACK MODE — bundle/demo-packs/<id>/pack.json se demo data.
// Har item par _tameero_demo meta + site option 'tameero_demo_registry'
// mein IDs aur content hash (baad mein "modified" pehchan ke liye).
// =====================================================================
if ( $packId !== '' ) {
	$repoDir  = dirname( __DIR__ );
	$packDir  = $repoDir . '/bundle/demo-packs/' . $packId;
	$packFile = $packDir . '/pack.json';
	if ( ! file_exists( $packFile ) ) {
		fwrite( STDERR, "Demo pack nahi mila: $packId\n" );
		exit( 1 );
	}
	$pack = json_decode( (string) file_get_contents( $packFile ), true );
	if ( ! is_array( $pack ) ) {
		fwrite( STDERR, "Demo pack ki file kharab hai: $packId\n" );
		exit( 1 );
	}
	$reg = get_option( 'tameero_demo_registry' );
	if ( is_array( $reg ) && ! empty( $reg['pack'] ) ) {
		echo "Demo pack pehle se laga hai ({$reg['pack']}) — dobara nahi lagaya.\n";
		exit( 0 );
	}
	$reg = [ 'pack' => $packId, 'applied_at' => time(), 'items' => [], 'terms' => [], 'attachments' => [] ];

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// Tasveer ko media library mein lao — attachment bhi registry mein jata hai
	$importImage = function ( $file, $title ) use ( $packDir, &$reg, $packId ) {
		$src = $packDir . '/img/' . $file;
		if ( ! file_exists( $src ) ) { echo "Note: tasveer nahi mili: $file\n"; return 0; }
		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) { return 0; }
		$name = 'demo-' . $packId . '-' . basename( $file );
		$dst  = $up['path'] . '/' . $name;
		$i = 1;
		while ( file_exists( $dst ) ) { $i++; $dst = $up['path'] . '/' . pathinfo( $name, PATHINFO_FILENAME ) . '-' . $i . '.' . pathinfo( $name, PATHINFO_EXTENSION ); }
		if ( ! copy( $src, $dst ) ) { echo "Note: tasveer copy nahi ho saki: $file\n"; return 0; }
		$attId = wp_insert_attachment( [
			'post_title'     => $title,
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/webp',
		], $dst );
		if ( ! $attId || is_wp_error( $attId ) ) { return 0; }
		$meta = wp_generate_attachment_metadata( $attId, $dst );
		if ( is_array( $meta ) ) { wp_update_attachment_metadata( $attId, $meta ); }
		update_post_meta( $attId, '_tameero_demo', $packId );
		$reg['attachments'][] = (int) $attId;
		return (int) $attId;
	};

	$termByName = [];
	$getTerm = function ( $taxonomy, $name ) use ( &$termByName, &$reg, $packId ) {
		$key = $taxonomy . '|' . $name;
		if ( isset( $termByName[ $key ] ) ) { return $termByName[ $key ]; }
		$t = term_exists( $name, $taxonomy );
		if ( ! $t ) {
			$t = wp_insert_term( $name, $taxonomy );
			if ( ! is_wp_error( $t ) ) {
				update_term_meta( (int) $t['term_id'], '_tameero_demo', $packId );
				$reg['terms'][] = [ 'id' => (int) $t['term_id'], 'taxonomy' => $taxonomy, 'hash' => md5( $name ) ];
			}
		}
		if ( is_wp_error( $t ) || ! $t ) { return 0; }
		$tid = is_array( $t ) ? (int) $t['term_id'] : (int) $t;
		$termByName[ $key ] = $tid;
		return $tid;
	};

	if ( ( $pack['type'] ?? '' ) === 'store' ) {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			fwrite( STDERR, "Store pack ke liye WooCommerce active hona chahiye.\n" );
			exit( 1 );
		}
		foreach ( (array) ( $pack['categories'] ?? [] ) as $catName ) { $getTerm( 'product_cat', (string) $catName ); }
		$n = 0;
		foreach ( (array) ( $pack['products'] ?? [] ) as $pr ) {
			$n++;
			$p = new WC_Product_Simple();
			$p->set_name( (string) $pr['name'] );
			$p->set_regular_price( (string) (int) $pr['price'] );
			if ( ! empty( $pr['sale'] ) ) { $p->set_sale_price( (string) (int) $pr['sale'] ); }
			$p->set_short_description( (string) ( $pr['desc'] ?? '' ) );
			$p->set_description( (string) ( $pr['desc'] ?? '' ) );
			$p->set_sku( 'DEMO-' . strtoupper( $packId ) . '-' . str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) );
			$p->set_status( 'publish' );
			if ( ! empty( $pr['featured'] ) ) { $p->set_featured( true ); }
			if ( ! empty( $pr['cat'] ) ) {
				$tid = $getTerm( 'product_cat', (string) $pr['cat'] );
				if ( $tid ) { $p->set_category_ids( [ $tid ] ); }
			}
			$pid = $p->save();
			if ( ! $pid ) { continue; }
			if ( ! empty( $pr['img'] ) ) {
				$att = $importImage( (string) $pr['img'], (string) $pr['name'] );
				if ( $att ) { set_post_thumbnail( $pid, $att ); }
			}
			update_post_meta( $pid, '_tameero_demo', $packId );
			$reg['items'][] = [ 'id' => (int) $pid, 'type' => 'product', 'hash' => md5( (string) $pr['name'] . '|' . (int) $pr['price'] . '|' . (int) ( $pr['sale'] ?? 0 ) ) ];
		}
		echo "Demo pack lag gaya: $packId (" . count( $reg['items'] ) . " products)\n";
	} elseif ( ( $pack['type'] ?? '' ) === 'news' ) {
		foreach ( (array) ( $pack['posts'] ?? [] ) as $po ) {
			$pid = wp_insert_post( [
				'post_title'   => (string) $po['title'],
				'post_content' => (string) $po['body'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
			] );
			if ( ! $pid || is_wp_error( $pid ) ) { continue; }
			if ( ! empty( $po['cat'] ) ) {
				$term = get_term_by( 'slug', (string) $po['cat'], 'category' );
				if ( $term ) { wp_set_post_categories( $pid, [ (int) $term->term_id ], false ); }
			}
			wp_set_post_tags( $pid, 'webdevhub-demo', true );
			if ( ! empty( $po['img'] ) ) {
				$att = $importImage( (string) $po['img'], (string) $po['title'] );
				if ( $att ) { set_post_thumbnail( $pid, $att ); }
			}
			update_post_meta( $pid, '_tameero_demo', $packId );
			$reg['items'][] = [ 'id' => (int) $pid, 'type' => 'post', 'hash' => md5( (string) $po['title'] . '|' . (string) $po['body'] ) ];
		}
		echo "Demo pack lag gaya: $packId (" . count( $reg['items'] ) . " posts)\n";
	} else {
		fwrite( STDERR, "Demo pack ki qisam samajh nahi ayi: $packId\n" );
		exit( 1 );
	}
	update_option( 'tameero_demo_registry', $reg, false );
	// v2.14 gate diagnosis: registry ko foran dobara parh kar verify karo —
	// ye line creation context ka saboot hai (job log mein dikhegi).
	$rb = get_option( 'tameero_demo_registry' );
	$rbOk = is_array( $rb ) && ( $rb['pack'] ?? '' ) === $packId && count( (array) ( $rb['items'] ?? [] ) ) === count( $reg['items'] );
	echo 'Registry verify: pack=' . $packId . ' items=' . count( $reg['items'] ) . ' readback=' . ( $rbOk ? 'OK' : 'FAIL' ) . "\n";
	echo "Demo data tayyar hai (pack: $packId).\n";
	exit( 0 );
}


// Pehle se pack laga hai to dobara nahi
$existing = get_posts( [ 'tag' => 'webdevhub-demo', 'post_type' => 'any', 'numberposts' => 1 ] );
if ( $existing ) {
	echo "Demo content pehle se mojood hai — dobara nahi lagaya.\n";
	exit( 0 );
}

// Dukaan theme active ho to demo content Urdu script mein lagta hai
// (theme Urdu-first hai; Roman Urdu wahan ajnabi lage ga). Baqi
// themes par maujooda Roman Urdu pack hi chalta hai.
$isDukaan = ( $blueprint === 'store' && get_stylesheet() === 'dukaan-fse' );

if ( $isDukaan ) {
	$posts = [
		[ 'خوش آمدید — ہماری ویب سائٹ تیار ہے',
			"السلام علیکم! ہماری نئی ویب سائٹ اب لائیو ہے۔\n\nیہاں آپ کو ہماری تمام خدمات اور پروڈکٹس کی معلومات ملیں گی۔ ہم نے خاص طور پر کوشش کی ہے کہ ویب سائٹ کا استعمال ہر کسی کے لیے آسان ہو — موبائل پر بھی اتنی ہی تیز اور صاف۔\n\nکوئی سوال ہو تو رابطے والے صفحے سے ہم سے بات کریں، ہمیں خوشی ہو گی۔" ],
		[ 'اپنے کاروبار کو آن لائن لانے کے 5 فائدے',
			"آج کے دور میں آن لائن موجودگی صرف بڑی کمپنیوں کے لیے نہیں — چھوٹا کاروبار بھی ویب سائٹ سے بہت کچھ حاصل کر سکتا ہے۔\n\n1. گاہک آپ کو کہیں سے بھی ڈھونڈ سکتے ہیں۔\n2. آپ کی دکان 24 گھنٹے کھلی رہتی ہے۔\n3. واٹس ایپ پر آرڈر لینا آسان ہو جاتا ہے۔\n4. لوگوں کا اعتماد بڑھتا ہے۔\n5. نئے شہروں تک پہنچ ممکن ہوتی ہے۔\n\nشروعات چھوٹی رکھیں — ایک صاف ویب سائٹ اور درست معلومات ہی کافی ہیں۔" ],
		[ 'آرڈر کرنے کا طریقہ — مکمل رہنمائی',
			"ہمارے یہاں آرڈر کرنا بہت آسان ہے۔\n\nپہلے اپنی پسند کی پروڈکٹ چنیں اور 'آرڈر کریں' پر کلک کریں۔ پھر اپنا نام، موبائل نمبر اور پتہ درج کریں۔ ہماری ٹیم واٹس ایپ پر تصدیق کے لیے رابطہ کرے گی۔\n\nادائیگی ایڈوانس میں ہوتی ہے (ایزی پیسہ / جاز کیش / بینک ٹرانسفر)، اور ڈیلیوری چارجز آرڈر کے حساب سے پہلے ہی بتا دیے جاتے ہیں — کوئی پوشیدہ خرچہ نہیں۔" ],
		[ 'معیار پر کوئی سمجھوتا نہیں',
			"ہم ہر پروڈکٹ کو بھیجنے سے پہلے خود جانچتے ہیں۔\n\nکپڑا ہو یا کوئی اور چیز — رنگ، سائز اور فنشنگ کی جانچ ہماری پہلی ترجیح ہے۔ اگر کبھی چیز میں کوئی مسئلہ نکلے تو فوراً ہم سے رابطہ کریں، مسئلہ حل کرنا ہماری ذمہ داری ہے۔\n\nآپ کا اعتماد ہی ہماری اصل کمائی ہے۔" ],
		[ 'نئی کلیکشن جلد آ رہی ہے',
			"خوشخبری! ہماری نئی کلیکشن پر کام چل رہا ہے اور جلد ہی ویب سائٹ پر نظر آئے گی۔\n\nاس دفعہ ہم نے گاہکوں کی پسند کو مدِنظر رکھ کر رنگوں اور ڈیزائنوں کا انتخاب کیا ہے۔ سب سے پہلے خبر پانے کے لیے ہماری پوسٹس دیکھتے رہیں۔\n\nاپنی رائے ہمیں ضرور بتائیں — آپ کی تجاویز ہی ہماری رہنما ہیں۔" ],
	];
} else {
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
}

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
if ( $isDukaan ) {
	$pages = [
		[ 'ہمارے بارے میں',
			"ہم ایک چھوٹی سی محنتی ٹیم ہیں جو اپنے گاہکوں کو بہترین معیار اور مناسب قیمت پر پروڈکٹس پہنچانے کے لیے کام کرتی ہے۔\n\nہمارا وعدہ صاف ہے: جو دکھایا جائے، وہی بھیجا جائے۔ اسی لیے ہماری ہر پروڈکٹ کی اصل تصاویر اور مکمل تفصیل ویب سائٹ پر دی جاتی ہے۔\n\nآپ کے اعتماد کا شکریہ!" ],
		[ 'رابطہ کریں',
			"ہم سے رابطہ کرنے کے لیے واٹس ایپ سب سے تیز طریقہ ہے۔\n\nکام کے اوقات: پیر تا ہفتہ، صبح 9 سے رات 9 بجے تک۔\n\nآپ کا پیغام ملتے ہی ہماری ٹیم جواب دے گی۔ آرڈرز، سوالوں یا تجاویز — سب کے لیے ہم حاضر ہیں۔" ],
		[ 'ڈیلیوری اور ادائیگی',
			"آرڈر کی تصدیق کے بعد ڈیلیوری عام طور پر 2 سے 5 کاروباری دنوں میں ہو جاتی ہے۔\n\nادائیگی ایڈوانس میں ہوتی ہے — ایزی پیسہ، جاز کیش یا بینک ٹرانسفر کے ذریعے۔ ادائیگی کی تصدیق واٹس ایپ پر کر لیں، اس کے بعد آپ کا آرڈر روانہ کر دیا جاتا ہے۔\n\nڈیلیوری چارجز آرڈر کے حساب سے تصدیق کے وقت ہی بتا دیے جاتے ہیں — کوئی پوشیدہ خرچہ نہیں۔" ],
	];
}
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
	if ( $isDukaan ) {
		$products = [
			[ 'کھدر سوٹ — گلابی (دو پیس)', 3500, 'نرم کھدر، سردیوں کے لیے بہترین۔ رنگ پکا اور کپڑا مضبوط۔', 'زنانہ کپڑے' ],
			[ 'لان سوٹ — نیلا (تھری پیس)', 4200, 'ہلکی لان کے ساتھ خوبصورت دوپٹہ۔ گرمیوں کی پہلی پسند۔', 'زنانہ کپڑے' ],
			[ 'کاٹن کُرتہ — سفید', 2200, 'سادہ اور پُروقار سفید کُرتہ، ہر موسم کے لیے۔', 'مردانہ کپڑے' ],
			[ 'شال — کشمیری اسٹائل', 2800, 'گرم اور نرم شال، سرد راتوں کا ساتھی۔', 'شال اور دوپٹے' ],
			[ 'پرنٹڈ دوپٹہ — پھول دار', 1200, 'رنگ برنگے پھولوں والا دوپٹہ، ہر سوٹ کے ساتھ جچے۔', 'شال اور دوپٹے' ],
		];
	}
	$i = 0;
	foreach ( $products as $pr ) {
		$i++;
		$product = new WC_Product_Simple();
		$product->set_name( $pr[0] );
		$product->set_regular_price( (string) $pr[1] );
		$product->set_short_description( $pr[2] );
		$product->set_sku( 'WDH-DEMO-' . $i );
		$product->set_status( 'publish' );
		if ( ! empty( $pr[3] ) ) {
			$term = term_exists( $pr[3], 'product_cat' );
			if ( ! $term ) { $term = wp_insert_term( $pr[3], 'product_cat' ); }
			if ( ! is_wp_error( $term ) ) {
				$tid = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				if ( $tid ) { $product->set_category_ids( [ $tid ] ); }
			}
		}
		$product->save();
	}
	echo "Demo products lag gaye.\n";
}

echo "Demo content pack tayyar hai.\n";
exit( 0 );
