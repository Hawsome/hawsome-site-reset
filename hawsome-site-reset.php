<?php
/**
 * Plugin Name:       Hawsome Site Reset
 * Plugin URI:        https://hawsome.github.io/
 * Description:       Permanently wipes your database and deletes all media, plugins, and inactive themes to restore a clean factory state.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            Awesome Akinfenwa
 * Author URI:        https://github.com/Hawsome
 * Text Domain:       hawsome-site-reset
 * License:           GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'hawsome_reset_VERSION', '1.0.0' );
define( 'hawsome_reset_DIR', plugin_dir_path( __FILE__ ) );
define( 'hawsome_reset_URL', plugin_dir_url( __FILE__ ) );
define( 'hawsome_reset_BASENAME', plugin_basename( __FILE__ ) );

function hawsome_reset_init() {
	require_once hawsome_reset_DIR . 'classes/class-sudo-reset-admin.php';
	require_once hawsome_reset_DIR . 'classes/class-sudo-reset-core.php';
	require_once hawsome_reset_DIR . 'classes/class-sudo-reset-db.php';
	require_once hawsome_reset_DIR . 'classes/class-sudo-reset-filesystem.php';

	new hawsome_reset_Admin();
	new hawsome_reset_Core();
}
add_action( 'plugins_loaded', 'hawsome_reset_init' );