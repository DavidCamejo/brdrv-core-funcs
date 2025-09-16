<?php
/**
 * Snippet Editor View with Tabs
 */
if (!defined('ABSPATH')) exit;

// Asegurar que tengamos los datos del snippet
if (!isset($snippet)) {
    $snippet = [
        'id' => '',
        'name' => '',
        'description' => '',
        'php' => '',
        'js' => '',
        'css' => '',
        'active' => 1
    ];
}

// Extraer variables para uso más fácil
$id = isset($snippet['id']) ? $snippet['id'] : '';
$name = isset($snippet['name']) ? $snippet['name'] : '';
$description = isset($snippet['description']) ? $snippet['description'] : '';
$php_code = isset($snippet['php']) ? $snippet['php'] : '';
$js_code = isset($snippet['js']) ? $snippet['js'] : '';
$css_code = isset($snippet['css']) ? $snippet['css'] : '';
$active = isset($snippet['active']) ? $snippet['active'] : 1;

// Variable crítica para hooks (definida para evitar warning)
$critical_hooks = [
    'wp_head', 'wp_footer', 'init', 'wp_loaded', 'admin_init'
];
?>

<div class="wrap">
    <h1><?php echo $id ? __('Edit Snippet', 'simply-code') : __('Add New Snippet', 'simply-code'); ?></h1>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('save_snippet', 'sc_nonce'); ?>
        
        <input type="hidden" name="action" value="save_snippet">
        <?php if ($id): ?>
            <input type="hidden" name="snippet_id" value="<?php echo esc_attr($id); ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="snippet_name"><?php _e('Name', 'simply-code'); ?></label></th>
                <td><input name="snippet_name" type="text" id="snippet_name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="snippet_description"><?php _e('Description', 'simply-code'); ?></label></th>
                <td><textarea name="snippet_description" id="snippet_description" rows="3" cols="50" class="large-text"><?php echo esc_textarea($description); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Active', 'simply-code'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="snippet_active" value="1" <?php checked($active, 1); ?>>
                            <span><?php _e('Enable this snippet', 'simply-code'); ?></span>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <!-- TABS INTERFACE -->
        <div class="sc-tabs-wrapper">
            <div class="sc-tab-buttons">
                <button type="button" class="sc-tab-button active" data-tab="php"><?php _e('PHP', 'simply-code'); ?></button>
                <button type="button" class="sc-tab-button" data-tab="js"><?php _e('JavaScript', 'simply-code'); ?></button>
                <button type="button" class="sc-tab-button" data-tab="css"><?php _e('CSS', 'simply-code'); ?></button>
            </div>

            <div class="sc-tab-content">
                <div id="php" class="sc-tab-pane active">
                    <textarea name="php_code" id="php_code" class="large-text code" rows="20"><?php echo esc_textarea($php_code); ?></textarea>
                </div>
                
                <div id="js" class="sc-tab-pane">
                    <textarea name="js_code" id="js_code" class="large-text code" rows="20"><?php echo esc_textarea($js_code); ?></textarea>
                </div>
                
                <div id="css" class="sc-tab-pane">
                    <textarea name="css_code" id="css_code" class="large-text code" rows="20"><?php echo esc_textarea($css_code); ?></textarea>
                </div>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<style>
.sc-tabs-wrapper {
    margin: 20px 0;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    background: #fff;
}

.sc-tab-buttons {
    display: flex;
    border-bottom: 1px solid #c3c4c7;
    background: #f0f0f1;
}

.sc-tab-button {
    background: none;
    border: none;
    padding: 12px 16px;
    cursor: pointer;
    font-weight: 500;
    border-right: 1px solid #c3c4c7;
    transition: all 0.1s ease-in-out;
}

.sc-tab-button:last-child {
    border-right: none;
}

.sc-tab-button:hover {
    background: #ddd;
}

.sc-tab-button.active {
    background: #fff;
    color: #1d2327;
    box-shadow: inset 0 -1px 0 #fff;
}

.sc-tab-content {
    padding: 0;
}

.sc-tab-pane {
    display: none;
    padding: 15px;
}

.sc-tab-pane.active {
    display: block;
}

.sc-tab-pane textarea {
    width: 100%;
    font-family: monospace;
    resize: vertical;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.sc-tab-button');
    const tabPanes = document.querySelectorAll('.sc-tab-pane');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to current button and pane
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Auto-select first tab if none is active
    if (document.querySelectorAll('.sc-tab-button.active').length === 0) {
        document.querySelector('.sc-tab-button').click();
    }
});
</script>
