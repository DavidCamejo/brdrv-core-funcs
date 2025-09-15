<?php
if (!defined('ABSPATH')) {
    exit;
}

class SnippetEditor {
    
    private $snippets_dir;
    private $js_dir;
    private $css_dir;
    private $config_file;
    
    public function __construct() {
        $this->snippets_dir = SC_PLUGIN_DIR . 'storage/snippets/';
        $this->js_dir = SC_PLUGIN_DIR . 'storage/js/';
        $this->css_dir = SC_PLUGIN_DIR . 'storage/css/';
        $this->config_file = $this->snippets_dir . 'snippets.json';
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_sc_code_snippet', array($this, 'save_snippet'));
    }
    
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        $screen = get_current_screen();
        if ($screen->post_type !== 'sc_code_snippet') {
            return;
        }
        
        wp_enqueue_style('sc-editor-style', SC_PLUGIN_URL . 'assets/css/editor.css', array(), '1.0.0');
        wp_enqueue_script('sc-editor-script', SC_PLUGIN_URL . 'assets/js/editor.js', array('jquery'), '1.0.0', true);
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'sc_code_editor',
            __('Code Editor', 'simply-code'),
            array($this, 'render_editor'),
            'sc_code_snippet',
            'normal',
            'high'
        );
    }
    
    public function render_editor($post) {
        wp_nonce_field('save_sc_snippet', 'sc_snippet_nonce');
        
        // Leer el código desde los archivos existentes
        $php_code = '';
        $js_code = '';
        $css_code = '';
        
        // Buscar en la estructura antigua
        $old_php_file = $this->snippets_dir . $post->ID . '.php';
        $old_js_file = $this->js_dir . $post->ID . '.js';
        $old_css_file = $this->css_dir . $post->ID . '.css';
        
        if (file_exists($old_php_file)) {
            $php_code = file_get_contents($old_php_file);
        }
        
        if (file_exists($old_js_file)) {
            $js_code = file_get_contents($old_js_file);
        }
        
        if (file_exists($old_css_file)) {
            $css_code = file_get_contents($old_css_file);
        }
        
        include SC_PLUGIN_DIR . 'admin/views/snippet-editor.php';
    }
    
    public function save_snippet($post_id) {
        if (!isset($_POST['sc_snippet_nonce']) || !wp_verify_nonce($_POST['sc_snippet_nonce'], 'save_sc_snippet')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Crear directorios si no existen
        if (!file_exists($this->snippets_dir)) {
            wp_mkdir_p($this->snippets_dir);
        }
        if (!file_exists($this->js_dir)) {
            wp_mkdir_p($this->js_dir);
        }
        if (!file_exists($this->css_dir)) {
            wp_mkdir_p($this->css_dir);
        }
        
        // Guardar código PHP y crear backup
        if (isset($_POST['sc_php_code'])) {
            $php_code = wp_unslash($_POST['sc_php_code']);
            $php_file = $this->snippets_dir . $post_id . '.php';
            file_put_contents($php_file, $php_code);
            
            // Crear backup de PHP
            $this->create_backup($post_id, 'php', $php_code);
        }
        
        // Guardar código JavaScript y crear backup
        if (isset($_POST['sc_js_code'])) {
            $js_code = wp_unslash($_POST['sc_js_code']);
            $js_file = $this->js_dir . $post_id . '.js';
            file_put_contents($js_file, $js_code);
            
            // Crear backup de JS
            $this->create_backup($post_id, 'js', $js_code);
        }
        
        // Guardar código CSS y crear backup
        if (isset($_POST['sc_css_code'])) {
            $css_code = wp_unslash($_POST['sc_css_code']);
            $css_file = $this->css_dir . $post_id . '.css';
            file_put_contents($css_file, $css_code);
            
            // Crear backup de CSS
            $this->create_backup($post_id, 'css', $css_code);
        }
        
        // Actualizar configuración JSON
        $this->update_config($post_id, $_POST['post_title']);
    }
    
    private function create_backup($snippet_id, $type, $code) {
        $backup_dir = SC_PLUGIN_DIR . 'storage/backups/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $filename = sprintf('snippet-%d-%s-%s.%s', 
            $snippet_id, 
            $type, 
            date('Y-m-d-H-i-s'), 
            $type
        );
        
        $filepath = $backup_dir . $filename;
        file_put_contents($filepath, $code);
        
        // Mantener solo los últimos 5 backups por tipo
        $this->cleanup_old_backups($snippet_id, $type);
    }
    
    private function cleanup_old_backups($snippet_id, $type) {
        $backup_dir = SC_PLUGIN_DIR . 'storage/backups/';
        
        if (!file_exists($backup_dir)) {
            return;
        }
        
        $pattern = sprintf('%ssnippet-%d-%s-*.%s', $backup_dir, $snippet_id, $type, $type);
        $files = glob($pattern);
        
        if (count($files) <= 5) {
            return;
        }
        
        // Ordenar por tiempo de modificación (más reciente primero)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Eliminar archivos más antiguos
        for ($i = 5; $i < count($files); $i++) {
            unlink($files[$i]);
        }
    }
    
    private function update_config($snippet_id, $title) {
        // Leer configuración existente
        $config = array();
        if (file_exists($this->config_file)) {
            $config_content = file_get_contents($this->config_file);
            $config = json_decode($config_content, true);
            if (!is_array($config)) {
                $config = array();
            }
        }
        
        // Actualizar o agregar snippet
        $config[$snippet_id] = array(
            'id' => $snippet_id,
            'title' => $title,
            'status' => 'active',
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s')
        );
        
        // Guardar configuración
        file_put_contents($this->config_file, json_encode($config, JSON_PRETTY_PRINT));
    }
}
