<?php
/**
 * Admin Notices class
 *
 * @package Copy_Link_To_Heading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CLTH_Admin_Notices class
 */
class CLTH_Admin_Notices {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_review_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_clth_dismiss_review_notice', array( $this, 'dismiss_review_notice' ) );
	}

	/**
	 * Enqueue required scripts and styles for the notice
	 */
	public function enqueue_scripts( $hook ) {
		// Only load our scripts where we actually need them
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_register_script( 'clth-admin-notices', plugin_dir_url( dirname( __FILE__ ) ) . 'js/clth-admin-notices' . $suffix . '.js', array( 'jquery' ), clth_get_plugin_version(), true );
		wp_localize_script(
			'clth-admin-notices',
			'clth_admin_notices_data',
			array(
				'nonce'   => wp_create_nonce( 'clth-dismiss-notice-nonce' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Display the review notice.
	 */
	public function display_review_notice() {
		// Don't show notice to users who can't manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show on our own settings page (to avoid clutter, we have the sidebar box instead)
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'settings_page_clth-settings' ) {
			return;
		}

		$dismissed = get_option( 'clth_review_notice_dismissed', false );
		if ( $dismissed ) {
			return;
		}

		$activation_date = get_option( 'clth_activation_date', false );

		// Only show after 7 days
		if ( false === $activation_date || ( time() - $activation_date ) < ( 7 * DAY_IN_SECONDS ) ) {
			return;
		}

		wp_enqueue_style( 'clth-admin-style', plugin_dir_url( dirname( __FILE__ ) ) . 'css/clth-admin-style.css', array(), clth_get_plugin_version() );
		wp_enqueue_script( 'clth-admin-notices' );
		?>
		<div class="clth-notice clth-site-wide-notice" id="clth-review-notice">
			<div class="clth-notice-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
			</div>
			<div class="clth-notice-content">
				<div class="clth-state-initial">
					<h3><?php esc_html_e( 'Are you enjoying Copy Link to Heading?', 'copy-link-to-heading' ); ?></h3>
					<p><?php esc_html_e( 'You\'ve been using our plugin for a few days now. We\'d love to know if you\'re happy with it!', 'copy-link-to-heading' ); ?></p>
					<div class="clth-notice-actions">
						<button class="clth-btn clth-btn-green clth-btn-happy">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg> <?php esc_html_e( 'Yes, I am happy', 'copy-link-to-heading' ); ?>
						</button>
						<button class="clth-btn clth-btn-secondary clth-btn-unhappy">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <?php esc_html_e( 'No, I need help', 'copy-link-to-heading' ); ?>
						</button>
					</div>
				</div>
				
				<div class="clth-state-review" style="display:none;">
					<h3><?php esc_html_e( 'Thanks for your feedback!', 'copy-link-to-heading' ); ?></h3>
					<p><?php esc_html_e( 'That\'s awesome! Could you take a minute to leave a 5-star review on WordPress.org? It helps us a lot to grow and encourages us to add more features in the future.', 'copy-link-to-heading' ); ?></p>
					<div class="clth-notice-actions">
						<a href="https://wordpress.org/support/plugin/copy-link-to-heading/reviews/#new-post" target="_blank" class="clth-btn clth-btn-green clth-external-link">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> <?php esc_html_e( 'Leave a Review', 'copy-link-to-heading' ); ?>
						</a>
						<button class="clth-btn clth-btn-secondary clth-btn-dismiss">
                            <?php esc_html_e( 'I already did', 'copy-link-to-heading' ); ?>
                        </button>
					</div>
				</div>

				<div class="clth-state-support" style="display:none;">
					<h3><?php esc_html_e( 'We\'re here to help you!', 'copy-link-to-heading' ); ?></h3>
					<p><?php esc_html_e( 'We\'re sorry to hear that. Please create a support ticket so we can help you fix any issues you\'re facing. The issues reported by you help us improve the plugin and create a better experience for everyone.', 'copy-link-to-heading' ); ?></p>
					<div class="clth-notice-actions">
						<a href="https://wordpress.org/support/plugin/copy-link-to-heading/#new-topic-0" target="_blank" class="clth-btn clth-btn-primary clth-external-link">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg> <?php esc_html_e( 'Create a Support Ticket', 'copy-link-to-heading' ); ?>
						</a>
						<button class="clth-btn clth-btn-secondary clth-btn-dismiss">
                            <?php esc_html_e( 'No, thanks', 'copy-link-to-heading' ); ?>
                        </button>
					</div>
				</div>
			</div>
			<button type="button" class="clth-notice-dismiss clth-btn-dismiss-icon">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'copy-link-to-heading' ); ?></span>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
		</div>
		<?php
	}

	/**
	 * AJAX handler for dismissing the notice
	 */
	public function dismiss_review_notice() {
		check_ajax_referer( 'clth-dismiss-notice-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$dismiss_count = (int) get_option( 'clth_notice_dismiss_count', 0 );
		update_option( 'clth_notice_dismiss_count', $dismiss_count + 1 );
		
		update_option( 'clth_review_notice_dismissed', true );

		if ( isset( $_POST['dismiss_reason'] ) ) {
			$dismiss_reason = sanitize_text_field( wp_unslash( $_POST['dismiss_reason'] ) );
			
			if ( in_array( $dismiss_reason, array( 'already_reviewed', 'leave_review_clicked' ) ) ) {
				update_option( 'clth_user_already_reviewed', true );
			} elseif ( in_array( $dismiss_reason, array( 'needs_help_dismissed', 'needs_help_clicked' ) ) ) {
				update_option( 'clth_user_needs_help', true );
			}
		}

		wp_send_json_success();
	}
}
