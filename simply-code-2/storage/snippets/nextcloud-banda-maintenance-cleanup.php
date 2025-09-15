<?php
// @description Nuevo snippet

if (!defined('ABSPATH')) exit;

/**
 * Función de mantenimiento para limpiar datos huérfanos
 * Ejecutar una vez después de implementar los cambios
 */
function nextcloud_banda_maintenance_cleanup() {
    global $wpdb;
    
    // Limpiar metadatos de usuarios que ya no existen
    $wpdb->query("
        DELETE um FROM {$wpdb->usermeta} um
        LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
        WHERE u.ID IS NULL AND um.meta_key LIKE '%nextcloud_banda%'
    ");
    
    // Limpiar configuraciones de usuarios sin membresía activa
    nextcloud_banda_cleanup_inactive_users();
    
    nextcloud_banda_log_info("Mantenimiento completado: datos huérfanos eliminados");
}
