<?php
/**
 * WebDevHub tool — copy/clone/import ke baad site ki URL aur
 * folder path theek karna (copy engine ka dil).
 *
 * WP bootstrap NAHI karta — seedha SQLite par serialized-aware
 * search-replace chalata hai (purana tareeqa, baghair side-effects
 * ke). guid column jaan boojh kar nahi chherta.
 *
 * Usage: php fix-url.php --path=<naya dir> --old-dir=<purana dir> --new-url=<nayi URL>
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$args = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([^=]+)=(.*)$/', $a, $m ) ) { $args[ $m[1] ] = $m[2]; }
}
$path   = $args['path'] ?? '';
$oldDir = $args['old-dir'] ?? '';
$newUrl = rtrim( $args['new-url'] ?? '', '/' );

if ( ! $path || ! file_exists( $path . '/wp-config.php' ) ) {
	fwrite( STDERR, "Site ka path ghalat hai.\n" );
	exit( 1 );
}
if ( ! $newUrl ) {
	fwrite( STDERR, "--new-url lazmi hai.\n" );
	exit( 1 );
}

// 1) wp-config mein DB_DIR ka purana rasta naye se badlo
if ( $oldDir && realpath( $oldDir ) !== realpath( $path ) ) {
	$cfgFile = $path . '/wp-config.php';
	$cfg = (string) file_get_contents( $cfgFile );
	$oldNorm = str_replace( '\\', '/', rtrim( $oldDir, '/' ) );
	$newNorm = str_replace( '\\', '/', rtrim( $path, '/' ) );
	$cfg = str_replace( $oldNorm, $newNorm, $cfg );
	$cfg = str_replace( str_replace( '/', '\\', $oldNorm ), str_replace( '/', '\\', $newNorm ), $cfg );
	file_put_contents( $cfgFile, $cfg );
}

// 2) Database kholo
$dbFile = $path . '/wp-content/database/.ht.sqlite';
if ( ! file_exists( $dbFile ) ) {
	fwrite( STDERR, "SQLite database nahi mili: $dbFile\n" );
	exit( 1 );
}
$pdo = new PDO( 'sqlite:' . $dbFile );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

// Purani URL options se khud parho
$oldUrl = null;
try {
	$st = $pdo->prepare( "SELECT option_value FROM wp_options WHERE option_name = 'siteurl'" );
	$st->execute();
	$oldUrl = rtrim( (string) $st->fetchColumn(), '/' );
} catch ( Exception $e ) { /* neeche handle hoga */ }
if ( ! $oldUrl ) {
	fwrite( STDERR, "Purani URL (siteurl) database se nahi mili.\n" );
	exit( 1 );
}
if ( $oldUrl === $newUrl ) {
	echo "URL pehle se sahi hai: $newUrl\n";
	exit( 0 );
}

$from = [ $oldUrl, str_replace( '/', '\\/', $oldUrl ) ];
$to   = [ $newUrl, str_replace( '/', '\\/', $newUrl ) ];

function wdh_sr( $data, $from, $to ) {
	if ( is_string( $data ) ) {
		$trim = trim( $data );
		if ( strlen( $trim ) > 1 && ( $trim[0] === 'a' || $trim[0] === 'O' || $trim[0] === 's' )
			&& preg_match( '/^(a|O|s):/', $trim ) ) {
			$un = @unserialize( $data, [ 'allowed_classes' => false ] );
			if ( $un !== false ) {
				return serialize( wdh_sr( $un, $from, $to ) );
			}
		}
		return str_replace( $from, $to, $data );
	}
	if ( is_array( $data ) ) {
		foreach ( $data as $k => $v ) { $data[ $k ] = wdh_sr( $v, $from, $to ); }
		return $data;
	}
	if ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $k => $v ) { $data->$k = wdh_sr( $v, $from, $to ); }
		return $data;
	}
	return $data;
}

$tables = $pdo->query( "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'" )->fetchAll( PDO::FETCH_COLUMN );
$touchedTables = 0;
$touchedRows = 0;
foreach ( $tables as $table ) {
	// Naam sqlite_master se aya hai — mehfooz hai
	$cols = $pdo->query( "PRAGMA table_info($table)" )->fetchAll( PDO::FETCH_ASSOC );
	$pk = null;
	$names = [];
	foreach ( $cols as $c ) {
		$names[] = $c['name'];
		if ( ! empty( $c['pk'] ) && $pk === null ) { $pk = $c['name']; }
	}
	if ( $pk === null ) { continue; }
	$rows = $pdo->query( "SELECT * FROM $table" )->fetchAll( PDO::FETCH_ASSOC );
	$tableTouched = false;
	foreach ( $rows as $row ) {
		$sets = [];
		$params = [];
		foreach ( $names as $col ) {
			if ( $col === 'guid' ) { continue; }
			$val = $row[ $col ];
			if ( ! is_string( $val ) || strpos( $val, $oldUrl ) === false ) { continue; }
			$newVal = wdh_sr( $val, $from, $to );
			if ( $newVal !== $val ) {
				$sets[] = "$col = ?";
				$params[] = $newVal;
			}
		}
		if ( $sets ) {
			$params[] = $row[ $pk ];
			$pdo->prepare( "UPDATE $table SET " . implode( ', ', $sets ) . " WHERE $pk = ?" )->execute( $params );
			$touchedRows++;
			$tableTouched = true;
		}
	}
	if ( $tableTouched ) { $touchedTables++; }
}

// Pakka karo ke siteurl/home naye hain
$pdo->prepare( "UPDATE wp_options SET option_value = ? WHERE option_name IN ('siteurl', 'home')" )->execute( [ $newUrl ] );

echo "URL fix ho gaya: $oldUrl -> $newUrl ($touchedTables tables, $touchedRows rows)\n";
exit( 0 );
