<?php 
/**
 * Plugin Name: Copy Link to Heading
 * Description: Adds a copy link icon to headings for easy copying, bookmarking, sharing, and navigation within the content.
 * Version: 1.6
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

// Enqueue CSS and JS
function clth_enqueue_assets() {
    if ( ! clth_should_load() ) {
        return;
    }

    $version    = clth_get_plugin_version();
    $plugin_url = plugin_dir_url( __FILE__ );

    // Register CSS
    wp_register_style( 'clth-style', $plugin_url . 'css/clth-style.css', array(), $version );
    wp_enqueue_style( 'clth-style' );

    // Register JS
    wp_register_script( 'clth-script', $plugin_url . 'js/clth-script.js', array(), $version, true );
    wp_enqueue_script( 'clth-script' );

    // Add defer attribute to script
    wp_script_add_data( 'clth-script', 'defer', true );

    $custom_svg = get_option( 'clth_custom_svg_icon', '' );
    $icon_svg   = ! empty( trim( $custom_svg ) ) ? $custom_svg : clth_get_default_svg_icon();

    // Pass data to JS
    wp_localize_script( 'clth-script', 'clthData', array(
        'iconSvg'           => $icon_svg,
        'headings'          => get_option( 'clth_heading_levels', array( 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
        'showIconOnMobile'  => get_option( 'clth_show_icon_on_mobile', true ),
        'enableTooltip'     => get_option( 'clth_enable_tooltip', true ),
        'copyText'          => get_option( 'clth_copy_text', 'Copy Link to Heading' ),
        'copiedText'        => get_option( 'clth_copied_text', 'Copied' ),
        'iconPosition'      => get_option( 'clth_icon_position', 'after' ),
        'showIconOnDesktop' => get_option( 'clth_show_icon_on_desktop', false ),
        'iconSize'          => get_option( 'clth_icon_size', '' ), // Pass as string
        'iconColor'         => get_option( 'clth_icon_color', '' ), // Default empty, implies currentColor
    ) );
}
add_action( 'wp_enqueue_scripts', 'clth_enqueue_assets' );

// Enqueue admin scripts for color picker
function clth_admin_enqueue_scripts( $hook_suffix ) {
    // Only load on our settings page: settings_page_clth-settings
    if ( 'settings_page_clth-settings' !== $hook_suffix ) {
        return;
    }
    // Enqueue WordPress color picker style and script
    wp_enqueue_style( 'wp-color-picker' );
    // Enqueue our custom admin script
    wp_enqueue_script( 'clth-admin-script', plugin_dir_url( __FILE__ ) . 'js/clth-admin.js', array( 'jquery', 'wp-color-picker' ), clth_get_plugin_version(), true );
}
add_action( 'admin_enqueue_scripts', 'clth_admin_enqueue_scripts' );


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
    if ( ( is_singular( 'post' ) && $show_for_posts ) || ( is_singular( 'page' ) && $show_for_pages ) || ( is_singular( $enabled_cpts ) ) ) {
        return true;
    }

    return false;
}

// Add settings page
function clth_add_settings_page() {
    add_options_page(
        esc_html__( 'Copy Link to Heading Settings', 'copy-link-to-heading' ),
        esc_html__( 'Copy Link to Heading', 'copy-link-to-heading' ),
        'manage_options',
        'clth-settings',
        'clth_render_settings_page'
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
                        $args         = array( 'public' => true, '_builtin' => false );
                        $post_types   = get_post_types( $args, 'objects' );
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
                <?php $enable_tooltip_checked = get_option( 'clth_enable_tooltip', true ); ?>
                <tr valign="top" class="tooltip-text-options" style="<?php echo $enable_tooltip_checked ? '' : 'display: none;'; ?>">
                    <th scope="row"><?php esc_html_e( 'Text for "Copy Link to Heading":', 'copy-link-to-heading' ); ?></th>
                    <td>
                        <input type="text" name="clth_copy_text" value="<?php echo esc_attr( get_option( 'clth_copy_text', 'Copy Link to Heading' ) ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Text to display when hovering over the icon before the link is copied.', 'copy-link-to-heading' ); ?></p>
                    </td>
                </tr>
                <tr valign="top" class="tooltip-text-options" style="<?php echo $enable_tooltip_checked ? '' : 'display: none;'; ?>">
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
                        <p class="description"><?php esc_html_e( 'Select the position of the copy link icon relative to the heading. "After Heading" is the default.', 'copy-link-to-heading' ); ?></p>
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
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Custom SVG Icon:', 'copy-link-to-heading' ); ?></th>
                    <td>
                        <textarea name="clth_custom_svg_icon" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'clth_custom_svg_icon', '' ) ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Enter your custom SVG markup for the link icon. If empty, the default icon will be used.', 'copy-link-to-heading' ); ?>
                            <br>
                            <?php esc_html_e( 'Default icon example (ensure your SVG has `fill="currentColor"` for theme compatibility):', 'copy-link-to-heading' ); ?>
                            <code><?php echo esc_html( clth_get_default_svg_icon() ); ?></code>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Icon Size (px):', 'copy-link-to-heading' ); ?></th>
                    <td>
                        <input type="text" name="clth_icon_size" value="<?php echo esc_attr( get_option( 'clth_icon_size', '' ) ); ?>" class="small-text" placeholder="e.g., 16px or 1.2em" />
                        <p class="description"><?php esc_html_e( 'Override the default icon size. Enter a value with a unit (e.g., 16px, 1.2em, 100%). If only a number is entered, "px" will be assumed. Leave empty to use the default size (1em, scales with text).', 'copy-link-to-heading' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Icon Color:', 'copy-link-to-heading' ); ?></th>
                    <td>
                        <input type="text" name="clth_icon_color" value="<?php echo esc_attr( get_option( 'clth_icon_color', '' ) ); ?>" class="clth-color-picker" data-default-color="" />
                        <p class="description"><?php esc_html_e( 'Choose a custom color for the icon. Leave empty to inherit the heading\'s text color.', 'copy-link-to-heading' ); ?></p>
                    </td>
                </tr>



            </table>
            <?php submit_button(); ?>
        </form>
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

// Set transient on plugin activation
function clth_set_activation_notice() {
    set_transient( 'clth_show_activation_notice', true, 30 );
}
register_activation_hook( __FILE__, 'clth_set_activation_notice' );

// Add settings and donate links on plugins page
function clth_add_plugin_action_links( $links ) {
    $settings_link = '<a href="options-general.php?page=clth-settings">' . esc_html__( 'Settings', 'copy-link-to-heading' ) . '</a>';
    $donate_link   = '<a href="https://superwebshare.com/donate" target="_blank">' . esc_html__( 'Donate', 'copy-link-to-heading' ) . '</a>';
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

    $args_custom_svg = array(
        'sanitize_callback' => 'clth_sanitize_svg',
        'default'           => '',
        'type'              => 'string',
        'show_in_rest'      => false,
    );
    register_setting( 'clth_options_group', 'clth_custom_svg_icon', $args_custom_svg );

    $args_icon_size = array(
        'sanitize_callback' => 'clth_sanitize_icon_size_string',
        'default'           => '',        // Empty means use CSS default
        'type'              => 'string',
    );
    register_setting( 'clth_options_group', 'clth_icon_size', $args_icon_size );

    $args_icon_color = array(
        'sanitize_callback' => 'clth_sanitize_color',
        'default'           => '', // Empty means use currentColor (inherit)
        'type'              => 'string',
    );
    register_setting( 'clth_options_group', 'clth_icon_color', $args_icon_color );
}
add_action( 'admin_init', 'clth_register_settings' );

// Sanitization callbacks
function clth_sanitize_headings( $input ) {
    return array_filter( (array) $input, function ( $value ) {
        return in_array( $value, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) );
    } );
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

function clth_sanitize_icon_size_string( $input ) {
    $input = trim( (string) $input );
    if ( empty( $input ) ) {
        return ''; // Use CSS default
    }

    // If it's just a number, append 'px'
    if ( is_numeric( $input ) ) {
        return floatval( $input ) . 'px';
    }

    // Check for valid CSS size format (number followed by allowed unit)
    // Allows for units like px, em, rem, %, vw, vh.
    if ( preg_match( '/^(\d*\.?\d+)(px|em|rem|%|vw|vh)$/i', $input, $matches ) ) {
        // Reconstruct to ensure consistent formatting e.g. lowercase unit
        return $matches[1] . strtolower( $matches[2] );
    }

    return ''; // Invalid format, revert to CSS default
}

function clth_sanitize_color( $color ) {
    $color = trim( (string) $color );
    if ( empty( $color ) ) {
        return ''; // Default to currentColor (inherit)
    }
    // Check if it's a valid hex color (3, 6, or 8 digits).
    if ( preg_match( '/^#([a-f0-9]{3}){1,2}([a-f0-9]{2})?$/i', $color ) ) {
        return $color;
    }
    // Basic check for rgba/rgb - WordPress color picker might not output this directly but user could type it
    if ( preg_match( '/^rgba?\s*\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)$/i', $color ) ) {
        return $color;
    }
    return ''; // Invalid color format, revert to default (inherit)
}

// Admin footer text only on the settings page, with proper output escaping
function clth_admin_footer_text( $text ) {
    global $pagenow;

    // Check if we're on the plugin's settings page
    if ( $pagenow === 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] === 'clth-settings' ) {
        $custom_text = wp_kses_post( __( 'Thank you for using the Copy Link to Heading plugin :) If you like it, please leave <a href="https://wordpress.org/support/plugin/copy-link-to-heading/reviews/?filter=5#new-post" target="_blank">a ★★★★★ rating</a> to support us on WordPress.org to help us spread the word to the community. If you love to donate, you can provide it via <a href="https://superwebshare.com/donate" target="_blank">here</a>. Thanks a lot!', 'copy-link-to-heading' ) );
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

// Helper function to get the default SVG icon
/**
 * Get the default SVG icon markup.
 *
 * @return string SVG markup.
 */
