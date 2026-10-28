<?php
/**
 * WebDevHub tool — kisi local site ka plugin activate/deactivate
 * karna (dashboard ke Plugin Manager se chalta hai).
 *
 * Worker (panel/bin/wdh.php, plugintoggle command) isay alag
 * process mein chalata hai. Plugin ki file worker pehle
 * wp-content/plugins ke andar hone ki tasdeeq kar chuka hota hai.
 *
 * NOTE (WP gotcha): variable naam $wdh wale prefix ke sath rakhe
 * hain — wp-load.php ke bootstrap mein $plugin/$path jaisay aam
 * naam WP ke apne globals se takra kar overwrite ho jate hain
 * (pehli koshish mein yahi hua tha).
 *
 * Usage: php plugin-toggle.php --path=<site dir> --plugin=<file> --mode=on|off
 */
if ( php_sapi_name() !== 'cli' ) { exit( "CLI only\n" ); }

$wdhPath = null; $wdhPluginFile = null; $wdhMode = null;
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( strpos( $a, '--path=' ) === 0 ) { $wdhPath = substr( $a, 7 ); }
	if ( strpos( $a, '--plugin=' ) === 0 ) { $wdhPluginFile = substr( $a, 9 ); }
	if ( strpos( $a, '--mode=' ) === 0 ) { $wdhMode = substr( $a, 7 ); }
}

if ( ! $wdhPath || ! file_exists( $wdhPath . '/wp-load.php' ) ) {
	fwrite( STDERR, "Site ka path ghalat hai.\n" );
	exit( 1 );
}
// Path traversal ki rok: plugin file sirf ek naam ya folder/naam ho
if ( ! $wdhPluginFile || strpos( $wdhPluginFile, '..' ) !== false || ! file_exists( $wdhPath . '/wp-content/plugins/' . $wdhPluginFile ) ) {
	fwrite( STDERR, "Plugin nahi mila.\n" );
	exit( 1 );
}
if ( ! in_array( $wdhMode, [ 'on', 'off' ], true ) ) {
	fwrite( STDERR, "Mode on ya off ho.\n" );
	exit( 1 );
}

require_once $wdhPath . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( $wdhMode === 'on' ) {
	if ( is_plugin_active( $wdhPluginFile ) ) { echo "Plugin pehle se chalu hai: $wdhPluginFile\n"; exit( 0 ); }
	$res = activate_plugin( $wdhPluginFile, '', false, true );
	if ( is_wp_error( $res ) ) {
		fwrite( STDERR, 'Activate fail: ' . $res->get_error_message() . "\n" );
		exit( 2 );
	}
	echo "Plugin chalu ho gaya: $wdhPluginFile\n";
} else {
	if ( ! is_plugin_active( $wdhPluginFile ) ) { echo "Plugin pehle se band hai: $wdhPluginFile\n"; exit( 0 ); }
	deactivate_plugins( [ $wdhPluginFile ], true );
	echo "Plugin band ho gaya: $wdhPluginFile\n";
}
exit( 0 );
