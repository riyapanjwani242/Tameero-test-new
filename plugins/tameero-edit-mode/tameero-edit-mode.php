<?php
/**
 * Plugin Name:       TAMEERO Edit Mode
 * Plugin URI:        https://tameero.example
 * Description:       Front-end Edit Mode for TAMEERO themes — sections aur design settings site par khade khade badlein. Theme apne editable sections `tameero-edit-config.json` manifest se declare karti hai; values un ke declared storage (theme_mods / options / Global Styles) mein rehti hain.
 * Version:           1.1.0
 * Author:            TAMEERO
 * Text Domain:       tameero-edit-mode
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAMEERO_EDIT_VERSION', '1.1.0' );
define( 'TAMEERO_EDIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAMEERO_EDIT_URL', plugin_dir_url( __FILE__ ) );

require_once TAMEERO_EDIT_DIR . 'includes/class-te-config.php';
require_once TAMEERO_EDIT_DIR . 'includes/class-te-rest.php';
require_once TAMEERO_EDIT_DIR . 'includes/class-te-shell.php';
require_once TAMEERO_EDIT_DIR . 'includes/class-te-categories.php';
require_once TAMEERO_EDIT_DIR . 'includes/class-te-safety.php';

/**
 * Edit Mode sirf tab zinda hota hai jab:
 *  - active theme ke pas edit config ho (JSON manifest ya filter), AUR
 *  - current user ke pas edit_theme_options capability ho.
 * Visitor ke liye plugin ka koi trace nahi (koi asset, koi markup nahi).
 */
function tameero_edit_available() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_theme_options' ) ) {
		return false;
	}
	return null !== TE_Config::load();
}

add_action( 'rest_api_init', [ 'TE_REST', 'register_routes' ] );
add_action( 'admin_bar_menu', [ 'TE_Shell', 'admin_bar_node' ], 90 );
add_action( 'wp_enqueue_scripts', [ 'TE_Shell', 'enqueue' ] );
add_action( 'wp_footer', [ 'TE_Shell', 'shell_markup' ] );
// Category rename ke 301 redirects (sirf 404 + map non-empty par check hota hai).
add_action( 'template_redirect', [ 'TE_Categories', 'maybe_redirect' ] );
