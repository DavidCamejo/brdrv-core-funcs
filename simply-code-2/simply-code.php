<?php
/**
 * Plugin Name: Simply Code
 * Plugin URI: https://github.com/DavidCamejo/simply-code
 * Description: A minimalist plugin to run custom code snippets using JSON files instead of database.
 * Version: 1.2.1
 * Author: David Camejo
 * Text Domain: simply-code
 */

if (!defined('ABSPATH')) exit;

// Define constants
define('SC_VERSION', '1.2.1');
define('SC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SC_ADMIN_DIR', SC_PLUGIN_DIR . 'admin');
define('SC_INCLUDES_DIR', SC_PLUGIN_DIR . 'includes');
define('SC_ASSETS_DIR', SC_PLUGIN_DIR . 'assets');
define('SC_STORAGE_DIR', SC_PLUGIN_DIR . 'storage');
define('SC_TEMPLATES_DIR', SC_PLUGIN_DIR . 'templates');

// Autoload classes
spl_autoload_register(function($class) {
    $prefix = 'Simply_Code_';
    $base_dir = SC_INCLUDES_DIR . '/';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Admin classes
if (is_admin()) {
    spl_autoload_register(function($class) {
        $prefix = 'Simply_Code_';
        $base_dir = SC_ADMIN_DIR . '/';
        
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

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
        $this->snippet_manager = Simply_Code_Snippet_Manager::get_instance();
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
            'nameRequired' => __('Please enter a name for the snippet.', 'simply-code'),
            'enableSyntax' => __('Enable Syntax Highlighting', 'simply-code'),
            'disableSyntax' => __('Disable Syntax Highlighting', 'simply-code')
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
        $action = $_GET['action'] ?? '';
        $id = $_GET['id'] ?? '';
        
        switch ($action) {
            case 'edit':
                $this->render_editor_page($id);
                break;
            case 'view-backups':
                $this->render_backups_page($id);
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
        $manager = Simply_Code_Snippet_Manager::get_instance();
        $snippets = $manager->get_all_snippets();
        
        include_once SC_ADMIN_DIR . '/views/snippets-list.php';
    }
    
    public function render_backups_page($id) {
        $manager = Simply_Code_Snippet_Manager::get_instance();
        $snippet = $manager->get_snippet($id);
        $backups = $manager->get_backups($id);
        
        if (!$snippet) {
            wp_die(__('Snippet not found.', 'simply-code'));
        }
        
        // Incluir un archivo de backups si existe, o mostrar mensaje
        $backups_file = SC_ADMIN_DIR . '/views/backups-list.php';
        if (file_exists($backups_file)) {
            include_once $backups_file;
        } else {
            echo '<div class="wrap"><h1>' . __('Backups', 'simply-code') . '</h1>';
            echo '<p>' . __('Backup functionality will be available in future versions.', 'simply-code') . '</p>';
            echo '<a href="' . admin_url('admin.php?page=simply-code') . '" class="button button-secondary">' . __('Back to Snippets', 'simply-code') . '</a>';
            echo '</div>';
        }
    }
    
    public function handle_admin_actions() {
        // Handle messages
        if (isset($_GET['sc_message'])) {
            add_action('admin_notices', [$this, 'display_admin_message']);
        }
    }
    
    public function display_admin_message() {
        $message = urldecode($_GET['sc_message']);
        $type = $_GET['sc_message_type'] ?? 'info';
        
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
    
    public function execute_active_snippets() {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }
        
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
Simply_Code::get_instance();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create directories
    $dirs = [
        SC_STORAGE_DIR . '/snippets',
        SC_STORAGE_DIR . '/js',
        SC_STORAGE_DIR . '/css',
        SC_STORAGE_DIR . '/backups'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
    }
});
