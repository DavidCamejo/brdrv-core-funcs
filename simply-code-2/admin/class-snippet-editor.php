<?php
/**
 * Snippet Editor Handler
 */

if (!defined('ABSPATH')) exit;

// Verificar que la clase no exista antes de declararla
if (!class_exists('Simply_Code_Snippet_Editor')) {

class Simply_Code_Snippet_Editor {
    
    public function __construct() {
        add_action('admin_post_save_snippet', [$this, 'handle_save']);
        add_action('admin_post_delete_snippet', [$this, 'handle_delete']);
        add_action('admin_post_activate_snippet', [$this, 'handle_activation']);
        add_action('admin_post_deactivate_snippet', [$this, 'handle_activation']);
    }
    
    public function render_editor($id = '') {
        $snippet = [];
        
        if ($id) {
            $manager = Simply_Code_Snippet_Manager::get_instance();
            $snippet = $manager->get_snippet($id);
            
            if ($snippet === false) {
                wp_die(__('Snippet not found.', 'simply-code'));
            }
            
            // Asegurar que todos los campos existan
            $snippet = wp_parse_args($snippet, [
                'id' => $id,
                'name' => '',
                'description' => '',
                'php' => '',
                'js' => '',
                'css' => '',
                'active' => 1
            ]);
        }
        
        // Verificar que el archivo de vista exista
        $view_file = SC_ADMIN_DIR . '/views/snippet-editor.php';
        if (!file_exists($view_file)) {
            wp_die(__('Editor view file not found.', 'simply-code'));
        }
        
        // Pasar los datos del snippet a la vista
        include_once $view_file;
    }
    
    public function handle_save() {
        // Verificar nonce
        if (!isset($_POST['sc_nonce']) || !wp_verify_nonce($_POST['sc_nonce'], 'save_snippet')) {
            wp_die(__('Security check failed.', 'simply-code'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'simply-code'));
        }
        
        $manager = Simply_Code_Snippet_Manager::get_instance();
        
        $data = [
            'id' => sanitize_file_name(isset($_POST['snippet_id']) ? $_POST['snippet_id'] : uniqid()),
            'name' => sanitize_text_field(isset($_POST['snippet_name']) ? $_POST['snippet_name'] : ''),
            'description' => sanitize_textarea_field(isset($_POST['snippet_description']) ? $_POST['snippet_description'] : ''),
            'php' => isset($_POST['php_code']) ? wp_unslash($_POST['php_code']) : '',
            'js' => isset($_POST['js_code']) ? wp_unslash($_POST['js_code']) : '',
            'css' => isset($_POST['css_code']) ? wp_unslash($_POST['css_code']) : '',
            'active' => isset($_POST['snippet_active']) ? 1 : 0
        ];
        
        // Validar nombre
        if (empty($data['name'])) {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code-editor'), 'error', __('Name is required.', 'simply-code'));
            return;
        }
        
        // Guardar snippet
        $result = $manager->save_snippet($data);
        
        if ($result) {
            $redirect_url = add_query_arg([
                'page' => 'simply-code-editor',
                'action' => 'edit',
                'id' => $result
            ], admin_url('admin.php'));
            
            $this->redirect_with_message($redirect_url, 'success', __('Snippet saved successfully.', 'simply-code'));
        } else {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code-editor'), 'error', __('Error saving snippet.', 'simply-code'));
        }
    }
    
    public function handle_delete() {
        // Verificar nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_snippet')) {
            wp_die(__('Security check failed.', 'simply-code'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'simply-code'));
        }
        
        $id = sanitize_file_name(isset($_GET['id']) ? $_GET['id'] : '');
        
        if (empty($id)) {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code'), 'error', __('Invalid snippet ID.', 'simply-code'));
            return;
        }
        
        $manager = Simply_Code_Snippet_Manager::get_instance();
        $result = $manager->delete_snippet($id);
        
        if ($result) {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code'), 'success', __('Snippet deleted successfully.', 'simply-code'));
        } else {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code'), 'error', __('Error deleting snippet.', 'simply-code'));
        }
    }
    
    public function handle_activation() {
        // Verificar nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'toggle_snippet')) {
            wp_die(__('Security check failed.', 'simply-code'));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'simply-code'));
        }
        
        $id = sanitize_file_name(isset($_GET['id']) ? $_GET['id'] : '');
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        
        if (empty($id)) {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code'), 'error', __('Invalid snippet ID.', 'simply-code'));
            return;
        }
        
        $manager = Simply_Code_Snippet_Manager::get_instance();
        $activate = ($action === 'activate_snippet');
        $result = $manager->activate_snippet($id, $activate);
        
        if ($result) {
            $message = $activate ? __('Snippet activated.', 'simply-code') : __('Snippet deactivated.', 'simply-code');
            $this->redirect_with_message(admin_url('admin.php?page=simply-code'), 'success', $message);
        } else {
            $this->redirect_with_message(admin_url('admin.php?page=simply-code'), 'error', __('Error updating snippet status.', 'simply-code'));
        }
    }
    
    private function redirect_with_message($url, $type, $message) {
        $redirect_url = add_query_arg([
            'sc_message' => urlencode($message),
            'sc_message_type' => $type
        ], $url);
        
        wp_redirect($redirect_url);
        exit;
    }
}

} // End class_exists check
