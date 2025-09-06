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
        $num_users = isset($config_data['num_users']) ? intval($config_data['num_users']) : (isset($_REQUEST['num_users']) ? intval($_REQUEST['num_users']) : 2);

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
        $shared_password = wp_generate_password(12, false, false);
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
 * 
 * @param int $user_id ID del usuario
 * @param object $morder Objeto de orden de membresía
 * @param int $num_users Número de usuarios a crear
 * @param string $shared_password Contraseña compartida para los usuarios
 * @return bool True si se procesó correctamente, false en caso de error
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
        // Obtener y validar información del usuario
        $user = get_userdata($user_id);
        if (!$user) {
            nextcloud_create_banda_log_error('User not found', ['user_id' => $user_id]);
            return false;
        }

        // Obtener y validar nivel de membresía
        $level = pmpro_getMembershipLevelForUser($user_id);
        if (!$level) {
            nextcloud_create_banda_log_error('No membership level found for user', ['user_id' => $user_id]);
            return false;
        }

        // Preparar datos básicos
        $user_info = [
            'email' => $user->user_email,
            'username' => $user->user_login,
            'displayname' => $user->display_name ?: $user->user_login
        ];

        // Procesar configuración y cuotas
        $config_data = get_nextcloud_create_banda_user_config($user_id);
        $storage_config = process_storage_configuration($config_data);
        $quotas = calculate_user_quotas($storage_config['total_gb'], $num_users);

        // --- INICIO: CÁLCULO Y ALMACENAMIENTO DEL PRORRATEO ---
        $prorated_amount = 0.0;
        $prorated_days_remaining = 0;
        $prorated_cycle_days = 0;

        // Obtener configuración previa (si existe) para detectar upgrades
        $current_config_json = get_user_meta($user_id, 'nextcloud_banda_config', true);
        $current_config = $current_config_json ? json_decode($current_config_json, true) : null;

        if ($current_config && !empty($current_config['storage_space'])) {
            // Parsing robusto para la config previa (mismo formato que arriba)
            $cur_storage_str = strtolower($current_config['storage_space']);
            $cur_total_gb = 1024;
            if (preg_match('/^([\d\.]+)\s*tb$/i', $cur_storage_str, $mcur)) {
                $cur_total_gb = intval(round(floatval($mcur[1]) * 1024));
            } elseif (preg_match('/^([\d\.]+)\s*gb$/i', $cur_storage_str, $mcur)) {
                $cur_total_gb = intval(round(floatval($mcur[1])));
            } elseif (preg_match('/^(\d+)\s*$/', $cur_storage_str, $mcur)) {
                $cur_total_gb = intval($mcur[1]) * 1024;
            }

            // Comparar y aplicar prorrateo solo si hay upgrade de storage (nuevo > actual)
            if ($storage_config['total_gb'] > $cur_total_gb) {
                // Diferencia en TB (usar valor en TB para precio por TB)
                $tb_diff = ($storage_config['total_gb'] - $cur_total_gb) / 1024.0;

                // ✅ CORRECCIÓN: Usar mismo precio que dynamic pricing (70.00)
                $config_data = nextcloud_banda_get_config();
                $price_per_tb = $config_data['price_per_tb'] ?? 70.00;
                $price_per_tb = floatval($price_per_tb);

                // Precio total extra (completo)
                $full_price_diff = $tb_diff * $price_per_tb;

                // Determinar días restantes en el ciclo usando MemberOrder o cálculo por level
                $days_remaining = 0;
                $cycle_days = 0;
                if (class_exists('MemberOrder')) {
                    $last_order = new MemberOrder();
                    $last_order->getLastMemberOrder($user_id, 'success');
                    if (!empty($last_order->timestamp)) {
                        $last_payment_ts = is_numeric($last_order->timestamp) ? intval($last_order->timestamp) : strtotime($last_order->timestamp);
                        $cycle_seconds = get_banda_cycle_seconds_from_level($level);
                        $next_payment_ts = $last_payment_ts + intval($cycle_seconds);
                        $now_ts = current_time('timestamp');
                        $days_remaining = max(0, ceil(($next_payment_ts - $now_ts) / DAY_IN_SECONDS));
                        $cycle_days = max(1, ceil($cycle_seconds / DAY_IN_SECONDS));
                    }
                }

                // Si no pudimos obtener last_order o días, como fallback intentar calcular ciclo desde level actual
                if ($cycle_days === 0) {
                    $cycle_seconds = get_banda_cycle_seconds_from_level($level);
                    $cycle_days = max(1, ceil($cycle_seconds / DAY_IN_SECONDS));
                    // si no hay last payment, asumimos cobro completo el primer día (no prorratear)
                    $days_remaining = 0;
                }

                if ($days_remaining > 0 && $full_price_diff > 0) {
                    $prorated_amount = round(($full_price_diff / $cycle_days) * $days_remaining, 2);
                    $prorated_days_remaining = intval($days_remaining);
                    $prorated_cycle_days = intval($cycle_days);

                    // Guardar para trazabilidad en user_meta
                    update_user_meta($user_id, 'nextcloud_banda_prorated_amount', $prorated_amount);
                    update_user_meta($user_id, 'nextcloud_banda_prorated_days_remaining', $prorated_days_remaining);
                    update_user_meta($user_id, 'nextcloud_banda_prorated_cycle_days', $prorated_cycle_days);

                    nextcloud_create_banda_log_info('Prorated charge calculated', [
                        'user_id' => $user_id,
                        'cur_total_gb' => $cur_total_gb,
                        'new_total_gb' => $storage_config['total_gb'],
                        'tb_diff' => $tb_diff,
                        'full_price_diff' => $full_price_diff,
                        'prorated_amount' => $prorated_amount,
                        'days_remaining' => $prorated_days_remaining,
                        'cycle_days' => $prorated_cycle_days
                    ]);
                }
            }
        }
        // --- FIN: CÁLCULO Y ALMACENAMIENTO DEL PRORRATEO ---

        // Preparar fechas
        $fechas = prepare_dates($morder, $user_id, $level);

        // Preparar información del grupo (incluyendo prorrateo)
        $grupo_info = prepare_group_info($user_id, $user_info['username'], $num_users, $shared_password, $quotas, $storage_config['total_gb']);
        $grupo_info['prorated_amount'] = $prorated_amount;
        $grupo_info['prorated_days_remaining'] = $prorated_days_remaining;
        $grupo_info['prorated_cycle_days'] = $prorated_cycle_days;

        // Preparar y enviar emails
        $email_results = send_emails($user, $level, $morder, $config_data, $fechas, $grupo_info);

        // Registrar resultado final
        log_processing_result($user_id, $user_info, $grupo_info, $level, $email_results, $config_data, $quotas);

        return true;

    } catch (Exception $e) {
        handle_exception($e, $user_id);
        return false;
    }
}

