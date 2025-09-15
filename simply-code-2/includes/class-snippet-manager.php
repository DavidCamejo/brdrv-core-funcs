<?php
/**
 * Simply Code Snippet Manager
 */

if (!defined('ABSPATH')) exit;

class Simply_Code_Snippet_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_directories();
    }
    
    private function init_directories() {
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
    }
    
    public function get_snippet($id) {
        $file = SC_STORAGE_DIR . "/snippets/{$id}.json";
        if (!file_exists($file)) {
            return false;
        }
        
        $content = file_get_contents($file);
        if (!$content) {
            return false;
        }
        
        $data = json_decode($content, true);
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            error_log("Simply Code: Error parsing JSON for snippet {$id}: " . json_last_error_msg());
            return false;
        }
        
        $data['id'] = $id;
        return $data;
    }
    
    public function get_all_snippets() {
        $snippets = [];
        $files = glob(SC_STORAGE_DIR . '/snippets/*.json');
        
        if (!$files) {
            return $snippets;
        }
        
        foreach ($files as $file) {
            $id = basename($file, '.json');
            $snippet = $this->get_snippet($id);
            if ($snippet) {
                $snippets[$id] = $snippet;
            }
        }
        
        return $snippets;
    }
    
    public function save_snippet($data) {
        $id = sanitize_file_name($data['id'] ?? uniqid());
        $file = SC_STORAGE_DIR . "/snippets/{$id}.json";
        
        // Si existe y estamos actualizando, hacer backup
        if (file_exists($file)) {
            $existing_content = file_get_contents($file);
            if ($existing_content) {
                $existing_data = json_decode($existing_content, true);
                if ($existing_data && json_last_error() === JSON_ERROR_NONE) {
                    $this->create_backups($id, $existing_data);
                }
            }
        }
        
        // Preparar datos para guardar
        $snippet_data = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'php' => isset($data['php']) ? $data['php'] : '',
            'js' => isset($data['js']) ? $data['js'] : '',
            'css' => isset($data['css']) ? $data['css'] : '',
            'active' => isset($data['active']) ? 1 : 0,
            'created_at' => $existing_data['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // Guardar snippet
        $json_content = wp_json_encode($snippet_data, JSON_PRETTY_PRINT);
        if (!$json_content) {
            error_log("Simply Code: Error encoding JSON for snippet {$id}");
            return false;
        }
        
        $result = file_put_contents($file, $json_content);
        
        // Guardar archivos separados si es necesario
        if (!empty($data['js'])) {
            file_put_contents(SC_STORAGE_DIR . "/js/{$id}.js", $data['js']);
        } else {
            // Eliminar archivo JS si ya no hay contenido
            $js_file = SC_STORAGE_DIR . "/js/{$id}.js";
            if (file_exists($js_file)) {
                unlink($js_file);
            }
        }
        
        if (!empty($data['css'])) {
            file_put_contents(SC_STORAGE_DIR . "/css/{$id}.css", $data['css']);
        } else {
            // Eliminar archivo CSS si ya no hay contenido
            $css_file = SC_STORAGE_DIR . "/css/{$id}.css";
            if (file_exists($css_file)) {
                unlink($css_file);
            }
        }
        
        return $result !== false ? $id : false;
    }
    
    /**
     * Crear backups de PHP, JS y CSS
     */
    private function create_backups($id, $existing_data) {
        $backup_dir = SC_STORAGE_DIR . '/backups';
        $timestamp = time();
        
        // Backup de PHP
        if (!empty($existing_data['php'])) {
            $backup_file = "{$backup_dir}/{$id}_php_backup_{$timestamp}.txt";
            $result = file_put_contents($backup_file, $existing_data['php']);
            if ($result === false) {
                error_log("Simply Code: Error creating PHP backup for {$id}");
            }
        }
        
        // Backup de JS
        if (!empty($existing_data['js'])) {
            $backup_file = "{$backup_dir}/{$id}_js_backup_{$timestamp}.txt";
            $result = file_put_contents($backup_file, $existing_data['js']);
            if ($result === false) {
                error_log("Simply Code: Error creating JS backup for {$id}");
            }
        }
        
        // Backup de CSS
        if (!empty($existing_data['css'])) {
            $backup_file = "{$backup_dir}/{$id}_css_backup_{$timestamp}.txt";
            $result = file_put_contents($backup_file, $existing_data['css']);
            if ($result === false) {
                error_log("Simply Code: Error creating CSS backup for {$id}");
            }
        }
    }
    
    public function delete_snippet($id) {
        $files_to_delete = [
            SC_STORAGE_DIR . "/snippets/{$id}.json",
            SC_STORAGE_DIR . "/js/{$id}.js",
            SC_STORAGE_DIR . "/css/{$id}.css"
        ];
        
        foreach ($files_to_delete as $file) {
            if (file_exists($file)) {
                if (!unlink($file)) {
                    error_log("Simply Code: Error deleting file {$file}");
                }
            }
        }
        
        return true;
    }
    
    public function activate_snippet($id, $activate = true) {
        $file = SC_STORAGE_DIR . "/snippets/{$id}.json";
        if (!file_exists($file)) {
            return false;
        }
        
        $content = file_get_contents($file);
        if (!$content) {
            return false;
        }
        
        $data = json_decode($content, true);
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        $data['active'] = $activate ? 1 : 0;
        
        $json_content = wp_json_encode($data, JSON_PRETTY_PRINT);
        if (!$json_content) {
            return false;
        }
        
        return file_put_contents($file, $json_content) !== false;
    }
    
    public function execute_snippet($id) {
        $snippet = $this->get_snippet($id);
        if (!$snippet || !$snippet['active']) {
            return;
        }
        
        // Ejecutar PHP con manejo de errores
        if (!empty($snippet['php'])) {
            try {
                eval($snippet['php']);
            } catch (Exception $e) {
                error_log("Simply Code: PHP execution error in snippet {$id}: " . $e->getMessage());
            }
        }
        
        // Encolar JS
        if (!empty($snippet['js'])) {
            wp_add_inline_script('jquery', $snippet['js']);
        }
        
        // Encolar CSS
        if (!empty($snippet['css'])) {
            wp_add_inline_style('wp-admin', $snippet['css']);
        }
    }
    
    public function get_backups($id) {
        $backups = [];
        $pattern = SC_STORAGE_DIR . "/backups/{$id}_*_*.*";
        $files = glob($pattern);
        
        if (!$files) {
            return $backups;
        }
        
        foreach ($files as $file) {
            $filename = basename($file);
            preg_match('/_(php|js|css)_backup_(\d+)\.(txt|js|css)$/', $filename, $matches);
            
            if (!empty($matches)) {
                $type = $matches[1];
                $timestamp = (int)$matches[2];
                
                $backups[] = [
                    'file' => $file,
                    'type' => strtoupper($type),
                    'timestamp' => $timestamp,
                    'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
                    'size' => size_format(filesize($file)),
                    'filename' => $filename
                ];
            }
        }
        
        // Ordenar por fecha descendente
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
}
