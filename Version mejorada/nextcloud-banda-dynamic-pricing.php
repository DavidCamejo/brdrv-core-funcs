<?php
/**
 * PMPro Dynamic Pricing para Nextcloud Banda - VERSIÓN SIMPLY CODE v2.5
 * 
 * Versión corregida: Precio base incluye 2 usuarios + 1TB
 * 
 * @version 2.6.0
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// ====
// CONFIGURACIÓN GLOBAL Y CONSTANTES - ACTUALIZADA
// ====

define('NEXTCLOUD_BANDA_PLUGIN_VERSION', '2.6.0');
define('NEXTCLOUD_BANDA_CACHE_GROUP', 'nextcloud_banda_dynamic');
define('NEXTCLOUD_BANDA_CACHE_EXPIRY', HOUR_IN_SECONDS);

/**
 * Configuración centralizada - CORREGIDA para 2 usuarios base
 */
function nextcloud_banda_get_config($key = null) {
    static $config = null;
    
    if ($config === null) {
        $config = [
            'allowed_levels' => [2], // ID del nivel Nextcloud Banda
            'price_per_tb' => 70.00, // Precio por TB adicional
            'price_per_additional_user' => 10.00, // Precio por usuario adicional (a partir del 3er usuario)
            'base_users_included' => 2, // Usuarios incluidos en precio base
            'base_storage_included' => 1, // TB incluidos en precio base
            'min_users' => 2,
            'max_users' => 20,
            'min_storage' => 1,
            'max_storage' => 20,
            'frequency_multipliers' => [
                'monthly' => 1.0,
                'semiannual' => 5.7,
                'annual' => 10.8,
                'biennial' => 20.4,
                'triennial' => 28.8,
                'quadrennial' => 36.0,
                'quinquennial' => 42.0
            ],
            'storage_options' => [
                '1tb' => '1 Terabyte', '2tb' => '2 Terabytes', '3tb' => '3 Terabytes',
                '4tb' => '4 Terabytes', '5tb' => '5 Terabytes', '6tb' => '6 Terabytes',
                '7tb' => '7 Terabytes', '8tb' => '8 Terabytes', '9tb' => '9 Terabytes',
                '10tb' => '10 Terabytes', '15tb' => '15 Terabytes', '20tb' => '20 Terabytes'
            ],
            'user_options' => [
                '2' => '2 usuários (incluídos)',
                '3' => '3 usuários',
                '4' => '4 usuários',
                '5' => '5 usuários',
                '6' => '6 usuários',
                '7' => '7 usuários',
                '8' => '8 usuários',
                '9' => '9 usuários',
                '10' => '10 usuários',
                '15' => '15 usuários',
                '20' => '20 usuários'
            ]
        ];
    }
    
    return $key ? ($config[$key] ?? null) : $config;
}

// ====
// SISTEMA DE LOGGING
// ====

function nextcloud_banda_log($level, $message, $context = []) {
    static $log_level = null;
    
    if ($log_level === null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_level = 4; // DEBUG
        } elseif (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_level = 3; // INFO
        } else {
            $log_level = 1; // ERROR only
        }
    }
    
    $levels = [1 => 'ERROR', 2 => 'WARNING', 3 => 'INFO', 4 => 'DEBUG'];
    
    if ($level > $log_level) return;
    
    $log_message = sprintf(
        '[PMPro Dyn Banda %s] %s',
        $levels[$level],
        $message
    );
    
    if (!empty($context)) {
        $log_message .= ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    
    error_log($log_message);
}

function nextcloud_banda_log_error($message, $context = []) {
    nextcloud_banda_log(1, $message, $context);
}

function nextcloud_banda_log_info($message, $context = []) {
    nextcloud_banda_log(3, $message, $context);
}

function nextcloud_banda_log_debug($message, $context = []) {
    nextcloud_banda_log(4, $message, $context);
}

// ====
// SISTEMA DE CACHÉ
// ====

function nextcloud_banda_cache_get($key, $default = false) {
    $cached = wp_cache_get($key, NEXTCLOUD_BANDA_CACHE_GROUP);
    if ($cached !== false) {
        nextcloud_banda_log_debug("Cache hit for key: {$key}");
        return $cached;
    }
    
    nextcloud_banda_log_debug("Cache miss for key: {$key}");
    return $default;
}

function nextcloud_banda_cache_set($key, $data, $expiry = NEXTCLOUD_BANDA_CACHE_EXPIRY) {
    $result = wp_cache_set($key, $data, NEXTCLOUD_BANDA_CACHE_GROUP, $expiry);
    nextcloud_banda_log_debug("Cache set for key: {$key}", ['success' => $result]);
    return $result;
}

function nextcloud_banda_invalidate_user_cache($user_id) {
    $keys = [
        "banda_config_{$user_id}",
        "pmpro_membership_{$user_id}",
        "last_payment_date_{$user_id}",
        "used_space_{$user_id}"
    ];
    
    foreach ($keys as $key) {
        wp_cache_delete($key, NEXTCLOUD_BANDA_CACHE_GROUP);
    }
    
    nextcloud_banda_log_info("User cache invalidated", ['user_id' => $user_id]);
}

