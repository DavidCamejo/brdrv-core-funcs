<?php
/**
 * Gestiona la creación/actualización de usuarios en Nextcloud usando WP HTTP API
 */
function crear_usuario_nextcloud_seguro($user_id, $morder) {
    // 1. Verificación inicial
    if (!getenv('NEXTCLOUD_API_ADMIN') || !getenv('NEXTCLOUD_API_PASS')) {
        error_log('Error: Credenciales de Nextcloud no configuradas');
        return false;
    }

    $user = get_userdata($user_id);
    if (!$user) {
        error_log("Error: Usuario WordPress no encontrado (ID: $user_id)");
        return false;
    }

    // 2. Configuración base
    $base_url = 'https://cloud.' . sanitize_text_field(basename(get_site_url()));
    $auth = [
        'username' => getenv('NEXTCLOUD_API_ADMIN'),
        'password' => getenv('NEXTCLOUD_API_PASS')
    ];

    // 3. Determinar datos del plan
    $level = pmpro_getMembershipLevelForUser($user_id);
    $quota_parts = explode(" ", $level->name);
    $user_group = strtolower($quota_parts[1]) . $user_id;
    $is_trial = ($level->id === 5);

    // 4. Lógica principal
    if (!get_user_meta($user_id, 'created_in_nextcloud', true)) {
        return crear_nuevo_usuario_nextcloud($user, $level, $base_url, $auth, $user_group, $is_trial, $morder);
    } else {
        return actualizar_usuario_nextcloud($user, $level, $base_url, $auth, $user_group);
    }
}

/**
 * Crea un nuevo usuario en Nextcloud usando WP HTTP API
 */
function crear_nuevo_usuario_nextcloud($user, $level, $base_url, $auth, $user_group, $is_trial, $morder) {
    $password = wp_generate_password(12, false);
    $username = sanitize_user($user->user_login);
    
    // 1. Crear grupo
    $response = wp_remote_post("$base_url/ocs/v1.php/cloud/groups", [
        'headers' => [
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => ['groupid' => $user_group],
        'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        error_log('Error al crear grupo en Nextcloud');
        return false;
    }

    // 2. Crear usuario
    $response = wp_remote_post("$base_url/ocs/v1.php/cloud/users", [
        'headers' => [
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => [
            'userid' => $username,
            'password' => $password,
            'groups[]' => $user_group,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        error_log('Error al crear usuario en Nextcloud');
        return false;
    }

    // 3. Configurar atributos del usuario
    $attributes = [
        ['key' => 'displayname', 'value' => $user->display_name ?: $username],
        ['key' => 'email', 'value' => sanitize_email($user->user_email)],
        ['key' => 'quota', 'value' => calcular_cuota($level->name)],
        ['key' => 'profile_enabled', 'value' => 'false'],
        ['key' => 'locale', 'value' => 'pt_BR'],
    ];

    foreach ($attributes as $attr) {
        $response = wp_remote_request("$base_url/ocs/v1.php/cloud/users/$username", [
            'method' => 'PUT',
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $attr,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log("Error al configurar atributo {$attr['key']}");
        }
    }

    // 4. Configurar estado del usuario
    $response = wp_remote_request("$base_url/ocs/v2.php/apps/user_status/api/v1/user_status/status", [
        'method' => 'PUT',
        'headers' => [
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode("$username:$password"),
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode(['statusType' => 'invisible']),
        'timeout' => 15,
    ]);

    // 5. Marcar como creado y enviar email
    update_user_meta($user->ID, 'created_in_nextcloud', true);
    enviar_email_bienvenida($user, $password, $level, $morder, $is_trial);

    return true;
}

/**
 * Actualiza un usuario existente en Nextcloud
 */
function actualizar_usuario_nextcloud($user, $level, $base_url, $auth, $new_user_group) {
    $username = sanitize_user($user->user_login);
    
    // 1. Obtener grupo actual
    $response = wp_remote_get("$base_url/ocs/v1.php/cloud/users/$username/groups", [
        'headers' => [
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('Error al obtener grupos del usuario');
        return false;
    }

    $xml = simplexml_load_string(wp_remote_retrieve_body($response));
    $old_user_group = (string)$xml->data->groups->element[0];

    // 2. Actualizar cuota
    $response = wp_remote_request("$base_url/ocs/v1.php/cloud/users/$username", [
        'method' => 'PUT',
        'headers' => [
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => [
            'key' => 'quota',
            'value' => calcular_cuota($level->name),
        ],
        'timeout' => 15,
    ]);

    // 3. Manejar grupos
    if ($old_user_group !== $new_user_group) {
        // Crear nuevo grupo
        wp_remote_post("$base_url/ocs/v1.php/cloud/groups", [
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => ['groupid' => $new_user_group],
            'timeout' => 15,
        ]);

        // Añadir a nuevo grupo
        wp_remote_post("$base_url/ocs/v1.php/cloud/users/$username/groups", [
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => ['groupid' => $new_user_group],
            'timeout' => 15,
        ]);

        // Eliminar de grupo anterior
        wp_remote_request("$base_url/ocs/v1.php/cloud/users/$username/groups", [
            'method' => 'DELETE',
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => ['groupid' => $old_user_group],
            'timeout' => 15,
        ]);

        // Eliminar grupo anterior (opcional)
        wp_remote_request("$base_url/ocs/v1.php/cloud/groups/$old_user_group", [
            'method' => 'DELETE',
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Authorization' => 'Basic ' . base64_encode("{$auth['username']}:{$auth['password']}"),
            ],
            'timeout' => 15,
        ]);
    }

    return true;
}

/**
 * Calcula la cuota basada en el nombre del plan
 */
function calcular_cuota($plan_name) {
    $quota_parts = explode(" ", $plan_name);
    $total = ($quota_parts[2] >= 1000) ? $quota_parts[2]/1000 : $quota_parts[2];
    $measure = ($quota_parts[2] >= 1000) ? "TB" : "GB";
    return $total . $measure;
}

/**
 * Envía el email de bienvenida (similar a tu versión original)
 */
function enviar_email_bienvenida($user, $password, $level, $morder, $is_trial) {
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
