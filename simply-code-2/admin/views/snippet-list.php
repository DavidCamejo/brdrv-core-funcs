<?php
/**
 * Snippets List View
 */
if (!defined('ABSPATH')) exit;

$manager = Simply_Code_Snippet_Manager::get_instance();
$snippets = $manager->get_all_snippets();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Snippets', 'simply-code'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=simply-code-editor')); ?>" class="page-title-action"><?php _e('Add New', 'simply-code'); ?></a>
    
    <hr class="wp-header-end">
    
    <?php if (empty($snippets)): ?>
        <div class="notice notice-info">
            <p><?php _e('No snippets found. Create your first snippet!', 'simply-code'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary"><?php _e('Name', 'simply-code'); ?></th>
                    <th scope="col" class="manage-column column-description"><?php _e('Description', 'simply-code'); ?></th>
                    <th scope="col" class="manage-column column-type"><?php _e('Type', 'simply-code'); ?></th>
                    <th scope="col" class="manage-column column-status"><?php _e('Status', 'simply-code'); ?></th>
                    <th scope="col" class="manage-column column-date"><?php _e('Modified', 'simply-code'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'simply-code'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snippets as $id => $snippet): ?>
                    <tr>
                        <td class="column-name has-row-actions column-primary" data-colname="<?php esc_attr_e('Name', 'simply-code'); ?>">
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=simply-code-editor&action=edit&id=' . $id)); ?>" class="row-title">
                                    <?php echo esc_html($snippet['name']); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=simply-code-editor&action=edit&id=' . $id)); ?>">
                                        <?php _e('Edit', 'simply-code'); ?>
                                    </a> | 
                                </span>
                                <?php if ($snippet['active']): ?>
                                    <span class="deactivate">
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=deactivate_snippet&id=' . $id), 'toggle_snippet')); ?>" class="sc-confirm-delete">
                                            <?php _e('Deactivate', 'simply-code'); ?>
                                        </a> | 
                                    </span>
                                <?php else: ?>
                                    <span class="activate">
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=activate_snippet&id=' . $id), 'toggle_snippet')); ?>">
                                            <?php _e('Activate', 'simply-code'); ?>
                                        </a> | 
                                    </span>
                                <?php endif; ?>
                                <span class="delete">
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=delete_snippet&id=' . $id), 'delete_snippet')); ?>" class="submitdelete sc-confirm-delete">
                                        <?php _e('Delete', 'simply-code'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-description" data-colname="<?php esc_attr_e('Description', 'simply-code'); ?>">
                            <?php echo esc_html($snippet['description'] ?: __('No description', 'simply-code')); ?>
                        </td>
                        <td class="column-type" data-colname="<?php esc_attr_e('Type', 'simply-code'); ?>">
                            <?php
                            $types = [];
                            if (!empty($snippet['php'])) $types[] = 'PHP';
                            if (!empty($snippet['js'])) $types[] = 'JS';
                            if (!empty($snippet['css'])) $types[] = 'CSS';
                            echo esc_html(implode(', ', $types) ?: __('None', 'simply-code'));
                            ?>
                        </td>
                        <td class="column-status" data-colname="<?php esc_attr_e('Status', 'simply-code'); ?>">
                            <?php if ($snippet['active']): ?>
                                <span class="sc-status-active"><?php _e('Active', 'simply-code'); ?></span>
                            <?php else: ?>
                                <span class="sc-status-inactive"><?php _e('Inactive', 'simply-code'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-date" data-colname="<?php esc_attr_e('Modified', 'simply-code'); ?>">
                            <?php 
                            $modified = $snippet['updated_at'] ?? $snippet['created_at'] ?? '';
                            if ($modified) {
                                echo esc_html(date_i18n(get_option('date_format'), strtotime($modified)));
                            } else {
                                _e('Unknown', 'simply-code');
                            }
                            ?>
                        </td>
                        <td class="column-actions" data-colname="<?php esc_attr_e('Actions', 'simply-code'); ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=simply-code&action=view-backups&id=' . $id)); ?>" class="button button-small">
                                <?php _e('Backups', 'simply-code'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.sc-status-active {
    color: #00a32a;
    font-weight: bold;
}

.sc-status-inactive {
    color: #d63638;
    font-weight: bold;
}

.row-actions .sc-confirm-delete {
    color: #d63638;
}

.wp-list-table .column-name { width: 25%; }
.wp-list-table .column-description { width: 30%; }
.wp-list-table .column-type { width: 10%; }
.wp-list-table .column-status { width: 10%; }
.wp-list-table .column-date { width: 15%; }
.wp-list-table .column-actions { width: 10%; }

@media screen and (max-width: 782px) {
    .wp-list-table .column-type,
    .wp-list-table .column-status,
    .wp-list-table .column-date,
    .wp-list-table .column-actions {
        display: none;
    }
}
</style>
