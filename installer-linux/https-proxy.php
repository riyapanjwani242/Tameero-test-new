<?php
/**
 * WebDevHub — Local HTTPS proxy (feature 19).
 *
 * PHP ka built-in server HTTPS nahi de sakta, is liye ye chhota
 * TLS proxy site ke HTTP server ke aage lagta hai:
 *   browser -> https://127.0.0.1:<httpsPort> (ye proxy, cert ke sath)
 *           -> http://127.0.0.1:<httpPort> (asal site server)
 *
 * Sirf TLS hi nahi — proxy do aur kaam karta hai taake WordPress
 * asal mein HTTPS par chale (warna WP apne http URL par redirect
 * kar deta aur saare assets http ke rehte):
 *   - Request mein X-Forwarded-Proto: https aur X-WDH-HTTPS-Port
 *     jorta hai — bundle ka mu-plugin inhen dekh kar siteurl/home
 *     ko https origin par kar deta hai.
 *   - Response ke Location header mein purana http origin ho to
 *     https origin se badal deta hai.
 * Headers ke baad body seedhi pipe hoti hai (koi buffering nahi).
 *
 * Certificates PHP ke openssl functions se bante hain (cross-
 * platform, bahari tool nahi chahiye):
 *   - Ek local CA ("WebDevHub Local CA") — ek dafa banta hai.
 *     Browser ki warning khatam karne ke liye dashboard se CA
 *     certificate download karke trust store mein lagana hota hai.
 *   - Har site ka apna cert (SAN: localhost, <slug>.local, 127.0.0.1)
 *
 * Istemal (worker wdh.php httpsstart chalata hai):
 *   php https-proxy.php --ca-dir D --slug S --https-port P1 --http-port P2
 */

if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$opts = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--' ) === 0 ) {
		[ $k, $v ] = array_pad( explode( '=', substr( $a, 2 ), 2 ), 2, '1' );
		$opts[ $k ] = $v;
	}
}
$caDir = $opts['ca-dir'] ?? '';
$slug = $opts['slug'] ?? 'site';
$httpsPort = (int) ( $opts['https-port'] ?? 0 );
$httpPort = (int) ( $opts['http-port'] ?? 0 );
if ( ! $caDir || ! $httpsPort || ! $httpPort ) { fwrite( STDERR, "ca-dir/https-port/http-port lazmi hain.\n" ); exit( 1 ); }
if ( ! is_dir( $caDir ) ) { mkdir( $caDir, 0775, true ); }

function wdh_openssl_cnf( $path, $san = '' ) {
	$cnf = "[req]\ndistinguished_name = dn\nreq_extensions = v3_req\n[dn]\n"
		. "[v3_ca]\nbasicConstraints = critical,CA:TRUE\nkeyUsage = critical,keyCertSign,cRLSign\nsubjectKeyIdentifier = hash\n"
		. "[v3_req]\nbasicConstraints = CA:FALSE\nkeyUsage = digitalSignature,keyEncipherment\nextendedKeyUsage = serverAuth\nsubjectAltName = " . ( $san ?: 'DNS:localhost,IP:127.0.0.1' ) . "\n";
	file_put_contents( $path, $cnf );
	return $path;
}

function wdh_ensure_ca( $caDir ) {
	$crt = $caDir . '/ca.crt';
	$key = $caDir . '/ca.key';
	if ( file_exists( $crt ) && file_exists( $key ) ) { return [ $crt, $key ]; }
	$cnf = wdh_openssl_cnf( $caDir . '/openssl.cnf' );
	$priv = openssl_pkey_new( [ 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf ] );
	if ( ! $priv ) { fwrite( STDERR, "CA key nahi bani.\n" ); exit( 1 ); }
	$dn = [ 'commonName' => 'WebDevHub Local CA', 'organizationName' => 'WebDevHub' ];
	$csr = openssl_csr_new( $dn, $priv, [ 'digest_alg' => 'sha256', 'config' => $cnf ] );
	$cert = openssl_csr_sign( $csr, null, $priv, 3650, [ 'digest_alg' => 'sha256', 'x509_extensions' => 'v3_ca', 'config' => $cnf ] );
	if ( ! $cert ) { fwrite( STDERR, "CA cert nahi bana.\n" ); exit( 1 ); }
	openssl_x509_export( $cert, $crtOut );
	openssl_pkey_export( $priv, $keyOut, null, [ 'config' => $cnf ] );
	file_put_contents( $crt, $crtOut );
	file_put_contents( $key, $keyOut );
	@chmod( $key, 0600 );
	echo "Local CA tayyar hai.\n";
	return [ $crt, $key ];
}

