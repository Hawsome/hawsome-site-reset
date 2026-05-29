<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class hawsome_reset_Admin {

	public function __construct() {
    add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    add_action( 'load-tools_page_hawsome-site-reset', array( $this, 'add_security_headers' ) );
    add_action( 'admin_notices', array( $this, 'maybe_show_review_notice' ) );
    add_action( 'wp_ajax_hawsome_dismiss_review', array( $this, 'dismiss_review_notice' ) );
}

public function maybe_show_review_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $dismissed = get_option( 'hawsome_review_dismissed' );
    if ( $dismissed ) return;
    $activated = get_option( 'hawsome_activated_time' );
    if ( ! $activated ) {
        update_option( 'hawsome_activated_time', time() );
        return;
    }
    if ( ( time() - $activated ) < 7 * DAY_IN_SECONDS ) return;
    ?>
    <div class="notice notice-info is-dismissible" id="hawsome-review-notice">
        <p>
            <?php esc_html_e( 'Enjoying Hawsome Site Reset? An honest review on WordPress.org helps other developers find it.', 'hawsome-site-reset' ); ?>
            <a href="https://wordpress.org/support/plugin/hawsome-site-reset/reviews/#new-post" target="_blank">
                <?php esc_html_e( 'Leave a review →', 'hawsome-site-reset' ); ?>
            </a>
        </p>
    </div>
    <script>
    jQuery(document).on('click', '#hawsome-review-notice .notice-dismiss', function() {
        jQuery.post(ajaxurl, { action: 'hawsome_dismiss_review', nonce: '<?php echo esc_js( wp_create_nonce( "hawsome_dismiss_review" ) ); ?>' });
    });
    </script>
    <?php
}

