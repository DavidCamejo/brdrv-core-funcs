<?php
if (!defined('ABSPATH')) {
    exit;
}

class SnippetManager {
    
    private static $snippets_dir;
    
    public static function init() {
        self::$snippets_dir = SC_PLUGIN_DIR . 'storage/snippets/';
    }
    
    public static function execute_php() {
        if (is_admin() || defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        self::init();
        
        if (!file_exists(self::$snippets_dir)) {
            return;
        }
        
        // Leer configuración
        $config_file = self::$snippets_dir . 'snippets.json';
        if (!file_exists($config_file)) {
            return;
        }
        
        $config_content = file_get_contents($config_file);
        $snippets_config = json_decode($config_content, true);
        
        if (!is_array($snippets_config)) {
            return;
        }
        
        // Ejecutar snippets PHP
        foreach ($snippets_config as $snippet_id => $snippet_data) {
            if (isset($snippet_data['status']) && $snippet_data['status'] === 'active') {
                $php_file = self::$snippets_dir . $snippet_id . '.php';
                if (file_exists($php_file)) {
                    $php_code = file_get_contents($php_file);
                    if (!empty($php_code)) {
                        self::safe_execute_php($php_code);
                    }
                }
            }
        }
    }
    
    private static function safe_execute_php($code) {
        if (empty($code)) {
            return;
        }
        
        try {
            ob_start();
            eval('?>' . $code);
            ob_end_clean();
        } catch (ParseError $e) {
            error_log('Simply Code Parse Error: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log('Simply Code Exception: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('Simply Code Fatal Error: ' . $e->getMessage());
        }
    }
    
    public static function output_css() {
        if (is_admin()) {
            return;
        }
        
        self::init();
        
        if (!file_exists(self::$snippets_dir)) {
            return;
        }
        
        // Leer configuración
        $config_file = self::$snippets_dir . 'snippets.json';
        if (!file_exists($config_file)) {
            return;
        }
        
        $config_content = file_get_contents($config_file);
        $snippets_config = json_decode($config_content, true);
        
        if (!is_array($snippets_config)) {
            return;
        }
        
        $css_content = '';
        // Procesar snippets CSS
        foreach ($snippets_config as $snippet_id => $snippet_data) {
            if (isset($snippet_data['status']) && $snippet_data['status'] === 'active') {
                $css_file = self::$snippets_dir . $snippet_id . '.css';
                if (file_exists($css_file)) {
                    $css_code = file_get_contents($css_file);
                    if (!empty($css_code)) {
                        $css_content .= "/* Snippet ID: {$snippet_id} */\n";
                        $css_content .= $css_code . "\n\n";
                    }
                }
            }
        }
        
        if (!empty($css_content)) {
            echo "<style id='simply-code-css'>\n" . $css_content . "</style>\n";
        }
    }
    
    public static function output_js() {
        if (is_admin()) {
            return;
        }
        
        self::init();
        
        if (!file_exists(self::$snippets_dir)) {
            return;
        }
        
        // Leer configuración
        $config_file = self::$snippets_dir . 'snippets.json';
        if (!file_exists($config_file)) {
            return;
        }
        
        $config_content = file_get_contents($config_file);
        $snippets_config = json_decode($config_content, true);
        
        if (!is_array($snippets_config)) {
            return;
        }
        
        $js_content = '';
        // Procesar snippets JavaScript
        foreach ($snippets_config as $snippet_id => $snippet_data) {
            if (isset($snippet_data['status']) && $snippet_data['status'] === 'active') {
                $js_file = self::$snippets_dir . $snippet_id . '.js';
                if (file_exists($js_file)) {
                    $js_code = file_get_contents($js_file);
                    if (!empty($js_code)) {
                        $js_content .= "// Snippet ID: {$snippet_id}\n";
                        $js_content .= $js_code . "\n\n";
                    }
                }
            }
        }
        
        if (!empty($js_content)) {
            echo "<script id='simply-code-js'>\n" . $js_content . "</script>\n";
        }
    }
}
