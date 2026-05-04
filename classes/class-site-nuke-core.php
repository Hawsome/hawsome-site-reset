<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Site_Nuke_Core {

	public function __construct() {
		add_action( 'admin_post_site_nuke_verify', array( $this, 'process_verification_step' ) );
		add_action( 'admin_post_site_nuke_execute', array( $this, 'process_execution_step' ) );
		add_action( 'wp_ajax_site_nuke_analyze', array( $this, 'ajax_analyze' ) );
		add_action( 'wp_ajax_site_nuke_execute_ajax', array( $this, 'ajax_execute' ) );
	}

	public function ajax_analyze() {
		check_ajax_referer( 'site_nuke_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) wp_send_json_error( 'Unauthorized.' );

		$step = isset( $_POST['scan_step'] ) ? sanitize_text_field( wp_unslash( $_POST['scan_step'] ) ) : 'init';
		$user_id = get_current_user_id();

		if ( 'init' === $step ) {
			global $wpdb;
			
			// 1. Get Detailed Post Breakdown
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

			// 2. Get Custom Table Names
			$core_tables = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'options', 'users', 'usermeta', 'links' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$all_tables = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
			
			$custom_tables = array();
			foreach ( $all_tables as $table ) {
				$base = str_replace( $wpdb->prefix, '', $table );
				if ( ! in_array( $base, $core_tables, true ) ) {
					$custom_tables[] = $table;
				}
			}

			// 3. Get Plugin & Theme Names
			if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
			
			$plugin_names = array();
			foreach ( get_plugins() as $file => $p ) {
				if ( strpos( $file, 'site-nuke' ) === false ) $plugin_names[] = $p['Name'];
			}
			
			$theme_names = array();
			$active_theme = get_stylesheet();
			foreach ( wp_get_themes() as $slug => $t ) {
				if ( $slug !== $active_theme ) $theme_names[] = $t->get('Name');
			}

			// Setup FS scanning
			$upload_dir = wp_upload_dir();
			set_transient( 'site_nuke_queue_' . $user_id, array( $upload_dir['basedir'] ), HOUR_IN_SECONDS );
			set_transient( 'site_nuke_size_' . $user_id, 0, HOUR_IN_SECONDS );
			set_transient( 'site_nuke_files_' . $user_id, 0, HOUR_IN_SECONDS );

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
			$queue = get_transient( 'site_nuke_queue_' . $user_id );
			$size  = get_transient( 'site_nuke_size_' . $user_id );
			$files = get_transient( 'site_nuke_files_' . $user_id );

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

				delete_transient( 'site_nuke_queue_' . $user_id );
				delete_transient( 'site_nuke_size_' . $user_id );
				delete_transient( 'site_nuke_files_' . $user_id );

				wp_send_json_success( array( 'status' => 'complete', 'total_size' => $formatted_size, 'total_files' => $files ) );
			} else {
				set_transient( 'site_nuke_queue_' . $user_id, $queue, HOUR_IN_SECONDS );
				set_transient( 'site_nuke_size_' . $user_id, $size, HOUR_IN_SECONDS );
				set_transient( 'site_nuke_files_' . $user_id, $files, HOUR_IN_SECONDS );
				// Pass the LIVE count back to the browser!
				wp_send_json_success( array( 'status' => 'processing', 'current_files' => $files ) );
			}
		}
	}

	public function process_verification_step() {
		$this->run_preflight_checks();

		$nonce = isset( $_POST['site_nuke_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['site_nuke_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'site_nuke_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page.', 'site-nuke' ) );
		}

		$expected_string = 'DELETE ' . wp_parse_url( home_url(), PHP_URL_HOST );
		$confirm_text = isset( $_POST['site_nuke_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['site_nuke_confirm'] ) ) : '';
		if ( $confirm_text !== $expected_string ) {
			wp_die( esc_html__( 'Action aborted: Confirmation string incorrect.', 'site-nuke' ) );
		}

		$sudo_token = wp_generate_password( 32, false );
		set_transient( 'site_nuke_sudo_' . get_current_user_id(), $sudo_token, 5 * MINUTE_IN_SECONDS );

		$form  = '<h2>' . esc_html__( 'Final Verification Required', 'site-nuke' ) . '</h2>';
		$form .= '<p>' . esc_html__( 'Please re-enter your administrator password to authorize the complete destruction of this site.', 'site-nuke' ) . '</p>';
		$form .= '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="POST">';
		$form .= '<input type="hidden" name="action" value="site_nuke_execute">';
		$form .= '<input type="hidden" name="sudo_token" value="' . esc_attr( $sudo_token ) . '">';
		$form .= wp_nonce_field( 'site_nuke_execute', 'site_nuke_execute_nonce', true, false );
		$form .= '<div style="margin-bottom: 20px;"><input type="password" name="sudo_password" autocomplete="current-password" required autofocus style="padding: 8px; font-size: 16px; width: 100%; box-sizing: border-box;"></div>';
		$form .= '<div><button type="submit" class="button button-primary" style="background:#d63638; border:none; color:#fff; cursor:pointer;">' . esc_html__( 'Verify & Destroy Site', 'site-nuke' ) . '</button></div>';
		$form .= '</form>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( $form, esc_html__( 'Sudo Verification', 'site-nuke' ) );
	}

	public function process_execution_step() {
		$this->run_preflight_checks();
		$current_user_id = get_current_user_id();
		$strikes = get_transient( 'site_nuke_strikes_' . $current_user_id );

		$submitted_token = isset( $_POST['sudo_token'] ) ? sanitize_text_field( wp_unslash( $_POST['sudo_token'] ) ) : '';
		$saved_token = get_transient( 'site_nuke_sudo_' . $current_user_id );
		$nonce = isset( $_POST['site_nuke_execute_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['site_nuke_execute_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'site_nuke_execute' ) || ! $saved_token || $submitted_token !== $saved_token ) {
			wp_die( esc_html__( 'Session expired or invalid. Please start over.', 'site-nuke' ) );
		}
		delete_transient( 'site_nuke_sudo_' . $current_user_id );

		$user = wp_get_current_user();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = isset( $_POST['sudo_password'] ) ? wp_unslash( $_POST['sudo_password'] ) : ''; 
		
		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			$this->add_strike( $current_user_id, $strikes );
			wp_die( esc_html__( 'Authentication failed. A strike has been added to your account.', 'site-nuke' ) );
		}

		$exec_token = wp_generate_password( 32, false );
		set_transient( 'site_nuke_exec_' . $current_user_id, $exec_token, 15 * MINUTE_IN_SECONDS );
		$ajax_nonce = wp_create_nonce( 'site_nuke_exec_ajax' );

		$terminal  = '<div style="background:#0a0a0a; color:#00ff00; font-family:monospace; padding:20px; border-radius:5px; height:300px; overflow-y:auto; border: 1px solid #333;" id="nuke-terminal">';
		$terminal .= '<p style="margin:0;">> INITIALIZING SITE NUKE PROTOCOL...</p>';
		$terminal .= '</div>';
		$terminal .= '<div style="background:#ddd; height:15px; margin-top:20px; border-radius:10px; overflow:hidden;">';
		$terminal .= '<div id="nuke-progress" style="background:#d63638; width:0%; height:100%; transition: width 0.3s;"></div>';
		$terminal .= '</div>';
		
		$terminal .= '<script>
			async function runNuke() {
				const term = document.getElementById("nuke-terminal");
				const prog = document.getElementById("nuke-progress");
				function log(msg) { term.innerHTML += "<p style=\"margin:4px 0;\">> " + msg + "</p>"; term.scrollTop = term.scrollHeight; }

				async function step(action_step) {
					let fd = new FormData();
					fd.append("action", "site_nuke_execute_ajax");
					fd.append("nonce", "' . esc_js( $ajax_nonce ) . '");
					fd.append("exec_token", "' . esc_js( $exec_token ) . '");
					fd.append("nuke_step", action_step);

					let res = await fetch("' . esc_url( admin_url( 'admin-ajax.php' ) ) . '", {method:"POST", body:fd});
					return await res.json();
				}

				log("STAGE 1: Purging database (custom tables, content, options)...");
				let dbRes = await step("database");
				if(!dbRes.success) { log("CRITICAL ERROR: " + dbRes.data); return; }
				prog.style.width = "20%";
				log("Database completely purged.");

				log("STAGE 2: Initializing filesystem purge (this may take a few minutes)...");
				let fsStatus = "processing";
				let totalDeleted = 0;
				while(fsStatus === "processing") {
					let fsRes = await step("filesystem");
					if(!fsRes.success) { log("CRITICAL ERROR: " + fsRes.data); return; }
					fsStatus = fsRes.data.status;
					totalDeleted += fsRes.data.deleted;
					log("Chunk cleared. Deleted items in this pass: " + fsRes.data.deleted + " (Total: " + totalDeleted + ")");
					prog.style.width = Math.min(90, 20 + (totalDeleted / 50)) + "%"; 
				}
				prog.style.width = "90%";
				log("Filesystem completely purged.");

				log("STAGE 3: Restoring WordPress core defaults (Theme, Admin, Cache)...");
				let rstRes = await step("restore");
				if(!rstRes.success) { log("CRITICAL ERROR: " + rstRes.data); return; }
				prog.style.width = "100%";
				log("System successfully restored.");

				log("NUKE COMPLETE. Redirecting to dashboard...");
				setTimeout(() => { window.location.href = "' . esc_url_raw( admin_url( 'tools.php?page=site-nuke&nuked=true' ) ) . '"; }, 2000);
			}
			setTimeout(runNuke, 1000);
		</script>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( $terminal, esc_html__( 'Active Nuke Mission Control', 'site-nuke' ) );
	}

	public function ajax_execute() {
		check_ajax_referer( 'site_nuke_exec_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) wp_send_json_error( 'Unauthorized.' );

		$user_id = get_current_user_id();
		$submitted_token = isset( $_POST['exec_token'] ) ? sanitize_text_field( wp_unslash( $_POST['exec_token'] ) ) : '';
		$saved_token = get_transient( 'site_nuke_exec_' . $user_id );

		if ( ! $saved_token || $submitted_token !== $saved_token ) wp_send_json_error( 'Security Token Invalid.' );

		$step = isset( $_POST['nuke_step'] ) ? sanitize_text_field( wp_unslash( $_POST['nuke_step'] ) ) : '';

		if ( 'database' === $step ) {
			$db_engine = new Site_Nuke_DB();
			$db_engine->purge_database();
			wp_send_json_success( 'Database Purged.' );
		}

		if ( 'filesystem' === $step ) {
			$fs_engine = new Site_Nuke_Filesystem();
			$result = $fs_engine->chunked_nuke( $user_id );
			if ( 'error' === $result['status'] ) wp_send_json_error( $result['message'] );
			wp_send_json_success( $result );
		}

		if ( 'restore' === $step ) {
			$db_engine = new Site_Nuke_DB();
			$db_engine->restore_system();

			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[SITE NUKE] EXECUTED: user=%d ip=%s time=%s', $user_id, $ip, current_time( 'mysql' ) ) );
			do_action( 'site_nuke_executed', $user_id, $ip );

			delete_transient( 'site_nuke_exec_' . $user_id );
			delete_transient( 'site_nuke_strikes_' . $user_id );
			wp_send_json_success( 'Restore Complete.' );
		}
	}

	private function run_preflight_checks() {
		if ( defined( 'DISABLE_SITE_NUKE' ) && DISABLE_SITE_NUKE ) wp_die( esc_html__( 'Site Nuke is disabled.', 'site-nuke' ) );
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) wp_die( esc_html__( 'Unauthorized.', 'site-nuke' ) );
		
		$strikes = get_transient( 'site_nuke_strikes_' . get_current_user_id() );
		if ( $strikes && $strikes >= 3 ) wp_die( esc_html__( 'Too many failed attempts. Locked for 15 minutes.', 'site-nuke' ) );
		
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( ! is_object( $wp_filesystem ) ) wp_die( esc_html__( 'CRITICAL: Filesystem access denied by server. Aborting.', 'site-nuke' ) );
	}

	private function add_strike( $user_id, $current_strikes ) {
		$strikes = $current_strikes ? $current_strikes + 1 : 1;
		set_transient( 'site_nuke_strikes_' . $user_id, $strikes, 15 * MINUTE_IN_SECONDS );
	}
}