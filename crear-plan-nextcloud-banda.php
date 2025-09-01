<?php
// Crear plan Nextcloud Banda y enviar emails de confirmación

if (!defined('ABSPATH')) exit;

/**
 * Función mejorada para crear plano Nextcloud Banda y enviar emails de confirmación
 * Integrada con el sistema de pricing dinámico Banda
 */

// Importar funciones de logging si están disponibles
if (!function_exists('nextcloud_create_banda_log_info') && function_exists('error_log')) {
    function nextcloud_create_banda_log_info($message, $context = []) {
        $log_message = '[PMPro Create Banda] ' . $message;
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

// AGREGADA: Función de debug faltante
if (!function_exists('nextcloud_create_banda_log_debug') && function_exists('error_log')) {
    function nextcloud_create_banda_log_debug($message, $context = []) {
        // Solo hacer log de debug si WP_DEBUG está habilitado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            nextcloud_create_banda_log_info('DEBUG: ' . $message, $context);
        }
    }
}

/**
 * Hook principal después del checkout para planes Banda - CORREGIDO
 */
function nextcloud_banda_pmpro_after_checkout($user_id, $invoice) {
    $allowed_levels = nextcloud_banda_get_config('allowed_levels');

    // Verificar que es un plan Banda (nivel 2)
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

        // CORREGIDO: Solo procesar emails, crear_nextcloud_banda ya se llama en plan_nextcloud_banda
        plan_nextcloud_banda($user_id, $invoice, $num_users);
        
    } catch (Exception $e) {
        nextcloud_create_banda_log_error('Exception in banda checkout processing', [
            'user_id' => $user_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

/**
 * Procesar plan Nextcloud Banda - CORREGIDO para usar la misma contraseña
 */
function plan_nextcloud_banda($user_id, $morder, $num_users) {
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
        $config_data = get_nextcloud_banda_user_config($user_id);
        
        // Obtener fecha del próximo pago
        $fecha_pago_proximo = get_pmpro_next_payment_banda_date($user_id, $level);

        // CORREGIDO: Crear el plano PRIMERO para obtener la contraseña real
        $plano_resultado = crear_nextcloud_banda($user_id, $num_users);
        
        if (!$plano_resultado['success']) {
            nextcloud_create_banda_log_error('Failed to create Nextcloud group, skipping email sending');
            return false;
        }

        // Usar la contraseña real generada en la creación del plano
        $real_password = $plano_resultado['shared_password'];

        // Preparar datos específicos del plan Banda con contraseña real
        $plano_info = [
            'group_name' => 'banda-' . $user_id,
            'num_users' => $num_users,
            'admin_user' => $username,
            'password' => $real_password // Usar contraseña real
        ];

        // Preparar datos del email
        $email_data = prepare_nextcloud_banda_email_data($user, $level, $morder, $config_data, [
            'fecha_pedido' => $fecha_pedido,
            'fecha_pago_proximo' => $fecha_pago_proximo,
            'plano_info' => $plano_info
        ]);

        // Enviar email al usuario
        $user_email_sent = send_nextcloud_banda_user_email($email_data);
        
        // Enviar email al administrador
        $admin_email_sent = send_nextcloud_banda_admin_email($email_data);

        // Log del resultado
        nextcloud_create_banda_log_info('Nextcloud Banda plan processing completed', [
            'user_id' => $user_id,
            'username' => $username,
            'group_name' => $plano_info['group_name'],
            'num_users' => $num_users,
            'level_name' => $level->name,
            'user_email_sent' => $user_email_sent,
            'admin_email_sent' => $admin_email_sent,
            'config_data' => $config_data,
            'password_sent' => 'Real password used'
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
 * Obtiene la configuración dinámica del usuario Banda
 */
function get_nextcloud_banda_user_config($user_id) {
    $config_json = get_user_meta($user_id, 'nextcloud_banda_config', true);
    
    if (empty($config_json)) {
        nextcloud_create_banda_log_info('No dynamic config found for user, using defaults', ['user_id' => $user_id]);
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
        nextcloud_create_banda_log_error('Invalid JSON in user config', [
            'user_id' => $user_id,
            'json_error' => json_last_error_msg()
        ]);
        return null;
    }

    // Enriquecer con información de display
    $config['storage_display'] = get_storage_banda_display_name($config['storage_space'] ?? '1tb');
    $config['users_display'] = get_users_display_name($config['num_users'] ?? 2);
    $config['frequency_display'] = get_frequency_banda_display_name($config['payment_frequency'] ?? 'monthly');

    return $config;
}

/**
 * Prepara los datos para los emails Banda
 */
function prepare_nextcloud_banda_email_data($user, $level, $morder, $config_data, $additional_data) {
    // Determinar mensajes basados en la frecuencia
    $frequency_messages = get_frequency_banda_messages($config_data['payment_frequency'] ?? 'monthly');
    
    return [
        'user' => $user,
        'level' => $level,
        'morder' => $morder,
        'config' => $config_data,
        'fecha_pedido' => $additional_data['fecha_pedido'],
        'fecha_pago_proximo' => $additional_data['fecha_pago_proximo'],
        'plano_info' => $additional_data['plano_info'],
        'monthly_message' => $frequency_messages['monthly_message'],
        'date_message' => $frequency_messages['date_message']
    ];
}

/**
 * Envía email al usuario para plan Banda - Actualizado con información de contraseñas
 */
function send_nextcloud_banda_user_email($data) {
    $user = $data['user'];
    $level = $data['level'];
    $morder = $data['morder'];
    $config = $data['config'];
    $plano_info = $data['plano_info'];

    // Configuración del email
    $brdrv_email = "cloud@" . basename(get_site_url());
    $mailto = "mailto:" . $brdrv_email;

    // Título del email
    $subject = "Seu plano Nextcloud Banda foi criado";
    
    // Construir mensaje
    $message = "<h1>Cloud Brasdrive</h1>";
    $message .= "<p>Prezado(a) <b>" . $user->display_name . "</b> (" . $user->user_login . "),</p>";
    $message .= "<p>Parabéns! Seu pagamento foi confirmado e seu plano Nextcloud Banda foi criado com sucesso.</p>";
    
    // Datos de acceso al plan
    $message .= "<h3>Dados de acesso do seu plano:</h3>";
    $message .= "<p><strong>Nome do grupo:</strong> " . $plano_info['group_name'] . "<br/>";
    $message .= "<strong>Usuário administrador:</strong> " . $plano_info['admin_user'] . " (você é o admin do grupo)<br/>";
    $message .= "<strong>Senha compartilhada inicial:</strong> " . $plano_info['password'] . "</p>";
    
    // Detalles del plan Banda
    $message .= "<h3>Detalhes do seu plano:</h3>";
    $message .= "<p><strong>Plano:</strong> " . $level->name . "<br/>";
    
    // Información específica del plan
    if (!empty($config)) {
        $message .= "<strong>Armazenamento total:</strong> " . ($config['storage_display'] ?? 'N/A') . "<br/>";
        $message .= "<strong>Número de usuários:</strong> " . ($config['users_display'] ?? 'N/A') . "<br/>";
        $message .= "<strong>Frequência de Pagamento:</strong> " . ($config['frequency_display'] ?? 'N/A') . "<br/>";
    }
    
    $message .= "<strong>Data do pedido:</strong> " . $data['fecha_pedido'] . "<br/>";
    $message .= "<strong>Valor " . $data['monthly_message'] . ":</strong> R$ " . number_format($morder->total, 2, ',', '.') . "<br/>";
    $message .= $data['date_message'] . $data['fecha_pago_proximo'] . "</p>";
    
    // Información específica del grupo con distribución de cuotas
    $message .= "<div style='background-color: #e3f2fd; border: 1px solid #2196f3; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    $message .= "<p><strong>📋 Informações do grupo:</strong></p>";
    $message .= "<ul>";
    $message .= "<li><strong>Grupo criado:</strong> " . $plano_info['group_name'] . "</li>";
    $message .= "<li><strong>Total de usuários:</strong> " . $plano_info['num_users'] . "</li>";
    $message .= "<li><strong>Você é o administrador do grupo</strong> com privilégios de gestão</li>";
    $message .= "<li><strong>Distribuição de armazenamento:</strong></li>";
    $message .= "<ul>";
    $message .= "<li>Você (admin): 30% da cota total</li>";
    $message .= "<li>Demais usuários: 70% dividido igualmente</li>";
    $message .= "</ul>";
    $message .= "<li>Usuários criados: " . $plano_info['admin_user'];
    for ($i = 1; $i < $plano_info['num_users']; $i++) {
        $message .= ", " . $plano_info['admin_user'] . "-" . $i;
    }
    $message .= "</li>";
    $message .= "</ul></div>";
    
    // Información importante sobre contraseñas
    $message .= "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    $message .= "<p><strong>🔐 Importante - Gestão de Senhas:</strong></p>";
    $message .= "<ul>";
    $message .= "<li><strong>Senha inicial:</strong> Todos os usuários do grupo compartilham a mesma senha inicial</li>";
    $message .= "<li><strong>Recomendação:</strong> Cada usuário deve alterar sua senha individualmente após o primeiro acesso</li>";
    $message .= "<li><strong>Como alterar:</strong> Configurações pessoais → Segurança → Alterar senha</li>";
    $message .= "<li><strong>Segurança:</strong> Como admin do grupo, você pode gerenciar os usuários e suas configurações</li>";
    $message .= "</ul></div>";
    
    // Recomendações gerais de segurança
    $message .= "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    $message .= "<p><strong>⚠️ Segurança do Grupo:</strong><br/>";
    $message .= "Por segurança, recomendamos:</p>";
    $message .= "<ul>";
    $message .= "<li>Compartilhar este e-mail apenas com usuários autorizados do grupo</li>";
    $message .= "<li>Solicitar que cada usuário altere sua senha no primeiro acesso</li>";
    $message .= "<li>Manter as credenciais do grupo em local seguro</li>";
    $message .= "<li>Como admin, você pode monitorar o uso de armazenamento de cada usuário</li>";
    $message .= "<li>Excluir este e-mail após distribuir as informações aos usuários</li>";
    $message .= "</ul></div>";
    
    // Informações de contato
    $message .= "<p>Se você tiver alguma dúvida sobre a gestão do grupo ou administração dos usuários, entre em contato conosco no e-mail: <a href='" . $mailto . "'>" . $brdrv_email . "</a>.</p>";
    $message .= "<p>Atenciosamente,<br/><strong>Equipe Brasdrive</strong></p>";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Enviar email
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
 * Envía email al administrador de Brasdrive - Actualizado con información de cuotas
 */
function send_nextcloud_banda_admin_email($data) {
    $user = $data['user'];
    $level = $data['level'];
    $config = $data['config'];
    $plano_info = $data['plano_info'];

    $to = get_option('admin_email');
    $subject = "Novo grupo Nextcloud Banda criado - " . $level->name;
    
    $admin_message = "<h2>Novo grupo Nextcloud Banda criado</h2>";
    $admin_message .= "<p><strong>Plano:</strong> " . $level->name . "<br/>";
    $admin_message .= "<strong>Nome:</strong> " . $user->display_name . "<br/>";
    $admin_message .= "<strong>Usuário:</strong> " . $user->user_login . "<br/>";
    $admin_message .= "<strong>Email:</strong> " . $user->user_email . "</p>";
    
    // Información del grupo creado
    $admin_message .= "<h3>Detalhes do grupo criado:</h3>";
    $admin_message .= "<p><strong>Nome do grupo:</strong> " . $plano_info['group_name'] . "<br/>";
    $admin_message .= "<strong>Admin do grupo:</strong> " . $plano_info['admin_user'] . " (com privilégios de administrador)<br/>";
    $admin_message .= "<strong>Total de usuários:</strong> " . $plano_info['num_users'] . "<br/>";
    $admin_message .= "<strong>Senha compartilhada inicial:</strong> Definida para todos os usuários</p>";
    
    // Configuração do plan con información de cuotas
    if (!empty($config)) {
        $admin_message .= "<h3>Configuração do plano Banda:</h3>";
        $admin_message .= "<p><strong>Armazenamento total:</strong> " . ($config['storage_display'] ?? 'N/A') . "<br/>";
        $admin_message .= "<strong>Usuários:</strong> " . ($config['users_display'] ?? 'N/A') . "<br/>";
        $admin_message .= "<strong>Frequência de Pagamento:</strong> " . ($config['frequency_display'] ?? 'N/A') . "<br/>";
        
        // Información adicional de cuotas
        $storage_tb = (int)str_replace('tb', '', $config['storage_space'] ?? '1tb');
        $total_quota_gb = $storage_tb * 1024;
        $admin_quota_gb = round($total_quota_gb * 0.30);
        $user_quota_gb = $plano_info['num_users'] > 1 ? round(($total_quota_gb - $admin_quota_gb) / ($plano_info['num_users'] - 1)) : 0;
        
        $admin_message .= "<strong>Distribuição de cotas:</strong><br/>";
        $admin_message .= "- Admin (" . $plano_info['admin_user'] . "): " . $admin_quota_gb . "GB (30%)<br/>";
        if ($plano_info['num_users'] > 1) {
            $admin_message .= "- Demais usuários: " . $user_quota_gb . "GB cada (70% dividido)</p>";
        } else {
            $admin_message .= "- Nenhum usuário adicional</p>";
        }
    }
    
    $admin_message .= "<p><strong>Data do pedido:</strong> " . $data['fecha_pedido'] . "<br/>";
    $admin_message .= "<strong>Próximo pagamento:</strong> " . $data['fecha_pago_proximo'] . "</p>";

    $admin_message .= "<p><em>Nota: Os usuários foram configurados com senha compartilhada inicial. Foi recomendado que cada usuário altere sua senha individualmente.</em></p>";

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
 * Función mejorada para crear plan y usuarios en Nextcloud
 * CORREGIDO basado en el snippet antiguo que funciona
 */
function crear_nextcloud_banda($user_id, $num_users) {
    try {
        // [Código anterior igual hasta la creación de usuarios...]
        
        // 3. Crear el usuario principal (admin del grupo)
        $admin_display_name = sanitize_text_field($main_user->display_name);
        if (empty($admin_display_name)) {
            $admin_display_name = $main_user->user_login;
        }

        // Crear usuario principal - FORMATO CORRECTO basado en snippet antiguo
        $user_response = call_nextcloud_api('/ocs/v1.php/cloud/users', [
            'userid' => $main_user->user_login, 
            'password' => $shared_password,
            'displayName' => $admin_display_name,
            'email' => $main_user->user_email,
            'groups[]' => $group_name // CORREGIDO: agregar grupo durante creación
        ], 'POST');
        
        // Verificar creación del usuario principal
        if (isset($user_response['ocs']['meta']['statuscode']) && $user_response['ocs']['meta']['statuscode'] == 100) {
            
            // CORREGIDO: Asignar quota usando el formato DEL SNIPPET ANTIGUO
            $quota_response = call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($main_user->user_login), [
                'key' => 'quota',
                'value' => $admin_quota_gb . ' GB' // FORMATO CORRECTO: "X GB"
            ], 'PUT');
            
            // CORREGIDO: Deshabilitar perfil público
            call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($main_user->user_login), [
                'key' => 'profile_enabled',
                'value' => 'false'
            ], 'PUT');
            
            // CORREGIDO: Establecer locale
            call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($main_user->user_login), [
                'key' => 'locale',
                'value' => 'pt_BR'
            ], 'PUT');
            
            // Establecer como administrador del grupo
            $subadmin_response = call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($main_user->user_login) . '/subadmins', [
                'groupid' => $group_name
            ], 'POST');
            
            nextcloud_create_banda_log_info('Main user created as group admin', [
                'username' => $main_user->user_login,
                'group_name' => $group_name,
                'quota_gb' => $admin_quota_gb,
                'quota_success' => isset($quota_response['ocs']['meta']['statuscode']) && $quota_response['ocs']['meta']['statuscode'] == 100
            ]);
            
        } else {
            // [Manejo de errores...]
        }

        // 4. Crear usuarios adicionales con el mismo formato
        $users_created = 1;
        
        for ($i = 1; $i < $num_users; $i++) {
            $additional_username = $main_user->user_login . '-' . $i;
            $additional_email = str_replace('@', "+user{$i}@", $main_user->user_email);
            $additional_display_name = $admin_display_name . ' User' . ($i + 1);
            
            // CORREGIDO: Mismo formato que snippet antiguo
            $user_response = call_nextcloud_api('/ocs/v1.php/cloud/users', [
                'userid' => $additional_username, 
                'password' => $shared_password,
                'displayName' => $additional_display_name,
                'email' => $additional_email,
                'groups[]' => $group_name
            ], 'POST');
            
            if (isset($user_response['ocs']['meta']['statuscode']) && $user_response['ocs']['meta']['statuscode'] == 100) {
                // CORREGIDO: Asignar quota con formato correcto
                call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($additional_username), [
                    'key' => 'quota',
                    'value' => $user_quota_gb . ' GB'
                ], 'PUT');
                
                // Configuraciones adicionales como snippet antiguo
                call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($additional_username), [
                    'key' => 'profile_enabled',
                    'value' => 'false'
                ], 'PUT');
                
                call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($additional_username), [
                    'key' => 'locale',
                    'value' => 'pt_BR'
                ], 'PUT');
                
                $users_created++;
                
            } else {
                // [Manejo de errores...]
            }
        }

        // [Resto del código...]

    } catch (Exception $e) {
        // [Manejo de excepciones...]
    }
}

/**
 * Función AUXILIAR para manejar notificaciones (como en snippet antiguo)
 */
function send_nextcloud_notification($username, $short_message, $long_message) {
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN') ?: 'CloudBrasdrive';
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS') ?: '*PropoEterCloudBrdrv#';
    
    $notification_url = "https://cloud.brasdrive.com.br/ocs/v2.php/apps/notifications/api/v2/admin_notifications/" . urlencode($username);
    
    return call_nextcloud_api($notification_url, [
        'shortMessage' => $short_message,
        'longMessage' => $long_message
    ], 'POST');
}

/**
 * Función específica para establecer quota de usuario en Nextcloud
 * CORREGIDO el formato y método de asignación
 */
function set_nextcloud_user_quota($username, $quota) {
    // Nextcloud espera el formato: "X GB" (con espacio)
    // Endpoint: PUT /ocs/v1.php/cloud/users/{userid}
    
    $response = call_nextcloud_api('/ocs/v1.php/cloud/users/' . urlencode($username), [
        'key' => 'quota',
        'value' => $quota
    ], 'PUT');
    
    if (isset($response['ocs']['meta']['statuscode']) && $response['ocs']['meta']['statuscode'] == 100) {
        return ['success' => true];
    } else {
        $error_msg = $response['ocs']['meta']['message'] ?? 'Unknown error setting quota';
        nextcloud_create_banda_log_error('Failed to set user quota', [
            'username' => $username,
            'quota' => $quota,
            'error' => $error_msg,
            'status_code' => $response['ocs']['meta']['statuscode'] ?? 'unknown'
        ]);
        return ['success' => false, 'error' => $error_msg];
    }
}

/**
 * Realiza una llamada SEGURA a la API de Nextcloud - COMPATIBLE con formato antiguo
 */
function call_nextcloud_api($endpoint, $data = [], $method = 'POST') {
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN') ?: 'CloudBrasdrive';
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS') ?: '*PropoEterCloudBrdrv#';

    $url = 'https://cloud.brasdrive.com.br' . $endpoint;
    
    // Headers compatibles con snippet antiguo
    $headers = [
        'Authorization' => 'Basic ' . base64_encode($nextcloud_api_admin . ':' . $nextcloud_api_pass),
        'OCS-APIRequest' => 'true',
        'Content-Type' => 'application/x-www-form-urlencoded'
    ];

    $args = [
        'method' => $method,
        'headers' => $headers,
        'timeout' => 30,
        'sslverify' => true
    ];

    // CORREGIDO: Formato compatible con snippet antiguo
    if (!empty($data) && ($method === 'POST' || $method === 'PUT')) {
        $args['body'] = http_build_query($data);
    } elseif (!empty($data)) {
        $url .= '?' . http_build_query($data);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    $body = wp_remote_retrieve_body($response);
    
    // Manejar respuestas XML de Nextcloud (como en snippet antiguo)
    if (function_exists('simplexml_load_string')) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml !== false) {
            $json = json_encode($xml);
            return json_decode($json, true);
        }
    }
    
    // Intentar JSON como fallback
    $json_response = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json_response;
    }
    
    return ['raw_response' => $body];
}