public function dismiss_review_notice() {
    check_ajax_referer( 'hawsome_dismiss_review', 'nonce' );
    update_option( 'hawsome_review_dismissed', true );
    wp_send_json_success();
}

	public function add_security_headers() {
		send_frame_options_header();
		header( "Content-Security-Policy: frame-ancestors 'none';" );
	}

	public function register_admin_page() {
		add_management_page( __( 'Hawsome Reset', 'hawsome-site-reset' ), __( 'Hawsome Reset', 'hawsome-site-reset' ), 'manage_options', 'hawsome-site-reset', array( $this, 'render_admin_page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'tools_page_hawsome-site-reset' !== $hook ) return;
		wp_enqueue_style( 'sudo-reset-admin-css', hawsome_reset_URL . 'assets/css/admin.css', array(), hawsome_reset_VERSION );
		wp_enqueue_script( 'sudo-reset-admin-js', hawsome_reset_URL . 'assets/js/admin.js', array(), hawsome_reset_VERSION, true );
		
		wp_localize_script( 'sudo-reset-admin-js', 'hawsomeResetData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hawsome_reset_action' )
		) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// ENQUEUE TERMINAL JS ONLY ON TERMINAL PAGE
		if ( 'terminal' === $action ) {
			wp_enqueue_script( 'sudo-reset-terminal-js', hawsome_reset_URL . 'assets/js/terminal.js', array(), hawsome_reset_VERSION, true );
			$exec_token = get_transient( 'hawsome_reset_exec_' . get_current_user_id() );
			wp_localize_script( 'sudo-reset-terminal-js', 'hawsomeResetTerminal', array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'hawsome_reset_exec_ajax' ),
				'exec_token'   => $exec_token ? $exec_token : '',
				'redirect_url' => esc_url_raw( admin_url( 'tools.php?page=hawsome-site-reset&wiped=true' ) )
			) );
		}
	}

	public function render_admin_page() {
		if ( is_multisite() || ( defined( 'DISABLE_Hawsome_Reset' ) && DISABLE_Hawsome_Reset ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Disabled by your system configuration.', 'hawsome-site-reset' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wiped  = isset( $_GET['wiped'] ) ? sanitize_text_field( wp_unslash( $_GET['wiped'] ) ) : '';

		// THE TERMINAL RENDERER
		if ( 'terminal' === $action ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Active Mission Control', 'hawsome-site-reset' ) . '</h1>';
			echo '<div style="background:#0a0a0a; color:#00ff00; font-family:monospace; padding:20px; border-radius:5px; height:300px; overflow-y:auto; border: 1px solid #333;" id="nuke-terminal"><p style="margin:0;">> INITIALIZING Hawsome Reset PROTOCOL...</p></div>';
			echo '<div style="background:#ddd; height:15px; margin-top:20px; border-radius:10px; overflow:hidden;"><div id="nuke-progress" style="background:#d63638; width:0%; height:100%; transition: width 0.3s;"></div></div></div>';
			return;
		}

		if ( 'true' === $wiped ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'WIPE SUCCESSFUL:', 'hawsome-site-reset' ) . '</strong> ' . esc_html__( 'The database and filesystem have been purged.', 'hawsome-site-reset' ) . '</p></div>';
		}

		$dynamic_string = 'DELETE ' . wp_parse_url( home_url(), PHP_URL_HOST );
		?>
		<div class="wrap sudo-reset-premium-wrapper">
			<h1><?php esc_html_e( 'Hawsome Site Reset', 'hawsome-site-reset' ); ?></h1>
			<div class="sudo-reset-card">
				<h2><?php esc_html_e( 'Pre-Reset Analysis', 'hawsome-site-reset' ); ?></h2>
				<p><strong><?php esc_html_e( 'WARNING:', 'hawsome-site-reset' ); ?></strong> <?php esc_html_e( 'This tool will permanently wipe your database, media, and inactive plugins/themes to restore a factory state. Your admin account and active theme will be preserved. Please run an Impact Analysis.', 'hawsome-site-reset' ); ?></p>
				<button id="sudo-reset-run-analysis" class="button button-primary button-hero" style="margin-top: 15px;"><?php esc_html_e( 'Scan Site Data', 'hawsome-site-reset' ); ?></button>
				
				<div id="sudo-reset-analysis-progress" style="display: none;">
					<div class="sudo-reset-progress-container"><div class="sudo-reset-progress-bar"></div></div>
					<p class="sudo-reset-loading-text"><?php esc_html_e( 'Analyzing structures...', 'hawsome-site-reset' ); ?></p>
				</div>

				<div id="sudo-reset-impact-report" style="display: none;">
					<hr style="margin-top: 30px;">
					<h3><?php esc_html_e( 'Impact Report: Data to be Destroyed', 'hawsome-site-reset' ); ?></h3>
					<div class="sudo-reset-grid">
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Database Content', 'hawsome-site-reset' ); ?></h4>
							<p id="stat-db-posts">...</p>
							<div id="stat-db-posts-details"></div>
						</div>
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Custom Tables', 'hawsome-site-reset' ); ?></h4>
							<p id="stat-db-tables">...</p>
							<div id="stat-db-tables-details"></div>
						</div>
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Extensions', 'hawsome-site-reset' ); ?></h4>
							<p id="stat-ext-plugins">...</p>
							<div id="stat-ext-details"></div>
						</div>
						<div class="sudo-reset-stat-box">
							<h4><?php esc_html_e( 'Media Uploads Size', 'hawsome-site-reset' ); ?></h4>
							<p id="stat-file-size">...</p>
						</div>
					</div>
				</div>

				<div id="sudo-reset-confirmation" style="display: none;">
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" style="background:#fff3f3; padding:20px; border-left:4px solid #d63638; margin-top:20px;">
						<input type="hidden" name="action" value="hawsome_reset_verify">
						<?php wp_nonce_field( 'hawsome_reset_action', 'hawsome_reset_nonce' ); ?>
						<p style="margin-top:0;"><label for="sudo-reset-confirm"><strong>
							<?php 
							/* translators: %s: The dynamic confirmation string */
							printf( esc_html__( 'To proceed to Final Verification, type "%s" exactly:', 'hawsome-site-reset' ), esc_html( $dynamic_string ) ); 
							?>
						</strong></label></p>
						<input type="text" id="sudo-reset-confirm" name="hawsome_reset_confirm" autocomplete="off" required style="border: 2px solid #d63638; font-size: 16px; padding: 5px; width: 300px;" data-expected="<?php echo esc_attr( $dynamic_string ); ?>">
						<p><button type="submit" id="sudo-reset-submit" class="button button-primary" style="background: #d63638; border-color: #d63638;" disabled><?php esc_attr_e( 'Acknowledge & Proceed', 'hawsome-site-reset' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}