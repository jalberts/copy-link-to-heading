<?php

/**
 * Plugin Name: Copy Link to Heading
 * Description: Adds a copy link icon to headings for easy copying, bookmarking, sharing, and navigation within the content.
 * Version: 1.7
 * Author: Jose Varghese
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Text Domain: copy-link-to-heading
 * Domain Path: /languages
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class Copy_Link_To_Heading
{

    private static $instance;

    public static function get_instance()
    {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->define_hooks();
    }

    private function define_hooks()
    {
        add_action('admin_head', array($this, 'remove_admin_notices'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'activation_notice'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
        register_activation_hook(__FILE__, array($this, 'set_activation_notice'));
        register_uninstall_hook(__FILE__, array('Copy_Link_To_Heading', 'uninstall_cleanup'));
    }

    public function remove_admin_notices()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_clth-settings') {
            remove_all_actions('admin_notices');
        }
    }

    public function enqueue_assets()
    {
        if (! $this->should_load()) {
            return;
        }

        $version    = $this->get_plugin_version();
        $plugin_url = plugin_dir_url(__FILE__);

        wp_register_style('clth-style', $plugin_url . 'css/clth-style.css', array(), $version);
        wp_enqueue_style('clth-style');

        wp_register_script('clth-script', $plugin_url . 'js/clth-script.js', array(), $version, true);
        wp_enqueue_script('clth-script');
        wp_script_add_data('clth-script', 'defer', true);

        $custom_svg = get_option('clth_custom_svg_icon', '');
        $icon_svg   = ! empty(trim($custom_svg)) ? $custom_svg : $this->get_default_svg_icon();

        wp_localize_script('clth-script', 'clthData', array(
            'iconSvg'           => $icon_svg,
            'headings'          => get_option('clth_heading_levels', array('h2', 'h3', 'h4', 'h5', 'h6')),
            'showIconOnMobile'  => get_option('clth_show_icon_on_mobile', true),
            'enableTooltip'     => get_option('clth_enable_tooltip', true),
            'copyText'          => get_option('clth_copy_text', 'Copy Link to Heading'),
            'copiedText'        => get_option('clth_copied_text', 'Copied'),
            'iconPosition'      => get_option('clth_icon_position', 'after'),
            'showIconOnDesktop' => get_option('clth_show_icon_on_desktop', false),
            'iconSize'          => get_option('clth_icon_size', ''),
            'iconColor'         => get_option('clth_icon_color', ''),
            'customSelectors'   => get_option('clth_custom_selectors', ''),
        ));
    }

    public function admin_enqueue_scripts($hook_suffix)
    {
        if ('settings_page_clth-settings' !== $hook_suffix) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('clth-admin-script', plugin_dir_url(__FILE__) . 'js/clth-admin.js', array('jquery', 'wp-color-picker'), $this->get_plugin_version(), true);
    }

    public function should_load()
    {
        $current_post_type = get_post_type();
        $excluded_ids      = get_option('clth_excluded_ids', array());
        $enabled_cpts      = get_option('clth_enable_for_cpt', array());
        $show_for_posts    = get_option('clth_enable_for_posts', true);
        $show_for_pages    = get_option('clth_enable_for_pages', false);

        if (! is_array($excluded_ids)) {
            $excluded_ids = array();
        }
        if (! is_array($enabled_cpts)) {
            $enabled_cpts = array();
        }

        if (empty($current_post_type)) {
            return false;
        }

        if (in_array(get_the_ID(), $excluded_ids)) {
            return false;
        }

        if ((is_singular('post') && $show_for_posts) || (is_singular('page') && $show_for_pages) || (is_singular($enabled_cpts))) {
            return true;
        }

        return false;
    }

    public function add_settings_page()
    {
        add_options_page(
            esc_html__('Copy Link to Heading Settings', 'copy-link-to-heading'),
            esc_html__('Copy Link to Heading', 'copy-link-to-heading'),
            'manage_options',
            'clth-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'copy-link-to-heading'));
        }
?>
        <div class="wrap">
            <h1><?php esc_html_e('Copy Link to Heading Settings', 'copy-link-to-heading'); ?> <sup><?php echo esc_html($this->get_plugin_version()); ?></sup></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('clth_options_group');
                do_settings_sections('clth-settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Select heading levels to show link icon:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <p class="description"><?php esc_html_e('By default, no icon is shown for H1 as it is typically used for the title of the page or post.', 'copy-link-to-heading'); ?></p>
                            <?php
                            $headings          = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
                            $selected_headings = get_option('clth_heading_levels', array('h2', 'h3', 'h4', 'h5', 'h6'));
                            if (! is_array($selected_headings)) {
                                $selected_headings = array();
                            }

                            foreach ($headings as $heading) {
                            ?>
                                <label>
                                    <input type="checkbox" name="clth_heading_levels[]" value="<?php echo esc_attr($heading); ?>" <?php checked(in_array($heading, $selected_headings)); ?> />
                                    <?php echo esc_html(strtoupper($heading)); ?>
                                </label><br>
                            <?php
                            }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Show link icons on the following content types:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="checkbox" name="clth_enable_for_posts" value="1" <?php checked(get_option('clth_enable_for_posts', true), 1); ?> /> <?php esc_html_e('Posts', 'copy-link-to-heading'); ?><br />
                            <input type="checkbox" name="clth_enable_for_pages" value="1" <?php checked(get_option('clth_enable_for_pages', false), 1); ?> /> <?php esc_html_e('Pages', 'copy-link-to-heading'); ?><br />
                            <?php
                            $args         = array('public' => true, '_builtin' => false);
                            $post_types   = get_post_types($args, 'objects');
                            $selected_cpts = get_option('clth_enable_for_cpt', array());
                            if (! is_array($selected_cpts)) {
                                $selected_cpts = array();
                            }
                            ?>
                            <input type="hidden" name="clth_enable_for_cpt[]" value="" />
                            <?php
                            foreach ($post_types as $post_type) {
                            ?>
                                <label>
                                    <input type="checkbox" name="clth_enable_for_cpt[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $selected_cpts)); ?> />
                                    <?php echo esc_html($post_type->labels->singular_name); ?>
                                </label><br>
                            <?php
                            }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Exclude Pages (IDs):', 'copy-link-to-heading'); ?></th>
                        <td>
                            <textarea name="clth_excluded_ids" rows="3" class="large-text"><?php echo esc_textarea(implode(',', (array) get_option('clth_excluded_ids', array()))); ?></textarea>
                            <p class="description"><?php esc_html_e('Enter the IDs of pages to exclude, separated by commas. Example: 12, 34, 56', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Always Show Icon on Mobile:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="checkbox" name="clth_show_icon_on_mobile" value="1" <?php checked(get_option('clth_show_icon_on_mobile', true), 1); ?> />
                            <label for="clth_show_icon_on_mobile"><?php esc_html_e('Always show the icon on mobile devices without hovering.', 'copy-link-to-heading'); ?></label>
                            <p class="description"><?php esc_html_e('This is recommended for better usability on touchscreens; otherwise, the icon will only appear when the heading is tapped on mobile device.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable Tooltip:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="checkbox" name="clth_enable_tooltip" id="clth_enable_tooltip" value="1" <?php checked(get_option('clth_enable_tooltip', true), 1); ?> />
                            <label for="clth_enable_tooltip"><?php esc_html_e('Enable tooltips when hovering over the copy icon.', 'copy-link-to-heading'); ?></label>
                            <p class="description"><?php esc_html_e('If disabled, an alert will show instead of the tooltip.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <?php $enable_tooltip_checked = get_option('clth_enable_tooltip', true); ?>
                    <tr valign="top" class="tooltip-text-options" style="<?php echo $enable_tooltip_checked ? '' : 'display: none;'; ?>">
                        <th scope="row"><?php esc_html_e('Text for "Copy Link to Heading":', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="text" name="clth_copy_text" value="<?php echo esc_attr(get_option('clth_copy_text', 'Copy Link to Heading')); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Text to display when hovering over the icon before the link is copied.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top" class="tooltip-text-options" style="<?php echo $enable_tooltip_checked ? '' : 'display: none;'; ?>">
                        <th scope="row"><?php esc_html_e('Text for "Copied":', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="text" name="clth_copied_text" value="<?php echo esc_attr(get_option('clth_copied_text', 'Copied')); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Text to display when the link is copied.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Icon Position:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="clth_icon_position" value="after" <?php checked(get_option('clth_icon_position', 'after'), 'after'); ?> />
                                <?php esc_html_e('After Heading', 'copy-link-to-heading'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="clth_icon_position" value="before" <?php checked(get_option('clth_icon_position', 'after'), 'before'); ?> />
                                <?php esc_html_e('Before Heading', 'copy-link-to-heading'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Select the position of the copy link icon relative to the heading. "After Heading" is the default.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Show Icon on Desktop:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="checkbox" name="clth_show_icon_on_desktop" value="1" <?php checked(get_option('clth_show_icon_on_desktop', false), 1); ?> />
                            <label for="clth_show_icon_on_desktop"><?php esc_html_e('Always show the copy link icon on desktop without hovering.', 'copy-link-to-heading'); ?></label>
                            <p class="description"><?php esc_html_e('If unchecked, the icon will appear on hover as usual. This setting is separate from mobile behavior.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Custom SVG Icon:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <textarea name="clth_custom_svg_icon" rows="5" class="large-text code"><?php echo esc_textarea(get_option('clth_custom_svg_icon', '')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Enter your custom SVG markup for the link icon. If empty, the default icon will be used.', 'copy-link-to-heading'); ?>
                                <br>
                                <?php esc_html_e('Default icon example (ensure your SVG has `fill="currentColor"` for theme compatibility):', 'copy-link-to-heading'); ?>
                                <code><?php echo esc_html($this->get_default_svg_icon()); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Icon Size (px):', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="text" name="clth_icon_size" value="<?php echo esc_attr(get_option('clth_icon_size', '')); ?>" class="small-text" placeholder="e.g., 16px or 1.2em" />
                            <p class="description"><?php esc_html_e('Override the default icon size. Enter a value with a unit (e.g., 16px, 1.2em, 100%). If only a number is entered, "px" will be assumed. Leave empty to use the default size (1em, scales with text).', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Icon Color:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="text" name="clth_icon_color" value="<?php echo esc_attr(get_option('clth_icon_color', '')); ?>" class="clth-color-picker" data-default-color="" />
                            <p class="description"><?php esc_html_e('Choose a custom color for the icon. Leave empty to inherit the heading\'s text color.', 'copy-link-to-heading'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Custom Content Selectors:', 'copy-link-to-heading'); ?></th>
                        <td>
                            <input type="text" name="clth_custom_selectors" value="<?php echo esc_attr(get_option('clth_custom_selectors', '')); ?>" class="large-text" placeholder="e.g., .article-body, #main" />
                            <p class="description">
                                <?php
                                echo wp_kses(
                                    sprintf(
                                        /* translators: %s: A list of default CSS selectors. */
                                        __('<b>Important for theme compatibility.</b> If icons are not appearing, it is likely because your theme uses a different CSS selector for the main content area. This plugin targets common selectors like %s, but you can add your theme\'s specific selectors here (comma-separated). For example, if your content is inside a `div` with a class of `main-story`, you would add `.main-story` here.', 'copy-link-to-heading'),
                                        '<code>.entry-content, .post-content, .page-content, .site-content, .main-content, [role="main"]</code>'
                                    ),
                                    array('code' => array(), 'b' => array())
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    public function activation_notice()
    {
        if (get_transient('clth_show_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Thank you for installing Copy Link to Heading.', 'copy-link-to-heading') . ' <a href="' . esc_url(admin_url('options-general.php?page=clth-settings')) . '">' . esc_html__('Customize your settings here', 'copy-link-to-heading') . '</a>.</p>';
            echo '</div>';
            delete_transient('clth_show_activation_notice');
        }
    }

    public function set_activation_notice()
    {
        set_transient('clth_show_activation_notice', true, 30);
    }

    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="options-general.php?page=clth-settings">' . esc_html__('Settings', 'copy-link-to-heading') . '</a>';
        $donate_link   = '<a href="https://superwebshare.com/donate" target="_blank">' . esc_html__('Donate', 'copy-link-to-heading') . '</a>';
        array_unshift($links, $settings_link, $donate_link);
        return $links;
    }

    public function register_settings()
    {
        $settings = array(
            'clth_heading_levels' => array('sanitize_callback' => array($this, 'sanitize_headings'), 'default' => array('h2', 'h3', 'h4', 'h5', 'h6')),
            'clth_enable_for_posts' => array('sanitize_callback' => array($this, 'sanitize_checkbox'), 'default' => true),
            'clth_enable_for_pages' => array('sanitize_callback' => array($this, 'sanitize_checkbox'), 'default' => false),
            'clth_enable_for_cpt' => array('sanitize_callback' => array($this, 'sanitize_array'), 'default' => array()),
            'clth_excluded_ids' => array('sanitize_callback' => array($this, 'sanitize_ids'), 'default' => array()),
            'clth_show_icon_on_mobile' => array('sanitize_callback' => array($this, 'sanitize_checkbox'), 'default' => true),
            'clth_enable_tooltip' => array('sanitize_callback' => array($this, 'sanitize_checkbox'), 'default' => true),
            'clth_copy_text' => array('sanitize_callback' => 'sanitize_text_field', 'default' => 'Copy Link to Heading'),
            'clth_copied_text' => array('sanitize_callback' => 'sanitize_text_field', 'default' => 'Copied'),
            'clth_icon_position' => array('sanitize_callback' => 'sanitize_text_field', 'default' => 'after'),
            'clth_show_icon_on_desktop' => array('sanitize_callback' => array($this, 'sanitize_checkbox'), 'default' => false),
            'clth_custom_svg_icon' => array('sanitize_callback' => array($this, 'sanitize_svg'), 'default' => ''),
            'clth_icon_size' => array('sanitize_callback' => array($this, 'sanitize_icon_size_string'), 'default' => ''),
            'clth_icon_color' => array('sanitize_callback' => array($this, 'sanitize_color'), 'default' => ''),
            'clth_custom_selectors' => array('sanitize_callback' => array($this, 'sanitize_css_selectors'), 'default' => ''),
        );

        foreach ($settings as $option_name => $args) {
            register_setting('clth_options_group', $option_name, $args);
        }
    }

    public function sanitize_headings($input)
    {
        return array_filter((array) $input, function ($value) {
            return in_array($value, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'));
        });
    }

    public function sanitize_checkbox($input)
    {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }

    public function sanitize_array($input)
    {
        if (! is_array($input)) {
            return array();
        }
        return array_map('sanitize_key', array_filter($input));
    }

    public function sanitize_ids($input)
    {
        if (is_string($input)) {
            $input = explode(',', $input);
        }
        return array_filter(array_map('absint', (array) $input));
    }

    public function sanitize_css_selectors($selectors)
    {
        $sanitized = wp_strip_all_tags((string) $selectors);
        $sanitized = str_replace('`', '', $sanitized);
        return trim($sanitized);
    }

    public function sanitize_icon_size_string($input)
    {
        $input = trim((string) $input);
        if (empty($input)) return '';
        if (is_numeric($input)) return floatval($input) . 'px';
        if (preg_match('/^(\d*\.?\d+)(px|em|rem|%|vw|vh)$/i', $input, $matches)) {
            return $matches[1] . strtolower($matches[2]);
        }
        return '';
    }

    public function sanitize_color($color)
    {
        $color = trim((string) $color);
        if (empty($color)) return '';
        if (preg_match('/^#([a-f0-9]{3}){1,2}([a-f0-9]{2})?$/i', $color)) return $color;
        if (preg_match('/^rgba?\s*\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)$/i', $color)) return $color;
        return '';
    }

    public function sanitize_svg($input)
    {
        $input = trim($input);
        if (empty($input)) return '';

        $allowed_protocols = array('http', 'https', 'mailto');
        $allowed_tags = array(
            'svg' => array('xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'class' => true, 'style' => true, 'aria-hidden' => true, 'version' => true, 'focusable' => true, 'role' => true),
            'path' => array('d' => true, 'fill' => true, 'fill-rule' => true, 'class' => true, 'style' => true, 'transform' => true),
            'g' => array('fill' => true, 'class' => true, 'style' => true, 'transform' => true),
            'title' => array(),
            'desc' => array(),
            'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
            'rect' => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
            'line' => array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
            'polyline' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
            'polygon' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        );

        $cleaned_svg = wp_kses($input, $allowed_tags, $allowed_protocols);
        $trimmed_cleaned_svg = trim($cleaned_svg);

        if (! empty($trimmed_cleaned_svg) && strtolower(substr($trimmed_cleaned_svg, 0, 4)) === '<svg' && strtolower(substr($trimmed_cleaned_svg, -6)) === '</svg>') {
            return $cleaned_svg;
        }
        add_settings_error('clth_custom_svg_icon', 'invalid_svg', __('The custom SVG provided was invalid or contained disallowed elements. Default icon will be used.', 'copy-link-to-heading'), 'error');
        return '';
    }

    public function admin_footer_text($text)
    {
        global $pagenow;
        if ($pagenow === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'clth-settings') {
            return wp_kses_post(__('Thank you for using the Copy Link to Heading plugin :) If you like it, please leave <a href="https://wordpress.org/support/plugin/copy-link-to-heading/reviews/?filter=5#new-post" target="_blank">a ★★★★★ rating</a> to support us on WordPress.org. If you love to donate, you can do so <a href="https://superwebshare.com/donate" target="_blank">here</a>. Thanks a lot!', 'copy-link-to-heading'));
        }
        return $text;
    }

    public function get_plugin_version()
    {
        $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'));
        return $plugin_data['Version'];
    }

    public function get_default_svg_icon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="clth-svg-icon" aria-hidden="true">';
        $svg .= '<path d="m7.775 3.275 1.25-1.25a3.5 3.5 0 1 1 4.95 4.95l-2.5 2.5a3.5 3.5 0 0 1-4.95 0 .751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018 1.998 1.998 0 0 0 2.83 0l2.5-2.5a2.002 2.002 0 0 0-2.83-2.83l-1.25 1.25a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042Zm-4.69 9.64a1.998 1.998 0 0 0 2.83 0l1.25-1.25a.751.751 0 0 1 1.042.018.751.751 0 0 1 .018 1.042l-1.25 1.25a3.5 3.5 0 1 1-4.95-4.95l2.5-2.5a3.5 3.5 0 0 1 4.95 0 .751.751 0 0 1-.018 1.042.751.751 0 0 1-1.042.018 1.998 1.998 0 0 0-2.83 0l-2.5 2.5a1.998 1.998 0 0 0 0 2.83Z"></path>';
        $svg .= '</svg>';
        return $svg;
    }

    public static function uninstall_cleanup()
    {
        $options = array(
            'clth_heading_levels',
            'clth_enable_for_posts',
            'clth_enable_for_pages',
            'clth_enable_for_cpt',
            'clth_excluded_ids',
            'clth_show_icon_on_mobile',
            'clth_enable_tooltip',
            'clth_copy_text',
            'clth_copied_text',
            'clth_icon_position',
            'clth_show_icon_on_desktop',
            'clth_custom_svg_icon',
            'clth_icon_size',
            'clth_icon_color',
            'clth_custom_selectors',
        );
        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

Copy_Link_To_Heading::get_instance();