// ================= FUNCIONES AUXILIARES =================

/**
 * Procesa la configuración de almacenamiento
 */
function process_storage_configuration($config_data) {
    $storage_str = strtolower($config_data['storage_space'] ?? '1tb');
    $total_gb = 1024; // default 1TB

    if (preg_match('/^([\d\.]+)\s*tb$/i', $storage_str, $m)) {
        $total_gb = intval(round(floatval($m[1]) * 1024));
    } elseif (preg_match('/^([\d\.]+)\s*gb$/i', $storage_str, $m)) {
        $total_gb = intval(round(floatval($m[1])));
    } elseif (preg_match('/^(\d+)\s*$/', $storage_str, $m)) {
        $total_gb = intval($m[1]) * 1024;
    }

    error_log('CONFIG DATA: ' . print_r($config_data, true));
    
    return [
        'storage_str' => $storage_str,
        'total_gb' => $total_gb
    ];
}

/**
 * Calcula las cuotas de usuarios
 */
function calculate_user_quotas($total_gb, $num_users) {
    $quotas = calc_quotas_banda($total_gb, $num_users);
    
    return [
        'admin' => intval($quotas['admin']),
        'others_each' => intval($quotas['others_each'])
    ];
}

/**
 * Prepara las fechas relevantes
 */
