<?php
/**
 * Crea o actualiza usuario en Nextcloud manteniendo el aislamiento por grupos
 * 
 * @param int $user_id ID de usuario WordPress
 * @param object $morder Objeto de orden de PMPro
 * @return void
 */
function crear_usuario_nextcloud_optimizado($user_id, $morder) {
    // 1. Verificar si el usuario ya existe en Nextcloud
    $created_in_nextcloud = get_user_meta($user_id, 'created_in_nextcloud', true);
    
    // 2. Obtener datos del usuario
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("Usuario WordPress no encontrado (ID: $user_id)");
        return;
    }

    // 3. Configuración básica
    $username = sanitize_user($user->user_login);
    $email = sanitize_email($user->user_email);
    $displayname = $user->display_name ?: $username;
    $level = pmpro_getMembershipLevelForUser($user_id);
    
    // 4. Configuración de zona horaria y fechas
    $dt = new DateTime('now', new DateTimeZone('America/Boa_Vista'));
    $dt->setTimestamp($morder->timestamp);
    $fecha_pedido = $dt->format('d/m/Y H:i:s');
    $fecha_pago_proximo_mes = ajustar_proxima_fecha_pago($user_id);

    // 5. Determinar configuración del plan
    $quota_parts = explode(" ", $level->name);
    $plan_type = strtolower($quota_parts[1]);
    $user_group = $plan_type . $user_id;
    $total_quota = ($quota_parts[2] >= 1000) ? $quota_parts[2]/1000 : $quota_parts[2];
    $measure_quota = ($quota_parts[2] >= 1000) ? "TB" : "GB";
    $user_quota = $total_quota . $measure_quota;
    $user_status = '{"statusType": "invisible"}';
    $locale = 'pt_BR';

    // 6. Mensajes según tipo de plan
    $is_trial = ($level->id === 5);
    $date_message = $is_trial ? "Avaliação gratuita até: " : "Data do próximo pagamento: ";
    $monthly_message = $is_trial ? "" : "mensal ";

    // 7. Obtener credenciales de Nextcloud
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');
    
    if (!$nextcloud_api_admin || !$nextcloud_api_pass) {
        error_log("Credenciales de Nextcloud no configuradas");
        return;
    }

    $base_url = "https://cloud." . basename(get_site_url());
    $auth = "$nextcloud_api_admin:$nextcloud_api_pass";
    $headers = ['OCS-APIRequest: true'];

    // 8. Lógica para nuevo usuario
    if (!$created_in_nextcloud) {
        $password = wp_generate_password(12, false);
        $nc_auth = "$username:$password";

        // Comandos para creación de usuario
        $commands = [
            // Crear grupo
            build_curl_command($base_url, $auth, 'POST', '/ocs/v1.php/cloud/groups', ['groupid' => $user_group]),
            
            // Crear usuario
            build_curl_command($base_url, $auth, 'POST', '/ocs/v1.php/cloud/users', [
                'userid' => $username,
                'password' => $password,
                'groups[]' => $user_group
            ]),
            
            // Configurar atributos
            build_curl_command($base_url, $auth, 'PUT', "/ocs/v1.php/cloud/users/$username", ['key' => 'displayname', 'value' => $displayname]),
            build_curl_command($base_url, $auth, 'PUT', "/ocs/v1.php/cloud/users/$username", ['key' => 'email', 'value' => $email]),
            build_curl_command($base_url, $auth, 'PUT', "/ocs/v1.php/cloud/users/$username", ['key' => 'quota', 'value' => $user_quota]),
            build_curl_command($base_url, $auth, 'PUT', "/ocs/v1.php/cloud/users/$username", ['key' => 'profile_enabled', 'value' => 'false']),
            
            // Configurar estado y localización
            build_curl_command($base_url, $nc_auth, 'PUT', '/ocs/v2.php/apps/user_status/api/v1/user_status/status', 
                $user_status, ['Content-Type: application/json']),
                
            build_curl_command($base_url, $auth, 'PUT', "/ocs/v1.php/cloud/users/$username", ['key' => 'locale', 'value' => $locale]),
            
            // Notificación al admin
            build_curl_command($base_url, $auth, 'POST', '/ocs/v2.php/apps/notifications/api/v2/admin_notifications/' . $nextcloud_api_admin, [
                'shortMessage' => 'Nova conta criada',
                'longMessage' => "Foi criada a conta {$level->name} do $username."
            ])
        ];

        // Ejecutar comandos
        execute_commands($commands);

        // Marcar como creado y enviar email
        update_user_meta($user_id, 'created_in_nextcloud', true);
        send_welcome_email($user, $password, $level, $fecha_pedido, $morder->total, $fecha_pago_proximo_mes, $date_message, $monthly_message);
    } 
    // 9. Lógica para actualización de usuario existente
    else {
        $new_user_group = $plan_type . $user_id;
        
        // Obtener grupo actual
        $response = shell_exec(build_curl_command($base_url, $auth, 'GET', "/ocs/v1.php/cloud/users/$username/groups"));
        $old_user_group = simplexml_load_string($response)->data->groups->element;

        // Comandos para actualización
        $commands = [
            // Actualizar quota
            build_curl_command($base_url, $auth, 'PUT', "/ocs/v1.php/cloud/users/$username", ['key' => 'quota', 'value' => $user_quota]),
            
            // Notificar usuario
            build_curl_command($base_url, $auth, 'POST', "/ocs/v2.php/apps/notifications/api/v2/admin_notifications/$username", [
                'shortMessage' => 'Atualização do plano',
                'longMessage' => "Seu plano foi atualizado para: {$level->name}. Esperamos que você aproveite ao máximo."
            ]),
            
            // Manejo de grupos
            build_curl_command($base_url, $auth, 'POST', '/ocs/v1.php/cloud/groups', ['groupid' => $new_user_group]),
            build_curl_command($base_url, $auth, 'POST', "/ocs/v1.php/cloud/users/$username/groups", ['groupid' => $new_user_group]),
            build_curl_command($base_url, $auth, 'DELETE', "/ocs/v1.php/cloud/users/$username/groups", ['groupid' => $old_user_group]),
            build_curl_command($base_url, $auth, 'DELETE', "/ocs/v1.php/cloud/groups/$old_user_group")
        ];

        // Ejecutar comandos
        execute_commands($commands);
    }
}

