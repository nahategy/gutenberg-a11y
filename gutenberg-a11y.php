<?php
/**
 * Plugin Name: Gutenberg A11y
 * Plugin URI: https://example.com/
 * Description: Check Accessibility of Pages made with the Gutenberg editor
 * Version:     0.1
 * Author:      University Of Pannonia
 * Author URI:  https://mik.uni-pannon.hu/en
 * Text Domain: gtnbrga11y
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

final class GutenbergA11y
{
    const BADGE_BUTTON = 'off';
    const PLUGIN_VERSION = "0.1";
    private static $instance = null;
    private $js_added = false;
    private $settings;
    protected $options;

    public static function instance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->includes();
        $this->settings = new WSC_Settings(
            __('GutenbergA11y', 'gutenberga11y'),
            __('GutenbergA11y', 'gutenberga11y'),
            'gutenberg-a11y-settings'
        );

        $this->options = !empty(get_option(WSC_Settings::OPTION_NAME)) ? get_option(WSC_Settings::OPTION_NAME) : array();

        if (empty($this->options)) {
            //set default setting
            $this->options['enable_on_posts'] = 'on';
            $this->options['enable_on_pages'] = 'on';
            $this->options['enable_on_products'] = 'off';
            $this->options['enable_on_categories'] = 'on';
            $this->options['enable_on_tags'] = 'on';
            update_option('wsc_proofreader_version', self::PLUGIN_VERSION);
        }


        add_action('admin_enqueue_scripts', array($this, 'register_proofreader_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        add_action('admin_head', array($this, 'init_proofreader'));

        $this->check_version();

        add_action('wp_ajax_get_proofreader_info_callback', array($this, 'get_proofreader_info_callback'));
        do_action('wsc_loaded');
    }

    public function check_version()
    {
        //todo add Class for upgrade plugin
        if (get_option('wsc_proofreader_version') !== self::PLUGIN_VERSION) {
            //Clear old options in versions below 2
            delete_option('wsc');
            update_option('wsc_proofreader_version', self::PLUGIN_VERSION);
        }
    }

    public function init_proofreader()
    {
        $editable_post_type = $this->get_editable_post_type();
        $screen = get_current_screen();
        if ($screen->base === 'settings_page_gutenberg-a11y-settings') {
            $this->api_proofreader_info();
        }

        foreach ($this->options as $option => $on) {
            if ($option === 'enable_on_categories' && $on === 'on') {
                if ($screen->id === 'edit-category'
                    || $screen->id === 'edit-product_cat'
                    || $screen->id === 'edit-wpsc_product_category') {
                    $this->init_proofreader_js();
                }
            }
        }

        foreach ($this->options as $option => $on) {
            if ($option === 'enable_on_tags' && $on === 'on') {
                if ($screen->id === 'edit-product_tag'
                    || $screen->id === 'edit-post_tag') {
                    $this->init_proofreader_js();
                }
            }
        }

        if (false !== $editable_post_type) {

            foreach ($this->options as $option => $on) {

                if ($option === 'enable_on_posts' && $on === 'on') {
                    if (0 === strcasecmp('post', $editable_post_type)) {

                        $this->init_proofreader_js();
                    }
                    break;
                }
            }

            foreach ($this->options as $option => $on) {

                if ($option === 'enable_on_pages' && $on === 'on') {

                    if (0 === strcasecmp('page', $editable_post_type)) {
                        $this->init_proofreader_js();
                    }
                    break;
                }
            }

            foreach ($this->options as $option => $on) {

                if ($option === 'enable_on_products' && $on === 'on') {
                    if ($screen->id === 'wpsc-product') {
                        $this->init_proofreader_js();
                    }

                    if ($screen->id === 'edit-product_cat') {
                        $this->init_proofreader_js();
                    }
                    if ($screen->id === 'edit-product_tag') {

                        $this->init_proofreader_js();
                    }
                    if (0 === strcasecmp('product', $editable_post_type)) {
                        $this->init_proofreader_js();
                    }

                    break;

                }

            }

            $additionalCPT = apply_filters('wproofreader_add_cpt', $CPT = array());

            if (!empty($additionalCPT)) {

                foreach ($additionalCPT as $key => $value) {
                    if (0 === strcasecmp($value, $editable_post_type)) {
                        $this->init_proofreader_js();
                    }
                }

            }

            return $this->js_added = true;
        }

        return $this->js_added = false;
    }

    public function includes()
    {
        require_once dirname(__FILE__) . '/vendor/class.settings-api.php';
        require_once dirname(__FILE__) . '/includes/class-wsc-settings.php';
    }

    public function get_editable_post_type()
    {
        $screen = get_current_screen();

        if ($screen->post_type === $screen->id) {
            $post_type = $screen->post_type;

            return $post_type;
        }

        return false;
    }

    function add_action_links($links)
    {
        $mylinks = array(
//            '<a href="' . admin_url('options-general.php?page=gutenberg-a11y-settings') . '">' . __('Settings', 'gutenberga11y') . '</a>',
        );

        return array_merge($links, $mylinks);
    }

    function register_proofreader_scripts()
    {
        wp_register_script('ProofreaderInstance', plugin_dir_url(__FILE__) . '/assets/instance.js', null, '171220181251', true);
    }

    function init_proofreader_js()
    {
        $badge_button_optinon = ($this->get_badge_button_optinon() === self::BADGE_BUTTON) ? 'true' : 'false';
        $wsc_proofreader_config = array(
            'enableGrammar' => 'false',
            'disableBadgeButton' => $badge_button_optinon,
        );
        wp_enqueue_script('ProofreaderInstance');
    }

    public function get_option_example()
    {
        return $this->get_option('example', 'default');
    }

    public function get_badge_button_optinon()
    {
        $badge_button_optinon = $this->get_option('disable_badge_button', self::BADGE_BUTTON);

        return $badge_button_optinon;
    }

    public function get_option($name, $default = '')
    {
        return (isset($this->options[$name])) ? $this->options[$name] : $default;
    }

    function api_proofreader_info()
    {
        $ajax_nonce = wp_create_nonce("gutenberg-a11y-pl");
        $wsc_proofreader_config = array(
            'ajax_nonce' => $ajax_nonce,
        );
        wp_enqueue_script('ProofreaderInstance');
        wp_localize_script('ProofreaderInstance', 'ProofreaderInstance', $wsc_proofreader_config);
    }


    function get_proofreader_info_callback()
    {
        check_ajax_referer('gutenberg-a11y-pl', 'security');
        $proofreader_info = $_POST['getInfoResult'];
        update_option('wsc_proofreader_info', $proofreader_info);
        ob_start();
        ?>
      <select class="regular" name="wsc_proofreader[slang]" id="wsc_proofreader[slang]">
          <?php foreach ($proofreader_info['langList']['ltr'] as $key => $value): ?>
            <option <?php if ($key === $key) {
                echo 'selected';
            } ?> value="<?php echo $key; ?>"><?php echo $value; ?></option>
          <?php endforeach; ?>
      </select>
        <?php
        wp_send_json(ob_get_clean());
        wp_die();
    }

    public static function fix_for_gutenberg()
    {
        add_action('wp_insert_post_data', function ($data, $postarr) {
            if ('publish' == $data['post_status']) {
                $string = $data['post_content'];
                $new_string = preg_replace('#(<span class=."wsc-spelling-problem." .*?>)(.*?)(</span>)#', '$2', $string);
                $new_string = preg_replace('#(<span class=."wsc-grammar-problem." .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class=."rangySelectionBoundary." .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class="wsc-spelling-problem".*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class="wsc-grammar-problem" .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class="rangySelectionBoundary" .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class=..rangySelectionBoundary.. .*?>)(.*?)(</span>)#', '$2', $new_string);
                $data['post_content'] = $new_string;
            }

            return $data;
        }, 100, 2);
    }
}

function WSC()
{
    return GutenbergA11y::instance();
}

if (is_admin()) {
    WSC();
}
/**
 * fix for Gutenberg
 * todo write more cleaner
 */
if (true === is_gutenberg_active()) {
    GutenbergA11y::fix_for_gutenberg();
};

function is_gutenberg_active()
{
    $gutenberg = false;
    $block_editor = false;

    if (has_filter('replace_editor', 'gutenberg_init')) {
        $gutenberg = true;
    }

    if (version_compare($GLOBALS['wp_version'], '5.0', '>')) {
        $block_editor = true;
    }

    if (!$gutenberg && !$block_editor) {
        return false;
    }

    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active('classic-editor/classic-editor.php')) {
        return true;
    }

    return (get_option('classic-editor-replace') === 'no-replace');
}
