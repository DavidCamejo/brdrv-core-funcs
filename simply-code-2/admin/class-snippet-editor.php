<?php
/**
 * Class to handle snippet editing with multiple components
 */
class Simply_Code_Snippet_Editor {
    
    private $manager;
    
    public function __construct() {
        $this->manager = Simply_Code_Snippet_Manager::get_instance();
        add_action('admin_init', array($this, 'handle_save'));
    }
    
    /**
     * Render editor page with tabs for each component
     */
    public function render_editor($id = '') {
        $snippet = null;
        $is_new = empty($id);

        if (!$is_new) {
            // Hacer backup antes de editar
            $this->manager->backup_snippet_before_edit($id);
            // Cargar snippet con contenido real
            $snippet = $this->manager->get_snippet_for_editor($id);
        }

        if (!$is_new) {
            // ✅ USAR EL NUEVO MÉTODO QUE CARGA EL CONTENIDO REAL
            $snippet = $this->manager->get_snippet_for_editor($id);
        }
        
        $data = array(
            'id' => $is_new ? '' : $id,
            'title' => $is_new ? '' : (isset($snippet['title']) ? $snippet['title'] : $id),
            'description' => $is_new ? '' : (isset($snippet['description']) ? $snippet['description'] : ''),
            'components' => $is_new ? array(
                'php' => '',
                'js' => '',
                'css' => ''
            ) : (isset($snippet['components']) ? $snippet['components'] : array(
                'php' => '',
                'js' => '',
                'css' => ''
            )),
            'components_enabled' => $is_new ? array() : (isset($snippet['components_enabled']) ? $snippet['components_enabled'] : array()),
            'active' => $is_new ? false : (isset($snippet['active']) ? $snippet['active'] : false)
        );
        
        include SC_ADMIN_DIR . '/views/snippet-editor.php';
    }
    
    /**
     * Handle save action
     */
    public function handle_save() {
        if (!isset($_POST['sc_save_nonce']) || !wp_verify_nonce($_POST['sc_save_nonce'], 'sc_save_snippet')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'simply-code'));
        }
        
        $id = sanitize_file_name($_POST['snippet_id']);
        $title = sanitize_text_field($_POST['snippet_title']);
        $description = sanitize_textarea_field($_POST['snippet_description']);
        $active = isset($_POST['snippet_active']);
        
        // Get component contents
        $components = array();
        $components_enabled = array();
        
        $component_types = array('php', 'js', 'css');
        foreach ($component_types as $component) {
            $content = isset($_POST['snippet_' . $component . '_content']) ? stripslashes($_POST['snippet_' . $component . '_content']) : '';
            $enabled = isset($_POST['snippet_' . $component . '_enabled']);
            
            if ($enabled && !empty($content)) {
                $components[$component] = $content;
                $components_enabled[] = $component;
            } elseif (!empty($content)) {
                // Even if not explicitly enabled, save if there's content
                $components[$component] = $content;
                $components_enabled[] = $component;
            }
        }
        
        if (empty($id)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Please enter a snippet ID.', 'simply-code') . '</p></div>';
            });
            return;
        }
        
        $data = array(
            'title' => $title,
            'description' => $description,
            'components' => $components,
            'active' => $active
        );
        
        if ($this->manager->save_snippet($id, $data)) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'simply-code-editor',
                    'id' => $id,
                    'sc_message' => urlencode(__('Snippet saved successfully.', 'simply-code')),
                    'sc_message_type' => 'success'
                ),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error saving snippet.', 'simply-code') . '</p></div>';
            });
        }
    }

    /**
     * Backup snippet before editing
     */
    public function backup_snippet_before_edit($id) {
        $backup_dir = SC_STORAGE_PATH . '/backups/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $timestamp = date('Y-m-d-H-i-s');
        $backup_subdir = $backup_dir . $id . '/';
        
        if (!file_exists($backup_subdir)) {
            wp_mkdir_p($backup_subdir);
        }
        
        // Copy JSON metadata
        $source_json = SC_STORAGE_PATH . '/snippets/' . $id . '.json';
        $backup_json = $backup_subdir . $id . '-' . $timestamp . '.json';
        
        if (file_exists($source_json)) {
            copy($source_json, $backup_json);
        }
        
        // Copy component files
        $components = array('php', 'js', 'css');
        foreach ($components as $component) {
            $source_file = SC_STORAGE_PATH . '/snippets/' . $id . '.' . $component;
            $backup_file = $backup_subdir . $id . '-' . $timestamp . '.' . $component;
            
            if (file_exists($source_file)) {
                copy($source_file, $backup_file);
            }
        }
        
        return true;
    }
}
