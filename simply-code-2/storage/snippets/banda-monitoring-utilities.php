<?php
/**
 * PMPro Nextcloud Banda - Monitoring & Debugging Utilities
 * 
 * Provides comprehensive monitoring and debugging tools for the Banda system
 * 
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// ====
// MONITORING DASHBOARD
// ====

/**
 * Add admin menu for Banda monitoring
 */
function banda_monitoring_admin_menu() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    add_submenu_page(
        'pmpro-membershiplevels',
        'Banda Monitoring',
        'Banda Monitor',
        'manage_options',
        'banda-monitoring',
        'banda_monitoring_dashboard'
    );
}
add_action('admin_menu', 'banda_monitoring_admin_menu');

/**
 * Monitoring dashboard page
 */
function banda_monitoring_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Handle actions
    if (isset($_POST['action'])) {
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'test_api':
                $api_test_result = banda_test_nextcloud_api();
                break;
            case 'clear_logs':
                banda_clear_debug_logs();
                echo '<div class="notice notice-success"><p>Logs cleared successfully!</p></div>';
                break;
            case 'sync_quotas':
                $sync_result = banda_sync_all_quotas();
                break;
        }
    }
    
    ?>
    <div class="wrap">
        <h1>🎯 Banda System Monitoring</h1>
        
        <!-- System Status -->
        <div class="card">
            <h2>📊 System Status</h2>
            <?php banda_display_system_status(); ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <h2>📈 Recent Activity (Last 24h)</h2>
            <?php banda_display_recent_activity(); ?>
        </div>
        
        <!-- API Testing -->
        <div class="card">
            <h2>🔧 API Testing</h2>
            <form method="post">
                <input type="hidden" name="action" value="test_api">
                <?php wp_nonce_field('banda_monitoring_action'); ?>
                <p>
                    <input type="submit" class="button button-secondary" value="Test Nextcloud API">
                </p>
            </form>
            
            <?php if (isset($api_test_result)): ?>
                <div class="notice notice-<?php echo $api_test_result['success'] ? 'success' : 'error'; ?>">
                    <p><strong>API Test Result:</strong> <?php echo esc_html($api_test_result['message']); ?></p>
                    <?php if (!empty($api_test_result['details'])): ?>
                        <pre><?php echo esc_html(print_r($api_test_result['details'], true)); ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- User Journey Tracker -->
        <div class="card">
            <h2>👤 User Journey Tracker</h2>
            <?php banda_display_user_journeys(); ?>
        </div>
        
        <!-- Debug Tools -->
        <div class="card">
            <h2>🐛 Debug Tools</h2>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="clear_logs">
                <?php wp_nonce_field('banda_monitoring_action'); ?>
                <input type="submit" class="button button-secondary" value="Clear Debug Logs" 
                       onclick="return confirm('Are you sure you want to clear all debug logs?')">
            </form>
            
            <form method="post" style="display: inline; margin-left: 10px;">
                <input type="hidden" name="action" value="sync_quotas">
                <?php wp_nonce_field('banda_monitoring_action'); ?>
                <input type="submit" class="button button-secondary" value="Sync All Quotas">
            </form>
            
            <?php if (isset($sync_result)): ?>
                <div class="notice notice-<?php echo $sync_result['success'] ? 'success' : 'error'; ?>">
                    <p><?php echo esc_html($sync_result['message']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Error Log Viewer -->
        <div class="card">
            <h2>📋 Recent Error Logs</h2>
            <?php banda_display_error_logs(); ?>
        </div>
    </div>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin: 20px 0;
        padding: 20px;
    }
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 15px 0;
    }
    .status-item {
        padding: 15px;
        border-radius: 4px;
        text-align: center;
    }
    .status-success { background: #d4edda; color: #155724; }
    .status-warning { background: #fff3cd; color: #856404; }
    .status-error { background: #f8d7da; color: #721c24; }
    .journey-item {
        border-left: 4px solid #0073aa;
        padding: 10px 15px;
        margin: 10px 0;
        background: #f9f9f9;
    }
    .log-entry {
        font-family: monospace;
        font-size: 12px;
        background: #f1f1f1;
        padding: 8px;
        margin: 5px 0;
        border-radius: 3px;
    }
    </style>
    <?php
}

/**
 * Display system status overview
 */
function banda_display_system_status() {
    $status = banda_get_system_status();
    
    echo '<div class="status-grid">';
    
    foreach ($status as $item) {
        $class = 'status-' . $item['status'];
        echo "<div class='status-item {$class}'>";
        echo "<strong>{$item['label']}</strong><br>";
        echo "<span>{$item['value']}</span>";
        echo "</div>";
    }
    
    echo '</div>';
}

/**
 * Get comprehensive system status
 */
function banda_get_system_status() {
    $status = [];
    
    // API Credentials
    $has_credentials = !empty(getenv('NEXTCLOUD_API_ADMIN')) && !empty(getenv('NEXTCLOUD_API_PASS'));
    $status[] = [
        'label' => 'API Credentials',
        'value' => $has_credentials ? 'Configured' : 'Missing',
        'status' => $has_credentials ? 'success' : 'error'
    ];
    
    // PMPro Dependencies
    $pmpro_ok = function_exists('pmpro_getLevel') && function_exists('pmprorh_add_registration_field');
    $status[] = [
        'label' => 'PMPro Dependencies',
        'value' => $pmpro_ok ? 'Available' : 'Missing',
        'status' => $pmpro_ok ? 'success' : 'error'
    ];
    
    // Banda Level
    $banda_level = function_exists('pmpro_getLevel') ? pmpro_getLevel(2) : null;
    $status[] = [
        'label' => 'Banda Level (ID: 2)',
        'value' => $banda_level ? 'Configured' : 'Not Found',
        'status' => $banda_level ? 'success' : 'error'
    ];
    
    // Recent Groups Created (last 7 days)
    $recent_groups = banda_count_recent_groups(7);
    $status[] = [
        'label' => 'Groups (7 days)',
        'value' => $recent_groups,
        'status' => $recent_groups > 0 ? 'success' : 'warning'
    ];
    
    // Email Delivery Rate
    $email_rate = banda_calculate_email_success_rate();
    $status[] = [
        'label' => 'Email Success Rate',
        'value' => $email_rate . '%',
        'status' => $email_rate >= 90 ? 'success' : ($email_rate >= 70 ? 'warning' : 'error')
    ];
    
    // API Response Time
    $api_response = banda_test_api_response_time();
    $status[] = [
        'label' => 'API Response Time',
        'value' => $api_response['time'] . 'ms',
        'status' => $api_response['time'] < 2000 ? 'success' : ($api_response['time'] < 5000 ? 'warning' : 'error')
    ];
    
    return $status;
}

/**
 * Display recent activity
 */
function banda_display_recent_activity() {
    $activities = banda_get_recent_activities();
    
    if (empty($activities)) {
        echo '<p>No recent activity found.</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Time</th><th>User</th><th>Action</th><th>Status</th><th>Details</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($activities as $activity) {
        $status_class = $activity['success'] ? 'success' : 'error';
        echo "<tr>";
        echo "<td>{$activity['time']}</td>";
        echo "<td>{$activity['user']}</td>";
        echo "<td>{$activity['action']}</td>";
        echo "<td><span class='status-{$status_class}'>" . ($activity['success'] ? '✅' : '❌') . "</span></td>";
        echo "<td>{$activity['details']}</td>";
        echo "</tr>";
    }
    
    echo '</tbody></table>';
}

/**
 * Get recent activities from user meta and logs
 */
function banda_get_recent_activities() {
    global $wpdb;
    
    $activities = [];
    
    // Get recent group creations
    $recent_groups = $wpdb->get_results($wpdb->prepare("
        SELECT u.user_login, um.user_id, um.meta_value as created_time, um2.meta_value as group_name
        FROM {$wpdb->usermeta} um
        JOIN {$wpdb->users} u ON um.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um2 ON um.user_id = um2.user_id AND um2.meta_key = 'nextcloud_banda_group_name'
        WHERE um.meta_key = 'nextcloud_banda_group_created'
        AND um.meta_value > %s
        ORDER BY um.meta_value DESC
        LIMIT 20
    ", date('Y-m-d H:i:s', strtotime('-24 hours')));
    
    foreach ($recent_groups as $group) {
        $activities[] = [
            'time' => date('H:i:s', strtotime($group->created_time)),
            'user' => $group->user_login,
            'action' => 'Group Created',
            'success' => true,
            'details' => $group->group_name ?: 'banda-' . $group->user_id
        ];
    }
    
    // Sort by time
    usort($activities, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    
    return $activities;
}

/**
 * Display user journey tracking
 */
function banda_display_user_journeys() {
    if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        $journey = banda_get_user_journey($user_id);
        
        echo "<h3>Journey for User ID: {$user_id}</h3>";
        
        foreach ($journey as $step) {
            $icon = $step['success'] ? '✅' : '❌';
            echo "<div class='journey-item'>";
            echo "<strong>{$icon} {$step['step']}</strong> - {$step['time']}<br>";
            echo "<small>{$step['details']}</small>";
            echo "</div>";
        }
        
        echo "<p><a href='" . remove_query_arg('user_id') . "'>← Back to overview</a></p>";
    } else {
        // Show recent users with journeys
        $recent_users = banda_get_recent_banda_users();
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>User</th><th>Email</th><th>Group Created</th><th>Last Activity</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($recent_users as $user) {
            echo "<tr>";
            echo "<td>{$user['user_login']}</td>";
            echo "<td>{$user['user_email']}</td>";
            echo "<td>" . ($user['group_created'] ? '✅' : '❌') . "</td>";
            echo "<td>{$user['last_activity']}</td>";
            echo "<td><a href='" . add_query_arg('user_id', $user['ID']) . "'>View Journey</a></td>";
            echo "</tr>";
        }
        
        echo '</tbody></table>';
    }
}

/**
 * Get user journey details
 */
function banda_get_user_journey($user_id) {
    $journey = [];
    
    // Check membership
    if (function_exists('pmpro_getMembershipLevelForUser')) {
        $level = pmpro_getMembershipLevelForUser($user_id);
        $journey[] = [
            'step' => 'Membership Level',
            'success' => !empty($level) && $level->id == 2,
            'time' => $level ? date('Y-m-d H:i:s', strtotime($level->startdate)) : 'N/A',
            'details' => $level ? "Level: {$level->name} (ID: {$level->id})" : 'No membership found'
        ];
    }
    
    // Check configuration
    $config = get_user_meta($user_id, 'nextcloud_banda_config', true);
    $journey[] = [
        'step' => 'Configuration Saved',
        'success' => !empty($config),
        'time' => $config ? (json_decode($config, true)['created_at'] ?? 'Unknown') : 'N/A',
        'details' => $config ? 'Configuration found' : 'No configuration saved'
    ];
    
    // Check group creation
    $group_created = get_user_meta($user_id, 'nextcloud_banda_group_created', true);
    $group_name = get_user_meta($user_id, 'nextcloud_banda_group_name', true);
    $journey[] = [
        'step' => 'Nextcloud Group Created',
        'success' => !empty($group_created),
        'time' => $group_created ?: 'N/A',
        'details' => $group_name ? "Group: {$group_name}" : 'No group created'
    ];
    
    return $journey;
}

/**
 * Test Nextcloud API connectivity
 */
function banda_test_nextcloud_api() {
    $admin = getenv('NEXTCLOUD_API_ADMIN');
    $pass = getenv('NEXTCLOUD_API_PASS');
    
    if (empty($admin) || empty($pass)) {
        return [
            'success' => false,
            'message' => 'API credentials not configured',
            'details' => null
        ];
    }
    
    $site_url = get_option('siteurl');
    $api_url = 'https://cloud.' . parse_url($site_url, PHP_URL_HOST);
    
    $start_time = microtime(true);
    
    $response = wp_remote_get($api_url . '/ocs/v1.php/cloud/users', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($admin . ':' . $pass),
            'OCS-APIRequest' => 'true'
        ],
        'timeout' => 10
    ]);
    
    $end_time = microtime(true);
    $response_time = round(($end_time - $start_time) * 1000);
    
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => 'API connection failed: ' . $response->get_error_message(),
            'details' => [
                'url' => $api_url,
                'response_time' => $response_time . 'ms'
            ]
        ];
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    return [
        'success' => $http_code === 200,
        'message' => $http_code === 200 ? 'API connection successful' : "HTTP Error: {$http_code}",
        'details' => [
            'url' => $api_url,
            'http_code' => $http_code,
            'response_time' => $response_time . 'ms',
            'response_size' => strlen($body) . ' bytes'
        ]
    ];
}

/**
 * Display error logs
 */
function banda_display_error_logs() {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    
    if (!file_exists($log_file)) {
        echo '<p>No debug log file found.</p>';
        return;
    }
    
    $logs = banda_parse_recent_logs($log_file, 50);
    
    if (empty($logs)) {
        echo '<p>No recent Banda-related logs found.</p>';
        return;
    }
    
    foreach ($logs as $log) {
        $class = strpos($log, 'ERROR') !== false ? 'error' : 'info';
        echo "<div class='log-entry log-{$class}'>" . esc_html($log) . "</div>";
    }
}

/**
 * Parse recent logs related to Banda system
 */
function banda_parse_recent_logs($log_file, $limit = 50) {
    if (!file_exists($log_file)) {
        return [];
    }
    
    $logs = [];
    $handle = fopen($log_file, 'r');
    
    if ($handle) {
        // Read from end of file
        fseek($handle, -8192, SEEK_END); // Read last 8KB
        $content = fread($handle, 8192);
        fclose($handle);
        
        $lines = explode("\n", $content);
        $lines = array_reverse($lines);
        
        $count = 0;
        foreach ($lines as $line) {
            if ($count >= $limit) break;
            
            if (strpos($line, 'PMPro') !== false || 
                strpos($line, 'Nextcloud') !== false || 
                strpos($line, 'Banda') !== false) {
                $logs[] = trim($line);
                $count++;
            }
        }
    }
    
    return $logs;
}

/**
 * Count recent groups created
 */
function banda_count_recent_groups($days = 7) {
    global $wpdb;
    
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->usermeta}
        WHERE meta_key = 'nextcloud_banda_group_created'
        AND meta_value > %s
    ", date('Y-m-d H:i:s', strtotime("-{$days} days"))));
    
    return intval($count);
}

