<?php
/**
 * Deactivation Feedback Handler
 * Copy Link to Heading
 *
 * Handles the deactivation feedback modal and email functionality
 *
 * @package Copy_Link_To_Heading
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLTH_Deactivation_Feedback
 */
class CLTH_Deactivation_Feedback {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin URL
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Constructor
	 *
	 * @param string $plugin_url  Plugin URL.
	 * @param string $version     Plugin version.
	 * @param string $plugin_slug Plugin slug.
	 */
	public function __construct( $plugin_url, $version, $plugin_slug = 'copy-link-to-heading' ) {
		$this->plugin_url  = $plugin_url;
		$this->version     = $version;
		$this->plugin_slug = $plugin_slug;

		$this->init();
	}

	/**
	 * Initialize the deactivation feedback
	 */
	public function init() {
		// Only run in admin.
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'current_screen',
			function () {
				if ( ! $this->is_plugins_screen() ) {
					return;
				}

				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_feedback_dialog_scripts' ) );
			}
		);

		// Register AJAX handlers.
		add_action( 'wp_ajax_clth_deactivate_feedback', array( $this, 'ajax_clth_deactivate_feedback' ) );
	}

	/**
	 * Check if we're on plugins screen
	 */
	private function is_plugins_screen() {
		return in_array( get_current_screen()->id, array( 'plugins', 'plugins-network' ), true );
	}

	/**
	 * Enqueue feedback dialog scripts
	 */
	public function enqueue_feedback_dialog_scripts() {
		add_action( 'admin_footer', array( $this, 'print_deactivate_feedback_dialog' ) );

		wp_register_script(
			'clth-deactivation-feedback',
			$this->plugin_url . 'admin/js/clth-deactivation-feedback.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'clth-deactivation-feedback',
			'clth_deactivation_data',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'clth_deactivate_feedback_nonce' ),
				'i18n'    => array(
					'select_reason' => esc_html__( 'Please select a reason', 'copy-link-to-heading' ),
					'provide_email' => esc_html__( 'Please provide an email address so we can contact you', 'copy-link-to-heading' ),
					'submitting'    => esc_html__( 'Submitting...', 'copy-link-to-heading' ),
					'submit_btn'    => esc_html__( 'Submit & Deactivate', 'copy-link-to-heading' ),
					'thank_you'     => esc_html__( 'Thank you!', 'copy-link-to-heading' ),
					'error'         => esc_html__( 'An error occurred', 'copy-link-to-heading' ),
					'network_error' => esc_html__( 'Network error occurred', 'copy-link-to-heading' ),
				),
			)
		);

		wp_enqueue_script( 'clth-deactivation-feedback' );

		wp_register_style(
			'clth-deactivation-feedback',
			$this->plugin_url . 'admin/css/clth-deactivation-feedback.css',
			array(),
			$this->version
		);

		wp_enqueue_style( 'clth-deactivation-feedback' );
	}

	/**
	 * Print deactivate feedback dialog
	 */
	public function print_deactivate_feedback_dialog() {
		$deactivate_reasons = array(
			'no_longer_needed'       => array(
				'title'             => esc_html__( 'I no longer need the plugin', 'copy-link-to-heading' ),
				'input_placeholder' => '',
			),
			'found_better_plugin'    => array(
				'title'             => esc_html__( 'I found a better plugin', 'copy-link-to-heading' ),
				'input_placeholder' => esc_attr__( 'Please share which plugin', 'copy-link-to-heading' ),
			),
			'couldnt_get_to_work'    => array(
				'title'             => esc_html__( 'I couldn\'t get the plugin to work', 'copy-link-to-heading' ),
				'input_placeholder' => esc_attr__( 'Please describe the issue you encountered.', 'copy-link-to-heading' ),
			),
			'temporary_deactivation' => array(
				'title'             => esc_html__( 'It\'s a temporary deactivation', 'copy-link-to-heading' ),
				'input_placeholder' => '',
			),
			'other'                  => array(
				'title'             => esc_html__( 'Other', 'copy-link-to-heading' ),
				'input_placeholder' => esc_attr__( 'Please share the reason', 'copy-link-to-heading' ),
			),
		);
		?>
		<div id="clth-deactivate-feedback-dialog-wrapper" class="clth-deactivate-feedback-dialog-wrapper">
			<div class="clth-feedback-modal">
				<button type="button" class="clth-close-btn" aria-label="<?php esc_attr_e( 'Close modal', 'copy-link-to-heading' ); ?>">&times;</button>
				
				<div id="clth-deactivate-feedback-dialog-header">
					<span class="clth-feedback-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 16s-1.5-2-4-2-4 2-4 2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
					</span>
					<span id="clth-deactivate-feedback-dialog-header-title"><?php esc_html_e( 'Quick Feedback', 'copy-link-to-heading' ); ?></span>
				</div>
				
				<form id="clth-deactivate-feedback-dialog-form" method="post">
					
					<div id="clth-deactivate-feedback-dialog-form-caption">
						<p style="margin-top: 0;"><?php esc_html_e( 'If you have a moment, please let us know why you are deactivating Copy Link to Heading:', 'copy-link-to-heading' ); ?></p>
						<div style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 20px; border-radius: 4px; font-size: 14px;">
							<strong><?php esc_html_e( 'Need help?', 'copy-link-to-heading' ); ?></strong> <?php esc_html_e( 'We are here to help you! Email us at', 'copy-link-to-heading' ); ?> <a href="mailto:support@superwebshare.com" style="color: #2271b1; text-decoration: none; font-weight: 600;">support@superwebshare.com</a> <?php esc_html_e( 'so we can help you as fast as possible.', 'copy-link-to-heading' ); ?>
						</div>
					</div>
					
					<div id="clth-deactivate-feedback-dialog-form-body">
						<?php foreach ( $deactivate_reasons as $reason_key => $reason ) : ?>
							<div class="clth-deactivate-feedback-dialog-input-wrapper">
								<label for="clth-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" class="clth-deactivate-feedback-dialog-label">
									<input 
										id="clth-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" 
										class="clth-deactivate-feedback-dialog-input" 
										type="radio" 
										name="reason_key" 
										value="<?php echo esc_attr( $reason_key ); ?>" 
									/>
									<?php echo esc_html( $reason['title'] ); ?>
								</label>
								<?php if ( ! empty( $reason['input_placeholder'] ) ) : ?>
									<textarea 
										class="clth-feedback-text" 
										name="reason_<?php echo esc_attr( $reason_key ); ?>" 
										placeholder="<?php echo esc_attr( $reason['input_placeholder'] ); ?>"
									></textarea>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<div class="clth-contact-wrapper">
							<label class="clth-contact-label" for="clth-contact-consent">
								<input type="checkbox" id="clth-contact-consent" name="contact_consent" value="1" class="clth-contact-checkbox" checked>
								<?php esc_html_e( 'Contact me to help resolve the issues', 'copy-link-to-heading' ); ?>
							</label>
							<div id="clth-email-field-container">
								<input type="email" name="contact_email" class="clth-email-input" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" placeholder="<?php esc_attr_e( 'Your Email Address', 'copy-link-to-heading' ); ?>">
							</div>
						</div>

					</div>
					
					<div class="clth-api-error"></div>
					
					<div class="clth-modal-footer">
						<button class="button button-primary" type="submit"><?php esc_html_e( 'Submit & Deactivate', 'copy-link-to-heading' ); ?></button>
						<a href="#" class="clth_skip_and_deactivate button"><?php esc_html_e( 'Skip & Deactivate', 'copy-link-to-heading' ); ?></a>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Ajax feedback submission handler
	 */
	public function ajax_clth_deactivate_feedback() {
		// Sanitize and verify nonce properly.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'clth_deactivate_feedback_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed', 'copy-link-to-heading' ) );
		}

		// Check if reason_key exists and sanitize it.
		$reason_key = isset( $_POST['reason_key'] ) ? sanitize_text_field( wp_unslash( $_POST['reason_key'] ) ) : '';

		if ( empty( $reason_key ) ) {
			wp_send_json_error( esc_html__( 'Please select a reason', 'copy-link-to-heading' ) );
		}

		// Verify user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		header( 'Content-Type: application/json' );

		// Process form data safely.
		$data               = array();
		$data['reason_key'] = $reason_key;

		// Process reason-specific data.
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, 'reason_' ) === 0 ) {
				$data[ $key ] = sanitize_textarea_field( wp_unslash( $value ) );
			}
		}

		$wants_contact = isset( $_POST['contact_consent'] ) && '1' === $_POST['contact_consent'];
		$contact_email = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';

		if ( $wants_contact && ! is_email( $contact_email ) ) {
			wp_send_json_error( esc_html__( 'Please provide a valid email address.', 'copy-link-to-heading' ) );
		}

		// Email configuration.
		$to        = 'support@superwebshare.com';
		$site_name = get_bloginfo( 'name' );
		$subject   = 'Plugin Deactivate Feedback from ' . $site_name . ' - Copy Link to Heading';

		$reason_text = isset( $data[ 'reason_' . $data['reason_key'] ] ) ? $data[ 'reason_' . $data['reason_key'] ] : '';

		// Build email body.
		$body  = "Site Name: {$site_name}\n";
		$body .= 'Site URL: ' . get_site_url() . "\n";
		$body .= "Plugin: Copy Link to Heading\n";
		$body .= 'WordPress Version: ' . get_bloginfo( 'version' ) . "\n";
		$body .= 'Plugin Version: ' . $this->version . "\n";
		$body .= "Reason Key: {$data['reason_key']}\n";
		$body .= "Reason Details: {$reason_text}\n";

		if ( $wants_contact && $contact_email ) {
			$body .= "Contact Requested: Yes\n";
			$body .= "Contact Email: {$contact_email}\n";
		} else {
			$body .= "Contact Requested: No\n";
		}

		$body .= 'Date: ' . current_time( 'Y-m-d H:i:s' ) . "\n";

		// Send email.
		$mail_sent = wp_mail( $to, $subject, $body );

		// Always return success for better UX.
		wp_send_json_success( esc_html__( 'Thank you for your feedback!', 'copy-link-to-heading' ) );

		wp_die();
	}
}
