<?php
/**
 * WebDevHub wp-config template.
 * Installer is file mein placeholders replace karta hai:
 *   %%SITE_DIR%%  %%DB_NAME%%  %%SITE_URL%%
 * Database: SQLite (sqlite-database-integration plugin + wp-content/db.php drop-in).
 * Salts: installer har site ke liye fresh random salts generate karta hai.
 */
define( 'DB_NAME', '%%DB_NAME%%' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// SQLite integration constants
define( 'DB_DIR', '%%SITE_DIR%%/wp-content/database/' );
define( 'DB_FILE', '.ht.sqlite' );

%%SALTS%%

$table_prefix = 'wp_';

define( 'WP_DEBUG', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'FS_METHOD', 'direct' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