// ====
// FUNCIONES DE API DE NEXTCLOUD - CORREGIDA
// ====

/**
 * Obtiene el espacio usado por un grupo desde la API de Nextcloud
 * Utiliza variables de entorno para conectarse a la API
 */
function nextcloud_banda_api_get_group_used_space_mb($user_id) {
    // Obtener las constantes de la URL y la API de Nextcloud
    $site_url = get_option('siteurl');
    $nextcloud_api_url = 'https://cloud.' . parse_url($site_url, PHP_URL_HOST);

    // Obtener credenciales de variables de entorno
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    // Verificar que las credenciales estén disponibles - CORREGIDO
    if (empty($nextcloud_api_admin) || empty($nextcloud_api_pass)) {
        nextcloud_banda_log_error('Las credenciales de la API de Nextcloud no están definidas en variables de entorno.');
        return false;
    }

    // Obtener el nombre de usuario de WordPress, que se usará como el ID del grupo en Nextcloud
    $wp_user = get_userdata($user_id);
    if (!$wp_user) {
        nextcloud_banda_log_error("No se pudo encontrar el usuario de WordPress con ID: {$user_id}");
        return false;
    }
    $group_id = 'banda-' . $user_id;

    // Argumentos base para las peticiones a la API
    $api_args = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($nextcloud_api_admin . ':' . $nextcloud_api_pass),
            'OCS-APIRequest' => 'true',
            'Accept' => 'application/json',
        ],
        'timeout' => 20, // Aumentar el timeout a 20 segundos por si la API es lenta
    ];

    // --- PASO 1: Obtener la lista de usuarios del grupo ---
    $users_url = sprintf('%s/ocs/v2.php/cloud/groups/%s/users', $nextcloud_api_url, urlencode($group_id));
    $response_users = wp_remote_get($users_url, $api_args);

    if (is_wp_error($response_users)) {
        nextcloud_banda_log_error('Error en la conexión a la API de Nextcloud (obteniendo usuarios)', ['error' => $response_users->get_error_message()]);
        return false;
    }

    $status_code_users = wp_remote_retrieve_response_code($response_users);
    if ($status_code_users !== 200) {
        nextcloud_banda_log_error("La API de Nextcloud devolvió un error al obtener usuarios del grupo '{$group_id}'", ['status_code' => $status_code_users]);
        return false;
    }

    $users_body = wp_remote_retrieve_body($response_users);
    $users_data = json_decode($users_body, true);

    if (empty($users_data['ocs']['data']['users'])) {
        nextcloud_banda_log_info("El grupo '{$group_id}' no tiene usuarios o no existe en Nextcloud. Se devuelve 0MB.");
        return 0.0;
    }

    $nextcloud_user_ids = $users_data['ocs']['data']['users'];
    $total_used_bytes = 0;

    // --- PASO 2: Obtener el espacio usado por cada usuario y sumarlo ---
    foreach ($nextcloud_user_ids as $nc_user_id) {
        $user_detail_url = sprintf('%s/ocs/v2.php/cloud/users/%s', $nextcloud_api_url, urlencode($nc_user_id));
        $response_user = wp_remote_get($user_detail_url, $api_args);

        if (is_wp_error($response_user) || wp_remote_retrieve_response_code($response_user) !== 200) {
            nextcloud_banda_log_error("No se pudo obtener la información del usuario de Nextcloud: {$nc_user_id}");
            continue; // Saltar a la siguiente iteración
        }

        $user_body = wp_remote_retrieve_body($response_user);
        $user_data = json_decode($user_body, true);

        if (isset($user_data['ocs']['data']['quota']['used'])) {
            $total_used_bytes += (int) $user_data['ocs']['data']['quota']['used'];
        }
    }
    
    // --- PASO 3: Convertir bytes a Megabytes ---
    $total_used_mb = $total_used_bytes / (1024 * 1024);

    nextcloud_banda_log_debug("Cálculo de espacio finalizado para el grupo '{$group_id}'", [
        'total_bytes' => $total_used_bytes,
        'total_mb' => $total_used_mb,
        'users_count' => count($nextcloud_user_ids)
    ]);

    return $total_used_mb;
}

// ====
// VERIFICACIÓN DE DEPENDENCIAS
// ====

