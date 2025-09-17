<?php
// @description Hook de WordPress
add_action('init', function() {
    // Tu código aquí
});<?php
/**
 * Template: WordPress Hook
 */

// Action example
add_action('wp_head', 'my_custom_action');
function my_custom_action() {
    // Code to run on wp_head
}

// Filter example
add_filter('the_content', 'my_custom_filter');
function my_custom_filter($content) {
    // Modify content
    return $content;
}
