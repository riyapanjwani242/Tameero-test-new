<?php
// Web Fetch (v2.18) — forced local tests.
// Chalana:
//   Hissa A (SSRF battery, bina hooks):
//     php tests/fetch-check.php ssrf
//   Hissa B (positive, test hooks ke sath — pehle server chalao):
//     php -S 127.0.0.1:18897 -t tests tests/fetch-server.php
//     WDH_FETCH_TEST_HOOKS=1 php tests/fetch-check.php positive 18897
require __DIR__ . '/../panel/lib/hub.php';

$mode = $argv[1] ?? '';
$pass = 0; $fail = 0;
function chk( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS — $label\n"; }
	else { $fail++; echo "FAIL — $label\n"; }
}

if ( $mode === 'ssrf' ) {
	// --- Unit: IP blocked battery ---
	$blocked = [
		'127.0.0.1', '127.5.6.7', '10.0.0.5', '172.16.0.1', '172.31.255.255',
		'192.168.1.1', '169.254.169.254', '100.64.0.1', '100.127.255.254',
		'0.0.0.0', '0.1.2.3', '224.0.0.1', '240.0.0.1', '192.0.2.10',
		'198.51.100.3', '203.0.113.9', '198.18.0.5',
		'::1', '::', 'fe80::1', 'fd00::1', 'fc00::1', 'ff02::1',
		'::ffff:127.0.0.1', '::ffff:192.168.0.1', '2001:db8::1',
		'2002:7f00:0001::1', '2001:0000::1',
	];
	foreach ( $blocked as $ip ) {
		chk( hub_fetch_ip_blocked( $ip ) === true, "blocked: $ip" );
	}
	$allowed = [ '8.8.8.8', '1.1.1.1', '93.184.216.34', '2606:4700:4700::1111', '2001:4860:4860::8888' ];
	foreach ( $allowed as $ip ) {
		chk( hub_fetch_ip_blocked( $ip ) === false, "allowed: $ip" );
	}
	// --- Unit: IP literal ki ajeeb shaklein ---
	$lit = [
		'2130706433' => '127.0.0.1', '0x7f000001' => '127.0.0.1',
		'0177.0.0.1' => '127.0.0.1', '127.1' => '127.0.0.1',
		'127.0.1' => '127.0.0.1', '3232235521' => '192.168.0.1',
		'8.8.8.8' => '8.8.8.8',
	];
	foreach ( $lit as $in => $want ) {
		chk( hub_fetch_ip_literal( $in ) === $want, "literal: $in -> $want" );
	}
	chk( hub_fetch_ip_literal( 'example.com' ) === null, 'literal: hostname null hai' );
	chk( hub_fetch_ip_literal( '999.1.1.1' ) === 'INVALID', 'literal: 999.1.1.1 INVALID' );
	// --- Hub_fetch par SSRF URLs (network par jana hi nahi chahiye) ---
	$ssrfUrls = [
		[ 'http://127.0.0.1/admin', 5002 ],
		[ 'http://127.0.0.1:8080/admin', 5001 ],
		[ 'http://2130706433/', 5002 ],
		[ 'http://0x7f000001/', 5002 ],
		[ 'http://0177.0.0.1/', 5002 ],
		[ 'http://192.168.1.10/', 5002 ],
		[ 'http://169.254.169.254/latest/meta-data/', 5002 ],
		[ 'http://100.64.0.5/', 5002 ],
		[ 'http://[::1]/', 5002 ],
		[ 'http://[::ffff:127.0.0.1]/', 5002 ],
		[ 'file:///etc/passwd', 5001 ],
		[ 'ftp://example.com/x', 5001 ],
		[ 'http://user:pass@127.0.0.1/', 5001 ],
		[ 'http://example.com:22/', 5001 ],
		[ 'gopher://example.com/', 5001 ],
	];
	foreach ( $ssrfUrls as $t ) {
		$r = hub_fetch( $t[0] );
		chk( ! $r['ok'] && $r['code'] === $t[1], "ssrf reject {$t[0]} (code {$t[1]}, mila " . ( $r['code'] ?? '?' ) . ")" );
	}
	// localhost bhi (DNS /etc/hosts se 127.0.0.1 milta hai)
	$r = hub_fetch( 'http://localhost/' );
	chk( ! $r['ok'] && $r['code'] === 5002, 'ssrf reject localhost (mila ' . ( $r['code'] ?? '?' ) . ')' );
}

