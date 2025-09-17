<?php
/**
 * Clase para verificar sintaxis de código
 */
class Simply_Code_Syntax_Checker {
    
    /**
     * Verificar sintaxis PHP
     */
    public static function check_php_syntax($code) {
        if (empty($code)) {
            return array('valid' => true, 'message' => '');
        }
        
        // Crear archivo temporal
        $temp_file = tempnam(sys_get_temp_dir(), 'sc_syntax_');
        file_put_contents($temp_file, $code);
        
        // Verificar sintaxis
        $output = array();
        $return_var = 0;
        exec('php -l ' . escapeshellarg($temp_file) . ' 2>&1', $output, $return_var);
        
        // Limpiar archivo temporal
        unlink($temp_file);
        
        $output_str = implode("\n", $output);
        
        if ($return_var === 0) {
            return array('valid' => true, 'message' => __('Sintaxis PHP válida'));
        } else {
            return array('valid' => false, 'message' => $output_str);
        }
    }
    
    /**
     * Verificar sintaxis JavaScript (básico)
     */
    public static function check_js_syntax($code) {
        // Implementación básica - en producción podrías usar herramientas como ESLint
        if (empty($code)) {
            return array('valid' => true, 'message' => '');
        }
        
        // Verificaciones básicas
        $errors = array();
        
        // Verificar paréntesis balanceados
        if (!self::are_brackets_balanced($code, '(', ')')) {
            $errors[] = __('Paréntesis desbalanceados');
        }
        
        // Verificar llaves balanceadas
        if (!self::are_brackets_balanced($code, '{', '}')) {
            $errors[] = __('Llaves desbalanceadas');
        }
        
        // Verificar corchetes balanceados
        if (!self::are_brackets_balanced($code, '[', ']')) {
            $errors[] = __('Corchetes desbalanceados');
        }
        
        if (empty($errors)) {
            return array('valid' => true, 'message' => __('Sintaxis JavaScript básica válida'));
        } else {
            return array('valid' => false, 'message' => implode(', ', $errors));
        }
    }
    
    /**
     * Verificar sintaxis CSS (básico)
     */
    public static function check_css_syntax($code) {
        if (empty($code)) {
            return array('valid' => true, 'message' => '');
        }
        
        $errors = array();
        
        // Verificar llaves balanceadas
        if (!self::are_brackets_balanced($code, '{', '}')) {
            $errors[] = __('Llaves desbalanceadas');
        }
        
        if (empty($errors)) {
            return array('valid' => true, 'message' => __('Sintaxis CSS básica válida'));
        } else {
            return array('valid' => false, 'message' => implode(', ', $errors));
        }
    }
    
    /**
     * Verificar si los brackets están balanceados
     */
    private static function are_brackets_balanced($string, $open, $close) {
        $count = 0;
        $length = strlen($string);
        
        for ($i = 0; $i < $length; $i++) {
            if ($string[$i] === $open) {
                $count++;
            } elseif ($string[$i] === $close) {
                $count--;
                if ($count < 0) {
                    return false;
                }
            }
        }
        
        return $count === 0;
    }
}