function nextcloud_banda_check_dependencies() {
    static $dependencies_checked = false;
    static $dependencies_ok = false;
    
    if ($dependencies_checked) {
        return $dependencies_ok;
    }
    
    $missing_plugins = [];

    if (!function_exists('pmprorh_add_registration_field')) {
        $missing_plugins[] = 'PMPro Register Helper';
        nextcloud_banda_log_error('PMPro Register Helper functions not found');
    }

    if (!function_exists('pmpro_getOption')) {
        $missing_plugins[] = 'Paid Memberships Pro';
        nextcloud_banda_log_error('PMPro core functions not found');
    }

    if (!class_exists('PMProRH_Field')) {
        $missing_plugins[] = 'PMProRH_Field class';
        nextcloud_banda_log_error('PMProRH_Field class not available');
    }

    if (!empty($missing_plugins) && is_admin() && current_user_can('manage_options')) {
        add_action('admin_notices', function() use ($missing_plugins) {
            $plugins_list = implode(', ', $missing_plugins);
            printf(
                '<div class="notice notice-error"><p><strong>PMPro Banda Dynamic:</strong> Los siguientes plugins son requeridos: %s</p></div>',
                esc_html($plugins_list)
            );
        });
    }

    $dependencies_ok = empty($missing_plugins);
    $dependencies_checked = true;
    
    return $dependencies_ok;
}

// ====
// FUNCIONES AUXILIARES - SECCIÓN ACTUALIZADA
// ====