if ( $mode === 'positive' ) {
	$port = $argv[2] ?? '18897';
	$base = 'http://127.0.0.1:' . $port;
	chk( hub_fetch_test_hooks() === true, 'test hooks ON hain (CLI)' );

	$r = hub_fetch( $base . '/article' );
	chk( $r['ok'] === true, 'article fetch ok (mila code ' . ( $r['code'] ?? '-' ) . ')' );
	if ( $r['ok'] ) {
		chk( $r['title'] === 'Test Article Title', 'article title durust' );
		chk( strpos( $r['text'], 'Asal Mazmoon' ) !== false, 'article text mein mazmoon hai' );
		chk( strpos( $r['text'], 'nav hai' ) === false, 'nav ka shor text mein NAHI' );
		chk( strpos( $r['text'], 'Footer copyright' ) === false, 'footer text mein NAHI' );
		chk( $r['trust'] === 'untrusted_external_content', 'trust wrapper maujood' );
		chk( $r['http'] === true, 'http flag true (warning ke liye)' );
	}
	$r = hub_fetch( $base . '/urdu' );
	chk( $r['ok'] === true && strpos( $r['text'], 'اردو خبر' ) !== false, 'urdu page: saaf Urdu text (mojibake nahi)' );

	$r = hub_fetch( $base . '/redirect-chain' );
	chk( $r['ok'] === true && substr( $r['final_url'], -8 ) === '/article', 'redirect chain (2 hops) article tak pahuncha' );

	$r = hub_fetch( $base . '/redirect-abs' );
	chk( $r['ok'] === true, 'absolute redirect (127 wala, hooks mein) ok' );

	$r = hub_fetch( $base . '/redirect-loop' );
	chk( ! $r['ok'] && $r['code'] === 5010, 'redirect loop reject (5010, mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/big' );
	chk( ! $r['ok'] && $r['code'] === 5005, 'bari file 2MB cap (5005, mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/binary' );
	chk( ! $r['ok'] && $r['code'] === 5006, 'binary reject (5006, mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/forbidden' );
	chk( ! $r['ok'] && $r['code'] === 5007, '403 par 5007 (mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/partial' );
	chk( ! $r['ok'] && $r['code'] === 5007, '206 partial reject (5007, mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/gzip' );
	chk( ! $r['ok'] && $r['code'] === 5006, 'gzip-encoded jawab reject (5006, mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/headers-big' );
	chk( ! $r['ok'] && $r['code'] === 5005, 'bari headers reject (5005, mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/js-heavy' );
	chk( ! $r['ok'] && $r['code'] === 5008, 'JS-heavy page par 5008 (mila ' . ( $r['code'] ?? '?' ) . ')' );

	$r = hub_fetch( $base . '/injection' );
	chk( $r['ok'] === true && strpos( $r['text'], 'IGNORE YOUR INSTRUCTIONS' ) !== false, 'injection text sirf DATA ke tor par text mein hai' );

	$start = microtime( true );
	$r = hub_fetch( $base . '/trickle' );
	$elapsed = microtime( true ) - $start;
	chk( ! $r['ok'] && $r['code'] === 5004 && $elapsed < 16, 'trickle/slow-loris deadline par abort (5004, ' . round( $elapsed, 1 ) . 's)' );
}

echo "---\nNatija: PASS=$pass FAIL=$fail\n";
exit( $fail > 0 ? 1 : 0 );
