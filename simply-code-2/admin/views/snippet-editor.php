<?php
/**
 * Snippet editor view with tabs for multiple components
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo empty($data['id']) ? __('Add New Snippet', 'simply-code') : __('Edit Snippet', 'simply-code'); ?></h1>
    
    <form method="post" action="" id="snippet-editor-form">
        <?php wp_nonce_field('sc_save_snippet', 'sc_save_nonce'); ?>
        
        <!-- Basic Info -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('Snippet Information', 'simply-code'); ?></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="snippet_id"><?php _e('Snippet ID', 'simply-code'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="snippet_id" 
                                   name="snippet_id" 
                                   value="<?php echo esc_attr($data['id']); ?>" 
                                   class="regular-text" 
                                   <?php echo empty($data['id']) ? '' : 'readonly'; ?> />
                            <p class="description"><?php _e('Unique identifier for the snippet (letters, numbers, hyphens, underscores only).', 'simply-code'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="snippet_title"><?php _e('Title', 'simply-code'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="snippet_title" 
                                   name="snippet_title" 
                                   value="<?php echo esc_attr($data['title']); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="snippet_description"><?php _e('Description', 'simply-code'); ?></label>
                        </th>
                        <td>
                            <textarea id="snippet_description" 
                                      name="snippet_description" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea($data['description']); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="snippet_active"><?php _e('Active', 'simply-code'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="snippet_active" 
                                   name="snippet_active" 
                                   value="1" 
                                   <?php checked($data['active']); ?> />
                            <label for="snippet_active"><?php _e('Enable this snippet', 'simply-code'); ?></label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Component Tabs -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('Components', 'simply-code'); ?></h2>
            <div class="inside">
                <div class="snippet-tabs">
                    <div class="snippet-tab-nav">
                        <button type="button" class="tab-button active" data-tab="php">
                            PHP <?php if (!empty($data['components']['php'])): ?><span class="has-content">●</span><?php endif; ?>
                        </button>
                        <button type="button" class="tab-button" data-tab="js">
                            JavaScript <?php if (!empty($data['components']['js'])): ?><span class="has-content">●</span><?php endif; ?>
                        </button>
                        <button type="button" class="tab-button" data-tab="css">
                            CSS <?php if (!empty($data['components']['css'])): ?><span class="has-content">●</span><?php endif; ?>
                        </button>
                    </div>
                    
                    <!-- PHP Component -->
                    <div class="tab-content active" id="tab-php">
                        <div class="component-header">
                            <label>
                                <input type="checkbox" 
                                       name="snippet_php_enabled" 
                                       value="1" 
                                       <?php checked(in_array('php', $data['components_enabled']) || !empty($data['components']['php'])); ?> />
                                <?php _e('Enable PHP Component', 'simply-code'); ?>
                            </label>
                        </div>
                        <textarea name="snippet_php_content" 
                                  class="large-text code-editor" 
                                  rows="15"
                                  placeholder="<?php _e('Enter your PHP code here...', 'simply-code'); ?>"><?php echo esc_textarea($data['components']['php']); ?></textarea>
                    </div>
                    
                    <!-- JavaScript Component -->
                    <div class="tab-content" id="tab-js">
                        <div class="component-header">
                            <label>
                                <input type="checkbox" 
                                       name="snippet_js_enabled" 
                                       value="1" 
                                       <?php checked(in_array('js', $data['components_enabled']) || !empty($data['components']['js'])); ?> />
                                <?php _e('Enable JavaScript Component', 'simply-code'); ?>
                            </label>
                        </div>
                        <textarea name="snippet_js_content" 
                                  class="large-text code-editor" 
                                  rows="15"
                                  placeholder="<?php _e('Enter your JavaScript code here...', 'simply-code'); ?>"><?php echo esc_textarea($data['components']['js']); ?></textarea>
                    </div>
                    
                    <!-- CSS Component -->
                    <div class="tab-content" id="tab-css">
                        <div class="component-header">
                            <label>
                                <input type="checkbox" 
                                       name="snippet_css_enabled" 
                                       value="1" 
                                       <?php checked(in_array('css', $data['components_enabled']) || !empty($data['components']['css'])); ?> />
                                <?php _e('Enable CSS Component', 'simply-code'); ?>
                            </label>
                        </div>
                        <textarea name="snippet_css_content" 
                                  class="large-text code-editor" 
                                  rows="15"
                                  placeholder="<?php _e('Enter your CSS code here...', 'simply-code'); ?>"><?php echo esc_textarea($data['components']['css']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Snippet', 'simply-code')); ?>
        
        <a href="<?php echo admin_url('admin.php?page=simply-code'); ?>" class="button button-secondary">
            <?php _e('Cancel', 'simply-code'); ?>
        </a>
        
        <?php if (!empty($data['id'])): ?>
            <a href="<?php echo wp_nonce_url(
                add_query_arg(
                    array('page' => 'simply-code', 'action' => 'delete', 'id' => $data['id']),
                    admin_url('admin.php')
                ),
                'sc_delete_snippet_' . $data['id']
            ); ?>" 
               class="button button-link-delete" 
               onclick="return confirm('<?php _e('Are you sure you want to delete this snippet?', 'simply-code'); ?>')">
                <?php _e('Delete', 'simply-code'); ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.tab-button').click(function() {
        var tabId = $(this).data('tab');
        
        // Update active tab button
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show active tab content
        $('.tab-content').removeClass('active');
        $('#tab-' + tabId).addClass('active');
    });
    
    // Form validation
    $('#snippet-editor-form').submit(function() {
        var snippetId = $('#snippet_id').val().trim();
        if (snippetId === '') {
            alert('<?php _e('Please enter a snippet ID.', 'simply-code'); ?>');
            $('#snippet_id').focus();
            return false;
        }
        return true;
    });
});
</script>

<style>
.postbox {
    margin-bottom: 20px;
}

.hndle {
    font-size: 14px;
    padding: 8px 12px;
    margin: 0;
    line-height: 1.4;
}

.inside {
    padding: 0 12px 12px;
}

.snippet-tabs {
    margin-top: 10px;
}

.snippet-tab-nav {
    border-bottom: 1px solid #ccc;
    margin-bottom: 0;
}

.tab-button {
    background: #f1f1f1;
    border: 1px solid #ccc;
    border-bottom: none;
    padding: 8px 16px;
    margin-bottom: -1px;
    cursor: pointer;
    border-radius: 3px 3px 0 0;
    margin-right: 2px;
}

.tab-button.active {
    background: #fff;
    border-bottom: 1px solid #fff;
    position: relative;
}

.tab-button:hover:not(.active) {
    background: #e5e5e5;
}

.has-content {
    color: #0073aa;
    margin-left: 5px;
}

.tab-content {
    display: none;
    padding: 15px 0;
}

.tab-content.active {
    display: block;
}

.component-header {
    margin-bottom: 10px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.code-editor {
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    width: 100%;
    box-sizing: border-box;
    resize: vertical;
}

.form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.form-table td {
    padding: 15px 10px;
}
</style>
