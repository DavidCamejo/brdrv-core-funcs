<?php
/**
 * Syntax Checker for Simply Code
 */

if (!defined('ABSPATH')) exit;

class Simply_Code_Syntax_Checker {
    
    public static function check_php_syntax($code) {
        // Remover etiquetas PHP si existen
        $code = trim($code);
        if (substr($code, 0, 5) === '<?php') {
            $code = substr($code, 5);
        } elseif (substr($code, 0, 2) === '<?') {
            $code = substr($code, 2);
        }
        
        if (substr($code, -2) === '?>') {
            $code = substr($code, 0, -2);
        }
        
        // Crear un archivo temporal para verificar sintaxis
        $temp_file = tempnam(sys_get_temp_dir(), 'sc_syntax_check');
        file_put_contents($temp_file, "<?php " . $code);
        
        // Verificar sintaxis usando php -l
        $output = [];
        $return_var = 0;
        exec(sprintf('php -l %s 2>&1', escapeshellarg($temp_file)), $output, $return_var);
        
        unlink($temp_file);
        
        if ($return_var !== 0) {
            return [
                'valid' => false,
                'errors' => implode("\n", $output)
            ];
        }
        
        return [
            'valid' => true,
            'errors' => ''
        ];
    }
    
    public static function sanitize_js($js_code) {
        // Basic sanitization for JavaScript
        $js_code = trim($js_code);
        
        // Remove potentially dangerous patterns
        $dangerous_patterns = [
            '/document\.write\(.*?\)/i',
            '/eval\(.*?\)/i',
            '/innerHTML\s*=/i',
            '/outerHTML\s*=/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $js_code)) {
                return [
                    'valid' => false,
                    'errors' => sprintf(__('Potentially dangerous pattern detected: %s', 'simply-code'), $pattern)
                ];
            }
        }
        
        return [
            'valid' => true,
            'errors' => ''
        ];
    }
    
    public static function validate_css($css_code) {
        // Basic CSS validation
        $css_code = trim($css_code);
        
        // Check for basic CSS syntax
        if (!empty($css_code) && !preg_match('/^[^{]*\{[^}]*\}*$/s', $css_code) && strpos($css_code, '{') === false) {
            // This is a very basic check - in reality you might want more sophisticated validation
        }
        
        // Check for potentially dangerous CSS properties
        $dangerous_css = [
            'expression',
            'javascript:',
            'vbscript:',
            'data:'
        ];
        
        foreach ($dangerous_css as $danger) {
            if (stripos($css_code, $danger) !== false) {
                return [
                    'valid' => false,
                    'errors' => sprintf(__('Potentially dangerous CSS detected: %s', 'simply-code'), $danger)
                ];
            }
        }
        
        return [
            'valid' => true,
            'errors' => ''
        ];
    }
    
    public static function format_code($code, $type = 'php') {
        switch (strtolower($type)) {
            case 'php':
                return self::format_php($code);
            case 'js':
                return self::format_js($code);
            case 'css':
                return self::format_css($code);
            default:
                return $code;
        }
    }
    
    private static function format_php($code) {
        // Simple PHP formatting
        $code = trim($code);
        
        // Add PHP tags if missing
        if (substr($code, 0, 5) !== '<?php' && substr($code, 0, 2) !== '<?') {
            $code = '<?php' . "\n" . $code;
        }
        
        return $code;
    }
    
    private static function format_js($code) {
        return trim($code);
    }
    
    private static function format_css($code) {
        return trim($code);
    }
}