function nextcloud_banda_get_used_space_tb($user_id) {
    $cache_key = "used_space_{$user_id}";
    $cached = nextcloud_banda_cache_get($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    // Llamada a la función de API real (ya no es TODO)
    $used_space_mb = nextcloud_banda_api_get_group_used_space_mb($user_id);
    
    // Si la llamada a la API falla (devuelve false), se registra el error y se devuelve 0 para no romper el cálculo.
    if ($used_space_mb === false) {
        nextcloud_banda_log_error("Fallo crítico al obtener el espacio usado desde la API para user_id: {$user_id}. Se utilizará 0 como valor por defecto.");
        $used_space_mb = 0;
    }
    
    // Convierte el valor de MB a TB y redondea a 2 decimales
    $used_space_tb = round($used_space_mb / 1024, 2);
    
    // Guarda el resultado en caché por 5 minutos (300 segundos) para no sobrecargar la API
    nextcloud_banda_cache_set($cache_key, $used_space_tb, 300);
    
    nextcloud_banda_log_debug("Espacio calculado desde API para user {$user_id}", [
        'used_space_mb' => $used_space_mb,
        'used_space_tb' => $used_space_tb
    ]);
    
    return $used_space_tb;
}

function nextcloud_banda_get_current_level_id() {
    static $cached_level_id = null;
    
    if ($cached_level_id !== null) {
        return $cached_level_id;
    }
    
    $detectors = [
        'global_checkout_level' => function() {
            global $pmpro_checkout_level;
            return isset($pmpro_checkout_level->id) ? (int)$pmpro_checkout_level->id : 0;
        },
        'get_level' => function() {
            return !empty($_GET['level']) ? (int)sanitize_text_field($_GET['level']) : 0;
        },
        'get_pmpro_level' => function() {
            return !empty($_GET['pmpro_level']) ? (int)sanitize_text_field($_GET['pmpro_level']) : 0;
        },
        'post_level' => function() {
            return !empty($_POST['level']) ? (int)sanitize_text_field($_POST['level']) : 0;
        },
        'post_pmpro_level' => function() {
            return !empty($_POST['pmpro_level']) ? (int)sanitize_text_field($_POST['pmpro_level']) : 0;
        },
        'global_level' => function() {
            global $pmpro_level;
            return isset($pmpro_level->id) ? (int)$pmpro_level->id : 0;
        },
        'session_level' => function() {
            return !empty($_SESSION['pmpro_level']) ? (int)$_SESSION['pmpro_level'] : 0;
        }
    ];
    
    foreach ($detectors as $source => $detector) {
        $level_id = $detector();
        if ($level_id > 0) {
            nextcloud_banda_log_debug("Level ID detected from {$source}: {$level_id}");
            $cached_level_id = $level_id;
            return $level_id;
        }
    }
    
    $cached_level_id = 0;
    return 0;
}

// ====
// CAMPOS DINÁMICOS - CORREGIDOS
// ====

function nextcloud_banda_add_dynamic_fields() {
    static $fields_added = false;
    
    if ($fields_added) {
        return true;
    }
    
    if (!nextcloud_banda_check_dependencies()) {
        return false;
    }
    
    $current_level_id = nextcloud_banda_get_current_level_id();
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    if (!in_array($current_level_id, $allowed_levels, true)) {
        nextcloud_banda_log_info("Level {$current_level_id} not in allowed levels, skipping fields");
        return false;
    }
    
    try {
        $config = nextcloud_banda_get_config();
        $fields = [];
        
        // Campo de almacenamiento
        $fields[] = new PMProRH_Field(
            'storage_space', // SIN prefijo banda_
            'select',
            [
                'label' => 'Espaço de armazenamento',
                'options' => $config['storage_options'],
                'profile' => true,
                'required' => false,
                'memberslistcsv' => true,
                'addmember' => true,
                'location' => 'after_level'
            ]
        );
        
        // Campo de número de usuarios
        $fields[] = new PMProRH_Field(
            'num_users', // SIN prefijo banda_
            'select',
            [
                'label' => 'Número de usuários',
                'options' => $config['user_options'],
                'profile' => true,
                'required' => false,
                'memberslistcsv' => true,
                'addmember' => true,
                'location' => 'after_level'
            ]
        );
        
        // Campo de frecuencia
        $frequency_options = [
            'monthly' => 'Mensal',
            'semiannual' => 'Semestral (-5%)',
            'annual' => 'Anual (-10%)',
            'biennial' => 'Bienal (-15%)',
            'triennial' => 'Trienal (-20%)',
            'quadrennial' => 'Quadrienal (-25%)',
            'quinquennial' => 'Quinquenal (-30%)'
        ];
        
        $fields[] = new PMProRH_Field(
            'payment_frequency', // SIN prefijo banda_
            'select',
            [
                'label' => 'Frequência de pagamento',
                'options' => $frequency_options,
                'profile' => true,
                'required' => false,
                'memberslistcsv' => true,
                'addmember' => true,
                'location' => 'after_level'
            ]
        );
        
        // Campo de precio total
        $fields[] = new PMProRH_Field(
            'total_price_display',
            'text',
            [
                'label' => 'Preço total',
                'profile' => false,
                'required' => false,
                'memberslistcsv' => false,
                'addmember' => false,
                'readonly' => true,
                'location' => 'after_level',
                'showrequired' => false,
                'divclass' => 'pmpro_checkout-field-price-display',
                'default' => 'R$ 0,00'
            ]
        );
        
        // Añadir campos
        foreach($fields as $field) {
            pmprorh_add_registration_field('Configuração do plano', $field);
        }
        
        $fields_added = true;
        
        nextcloud_banda_log_info("Dynamic fields added successfully (fixed field names)", [
            'level_id' => $current_level_id,
            'fields_count' => count($fields)
        ]);
        
        return true;
        
    } catch (Exception $e) {
        nextcloud_banda_log_error('Exception adding dynamic fields', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return false;
    }
}

// ====
// CÁLCULOS DE PRECIO - CORREGIDOS PARA 2 USUARIOS BASE
// ====

/**
 * Calcula el precio total - CORREGIDO para 2 usuarios base
 */
function nextcloud_banda_calculate_pricing($storage_space, $num_users, $payment_frequency, $base_price) {
    if (empty($storage_space) || empty($num_users) || empty($payment_frequency)) {
        nextcloud_banda_log_error('Missing parameters for price calculation');
        return $base_price;
    }
    
    // Verificar caché
    $cache_key = "pricing_{$storage_space}_{$num_users}_{$payment_frequency}_{$base_price}";
    $cached_price = nextcloud_banda_cache_get($cache_key);
    if ($cached_price !== false) {
        return $cached_price;
    }
    
    $config = nextcloud_banda_get_config();
    $price_per_tb = $config['price_per_tb'];
    $price_per_user = $config['price_per_additional_user'];
    $base_users_included = $config['base_users_included'];
    $base_storage_included = $config['base_storage_included'];
    
    // Calcular precio de almacenamiento (1TB incluido en base_price)
    $storage_tb = (int)str_replace('tb', '', $storage_space);
    $additional_tb = max(0, $storage_tb - $base_storage_included);
    $storage_price = $base_price + ($price_per_tb * $additional_tb);
    
    // Calcular precio por usuarios (2 usuarios incluidos en base_price) - CORREGIDO
    $additional_users = max(0, (int)$num_users - $base_users_included);
    $user_price = $price_per_user * $additional_users;
    
    // Precio combinado
    $combined_price = $storage_price + $user_price;
    
    // Aplicar multiplicador de frecuencia
    $multipliers = $config['frequency_multipliers'];
    $frequency_multiplier = $multipliers[$payment_frequency] ?? 1.0;
    
    // Calcular precio total
    $total_price = ceil($combined_price * $frequency_multiplier);
    
    // Guardar en caché
    nextcloud_banda_cache_set($cache_key, $total_price, 300);
    
    nextcloud_banda_log_debug('Price calculated with storage and users (2 users base)', [
        'storage_space' => $storage_space,
        'storage_tb' => $storage_tb,
        'additional_tb' => $additional_tb,
        'num_users' => $num_users,
        'additional_users' => $additional_users,
        'payment_frequency' => $payment_frequency,
        'storage_price' => $storage_price,
        'user_price' => $user_price,
        'total_price' => $total_price
    ]);
    
    return $total_price;
}

function nextcloud_banda_configure_billing_period($level, $payment_frequency, $total_price) {
    if (empty($level) || !is_object($level)) {
        nextcloud_banda_log_error('Invalid level object provided');
        return $level;
    }
    
    $billing_cycles = [
        'monthly' => ['number' => 1, 'period' => 'Month'],
        'semiannual' => ['number' => 6, 'period' => 'Month'],
        'annual' => ['number' => 12, 'period' => 'Month'],
        'biennial' => ['number' => 24, 'period' => 'Month'],
        'triennial' => ['number' => 36, 'period' => 'Month'],
        'quadrennial' => ['number' => 48, 'period' => 'Month'],
        'quinquennial' => ['number' => 60, 'period' => 'Month']
    ];
    
    $cycle_config = $billing_cycles[$payment_frequency] ?? $billing_cycles['monthly'];
    
    $level->cycle_number = $cycle_config['number'];
    $level->cycle_period = $cycle_config['period'];
    $level->billing_amount = $total_price;
    $level->initial_payment = $total_price;
    $level->trial_amount = 0;
    $level->trial_limit = 0;
    $level->recurring = true;
    
    return $level;
}

// ====
// HOOK PRINCIPAL DE MODIFICACIÓN DE PRECIO - CORREGIDO
// ====

function nextcloud_banda_modify_level_pricing($level) {
    if (!empty($level->_nextcloud_banda_applied)) {
        return $level;
    }
    
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    if (!in_array((int)$level->id, $allowed_levels, true)) {
        return $level;
    }

    // CORREGIDO: Buscar los campos SIN prefijo banda_
    $required_fields = ['storage_space', 'num_users', 'payment_frequency'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            return $level;
        }
    }

    try {
        // Sanitizar entrada
        $storage_space = sanitize_text_field($_POST['storage_space']);
        $num_users = (int)sanitize_text_field($_POST['num_users']);
        $payment_frequency = sanitize_text_field($_POST['payment_frequency']);

        // Obtener precio base original
        $original_level = pmpro_getLevel($level->id);
        $base_price = $original_level ? (float)$original_level->initial_payment : (float)$level->initial_payment;

        // Calcular precio total
        $total_price = nextcloud_banda_calculate_pricing($storage_space, $num_users, $payment_frequency, $base_price);

        // Aplicar configuración
        $level->initial_payment = $total_price;
        $level = nextcloud_banda_configure_billing_period($level, $payment_frequency, $total_price);
        $level->_nextcloud_banda_applied = true;

        nextcloud_banda_log_info('Level pricing modified with storage and users (2 users base)', [
            'level_id' => $level->id,
            'final_price' => $total_price,
            'storage_space' => $storage_space,
            'num_users' => $num_users,
            'payment_frequency' => $payment_frequency
        ]);

    } catch (Exception $e) {
        nextcloud_banda_log_error('Exception in pricing modification', [
            'message' => $e->getMessage()
        ]);
    }

    return $level;
}

// ====
// GUARDADO DE CONFIGURACIÓN - CORREGIDO
// ====

function nextcloud_banda_save_configuration($user_id, $morder) {
    if (!$user_id || !$morder) {
        return;
    }

    // CORREGIDO: Buscar los campos SIN prefijo banda_
    $required_fields = ['storage_space', 'num_users', 'payment_frequency'];
    $config_data = [];

    foreach ($required_fields as $field) {
        if (!isset($_REQUEST[$field])) {
            return;
        }
        $config_data[$field] = sanitize_text_field($_REQUEST[$field]);
    }

    $config = array_merge($config_data, [
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'level_id' => $morder->membership_id,
        'final_amount' => $morder->InitialPayment,
        'order_id' => $morder->id ?? null,
        'version' => NEXTCLOUD_BANDA_PLUGIN_VERSION
    ]);

    $config_json = wp_json_encode($config);
    update_user_meta($user_id, 'nextcloud_banda_config', $config_json);
    
    // Invalidar caché
    nextcloud_banda_invalidate_user_cache($user_id);

    nextcloud_banda_log_info('Configuration saved with storage and users', [
        'user_id' => $user_id,
        'config' => $config
    ]);
}

// ====
// LOCALIZACIÓN DE SCRIPT JS - ACTUALIZADA PARA 2 USUARIOS BASE
// ====

/**
 * Localización de script JS con datos optimizados - ACTUALIZADA
 */
function nextcloud_banda_localize_pricing_script() {
    // Verificar páginas relevantes
    $is_relevant_page = false;
    
    if (function_exists('pmpro_getOption')) {
        $checkout_page = pmpro_getOption('checkout_page_slug');
        $billing_page = pmpro_getOption('billing_page_slug');
        $account_page = pmpro_getOption('account_page_slug');
        
        $is_relevant_page = (
            (!empty($checkout_page) && is_page($checkout_page)) ||
            (!empty($billing_page) && is_page($billing_page)) ||
            (!empty($account_page) && is_page($account_page))
        );
    }

    if (!$is_relevant_page) {
        return;
    }

    // Verificar nivel permitido
    $current_level = nextcloud_banda_get_current_level_id();
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    if (!in_array($current_level, $allowed_levels, true)) {
        return;
    }

    // Obtener datos del nivel actual
    $base_price = 0;
    if ($current_level > 0) {
        $level = pmpro_getLevel($current_level);
        $base_price = $level ? (float)$level->initial_payment : 0;
    }

    // Datos del usuario actual
    $current_storage = null;
    $current_users = null;
    $current_frequency = null;
    $has_previous_config = false;
    $used_space_tb = 0;
    $next_payment_date = null;

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();

        // Comprobar si tiene una membership BANDA activa (evita falsos positivos)
        $user_levels = function_exists('pmpro_getMembershipLevelsForUser') ? pmpro_getMembershipLevelsForUser($user_id) : [];
        $allowed_levels = nextcloud_banda_get_config('allowed_levels') ?: [];
        $has_banda_membership = false;

        if (!empty($user_levels)) {
            foreach ($user_levels as $l) {
                if (in_array((int)$l->id, $allowed_levels, true)) {
                    $has_banda_membership = true;
                    break;
                }
            }
        }

        // Obtener configuración guardada sólo si tiene membership Banda y user_meta existe
        $config_json = get_user_meta($user_id, 'nextcloud_banda_config', true);
        if ($has_banda_membership && !empty($config_json)) {
            $config = json_decode($config_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $current_storage = $config['storage_space'] ?? null;
                $current_users = isset($config['num_users']) ? (int)$config['num_users'] : null;
                $current_frequency = $config['payment_frequency'] ?? null;
                $has_previous_config = true;
            }
        }

        // Intentar obtener fecha de próximo pago desde PMPro (si aplica)
        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $member_level = pmpro_getMembershipLevelForUser($user_id);
            if (!empty($member_level) && !empty($member_level->enddate) && $member_level->enddate !== '0000-00-00 00:00:00') {
                // Formato ISO 8601 para que JS lo parsee con `new Date(...)`
                $next_payment_date = date('c', strtotime($member_level->enddate));
            }
        }

        // Espacio usado desde Nextcloud (siempre se puede consultar)
        $used_space_tb = nextcloud_banda_get_used_space_tb($user_id);
    }

    // Datos localizados (añadir has_previous_config y current_frequency)
    $localization_data = [
        'level_id' => $current_level,
        'base_price' => $base_price,
        'price_per_tb' => nextcloud_banda_get_config('price_per_tb'),
        'price_per_user' => nextcloud_banda_get_config('price_per_additional_user'), // si tu key usa otro nombre ajústalo
        'base_users_included' => nextcloud_banda_get_config('base_users_included'),
        'base_storage_included' => nextcloud_banda_get_config('base_storage_included'),
        'currency_symbol' => 'R$',
        'current_storage' => $current_storage,           // null si no hay config previa
        'current_users' => $current_users,               // null si no hay config previa
        'current_frequency' => $current_frequency,       // null si no hay config previa
        'has_previous_config' => $has_previous_config,   // bandera explícita
        'next_payment_date' => $next_payment_date,
        'used_space_tb' => $used_space_tb,
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'version' => NEXTCLOUD_BANDA_PLUGIN_VERSION,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('nextcloud_banda_nonce')
    ];

    // Handles posibles que Simply Code podría generar
    $possible_handles = [
        'simply-snippet-nextcloud-banda-dynamic-pricing', // Más probable
        'simply-code-nextcloud-banda-dynamic-pricing',
        'nextcloud-banda-dynamic-pricing',
        'banda-dynamic-pricing'
    ];
    
    // Intentar localizar con múltiples handles
    $localized = false;
    foreach ($possible_handles as $handle) {
        if (wp_script_is($handle, 'enqueued') || wp_script_is($handle, 'registered')) {
            wp_localize_script($handle, 'nextcloud_banda_pricing', $localization_data);
            nextcloud_banda_log_info("Script localized successfully with handle: {$handle}");
            $localized = true;
            break;
        }
    }
    
    if (!$localized) {
        nextcloud_banda_log_debug('No suitable script handle found for localization');
    }

    nextcloud_banda_log_info('Script localization completed (2 users base)', [
        'level_id' => $current_level,
        'base_price' => $base_price,
        'localized' => $localized
    ]);
}

