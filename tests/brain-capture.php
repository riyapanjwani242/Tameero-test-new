<?php
// v2.9.0 test driver: system prompt capture (dashboard suite ke liye).
// WDH_SITES_DIR env se ata hai; WDH_BRAIN_DIR se brain folder override
// karke missing-folder fallback bhi azmaya ja sakta hai.
$repo = dirname( __DIR__ );
require $repo . '/panel/lib/chat.php';
$sys = hub_chat_system_prompt();
$out = $argv[1] ?? '';
if ( $out !== '' ) { file_put_contents( $out, $sys ); }
echo hub_chat_brain_ok() ? "BRAIN-OK\n" : "BRAIN-NO\n";
echo strpos( $sys, 'TAMEERO dashboard ke AI assistant ho' ) !== false ? "FALLBACK-YES\n" : "FALLBACK-NO\n";
echo 'SYS-LEN: ' . strlen( $sys ) . "\n";
