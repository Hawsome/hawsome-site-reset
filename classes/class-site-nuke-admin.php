<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Site_Nuke_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'load-tools_page_site-nuke', array( $this, 'add_security_headers' ) );
	}

	public function add_security_headers() {
		send_frame_options_header();
		header( "Content-Security-Policy: frame-ancestors 'none';" );
	}

	public function register_admin_page() {
		add_management_page( __( 'Site Nuke', 'site-nuke' ), __( 'Site Nuke', 'site-nuke' ), 'manage_options', 'site-nuke', array( $this, 'render_admin_page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'tools_page_site-nuke' !== $hook ) return;
		wp_enqueue_style( 'site-nuke-admin-css', SITE_NUKE_URL . 'assets/css/admin.css', array(), SITE_NUKE_VERSION );
		wp_enqueue_script( 'site-nuke-admin-js', SITE_NUKE_URL . 'assets/js/admin.js', array(), SITE_NUKE_VERSION, true );
		
		wp_localize_script( 'site-nuke-admin-js', 'siteNukeData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'site_nuke_action' )
		) );
	}

	public function render_admin_page() {
		if ( is_multisite() || ( defined( 'DISABLE_SITE_NUKE' ) && DISABLE_SITE_NUKE ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Site Nuke is disabled by your system configuration.', 'site-nuke' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['nuked'] ) && 'true' === $_GET['nuked'] ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'TOTAL NUKE SUCCESSFUL:', 'site-nuke' ) . '</strong> ' . esc_html__( 'The database and filesystem have been purged.', 'site-nuke' ) . '</p></div>';
		}

		$dynamic_string = 'DELETE ' . wp_parse_url( home_url(), PHP_URL_HOST );
		?>
		<div class="wrap site-nuke-premium-wrapper">
			<h1><?php esc_html_e( 'Site Nuke', 'site-nuke' ); ?></h1>
			<div class="site-nuke-card">
				<h2><?php esc_html_e( 'Pre-Flight Initialization', 'site-nuke' ); ?></h2>
				<p><strong><?php esc_html_e( 'WARNING:', 'site-nuke' ); ?></strong> <?php esc_html_e( 'This tool will permanently delete all content, media, plugins, and custom tables. Please run an Impact Analysis to review what will be destroyed.', 'site-nuke' ); ?></p>
				<button id="site-nuke-run-analysis" class="button button-primary button-hero" style="margin-top: 15px;"><?php esc_html_e( 'Scan Site Data', 'site-nuke' ); ?></button>
				
				<div id="site-nuke-analysis-progress" style="display: none;">
					<div class="site-nuke-progress-container"><div class="site-nuke-progress-bar"></div></div>
					<p class="site-nuke-loading-text"><?php esc_html_e( 'Analyzing filesystem and database structures...', 'site-nuke' ); ?></p>
				</div>

				<div id="site-nuke-impact-report" style="display: none;">
					<hr style="margin-top: 30px;">
					<h3><?php esc_html_e( 'Impact Report: Data to be Destroyed', 'site-nuke' ); ?></h3>
					<div class="site-nuke-grid">
						<div class="site-nuke-stat-box">
							<h4><?php esc_html_e( 'Database Content', 'site-nuke' ); ?></h4>
							<p id="stat-db-posts">...</p>
							<div id="stat-db-posts-details"></div>
						</div>
						<div class="site-nuke-stat-box">
							<h4><?php esc_html_e( 'Orphaned/Custom Tables', 'site-nuke' ); ?></h4>
							<p id="stat-db-tables">...</p>
							<div id="stat-db-tables-details"></div>
						</div>
						<div class="site-nuke-stat-box">
							<h4><?php esc_html_e( 'Extensions', 'site-nuke' ); ?></h4>
							<p id="stat-ext-plugins">...</p>
							<div id="stat-ext-details"></div>
						</div>
						<div class="site-nuke-stat-box">
							<h4><?php esc_html_e( 'Media Uploads Size', 'site-nuke' ); ?></h4>
							<p id="stat-file-size">...</p>
						</div>
					</div>
				</div>

				<div id="site-nuke-confirmation" style="display: none;">
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" style="background:#fff3f3; padding:20px; border-left:4px solid #d63638; margin-top:20px;">
						<input type="hidden" name="action" value="site_nuke_verify">
						<?php wp_nonce_field( 'site_nuke_action', 'site_nuke_nonce' ); ?>
						<p style="margin-top:0;"><label for="site-nuke-confirm"><strong>
							<?php 
							/* translators: %s: The dynamic confirmation string */
							printf( esc_html__( 'To proceed to Sudo Verification, type "%s" exactly:', 'site-nuke' ), esc_html( $dynamic_string ) ); 
							?>
						</strong></label></p>
						<input type="text" id="site-nuke-confirm" name="site_nuke_confirm" autocomplete="off" required style="border: 2px solid #d63638; font-size: 16px; padding: 5px; width: 300px;" data-expected="<?php echo esc_attr( $dynamic_string ); ?>">
						<p><button type="submit" id="site-nuke-submit" class="button button-primary" style="background: #d63638; border-color: #d63638;" disabled><?php esc_attr_e( 'Acknowledge Impact & Proceed', 'site-nuke' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}