function wdh_ensure_site_cert( $caDir, $slug, $caCrt, $caKey ) {
	$crt = $caDir . '/' . $slug . '.crt';
	$key = $caDir . '/' . $slug . '.key';
	$pem = $caDir . '/' . $slug . '.pem';
	if ( file_exists( $pem ) ) { return $pem; }
	$san = 'DNS:localhost,DNS:' . $slug . '.local,IP:127.0.0.1';
	$cnf = wdh_openssl_cnf( $caDir . '/openssl-' . $slug . '.cnf', $san );
	$priv = openssl_pkey_new( [ 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf ] );
	$dn = [ 'commonName' => $slug . '.local', 'organizationName' => 'WebDevHub' ];
	$csr = openssl_csr_new( $dn, $priv, [ 'digest_alg' => 'sha256', 'config' => $cnf ] );
	$caCertRes = openssl_x509_read( file_get_contents( $caCrt ) );
	$caKeyRes = openssl_pkey_get_private( file_get_contents( $caKey ) );
	$cert = openssl_csr_sign( $csr, $caCertRes, $caKeyRes, 825, [ 'digest_alg' => 'sha256', 'x509_extensions' => 'v3_req', 'config' => $cnf ] );
	if ( ! $cert ) { fwrite( STDERR, "Site cert nahi bana.\n" ); exit( 1 ); }
	openssl_x509_export( $cert, $crtOut );
	openssl_pkey_export( $priv, $keyOut, null, [ 'config' => $cnf ] );
	file_put_contents( $crt, $crtOut );
	file_put_contents( $key, $keyOut );
	@chmod( $key, 0600 );
	file_put_contents( $pem, $crtOut . $keyOut );
	echo "Site cert tayyar hai: $slug\n";
	return $pem;
}

[ $caCrt, $caKey ] = wdh_ensure_ca( $caDir );
$pem = wdh_ensure_site_cert( $caDir, $slug, $caCrt, $caKey );

$ctx = stream_context_create( [ 'ssl' => [
	'local_cert' => $pem,
	'allow_self_signed' => true,
	'verify_peer' => false,
	'disable_compression' => true,
] ] );
$server = @stream_socket_server( 'tls://127.0.0.1:' . $httpsPort, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx );
if ( ! $server ) { fwrite( STDERR, "HTTPS port $httpsPort nahi khula: $errstr\n" ); exit( 1 ); }
stream_set_blocking( $server, false );
echo 'HTTPS proxy chal raha hai: https://127.0.0.1:' . $httpsPort . ' -> http://127.0.0.1:' . $httpPort . "\n";

$httpOrigin = 'http://127.0.0.1:' . $httpPort;
$httpsOrigin = 'https://127.0.0.1:' . $httpsPort;

// Select loop. Har connection ki haalat:
//   tls, tcp, reqBuf/reqDone (client->server headers), resBuf/resDone
$clients = [];
while ( true ) {
	$read = [ $server ];
	foreach ( $clients as $c ) {
		$read[] = $c['tls'];
		if ( $c['tcp'] ) { $read[] = $c['tcp']; }
	}
	$write = null;
	$except = null;
	$n = @stream_select( $read, $write, $except, 5 );
	if ( $n === false ) { break; }
	if ( $n === 0 ) { continue; }
	foreach ( $read as $r ) {
		if ( $r === $server ) {
			$tls = @stream_socket_accept( $server, 5 );
			if ( $tls ) {
				stream_set_blocking( $tls, false );
				$tcp = @stream_socket_client( 'tcp://127.0.0.1:' . $httpPort, $errno, $errstr, 5 );
				if ( ! $tcp ) { fclose( $tls ); continue; }
				stream_set_blocking( $tcp, false );
				$clients[ (int) $tls ] = [ 'tls' => $tls, 'tcp' => $tcp, 'reqBuf' => '', 'reqDone' => false, 'resBuf' => '', 'resDone' => false ];
			}
			continue;
		}
		foreach ( $clients as $id => &$c ) {
			$isReq = ( $r === $c['tls'] );
			$isRes = ( $r === $c['tcp'] );
			if ( ! $isReq && ! $isRes ) { continue; }
			$data = @fread( $r, 16384 );
			if ( $data === false || ( $data === '' && feof( $r ) ) ) {
				@fclose( $c['tls'] );
				if ( $c['tcp'] ) { @fclose( $c['tcp'] ); }
				unset( $clients[ $id ] );
				break;
			}
			if ( $data === '' ) { break; }
			if ( $isReq && ! $c['reqDone'] ) {
				$c['reqBuf'] .= $data;
				$pos = strpos( $c['reqBuf'], "\r\n\r\n" );
				if ( $pos === false ) { break; }
				$head = substr( $c['reqBuf'], 0, $pos );
				$rest = substr( $c['reqBuf'], $pos + 4 );
				// Bharose ke liye mojooda spoofed headers hatao, phir apne joro
				$head = preg_replace( '/\r\nX-Forwarded-Proto:[^\r]*/i', '', $head );
				$head = preg_replace( '/\r\nX-WDH-HTTPS-Port:[^\r]*/i', '', $head );
				$head .= "\r\nX-Forwarded-Proto: https\r\nX-WDH-HTTPS-Port: " . $httpsPort;
				@fwrite( $c['tcp'], $head . "\r\n\r\n" . $rest );
				$c['reqDone'] = true;
				$c['reqBuf'] = '';
			} elseif ( $isRes && ! $c['resDone'] ) {
				$c['resBuf'] .= $data;
				$pos = strpos( $c['resBuf'], "\r\n\r\n" );
				if ( $pos === false ) { break; }
				$head = substr( $c['resBuf'], 0, $pos );
				$rest = substr( $c['resBuf'], $pos + 4 );
				$head = preg_replace_callback(
					'/\r\nLocation:\s*' . preg_quote( $httpOrigin, '/' ) . '/i',
					fn() => "\r\nLocation: " . $httpsOrigin,
					$head
				);
				@fwrite( $c['tls'], $head . "\r\n\r\n" . $rest );
				$c['resDone'] = true;
				$c['resBuf'] = '';
			} else {
				@fwrite( $isReq ? $c['tcp'] : $c['tls'], $data );
			}
			break;
		}
		unset( $c );
	}
}
