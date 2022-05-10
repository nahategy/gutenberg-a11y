<?php
/**
 * Plugin Name: Gutenberg Accessibility Checker
 * Description: The plugin allows you to check and improve the content entered in the WordPress Gutenberg block editor based on the recommendations of the Web Content Accessibility Guidelines (WCAG).
 * Version:     1.0
 * Author:      DCS, University of Pannonia
 * Author URI:  https://mik.uni-pannon.hu/en
 * Text Domain: gutenberga11y
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
    protected $lang = 'en-US';

    public static function instance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

        add_action('admin_enqueue_scripts', array($this, 'register_gutenberga11y_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        add_action('admin_head', array($this, 'init_a11y_checker'));

        $this->check_version();

        do_action('gta11y_loaded');
    }

    public function check_version()
    {
        //todo add Class for upgrade plugin
        if (get_option('gta11y_version') !== self::PLUGIN_VERSION) {
            //Clear old options in versions below 2
            delete_option('gta11y');
            update_option('gta11y_version', self::PLUGIN_VERSION);
        }
    }

    public static function is_gutenberg_active()
    {
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
    }

    public function init_a11y_checker()
    {
        $this->init_a11y_checker_js();
        return $this->js_added = false;
    }


    function add_action_links($links)
    {
        $mylinks = array(//            '<a href="' . admin_url('options-general.php?page=gutenberg-a11y-settings') . '">' . __('Settings', 'gutenberga11y') . '</a>',
        );

        return array_merge($links, $mylinks);
    }

    function register_gutenberga11y_scripts()
    {
        wp_register_script('GutenbergA11YInstance', plugin_dir_url(__FILE__) . '/assets/instance.js', null, '171220181251', true);
    }

    function init_a11y_checker_js()
    {
        wp_enqueue_script('GutenbergA11YInstance');
        $this->lang_code = preg_split('/,/', $this->lang)[0];

        $object = array(
            'language' => $this->lang,
            'language_code' => $this->lang_code,
            'plugin_url' => plugins_url() . '/gutenberg-a11y',
        );
        wp_localize_script('GutenbergA11YInstance', 'gutenberA11yConfig', $object);
    }

    public static function fix_for_gutenberg()
    {
        add_action('wp_insert_post_data', function ($data, $postarr) {
            if ('publish' == $data['post_status']) {
                $string = $data['post_content'];
                $new_string = preg_replace('#(<span class=."gta11y-spelling-problem." .*?>)(.*?)(</span>)#', '$2', $string);
                $new_string = preg_replace('#(<span class=."gta11y-grammar-problem." .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class=."rangySelectionBoundary." .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class="gta11y-spelling-problem".*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class="gta11y-grammar-problem" .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class="rangySelectionBoundary" .*?>)(.*?)(</span>)#', '$2', $new_string);
                $new_string = preg_replace('#(<span class=..rangySelectionBoundary.. .*?>)(.*?)(</span>)#', '$2', $new_string);
                $data['post_content'] = $new_string;
            }

            return $data;
        }, 100, 2);
    }
}

if (is_admin()) {
    GutenbergA11y::instance();
}

if (true === GutenbergA11y::is_gutenberg_active()) {
    GutenbergA11y::fix_for_gutenberg();
};

