<?php
// Crear plan Nextcloud Banda y enviar emails de confirmación

if (!defined('ABSPATH')) exit;

/**
 * Función mejorada para crear grupo Nextcloud Banda y enviar emails de confirmación
 * Integrada con el sistema de pricing dinámico Banda
 */

// Importar funciones de logging si están disponibles
if (!function_exists('nextcloud_create_banda_log_info') && function_exists('error_log')) {
    function nextcloud_create_banda_log_info($message, $context = []) {
        $log_message = '[Nextcloud Banda] ' . $message;
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        error_log($log_message);
    }
}

if (!function_exists('nextcloud_create_banda_log_error') && function_exists('error_log')) {
    function nextcloud_create_banda_log_error($message, $context = []) {
        nextcloud_create_banda_log_info('ERROR: ' . $message, $context);
    }
}

/**
 * Hook principal después del checkout para planes Banda
 */
function nextcloud_create_banda_pmpro_after_checkout($user_id, $invoice) {
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');

    // Verificar que es un plan Banda (nivel permitido)
    if (!in_array(intval($invoice->membership_id), $allowed_levels, true)) {
        nextcloud_create_banda_log_info('Checkout is not for Banda level, skipping', [
            'level_id' => $invoice->membership_id,
            'allowed_levels' => $allowed_levels
        ]);
        return;
    }

    try {
        // Obtiene la cantidad de usuarios del formulario
        $num_users = isset($_REQUEST['num_users']) ? intval($_REQUEST['num_users']) : 2;

        nextcloud_create_banda_log_info('Starting Banda plan processing', [
            'user_id' => $user_id,
            'num_users' => $num_users,
            'invoice_id' => $invoice->id ?? 'unknown'
        ]);

        // Obtener username y email desde WordPress
        $user_info     = get_userdata($user_id);
        $main_username = $user_info->user_login;
        $main_email    = $user_info->user_email;
        $group_name    = 'banda-' . $user_id;

        // 1. Crear grupo y usuarios en Nextcloud
        $shared_password = wp_generate_password(12, true, true);
        $grupo_creado = crear_nextcloud_banda($main_username, $main_email, $group_name, $num_users, $shared_password);

        if (!$grupo_creado) {
            nextcloud_create_banda_log_error('Failed to create Nextcloud group, skipping email sending');
            return;
        }

        // 2. Procesar emails de confirmación
        plan_nextcloud_banda($user_id, $invoice, $num_users, $shared_password);

    } catch (Exception $e) {
        nextcloud_create_banda_log_error('Exception in Banda checkout processing', [
            'user_id' => $user_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

/**
 * Reparto de cuotas para Nextcloud Banda
 */
function calc_quotas_banda($total_gb, $num_users) {
    $total_gb = (int)$total_gb;
    $num_users = max(2, (int)$num_users);
    if ($num_users === 2) {
        return [ 'admin' => (int)floor($total_gb/2), 'others_each' => (int)ceil($total_gb/2) ];
    }
    $admin_gb = (int)floor($total_gb * 0.5);
    $left = max(0, $total_gb - $admin_gb);
    $each = (int)floor($left / ($num_users - 1));
    return [ 'admin' => $admin_gb, 'others_each' => $each ];
}

/**
 * Procesar plan Nextcloud Banda - Versión con emails mejorada y reparto de cuotas
 */
function plan_nextcloud_banda($user_id, $morder, $num_users, $shared_password) {
    // Validaciones iniciales
    if (empty($user_id) || empty($morder)) {
        nextcloud_create_banda_log_error('Invalid parameters provided', [
            'user_id' => $user_id,
            'morder_exists' => !empty($morder)
        ]);
        return false;
    }

    try {
        // Obtener información del usuario con validaciones
        $user = get_userdata($user_id);
        if (!$user) {
            nextcloud_create_banda_log_error('User not found', ['user_id' => $user_id]);
            return false;
        }

        $email = $user->user_email;
        $username = $user->user_login;
        $displayname = $user->display_name ?: $username;

        // Obtener nivel de membresía actual
        $level = pmpro_getMembershipLevelForUser($user_id);
        if (!$level) {
            nextcloud_create_banda_log_error('No membership level found for user', ['user_id' => $user_id]);
            return false;
        }

        // Configurar timezone y fecha del pedido
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
        
        // Usar timestamp del morder o timestamp actual
        $order_timestamp = !empty($morder->timestamp) ? $morder->timestamp : current_time('timestamp');
        $dt->setTimestamp($order_timestamp);
        $fecha_pedido = $dt->format('d/m/Y H:i:s');

        // Obtener configuración dinámica del usuario Banda
        $config_data = get_nextcloud_create_banda_user_config($user_id);

        $storage_tb = (int)($config_data['storage_space'] ?? 1);
        $total_gb = $storage_tb * 1024;
        $quotas = calc_quotas_banda($total_gb, $num_users);

        // Obtener fecha del próximo pago
        $fecha_pago_proximo = get_pmpro_next_payment_date($user_id, $level);

        // Preparar datos específicos del grupo Banda
        $grupo_info = [
            'group_name' => 'banda-' . $user_id,
            'num_users' => $num_users,
            'admin_user' => $username,
            'password' => $shared_password,
            'quota_admin_gb' => $quotas['admin'],
            'quota_user_gb' => $quotas['others_each'],
            'total_gb' => $total_gb
        ];

        // Preparar datos del email
        $email_data = prepare_nextcloud_create_banda_email_data($user, $level, $morder, $config_data, [
            'fecha_pedido' => $fecha_pedido,
            'fecha_pago_proximo' => $fecha_pago_proximo,
            'grupo_info' => $grupo_info
        ]);

        // Enviar email al usuario
        $user_email_sent = send_nextcloud_create_banda_user_email($email_data);
        
        // Enviar email al administrador
        $admin_email_sent = send_nextcloud_create_banda_admin_email($email_data);

        // Log del resultado
        nextcloud_create_banda_log_info('Nextcloud Banda plan processing completed', [
            'user_id' => $user_id,
            'username' => $username,
            'group_name' => $grupo_info['group_name'],
            'num_users' => $num_users,
            'level_name' => $level->name,
            'user_email_sent' => $user_email_sent,
            'admin_email_sent' => $admin_email_sent,
            'config_data' => $config_data,
            'quota_admin_gb' => $quotas['admin'],
            'quota_user_gb' => $quotas['others_each']
        ]);

        return true;

    } catch (Exception $e) {
        nextcloud_create_banda_log_error('Exception in plan_nextcloud_banda', [
            'user_id' => $user_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return false;
    }
}

/**
 * Envía email al usuario para plan Banda, incluye reparto de cuotas
 */
function send_nextcloud_create_banda_user_email($data) {
    $user = $data['user'];
    $level = $data['level'];
    $morder = $data['morder'];
    $config = $data['config'];
    $grupo_info = $data['grupo_info'];

    $brdrv_email = "cloud@" . basename(get_site_url());
    $mailto = "mailto:" . $brdrv_email;

    $subject = "Seu grupo Nextcloud Banda foi criado";
    $message = "<h1>Cloud Brasdrive</h1>";
    $message .= "<p>Prezado(a) <b>{$user->display_name}</b> ({$user->user_login}),</p>";
    $message .= "<p>Parabéns! Seu pagamento foi confirmado e seu plano Nextcloud Banda foi criado com sucesso.</p>";

    $message .= "<h3>Dados de acesso do seu grupo:</h3>";
    $message .= "<p><strong>Nome do grupo:</strong> {$grupo_info['group_name']}<br/>";
    $message .= "<strong>Usuário administrador:</strong> {$grupo_info['admin_user']}<br/>";
    $message .= "<strong>Senha compartilhada inicial:</strong> {$grupo_info['password']}</p>";

    $message .= "<h3>Detalhes do seu plano:</h3>";
    $message .= "<p><strong>Plano:</strong> {$level->name}<br/>";
    $message .= "<strong>Armazenamento total:</strong> " . ($config['storage_display'] ?? 'N/A') . "<br/>";
    $message .= "<strong>Número de usuários:</strong> " . ($config['users_display'] ?? 'N/A') . "<br/>";
    $message .= "<strong>Frequência de pagamento:</strong> " . ($config['frequency_display'] ?? 'N/A') . "<br/>";
    $message .= "<strong>Data do pedido:</strong> {$data['fecha_pedido']}<br/>";
    $message .= "<strong>Valor {$data['monthly_message']}:</strong> R$ " . number_format($morder->total, 2, ',', '.') . "<br/>";
    $message .= "{$data['date_message']}{$data['fecha_pago_proximo']}</p>";

    // Información específica del grupo y reparto de cuotas
    $message .= "<div style='background-color: #e3f2fd; border: 1px solid #2196f3; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    $message .= "<p><strong>📋 Distribuição de armazenamento:</strong></p><ul>";
    if ($grupo_info['num_users'] == 2) {
        $message .= "<li>Você (admin): {$grupo_info['quota_admin_gb']} GB</li>";
        $message .= "<li>Segundo usuário: {$grupo_info['quota_user_gb']} GB</li>";
    } else {
        $message .= "<li>Você (admin): {$grupo_info['quota_admin_gb']} GB</li>";
        $message .= "<li>Cada usuário adicional: {$grupo_info['quota_user_gb']} GB</li>";
    }
    $message .= "</ul>";
    $message .= "<p><strong>Total contratado:</strong> {$grupo_info['total_gb']} GB</p>";
    $message .= "<li>Usuários criados: {$grupo_info['admin_user']}";
    for ($i = 1; $i < $grupo_info['num_users']; $i++) $message .= ", {$grupo_info['admin_user']}-{$i}";
    $message .= "</li>";
    $message .= "</div>";

    $message .= "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    $message .= "<p><strong>⚠️ Importante - Segurança:</strong><br/>";
    $message .= "Por segurança, recomendamos:</p>";
    $message .= "<ul>";
    $message .= "<li>Manter guardadas as credenciais do grupo em um local seguro</li>";
    $message .= "<li>Excluir este e-mail após salvar as informações</li>";
    $message .= "<li>Alterar a senha nas Configurações pessoais do Nextcloud</li>";
    $message .= "<li>Configurar senhas individuais para cada usuário do grupo</li>";
    $message .= "</ul></div>";

    $message .= "<p>Se você tiver alguma dúvida sobre a gestão do grupo, entre em contato conosco no e-mail: <a href='{$mailto}'>{$brdrv_email}</a>.</p>";
    $message .= "<p>Atenciosamente,<br/><strong>Equipe Brasdrive</strong></p>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $result = wp_mail($user->user_email, $subject, $message, $headers);

    if (!$result) {
        nextcloud_create_banda_log_error('Failed to send user email', [
            'user_id' => $user->ID,
            'email' => $user->user_email
        ]);
    }
    return $result;
}

/**
 * Envía email al administrador para plan Banda, incluye reparto de cuotas
 */
function send_nextcloud_create_banda_admin_email($data) {
    $user = $data['user'];
    $level = $data['level'];
    $config = $data['config'];
    $grupo_info = $data['grupo_info'];

    $to = get_option('admin_email');
    $subject = "Novo grupo Nextcloud Banda criado - " . $level->name;

    $admin_message = "<h2>Novo grupo Nextcloud Banda criado</h2>";
    $admin_message .= "<p><strong>Plano:</strong> {$level->name}<br/>";
    $admin_message .= "<strong>Nome:</strong> {$user->display_name}<br/>";
    $admin_message .= "<strong>Usuário:</strong> {$user->user_login}<br/>";
    $admin_message .= "<strong>Email:</strong> {$user->user_email}</p>";
    $admin_message .= "<h3>Detalhes do grupo criado:</h3>";
    $admin_message .= "<p><strong>Nome do grupo:</strong> {$grupo_info['group_name']}<br/>";
    $admin_message .= "<strong>Admin do grupo:</strong> {$grupo_info['admin_user']}<br/>";
    $admin_message .= "<strong>Senha compartilhada inicial:</strong> {$grupo_info['password']}<br/>";
    $admin_message .= "<strong>Total de usuários:</strong> {$grupo_info['num_users']}</p>";

    $admin_message .= "<h3>Distribuição de cotas:</h3><ul>";
    if ($grupo_info['num_users'] == 2) {
        $admin_message .= "<li>Admin: {$grupo_info['quota_admin_gb']} GB (50%)</li>";
        $admin_message .= "<li>Segundo usuário: {$grupo_info['quota_user_gb']} GB (50%)</li>";
    } else {
        $admin_message .= "<li>Admin: {$grupo_info['quota_admin_gb']} GB (50%)</li>";
        $admin_message .= "<li>Cada usuário adicional: {$grupo_info['quota_user_gb']} GB (parte dos 50%)</li>";
    }
    $admin_message .= "</ul>";
    $admin_message .= "<p><strong>Total contratado:</strong> {$grupo_info['total_gb']} GB</p>";

    $admin_message .= "<h3>Configuração do plano Banda:</h3>";
    $admin_message .= "<p><strong>Armazenamento:</strong> " . ($config['storage_display'] ?? 'N/A') . "<br/>";
    $admin_message .= "<strong>Usuários:</strong> " . ($config['users_display'] ?? 'N/A') . "<br/>";
    $admin_message .= "<strong>Frequência:</strong> " . ($config['frequency_display'] ?? 'N/A') . "</p>";

    $admin_message .= "<p><strong>Data do pedido:</strong> {$data['fecha_pedido']}<br/>";
    $admin_message .= "<strong>Próximo pagamento:</strong> {$data['fecha_pago_proximo']}</p>";
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $result = wp_mail($to, $subject, $admin_message, $headers);

    if (!$result) {
        nextcloud_create_banda_log_error('Failed to send admin email', [
            'admin_email' => $to,
            'user_id' => $user->ID
        ]);
    }
    return $result;
}

/**
 * Crea un grupo de Nextcloud y usuarios asociados
 */
function crear_nextcloud_banda($main_username, $main_email, $group_name, $num_users = 2, $shared_password) {
    $group_result = call_nextcloud_api('groups', 'POST', ['groupid' => $group_name]);
    if ($group_result['statuscode'] !== 100) {
        error_log("[Nextcloud Banda] ERROR: Failed to create group | " . json_encode($group_result));
        return false;
    }
    $user_data = [
        'userid'      => $main_username,
        'password'    => $shared_password,
        'displayname' => $main_username,
        'groups[]'    => $group_name,
        'email'       => $main_email
    ];
    $main_user_result = call_nextcloud_api('users', 'POST', $user_data);
    if ($main_user_result['statuscode'] !== 100) {
        error_log("[Nextcloud Banda] ERROR: Failed to create main user | " . json_encode($main_user_result));
        return false;
    }
    $admin_result = call_nextcloud_api("groups/$group_name/admins", 'POST', ['userid' => $main_username]);
    if ($admin_result['statuscode'] !== 100) {
        error_log("[Nextcloud Banda] WARNING: Failed to set main user as group admin | " . json_encode($admin_result));
    }
    for ($i = 1; $i < $num_users; $i++) {
        $username = sanitize_user($main_username . "-$i");
        $user_data = [
            'userid'      => $username,
            'password'    => $shared_password,
            'displayname' => $username,
            'groups[]'    => $group_name,
        ];
        $result = call_nextcloud_api('users', 'POST', $user_data);
        if ($result['statuscode'] !== 100) {
            error_log("[Nextcloud Banda] ERROR: Failed to create additional user | username=$username | " . json_encode($result));
        }
    }
    return true;
}

/**
 * Llama a la API de Nextcloud de forma segura
 */
function call_nextcloud_api($endpoint, $method = 'POST', $data = []) {
    // Obtener las constantes de la URL y la API de Nextcloud
    $site_url = get_option('siteurl');
    $nextcloud_api_url = 'https://cloud.' . parse_url($site_url, PHP_URL_HOST);

    // Obtener credenciales de variables de entorno
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    $nextcloud_url = trailingslashit($nextcloud_api_url) . 'ocs/v1.php/cloud/' . ltrim($endpoint, '/');
    $args = [
        'method'  => $method,
        'headers' => [
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode("$nextcloud_api_admin:$nextcloud_api_pass")
        ],
        'body'    => http_build_query($data, '', '&'),
        'timeout' => 30,
    ];
    $response = wp_remote_request($nextcloud_url, $args);
    if (is_wp_error($response)) {
        return [
            'status'  => 'error',
            'message' => $response->get_error_message(),
        ];
    }
    $body = wp_remote_retrieve_body($response);
    $xml  = simplexml_load_string($body);
    if ($xml && isset($xml->meta->statuscode)) {
        return [
            'status'     => (string)$xml->meta->status,
            'statuscode' => (int)$xml->meta->statuscode,
            'message'    => (string)$xml->meta->message,
        ];
    }
    return [
        'status'     => 'error',
        'statuscode' => 999,
        'message'    => 'Invalid response',
    ];
}

/**
 * Obtiene la configuración dinámica del usuario Banda
 */
function get_nextcloud_create_banda_user_config($user_id) {
    $config_json = get_user_meta($user_id, 'nextcloud_create_banda_config', true);
    if (empty($config_json)) {
        return [
            'storage_space' => '1tb',
            'num_users' => 2,
            'payment_frequency' => 'monthly',
            'storage_display' => '1 Terabyte',
            'users_display' => '2 usuários (incluídos)',
            'frequency_display' => 'Mensal'
        ];
    }
    $config = json_decode($config_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    $config['storage_display'] = get_storage_display_name($config['storage_space'] ?? '1tb');
    $config['users_display'] = get_users_display_name($config['num_users'] ?? 2);
    $config['frequency_display'] = get_frequency_display_name($config['payment_frequency'] ?? 'monthly');
    return $config;
}

/**
 * Utilidades para emails y display
 */
function get_users_display_name($num_users) {
    $base_users_included = nextcloud_banda_get_config('base_users_included') ?? 2;
    if ($num_users <= $base_users_included) {
        return "{$num_users} usuários (incluídos)";
    } else {
        $additional = $num_users - $base_users_included;
        return "{$num_users} usuários ({$base_users_included} incluídos + {$additional} adicionais)";
    }
}
function get_storage_display_name($storage_space) {
    $storage_options = [
        '1tb' => '1 Terabyte', '2tb' => '2 Terabytes', '3tb' => '3 Terabytes',
        '4tb' => '4 Terabytes', '5tb' => '5 Terabytes', '6tb' => '6 Terabytes',
        '7tb' => '7 Terabytes', '8tb' => '8 Terabytes', '9tb' => '9 Terabytes',
        '10tb' => '10 Terabytes', '15tb' => '15 Terabytes', '20tb' => '20 Terabytes'
    ];
    return $storage_options[$storage_space] ?? $storage_space;
}
function get_frequency_display_name($payment_frequency) {
    $frequency_options = [
        'monthly' => 'Mensal', 'semiannual' => 'Semestral',
        'annual' => 'Anual', 'biennial' => 'Bienal', 'triennial' => 'Trienal',
        'quadrennial' => 'Quadrienal', 'quinquennial' => 'Quinquenal'
    ];
    return $frequency_options[$payment_frequency] ?? $payment_frequency;
}
function get_frequency_messages($payment_frequency) {
    $messages = [
        'monthly' => [
            'monthly_message' => 'mensal ', 'date_message' => 'Data do próximo pagamento: '
        ], 'semiannual' => [
            'monthly_message' => 'semestral ', 'date_message' => 'Data da próxima cobrança semestral: '
        ], 'annual' => [
            'monthly_message' => 'anual ', 'date_message' => 'Data da próxima cobrança anual: '
        ], 'biennial' => [
            'monthly_message' => 'bienal ', 'date_message' => 'Data da próxima cobrança (em 2 anos): '
        ], 'triennial' => [
            'monthly_message' => 'trienal ', 'date_message' => 'Data da próxima cobrança (em 3 anos): '
        ], 'quadrennial' => [
            'monthly_message' => 'quadrienal ', 'date_message' => 'Data da próxima cobrança (em 4 anos): '
        ], 'quinquennial' => [
            'monthly_message' => 'quinquenal ', 'date_message' => 'Data da próxima cobrança (em 5 anos): '
        ]
    ];
    return $messages[$payment_frequency] ?? $messages['monthly'];
}
function get_pmpro_next_payment_date($user_id, $level) {
    if (function_exists('pmpro_next_payment')) {
        $next_payment = pmpro_next_payment($user_id);
        if (!empty($next_payment)) {
            return date('d/m/Y', $next_payment);
        }
    }
    if (class_exists('MemberOrder')) {
        $last_order = new MemberOrder();
        $last_order->getLastMemberOrder($user_id, 'success');
        if (!empty($last_order->timestamp)) {
            $last_payment_timestamp = is_numeric($last_order->timestamp) ? $last_order->timestamp : strtotime($last_order->timestamp);
            $cycle_seconds = get_cycle_seconds_from_level($level);
            $next_payment_timestamp = $last_payment_timestamp + $cycle_seconds;
            return date('d/m/Y', $next_payment_timestamp);
        }
    }
    $cycle_seconds = get_cycle_seconds_from_level($level);
    $next_payment_timestamp = current_time('timestamp') + $cycle_seconds;
    return date('d/m/Y', $next_payment_timestamp);
}
function get_cycle_seconds_from_level($level) {
    if (empty($level->cycle_number) || empty($level->cycle_period)) return 30 * DAY_IN_SECONDS;
    $multipliers = [
        'Day' => DAY_IN_SECONDS, 'Week' => WEEK_IN_SECONDS,
        'Month' => 30 * DAY_IN_SECONDS, 'Year' => YEAR_IN_SECONDS
    ];
    $multiplier = $multipliers[$level->cycle_period] ?? (30 * DAY_IN_SECONDS);
    return $level->cycle_number * $multiplier;
}

// Activación del hook
add_action('pmpro_after_checkout', 'nextcloud_create_banda_pmpro_after_checkout', 10, 2);

nextcloud_create_banda_log_info('Nextcloud Banda email system loaded successfully');



