<?php
/**
 * Class to detect WordPress hooks in code
 */
class Simply_Code_Hook_Detector {
    
    /**
     * Detect hooks in code
     */
    public static function detect_hooks($code) {
        $hooks = array();
        
        // Pattern to match add_action/add_filter calls
        $patterns = array(
            'actions' => '/add_action\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[^,]+/i',
            'filters' => '/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[^,]+/i'
        );
        
        foreach ($patterns as $type => $pattern) {
            preg_match_all($pattern, $code, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $hook) {
                    $hooks[] = array(
                        'type' => $type,
                        'hook' => $hook
                    );
                }
            }
        }
        
        return $hooks;
    }
    
    /**
     * Get common WordPress hooks
     */
    public static function get_common_hooks() {
        return array(
            // Common Actions
            'init',
            'wp_loaded',
            'wp_head',
            'wp_footer',
            'wp_enqueue_scripts',
            'admin_enqueue_scripts',
            'admin_menu',
            'admin_init',
            'save_post',
            'publish_post',
            'transition_post_status',
            'wp_login',
            'wp_logout',
            'widgets_init',
            
            // Common Filters
            'the_content',
            'the_excerpt',
            'the_title',
            'wp_title',
            'body_class',
            'post_class',
            'excerpt_length',
            'excerpt_more',
            'wp_nav_menu_items',
            'nav_menu_css_class',
            'wp_get_attachment_image_attributes'
        );
    }
}
