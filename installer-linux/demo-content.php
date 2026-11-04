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