// ====
// HOOKS DE ENQUEUE PARA SIMPLY CODE SNIPPETS
// ====

add_action('wp_enqueue_scripts', function() {
    // Solo en páginas relevantes
    if (!function_exists('pmpro_getOption')) return;
    
    $checkout_page = pmpro_getOption('checkout_page_slug');
    $billing_page = pmpro_getOption('billing_page_slug');
    $account_page = pmpro_getOption('account_page_slug');
    
    $is_relevant_page = (
        (!empty($checkout_page) && is_page($checkout_page)) ||
        (!empty($billing_page) && is_page($billing_page)) ||
        (!empty($account_page) && is_page($account_page))
    );

    if (!$is_relevant_page) return;

    // Verificar nivel permitido
    $current_level = nextcloud_banda_get_current_level_id();
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    if (!in_array($current_level, $allowed_levels, true)) return;

    // Enqueue Dashicons (necesario para funcionalidades)
    wp_enqueue_style('dashicons');
    
}, 20); // Prioridad 20 para ejecutar después de PMPro

// ====
// VISUALIZACIÓN DE CONFIGURACIÓN DEL MIEMBRO BANDA
// ====


/**
 * Muestra la configuración actual del plan del miembro Banda en su área de cuenta
 * CORREGIDA para manejar múltiples membresías
 */