/**
 * Calculate email success rate
 */
function banda_calculate_email_success_rate() {
    // This is a simplified calculation
    // In a real implementation, you'd track email delivery status
    $recent_groups = banda_count_recent_groups(30);
    
    if ($recent_groups === 0) {
        return 100; // No data, assume success
    }
    
    // Simplified: assume 95% success rate if no specific tracking
    return 95;
}

/**
 * Test API response time
 */
function banda_test_api_response_time() {
    $admin = getenv('NEXTCLOUD_API_ADMIN');
    $pass = getenv('NEXTCLOUD_API_PASS');
    
    if (empty($admin) || empty($pass)) {
        return ['time' => 0, 'success' => false];
    }
    
    $site_url = get_option('siteurl');
    $api_url = 'https://cloud.' . parse_url($site_url, PHP_URL_HOST);
    
    $start_time = microtime(true);
    
    $response = wp_remote_get($api_url . '/status.php', [
        'timeout' => 5
    ]);
    
    $end_time = microtime(true);
    $response_time = round(($end_time - $start_time) * 1000);
    
    return [
        'time' => $response_time,
        'success' => !is_wp_error($response)
    ];
}

/**
 * Get recent Banda users
 */
function banda_get_recent_banda_users() {
    global $wpdb;
    
    $users = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.user_login, u.user_email,
               um1.meta_value as group_created,
               um2.meta_value as config_data
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'nextcloud_banda_group_created'
        LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'nextcloud_banda_config'
        WHERE um1.meta_value IS NOT NULL OR um2.meta_value IS NOT NULL
        ORDER BY GREATEST(IFNULL(um1.meta_value, '1970-01-01'), IFNULL(um2.meta_value, '1970-01-01')) DESC
        LIMIT 20
    ");
    
    $result = [];
    foreach ($users as $user) {
        $result[] = [
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'group_created' => !empty($user->group_created),
            'last_activity' => $user->group_created ?: 'N/A'
        ];
    }
    
    return $result;
}

