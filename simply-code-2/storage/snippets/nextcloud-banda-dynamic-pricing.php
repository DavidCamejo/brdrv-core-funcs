<?php
/**
 * PMPro Dynamic Pricing para Nextcloud Banda - VERSIÓN SINCRONIZADA v2.7.7
 * 
 * RESPONSABILIDAD: Lógica de checkout, campos dinámicos y cálculos de precio
 * CORREGIDO: Sincronización completa con theme-scripts.php y JavaScript
 * 
 * @version 2.7.7
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// ====
// CONFIGURACIÓN GLOBAL Y CONSTANTES - SINCRONIZADAS
// ====

define('NEXTCLOUD_BANDA_PLUGIN_VERSION', '2.7.7');
define('NEXTCLOUD_BANDA_CACHE_GROUP', 'nextcloud_banda_dynamic');
define('NEXTCLOUD_BANDA_CACHE_EXPIRY', HOUR_IN_SECONDS);

// CORREGIDO: Definir constante que será usada en JavaScript
if (!defined('NEXTCLOUD_BANDA_BASE_PRICE')) {
    define('NEXTCLOUD_BANDA_BASE_PRICE', 70.00); // Precio base del plan (1TB + 2 usuarios)
}

/**
 * FUNCIÓN CRÍTICA - Normaliza configuración Banda
 */
if (!function_exists('normalize_banda_config')) {
    function normalize_banda_config($config_data) {
        if (!is_array($config_data)) {
            return [
                'storage_space' => '1tb',
                'num_users' => 2,
                'payment_frequency' => 'monthly'
            ];
        }

        // Validar y normalizar storage
        $storage_space = sanitize_text_field($config_data['storage_space'] ?? '1tb');
        $valid_storage = ['1tb', '2tb', '3tb', '4tb', '5tb', '6tb', '7tb', '8tb', '9tb', '10tb', '15tb', '20tb'];
        if (!in_array($storage_space, $valid_storage, true)) {
            $storage_space = '1tb';
        }

        // Validar y normalizar usuarios (mínimo 2, máximo 20)
        $num_users = max(2, min(20, intval($config_data['num_users'] ?? 2)));

        // Validar y normalizar frecuencia
        $payment_frequency = sanitize_text_field($config_data['payment_frequency'] ?? 'monthly');
        $valid_frequencies = ['monthly', 'semiannual', 'annual', 'biennial', 'triennial', 'quadrennial', 'quinquennial'];
        if (!in_array($payment_frequency, $valid_frequencies, true)) {
            $payment_frequency = 'monthly';
        }

        return [
            'storage_space' => $storage_space,
            'num_users' => $num_users,
            'payment_frequency' => $payment_frequency
        ];
    }
}

/**
 * Obtiene configuración real del usuario desde múltiples fuentes
 */
function nextcloud_banda_get_user_real_config($user_id, $membership = null) {
    $real_config = [
        'storage_space' => null,
        'num_users' => null,
        'payment_frequency' => null,
        'final_amount' => null,
        'source' => 'none'
    ];

    // 1. Intentar obtener desde configuración guardada (JSON)
    $config_json = get_user_meta($user_id, 'nextcloud_banda_config', true);
    if (!empty($config_json)) {
        $config = json_decode($config_json, true);
        if (is_array($config) && json_last_error() === JSON_ERROR_NONE && !isset($config['auto_created'])) {
            $real_config['storage_space'] = $config['storage_space'] ?? null;
            $real_config['num_users'] = $config['num_users'] ?? null;
            $real_config['payment_frequency'] = $config['payment_frequency'] ?? null;
            $real_config['final_amount'] = $config['final_amount'] ?? null;
            $real_config['source'] = 'saved_config';
            
            nextcloud_banda_log_debug("Real config found from saved JSON for user {$user_id}", $real_config);
            return $real_config;
        }
    }

    // 2. Intentar obtener desde campos personalizados de PMPro Register Helper
    if (function_exists('pmprorh_getProfileField')) {
        $storage_field = pmprorh_getProfileField('storage_space', $user_id);
        $users_field = pmprorh_getProfileField('num_users', $user_id);
        $frequency_field = pmprorh_getProfileField('payment_frequency', $user_id);

        if (!empty($storage_field) || !empty($users_field) || !empty($frequency_field)) {
            $real_config['storage_space'] = $storage_field ?: null;
            $real_config['num_users'] = $users_field ? intval($users_field) : null;
            $real_config['payment_frequency'] = $frequency_field ?: null;
            $real_config['source'] = 'profile_fields';
            
            nextcloud_banda_log_debug("Real config found from profile fields for user {$user_id}", $real_config);
            return $real_config;
        }
    }

    // 3. Intentar obtener desde user_meta directo
    $storage_meta = get_user_meta($user_id, 'storage_space', true);
    $users_meta = get_user_meta($user_id, 'num_users', true);
    $frequency_meta = get_user_meta($user_id, 'payment_frequency', true);

    if (!empty($storage_meta) || !empty($users_meta) || !empty($frequency_meta)) {
        $real_config['storage_space'] = $storage_meta ?: null;
        $real_config['num_users'] = $users_meta ? intval($users_meta) : null;
        $real_config['payment_frequency'] = $frequency_meta ?: null;
        $real_config['source'] = 'user_meta';
        
        nextcloud_banda_log_debug("Real config found from user meta for user {$user_id}", $real_config);
        return $real_config;
    }

    // 4. Intentar deducir desde información de membresía
    if ($membership && !empty($membership->initial_payment)) {
        $real_config['final_amount'] = (float)$membership->initial_payment;
        $real_config['source'] = 'membership_deduction';
        
        nextcloud_banda_log_debug("Config deduced from membership for user {$user_id}", $real_config);
    }

    // Verificar si el usuario tiene una membresía activa
	if (!pmpro_hasMembershipLevel($user_id)) {
		// Forzar valores por defecto si no hay membresía activa
		return [
			'storage_space' => '1tb',
			'num_users' => 2,
			'payment_frequency' => 'monthly',
			'final_amount' => null,
			'source' => 'defaults_no_membership'
		];
	}

    nextcloud_banda_log_debug("No real config found for user {$user_id}, returning empty", $real_config);
    return $real_config;
}

