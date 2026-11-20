<?php
/**
 * v2.19: YouTube URL parser battery — CLI test.
 * WP stubs ke sath plugin file load karke parser azmata hai.
 * Istemal: php tests/youtube-parse-check.php
 */
error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', '/tmp/' );
function add_action( ...$a ) {}
function add_shortcode( ...$a ) {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://example.test/wp-content/plugins/tameero-youtube/'; }
$tyFile = getenv( 'WDH_YOUTUBE_PLUGIN' ) ? getenv( 'WDH_YOUTUBE_PLUGIN' ) : dirname( __DIR__ ) . '/plugins/tameero-youtube/tameero-youtube.php';
require $tyFile;

$pass = 0; $fail = 0;
function chk( $cond, $name ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS — $name\n"; } else { $fail++; echo "FAIL — $name\n"; } }

$ID = 'dQw4w9WgXcQ';
// Durust forms — sab aik hi ID dein
$good = [
	"https://www.youtube.com/watch?v=$ID",
	"https://youtube.com/watch?v=$ID",
	"https://m.youtube.com/watch?v=$ID",
	"http://www.youtube.com/watch?v=$ID&feature=share",
	"https://youtu.be/$ID",
	"https://youtu.be/$ID?t=45",
	"https://www.youtube.com/shorts/$ID",
	"https://www.youtube.com/shorts/$ID?feature=share",
	"https://www.youtube.com/embed/$ID",
	"https://www.youtube.com/live/$ID",
	"https://www.youtube.com/v/$ID",
	"https://www.youtube-nocookie.com/embed/$ID",
	"www.youtube.com/watch?v=$ID",
	$ID,
];
foreach ( $good as $u ) {
	$p = tameero_youtube_parse_url( $u );
	chk( $p && $p['id'] === $ID, "parse: $u" );
}
// Timestamp
$p = tameero_youtube_parse_url( "https://www.youtube.com/watch?v=$ID&t=90" );
chk( $p && $p['start'] === 90, 't=90 -> start 90' );
$p = tameero_youtube_parse_url( "https://www.youtube.com/watch?v=$ID&t=1m30s" );
chk( $p && $p['start'] === 90, 't=1m30s -> start 90' );
$p = tameero_youtube_parse_url( "https://youtu.be/$ID?t=1h2m3s" );
chk( $p && $p['start'] === 3723, 't=1h2m3s -> start 3723' );
// list= ignore — sirf video
$p = tameero_youtube_parse_url( "https://www.youtube.com/watch?v=$ID&list=PLrAXtmErZgOeiKm4sgNOknGvNjby9efdf" );
chk( $p && $p['id'] === $ID, 'list= wali watch URL se sirf video ID' );
// t array ho to warning ke baghair ignore, video accept, start 0
$p = tameero_youtube_parse_url( "https://www.youtube.com/watch?v=$ID&t[]=90" );
chk( $p && $p['id'] === $ID && $p['start'] === 0, 't[]= array ignore — video accept, start 0' );
// Reject battery
$bad = [
	'https://youtube.com.attacker.example/watch?v=' . $ID,   // nakli host
	'https://attacker.example/youtube.com/watch?v=' . $ID,   // ulta nakli
	'https://www.google.com/watch?v=' . $ID,
	'https://www.youtube.com/watch?v=short',                 // chhoti ID
	'https://www.youtube.com/watch?v=' . $ID . 'EXTRA',      // lambi ID
	'https://www.youtube.com/watch',                         // v nahi
	'https://www.youtube.com/playlist?list=PLrAXtmErZgOeiKm4sgNOknGvNjby9efdf', // sirf playlist
	'https://www.youtube.com/@SomeChannel',                  // channel
	'https://youtu.be/',                                     // khali
	'https://vimeo.com/12345678901',
	'https://www.youtube.com/watch?v[]=jNQXAC9IVRw',
	'bilkul bakwas',
	'',
	'javascript:alert(1)',
];
foreach ( $bad as $u ) {
	chk( tameero_youtube_parse_url( $u ) === null, "reject: " . ( $u === '' ? '(khali)' : $u ) );
}
echo "---\nNatija: PASS=$pass FAIL=$fail\n";
exit( $fail > 0 ? 1 : 0 );
