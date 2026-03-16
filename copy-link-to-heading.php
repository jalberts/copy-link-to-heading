<?php
/**
 * Plugin Name: Copy Link to Heading
 * Description: Adds a copy link icon to headings for easy copying, bookmarking, sharing, and navigation within the content.
 * Version: 2.0
 * Author: Jose Varghese
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Text Domain: copy-link-to-heading
 * Domain Path: /languages
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Remove admin notices on the plugin settings page
function clth_remove_admin_notices() {
	$screen = get_current_screen();
	if ( $screen && $screen->id === 'settings_page_clth-settings' ) {
		remove_all_actions( 'admin_notices' );
	}
}
add_action( 'admin_head', 'clth_remove_admin_notices' );

// Initialize deactivation feedback and admin notices
function clth_init_admin_components() {
	if ( is_admin() ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-clth-deactivation-feedback.php';
		new CLTH_Deactivation_Feedback( plugin_dir_url( __FILE__ ), clth_get_plugin_version() );
		
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-clth-admin-notices.php';
		new CLTH_Admin_Notices();
	}
}
add_action( 'plugins_loaded', 'clth_init_admin_components' );

// Enqueue CSS and JS
function clth_enqueue_assets() {
	if ( ! clth_should_load() ) {
		return;
	}

	$version    = clth_get_plugin_version();
	$plugin_url = plugin_dir_url( __FILE__ );

	// Determine suffix for minified files
    $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    // Register CSS
    wp_register_style( 'clth-style', $plugin_url . "css/clth-style{$suffix}.css", array(), $version );
    wp_enqueue_style( 'clth-style' );

	// Register JS
    wp_register_script( 'clth-script', $plugin_url . "js/clth-script{$suffix}.js", array(), $version, true );
    wp_enqueue_script( 'clth-script' );

	// Add defer attribute to script
	wp_script_add_data( 'clth-script', 'defer', true );

	// Determine icon URL and SVG content
	$icon_style = get_option( 'clth_icon_style', 'default' );
	$plugin_dir = plugin_dir_path( __FILE__ );
	$icon_url   = $plugin_url . 'images/link-icon.svg'; // Default URL
	$icon_svg   = '';
	$icon_type  = 'stroke'; // Default type

	// Define icons metadata
	$icons_meta = array(
		'default' => array(
			'file' => 'link-icon.svg',
			'type' => 'stroke',
		),
		'style-2' => array(
			'file' => 'link-icon-2.svg',
			'type' => 'fill',
		),
		'style-3' => array(
			'file' => 'link-icon-3.svg',
			'type' => 'stroke',
		),
		'style-4' => array(
			'file' => 'link-icon-4.svg',
			'type' => 'fill',
		),
		'style-5' => array(
			'file' => 'link-icon-5.svg',
			'type' => 'fill',
		),
	);

	if ( 'custom' === $icon_style ) {
		$custom_url = get_option( 'clth_custom_icon_url' );
		if ( ! empty( $custom_url ) ) {
			$icon_url = $custom_url;
		}
	} elseif ( isset( $icons_meta[ $icon_style ] ) ) {
		$file_name = $icons_meta[ $icon_style ]['file'];
		$icon_url  = $plugin_url . 'images/' . $file_name;
		$icon_type = $icons_meta[ $icon_style ]['type'];

		$file_path = $plugin_dir . 'images/' . $file_name;
		if ( file_exists( $file_path ) ) {
			$icon_svg = file_get_contents( $file_path );
		}
	} else {
		// Fallback to default
		$file_path = $plugin_dir . 'images/link-icon.svg';
		if ( file_exists( $file_path ) ) {
			$icon_svg = file_get_contents( $file_path );
		}
	}

	// Pass data to JS
	wp_localize_script(
		'clth-script',
		'clthData',
		array(
			'iconUrl'           => $icon_url,
			'iconSvg'           => $icon_svg, // Inline SVG content
			'iconType'          => $icon_type, // 'stroke' or 'fill'
			'iconSize'          => get_option( 'clth_icon_size', 24 ),
			'iconColor'         => get_option( 'clth_icon_color' ),
			'iconThickness'     => get_option( 'clth_icon_thickness', 'bold' ),
			'headings'          => get_option( 'clth_heading_levels', array( 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
			'showIconOnMobile'  => get_option( 'clth_show_icon_on_mobile', true ),
			'enableTooltip'     => get_option( 'clth_enable_tooltip', true ),
			'copyText'          => get_option( 'clth_copy_text', 'Copy Link to Heading' ),
			'copiedText'        => get_option( 'clth_copied_text', 'Copied' ),
			'iconPosition'      => get_option( 'clth_icon_position', 'after' ),
			'showIconOnDesktop' => get_option( 'clth_show_icon_on_desktop', false ),
			'contentSelector'   => apply_filters( 'clth_content_selectors', '.entry-content, .post-content, .page-content, .dynamic-entry-content' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'clth_enqueue_assets' );

// Check whether to load the plugin on the current page
function clth_should_load() {
	$current_post_type = get_post_type();
	$excluded_ids      = get_option( 'clth_excluded_ids', array() );
	$enabled_cpts      = get_option( 'clth_enable_for_cpt', array() );
	$show_for_posts    = get_option( 'clth_enable_for_posts', true );
	$show_for_pages    = get_option( 'clth_enable_for_pages', false );

	if ( ! is_array( $excluded_ids ) ) {
		$excluded_ids = array();
	}
	if ( ! is_array( $enabled_cpts ) ) {
		$enabled_cpts = array();
	}

	if ( empty( $current_post_type ) ) {
		return false;
	}

	// Check exclusion by ID
	if ( in_array( get_the_ID(), $excluded_ids ) ) {
		return false;
	}

	// Check for specific content type
	if (
		( is_single() && $show_for_posts && $current_post_type === 'post' ) ||
		( is_page() && $show_for_pages && $current_post_type === 'page' ) ||
		( is_singular() && in_array( $current_post_type, $enabled_cpts ) )
	) {
		return true;
	}

	return false;
}

// Add settings page
function clth_add_settings_page() {
	$hook = add_options_page(
		esc_html__( 'Copy Link to Heading Settings', 'copy-link-to-heading' ),
		esc_html__( 'Copy Link to Heading', 'copy-link-to-heading' ),
		'manage_options',
		'clth-settings',
		'clth_render_settings_page'
	);
	add_action(
		'admin_enqueue_scripts',
		function ( $hook_suffix ) use ( $hook ) {
			if ( $hook_suffix === $hook ) {
				wp_enqueue_media();
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_style( 'clth-admin-style', plugin_dir_url( __FILE__ ) . 'css/clth-admin-style.css', array(), clth_get_plugin_version() );
            $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
            wp_enqueue_script( 'clth-admin-script', plugin_dir_url( __FILE__ ) . "js/clth-admin-script{$suffix}.js", array( 'wp-color-picker' ), clth_get_plugin_version(), true );
			}
		}
	);
}
add_action( 'admin_menu', 'clth_add_settings_page' );

// Render settings page
function clth_render_settings_page() {
	// Verify user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'copy-link-to-heading' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Copy Link to Heading Settings', 'copy-link-to-heading' ); ?> <sup><?php echo esc_html( clth_get_plugin_version() ); ?></sup></h1>
		
		<div class="clth-settings-wrapper">
			<div class="clth-settings-main">
				<form method="post" action="options.php">
					<?php
					/**
					 * Nonce verification is automatically handled by the settings_fields() function,
					 * which outputs the necessary nonce fields.
					 */
					settings_fields( 'clth_options_group' );
					do_settings_sections( 'clth-settings' );
					?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Select heading levels to show link icon:', 'copy-link-to-heading' ); ?></th>
							<td>
								<p class="description"><?php esc_html_e( 'By default, no icon is shown for H1 as it is typically used for the title of the page or post.', 'copy-link-to-heading' ); ?></p>
								<?php
								$headings          = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
								$selected_headings = get_option( 'clth_heading_levels', array( 'h2', 'h3', 'h4', 'h5', 'h6' ) );
								if ( ! is_array( $selected_headings ) ) {
									$selected_headings = array();
								}

								foreach ( $headings as $heading ) {
									?>
									<label>
										<input type="checkbox" name="clth_heading_levels[]" value="<?php echo esc_attr( $heading ); ?>" <?php checked( in_array( $heading, $selected_headings ) ); ?> />
										<?php echo esc_html( strtoupper( $heading ) ); ?>
									</label><br>
									<?php
								}
								?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Show link icons on the following content types:', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="checkbox" name="clth_enable_for_posts" value="1" <?php checked( get_option( 'clth_enable_for_posts', true ), 1 ); ?> /> <?php esc_html_e( 'Posts', 'copy-link-to-heading' ); ?><br />
								<input type="checkbox" name="clth_enable_for_pages" value="1" <?php checked( get_option( 'clth_enable_for_pages', false ), 1 ); ?> /> <?php esc_html_e( 'Pages', 'copy-link-to-heading' ); ?><br />
								<?php
								$args          = array(
									'public'   => true,
									'_builtin' => false,
								);
								$post_types    = get_post_types( $args, 'objects' );
								$selected_cpts = get_option( 'clth_enable_for_cpt', array() );
								if ( ! is_array( $selected_cpts ) ) {
									$selected_cpts = array();
								}

								foreach ( $post_types as $post_type ) {
									?>
									<label>
										<input type="checkbox" name="clth_enable_for_cpt[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $selected_cpts ) ); ?> />
										<?php echo esc_html( $post_type->labels->singular_name ); ?>
									</label><br>
									<?php
								}
								?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Exclude Pages (IDs):', 'copy-link-to-heading' ); ?></th>
							<td>
								<textarea name="clth_excluded_ids" rows="3" class="large-text"><?php echo esc_textarea( implode( ',', get_option( 'clth_excluded_ids', array() ) ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Enter the IDs of pages to exclude, separated by commas. Example: 12, 34, 56', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Always Show Icon on Mobile:', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="checkbox" name="clth_show_icon_on_mobile" value="1" <?php checked( get_option( 'clth_show_icon_on_mobile', true ), 1 ); ?> />
								<label for="clth_show_icon_on_mobile"><?php esc_html_e( 'Always show the icon on mobile devices without hovering.', 'copy-link-to-heading' ); ?></label>
								<p class="description"><?php esc_html_e( 'This is recommended for better usability on touchscreens; otherwise, the icon will only appear when the heading is tapped on mobile device.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Enable Tooltip:', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="checkbox" name="clth_enable_tooltip" id="clth_enable_tooltip" value="1" <?php checked( get_option( 'clth_enable_tooltip', true ), 1 ); ?> />
								<label for="clth_enable_tooltip"><?php esc_html_e( 'Enable tooltips when hovering over the copy icon.', 'copy-link-to-heading' ); ?></label>
								<p class="description"><?php esc_html_e( 'If disabled, an alert will show instead of the tooltip.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top" class="tooltip-text-options" style="<?php echo get_option( 'clth_enable_tooltip', true ) ? '' : 'display: none;'; ?>">
							<th scope="row"><?php esc_html_e( 'Text for "Copy Link to Heading":', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="text" name="clth_copy_text" value="<?php echo esc_attr( get_option( 'clth_copy_text', 'Copy Link to Heading' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Text to display when hovering over the icon before the link is copied.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top" class="tooltip-text-options" style="<?php echo get_option( 'clth_enable_tooltip', true ) ? '' : 'display: none;'; ?>">
							<th scope="row"><?php esc_html_e( 'Text for "Copied":', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="text" name="clth_copied_text" value="<?php echo esc_attr( get_option( 'clth_copied_text', 'Copied' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Text to display when the link is copied.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Icon Position:', 'copy-link-to-heading' ); ?></th>
							<td>
								<label>
									<input type="radio" name="clth_icon_position" value="after" <?php checked( get_option( 'clth_icon_position', 'after' ), 'after' ); ?> />
									<?php esc_html_e( 'After Heading', 'copy-link-to-heading' ); ?>
								</label><br>
								<label>
									<input type="radio" name="clth_icon_position" value="before" <?php checked( get_option( 'clth_icon_position', 'after' ), 'before' ); ?> />
									<?php esc_html_e( 'Before Heading', 'copy-link-to-heading' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Select the position of the copy link icon relative to the heading. "After Heading" is the default. Note: If "Before Heading" is selected, the icon will always be visible to prevent layout shifts.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Icon Style:', 'copy-link-to-heading' ); ?></th>
							<td>
								<?php
								$styles        = array(
									'default' => array(
										'label'   => 'Default (Current)',
										'preview' => plugin_dir_url( __FILE__ ) . 'images/link-icon.svg',
									),
									'style-2' => array(
										'label'   => 'Standard Link',
										'preview' => plugin_dir_url( __FILE__ ) . 'images/link-icon-2.svg',
									),
									'style-3' => array(
										'label'   => 'Hash / Anchor',
										'preview' => plugin_dir_url( __FILE__ ) . 'images/link-icon-3.svg',
									),
									'style-4' => array(
										'label'   => 'Paperclip',
										'preview' => plugin_dir_url( __FILE__ ) . 'images/link-icon-4.svg',
									),
									'style-5' => array(
										'label'   => 'Copy / Duplicate',
										'preview' => plugin_dir_url( __FILE__ ) . 'images/link-icon-5.svg',
									),
									'custom'  => array(
										'label'   => 'Custom Image',
										'preview' => '',
									),
								);
								$current_style = get_option( 'clth_icon_style', 'default' );

								foreach ( $styles as $key => $style ) {
									?>
									<label style="display: inline-block; margin-right: 15px; margin-bottom: 15px; vertical-align: top; text-align: center;">
										<input type="radio" name="clth_icon_style" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current_style, $key ); ?> />
										<?php if ( ! empty( $style['preview'] ) ) : ?>
											<br><img src="<?php echo esc_url( $style['preview'] ); ?>" style="width: 24px; height: 24px; display: block; margin: 5px auto;" />
										<?php elseif ( $key === 'custom' ) : ?>
											<br><span class="dashicons dashicons-format-image" style="font-size: 24px; width: 24px; height: 24px; display: block; margin: 5px auto;"></span>
										<?php endif; ?>
										<span style="display: block; margin-top: 5px;"><?php echo esc_html( $style['label'] ); ?></span>
									</label>
									<?php
								}
								?>
							</td>
						</tr>
						<tr valign="top" class="clth-custom-icon-row" style="<?php echo $current_style === 'custom' ? '' : 'display: none;'; ?>">
							<th scope="row"><?php esc_html_e( 'Custom Icon:', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="text" name="clth_custom_icon_url" id="clth_custom_icon_url" value="<?php echo esc_attr( get_option( 'clth_custom_icon_url' ) ); ?>" class="regular-text" />
								<input type="button" id="clth_upload_icon_button" class="button" value="<?php esc_attr_e( 'Upload Image', 'copy-link-to-heading' ); ?>" />
								<p class="description"><?php esc_html_e( 'Upload or select an image from the media library.', 'copy-link-to-heading' ); ?></p>
								<div id="clth_custom_icon_preview" style="margin-top: 10px;">
									<?php if ( get_option( 'clth_custom_icon_url' ) ) : ?>
										<img src="<?php echo esc_url( get_option( 'clth_custom_icon_url' ) ); ?>" style="max-width: 50px; max-height: 50px;" />
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Icon Size (px):', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="number" name="clth_icon_size" value="<?php echo esc_attr( get_option( 'clth_icon_size', 24 ) ); ?>" class="small-text" min="10" max="100" />
								<p class="description"><?php esc_html_e( 'Default is 24px.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top" class="clth-icon-thickness-row" style="<?php echo $current_style === 'custom' ? 'display:none;' : ''; ?>">
							<th scope="row"><?php esc_html_e( 'Icon Thickness', 'copy-link-to-heading' ); ?></th>
							<td>
								<select name="clth_icon_thickness">
									<option value="normal" <?php selected( get_option( 'clth_icon_thickness', 'bold' ), 'normal' ); ?>><?php esc_html_e( 'Normal', 'copy-link-to-heading' ); ?></option>
									<option value="medium" <?php selected( get_option( 'clth_icon_thickness', 'bold' ), 'medium' ); ?>><?php esc_html_e( 'Medium', 'copy-link-to-heading' ); ?></option>
									<option value="bold" <?php selected( get_option( 'clth_icon_thickness', 'bold' ), 'bold' ); ?>><?php esc_html_e( 'Bold', 'copy-link-to-heading' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Adjust the stroke thickness of the icon.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top" class="clth-icon-color-row">
							<th scope="row"><?php esc_html_e( 'Icon Color:', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="text" name="clth_icon_color" value="<?php echo esc_attr( get_option( 'clth_icon_color' ) ); ?>" class="clth-color-field" data-default-color="" />
								<p class="description"><?php esc_html_e( 'Select a color for the icon. Leave empty to use the default color from your theme or CSS.', 'copy-link-to-heading' ); ?></p>
								<p class="description clth-custom-icon-color-warning" style="display: none; color: #856404; background-color: #fff3cd; padding: 5px; border: 1px solid #ffeeba; border-radius: 3px; margin-top: 5px;"><?php esc_html_e( 'Note: The color might not be applied accurately to custom images, as results are not guaranteed and depend on the image format.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Show Icon on Desktop:', 'copy-link-to-heading' ); ?></th>
							<td>
								<input type="checkbox" name="clth_show_icon_on_desktop" value="1" <?php checked( get_option( 'clth_show_icon_on_desktop', false ), 1 ); ?> />
								<label for="clth_show_icon_on_desktop"><?php esc_html_e( 'Always show the copy link icon on desktop without hovering.', 'copy-link-to-heading' ); ?></label>
								<p class="description"><?php esc_html_e( 'If unchecked, the icon will appear on hover as usual. This setting is separate from mobile behavior.', 'copy-link-to-heading' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>

			<div class="clth-settings-sidebar">
				<div class="clth-sidebar-box clth-box-highlight">
					<div class="clth-sidebar-header">
						<span class="dashicons dashicons-heart clth-heart-icon"></span>
						<h3><?php esc_html_e( 'Like this plugin?', 'copy-link-to-heading' ); ?></h3>
					</div>
					<p><?php esc_html_e( 'If you find this plugin helpful and want to support its development, please consider buying me a coffee! Your support helps add more features and keeps the plugin free.', 'copy-link-to-heading' ); ?></p>
					<a href="https://superwebshare.com/donate" target="_blank" class="clth-button-primary">
						<span class="dashicons dashicons-coffee"></span>
						<?php esc_html_e( 'Buy me a Coffee', 'copy-link-to-heading' ); ?>
					</a>
					<p class="clth-sidebar-footer-text"><?php esc_html_e( 'Every contribution directly supports future updates.', 'copy-link-to-heading' ); ?></p>
				</div>
				<div class="clth-sidebar-box">
					<h3><?php esc_html_e( 'Need Help?', 'copy-link-to-heading' ); ?></h3>
					<p><?php esc_html_e( 'Found a bug or have a feature request? Check out the support forum.', 'copy-link-to-heading' ); ?></p>
					<a href="https://wordpress.org/support/plugin/copy-link-to-heading/#new-topic-0" target="_blank" class="clth-button-secondary">
						<?php esc_html_e( 'Get Support', 'copy-link-to-heading' ); ?>
					</a>
				</div>
				
				<?php
				/**
				 * Review Prompt Sidebar Box
				 *
				 * This block displays an interactive review box in the settings sidebar.
				 * Logic:
				 * - It checks if the `clth_activation_date` option exists and if 7 days (7 * DAY_IN_SECONDS) have passed since that date.
				 * - If 7 days have passed, it enqueues the required JavaScript (`clth-admin-notices.js`) snippet.
				 * - The box is generated directly below the "Need Help?" and "Donate" boxes permanently.
				 * - Interacting with the "Yes, I am happy" / "No, I need help" buttons dynamically changes the box text.
				 */
				$activation_date = get_option( 'clth_activation_date', false );
				
				if ( $activation_date && ( time() - $activation_date ) >= ( 7 * DAY_IN_SECONDS ) ) : 
					// Enqueue scripts specifically for this sidebar box
					$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
					wp_enqueue_script( 'clth-admin-notices', plugin_dir_url( __FILE__ ) . 'js/clth-admin-notices' . $suffix . '.js', array( 'jquery' ), clth_get_plugin_version(), true );
					wp_localize_script(
						'clth-admin-notices',
						'clth_admin_notices_data',
						array(
							'nonce'   => wp_create_nonce( 'clth-dismiss-notice-nonce' ),
							'ajaxurl' => admin_url( 'admin-ajax.php' ),
						)
					);
				?>
				<div class="clth-sidebar-box clth-sidebar-box-review">
					<div class="clth-stars">
						<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
						<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
						<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
						<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
						<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
					</div>
					
					<div class="clth-state-initial">
						<h3><?php esc_html_e( 'Enjoying the Plugin?', 'copy-link-to-heading' ); ?></h3>
						<p><?php esc_html_e( 'Are you happy with Copy Link to Heading so far?', 'copy-link-to-heading' ); ?></p>
						<div class="clth-actions">
							<button class="clth-btn clth-btn-green clth-btn-happy"><?php esc_html_e( 'Yes, I am happy', 'copy-link-to-heading' ); ?></button>
							<button class="clth-btn clth-btn-secondary clth-btn-unhappy"><?php esc_html_e( 'No, I need help', 'copy-link-to-heading' ); ?></button>
						</div>
					</div>

					<div class="clth-state-review" style="display:none;">
						<h3><?php esc_html_e( 'Thanks for your feedback!', 'copy-link-to-heading' ); ?></h3>
						<p><?php esc_html_e( 'That\'s awesome! Could you take a minute to leave a 5-star review? It helps us grow and encourages us to add more features.', 'copy-link-to-heading' ); ?></p>
						<div class="clth-actions">
							<a href="https://wordpress.org/support/plugin/copy-link-to-heading/reviews/#new-post" target="_blank" class="clth-btn clth-btn-green clth-external-link"><?php esc_html_e( 'Leave a Review', 'copy-link-to-heading' ); ?></a>
						</div>
					</div>

					<div class="clth-state-support" style="display:none;">
						<h3><?php esc_html_e( 'We\'re here to help!', 'copy-link-to-heading' ); ?></h3>
						<p><?php esc_html_e( 'We\'re sorry to hear that. Please create a support ticket. The issues you report help us improve the plugin for everyone.', 'copy-link-to-heading' ); ?></p>
						<div class="clth-actions">
							<a href="https://wordpress.org/support/plugin/copy-link-to-heading/#new-topic-0" target="_blank" class="clth-btn clth-btn-primary clth-external-link"><?php esc_html_e( 'Create a Ticket', 'copy-link-to-heading' ); ?></a>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

// Add admin notice on activation
function clth_activation_notice() {
	if ( get_transient( 'clth_show_activation_notice' ) ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>' . esc_html__( 'Thank you for installing Copy Link to Heading.', 'copy-link-to-heading' ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=clth-settings' ) ) . '">' . esc_html__( 'Customize your settings here', 'copy-link-to-heading' ) . '</a>.</p>';
		echo '</div>';
		delete_transient( 'clth_show_activation_notice' );
	}
}
add_action( 'admin_notices', 'clth_activation_notice' );

// Set transient on plugin activation and save activation date
function clth_set_activation_notice() {
	set_transient( 'clth_show_activation_notice', true, 30 );
	if ( false === get_option( 'clth_activation_date' ) ) {
		update_option( 'clth_activation_date', time() );
	}
}
register_activation_hook( __FILE__, 'clth_set_activation_notice' );

// Fallback to set the activation date for existing users who updated to the new version
function clth_activation_date_fallback() {
	if ( false === get_option( 'clth_activation_date' ) ) {
		// Set the date to right now to begin the 7-day period for existing users
		update_option( 'clth_activation_date', time() );
	}
}
add_action( 'admin_init', 'clth_activation_date_fallback' );

// Add settings and donate links on plugins page
function clth_add_plugin_action_links( $links ) {
	$settings_link = '<a href="options-general.php?page=clth-settings">' . esc_html__( 'Settings', 'copy-link-to-heading' ) . '</a>';
	$donate_link   = '<a href="https://superwebshare.com/donate" style="color: #00a32a; font-weight: bold;" target="_blank">' . esc_html__( 'Donate', 'copy-link-to-heading' ) . '</a>';
	array_unshift( $links, $settings_link, $donate_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'clth_add_plugin_action_links' );

// Register settings
function clth_register_settings() {
	// Register each setting with a static array for sanitization

	$args_heading_levels = array(
		'sanitize_callback' => 'clth_sanitize_headings',
		'default'           => array( 'h2', 'h3', 'h4', 'h5', 'h6' ),
	);
	register_setting( 'clth_options_group', 'clth_heading_levels', $args_heading_levels );

	$args_enable_for_posts = array(
		'sanitize_callback' => 'clth_sanitize_checkbox',
		'default'           => true,
	);
	register_setting( 'clth_options_group', 'clth_enable_for_posts', $args_enable_for_posts );

	$args_enable_for_pages = array(
		'sanitize_callback' => 'clth_sanitize_checkbox',
		'default'           => false,
	);
	register_setting( 'clth_options_group', 'clth_enable_for_pages', $args_enable_for_pages );

	$args_enable_for_cpt = array(
		'sanitize_callback' => 'clth_sanitize_array',
		'default'           => array(),
	);
	register_setting( 'clth_options_group', 'clth_enable_for_cpt', $args_enable_for_cpt );

	$args_excluded_ids = array(
		'sanitize_callback' => 'clth_sanitize_ids',
		'default'           => array(),
	);
	register_setting( 'clth_options_group', 'clth_excluded_ids', $args_excluded_ids );

	$args_show_icon_on_mobile = array(
		'sanitize_callback' => 'clth_sanitize_checkbox',
		'default'           => true,
	);
	register_setting( 'clth_options_group', 'clth_show_icon_on_mobile', $args_show_icon_on_mobile );

	$args_enable_tooltip = array(
		'sanitize_callback' => 'clth_sanitize_checkbox',
		'default'           => true,
	);
	register_setting( 'clth_options_group', 'clth_enable_tooltip', $args_enable_tooltip );

	$args_copy_text = array(
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => 'Copy Link to Heading',
	);
	register_setting( 'clth_options_group', 'clth_copy_text', $args_copy_text );

	$args_copied_text = array(
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => 'Copied',
	);
	register_setting( 'clth_options_group', 'clth_copied_text', $args_copied_text );

	$args_icon_position = array(
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => 'after',
	);
	register_setting( 'clth_options_group', 'clth_icon_position', $args_icon_position );

	$args_show_icon_on_desktop = array(
		'sanitize_callback' => 'clth_sanitize_checkbox',
		'default'           => false,
	);
	register_setting( 'clth_options_group', 'clth_show_icon_on_desktop', $args_show_icon_on_desktop );

	// New Settings
	$args_icon_size = array(
		'sanitize_callback' => 'absint',
		'default'           => 24,
	);
	register_setting( 'clth_options_group', 'clth_icon_size', $args_icon_size );

	$args_icon_style = array(
		'sanitize_callback' => 'sanitize_key',
		'default'           => 'default',
	);
	register_setting( 'clth_options_group', 'clth_icon_style', $args_icon_style );

	$args_custom_icon_url = array(
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	);
	register_setting( 'clth_options_group', 'clth_custom_icon_url', $args_custom_icon_url );

	$args_icon_color = array(
		'sanitize_callback' => 'sanitize_hex_color',
		'default'           => '',
	);
	register_setting( 'clth_options_group', 'clth_icon_color', $args_icon_color );

	$args_icon_thickness = array(
		'sanitize_callback' => 'sanitize_key',
		'default'           => 'bold',
	);
	register_setting( 'clth_options_group', 'clth_icon_thickness', $args_icon_thickness );
}
add_action( 'admin_init', 'clth_register_settings' );

// Check for first settings save
function clth_check_first_settings_save() {
	// Check if settings were updated.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- settings-updated is set by WordPress after a successful settings save, which implies nonce verification.
	if ( isset( $_GET['settings-updated'], $_GET['page'] ) ) {
		$settings_updated = sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) );
		$page             = sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( $settings_updated && 'clth-settings' === $page ) {
			if ( false === get_option( 'clth_first_settings_save_date' ) ) {
				update_option( 'clth_first_settings_save_date', current_time( 'mysql' ) );
				update_option( 'clth_first_settings_save_version', clth_get_plugin_version() );
			}
		}
	}
	// phpcs:enable
}
add_action( 'admin_init', 'clth_check_first_settings_save' );

// Sanitization callbacks
function clth_sanitize_headings( $input ) {
	return array_filter(
		(array) $input,
		function ( $value ) {
			return in_array( $value, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) );
		}
	);
}

function clth_sanitize_checkbox( $input ) {
	return filter_var( $input, FILTER_VALIDATE_BOOLEAN );
}

function clth_sanitize_array( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}
	return array_map( 'sanitize_key', $input );
}

function clth_sanitize_ids( $input ) {
	if ( is_string( $input ) ) {
		$input = explode( ',', $input );
	}
	return array_filter( array_map( 'absint', (array) $input ) );
}

// Admin footer text only on the settings page, with proper output escaping
function clth_admin_footer_text( $text ) {
	global $pagenow;

	// Check if we're on the plugin's settings page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $pagenow === 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] === 'clth-settings' ) {
		$custom_text = wp_kses_post( __( 'Thank you for using the Copy Link to Heading plugin :) If you like it, please leave <a href="https://wordpress.org/support/plugin/copy-link-to-heading/reviews/#new-post" target="_blank">a ★★★★★ rating</a> to support us on WordPress.org to help us spread the word to the community. If you love to donate, you can provide it via <a href="https://superwebshare.com/donate" style="color: #00a32a; font-weight: bold;" target="_blank">here</a>. Thanks a lot!', 'copy-link-to-heading' ) );
		return $custom_text;
	}

	return $text;
}
add_filter( 'admin_footer_text', 'clth_admin_footer_text' );

// Get plugin version
function clth_get_plugin_version() {
	$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
	return $plugin_data['Version'];
}

// Uninstall cleanup
register_uninstall_hook( __FILE__, 'clth_uninstall_cleanup' );
function clth_uninstall_cleanup() {
	delete_option( 'clth_heading_levels' );
	delete_option( 'clth_enable_for_posts' );
	delete_option( 'clth_enable_for_pages' );
	delete_option( 'clth_enable_for_cpt' );
	delete_option( 'clth_excluded_ids' );
	delete_option( 'clth_show_icon_on_mobile' );
	delete_option( 'clth_enable_tooltip' );
	delete_option( 'clth_copy_text' );
	delete_option( 'clth_copied_text' );
	delete_option( 'clth_icon_position' );
	delete_option( 'clth_show_icon_on_desktop' );
	delete_option( 'clth_icon_size' );
	delete_option( 'clth_icon_style' );
	delete_option( 'clth_custom_icon_url' );
	delete_option( 'clth_icon_color' );
}