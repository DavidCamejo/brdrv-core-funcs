<?php
/**
 * Clase para manejar las páginas de administración
 */
class Simply_Code_Admin_Page {
    
    private $editor;
    private $manager;
    
    public function __construct() {
        $this->editor = new Simply_Code_Snippet_Editor();
        $this->manager = new Simply_Code_Snippet_Manager();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Simply Code',
            'Simply Code',
            'manage_options',
            'simply-code-snippets',
            array($this, 'snippets_page'),
            'dashicons-editor-code'
        );
    }
    
    /**
     * Página principal de snippets
     */
    public function snippets_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'edit':
            case 'new':
                $this->editor->display_editor();
                break;
                
            case 'delete':
                $this->handle_delete();
                // Continuar mostrando la lista después de eliminar
                $this->display_snippets_list();
                break;
                
            default:
                $this->display_snippets_list();
                break;
        }
    }
    
    /**
     * Mostrar lista de snippets
     */
    private function display_snippets_list() {
        $snippets = $this->manager->get_all_snippets();
        include SC_PLUGIN_DIR . 'admin/views/snippets-list.php';
    }
    
    /**
     * Manejar eliminación de snippets
     */
    private function handle_delete() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_snippet')) {
            wp_die(__('Permiso denegado.'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.'));
        }
        
        $snippet_name = sanitize_file_name($_GET['snippet']);
        $snippet_type = isset($_GET['type']) ? $_GET['type'] : 'php';
        
        if ($this->manager->delete_snippet($snippet_name, $snippet_type)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Snippet eliminado correctamente.') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al eliminar el snippet.') . '</p></div>';
            });
        }
    }
    
    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_simply-code-snippets') {
            return;
        }
        
        wp_enqueue_style('sc-editor-css', SC_PLUGIN_URL . 'assets/css/editor.css');
        wp_enqueue_script('sc-editor-js', SC_PLUGIN_URL . 'assets/js/editor.js', array('jquery'), '1.0', true);
    }
}
