<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Sudo_Reset_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'load-tools_page_sudo-site-reset', array( $this, 'add_security_headers' ) );
	}

	public function add_security_headers() {
		send_frame_options_header();
		header( "Content-Security-Policy: frame-ancestors 'none';" );
	}

	public function register_admin_page() {
		add_management_page( __( 'Sudo Reset', 'sudo-site-reset' ), __( 'Sudo Reset', 'sudo-site-reset' ), 'manage_options', 'sudo-site-reset', array( $this, 'render_admin_page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'tools_page_sudo-site-reset' !== $hook ) return;
		wp_enqueue_style( 'sudo-reset-admin-css', SUDO_RESET_URL . 'assets/css/admin.css', array(), SUDO_RESET_VERSION );
		wp_enqueue_script( 'sudo-reset-admin-js', SUDO_RESET_URL . 'assets/js/admin.js', array(), SUDO_RESET_VERSION, true );
		
		wp_localize_script( 'sudo-reset-admin-js', 'sudoResetData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sudo_reset_action' )
		) );

		// ENQUEUE TERMINAL JS ONLY ON TERMINAL PAGE
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'terminal' === $_GET['action'] ) {
			wp_enqueue_script( 'sudo-reset-terminal-js', SUDO_RESET_URL . 'assets/js/terminal.js', array(), SUDO_RESET_VERSION, true );
			$exec_token = get_transient( 'sudo_reset_exec_' . get_current_user_id() );
			wp_localize_script( 'sudo-reset-terminal-js', 'sudoResetTerminal', array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'sudo_reset_exec_ajax' ),
				'exec_token'   => $exec_token ? $exec_token : '',
				'redirect_url' => esc_url_raw( admin_url( 'tools.php?page=sudo-site-reset&wiped=true' ) )
			) );
		}
	}

	public function render_admin_page() {
		if ( is_multisite() || ( defined( 'DISABLE_SUDO_RESET' ) && DISABLE_SUDO_RESET ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Disabled by your system configuration.', 'sudo-site-reset' ) . '</p></div>';
			return;
		}

		// THE TERMINAL RENDERER
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'terminal' === $_GET['action'] ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Active Mission Control', 'sudo-site-reset' ) . '</h1>';
			echo '<div style="background:#0a0a0a; color:#00ff00; font-family:monospace; padding:20px; border-radius:5px; height:300px; overflow-y:auto; border: 1px solid #333;" id="nuke-terminal"><p style="margin:0;">> INITIALIZING SUDO RESET PROTOCOL...</p></div>';
			echo '<div style="background:#ddd; height:15px; margin-top:20px; border-radius:10px; overflow:hidden;"><div id="nuke-progress" style="background:#d63638; width:0%; height:100%; transition: width 0.3s;"></div></div></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wiped'] ) && 'true' === $_GET['wiped'] ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'WIPE SUCCESSFUL:', 'sudo-site-reset' ) . '</strong> ' . esc_html__( 'The database and filesystem have been purged.', 'sudo-site-reset' ) . '</p></div>';
		}

		$dynamic_string = 'DELETE ' . wp_parse_url( home_url(), PHP_URL_HOST );
		?>
		<div class="wrap sudo-reset-premium-wrapper">
			<h1><?php esc_html_e( 'Sudo Site Reset', 'sudo-site-reset' ); ?></h1>
			<div class="sudo-reset-card">
				<h2><?php esc_html_e( 'Pre-Flight Initialization', 'sudo-site-reset' ); ?></h2>
				<p><strong><?php esc_html_e( 'WARNING:', 'sudo-site-reset' ); ?></strong> <?php esc_html_e( 'This tool will permanently delete all content. Please run an Impact Analysis.', 'sudo-site-reset' ); ?></p>
				<button id="sudo-reset-run-analysis" class="button button-primary button-hero" style="margin-top: 15px;"><?php esc_html_e( 'Scan Site Data', 'sudo-site-reset' ); ?></button>
				
				<div id="sudo-reset-analysis-progress" style="display: none;">
					<div class="sudo-reset-progress-container"><div class="sudo-reset-progress-bar"></div></div>
					<p class="sudo-reset-loading-text"><?php esc_html_e( 'Analyzing structures...', 'sudo-site-reset' ); ?></p>
				</div>

				<div id="sudo-reset-impact-report" style="display: none;">
					<hr style="margin-top: 30px;">
					<h3><?php esc_html_e( 'Impact Report: Data to be Destroyed', 'sudo-site-reset' ); ?></h3>
					<div class="sudo-reset-grid">
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Database Content', 'sudo-site-reset' ); ?></h4>
							<p id="stat-db-posts">...</p>
							<div id="stat-db-posts-details"></div>
						</div>
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Custom Tables', 'sudo-site-reset' ); ?></h4>
							<p id="stat-db-tables">...</p>
							<div id="stat-db-tables-details"></div>
						</div>
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Extensions', 'sudo-site-reset' ); ?></h4>
							<p id="stat-ext-plugins">...</p>
							<div id="stat-ext-details"></div>
						</div>
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Media Uploads Size', 'sudo-site-reset' ); ?></h4>
							<p id="stat-file-size">...</p>
						</div>
					</div>
				</div>

				<div id="sudo-reset-confirmation" style="display: none;">
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" style="background:#fff3f3; padding:20px; border-left:4px solid #d63638; margin-top:20px;">
						<input type="hidden" name="action" value="sudo_reset_verify">
						<?php wp_nonce_field( 'sudo_reset_action', 'sudo_reset_nonce' ); ?>
						<p style="margin-top:0;"><label for="sudo-reset-confirm"><strong>
							<?php 
							/* translators: %s: The dynamic confirmation string */
							printf( esc_html__( 'To proceed to Sudo Verification, type "%s" exactly:', 'sudo-site-reset' ), esc_html( $dynamic_string ) ); 
							?>
						</strong></label></p>
						<input type="text" id="sudo-reset-confirm" name="sudo_reset_confirm" autocomplete="off" required style="border: 2px solid #d63638; font-size: 16px; padding: 5px; width: 300px;" data-expected="<?php echo esc_attr( $dynamic_string ); ?>">
						<p><button type="submit" id="sudo-reset-submit" class="button button-primary" style="background: #d63638; border-color: #d63638;" disabled><?php esc_attr_e( 'Acknowledge & Proceed', 'sudo-site-reset' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}