<?php
/**
 * Plugin Name: Simply Code
 * Plugin URI: https://github.com/DavidCamejo/simply-code
 * Description: A minimalist plugin to run custom code snippets using JSON files instead of database.
 * Version: 1.2.5
 * Author: David Camejo
 * Text Domain: simply-code
 */

if (!defined('ABSPATH')) exit;

// Define constants
define('SC_VERSION', '1.2.5');
define('SC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SC_ADMIN_DIR', SC_PLUGIN_DIR . 'admin');
define('SC_INCLUDES_DIR', SC_PLUGIN_DIR . 'includes');
define('SC_ASSETS_DIR', SC_PLUGIN_DIR . 'assets');
define('SC_STORAGE_PATH', SC_PLUGIN_DIR . 'storage');

// Autoload classes
function simply_code_autoload($class) {
    $prefix = 'Simply_Code_';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $relative_class = substr($class, strlen($prefix));
    
    // Try includes directory first
    $file = SC_INCLUDES_DIR . '/class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
    
    // Try admin directory
    $file = SC_ADMIN_DIR . '/class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
}

spl_autoload_register('simply_code_autoload');

class Simply_Code {
    
    private static $instance = null;
    private $snippet_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        add_action('wp_loaded', [$this, 'execute_active_snippets']);
    }
    
    public function init() {
        load_plugin_textdomain('simply-code', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_public_assets() {
        // Base styles for inline CSS
        wp_register_style('simply-code-styles', false);
        wp_enqueue_style('simply-code-styles');
        
        // jQuery for inline JS
        wp_enqueue_script('jquery');
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'simply-code') === false) {
            return;
        }
        
        wp_enqueue_style('simply-code-admin', SC_PLUGIN_URL . 'assets/css/editor.css', [], SC_VERSION);
        wp_enqueue_script('simply-code-admin', SC_PLUGIN_URL . 'assets/js/editor.js', ['jquery'], SC_VERSION, true);
        
        wp_localize_script('simply-code-admin', 'simplyCodeEditor', [
            'confirmDelete' => __('Are you sure you want to delete this snippet?', 'simply-code'),
            'nameRequired' => __('Please enter a name for the snippet.', 'simply-code')
        ]);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Simply Code', 'simply-code'),
            __('Simply Code', 'simply-code'),
            'manage_options',
            'simply-code',
            [$this, 'render_admin_page'],
            'dashicons-editor-code'
        );
        
        add_submenu_page(
            'simply-code',
            __('All Snippets', 'simply-code'),
            __('All Snippets', 'simply-code'),
            'manage_options',
            'simply-code',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'simply-code',
            __('Add New', 'simply-code'),
            __('Add New', 'simply-code'),
            'manage_options',
            'simply-code-editor',
            [$this, 'render_editor_page']
        );
    }
    
    public function render_admin_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $id = isset($_GET['id']) ? sanitize_file_name($_GET['id']) : '';
        
        switch ($action) {
            case 'edit':
                $this->render_editor_page($id);
                break;
            case 'delete':
                $this->handle_delete_snippet();
                $this->render_snippets_list();
                break;
            default:
                $this->render_snippets_list();
                break;
        }
    }
    
    public function render_editor_page($id = '') {
        $editor = new Simply_Code_Snippet_Editor();
        $editor->render_editor($id);
    }
    
    public function render_snippets_list() {
        // Verificar que el archivo exista
        $view_file = SC_ADMIN_DIR . '/views/snippets-list.php';
        if (!file_exists($view_file)) {
            echo '<div class="wrap"><h1>' . __('Simply Code', 'simply-code') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Snippets list view file not found.', 'simply-code') . '</p></div>';
            echo '</div>';
            return;
        }
        
        // Initialize manager and get snippets
        $manager = Simply_Code_Snippet_Manager::get_instance();
        $snippets = $manager->get_all_snippets();
        
        // Pasar datos a la vista
        include_once $view_file;
    }
    
    public function handle_delete_snippet() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sc_delete_snippet_' . $_GET['id'])) {
            wp_die(__('Permission denied.', 'simply-code'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'simply-code'));
        }
        
        $id = sanitize_file_name($_GET['id']);
        $manager = Simply_Code_Snippet_Manager::get_instance();
        
        if ($manager->delete_snippet($id)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Snippet deleted successfully.', 'simply-code') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error deleting snippet.', 'simply-code') . '</p></div>';
            });
        }
    }
    
    public function handle_admin_actions() {
        // Handle messages
        if (isset($_GET['sc_message'])) {
            add_action('admin_notices', [$this, 'display_admin_message']);
        }
    }
    
    public function display_admin_message() {
        $message = isset($_GET['sc_message']) ? urldecode($_GET['sc_message']) : '';
        $type = isset($_GET['sc_message_type']) ? sanitize_text_field($_GET['sc_message_type']) : 'info';
        
        if (!empty($message)) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        }
    }
    
    public function execute_active_snippets() {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }
        
        // Initialize manager
        $manager = Simply_Code_Snippet_Manager::get_instance();
        $snippets = $manager->get_all_snippets();
        
        foreach ($snippets as $id => $snippet) {
            if (isset($snippet['active']) && $snippet['active']) {
                $manager->execute_snippet($id);
            }
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    Simply_Code::get_instance();
}, 10);

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create directories
    $dirs = [
        SC_STORAGE_PATH . '/snippets',
        SC_STORAGE_PATH . '/js',
        SC_STORAGE_PATH . '/css',
        SC_STORAGE_PATH . '/backups'
    ];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
});