function prepare_dates($morder, $user_id, $level) {
    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
    
    $order_timestamp = !empty($morder->timestamp) ? $morder->timestamp : current_time('timestamp');
    $dt->setTimestamp($order_timestamp);
    
    return [
        'fecha_pedido' => $dt->format('d/m/Y H:i:s'),
        'fecha_pago_proximo' => get_pmpro_banda_next_payment_date($user_id, $level)
    ];
}

/**
 * Prepara la información del grupo
 */
function prepare_group_info($user_id, $username, $num_users, $shared_password, $quotas, $total_gb) {
    return [
        'group_name' => 'banda-' . $user_id,
        'num_users' => $num_users,
        'admin_user' => $username,
        'password' => $shared_password,
        'quota_admin_gb' => $quotas['admin'],
        'quota_user_gb' => $quotas['others_each'],
        'total_gb' => $total_gb
    ];
}

/**
 * Prepara y envía los emails
 */
function send_emails($user, $level, $morder, $config_data, $fechas, $grupo_info) {
    $email_data = prepare_nextcloud_create_banda_email_data(
        $user, 
        $level, 
        $morder, 
        $config_data, 
        [
            'fecha_pedido' => $fechas['fecha_pedido'],
            'fecha_pago_proximo' => $fechas['fecha_pago_proximo'],
            'grupo_info' => $grupo_info
        ]
    );

    return [
        'user_email_sent' => send_nextcloud_create_banda_user_email($email_data),
        'admin_email_sent' => send_nextcloud_create_banda_admin_email($email_data)
    ];
}

/**
 * Registra el resultado del procesamiento
 */
function log_processing_result($user_id, $user_info, $grupo_info, $level, $email_results, $config_data, $quotas) {
    nextcloud_create_banda_log_info('Nextcloud Banda plan processing completed', [
        'user_id' => $user_id,
        'username' => $user_info['username'],
        'group_name' => $grupo_info['group_name'],
        'num_users' => $grupo_info['num_users'],
        'level_name' => $level->name,
        'user_email_sent' => $email_results['user_email_sent'],
        'admin_email_sent' => $email_results['admin_email_sent'],
        'config_data' => $config_data,
        'quota_admin_gb' => $quotas['admin'],
        'quota_user_gb' => $quotas['others_each']
    ]);
}

/**
 * Maneja excepciones
 */