function nextcloud_banda_show_member_config() {
    // Verificar que el usuario esté logueado
    $user_id = get_current_user_id();
    if (!$user_id) {
        nextcloud_banda_log_debug('No user logged in for member config display');
        return;
    }

    try {
        // CORREGIDO: Obtener TODAS las membresías del usuario
        $user_levels = pmpro_getMembershipLevelsForUser($user_id);
        
        if (empty($user_levels)) {
            nextcloud_banda_log_debug("No memberships found for user {$user_id}");
            return;
        }

        // CORREGIDO: Buscar una membresía que coincida con niveles permitidos de Banda
        $allowed_levels = nextcloud_banda_get_config('allowed_levels'); // [2]
        $banda_membership = null;
        
        foreach ($user_levels as $level) {
            if (in_array((int)$level->id, $allowed_levels, true)) {
                $banda_membership = $level;
                break; // Encontramos un nivel Banda, salir del loop
            }
        }

        // Si no tiene membresía Banda, no mostrar configuración Banda
        if (!$banda_membership) {
            nextcloud_banda_log_debug("No Banda membership found for user {$user_id}", [
                'user_levels' => array_map(function($l) { return $l->id; }, $user_levels),
                'allowed_levels' => $allowed_levels
            ]);
            return;
        }

        // Usar la membresía Banda encontrada
        $membership = $banda_membership;

        // Obtener configuración del caché o base de datos
        $cache_key = "banda_config_{$user_id}";
        $config_json = nextcloud_banda_cache_get($cache_key);
        
        if ($config_json === false) {
            $config_json = get_user_meta($user_id, 'nextcloud_banda_config', true);
            if ($config_json) {
                nextcloud_banda_cache_set($cache_key, $config_json);
            }
        }

        // Si no hay configuración, no mostrar nada
        if (!$config_json) {
            nextcloud_banda_log_debug("No Banda configuration found for user {$user_id}");
            return;
        }

        $config = json_decode($config_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            nextcloud_banda_log_error("JSON decode error for user {$user_id}", ['error' => json_last_error_msg()]);
            return;
        }

        // Obtener configuraciones para las etiquetas
        $storage_options = nextcloud_banda_get_config('storage_options');
        $user_options = nextcloud_banda_get_config('user_options');
        
        $frequency_labels = [
            'monthly' => 'Mensal',
            'semiannual' => 'Semestral (-5%)',
            'annual' => 'Anual (-10%)',
            'biennial' => 'Bienal (-15%)',
            'triennial' => 'Trienal (-20%)',
            'quadrennial' => 'Quadrienal (-25%)',
            'quinquennial' => 'Quinquenal (-30%)'
        ];

        // Obtener espacio usado (desde API real)
        $used_space_tb = nextcloud_banda_get_used_space_tb($user_id);
        
        // Calcular próximo pago si está disponible
        $next_payment_date = '';
        if (isset($membership->enddate) && !empty($membership->enddate)) {
            $next_payment_date = date_i18n('d/m/Y', strtotime($membership->enddate));
        } elseif (isset($membership->next_payment_date) && !empty($membership->next_payment_date)) {
            $next_payment_date = date_i18n('d/m/Y', strtotime($membership->next_payment_date));
        }

        // Calcular información adicional
        $base_users_included = nextcloud_banda_get_config('base_users_included');
        $base_storage_included = nextcloud_banda_get_config('base_storage_included');
        $current_users = (int)($config['num_users'] ?? $base_users_included);
        $additional_users = max(0, $current_users - $base_users_included);

        nextcloud_banda_log_debug("Displaying Banda member config for user {$user_id}", [
            'membership_id' => $membership->id,
            'membership_name' => $membership->name,
            'storage' => $config['storage_space'] ?? 'unknown',
            'users' => $config['num_users'] ?? 'unknown',
            'frequency' => $config['payment_frequency'] ?? 'unknown'
        ]);

        ?>
        <div class="pmpro_account-profile-field">
            <h3>Detalhes do plano <strong><?php echo esc_html($membership->name); ?></strong></h3>
            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ff6b35;">
                
                <!-- Información de almacenamiento -->
                <div style="margin-bottom: 15px;">
                    <p><strong>🗄️ Armazenamento:</strong> 
                        <?php echo esc_html($storage_options[$config['storage_space']] ?? $config['storage_space']); ?>
                    </p>
                    
                    <?php if ($used_space_tb > 0): ?>
                    <p style="margin-left: 20px; color: #666; font-size: 0.9em;">
                        <em>Espaço usado: <?php echo number_format_i18n($used_space_tb, 2); ?> TB</em>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Información de usuarios -->
                <div style="margin-bottom: 15px;">
                    <p><strong>👥 Usuários:</strong> 
                        <?php echo esc_html($user_options[$current_users] ?? "{$current_users} usuários"); ?>
                    </p>
                    
                    <?php if ($additional_users > 0): ?>
                    <p style="margin-left: 20px; color: #666; font-size: 0.9em;">
                        <em><?php echo $base_users_included; ?> incluídos + <?php echo $additional_users; ?> adicionais</em>
                    </p>
                    <?php else: ?>
                    <p style="margin-left: 20px; color: #666; font-size: 0.9em;">
                        <em><?php echo $base_users_included; ?> usuários incluídos no plano base</em>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Información de frecuencia de pago -->
                <div style="margin-bottom: 15px;">
                    <p><strong>💳 Plano de Pagamento:</strong> 
                        <?php echo esc_html($frequency_labels[$config['payment_frequency']] ?? $config['payment_frequency']); ?>
                    </p>
                </div>

                <!-- Precio actual -->
                <?php if (!empty($config['final_amount'])): ?>
                <div style="margin-bottom: 15px;">
                    <p><strong>💰 Valor do plano:</strong> 
                        R$ <?php echo number_format_i18n((float)$config['final_amount'], 2); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Información de fechas -->
                <div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">
                    <?php if (!empty($config['created_at'])): ?>
                    <p style="font-size: 0.9em; color: #666;">
                        <strong>📅 Configurado em:</strong> 
                        <?php echo date_i18n('d/m/Y H:i', strtotime($config['created_at'])); ?>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($next_payment_date)): ?>
                    <p style="font-size: 0.9em; color: #666;">
                        <strong>🔄 Próximo pagamento:</strong> 
                        <?php echo esc_html($next_payment_date); ?>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($config['updated_at']) && $config['updated_at'] !== $config['created_at']): ?>
                    <p style="font-size: 0.9em; color: #666;">
                        <strong>✏️ Última modificação:</strong> 
                        <?php echo date_i18n('d/m/Y H:i', strtotime($config['updated_at'])); ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Información técnica adicional -->
                <?php if (!empty($config['version']) || !empty($config['order_id'])): ?>
                <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                    <?php if (!empty($config['order_id'])): ?>
                    <p style="font-size: 0.8em; color: #999;">
                        ID do pedido: <?php echo esc_html($config['order_id']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($config['version'])): ?>
                    <p style="font-size: 0.8em; color: #999;">
                        Grupo Nextcloud: <strong>banda-<?php echo esc_html($user_id); ?></strong>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Información del grupo Nextcloud (si está disponible) -->
                <?php if (function_exists('get_userdata')): 
                    $wp_user = get_userdata($user_id);
                    if ($wp_user): ?>
                <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                    <p style="font-size: 0.6em; color: #999;">
                        Versão da configuração: <?php echo esc_html($config['version']); ?>
                    </p>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>
        <?php

        nextcloud_banda_log_info("Banda member config displayed successfully for user {$user_id}");

    } catch (Exception $e) {
        nextcloud_banda_log_error('Exception in nextcloud_banda_show_member_config', [
            'user_id' => $user_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        // Mostrar mensaje de error amigable si estamos en modo debug
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            echo '<div class="pmpro_account-profile-field">';
            echo '<p style="color: red;"><strong>Erro:</strong> Não foi possível carregar os detalhes do plano Banda.</p>';
            echo '</div>';
        }
    }
}

