<?php
/**
 * Backups List View
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php printf(__('Backups for "%s"', 'simply-code'), esc_html($snippet['name'])); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=simply-code')); ?>" class="button button-secondary">
        &larr; <?php _e('Back to Snippets', 'simply-code'); ?>
    </a>
    
    <?php if (empty($backups)): ?>
        <div class="notice notice-info">
            <p><?php _e('No backups found for this snippet.', 'simply-code'); ?></p>
        </div>
    <?php else: ?>
        <div class="sc-backup-list">
            <h3><?php _e('Available Backups', 'simply-code'); ?></h3>
            <div class="sc-backup-items">
                <?php foreach ($backups as $backup): ?>
                    <div class="sc-backup-item">
                        <div class="sc-backup-info">
                            <span class="sc-backup-type <?php echo strtolower($backup['type']); ?>">
                                <?php echo esc_html($backup['type']); ?>
                            </span>
                            <div class="sc-backup-date">
                                <?php echo esc_html($backup['date']); ?> 
                                (<?php echo esc_html($backup['size']); ?>)
                            </div>
                        </div>
                        <div class="sc-backup-actions">
                            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=preview_backup&file=' . urlencode(basename($backup['file'])))); ?>" 
                               class="button button-small" target="_blank">
                                <?php _e('Preview', 'simply-code'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=restore_backup&file=' . urlencode(basename($backup['file'])) . '&snippet=' . $id), 'restore_backup')); ?>" 
                               class="button button-small">
                                <?php _e('Restore', 'simply-code'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