// ====
// FUNCIONES AUXILIARES ESPECÍFICAS PARA BANDA
// ====

/**
 * Obtiene nombres display para usuarios
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

/**
 * Reutilizar funciones del sistema TI (si están disponibles)
 */
if (!function_exists('get_storage_banda_display_name')) {
    function get_storage_banda_display_name($storage_space) {
        $storage_options = [
            '1tb' => '1 Terabyte', '2tb' => '2 Terabytes', '3tb' => '3 Terabytes',
            '4tb' => '4 Terabytes', '5tb' => '5 Terabytes', '6tb' => '6 Terabytes',
            '7tb' => '7 Terabytes', '8tb' => '8 Terabytes', '9tb' => '9 Terabytes',
            '10tb' => '10 Terabytes', '15tb' => '15 Terabytes', '20tb' => '20 Terabytes'
        ];
        return $storage_options[$storage_space] ?? $storage_space;
    }
}

if (!function_exists('get_frequency_banda_display_name')) {
    function get_frequency_banda_display_name($payment_frequency) {
        $frequency_options = [
            'monthly' => 'Mensal',
            'semiannual' => 'Semestral',
            'annual' => 'Anual',
            'biennial' => 'Bienal',
            'triennial' => 'Trienal',
            'quadrennial' => 'Quadrienal',
            'quinquennial' => 'Quinquenal'
        ];
        return $frequency_options[$payment_frequency] ?? $payment_frequency;
    }
}

