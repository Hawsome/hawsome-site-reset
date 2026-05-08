<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Sudo_Reset_DB {

	public function purge_database() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "SET FOREIGN_KEY_CHECKS = 0" );

		$core_tables = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'options', 'users', 'usermeta', 'links' );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_tables = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->prefix ) . '%' ) );

		foreach ( $all_tables as $table_name ) {
			$base_name  = str_replace( $wpdb->prefix, '', $table_name );
			$safe_table = '`' . esc_sql( $table_name ) . '`'; 
			
			if ( in_array( $base_name, $core_tables, true ) ) {
				if ( ! in_array( $base_name, array( 'options', 'users', 'usermeta' ), true ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->query( "DELETE FROM {$safe_table}" );
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "DROP TABLE IF EXISTS {$safe_table}" );
			}
		}

		$current_user_id = get_current_user_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->prefix}users` WHERE ID != %d", $current_user_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->prefix}usermeta` WHERE user_id != %d", $current_user_id ) );

		$options_to_keep = array(
			'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email', 
			'WPLANG', 'cron', 'template', 'stylesheet', 'db_version', 
			'initial_db_version', 'permalink_structure', 'rewrite_rules',
			'default_role', 'users_can_register', 'timezone_string', 'date_format', 'time_format',
			$wpdb->prefix . 'user_roles',
			'active_plugins'
		);

		$placeholders = implode( ',', array_fill( 0, count( $options_to_keep ), '%s' ) );
		$transient_like      = $wpdb->esc_like( '_transient' ) . '%';
		$site_transient_like = $wpdb->esc_like( '_site_transient' ) . '%';
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM `{$wpdb->prefix}options` 
				WHERE option_name NOT IN ($placeholders) 
				AND option_name NOT LIKE %s 
				AND option_name NOT LIKE %s", 
				array_merge( $options_to_keep, array( $transient_like, $site_transient_like ) )
			) 
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "SET FOREIGN_KEY_CHECKS = 1" );

		wp_cache_flush();

		if ( defined( 'SUDO_RESET_BASENAME' ) ) {
			update_option( 'active_plugins', array( SUDO_RESET_BASENAME ) );
		}
	}

	public function restore_system() {
		$current_user_id = get_current_user_id();

		if ( ! function_exists( 'wp_install_defaults' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		
		populate_options();
		populate_roles();
		wp_install_defaults( $current_user_id ); 

		$default_theme = WP_Theme::get_core_default_theme();
		if ( $default_theme ) {
			switch_theme( $default_theme->get_stylesheet() );
		}

		wp_cache_flush();
	}
}