// Funciones auxiliares

/**
 * Construye comando cURL
 */
function build_curl_command($base_url, $auth, $method, $endpoint, $data = [], $extra_headers = []) {
    $headers = array_merge(['OCS-APIRequest: true'], $extra_headers);
    $header_str = implode(' -H \'', $headers) . '\'';
    
    $data_str = '';
    foreach ($data as $key => $value) {
        $data_str .= " -d '$key=$value'";
    }
    
    return "curl -H '$header_str' -u $auth -X $method '$base_url$endpoint'$data_str";
}

/**
 * Ejecuta comandos con manejo de errores
 */
function execute_commands($commands) {
    foreach ($commands as $command) {
        $output = shell_exec($command);
        sleep(1); // Espera entre comandos
        
        // Verificar errores (simplificado)
        if (strpos($output, '"statuscode":100') === false) {
            error_log("Error en comando Nextcloud: $command");
            error_log("Salida: $output");
        }
    }
}

/**
 * Envía email de bienvenida
 */
function send_welcome_email($user, $password, $level, $fecha_pedido, $total, $fecha_pago_proximo_mes, $date_message, $monthly_message) {
    $site_url = basename(get_site_url());
    $cloud_url = "https://cloud.$site_url";
    
    ob_start();
    ?>
    <h1>Cloud Brasdrive</h1>
    <p>Prezado(a) <b><?= $user->display_name ?></b> (<?= $user->user_login ?>),</p>
    <p>Parabéns! Sua conta Nextcloud foi criada satisfatoriamente!</p>
    
    <p>Dados da sua conta:<br/>
    Usuário: <?= $user->user_login ?><br/>
    Senha: <?= $password ?></p>
    
    <p>Acesso à sua conta Nextcloud: <a href="<?= $cloud_url ?>"><?= $cloud_url ?></a></p>
    
    <p>Baixe o aplicativo <strong>Nextcloud Files</strong>:<br/>
    <a href="https://play.google.com/store/apps/details?id=com.nextcloud.client">Google Play</a><br/>
    <a href="https://f-droid.org/pt_BR/packages/com.nextcloud.client/">F-Droid</a><br/>
    <a href="https://itunes.apple.com/br/app/nextcloud/id1125420102?mt=8">App Store</a><br/>
    Baixe o <strong>Nextcloud Desktop</strong> para Windows, macOS ou Linux: <a href="https://nextcloud.com/install">Baixar</a></p>
    
    <p>Seu plano: <b><?= $level->name ?></b></p>
    <p>Data do seu pedido: <?= $fecha_pedido ?><br/>
    Valor <?= $monthly_message ?>do seu plano: <b>R$ <?= number_format($total, 2, ',', '.') ?></b><br/>
    <?= $date_message . date('d/m/Y', strtotime($fecha_pago_proximo_mes)) ?></p>
    
    <p><b>Por segurança, recomendamos manter guardada a senha do Nextcloud em um local seguro e excluir esse e-mail.</b></p>
    <p>Atenciosamente,<br/>Equipe Brasdrive</p>
    <?php
    
    $message = ob_get_clean();
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    wp_mail($user->user_email, "Sua conta Nextcloud foi criada", $message, $headers);
}