if (!function_exists('get_frequency_banda_messages')) {
    function get_frequency_banda_messages($payment_frequency) {
        $messages = [
            'monthly' => [
                'monthly_message' => 'mensal ',
                'date_message' => 'Data do próximo pagamento: '
            ],
            'semiannual' => [
                'monthly_message' => 'semestral ',
                'date_message' => 'Data da próxima cobrança semestral: '
            ],
            'annual' => [
                'monthly_message' => 'anual ',
                'date_message' => 'Data da próxima cobrança anual: '
            ],
            'biennial' => [
                'monthly_message' => 'bienal ',
                'date_message' => 'Data da próxima cobrança (em 2 anos): '
            ],
            'triennial' => [
                'monthly_message' => 'trienal ',
                'date_message' => 'Data da próxima cobrança (em 3 anos): '
            ],
            'quadrennial' => [
                'monthly_message' => 'quadrienal ',
                'date_message' => 'Data da próxima cobrança (em 4 anos): '
            ],
            'quinquennial' => [
                'monthly_message' => 'quinquenal ',
                'date_message' => 'Data da próxima cobrança (em 5 anos): '
            ]
        ];

        return $messages[$payment_frequency] ?? $messages['monthly'];
    }
}

if (!function_exists('get_pmpro_next_payment_banda_date')) {
    function get_pmpro_next_payment_banda_date($user_id, $level) {
        // Usar función nativa de PMPro si está disponible
        if (function_exists('pmpro_next_payment')) {
            $next_payment = pmpro_next_payment($user_id);
            if (!empty($next_payment)) {
                return date('d/m/Y', $next_payment);
            }
        }

        // Fallback: calcular basado en el nivel y la última orden
        if (class_exists('MemberOrder')) {
            $last_order = new MemberOrder();
            $last_order->getLastMemberOrder($user_id, 'success');
            
            if (!empty($last_order->timestamp)) {
                $last_payment_timestamp = is_numeric($last_order->timestamp) 
                    ? $last_order->timestamp 
                    : strtotime($last_order->timestamp);
                
                // Calcular próximo pago basado en el ciclo
                $cycle_seconds = get_cycle_seconds_from_banda_level($level);
                $next_payment_timestamp = $last_payment_timestamp + $cycle_seconds;
                
                return date('d/m/Y', $next_payment_timestamp);
            }
        }

        // Último fallback: basado en la fecha actual y ciclo del nivel
        $cycle_seconds = get_cycle_seconds_from_banda_level($level);
        $next_payment_timestamp = current_time('timestamp') + $cycle_seconds;
        
        return date('d/m/Y', $next_payment_timestamp);
    }
}

if (!function_exists('get_cycle_seconds_from_banda_level')) {
    function get_cycle_seconds_from_banda_level($level) {
        if (empty($level->cycle_number) || empty($level->cycle_period)) {
            return 30 * DAY_IN_SECONDS; // Default: 30 días
        }

        $multipliers = [
            'Day' => DAY_IN_SECONDS,
            'Week' => WEEK_IN_SECONDS,
            'Month' => 30 * DAY_IN_SECONDS,
            'Year' => YEAR_IN_SECONDS
        ];

        $multiplier = $multipliers[$level->cycle_period] ?? (30 * DAY_IN_SECONDS);
        return $level->cycle_number * $multiplier;
    }
}

// Activación del hook
add_action('pmpro_after_checkout', 'nextcloud_banda_pmpro_after_checkout', 10, 2);

nextcloud_create_banda_log_info('Nextcloud Banda email system loaded successfully');
