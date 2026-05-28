<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class hawsome_reset_DB {

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
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->query( "TRUNCATE TABLE {$safe_table}" );
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "DROP TABLE IF EXISTS {$safe_table}" );
			}
		}

		$current_user_id = get_current_user_id();
		
		// 1. Delete all other users
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->prefix}users` WHERE ID != %d", $current_user_id ) );
		
		// 2. Delete all usermeta for OTHER users
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->prefix}usermeta` WHERE user_id != %d", $current_user_id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}users` AUTO_INCREMENT = 1" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}usermeta` AUTO_INCREMENT = 1" );

		// 3. FIRST SCRUB
		$this->double_tap_scrub( $current_user_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "SET FOREIGN_KEY_CHECKS = 1" );

		wp_cache_flush();

		if ( defined( 'hawsome_reset_BASENAME' ) ) {
			update_option( 'active_plugins', array( hawsome_reset_BASENAME ) );
		}
	}

	public function restore_system() {
		$current_user_id = get_current_user_id();
		global $wpdb;

		// 4. SECOND SCRUB
		$this->double_tap_scrub( $current_user_id );

		if ( ! function_exists( 'wp_install_defaults' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		
		populate_options();
		// populate_roles() is handled flawlessly inside double_tap_scrub()
		wp_install_defaults( $current_user_id ); 

		$default_theme = WP_Theme::get_core_default_theme();
		if ( $default_theme ) {
			switch_theme( $default_theme->get_stylesheet() );
		}

		wp_cache_flush();
		remove_all_actions( 'shutdown' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->prefix}options` WHERE option_name LIKE %s", '%hawsome_reset_exec_%' ) );
	}

	private function double_tap_scrub( $current_user_id ) {
		global $wpdb;

		$core_user_meta = array(
			'nickname', 'first_name', 'last_name', 'description', 
			'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 
			'admin_color', 'use_ssl', 'show_admin_bar_front', 'locale', 
			'dismissed_wp_pointers', 'show_welcome_panel', 'session_tokens', 
			$wpdb->prefix . 'capabilities', 
			$wpdb->prefix . 'user_level',
			$wpdb->prefix . 'dashboard_quick_press_last_post_id'
		);

		$umeta_placeholders = implode( ',', array_fill( 0, count( $core_user_meta ), '%s' ) );
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM `{$wpdb->prefix}usermeta` 
				WHERE user_id = %d 
				AND meta_key NOT IN ($umeta_placeholders)", 
				array_merge( array( $current_user_id ), $core_user_meta )
			) 
		);
		// phpcs:enable

		$clean_caps = serialize( array( 'administrator' => true ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}usermeta` SET meta_value = %s WHERE user_id = %d AND meta_key = %s", $clean_caps, $current_user_id, $wpdb->prefix . 'capabilities' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}usermeta` SET meta_value = %s WHERE user_id = %d AND meta_key = %s", '10', $current_user_id, $wpdb->prefix . 'user_level' ) );

		$exec_transient         = '_transient_hawsome_reset_exec_' . $current_user_id;
		$exec_transient_timeout = '_transient_timeout_hawsome_reset_exec_' . $current_user_id;

		$options_to_keep = array(
			'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email', 
			'WPLANG', 'template', 'stylesheet', 'db_version', 
			'initial_db_version', 'permalink_structure', 
			'default_role', 'users_can_register', 'timezone_string', 'date_format', 'time_format',
			'active_plugins',
			$wpdb->prefix . 'user_roles', 
			$exec_transient,
			$exec_transient_timeout
		);

		$placeholders = implode( ',', array_fill( 0, count( $options_to_keep ), '%s' ) );
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM `{$wpdb->prefix}options` WHERE option_name NOT IN ($placeholders)", 
				$options_to_keep
			) 
		);
		// phpcs:enable

		// Reset Auto-Increment on Options table so IDs flow sequentially again
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}options` AUTO_INCREMENT = 1" );

		// 1. Delete roles using native API to trigger cache flush natively
		delete_option( $wpdb->prefix . 'user_roles' );

		// 2. Kill the global roles array in RAM
		unset( $GLOBALS['wp_roles'] );

		// 3. Natively rebuild the pure 5 roles and 61 capabilities
		if ( ! function_exists( 'populate_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/schema.php';
		}
		populate_roles();

		// 4. Re-initialize the active global variable and refresh the current user
		$GLOBALS['wp_roles'] = new WP_Roles();
		wp_set_current_user( $current_user_id );
	}
}