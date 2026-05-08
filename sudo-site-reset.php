<?php
/**
 * Plugin Name:       Sudo Site Reset
 * Plugin URI:        https://hawsome.github.io/
 * Description:       Permanently wipes your database and deletes all media, plugins, and inactive themes to restore a clean factory state.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            Awesome Akinfenwa
 * Author URI:        https://github.com/Hawsome
 * Text Domain:       sudo-site-reset
 * License:           GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SUDO_RESET_VERSION', '1.0.0' );
define( 'SUDO_RESET_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUDO_RESET_URL', plugin_dir_url( __FILE__ ) );
define( 'SUDO_RESET_BASENAME', plugin_basename( __FILE__ ) );

function sudo_reset_init() {
	require_once SUDO_RESET_DIR . 'classes/class-sudo-reset-admin.php';
	require_once SUDO_RESET_DIR . 'classes/class-sudo-reset-core.php';
	require_once SUDO_RESET_DIR . 'classes/class-sudo-reset-db.php';
	require_once SUDO_RESET_DIR . 'classes/class-sudo-reset-filesystem.php';

	new Sudo_Reset_Admin();
	new Sudo_Reset_Core();
}
add_action( 'plugins_loaded', 'sudo_reset_init' );