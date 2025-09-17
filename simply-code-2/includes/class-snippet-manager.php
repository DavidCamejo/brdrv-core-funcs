<?php
/**
 * Class to manage code snippets with multiple components
 */
class Simply_Code_Snippet_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor privado para singleton
    }
    
    /**
     * Get all snippets
     */
    public function get_all_snippets() {
        $snippets = array();
        $snippets_dir = SC_STORAGE_PATH . '/snippets/';
        
        if (!file_exists($snippets_dir)) {
            return $snippets;
        }
        
        $files = scandir($snippets_dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            // Only process .json files that contain metadata
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $json_path = $snippets_dir . $file;
                $data = json_decode(file_get_contents($json_path), true);
                
                if ($data) {
                    $snippet_id = $data['id'];
                    
                    // Get all component contents
                    $components = array('php', 'js', 'css');
                    foreach ($components as $component) {
                        $content_file = $snippet_id . '.' . $component;
                        $content_path = $snippets_dir . $content_file;
                        
                        if (file_exists($content_path)) {
                            $data['components'][$component] = file_get_contents($content_path);
                        } else {
                            $data['components'][$component] = '';
                        }
                    }
                    
                    $snippets[$snippet_id] = $data;
                }
            }
        }
        
        return $snippets;
    }
    
    /**
     * Get snippet by ID
     */
    public function get_snippet($id) {
        $snippets = $this->get_all_snippets();
        return isset($snippets[$id]) ? $snippets[$id] : null;
    }
    
    /**
     * Get specific component content
     */
    public function get_component_content($snippet_id, $component) {
        $allowed_components = ['php', 'js', 'css'];
        if (!in_array($component, $allowed_components)) {
            return '';
        }
        
        $filename = sanitize_file_name($snippet_id) . '.' . $component;
        $filepath = SC_STORAGE_PATH . '/snippets/' . $filename;
        
        if (file_exists($filepath)) {
            return file_get_contents($filepath);
        }
        
        return '';
    }
    
    /**
     * Save snippet with all components
     */
    public function save_snippet($id, $data) {
        $snippets_dir = SC_STORAGE_PATH . '/snippets/';
        
        if (!file_exists($snippets_dir)) {
            wp_mkdir_p($snippets_dir);
        }
        
        // Save component files
        $components = array('php', 'js', 'css');
        foreach ($components as $component) {
            if (isset($data['components'][$component]) && !empty($data['components'][$component])) {
                $content_filename = $id . '.' . $component;
                $content_filepath = $snippets_dir . $content_filename;
                file_put_contents($content_filepath, $data['components'][$component]);
            } else {
                // Remove file if component is empty
                $content_filename = $id . '.' . $component;
                $content_filepath = $snippets_dir . $content_filename;
                if (file_exists($content_filepath)) {
                    unlink($content_filepath);
                }
            }
        }
        
        // Save metadata (only non-empty components)
        $metadata = array(
            'id' => $id,
            'title' => isset($data['title']) ? $data['title'] : $id,
            'description' => isset($data['description']) ? $data['description'] : '',
            'active' => isset($data['active']) ? (bool)$data['active'] : false,
            'components_enabled' => array(),
            'created' => isset($data['created']) ? $data['created'] : current_time('mysql'),
            'modified' => current_time('mysql')
        );
        
        // Track which components have content
        foreach ($components as $component) {
            if (isset($data['components'][$component]) && !empty($data['components'][$component])) {
                $metadata['components_enabled'][] = $component;
            }
        }
        
        $json_filename = $id . '.json';
        $json_filepath = $snippets_dir . $json_filename;
        file_put_contents($json_filepath, json_encode($metadata));
        
        return true;
    }
    
    /**
     * Delete snippet and all its components
     */
    public function delete_snippet($id) {
        $snippets_dir = SC_STORAGE_PATH . '/snippets/';
        
        // Find all files related to this snippet
        $files_to_delete = array();
        $files = scandir($snippets_dir);
        
        foreach ($files as $file) {
            if (strpos($file, $id . '.') === 0) {
                $files_to_delete[] = $snippets_dir . $file;
            }
        }
        
        $deleted = false;
        foreach ($files_to_delete as $file) {
            if (file_exists($file)) {
                unlink($file);
                $deleted = true;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Execute snippet components
     */
    public function execute_snippet($id) {
        $snippets = $this->get_all_snippets();
        
        if (!isset($snippets[$id])) {
            return false;
        }
        
        $snippet = $snippets[$id];
        
        if (!$snippet['active']) {
            return false;
        }
        
        // Execute PHP component
        if (in_array('php', $snippet['components_enabled']) && !empty($snippet['components']['php'])) {
            eval('?>' . $snippet['components']['php']);
        }
        
        // Enqueue JS component
        if (in_array('js', $snippet['components_enabled']) && !empty($snippet['components']['js'])) {
            add_action('wp_footer', function() use ($snippet) {
                echo '<script>' . $snippet['components']['js'] . '</script>';
            });
        }
        
        // Enqueue CSS component
        if (in_array('css', $snippet['components_enabled']) && !empty($snippet['components']['css'])) {
            add_action('wp_head', function() use ($snippet) {
                echo '<style>' . $snippet['components']['css'] . '</style>';
            });
        }
        
        return true;
    }
}