function handle_exception($e, $user_id) {
    nextcloud_create_banda_log_error('Exception in plan_nextcloud_banda', [
        'user_id' => $user_id,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Prepara los datos para los emails Banda
 */
function prepare_nextcloud_create_banda_email_data($user, $level, $morder, $config_data, $additional_data) {
    // Determinar mensajes basados en la frecuencia
    $frequency_messages = get_banda_frequency_messages($config_data['payment_frequency'] ?? 'monthly');
    
    return [
        'user' => $user,
        'level' => $level,
        'morder' => $morder,
        'config' => $config_data,
        'fecha_pedido' => $additional_data['fecha_pedido'],
        'fecha_pago_proximo' => $additional_data['fecha_pago_proximo'],
        'grupo_info' => $additional_data['grupo_info'],
        'monthly_message' => $frequency_messages['monthly_message'],
        'date_message' => $frequency_messages['date_message']
    ];
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

    $subject = "Seu plano Nextcloud Banda foi criado";
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

    // Mostrar prorrateo si existe
    if (!empty($grupo_info['prorated_amount']) && floatval($grupo_info['prorated_amount']) > 0) {
        $message .= "<div style='background:#fff8e1;border:1px solid #ffecb3;padding:12px;margin:18px 0;border-radius:6px;'>";
        $message .= "<p><strong>⚖️ Valor prorrateado cobrado hoje:</strong> R$ " . number_format($grupo_info['prorated_amount'], 2, ',', '.') . "</p>";
        $message .= "<p>Esse valor corresponde a um ajuste pelo aumento de armazenamento contratado. Restam <strong>{$grupo_info['prorated_days_remaining']}</strong> dias do ciclo (de um total de {$grupo_info['prorated_cycle_days']} dias).</p>";
        $message .= "</div>";
    }

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

    // ... resto del código del email (mantener todo lo que sigue igual) ...
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
    $subject = "Novo plano Nextcloud Banda criado";

    $admin_message = "<h2>Novo plano Nextcloud Banda criado</h2>";
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
 * Convierte el valor de cuota a GB para la API de Nextcloud.
 * Ejemplo: "1TB" => "1024GB", "0.75TB" => "768GB", "500GB" => "500GB"
 */
function convert_quota_to_gb($quota_str) {
    $quota_str = strtoupper(trim($quota_str));
    if (strpos($quota_str, 'TB') !== false) {
        $tb_val = floatval(str_replace('TB', '', $quota_str));
        $gb_val = intval($tb_val * 1024);
        return $gb_val . 'GB';
    } elseif (strpos($quota_str, 'GB') !== false) {
        $gb_val = floatval(str_replace('GB', '', $quota_str));
        return intval($gb_val) . 'GB';
    }
    // Fallback: si no hay unidad, asumir GB
    return intval($quota_str) . 'GB';
}

/**
 * Mejoras en crear un grupo de Nextcloud y usuarios asociados (Agregados delays, logging, groupadmin y ASIGNACIÓN DE CUOTAS DINÁMICAS con unidad TB/GB)
 */
function crear_nextcloud_banda($main_username, $main_email, $group_name, $num_users = 2, $shared_password) {
    // Obtener la configuración dinámica del usuario Banda
    $user_id = 0;
    if (preg_match('/banda-(\d+)/', $group_name, $m)) {
        $user_id = intval($m[1]);
    }
    $config_data = get_nextcloud_create_banda_user_config($user_id);

    // ---------- INICIO: Parsing robusto del storage y cálculo de cuotas en GB ----------
    $storage_str = strtolower($config_data['storage_space'] ?? '1tb');
    $total_gb = 1024; // default 1TB

    // Manejar TB (incluye decimales) y GB
    if (preg_match('/^([\d\.]+)\s*tb$/i', $storage_str, $m)) {
        $total_gb = intval(round(floatval($m[1]) * 1024));
    } elseif (preg_match('/^([\d\.]+)\s*gb$/i', $storage_str, $m)) {
        $total_gb = intval(round(floatval($m[1])));
    } elseif (preg_match('/^(\d+)\s*$/', $storage_str, $m)) {
        // si vienen solo números, asumir TB
        $total_gb = intval($m[1]) * 1024;
    }

    // Obtener cuotas en GB usando la función centralizada (enteros)
    $quotas = calc_quotas_banda($total_gb, $num_users);
    $admin_quota_gb = intval($quotas['admin']) . 'GB';
    $other_quota_gb = intval($quotas['others_each']) . 'GB';
    // ---------- FIN: Parsing y cuotas ----------

    // Log para depuración
    nextcloud_create_banda_log_info('Calculated quotas', [
        'user_id' => $user_id,
        'storage_str' => $storage_str,
        'total_gb' => $total_gb,
        'num_users' => $num_users,
        'admin_quota_gb' => $admin_quota_gb,
        'other_quota_gb' => $other_quota_gb
    ]);

    // Crear grupo
    $group_result = call_nextcloud_api('groups', 'POST', ['groupid' => $group_name]);
    error_log("[Nextcloud Debug] Grupo creado: " . json_encode($group_result));
    if (!isset($group_result['statuscode']) || $group_result['statuscode'] !== 100) {
        error_log("[Nextcloud Banda] ERROR: Failed to create group | " . json_encode($group_result));
        return false;
    }
    usleep(400000);

    // Crear usuario principal
    $user_data = [
        'userid'   => $main_username,
        'password' => $shared_password,
        'groups[]' => $group_name
    ];

    $main_user_result = call_nextcloud_api('users', 'POST', $user_data);
    error_log("[Nextcloud Debug] Usuario principal creado: " . json_encode($main_user_result));
    if (!isset($main_user_result['statuscode']) || $main_user_result['statuscode'] !== 100) {
        error_log("[Nextcloud Banda] ERROR: Failed to create main user | " . json_encode($main_user_result));
        return false;
    }
    usleep(400000);

    // Configurar propiedades del usuario principal
    $main_user_configs = [
        'displayname' => ['value' => $main_username, 'debug' => 'Display name'],
        'email'       => ['value' => $main_email, 'debug' => 'email'],
        'locale'      => ['value' => 'pt_BR', 'debug' => 'locale'],
        'quota'       => ['value' => $admin_quota_gb, 'debug' => 'quota']
    ];

    foreach ($main_user_configs as $key => $config) {
        $result = call_nextcloud_api("users/$main_username", 'PUT', [
            'key' => $key,
            'value' => $config['value']
        ]);
        
        error_log("[Nextcloud Debug] Asignado {$config['debug']} a usuario principal: " . json_encode($result));
        
        if (!isset($result['statuscode']) || $result['statuscode'] !== 100) {
            $error_type = ($key === 'quota') ? 'WARNING' : 'ERROR';
            error_log("[Nextcloud Banda] $error_type: Failed to define {$config['debug']} to main user | " . json_encode($result));
            
            if ($error_type === 'ERROR') {
                return false;
            }
        }
        usleep(400000);
    }

    // Crear usuarios adicionales con delay, logging y ASIGNACIÓN DE CUOTA DINÁMICA EN GB
    for ($i = 1; $i < $num_users; $i++) {
        $username = sanitize_user($main_username . "-$i");
        $user_data = [
            'userid' => $username,
            'password' => $shared_password,
            'groups[]' => $group_name,
        ];
        
        error_log("[Nextcloud Debug] Creando usuario adicional $username con data: " . json_encode($user_data));
        
        $result = call_nextcloud_api('users', 'POST', $user_data);
        error_log("[Nextcloud Debug] Usuario adicional resultado para $username: " . json_encode($result));
        
        if (!isset($result['statuscode']) || $result['statuscode'] !== 100) {
            error_log("[Nextcloud Banda] ERROR: Failed to create additional user | username=$username | " . json_encode($result));
            continue; // Continuar con el siguiente usuario en lugar de retornar false
        }

        // Configurar propiedades de usuarios adicionales
        $additional_user_configs = [
            'locale' => ['value' => 'pt_BR', 'debug' => 'locale'],
            'quota'  => ['value' => $other_quota_gb, 'debug' => 'quota']
        ];

        foreach ($additional_user_configs as $key => $config) {
            $config_result = call_nextcloud_api("users/$username", 'PUT', [
                'key' => $key,
                'value' => $config['value']
            ]);
            
            error_log("[Nextcloud Debug] Asignado {$config['debug']} a $username: " . json_encode($config_result));
            
            if (!isset($config_result['statuscode']) || $config_result['statuscode'] !== 100) {
                $error_type = ($key === 'quota') ? 'WARNING' : 'ERROR';
                error_log("[Nextcloud Banda] $error_type: Failed to define {$config['debug']} to $username | " . json_encode($config_result));
                
                if ($error_type === 'ERROR') {
                    return false;
                }
            }
            usleep(400000);
        }
    }

    return true;
}

/**
 * Función robusta y segura para llamar a la API de Nextcloud
 */
function call_nextcloud_api($endpoint, $method = 'POST', $data = []) {
    // Obtener las constantes de la URL y la API de Nextcloud
    $site_url = 'brasdrive.com.br'; //get_option('siteurl');
    $nextcloud_api_url = 'https://cloud.' . $site_url; //parse_url($site_url, PHP_URL_HOST);
    $nextcloud_api_admin = 'CloudBrasdrive'; //getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = '*PropoEterCloudBrdrv#'; //getenv('NEXTCLOUD_API_PASS');

    $auth = "$nextcloud_api_admin:$nextcloud_api_pass";
    $nextcloud_url = trailingslashit($nextcloud_api_url) . 'ocs/v1.php/cloud/' . ltrim($endpoint, '/');
    $args = [
        'method'    => $method,
        'headers'   => [
            'OCS-APIRequest' => 'true',
            'Authorization'  => 'Basic ' . base64_encode($auth),
        ],
        'sslverify' => true,
        'timeout'   => 15,
    ];
    if (!empty($data)) {
        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = $data;
        } else {
            $nextcloud_url = add_query_arg($data, $nextcloud_url);
        }
    }
    error_log("[Nextcloud Debug] Solicitud API: $method $nextcloud_url | Data: " . json_encode($data));
    $response = wp_remote_request($nextcloud_url, $args);
    if (is_wp_error($response)) {
        error_log("[Nextcloud Debug] Error WP: " . $response->get_error_message() . " | URL: $nextcloud_url");
        return ['status' => 'error', 'message' => $response->get_error_message()];
    }
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($http_code != 200) {
        error_log("[Nextcloud Debug] HTTP Error: $http_code | URL: $nextcloud_url | Response: $body");
        return ['status' => 'error', 'http_code' => $http_code, 'message' => 'Error en la solicitud'];
    }
    $xml = simplexml_load_string($body);
    if ($xml === false) {
        error_log("[Nextcloud Debug] Error parsing XML | Response: $body | URL: $nextcloud_url");
        return ['status' => 'error', 'message' => 'Error parsing XML'];
    }
    $status     = isset($xml->meta->status) ? (string)$xml->meta->status : 'unknown';
    $statuscode = isset($xml->meta->statuscode) ? (int)$xml->meta->statuscode : 0;
    $message    = isset($xml->meta->message) ? (string)$xml->meta->message : '';
    $data_resp  = isset($xml->data) ? json_decode(json_encode($xml->data), true) : null;
    error_log("[Nextcloud Debug] Respuesta API: endpoint=$endpoint | status=$status | statuscode=$statuscode | message=$message | data=" . json_encode($data_resp));
    return ['status' => $status, 'statuscode' => $statuscode, 'message' => $message, 'data' => $data_resp];
}

/**
 * Obtiene la configuración dinámica del usuario Banda
 */
function get_nextcloud_create_banda_user_config($user_id) {
    $config_json = get_user_meta($user_id, 'nextcloud_banda_config', true);
    if (!empty($config_json)) {
        $config = json_decode($config_json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $config['storage_display'] = get_banda_storage_display_name($config['storage_space'] ?? '1tb');
            $config['users_display'] = get_users_display_name($config['num_users'] ?? 2);
            $config['frequency_display'] = get_banda_frequency_display_name($config['payment_frequency'] ?? 'monthly');
            return $config;
        }
    }
    // Solo usar valores por defecto si no hay config personalizada
    return [
        'storage_space' => '1tb',
        'num_users' => 2,
        'payment_frequency' => 'monthly',
        'storage_display' => '1 Terabyte',
        'users_display' => '2 usuários (incluídos)',
        'frequency_display' => 'Mensal'
    ];
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
function get_banda_storage_display_name($storage_space) {
    $storage_options = [
        '1tb' => '1 Terabyte', '2tb' => '2 Terabytes', '3tb' => '3 Terabytes',
        '4tb' => '4 Terabytes', '5tb' => '5 Terabytes', '6tb' => '6 Terabytes',
        '7tb' => '7 Terabytes', '8tb' => '8 Terabytes', '9tb' => '9 Terabytes',
        '10tb' => '10 Terabytes', '15tb' => '15 Terabytes', '20tb' => '20 Terabytes'
    ];
    return $storage_options[$storage_space] ?? $storage_space;
}
function get_banda_frequency_display_name($payment_frequency) {
    $frequency_options = [
        'monthly' => 'Mensal', 'semiannual' => 'Semestral',
        'annual' => 'Anual', 'biennial' => 'Bienal', 'triennial' => 'Trienal',
        'quadrennial' => 'Quadrienal', 'quinquennial' => 'Quinquenal'
    ];
    return $frequency_options[$payment_frequency] ?? $payment_frequency;
}
function get_banda_frequency_messages($payment_frequency) {
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
function get_pmpro_banda_next_payment_date($user_id, $level) {
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
            $cycle_seconds = get_banda_cycle_seconds_from_level($level);
            $next_payment_timestamp = $last_payment_timestamp + $cycle_seconds;
            return date('d/m/Y', $next_payment_timestamp);
        }
    }
    $cycle_seconds = get_banda_cycle_seconds_from_level($level);
    $next_payment_timestamp = current_time('timestamp') + $cycle_seconds;
    return date('d/m/Y', $next_payment_timestamp);
}
function get_banda_cycle_seconds_from_level($level) {
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