/**
 * Configuración centralizada - SINCRONIZADA
 */
function nextcloud_banda_get_config($key = null) {
    static $config = null;

    if ($config === null) {
        $config = [
            'allowed_levels' => [2], // ID del nivel Nextcloud Banda
            'price_per_tb' => 70.00, // Precio por TB adicional
            'price_per_additional_user' => 10.00, // Precio por usuario adicional
            'base_users_included' => 2, // Usuarios incluidos en precio base
            'base_storage_included' => 1, // TB incluidos en precio base
            'base_price_default' => NEXTCLOUD_BANDA_BASE_PRICE, // CORREGIDO: Usar constante
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
        '[PMPro Banda %s] %s',
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
// FUNCIONES DE API DE NEXTCLOUD - CORREGIDAS
// ====

function nextcloud_banda_api_get_group_used_space_mb($user_id) {
    // CORREGIDO: Usar get_option en lugar de hardcoded
    $site_url = get_option('siteurl');
    $nextcloud_api_url = 'https://cloud.' . parse_url($site_url, PHP_URL_HOST);

    // Obtener credenciales de variables de entorno
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    // Verificar que las credenciales estén disponibles
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
        'timeout' => 20,
    ];

    // Obtener la lista de usuarios del grupo
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

    // Obtener el espacio usado por cada usuario y sumarlo
    foreach ($nextcloud_user_ids as $nc_user_id) {
        $user_detail_url = sprintf('%s/ocs/v2.php/cloud/users/%s', $nextcloud_api_url, urlencode($nc_user_id));
        $response_user = wp_remote_get($user_detail_url, $api_args);

        if (is_wp_error($response_user) || wp_remote_retrieve_response_code($response_user) !== 200) {
            nextcloud_banda_log_error("No se pudo obtener la información del usuario de Nextcloud: {$nc_user_id}");
            continue;
        }

        $user_body = wp_remote_retrieve_body($response_user);
        $user_data = json_decode($user_body, true);

        if (isset($user_data['ocs']['data']['quota']['used'])) {
            $total_used_bytes += (int) $user_data['ocs']['data']['quota']['used'];
        }
    }

    // Convertir bytes a Megabytes
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
// FUNCIONES AUXILIARES
// ====

function nextcloud_banda_get_used_space_tb($user_id) {
    $cache_key = "used_space_{$user_id}";
    $cached = nextcloud_banda_cache_get($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    // Llamada a la función de API real
    $used_space_mb = nextcloud_banda_api_get_group_used_space_mb($user_id);

    // Si la llamada a la API falla, usar 0 como valor por defecto
    if ($used_space_mb === false) {
        nextcloud_banda_log_error("Fallo al obtener el espacio usado desde la API para user_id: {$user_id}. Se utilizará 0 como valor por defecto.");
        $used_space_mb = 0;
    }

    // Convierte el valor de MB a TB y redondea a 2 decimales
    $used_space_tb = round($used_space_mb / 1024, 2);

    // Guarda el resultado en caché por 5 minutos
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

    // CORREGIDO: Usar filter_input para mayor seguridad
    $sources = [
        filter_input(INPUT_GET, 'level', FILTER_VALIDATE_INT),
        filter_input(INPUT_GET, 'pmpro_level', FILTER_VALIDATE_INT),
        filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT),
        filter_input(INPUT_POST, 'pmpro_level', FILTER_VALIDATE_INT),
        isset($_SESSION['pmpro_level']) ? (int)$_SESSION['pmpro_level'] : null,
        isset($GLOBALS['pmpro_checkout_level']->id) ? (int)$GLOBALS['pmpro_checkout_level']->id : null,
        isset($GLOBALS['pmpro_level']->id) ? (int)$GLOBALS['pmpro_level']->id : null,
    ];

    foreach ($sources as $source) {
        if ($source > 0) {
            $cached_level_id = $source;
            nextcloud_banda_log_debug("Nivel detectado: {$source}");
            return $source;
        }
    }

    $cached_level_id = 0;
    return 0;
}

// ====
// CAMPOS DINÁMICOS - CORREGIDOS
// ====

// Actualizar la función de campos dinámicos para manejar mejor el estado
function nextcloud_banda_add_dynamic_fields() {
    $user_id = get_current_user_id();
    
    // Verificar membresía activa antes de mostrar campos con datos antiguos
    if ($user_id) {
        $user_levels = pmpro_getMembershipLevelsForUser($user_id);
        $allowed_levels = nextcloud_banda_get_config('allowed_levels');
        $has_active_banda_membership = false;
        
        if (!empty($user_levels)) {
            foreach ($user_levels as $level) {
                if (in_array((int)$level->id, $allowed_levels, true) && 
                    (empty($level->enddate) || $level->enddate === '0000-00-00 00:00:00' || strtotime($level->enddate) > time())) {
                    $has_active_banda_membership = true;
                    break;
                }
            }
        }
        
        // Si no tiene membresía Banda activa, limpiar caché
        if (!$has_active_banda_membership) {
            nextcloud_banda_invalidate_user_cache($user_id);
        }
    }

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
            'storage_space',
            'select',
            [
                'label' => 'Espaço de armazenamento',
                'options' => $config['storage_options'],
                'profile' => true,
                'required' => false,
                'memberslistcsv' => true,
                'addmember' => true,
                'location' => 'after_level',
                'default' => '1tb'
            ]
        );
    
        // Campo de número de usuarios
        $fields[] = new PMProRH_Field(
            'num_users',
            'select',
            [
                'label' => 'Número de usuários',
                'options' => $config['user_options'],
                'profile' => true,
                'required' => false,
                'memberslistcsv' => true,
                'addmember' => true,
                'location' => 'after_level',
                'default' => '2'
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
            'payment_frequency',
            'select',
            [
                'label' => 'Ciclo de pagamento',
                'options' => $frequency_options,
                'profile' => true,
                'required' => false,
                'memberslistcsv' => true,
                'addmember' => true,
                'location' => 'after_level',
                'default' => 'monthly'
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
                'default' => 'R$ ' . number_format(NEXTCLOUD_BANDA_BASE_PRICE, 2, ',', '.')
            ]
        );
    
        // Añadir campos
        foreach($fields as $field) {
            pmprorh_add_registration_field('Configuração do plano', $field);
        }
    
        $fields_added = true;
    
        nextcloud_banda_log_info("Dynamic fields added successfully", [
            'level_id' => $current_level_id,
            'fields_count' => count($fields),
            'base_price' => NEXTCLOUD_BANDA_BASE_PRICE
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
// CÁLCULOS DE PRECIO - CORREGIDOS
// ====

function nextcloud_banda_calculate_pricing($storage_space, $num_users, $payment_frequency, $base_price) {
    if (empty($storage_space) || empty($num_users) || empty($payment_frequency)) {
        nextcloud_banda_log_error('Missing parameters for price calculation');
        return $base_price ?: NEXTCLOUD_BANDA_BASE_PRICE; // CORREGIDO: Usar constante
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

    // CORREGIDO: Asegurar precio base válido
    if ($base_price <= 0) {
        $base_price = NEXTCLOUD_BANDA_BASE_PRICE;
    }

    // Calcular precio de almacenamiento (1TB incluido en base_price)
    $storage_tb = (int)str_replace('tb', '', $storage_space);
    $additional_tb = max(0, $storage_tb - $base_storage_included);
    $storage_price = $base_price + ($price_per_tb * $additional_tb);

    // Calcular precio por usuarios (2 usuarios incluidos en base_price)
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

    nextcloud_banda_log_debug('Price calculated', [
        'storage_space' => $storage_space,
        'storage_tb' => $storage_tb,
        'additional_tb' => $additional_tb,
        'num_users' => $num_users,
        'additional_users' => $additional_users,
        'payment_frequency' => $payment_frequency,
        'base_price' => $base_price,
        'storage_price' => $storage_price,
        'user_price' => $user_price,
        'combined_price' => $combined_price,
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
// HOOK PRINCIPAL DE MODIFICACIÓN DE PRECIO (MODIFICADO CON PRORRATEO)
// ====

function nextcloud_banda_modify_level_pricing($level) {
    if (!empty($level->_nextcloud_banda_applied)) {
        return $level;
    }

    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    if (!in_array((int)$level->id, $allowed_levels, true)) {
        return $level;
    }

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

        // CORREGIDO: Obtener precio base con fallback
        $base_price = NEXTCLOUD_BANDA_BASE_PRICE; // Usar constante como fallback
        
        $original_level = pmpro_getLevel($level->id);
        if ($original_level && !empty($original_level->initial_payment)) {
            $base_price = (float)$original_level->initial_payment;
        } elseif (!empty($level->initial_payment)) {
            $base_price = (float)$level->initial_payment;
        }

        // Calcular precio total
        $total_price = nextcloud_banda_calculate_pricing($storage_space, $num_users, $payment_frequency, $base_price);
        
        // Verificar si es actualización y aplicar prorrateo
        $user_id = get_current_user_id();
        if ($user_id && pmpro_hasMembershipLevel($level->id, $user_id)) {
            // Verificar si es un upgrade
            if (nextcloud_banda_is_plan_upgrade($user_id, $storage_space, $num_users, $payment_frequency)) {
                $prorated_amount = nextcloud_banda_calculate_proration($user_id, $total_price, $payment_frequency);
                
                if ($prorated_amount >= 0) {
                    $total_price = $prorated_amount;
                    
                    nextcloud_banda_log_info('Proration applied for upgrade', [
                        'user_id' => $user_id,
                        'original_price' => nextcloud_banda_calculate_pricing($storage_space, $num_users, $payment_frequency, $base_price),
                        'prorated_price' => $prorated_amount
                    ]);
                }
            }
        }

        // Aplicar configuración
        $level->initial_payment = $total_price;
        $level = nextcloud_banda_configure_billing_period($level, $payment_frequency, $total_price);
        $level->_nextcloud_banda_applied = true;

        nextcloud_banda_log_info('Level pricing modified', [
            'level_id' => $level->id,
            'final_price' => $total_price,
            'storage_space' => $storage_space,
            'num_users' => $num_users,
            'payment_frequency' => $payment_frequency,
            'base_price' => $base_price
        ]);

    } catch (Exception $e) {
        nextcloud_banda_log_error('Exception in pricing modification', [
            'message' => $e->getMessage()
        ]);
    }

    return $level;
}

// ====
// FUNCIONES DE PRORRATEO
// ====

/**
 * Calcula el monto prorrateado basado en días restantes de la suscripción actual
 */
function nextcloud_banda_calculate_proration($user_id, $new_total_price, $payment_frequency) {
    $user_levels = pmpro_getMembershipLevelsForUser($user_id);
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    $current_banda_level = null;
    if (!empty($user_levels)) {
        foreach ($user_levels as $level) {
            if (in_array((int)$level->id, $allowed_levels, true) && 
                (empty($level->enddate) || $level->enddate === '0000-00-00 00:00:00' || strtotime($level->enddate) > time())) {
                $current_banda_level = $level;
                break;
            }
        }
    }
    
    // Si no hay membresía activa, no aplicar prorrateo
    if (!$current_banda_level || empty($current_banda_level->enddate) || $current_banda_level->enddate === '0000-00-00 00:00:00') {
        return $new_total_price;
    }
    
    // Obtener configuración actual
    $current_config = nextcloud_banda_get_user_real_config_improved($user_id, $current_banda_level);
    
    if (empty($current_config['final_amount'])) {
        // Intentar obtener del nivel actual
        $current_config['final_amount'] = (float)$current_banda_level->initial_payment;
    }
    
    $current_amount = $current_config['final_amount'];
    
    if ($current_amount <= 0) {
        return $new_total_price;
    }
    
    // Calcular días totales del ciclo actual
    $cycle_start = strtotime($current_banda_level->startdate);
    $cycle_end = strtotime($current_banda_level->enddate);
    $total_days = ceil(($cycle_end - $cycle_start) / (60 * 60 * 24));
    
    if ($total_days <= 0) {
        return $new_total_price;
    }
    
    // Calcular días restantes
    $days_remaining = ceil(($cycle_end - time()) / (60 * 60 * 24));
    
    if ($days_remaining <= 0) {
        return $new_total_price;
    }
    
    // Calcular valor proporcional del tiempo restante
    $current_proportional_value = ($current_amount * $days_remaining) / $total_days;
    
    // Calcular valor proporcional del nuevo plan
    $new_proportional_value = ($new_total_price * $days_remaining) / $total_days;
    
    // El ajuste es la diferencia entre el nuevo valor y el actual
    $adjustment = $new_proportional_value - $current_proportional_value;
    
    // Si el ajuste es negativo (downgrade), aplicar descuento completo
    // Si es positivo (upgrade), cobrar la diferencia
    $prorated_amount = max(0, $adjustment);
    
    nextcloud_banda_log_debug('Proration calculation', [
        'user_id' => $user_id,
        'current_amount' => $current_amount,
        'new_amount' => $new_total_price,
        'total_days' => $total_days,
        'days_remaining' => $days_remaining,
        'current_proportional_value' => $current_proportional_value,
        'new_proportional_value' => $new_proportional_value,
        'adjustment' => $adjustment,
        'prorated_amount' => $prorated_amount
    ]);
    
    return round($prorated_amount, 2);
}

/**
 * Determina si el usuario está actualizando su plan
 */
function nextcloud_banda_is_plan_upgrade($user_id, $new_storage, $new_users, $new_frequency) {
    $user_levels = pmpro_getMembershipLevelsForUser($user_id);
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    $current_banda_level = null;
    if (!empty($user_levels)) {
        foreach ($user_levels as $level) {
            if (in_array((int)$level->id, $allowed_levels, true) && 
                (empty($level->enddate) || $level->enddate === '0000-00-00 00:00:00' || strtotime($level->enddate) > time())) {
                $current_banda_level = $level;
                break;
            }
        }
    }
    
    if (!$current_banda_level) {
        return false; // No es upgrade si no hay plan actual
    }
    
    // Obtener configuración actual
    $current_config = nextcloud_banda_get_user_real_config_improved($user_id, $current_banda_level);
    
    $current_storage = $current_config['storage_space'] ?: '1tb';
    $current_users = $current_config['num_users'] ?: 2;
    $current_frequency = $current_config['payment_frequency'] ?: 'monthly';
    
    // Convertir storage a números para comparación
    $current_storage_tb = (int)str_replace('tb', '', $current_storage);
    $new_storage_tb = (int)str_replace('tb', '', $new_storage);
    
    // Considerar upgrade si:
    // 1. Más storage
    // 2. Más usuarios  
    // 3. Frecuencia más larga (menos descuento)
    $storage_upgrade = $new_storage_tb > $current_storage_tb;
    $users_upgrade = $new_users > $current_users;
    
    // Comparar frecuencias por valor (mensual es el más corto, quinquenal el más largo)
    $frequency_order = [
        'monthly' => 1,
        'semiannual' => 2,
        'annual' => 3,
        'biennial' => 4,
        'triennial' => 5,
        'quadrennial' => 6,
        'quinquennial' => 7
    ];
    
    $frequency_upgrade = ($frequency_order[$new_frequency] ?? 1) > ($frequency_order[$current_frequency] ?? 1);
    
    return $storage_upgrade || $users_upgrade || $frequency_upgrade;
}

// ====
// GUARDADO DE CONFIGURACIÓN
// ====

/**
 * Versión mejorada de guardado de configuración
 */
function nextcloud_banda_save_configuration($user_id, $morder) {
    if (!$user_id || !$morder) {
        nextcloud_banda_log_error('Invalid parameters for save configuration', [
            'user_id' => $user_id,
            'morder' => !empty($morder)
        ]);
        return;
    }

    // Validar campos requeridos
    $required_fields = ['storage_space', 'num_users', 'payment_frequency'];
    $config_data = [];

    foreach ($required_fields as $field) {
        if (!isset($_REQUEST[$field])) {
            nextcloud_banda_log_error('Missing required field in request', [
                'user_id' => $user_id,
                'missing_field' => $field
            ]);
            return;
        }
        $config_data[$field] = sanitize_text_field(wp_unslash($_REQUEST[$field]));
    }

    // Normalizar y validar configuración
    $normalized_config = normalize_banda_config($config_data);

    // Preparar datos finales para guardar
    $config = array_merge($normalized_config, [
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'level_id' => intval($morder->membership_id),
        'final_amount' => floatval($morder->InitialPayment),
        'order_id' => $morder->id ?? null,
        'version' => NEXTCLOUD_BANDA_PLUGIN_VERSION
    ]);

    $config_json = wp_json_encode($config);
    if ($config_json === false) {
        nextcloud_banda_log_error('Failed to encode configuration JSON', [
            'user_id' => $user_id,
            'config' => $config
        ]);
        return;
    }

    // Limpiar datos anteriores antes de guardar nuevos
    nextcloud_banda_delete_all_user_data($user_id);
    
    // Guardar nueva configuración
    $result = update_user_meta($user_id, 'nextcloud_banda_config', $config_json);

    if ($result === false) {
        nextcloud_banda_log_error('Failed to update user meta for configuration', [
            'user_id' => $user_id
        ]);
        return;
    }

    // Invalidar caché
    nextcloud_banda_invalidate_user_cache($user_id);

    nextcloud_banda_log_info('Configuration saved successfully', [
        'user_id' => $user_id,
        'config' => $config
    ]);
}

// ====
// VISUALIZACIÓN DE CONFIGURACIÓN DEL MIEMBRO
// ====

function nextcloud_banda_show_member_config_improved() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        nextcloud_banda_log_debug('No user logged in for member config display');
        return;
    }

    try {
        // Usar la función mejorada que verifica membresías activas
        $user_levels = pmpro_getMembershipLevelsForUser($user_id);
    
        if (empty($user_levels)) {
            nextcloud_banda_log_debug("No memberships found for user {$user_id}");
            return;
        }

        $allowed_levels = nextcloud_banda_get_config('allowed_levels');
        $banda_membership = null;
    
        foreach ($user_levels as $level) {
            if (in_array((int)$level->id, $allowed_levels, true)) {
                $banda_membership = $level;
                break;
            }
        }

        if (!$banda_membership) {
            nextcloud_banda_log_debug("No Banda membership found for user {$user_id}");
            return;
        }

        // Verificar que la membresía esté activa
        if (!empty($banda_membership->enddate) && $banda_membership->enddate !== '0000-00-00 00:00:00' && strtotime($banda_membership->enddate) < time()) {
            nextcloud_banda_log_debug("Banda membership expired for user {$user_id}");
            return;
        }

        $membership = $banda_membership;
        // Usar la función mejorada
        $real_config = nextcloud_banda_get_user_real_config_improved($user_id, $membership);

        // Resto del código igual...
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

        $used_space_tb = nextcloud_banda_get_used_space_tb($user_id);
    
        $next_payment_date = '';
        if (!empty($membership->enddate) && $membership->enddate !== '0000-00-00 00:00:00') {
            $next_payment_date = date_i18n('d/m/Y', strtotime($membership->enddate));
        } else {
            $next_payment_date = __('Suscripción activa hasta cancelación', 'pmpro-banda');
        }

        $base_users_included = nextcloud_banda_get_config('base_users_included');
        
        $display_storage = $real_config['storage_space'] ?: '1tb';
        $display_users = $real_config['num_users'] ?: $base_users_included;
        $display_frequency = $real_config['payment_frequency'] ?: 'monthly';
        $display_amount = $real_config['final_amount'] ?: (float)$membership->initial_payment;
        
        $additional_users = max(0, $display_users - $base_users_included);
        $is_estimated = ($real_config['source'] === 'none' || $real_config['source'] === 'membership_deduction');

        ?>
        <div class="pmpro_account-profile-field">
            <h3>Detalhes do plano <strong><?php echo esc_html($membership->name); ?></strong></h3>
            
            <?php if ($is_estimated): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 10px 0; font-size: 0.9em;">
                <strong>ℹ️ Informação:</strong> Os dados abaixo são estimados baseados na sua assinatura. 
                Para configurar seu plano personalizado, entre em contato com o suporte.
            </div>
            <?php endif; ?>
            
            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ff6b35;">
            
                <div style="margin-bottom: 15px;">
                    <p><strong>🗄️ Armazenamento:</strong> 
                        <?php echo esc_html($storage_options[$display_storage] ?? $display_storage); ?>
                        <?php if ($is_estimated && $real_config['source'] === 'none'): ?>
                        <em style="color: #666; font-size: 0.85em;">(estimado)</em>
                        <?php endif; ?>
                    </p>
                
                    <?php if ($used_space_tb > 0): ?>
                    <p style="margin-left: 20px; color: #666; font-size: 0.9em;">
                        <em>Espaço usado: <?php echo number_format_i18n($used_space_tb, 2); ?> TB</em>
                    </p>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 15px;">
                    <p><strong>👥 Usuários:</strong> 
                        <?php echo esc_html($user_options[$display_users] ?? "{$display_users} usuários"); ?>
                        <?php if ($is_estimated && $real_config['source'] === 'none'): ?>
                        <em style="color: #666; font-size: 0.85em;">(estimado)</em>
                        <?php endif; ?>
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

                <div style="margin-bottom: 15px;">
                    <p><strong>💳 Plano de Pagamento:</strong> 
                        <?php echo esc_html($frequency_labels[$display_frequency] ?? $display_frequency); ?>
                        <?php if ($is_estimated && $real_config['source'] === 'none'): ?>
                        <em style="color: #666; font-size: 0.85em;">(estimado)</em>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (!empty($display_amount)): ?>
                <div style="margin-bottom: 15px;">
                    <p><strong>💰 Valor do plano:</strong> 
                        R$ <?php echo number_format_i18n((float)$display_amount, 2); ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if (!$is_estimated): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 0.85em;">
                    <strong>✅ Configuração confirmada</strong> - 
                    <?php 
                    $source_labels = [
                        'saved_config' => 'dados salvos do seu pedido',
                        'profile_fields' => 'campos do seu perfil',
                        'user_meta' => 'configuração do sistema'
                    ];
                    echo esc_html($source_labels[$real_config['source']] ?? 'fonte desconhecida');
                    ?>
                </div>
                <?php endif; ?>

                <div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">
                    <?php if (!empty($next_payment_date)): ?>
                    <p style="font-size: 0.9em; color: #666;">
                        <strong>🔄 Próximo pagamento:</strong> 
                        <?php echo esc_html($next_payment_date); ?>
                    </p>
                    <?php endif; ?>

                    <p style="font-size: 0.9em; color: #666;">
                        <strong>📅 Membro desde:</strong> 
                        <?php echo date_i18n('d/m/Y', strtotime($membership->startdate)); ?>
                    </p>
                </div>

                <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                    <p style="font-size: 0.8em; color: #999;">
                        Grupo Nextcloud: <strong>banda-<?php echo esc_html($user_id); ?></strong>
                    </p>
                    <p style="font-size: 0.8em; color: #999;">
                        ID da assinatura: <?php echo esc_html($membership->id); ?>
                    </p>
                </div>

                <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                    <p style="font-size: 0.6em; color: #999;">
                        Versão: <?php echo esc_html(NEXTCLOUD_BANDA_PLUGIN_VERSION); ?> | 
                        Fonte: <?php echo esc_html($real_config['source']); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php

        nextcloud_banda_log_info("Banda member config displayed successfully for user {$user_id}", [
            'source' => $real_config['source'],
            'is_estimated' => $is_estimated
        ]);

    } catch (Exception $e) {
        nextcloud_banda_log_error('Exception in nextcloud_banda_show_member_config_improved', [
            'user_id' => $user_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            echo '<div class="pmpro_account-profile-field">';
            echo '<p style="color: red;"><strong>Erro:</strong> Não foi possível carregar os detalhes do plano Banda.</p>';
            echo '</div>';
        }
    }
}

// ====
// MANEJO DE ELIMINACIÓN COMPLETA DE DATOS
// ====

/**
 * Elimina todos los datos de configuración de Banda para un usuario
 */
function nextcloud_banda_delete_all_user_data($user_id) {
    // Eliminar user meta específicos
    delete_user_meta($user_id, 'nextcloud_banda_config');
    delete_user_meta($user_id, 'storage_space');
    delete_user_meta($user_id, 'num_users');
    delete_user_meta($user_id, 'payment_frequency');
    
    // Eliminar campos de PMPro Register Helper si existen
    if (function_exists('pmprorh_getProfileFields')) {
        $fields = ['storage_space', 'num_users', 'payment_frequency'];
        foreach ($fields as $field_name) {
            delete_user_meta($user_id, $field_name);
        }
    }
    
    // Invalidar toda la caché del usuario
    nextcloud_banda_invalidate_user_cache($user_id);
    
    nextcloud_banda_log_info("Todos los datos de Banda eliminados para user_id: {$user_id}");
}

/**
 * Hook para eliminar datos cuando se elimina un usuario de WordPress
 */
add_action('delete_user', 'nextcloud_banda_cleanup_on_user_deletion');
function nextcloud_banda_cleanup_on_user_deletion($user_id) {
    nextcloud_banda_delete_all_user_data($user_id);
    nextcloud_banda_log_info("Limpieza completada al eliminar usuario: {$user_id}");
}

/**
 * Hook mejorado para eliminar configuración al cancelar membresía
 */
add_action('pmpro_after_cancel_membership_level', 'nextcloud_banda_clear_config_on_cancellation_improved', 10, 3);
function nextcloud_banda_clear_config_on_cancellation_improved($user_id, $membership_level_id, $cancelled_levels) {
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    // Verificar si alguno de los niveles cancelados es de Banda
    $has_banda_level = false;
    if (is_array($cancelled_levels)) {
        foreach ($cancelled_levels as $level) {
            if (in_array((int)$level->membership_id, $allowed_levels, true)) {
                $has_banda_level = true;
                break;
            }
        }
    } else {
        // Compatibilidad con versiones anteriores
        if (is_object($cancelled_levels) && isset($cancelled_levels->membership_id)) {
            if (in_array((int)$cancelled_levels->membership_id, $allowed_levels, true)) {
                $has_banda_level = true;
            }
        }
    }
    
    if ($has_banda_level) {
        nextcloud_banda_delete_all_user_data($user_id);
        nextcloud_banda_log_info("Configuración eliminada tras cancelación de membresía Banda", [
            'user_id' => $user_id,
            'cancelled_levels' => is_array($cancelled_levels) ? array_map(function($l) { return $l->membership_id; }, $cancelled_levels) : 'single_level'
        ]);
    }
}

/**
 * Hook para limpiar datos cuando se cambia completamente de nivel
 */
add_action('pmpro_after_change_membership_level', function($level_id, $user_id) {
    // Si el nuevo nivel no es de Banda, limpiar datos anteriores
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    if (!in_array((int)$level_id, $allowed_levels, true)) {
        nextcloud_banda_delete_all_user_data($user_id);
    } else {
        // Invalidar caché pero mantener datos
        nextcloud_banda_invalidate_user_cache($user_id);
    }
}, 20, 2); // Prioridad más baja para ejecutarse después de otros hooks

/**
 * Función mejorada para obtener configuración del usuario
 * Forzará valores por defecto si no hay membresía activa
 */
function nextcloud_banda_get_user_real_config_improved($user_id, $membership = null) {
    // Verificar si el usuario tiene membresía Banda activa
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    $user_levels = pmpro_getMembershipLevelsForUser($user_id);
    
    $has_active_banda_membership = false;
    if (!empty($user_levels)) {
        foreach ($user_levels as $level) {
            if (in_array((int)$level->id, $allowed_levels, true) && 
                (empty($level->enddate) || $level->enddate === '0000-00-00 00:00:00' || strtotime($level->enddate) > time())) {
                $has_active_banda_membership = true;
                break;
            }
        }
    }
    
    // Si no tiene membresía Banda activa, retornar valores por defecto
    if (!$has_active_banda_membership) {
        return [
            'storage_space' => '1tb',
            'num_users' => 2,
            'payment_frequency' => 'monthly',
            'final_amount' => null,
            'source' => 'defaults_no_active_membership'
        ];
    }
    
    // Si tiene membresía activa, proceder con la obtención normal
    return nextcloud_banda_get_user_real_config($user_id, $membership);
}

// ====
// FUNCIONES DE LIMPIEZA PARA CASOS ESPECIALES
// ====

/**
 * Función para limpiar datos de usuarios que ya no tienen membresía activa
 */
function nextcloud_banda_cleanup_inactive_users() {
    // Esta función puede ser llamada manualmente o programada
    $users_with_config = get_users([
        'meta_key' => 'nextcloud_banda_config',
        'fields' => ['ID']
    ]);
    
    $cleaned_count = 0;
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    
    foreach ($users_with_config as $user) {
        $user_id = $user->ID;
        $user_levels = pmpro_getMembershipLevelsForUser($user_id);
        
        $has_active_banda_membership = false;
        if (!empty($user_levels)) {
            foreach ($user_levels as $level) {
                if (in_array((int)$level->id, $allowed_levels, true) && 
                    (empty($level->enddate) || $level->enddate === '00:00:00' || strtotime($level->enddate) > time())) {
                    $has_active_banda_membership = true;
                    break;
                }
            }
        }
        
        // Si no tiene membresía Banda activa, limpiar sus datos
        if (!$has_active_banda_membership) {
            nextcloud_banda_delete_all_user_data($user_id);
            $cleaned_count++;
        }
    }
    
    nextcloud_banda_log_info("Limpieza de usuarios inactivos completada", [
        'usuarios_revisados' => count($users_with_config),
        'usuarios_limpiados' => $cleaned_count
    ]);
    
    return $cleaned_count;
}

// Agregar endpoint para limpieza manual (solo para administradores)
add_action('wp_ajax_nextcloud_banda_cleanup_inactive', 'nextcloud_banda_cleanup_inactive_endpoint');
function nextcloud_banda_cleanup_inactive_endpoint() {
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado');
    }
    
    $cleaned_count = nextcloud_banda_cleanup_inactive_users();
    
    wp_send_json_success([
        'message' => "Limpieza completada. {$cleaned_count} usuarios procesados.",
        'cleaned_count' => $cleaned_count
    ]);
}

// ====
// INICIALIZACIÓN Y HOOKS
// ====

// Hook de inicialización único
add_action('init', 'nextcloud_banda_add_dynamic_fields', 20);

// Hook principal de modificación de precio
add_filter('pmpro_checkout_level', 'nextcloud_banda_modify_level_pricing', 1);

// Hooks de guardado
add_action('pmpro_after_checkout', 'nextcloud_banda_save_configuration', 10, 2);

// Hook para mostrar configuración en área de miembros
// Modificar la función de visualización para usar la versión mejorada
remove_action('pmpro_account_bullets_bottom', 'nextcloud_banda_show_member_config');
add_action('pmpro_account_bullets_bottom', 'nextcloud_banda_show_member_config_improved');

// Invalidación de caché
add_action('pmpro_after_change_membership_level', function($level_id, $user_id) {
    nextcloud_banda_invalidate_user_cache($user_id);
}, 10, 2);

// Elimina la configuración guardada al cancelar la membresía
add_action('pmpro_after_cancel_membership_level', 'nextcloud_banda_clear_config_on_cancellation', 10, 2);
function nextcloud_banda_clear_config_on_cancellation($user_id, $membership) {
    // Verifica que el nivel de membresía pertenezca a "Nextcloud Banda"
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');
    if (in_array((int)$membership->membership_id, $allowed_levels, true)) {
        delete_user_meta($user_id, 'nextcloud_banda_config');
        nextcloud_banda_log_info("Configuración eliminada tras cancelación", ['user_id' => $user_id]);
    }
}

nextcloud_banda_log_info('PMPro Banda Dynamic Pricing loaded - SYNCHRONIZED VERSION', [
    'version' => NEXTCLOUD_BANDA_PLUGIN_VERSION,
    'php_version' => PHP_VERSION,
    'base_price_constant' => NEXTCLOUD_BANDA_BASE_PRICE,
    'normalize_function_exists' => function_exists('normalize_banda_config'),
    'real_config_function_exists' => function_exists('nextcloud_banda_get_user_real_config')
]);
