<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Site_Nuke
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_site_nuke_sudo_' ) . '%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_timeout_site_nuke_sudo_' ) . '%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_site_nuke_strikes_' ) . '%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_timeout_site_nuke_strikes_' ) . '%' ) );

wp_cache_flush();