<?php
/**
 * Plugin Name: Simply Code
 * Plugin URI: https://github.com/DavidCamejo/simply-code
 * Description: A minimalist plugin to run custom code snippets.
 * Version: 3.6.0
 * Author: David Camejo & AI
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simply-code
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SC_PLUGIN_DIR . 'admin/class-snippet-editor.php';
require_once SC_PLUGIN_DIR . 'includes/class-snippet-manager.php';

class SimplyCode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_head', array('SnippetManager', 'output_css'));
        add_action('wp_footer', array('SnippetManager', 'output_js'));
        add_action('wp', array('SnippetManager', 'execute_php'));
    }
    
    public function init() {
        $this->register_post_type();
        new SnippetEditor();
    }
    
    private function register_post_type() {
        $labels = array(
            'name'                  => _x('Code Snippets', 'Post type general name', 'simply-code'),
            'singular_name'         => _x('Code Snippet', 'Post type singular name', 'simply-code'),
            'menu_name'             => _x('Simply Code', 'Admin Menu text', 'simply-code'),
            'name_admin_bar'        => _x('Code Snippet', 'Add New on Toolbar', 'simply-code'),
            'add_new'               => __('Add New', 'simply-code'),
            'add_new_item'          => __('Add New Code Snippet', 'simply-code'),
            'new_item'              => __('New Code Snippet', 'simply-code'),
            'edit_item'             => __('Edit Code Snippet', 'simply-code'),
            'view_item'             => __('View Code Snippet', 'simply-code'),
            'all_items'             => __('All Code Snippets', 'simply-code'),
            'search_items'          => __('Search Code Snippets', 'simply-code'),
            'parent_item_colon'     => __('Parent Code Snippets:', 'simply-code'),
            'not_found'             => __('No code snippets found.', 'simply-code'),
            'not_found_in_trash'    => __('No code snippets found in Trash.', 'simply-code'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => array('slug' => 'code-snippet'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-editor-code',
            'supports'           => array('title'),
        );

        register_post_type('sc_code_snippet', $args);
    }
}

SimplyCode::get_instance();