function clth_get_default_svg_icon() {
    // A GitHub-style link icon
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="clth-svg-icon" aria-hidden="true">'; // Removed fixed width and height
    $svg .= '<path d="m7.775 3.275 1.25-1.25a3.5 3.5 0 1 1 4.95 4.95l-2.5 2.5a3.5 3.5 0 0 1-4.95 0 .751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018 1.998 1.998 0 0 0 2.83 0l2.5-2.5a2.002 2.002 0 0 0-2.83-2.83l-1.25 1.25a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042Zm-4.69 9.64a1.998 1.998 0 0 0 2.83 0l1.25-1.25a.751.751 0 0 1 1.042.018.751.751 0 0 1 .018 1.042l-1.25 1.25a3.5 3.5 0 1 1-4.95-4.95l2.5-2.5a3.5 3.5 0 0 1 4.95 0 .751.751 0 0 1-.018 1.042.751.751 0 0 1-1.042.018 1.998 1.998 0 0 0-2.83 0l-2.5 2.5a1.998 1.998 0 0 0 0 2.83Z"></path>';
    $svg .= '</svg>';
    return $svg;
}

/**
 * Sanitize custom SVG input.
 *
 * @param string $input The SVG markup.
 * @return string Sanitized SVG markup or empty string.
 */
