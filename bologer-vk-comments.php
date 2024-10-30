<?php
/**
 * Plugin Name: VK Man Comments
 * Plugin URI: http://bologer.ru
 * Description: VK Man Comments - comments from VK.com for WordPress
 * Author: Alexander Teshabaev <admin@bologer.ru>
 * Version: 0.0.21
 * Author URI: http://bologer.ru/
 * Text Domain: bvk-comments
 * Domain Path: /languages/
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    die();
}

define('BVK_COMMENTS_VERSION', '0.0.22');
define('BVK_COMMENTS_FOLDER', basename(dirname(__FILE__)));
define('BVK_COMMENTS_DIR', plugin_dir_path(__FILE__));
define('BVK_COMMENTS_INC', BVK_COMMENTS_DIR . 'includes' . '/');
define('BVK_COMMENTS_URL', plugin_dir_url(BVK_COMMENTS_FOLDER) . BVK_COMMENTS_FOLDER . '/');
define('BVK_COMMENTS_INC_URL', BVK_COMMENTS_URL . 'includes' . '/');

define('BVK_COMMENTS_LANG', 'bvk-comments');


define('BVK_COMMENTS_PAGE_DEFAULT', 'bologer-vk-comments');
define('BVK_COMMENTS_PAGE_SETTINGS', 'bologer-vk-comments-settings');
define('BVK_COMMENTS_PAGE_SETTINGS_DESIGN', 'bologer-vk-comments-settings-design');
define('BVK_COMMENTS_PAGE_VIEW_ALL', 'bologer-vk-comments-all');
define('BVK_ATTACHMENT_TYPES', 'graffiti,photo,audio,video,link');

define("VKMAN_DEBUG", false);
define('VKMAN_COMMENTS_PAGE_SETTINGS_BASE', 'vkman_options_base');
define('VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN', 'vkman_options_design');
define('VKMAN_COMMENTS_PAGE_SETTINGS_FUNCTION', 'vkman_options_function');

if (!class_exists('BVKComments_Plugin')) {

    class BVKComments_Plugin
    {
        /**
         * List of options fron native WordPress options.
         *
         * @var null|array
         */
        private $_options = null;

        /**
         * Initiate plugin.
         */
        public function __construct()
        {
            add_action('admin_menu', [$this, 'init_menu']);
            add_action('admin_init', [$this, 'init_settings']);

            register_setting(BVK_COMMENTS_PAGE_SETTINGS, 'bvk_comments_options');
            load_plugin_textdomain('bvk-comments', "", BVK_COMMENTS_FOLDER . '/languages');

            $this->_options = self::getOptions();

            if ($this->isActive()) {
                add_action('wp_head', [$this, 'head_scripts']);
                add_action('admin_head', [$this, 'head_scripts']);
            }

            if ($this->isOverride() && $this->isActive()) {
                add_filter('comments_template', [$this, 'new_comment_template']);
            }

            if ($this->isActive()) {
                if ($this->isPositionBeforeForm()) {
                    add_action('comment_form_before', [$this, 'insert_widget']);
                } else if ($this->isPositionAfterForm()) {
                    add_action('comment_form_after', [$this, 'insert_widget']);
                } else if ($this->isPositionUnderHeaderForm()) {
                    add_action('comment_form_top', [$this, 'insert_widget']);
                } else {
                    add_action('comment_form_before', [$this, 'insert_widget']);
                }
            }

            add_shortcode('bvk_comments', [$this, 'insert_widget']);
        }

        /**
         * Custom options and settings.
         * @return void
         */
        public function init_settings()
        {
            add_settings_section(
                VKMAN_COMMENTS_PAGE_SETTINGS_BASE,
                __('Base', 'bvk-comments'),
                null,
                BVK_COMMENTS_PAGE_SETTINGS
            );

            add_settings_section(
                VKMAN_COMMENTS_PAGE_SETTINGS_FUNCTION,
                __('Functions', 'bvk-comments'),
                null,
                BVK_COMMENTS_PAGE_SETTINGS
            );

            add_settings_section(
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                __('Design', 'bvk-comments'),
                null,
                BVK_COMMENTS_PAGE_SETTINGS
            );

            /**
             * Toggle on/off
             */
            add_settings_field(
                'bvk_comments_field_toggle_on_off',
                __('Active', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_BASE,
                [
                    'label_for' => 'bvk_comments_field_toggle_on_off',
                    'class' => 'bvk_comments_row',
                    'description' => __('Widget is active.', 'bvk-comments')
                ]
            );

            /**
             * Widget id, unique per domain.
             */
            add_settings_field(
                'bvk_comments_field_id',
                __('App ID', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_BASE,
                [
                    'label_for' => 'bvk_comments_field_id',
                    'class' => 'bvk_comments_row',
                    'description' => __('Enter app id. It should be taken from VK. Use button above to help you with this.', 'bvk-comments')
                ]
            );

            /**
             * Give support to the plugin
             */
            add_settings_field(
                'bvk_comments_field_support_plugin',
                __('Support', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_BASE,
                [
                    'label_for' => 'bvk_comments_field_support_plugin',
                    'class' => 'bvk_comments_row',
                    'description' => __('Check this checkbox if you like "VK Man Comments" and as part of appreciation display our link with text "Add VK Man Comments To My Site" under comments widget.', 'bvk-comments')
                ]
            );

            /**
             * Auto publish or not.
             */
            add_settings_field(
                'bvk_comments_field_auto_publish',
                __('Auto Publish', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_FUNCTION,
                [
                    'label_for' => 'bvk_comments_field_auto_publish',
                    'class' => 'bvk_comments_row',
                    'description' => __('Automatically publish the comment to the user\'s VK page', 'bvk-comments'),
                ]
            );

            /**
             * Whether to have real time or not.
             */
            add_settings_field(
                'bvk_comments_field_no_real_time',
                __('No Real Time', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_FUNCTION,
                [
                    'label_for' => 'bvk_comments_field_no_real_time',
                    'class' => 'bvk_comments_row',
                    'description' => __('Enable realtime update for comments', 'bvk-comments'),
                ]
            );

            /**
             * Together with original comments or override.
             */
            add_settings_field(
                'bvk_comments_field_comment_override',
                __('Override', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_comment_override',
                    'class' => 'bvk_comments_row',
                    'description' => __('Override native comments', 'bvk-comments')
                ]
            );

            add_settings_field(
                'bvk_comments_field_position',
                __('Position', 'bvk-comments'),
                [$this, 'field_position_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_position',
                    'class' => 'bvk_comments_row'
                ]
            );


            add_settings_field(
                'bvk_comments_field_limit',
                __('Limit', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_limit',
                    'class' => 'bvk_comments_row',
                    'description' => __('Minimum: 5, default is: 10. Or set custom value.', 'bvk-comments')
                ]
            );

            /**
             * Type of widget to display.
             */
            add_settings_field(
                'bvk_comments_field_mini',
                __('Type', 'bvk-comments'),
                [$this, 'field_mini_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_mini',
                    'class' => 'bvk_comments_row'
                ]
            );

            /**
             * Custom width.
             */
            add_settings_field(
                'bvk_comments_field_width',
                __('Width', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_width',
                    'class' => 'bvk_comments_row',
                    'description' => __('width in pixels. Minimal value is 300. If the parameter is not set, widget will take all available width.', 'bvk-comments')
                ]
            );

            /**
             * Custom height.
             */
            add_settings_field(
                'bvk_comments_field_height',
                __('Height', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_height',
                    'class' => 'bvk_comments_row',
                    'description' => __('maximum height of the widget in pixels. Minimal value is 500. If 0, heigth is not limited. If widget content is larger than the maximum allowed, internal scrolling appears. By default — 0.', 'bvk-comments')
                ]
            );

            /**
             * Number of comments to display.
             */
            add_settings_field(
                'bvk_comments_field_limit',
                __('Limit', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_limit',
                    'class' => 'bvk_comments_row',
                    'description' => __('number of comments on the page. Minimum - 5, maximum - 100.', 'bvk-comments')
                ]
            );

            // H-type
            add_settings_field(
                'bvk_comments_field_header_type',
                __('Header Element', 'bvk-comments'),
                [$this, 'field_header_type_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_header_type',
                    'class' => 'bvk_comments_row',
                    'description' => __('set element type of the header. E.g. h1, h2 up to h6.', 'bvk-comments')
                ]
            );

            // Space from the bottom of the header
            add_settings_field(
                'bvk_comments_field_header_margin_top',
                __('Header Top Space', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_header_margin_top',
                    'class' => 'bvk_comments_row',
                    'description' => __('set space (in pixels) from the top of the header', 'bvk-comments')
                ]
            );

            // Space from the bottom of the header
            add_settings_field(
                'bvk_comments_field_header_margin_bottom',
                __('Header Bottom Space', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_header_margin_bottom',
                    'class' => 'bvk_comments_row',
                    'description' => __('set space (in pixels) from the bottom of the header', 'bvk-comments')
                ]
            );

            // Space from the top of the widget
            add_settings_field(
                'bvk_comments_field_widget_margin_top',
                __('Widget Top Space', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_widget_margin_top',
                    'class' => 'bvk_comments_row',
                    'description' => __('set space (in pixels) from the top of the widget', 'bvk-comments')
                ]
            );

            // Space from the bottom of the widget
            add_settings_field(
                'bvk_comments_field_widget_margin_bottom',
                __('Widget Bottom Space', 'bvk-comments'),
                [$this, 'field_text_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_widget_margin_bottom',
                    'class' => 'bvk_comments_row',
                    'description' => __('set space (in pixels) from the bottom of the widget', 'bvk-comments')
                ]
            );


            /**
             * Checkbox graffiti
             */
            add_settings_field(
                'bvk_comments_field_show_override_header',
                __('Show Override Header', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_show_override_header',
                    'class' => 'bvk_comments_row',
                    'description' => __('Whether to show or hide header on active "Override" option', 'bvk-comments')
                ]
            );

            /**
             * Checkbox graffiti
             */
            add_settings_field(
                'bvk_comments_field_graffiti',
                __('Graffiti', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_graffiti',
                    'class' => 'bvk_comments_row',
                    'description' => __('Allow to attach graffiti', 'bvk-comments')
                ]
            );

            /**
             * Checkbox photos
             */
            add_settings_field(
                'bvk_comments_field_photo',
                __('Photos', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_photo',
                    'class' => 'bvk_comments_row',
                    'description' => __('Allow to attach photos', 'bvk-comments')
                ]
            );

            /**
             * Checkbox Videos
             */
            add_settings_field(
                'bvk_comments_field_video',
                __('Videos', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_video',
                    'class' => 'bvk_comments_row',
                    'description' => __('Allow to attach video', 'bvk-comments')
                ]
            );

            /**
             * Checkbox Audios
             */
            add_settings_field(
                'bvk_comments_field_audio',
                __('Audios', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_audio',
                    'class' => 'bvk_comments_row',
                    'description' => __('Allow to attach audio', 'bvk-comments')
                ]
            );

            /**
             * Checkbox links
             */
            add_settings_field(
                'bvk_comments_field_link',
                __('Links', 'bvk-comments'),
                [$this, 'field_checkbox_cb'],
                BVK_COMMENTS_PAGE_SETTINGS,
                VKMAN_COMMENTS_PAGE_SETTINGS_DESIGN,
                [
                    'label_for' => 'bvk_comments_field_link',
                    'class' => 'bvk_comments_row',
                    'description' => __('Allow to attach link', 'bvk-comments')
                ]
            );
        }

        /**
         * Registering admin menu.
         * @link https://developer.wordpress.org/reference/functions/add_menu_page/
         * @return void
         */
        public function init_menu()
        {
            add_menu_page(
                __('VK Comments', 'bvk-comments'),
                __('VK Comments', 'bvk-comments'),
                'manage_options',
                BVK_COMMENTS_PAGE_DEFAULT,
                [$this, 'index_page_html'],
                plugins_url('bologer-vk-comments/images/vk.png'),
                24
            );

            add_submenu_page(
                BVK_COMMENTS_PAGE_DEFAULT,
                __('Settings', 'bvk-comments'),
                __('Settings', 'bvk-comments'),
                'manage_options',
                BVK_COMMENTS_PAGE_SETTINGS,
                [$this, 'options_page_html']
            );

            add_submenu_page(
                BVK_COMMENTS_PAGE_DEFAULT,
                __('All Comments', 'bvk-comments'),
                __('All Comments', 'bvk-comments'),
                'manage_options',
                BVK_COMMENTS_PAGE_VIEW_ALL,
                [$this, 'view_all_html']
            );
        }


        /**
         * Make custom template for comments.
         * @return string
         */
        public function new_comment_template()
        {
            return dirname(__FILE__) . '/comments.php';
        }

        /**
         * Html content for position of the widget.
         * @param $args
         */
        public function field_position_cb($args)
        {
            // get the value of the setting we've registered with register_setting()
            $options = $this->_options;
            // output the field
            ?>
            <select id="<?php echo esc_attr($args['label_for']); ?>"
                    name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
            >
                <option value="before_form" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'before_form', false)) : (''); ?>>
                    <?= __('Before Form', 'bvk-comments'); ?>
                </option>

                <option value="after_form" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'after_form', false)) : (''); ?>>
                    <?= __('After Form', 'bvk-comments'); ?>
                </option>

                <option value="under_header_form" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'under_header_form', false)) : (''); ?>>
                    <?= __('Under Form Header', 'bvk-comments'); ?>
                </option>
            </select>

            <p class="description">
                <?= __('Position of the widget. Whether to put it before, after the form or under the header of native WordPress comments (if "Override" option is unchecked).', 'bvk-comments'); ?>
            </p>
            <?php
        }

        public function field_header_type_cb($args)
        {
            // get the value of the setting we've registered with register_setting()
            $options = $this->_options;
            // output the field
            ?>
            <select id="<?php echo esc_attr($args['label_for']); ?>"
                    name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
            >
                <?php for ($i = 1; $i < 6; $i++): ?>
                    <option value="h<?= $i ?>" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'h' . $i, false)) : (''); ?>>
                        <?= __('H' . $i, 'bvk-comments'); ?>
                    </option>
                <?php endfor; ?>
            </select>

            <p class="description"><?= $args['description'] ?></p>
            <?php
        }


        /**
         * Html content for widget selection select box.
         * @param $args
         */
        public function field_mini_cb($args)
        {
            $options = $this->_options;
            ?>
            <select id="<?php echo esc_attr($args['label_for']); ?>"
                    name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
            >
                <option value="auto" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'auto', false)) : (''); ?>>
                    <?= __('Auto', 'bvk-comments'); ?>
                </option>
                <option value="1" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], '1', false)) : (''); ?>>
                    <?= __('Enabled', 'bvk-comments'); ?>
                </option>
                <option value="0" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], '0', false)) : (''); ?>>
                    <?= __('Disabled', 'bvk-comments'); ?>
                </option>
            </select>

            <p class="description">
                <?= __('enables the mini version of the widget — smaller fonts, smaller attachment thumbnails, smaller profile pcitures in second level comments. (1 — enabled, 0 — disabled, auto — automatic selection depending on the available width). By default — auto', 'bvk-comments'); ?>
            </p>
            <?php
        }

        /**
         * Html content for choosing whether comments would be real time or not.
         * @param $args
         */
        public function field_no_real_time_cb($args)
        {
            $options = $this->_options;
            ?>
            <select id="<?php echo esc_attr($args['label_for']); ?>"
                    name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
            >
                <option value="1" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], '1', false)) : (''); ?>>
                    <?= __('Enabled', 'bvk-comments'); ?>
                </option>
                <option value="0" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], '0', false)) : (''); ?>>
                    <?= __('Disabled', 'bvk-comments'); ?>
                </option>
            </select>

            <p class="description">
                <?= __('disables realtime updates for the comments. (1 — disabled,0 — enabled). By default — 0', 'bvk-comments'); ?>
            </p>
            <?php
        }

        /**
         * Html content for auto publish select box.
         * @param $args
         */
        public function field_auto_publish_cb($args)
        {
            $options = $this->_options;
            ?>
            <select id="<?php echo esc_attr($args['label_for']); ?>"
                    name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
            >
                <option value="1" <?= isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], '1', false)) : (''); ?>>
                    <?= __('Enabled', 'bvk-comments'); ?>
                </option>
                <option value="0" <?= isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], '0', false)) : (''); ?>>
                    <?= __('Disabled', 'bvk-comments'); ?>
                </option>
            </select>

            <p class="description">
                <?= __('automatically publish the comment to the user\'s VK page (0 — disabled, 1 — enabled). By default — 0.', 'bvk-comments'); ?>
            </p>
            <?php
        }


        /**
         * Html content for checkbox-like inputs.
         * @param $args
         */
        public function field_checkbox_cb($args)
        {
            $options = $this->_options;
            ?>

            <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>"
                   name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
                <?= (isset($options[$args['label_for']]) ? 'checked' : '') ?>>

            <?php
            if (isset($args['description'])): ?>
                <p class="description">
                    <?= esc_html($args['description']); ?>
                </p>
                <?php
            endif;
        }

        /**
         * Html content for regular text-like inputs.
         * @param $args
         */
        public function field_text_cb($args)
        {
            $options = $this->_options;
            ?>

            <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
                   name="bvk_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
                   value="<?= (isset($options[$args['label_for']]) ? $options[$args['label_for']] : '') ?>">

            <?php
            if (isset($args['description'])): ?>
                <p class="description">
                    <?= esc_html($args['description']); ?>
                </p>
                <?php
            endif;
        }

        /**
         * Insert VK API JavaScript script into the head.
         * As mentioned in https://vk.com/dev/Comments.
         *
         * @return void
         */
        public function head_scripts()
        {
            ?>
            <script type="text/javascript" src="//vk.com/js/api/openapi.js?151"></script>
            <?php
        }

        /**
         * Check whether widget is active or not.
         * @return bool
         */
        public function isActive()
        {
            return isset($this->_options['bvk_comments_field_toggle_on_off']);
        }

        /**
         * Check if user would like to support plugin and display our custom url
         * to plugins official WordPress repo page or official website url.
         * @return bool
         */
        public function isSupport()
        {
            return isset($this->_options['bvk_comments_field_support_plugin']);
        }

        /**
         * Get widget options.
         *
         * @return mixed
         */
        public static function getOptions()
        {
            return get_option('bvk_comments_options');
        }


        /**
         * Get list of attachments types as array.
         * @return array
         */
        public function getAttachmentsArray()
        {
            $arr = explode(',', BVK_ATTACHMENT_TYPES);

            return $arr;
        }

        /**
         * Get app id.
         *
         * @return null|string String when app id is available.
         */
        public function getAppId()
        {
            return isset($this->_options['bvk_comments_field_id']) ? $this->_options['bvk_comments_field_id'] : null;
        }

        /**
         * Whether VK comment should override
         * native comments or not.
         *
         * @return bool
         */
        public function isOverride()
        {
            return isset($this->_options['bvk_comments_field_comment_override']);
        }

        /**
         * Whether show header on "Override" option enabled.
         *
         * @return bool
         */
        public function isShowHeaderOnOverride()
        {
            return isset($this->_options['bvk_comments_field_show_override_header']);
        }

        /**
         * Whether position is before the form.
         *
         * @return bool
         */
        public function isPositionBeforeForm()
        {
            return isset($this->_options['bvk_comments_field_position']) && $this->_options['bvk_comments_field_position'] === 'before_form';
        }

        /**
         * Whether position is after the native form.
         *
         * @return bool
         */
        public function isPositionAfterForm()
        {
            return isset($this->_options['bvk_comments_field_position']) && $this->_options['bvk_comments_field_position'] === 'after_form';
        }

        /**
         * Whether position of comments should be under the
         * native header of the comments.
         *
         * @return bool
         */
        public function isPositionUnderHeaderForm()
        {
            return isset($this->_options['bvk_comments_field_position']) && $this->_options['bvk_comments_field_position'] === 'under_header_form';
        }

        /**
         * Get header type of the override header.
         *
         * @return null
         */
        public function getHeaderType()
        {
            return isset($this->_options['bvk_comments_field_header_type']) && !empty($this->_options['bvk_comments_field_header_type']) ? $this->_options['bvk_comments_field_header_type'] : null;
        }

        /**
         * Get margin top of the override header.
         *
         * @return null
         */
        public function getHeaderMarginTop()
        {
            return (int)isset($this->_options['bvk_comments_field_header_margin_top']) ? $this->_options['bvk_comments_field_header_margin_top'] : null;
        }

        /**
         * Get margin bottom of the override header.
         *
         * @return null
         */
        public function getHeaderMarginBottom()
        {
            return (int)isset($this->_options['bvk_comments_field_header_margin_bottom']) ? $this->_options['bvk_comments_field_header_margin_bottom'] : null;
        }

        /**
         * Get widget space from the top.
         *
         * @return null
         */
        public function getWidgetMarginTop()
        {
            return (int)isset($this->_options['bvk_comments_field_widget_margin_top']) ? $this->_options['bvk_comments_field_widget_margin_top'] : null;
        }

        /**
         * Get widget space from the top.
         *
         * @return null
         */
        public function getWidgetMarginBottom()
        {
            return (int)isset($this->_options['bvk_comments_field_widget_margin_bottom']) ? $this->_options['bvk_comments_field_widget_margin_bottom'] : null;
        }

        /**
         * Set-up current active tab.
         *
         * @return string
         */
        public static function getCurrentTab()
        {
            return (!empty($_GET['tab']) ? esc_attr($_GET['tab']) : 'index');
        }

        /**
         * Set-up navigation tabs.
         *
         * @return string
         */
        public static function getPageTabs()
        {
            $current = self::getCurrentTab();

            $tabs = [
                'index' => __('Instructions', 'bvk-comments'),
                'settings' => __('Main', 'bvk-comments'),
                'preview' => __('Preview', 'bvk-comments'),
            ];

            $html = '<h2 class="nav-tab-wrapper">';
            foreach ($tabs as $tab => $name) {
                $class = ($tab == $current) ? 'nav-tab-active' : '';
                $html .= '<a class="nav-tab ' . $class . '" href="?page=' . BVK_COMMENTS_PAGE_SETTINGS . '&tab=' . $tab . '">' . $name . '</a>';
            }
            $html .= '</h2>';
            echo $html;
        }

        /**
         * Html content for viewing all comments.
         */
        public function view_all_html()
        {
            // check user capabilities
            if (!current_user_can('manage_options')) {
                return;
            }

            ?>

            <div class="wrap">
                <h1><?= esc_html(get_admin_page_title()); ?></h1>

                <?php if ($this->isActive()): ?>
                    <p><?= __('On this page you can see all of the comments on your website.', 'bvk-comments') ?></p>
                    <?php $this->insertViewAll(); ?>
                <?php else: ?>
                    <p><?= __('VK widget is disabled, preview is unavailable.', 'bvk-comments') ?></p>
                <?php endif; ?>

            </div>
            <?php
        }

        /**
         * Notice message when widget is disabled.
         */
        public function bvk_comments_disabled_notice()
        {

            if (!$this->isActive()) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?= __('It seems that VK widget is disabled. Please enable it if you did it by mistake.', 'bvk-comments') ?></p>
                </div>
                <?php
            }
        }

        /**
         * Html for index page.
         */
        public function index_page_html()
        {
            ?>

            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p><?= __('Welcome to Bologer VK Comments plugin.', 'bvk-comments') ?></p>
                <p><?= __('You may customize it as you like.', 'bvk-comments') ?></p>
                <p><?= __('Your support will be helpful. Give us <a href="https://ru.wordpress.org/plugins/bologer-vk-comments/">5 stars</a> to motivate us further.', 'bvk-comments') ?></p>

                <p><strong><?= __('Features you might like the most:', 'bvk-comments') ?></strong></p>
                <ol>
                    <li><?= __('Completely override native WordPress comments', 'bvk-comments') ?></li>
                    <li><?= __('Set VK widget together with native WordPress comments. Set position of VK widget (over, under or below the header of the native comments)', 'bvk-comments') ?></li>
                    <li><?= __('Copy code from VK.com and paste into special field to auto fill most of the settings in widget', 'bvk-comments') ?></li>
                    <li><?= __('Fully customized settings of the widget directly from admin panel', 'bvk-comments') ?></li>
                </ol>
            </div>
            <?php
        }

        /**
         * Html for settings page.
         */
        public function options_page_html()
        {
            // check user capabilities
            if (!current_user_can('manage_options')) {
                return;
            }

            $this->bvk_comments_disabled_notice();

            // check if the user have submitted the settings
            // wordpress will add the "settings-updated" $_GET parameter to the url
            if (isset($_GET['settings-updated'])) {
                // add settings saved message with the class of "updated"
                add_settings_error('bvk_comments_messages', 'bvk_comments_message', __('Settings Saved', 'bvk-comments'), 'updated');
            }

            // show error/update messages
            settings_errors('bvk_comments_messages');
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <?php
                if (VKMAN_DEBUG): ?>
                    <pre>
                <?php var_dump($this->getOptions()); ?>
                    </pre>
                <?php endif; ?>

                <?= self::getPageTabs() ?>

                <?php if (self::getCurrentTab() === 'index') : ?>
                    <p><?= __('Follow instructions below to set-up VK comments widget:', 'bvk-comments') ?></p>
                    <ol>
                        <li><?= __('Go to <a href="https://vk.com/dev/Comments" target="_blank">VK Developers page</a>', 'bvk-comments') ?></li>
                        <li><?= __('Add your website or choose an existing one', 'bvk-comments') ?></li>
                        <li><?= __('Copy code, click "Enter Settings Magically"', 'bvk-comments') ?></li>
                        <li><?= __('Paste code in the opened field and click "Fill Below" to auto fill some of values below', 'bvk-comments') ?></li>
                    </ol>
                <?php elseif (self::getCurrentTab() === 'settings') : ?>

                    <div id="vk-widget-code">
                        <p><a href="#" class="button button-default"
                              id="button-enter-settings-magically"><?= __('Enter Settings Magically', 'bvk-comments') ?></a>
                        </p>

                        <div id="textarea-wrapper" style="display: none;">
               <textarea name="vk-widget-code" cols="30" rows="5"
                         placeholder="<?= __('Enter widget code here...', 'bvk-comments') ?>"
               ></textarea>

                            <p><a href="#" class="button button-default"
                                  id="textarea-fill-automatically"><?= __('Fill Below', 'bvk-comments') ?></a></p>
                        </div>


                        <script>
                            jQuery(function ($) {
                                var textAreaWrapper = $('#textarea-wrapper');
                                var textArea = textAreaWrapper.find('textarea[name="vk-widget-code"]');


                                $('#button-enter-settings-magically').on('click', function (e) {
                                    e.preventDefault();

                                    textAreaWrapper.slideToggle();
                                    textArea.focus();

                                    return false;
                                });

                                $('#textarea-fill-automatically').on('click', function (e) {
                                    e.preventDefault();

                                    var textAreaText = textArea.val().trim();
                                    var reWidgetId = /apiId\:\s(\d{7,}),/g;
                                    var reWidgetLimit = /limit\:\s(\d{1,3}),/g;
                                    var reWidgetAttach = /attach\:\s"(.*?)"/g;

                                    var reWidgetIdArr = reWidgetId.exec(textAreaText);
                                    var reWidgetLimitArr = reWidgetLimit.exec(textAreaText);
                                    var reWidgetAttachArr = reWidgetAttach.exec(textAreaText);

                                    console.log(reWidgetIdArr);
                                    console.log(reWidgetLimitArr);
                                    console.log(reWidgetAttachArr);

                                    if (reWidgetIdArr.length > 1) {
                                        $('input#bvk_comments_field_id').val(reWidgetIdArr[1]);
                                    }

                                    if (reWidgetLimitArr.length > 1) {
                                        $('input#bvk_comments_field_limit').val(reWidgetLimitArr[1]);
                                    }

                                    return false;
                                });
                            });
                        </script>
                    </div>

                    <form action="options.php" method="post">
                        <?php
                        settings_fields(BVK_COMMENTS_PAGE_SETTINGS);

                        do_settings_sections(BVK_COMMENTS_PAGE_SETTINGS);

                        submit_button(__('Save Settings', 'bvk-comments'));
                        ?>
                    </form>
                <?php elseif (self::getCurrentTab() === 'preview'): ?>

                    <div class="bvk-widget-preview">
                        <h2><?= __('Widget Preview', 'bvk-comments') ?></h2>
                        <?php if ($this->isActive()): ?>
                            <p><?= __('Below you can see how your widget would look the comments', 'bvk-comments') ?></p>


                            <?php do_shortcode('[bvk_comments]') ?>
                        <?php else: ?>
                            <p><?= __('VK widget is disabled, preview is unavailable.', 'bvk-comments') ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }


        /**
         * Insert vk widget before regular comments.
         *
         * @link https://wordpress.stackexchange.com/a/39033
         * @return void
         */
        public function insert_widget()
        {
            $widgetId = $this->getAppId();
            ?>
            <script type="text/javascript">
                VK.init({apiId: <?= $widgetId ?>, onlyWidgets: true});
            </script>

            <?php
            $styles = null;

            if (($widgetMarginTop = $this->getWidgetMarginTop()) !== null) {
                $styles = "margin-top: $widgetMarginTop" . "px;";
            }

            if (($widgetMarginBittom = $this->getWidgetMarginBottom()) !== null) {
                $styles .= "margin-bottom: $widgetMarginBittom" . "px;";
            }
            ?>

            <div id="vk_comments" <?= ($styles !== null ? "style=\"$styles\"" : '') ?>></div>

            <?= $this->generateSupportUrl() ?>

            <script type="text/javascript">
                VK.Widgets.Comments("vk_comments", <?= $this->generateWidgetSettings() ?>);
            </script>
            <?php
        }

        /**
         * Preview all comments from vk in admin.
         */
        public function insertViewAll()
        {
            $widgetId = $this->getAppId();
            ?>
            <div id="vk_comments_preview_all"></div>
            <script type="text/javascript">
                window.onload = function () {
                    VK.init({apiId: <?= $widgetId ?>, onlyWidgets: true});
                    VK.Widgets.CommentsBrowse('vk_comments_preview_all', {limit: 10});
                }
            </script>
            <?php
        }

        /**
         * Generate support URL, when use would like to
         * support plugin and give some love.
         */
        public function generateSupportUrl()
        {
            if (!$this->isSupport()) {
                return null;
            }
            ?>

            <p class="text-align: right;">
                <a href="https://wordpress.org/plugins/bologer-vk-comments/" target="_blank"
                   style="font-size: 12px; text-decoration: none; letter-spacing: normal; color: rgba(0,0,0,0.57);">
                    <?= __('Add VK Man Comments To My Site', 'bvk-comments') ?>
                </a>
            </p>
            <?php
        }

        /**
         * Generate settings for vk comments.
         *
         * @return string Formatted string in JSON-like style.
         */
        public function generateWidgetSettings()
        {
            $options = $this->_options;

            $widgetLimit = $options['bvk_comments_field_limit'];
            $widgetMini = isset($options['bvk_comments_field_mini']) ? 1 : '';
            $widgetWidth = isset($options['bvk_comments_field_width']) ? (int)$options['bvk_comments_field_width'] : '';
            $widgetHeight = isset($options['bvk_comments_field_height']) ? (int)$options['bvk_comments_field_height'] : '';
            $widgetAutoPublish = isset($options['bvk_comments_field_auto_publish']) ? 1 : '';
            $widgetNoRealTime = isset($options['bvk_comments_field_no_real_time']) ? 1 : '';

            $attachmentsString = null;

            $attachmentArray = $this->getAttachmentsArray();

            $attachmentCount = 0;
            foreach ($attachmentArray as $attachment) {
                if (isset($options["bvk_comments_field_$attachment"])) {
                    $attachmentsString .= $attachment . ',';
                    $attachmentCount++;
                }
            }

            // If attachment count equal to length of all attachments, then
            // attach all
            if ($attachmentCount === count($attachmentArray) || empty($attachmentsString)) {
                $attachmentsString = '*';
            }

            $returnValue = "{limit: \"$widgetLimit\", attach: \"$attachmentsString\",";

            if (!empty($widgetMini)) {
                $returnValue .= "mini: \"$widgetMini\",";
            }

            if (!empty($widgetWidth)) {
                $returnValue .= "width: \"$widgetWidth\",";
            }

            if (!empty($widgetHeight)) {
                $returnValue .= "height: \"$widgetHeight\",";
            }

            if (!empty($widgetAutoPublish)) {
                $returnValue .= "autoPublish: \"$widgetAutoPublish\",";
            }

            if (!empty($widgetNoRealTime)) {
                $returnValue .= "norealtime: \"$widgetNoRealTime\",";
            }

            $returnValue .= "}";

            return $returnValue;
        }
    }

    $BVKComments = new BVKComments_Plugin();
}
