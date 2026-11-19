<?php
// v2.18 gate helper: bundled PHP mein curl + Dom\HTMLDocument
// maujood hain ya nahi — JSON jawab.
echo json_encode( [
	'curl' => extension_loaded( 'curl' ),
	'dom_html' => class_exists( 'Dom\\HTMLDocument' ),
	'curl_version' => function_exists( 'curl_version' ) ? ( curl_version()['version'] ?? '' ) : '',
] ) . "\n";
