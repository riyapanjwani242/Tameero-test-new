<?php
/**
 * WebDevHub tool — kisi local site ke pehle administrator ka
 * password reset karna (dashboard ke "Password Reset" se chalta hai).
 *
 * Worker (panel/bin/wdh.php, resetpass command) isay alag process
 * mein chalata hai. Password argv mein KABHI nahi jata — env
 * WDH_NEW_PASS se aata hai.
 *
 * Usage: php reset-admin.php --path=<site dir>
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$path = null;
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--path=' ) === 0 ) { $path = substr( $a, 7 ); }
}
$pass = getenv( 'WDH_NEW_PASS' );

if ( ! $path || ! file_exists( $path . '/wp-load.php' ) ) {
	fwrite( STDERR, "Site ka path ghalat hai.\n" );
	exit( 1 );
}
if ( ! $pass || strlen( $pass ) < 6 ) {
	fwrite( STDERR, "Naya password (WDH_NEW_PASS) nahi mila ya 6 characters se chhota hai.\n" );
	exit( 1 );
}

require_once $path . '/wp-load.php';

// Pehla administrator — wahi jo site bante waqt bana tha.
$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC' ] );
if ( ! $admins ) {
	fwrite( STDERR, "Is site mein koi administrator nahi mila.\n" );
	exit( 1 );
}
$user = $admins[0];
wp_set_password( $pass, $user->ID );

echo 'Password badal gaya — admin user: ' . $user->user_login . "\n";
exit( 0 );
