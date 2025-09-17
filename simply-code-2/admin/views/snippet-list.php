<?php
/**
 * Snippets list view showing components
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Snippets', 'simply-code'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=simply-code-editor'); ?>" class="page-title-action">
        <?php _e('Add New', 'simply-code'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php if (empty($snippets)): ?>
        <div class="notice notice-info">
            <p><?php _e('No snippets found. Create your first snippet!', 'simply-code'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Title', 'simply-code'); ?></th>
                    <th><?php _e('ID', 'simply-code'); ?></th>
                    <th><?php _e('Components', 'simply-code'); ?></th>
                    <th><?php _e('Status', 'simply-code'); ?></th>
                    <th><?php _e('Modified', 'simply-code'); ?></th>
                    <th><?php _e('Actions', 'simply-code'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snippets as $id => $snippet): ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=simply-code-editor&id=' . urlencode($id)); ?>">
                                    <?php echo esc_html(isset($snippet['title']) ? $snippet['title'] : $id); ?>
                                </a>
                            </strong>
                            <?php if (!empty($snippet['description'])): ?>
                                <p class="snippet-description"><?php echo esc_html($snippet['description']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($id); ?></td>
                        <td>
                            <?php 
                            $components = isset($snippet['components_enabled']) ? $snippet['components_enabled'] : array();
                            if (empty($components)):
                                echo '<span class="no-components">' . __('None', 'simply-code') . '</span>';
                            else:
                                foreach ($components as $component):
                                    $class = 'component-badge component-' . $component;
                                    echo '<span class="' . $class . '">' . strtoupper($component) . '</span> ';
                                endforeach;
                            endif;
                            ?>
                        </td>
                        <td>
                            <?php if (isset($snippet['active']) && $snippet['active']): ?>
                                <span class="snippet-status-active"><?php _e('Active', 'simply-code'); ?></span>
                            <?php else: ?>
                                <span class="snippet-status-inactive"><?php _e('Inactive', 'simply-code'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo isset($snippet['modified']) ? esc_html($snippet['modified']) : __('Never', 'simply-code'); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=simply-code-editor&id=' . urlencode($id)); ?>" class="button button-primary">
                                <?php _e('Edit', 'simply-code'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(
                                add_query_arg(
                                    array('page' => 'simply-code', 'action' => 'delete', 'id' => $id),
                                    admin_url('admin.php')
                                ),
                                'sc_delete_snippet_' . $id
                            ); ?>" 
                               class="button button-secondary" 
                               onclick="return confirm('<?php _e('Are you sure you want to delete this snippet?', 'simply-code'); ?>')">
                                <?php _e('Delete', 'simply-code'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.snippet-description {
    margin: 5px 0 0 0;
    font-style: italic;
    color: #666;
    font-size: 12px;
}

.component-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    margin: 0 2px;
}

.component-php {
    background-color: #8892BF;
    color: white;
}

.component-js {
    background-color: #F7DF1E;
    color: black;
}

.component-css {
    background-color: #1E90FF;
    color: white;
}

.no-components {
    color: #999;
    font-style: italic;
}

.snippet-status-active {
    color: #00A32A;
    font-weight: bold;
}

.snippet-status-inactive {
    color: #DC3232;
}
</style>