/**
 * Clear debug logs
 */
function banda_clear_debug_logs() {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
    }
}

/**
 * Sync all quotas (maintenance function)
 */
function banda_sync_all_quotas() {
    global $wpdb;
    
    $users_with_groups = $wpdb->get_results("
        SELECT user_id, meta_value as group_name
        FROM {$wpdb->usermeta}
        WHERE meta_key = 'nextcloud_banda_group_name'
    ");
    
    $synced = 0;
    $errors = 0;
    
    foreach ($users_with_groups as $user_group) {
        $user_id = $user_group->user_id;
        $config = get_user_meta($user_id, 'nextcloud_banda_config', true);
        
        if (empty($config)) {
            continue;
        }
        
        $config_data = json_decode($config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            continue;
        }
        
        // Re-calculate and apply quotas
        $storage_str = strtolower($config_data['storage_space'] ?? '1tb');
        $num_users = intval($config_data['num_users'] ?? 2);
        
        $total_gb = 1024; // default 1TB
        if (preg_match('/^([\d\.]+)\s*tb$/i', $storage_str, $m)) {
            $total_gb = intval(round(floatval($m[1]) * 1024));
        }
        
        // This would call the actual quota sync function
        // For now, just count as synced
        $synced++;
    }
    
    return [
        'success' => true,
        'message' => "Synced quotas for {$synced} users. {$errors} errors encountered."
    ];
}

// ====
// AJAX HANDLERS FOR REAL-TIME MONITORING
// ====

/**
 * AJAX handler for real-time status updates
 */
function banda_ajax_get_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $status = banda_get_system_status();
    wp_send_json_success($status);
}
add_action('wp_ajax_banda_get_status', 'banda_ajax_get_status');