function clth_sanitize_svg( $input ) {
    $input = trim( $input );
    if ( empty( $input ) ) {
        return ''; // Return empty to use default
    }

    $allowed_protocols = array( 'http', 'https', 'mailto' );

    $allowed_tags = array(
        'svg' => array(
            'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true,
            'fill' => true, 'class' => true, 'style' => true, 'aria-hidden' => true,
            'version' => true, 'focusable' => true, 'role' => true,
        ),
        'path' => array(
            'd' => true, 'fill' => true, 'fill-rule' => true, 'class' => true,
            'style' => true, 'transform' => true,
        ),
        'g' => array( 'fill' => true, 'class'=> true, 'style'=> true, 'transform' => true ),
        'title' => array(), 'desc' => array(),
    );
    $shape_attrs = array( 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true );
    $allowed_tags['circle']   = array_merge( array('cx' => true, 'cy' => true, 'r' => true), $shape_attrs );
    $allowed_tags['rect']     = array_merge( array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true), $shape_attrs );
    $allowed_tags['line']     = array_merge( array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true), $shape_attrs );
    $allowed_tags['polyline'] = array_merge( array('points' => true), $shape_attrs );
    $allowed_tags['polygon']  = array_merge( array('points' => true), $shape_attrs );

    $cleaned_svg = wp_kses( $input, $allowed_tags, $allowed_protocols );
    $trimmed_cleaned_svg = trim( $cleaned_svg );

    if ( ! empty( $trimmed_cleaned_svg ) && strtolower( substr( $trimmed_cleaned_svg, 0, 4 ) ) === '<svg' && strtolower( substr( $trimmed_cleaned_svg, -6 ) ) === '</svg>' ) {
        return $cleaned_svg;
    }
    add_settings_error( 'clth_custom_svg_icon', 'invalid_svg', __( 'The custom SVG provided was invalid or contained disallowed elements and could not be saved. The default icon will be used.', 'copy-link-to-heading' ), 'error' );
    return '';
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
    delete_option( 'clth_custom_svg_icon' );
    delete_option( 'clth_icon_size' );
    delete_option( 'clth_icon_color' );
}