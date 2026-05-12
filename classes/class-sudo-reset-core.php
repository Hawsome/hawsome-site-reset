<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class hawsome_reset_Core {

	public function __construct() {
		add_action( 'admin_post_hawsome_reset_verify', array( $this, 'process_verification_step' ) );
		add_action( 'admin_post_hawsome_reset_execute', array( $this, 'process_execution_step' ) );
		add_action( 'wp_ajax_hawsome_reset_analyze', array( $this, 'ajax_analyze' ) );
		add_action( 'wp_ajax_hawsome_reset_execute_ajax', array( $this, 'ajax_execute' ) );
	}

	public function ajax_analyze() {
		check_ajax_referer( 'hawsome_reset_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) wp_send_json_error( 'Unauthorized.' );

		$step = isset( $_POST['scan_step'] ) ? sanitize_text_field( wp_unslash( $_POST['scan_step'] ) ) : 'init';
		$user_id = get_current_user_id();

		if ( 'init' === $step ) {
			global $wpdb;
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_counts = $wpdb->get_results( "SELECT post_type, COUNT(*) as count FROM {$wpdb->prefix}posts GROUP BY post_type", ARRAY_A );
			$post_breakdown = array();
			$total_posts = 0;
			if ( $post_counts ) {
				foreach ( $post_counts as $row ) {
					$post_breakdown[] = $row['count'] . ' ' . ucfirst( $row['post_type'] ) . '(s)';
					$total_posts += (int) $row['count'];
				}
			}

			$core_tables = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'options', 'users', 'usermeta', 'links' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$all_tables = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
			
			$custom_tables = array();
			foreach ( $all_tables as $table ) {
				$base = str_replace( $wpdb->prefix, '', $table );
				if ( ! in_array( $base, $core_tables, true ) ) $custom_tables[] = $table;
			}

			if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
			
			$plugin_names = array();
			foreach ( get_plugins() as $file => $p ) {
				if ( strpos( $file, 'hawsome-site-reset' ) === false ) $plugin_names[] = $p['Name'];
			}
			
			$theme_names = array();
			$active_theme = get_stylesheet();
			foreach ( wp_get_themes() as $slug => $t ) {
				if ( $slug !== $active_theme ) $theme_names[] = $t->get('Name');
			}

			$upload_dir = wp_upload_dir();
			set_transient( 'hawsome_reset_queue_' . $user_id, array( $upload_dir['basedir'] ), HOUR_IN_SECONDS );
			set_transient( 'hawsome_reset_size_' . $user_id, 0, HOUR_IN_SECONDS );
			set_transient( 'hawsome_reset_files_' . $user_id, 0, HOUR_IN_SECONDS );

			wp_send_json_success( array( 
				'status'         => 'processing', 
				'total_posts'    => $total_posts,
				'post_breakdown' => $post_breakdown,
				'table_list'     => $custom_tables,
				'plugin_list'    => $plugin_names,
				'theme_list'     => $theme_names
			) );
		}

		if ( 'scanning_files' === $step ) {
			$start_time = microtime( true );
			$queue = get_transient( 'hawsome_reset_queue_' . $user_id );
			$size  = get_transient( 'hawsome_reset_size_' . $user_id );
			$files = get_transient( 'hawsome_reset_files_' . $user_id );

			if ( ! is_array( $queue ) ) $queue = array();

			while ( ! empty( $queue ) && ( microtime( true ) - $start_time ) < 2.0 ) {
				$current_dir = array_shift( $queue );
				if ( ! is_dir( $current_dir ) || ! is_readable( $current_dir ) ) continue;
				$items = scandir( $current_dir );
				if ( ! $items ) continue;

				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) continue;
					$path = $current_dir . DIRECTORY_SEPARATOR . $item;
					if ( is_dir( $path ) ) array_push( $queue, $path );
					else { $size += filesize( $path ); $files++; }
				}
			}

			if ( empty( $queue ) ) {
				$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
				$formatted_size = $size;
				$unit_index = 0;
				while ( $formatted_size >= 1024 && $unit_index < count( $units ) - 1 ) { $formatted_size /= 1024; $unit_index++; }
				$formatted_size = round( $formatted_size, 2 ) . ' ' . $units[ $unit_index ];

				delete_transient( 'hawsome_reset_queue_' . $user_id );
				delete_transient( 'hawsome_reset_size_' . $user_id );
				delete_transient( 'hawsome_reset_files_' . $user_id );

				wp_send_json_success( array( 'status' => 'complete', 'total_size' => $formatted_size, 'total_files' => $files ) );
			} else {
				set_transient( 'hawsome_reset_queue_' . $user_id, $queue, HOUR_IN_SECONDS );
				set_transient( 'hawsome_reset_size_' . $user_id, $size, HOUR_IN_SECONDS );
				set_transient( 'hawsome_reset_files_' . $user_id, $files, HOUR_IN_SECONDS );
				wp_send_json_success( array( 'status' => 'processing', 'current_files' => $files ) );
			}
		}
	}

	public function process_verification_step() {
		$this->run_preflight_checks();

		$nonce = isset( $_POST['hawsome_reset_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hawsome_reset_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hawsome_reset_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page.', 'hawsome-site-reset' ) );
		}

		$expected_string = 'DELETE ' . wp_parse_url( home_url(), PHP_URL_HOST );
		$confirm_text = isset( $_POST['hawsome_reset_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['hawsome_reset_confirm'] ) ) : '';
		if ( $confirm_text !== $expected_string ) {
			wp_die( esc_html__( 'Action aborted: Confirmation string incorrect.', 'hawsome-site-reset' ) );
		}

		$sudo_token = wp_generate_password( 32, false );
		set_transient( 'hawsome_reset_sudo_' . get_current_user_id(), $sudo_token, 5 * MINUTE_IN_SECONDS );

		$form  = '<h2>' . esc_html__( 'Final Verification Required', 'hawsome-site-reset' ) . '</h2>';
		$form .= '<p>' . esc_html__( 'Please re-enter your administrator password to authorize the complete destruction of this site.', 'hawsome-site-reset' ) . '</p>';
		$form .= '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="POST">';
		$form .= '<input type="hidden" name="action" value="hawsome_reset_execute">';
		$form .= '<input type="hidden" name="sudo_token" value="' . esc_attr( $sudo_token ) . '">';
		$form .= wp_nonce_field( 'hawsome_reset_execute', 'hawsome_reset_execute_nonce', true, false );
		$form .= '<div style="margin-bottom: 20px;"><input type="password" name="sudo_password" autocomplete="current-password" required autofocus style="padding: 8px; font-size: 16px; width: 100%; box-sizing: border-box;"></div>';
		$form .= '<div><button type="submit" class="button button-primary" style="background:#d63638; border:none; color:#fff; cursor:pointer;">' . esc_html__( 'Verify & Destroy Site', 'hawsome-site-reset' ) . '</button></div>';
		$form .= '</form>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( $form, esc_html__( 'Sudo Verification', 'hawsome-site-reset' ) );
	}

	public function process_execution_step() {
		$this->run_preflight_checks();
		$current_user_id = get_current_user_id();
		$strikes = get_transient( 'hawsome_reset_strikes_' . $current_user_id );

		$submitted_token = isset( $_POST['sudo_token'] ) ? sanitize_text_field( wp_unslash( $_POST['sudo_token'] ) ) : '';
		$saved_token = get_transient( 'hawsome_reset_sudo_' . $current_user_id );
		$nonce = isset( $_POST['hawsome_reset_execute_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hawsome_reset_execute_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'hawsome_reset_execute' ) || ! $saved_token || $submitted_token !== $saved_token ) {
			wp_die( esc_html__( 'Session expired or invalid. Please start over.', 'hawsome-site-reset' ) );
		}
		delete_transient( 'hawsome_reset_sudo_' . $current_user_id );

		$user = wp_get_current_user();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = isset( $_POST['sudo_password'] ) ? wp_unslash( $_POST['sudo_password'] ) : ''; 
		
		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			$this->add_strike( $current_user_id, $strikes );
			wp_die( esc_html__( 'Authentication failed. A strike has been added to your account.', 'hawsome-site-reset' ) );
		}

		$exec_token = wp_generate_password( 32, false );
		set_transient( 'hawsome_reset_exec_' . $current_user_id, $exec_token, 15 * MINUTE_IN_SECONDS );
		
		// REDIRECT TO TERMINAL PAGE INSTEAD OF WP_DIE TO LOAD JS!
		wp_safe_redirect( admin_url( 'tools.php?page=hawsome-site-reset&action=terminal' ) );
		exit;
	}

	public function ajax_execute() {
		check_ajax_referer( 'hawsome_reset_exec_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) wp_send_json_error( 'Unauthorized.' );

		$user_id = get_current_user_id();
		$submitted_token = isset( $_POST['exec_token'] ) ? sanitize_text_field( wp_unslash( $_POST['exec_token'] ) ) : '';
		$saved_token = get_transient( 'hawsome_reset_exec_' . $user_id );

		if ( ! $saved_token || $submitted_token !== $saved_token ) wp_send_json_error( 'Security Token Invalid.' );

		$step = isset( $_POST['reset_step'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_step'] ) ) : '';

		if ( 'database' === $step ) {
			$db_engine = new hawsome_reset_DB();
			$db_engine->purge_database();
			wp_send_json_success( 'Database Purged.' );
		}

		if ( 'filesystem' === $step ) {
			$fs_engine = new hawsome_reset_Filesystem();
			$result = $fs_engine->chunked_wipe( $user_id );
			if ( 'error' === $result['status'] ) wp_send_json_error( $result['message'] );
			wp_send_json_success( $result );
		}

		if ( 'restore' === $step ) {
			$db_engine = new hawsome_reset_DB();
			$db_engine->restore_system();

			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[Hawsome Reset] EXECUTED: user=%d ip=%s time=%s', $user_id, $ip, current_time( 'mysql' ) ) );
			do_action( 'hawsome_reset_executed', $user_id, $ip );

			delete_transient( 'hawsome_reset_exec_' . $user_id );
			delete_transient( 'hawsome_reset_strikes_' . $user_id );
			wp_send_json_success( 'Restore Complete.' );
		}
	}

	private function run_preflight_checks() {
		if ( defined( 'DISABLE_Hawsome_Reset' ) && DISABLE_Hawsome_Reset ) wp_die( esc_html__( 'Hawsome Reset is disabled.', 'hawsome-site-reset' ) );
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) wp_die( esc_html__( 'Unauthorized.', 'hawsome-site-reset' ) );
		
		$strikes = get_transient( 'hawsome_reset_strikes_' . get_current_user_id() );
		if ( $strikes && $strikes >= 3 ) wp_die( esc_html__( 'Too many failed attempts. Locked for 15 minutes.', 'hawsome-site-reset' ) );
		
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( ! is_object( $wp_filesystem ) ) wp_die( esc_html__( 'CRITICAL: Filesystem access denied by server. Aborting.', 'hawsome-site-reset' ) );
	}

	private function add_strike( $user_id, $current_strikes ) {
		$strikes = $current_strikes ? $current_strikes + 1 : 1;
		set_transient( 'hawsome_reset_strikes_' . $user_id, $strikes, 15 * MINUTE_IN_SECONDS );
	}
}