// ====
// INICIALIZACIÓN Y HOOKS
// ====

// Hooks de inicialización
add_action('plugins_loaded', 'nextcloud_banda_add_dynamic_fields', 25);
add_action('init', 'nextcloud_banda_add_dynamic_fields', 20);
add_action('wp_loaded', 'nextcloud_banda_add_dynamic_fields', 5);

// Hook principal de modificación de precio
add_filter('pmpro_checkout_level', 'nextcloud_banda_modify_level_pricing', 1);

// Hooks de guardado
add_action('pmpro_after_checkout', 'nextcloud_banda_save_configuration', 10, 2);

// HOOK DE LOCALIZACIÓN
add_action('wp_enqueue_scripts', 'nextcloud_banda_localize_pricing_script', 30);

// Hook para mostrar configuración en área de miembros BANDA
add_action('pmpro_account_bullets_bottom', 'nextcloud_banda_show_member_config');

// Invalidación de caché
add_action('pmpro_after_change_membership_level', function($level_id, $user_id) {
    nextcloud_banda_invalidate_user_cache($user_id);
}, 10, 2);

nextcloud_banda_log_info('PMPro Banda Dynamic Pricing loaded for Simply Code (2 users base)', [
    'version' => NEXTCLOUD_BANDA_PLUGIN_VERSION,
    'php_version' => PHP_VERSION
]);
