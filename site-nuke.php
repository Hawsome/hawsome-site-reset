<?php
/**
 * Plugin Name:       Site Nuke
 * Plugin URI:        https://awesomeakinfenwa.com/site-nuke
 * Description:       Permanently wipes your database and deletes all media, plugins, and inactive themes to restore a clean factory state.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            Awesome Akinfenwa
 * Author URI:        https://awesomeakinfenwa.com/
 * Text Domain:       site-nuke
 * License:           GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'SITE_NUKE_VERSION', '1.0.0' );
define( 'SITE_NUKE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITE_NUKE_URL', plugin_dir_url( __FILE__ ) );
define( 'SITE_NUKE_BASENAME', plugin_basename( __FILE__ ) );

// Initialize the plugin
function site_nuke_init() {
	// Include classes
	require_once SITE_NUKE_DIR . 'classes/class-site-nuke-admin.php';
	require_once SITE_NUKE_DIR . 'classes/class-site-nuke-core.php';
	require_once SITE_NUKE_DIR . 'classes/class-site-nuke-db.php';
	require_once SITE_NUKE_DIR . 'classes/class-site-nuke-filesystem.php';

	// Instantiate the Classes
	new Site_Nuke_Admin();
	new Site_Nuke_Core();
}
add_action( 'plugins_loaded', 'site_nuke_init' );