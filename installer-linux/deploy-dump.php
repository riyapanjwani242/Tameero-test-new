<?php
/**
 * WebDevHub tool — SQLite database ko MySQL dump (.sql) mein
 * badalna (feature 16, deploy package ka hissa).
 *
 * Site local par SQLite (wp-content/database/.ht.sqlite) par
 * chalti hai; live hosting par MySQL chahiye. Ye tool har table
 * ka CREATE + INSERTs ek .sql file mein likhta hai jo phpMyAdmin
 * ya mysql CLI se import ho sake.
 *
 * Usage: php deploy-dump.php --db=<sqlite path> --out=<sql path>
 */

if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$args = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([^=]+)=(.*)$/', $a, $m ) ) { $args[ $m[1] ] = $m[2]; }
}
$dbPath = $args['db'] ?? '';
$outPath = $args['out'] ?? '';
if ( ! $dbPath || ! file_exists( $dbPath ) || ! $outPath ) {
	fwrite( STDERR, "--db aur --out lazmi hain.\n" );
	exit( 1 );
}

$pdo = new PDO( 'sqlite:' . $dbPath, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );

function dd_mysql_escape( $v ) {
	if ( $v === null ) { return 'NULL'; }
	if ( is_int( $v ) || is_float( $v ) ) { return (string) $v; }
	$v = (string) $v;
	// Binary-safe: non-printable zyada hon to hex literal
	if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v ) ) {
		return "X'" . bin2hex( $v ) . "'";
	}
	return "'" . strtr( $v, [
		"\\" => "\\\\",
		"'" => "\\'",
		'"' => '\\"',
		"\n" => "\\n",
		"\r" => "\\r",
		"\0" => "\\0",
		"\x1A" => "\\Z",
	] ) . "'";
}

function dd_convert_create( $sql ) {
	// SQLite ke CREATE ko MySQL ke qareeb lao (WP tables standard hain)
	$sql = preg_replace( '/\bAUTOINCREMENT\b/i', 'AUTO_INCREMENT', $sql );
	$sql = preg_replace( '/\bINTEGER\b/i', 'bigint', $sql );
	$sql = preg_replace( '/\bINT\b/i', 'bigint', $sql );
	$sql = preg_replace( '/^\s*CREATE TABLE (?:IF NOT EXISTS )?/i', 'CREATE TABLE ', $sql );
	// SQLite-only cheezen hatao
	$sql = preg_replace( '/\bWITHOUT ROWID\b/i', '', $sql );
	$sql = preg_replace( '/\bSTRICT\b/i', '', $sql );
	// Aakhir mein engine/charset joro (band ) ke baad ;)
	$sql = rtrim( $sql, " \t\r\n;" );
	if ( substr( $sql, -1 ) === ')' ) {
		$sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	}
	return $sql . ';';
}

// sqlite_% = SQLite internal; _wp_sqlite_% = SQLite driver ki
// apni emulation tables (MySQL par in ka koi matlab nahi, aur in
// ke CREATE mein SQLite-only syntax hai jo import rok dega).
$tables = $pdo->query( "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '_wp_sqlite_%' ORDER BY name" )->fetchAll( PDO::FETCH_ASSOC );

$out = fopen( $outPath, 'wb' );
fwrite( $out, "-- WebDevHub deploy dump (SQLite -> MySQL)\n" );
fwrite( $out, "-- " . date( 'c' ) . "\n" );
fwrite( $out, "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET foreign_key_checks = 0;\n\n" );

$totalRows = 0;
foreach ( $tables as $t ) {
	$name = $t['name'];
	fwrite( $out, "DROP TABLE IF EXISTS `" . $name . "`;\n" );
	fwrite( $out, dd_convert_create( (string) $t['sql'] ) . "\n\n" );

	$cols = $pdo->query( "PRAGMA table_info(\"" . str_replace( '"', '""', $name ) . "\")" )->fetchAll( PDO::FETCH_ASSOC );
	$colNames = array_map( fn( $c ) => '`' . $c['name'] . '`', $cols );
	$stmt = $pdo->query( 'SELECT * FROM "' . str_replace( '"', '""', $name ) . '"' );
	$batch = [];
	while ( $row = $stmt->fetch( PDO::FETCH_NUM ) ) {
		$vals = array_map( 'dd_mysql_escape', $row );
		$batch[] = '(' . implode( ',', $vals ) . ')';
		$totalRows++;
		if ( count( $batch ) >= 100 ) {
			fwrite( $out, 'INSERT INTO `' . $name . '` (' . implode( ',', $colNames ) . ") VALUES\n" . implode( ",\n", $batch ) . ";\n" );
			$batch = [];
		}
	}
	if ( $batch ) {
		fwrite( $out, 'INSERT INTO `' . $name . '` (' . implode( ',', $colNames ) . ") VALUES\n" . implode( ",\n", $batch ) . ";\n" );
	}
	fwrite( $out, "\n" );
}
fwrite( $out, "SET foreign_key_checks = 1;\n" );
fclose( $out );
echo 'Dump tayyar hai: ' . count( $tables ) . " tables, $totalRows rows\n";