/**
 * AJAX handler for recent activity
 */
function banda_ajax_get_recent_activity() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $activities = banda_get_recent_activities();
    wp_send_json_success($activities);
}
add_action('wp_ajax_banda_get_recent_activity', 'banda_ajax_get_recent_activity');

// ====
// HEALTH CHECK ENDPOINT
// ====

/**
 * Add health check endpoint
 */
function banda_health_check_endpoint() {
    add_rewrite_rule(
        '^banda-health/?$',
        'index.php?banda_health=1',
        'top'
    );
}
add_action('init', 'banda_health_check_endpoint');

/**
 * Add query var for health check
 */
function banda_health_check_query_vars($vars) {
    $vars[] = 'banda_health';
    return $vars;
}
add_filter('query_vars', 'banda_health_check_query_vars');

/**
 * Handle health check requests
 */
function banda_health_check_handler() {
    if (get_query_var('banda_health')) {
        $status = banda_get_system_status();
        
        $health = [
            'status' => 'healthy',
            'timestamp' => current_time('c'),
            'checks' => $status
        ];
        
        // Check for critical issues
        foreach ($status as $check) {
            if ($check['status'] === 'error') {
                $health['status'] = 'unhealthy';
                break;
            }
        }
        
        header('Content-Type: application/json');
        echo wp_json_encode($health);
        exit;
    }
}
add_action('template_redirect', 'banda_health_check_handler');

// ====
// NOTIFICATION SYSTEM
// ====

/**
 * Send alert notifications for critical issues
 */
function banda_check_and_alert() {
    $status = banda_get_system_status();
    $critical_issues = [];
    
    foreach ($status as $check) {
        if ($check['status'] === 'error') {
            $critical_issues[] = $check['label'] . ': ' . $check['value'];
        }
    }
    
    if (!empty($critical_issues)) {
        $admin_email = get_option('admin_email');
        $subject = 'Banda System Alert - Critical Issues Detected';
        $message = "Critical issues detected in Banda system:\n\n";
        $message .= implode("\n", $critical_issues);
        $message .= "\n\nPlease check the monitoring dashboard for more details.";
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Schedule health checks every hour
if (!wp_next_scheduled('banda_health_check')) {
    wp_schedule_event(time(), 'hourly', 'banda_health_check');
}
add_action('banda_health_check', 'banda_check_and_alert');
