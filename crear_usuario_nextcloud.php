<?php
function crear_usuario_nextcloud($user_id, $morder) {
    // Verificar si el usuario fue creado previamente en Nextcloud
    $created_in_nextcloud = get_user_meta($user_id, 'created_in_nextcloud', true);

    // Obtener información del usuario
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'Usuario no encontrado.');
    }

    $email = $user->user_email;
    $username = $user->user_login;
    $displayname = $user->display_name;
    $level = pmpro_getMembershipLevelForUser($user_id);

    if (!$level) {
        return new WP_Error('level_not_found', 'Nivel de membresía no encontrado.');
    }

    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
    $dt->setTimestamp($morder->timestamp);

    // Obtener la fecha del próximo pago
    $fecha_pedido = $dt->format('d/m/Y H:i:s');
    $fecha_pago_proximo_mes = ajustar_proxima_fecha_pago($user_id);

    // Get the User Plan level
    $plan_level = $level->id;

    // Variables quota, status, curl, shell
    $quota = explode(" ", $level->name);
    $user_group = strtolower($quota[1]) . $user_id;
    $total_quota = ($quota[2] >= 1000) ? $quota[2] / 1000 : $quota[2];
    $measure_quota = ($quota[2] >= 1000) ? "TB" : "GB";
    $user_quota = $total_quota . $measure_quota;
    $user_status = '{"statusType": "invisible"}';
    $locale = 'pt_BR';

    if ($plan_level !== 5) {
        $date_message = "Data do próximo pagamento: ";
        $monthly_message = "mensal ";
    } else {
        $date_message = "Avaliação gratuita até: ";
        $monthly_message = "";
    }

    // Leer credenciales del administrador
    $nextcloud_api_admin = base64_decode(get_option('nextcloud_api_admin'));
    $nextcloud_api_pass = base64_decode(get_option('nextcloud_api_pass'));
    $nextcloud_autentication = $nextcloud_api_admin . ':' . $nextcloud_api_pass;
    $nextcloud_header = "OCS-APIRequest: true";
    $notifications_link = "/ocs/v2.php/apps/notifications/api/v2/admin_notifications/";
    $notification_url = "https://cloud." . basename(get_site_url()) . $notifications_link;

    // Si no se ha creado la cuenta en Nextcloud, proceder con su creación
    if (!$created_in_nextcloud) {
        // Generar password para la nueva cuenta Nextcloud
        $password = wp_generate_password(12, false);
        $ncnewuser_autentication = $username . ":" . $password;

        $to_admin_subject = 'Nova conta criada';
        $to_admin_smessage = 'Foi criada a conta ' . $level->name . ' do ' . $username . '.';

        // Crear usuario en Nextcloud
        $requests = [
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups', 'data' => ['groupid' => $user_group]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users?format=json', 'data' => ['userid' => $username, 'password' => $password, 'groups[]' => $user_group]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username, 'data' => ['key' => 'displayname', 'value' => $displayname]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username, 'data' => ['key' => 'email', 'value' => $email]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username, 'data' => ['key' => 'quota', 'value' => $user_quota]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username, 'data' => ['key' => 'profile_enabled', 'value' => 'false']],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v2.php/apps/user_status/api/v1/user_status/status', 'data' => $user_status, 'headers' => ['Content-Type: application/json'], 'auth' => $ncnewuser_autentication],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username, 'data' => ['key' => 'locale', 'value' => $locale]],
            ['url' => $notification_url . $nextcloud_api_admin, 'data' => ['shortMessage' => $to_admin_subject, 'longMessage' => $to_admin_smessage]],
        ];

        $responses = [];
        foreach ($requests as $request) {
            $args = [
                'headers' => [$nextcloud_header],
                'body' => $request['data'],
                'auth' => $request['auth'] ?? $nextcloud_autentication,
            ];
            $response = wp_remote_post($request['url'], $args);
            if (is_wp_error($response)) {
                return $response;
            }
            $responses[] = $response;
            sleep(1);
        }

        $new_account = true;
    } else {
        $short_message = 'Atualização do plano';
        $long_message = 'Seu plano foi atualizado para: ' . $level->name . '. Esperamos que você aproveite ao máximo.';
        $new_user_group = strtolower($quota[1]) . $user_id;

        $response = wp_remote_get('https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups', [
            'headers' => [$nextcloud_header],
            'auth' => $nextcloud_autentication,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $simplexml = simplexml_load_string($response['body']);
        $json = json_encode($simplexml);
        $obj = json_decode($json);
        $old_user_group = $obj->data->groups->element;

        $requests = [
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username, 'data' => ['key' => 'quota', 'value' => $user_quota]],
            ['url' => $notification_url . $username, 'data' => ['shortMessage' => $short_message, 'longMessage' => $long_message]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups', 'data' => ['groupid' => $new_user_group]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups', 'data' => ['groupid' => $new_user_group]],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups', 'data' => ['groupid' => $old_user_group], 'method' => 'DELETE'],
            ['url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups/' . $old_user_group, 'method' => 'DELETE'],
        ];

        $responses = [];
        foreach ($requests as $request) {
            $args = [
                'headers' => [$nextcloud_header],
                'body' => $request['data'],
                'method' => $request['method'] ?? 'POST',
                'auth' => $nextcloud_autentication,
            ];
            $response = wp_remote_request($request['url'], $args);
            if (is_wp_error($response)) {
                return $response;
            }
            $responses[] = $response;
        }

        $new_account = false;
    }

    // Verificar si hubo errores en las solicitudes de API
    $error_occurred = false;
    $error_message = '';

    foreach ($responses as $response) {
        $body = json_decode($response['body']);
        if ($body->ocs->meta->statuscode !== 100) {
            $error_occurred = true;
            $error_message = "Error en la solicitud de API: " . $body->ocs->meta->message . "\n";
            break;
        }
    }

    if ($error_occurred) {
        $to = get_option('admin_email');
        $subject = $new_account ? 'Error en la creación de usuario en Nextcloud' : 'Error al actualizar plan de usuario en Nextcloud';
        $message = 'Se ha producido un error. Los detalles del error son los siguientes:' . "\n\n" . $error_message;

        wp_mail($to, $subject, $message);
        return new WP_Error('api_error', $error_message);
    }

    if ($new_account) {
        update_user_meta($user_id, 'created_in_nextcloud', true);

        // Cloud URL
        $cloud_url = 'https://cloud.' . basename(get_site_url());
        $client_cloud_url = $cloud_url . '/remote.php/dav/files/' . $username;

        // App links
        $google_play = "https://play.google.com/store/apps/details?id=com.nextcloud.client";
        $f_droid = "https://f-droid.org/pt_BR/packages/com.nextcloud.client/";
        $app_store = "https://itunes.apple.com/br/app/nextcloud/id1125420102?mt=8";

        // mailto
        $brdrv_email = "cloud@" . basename(get_site_url());
        $mailto = "mailto:" . $brdrv_email;

        //Título de email
        $subject = "Sua conta Nextcloud foi criada";
        //mensaje
        $message = "<h1>Cloud Brasdrive</h1>";
        $message .= "<p>Prezado(a) <b>" . $displayname . "</b> (" . $username . "),</p>";
        $message .= "<p>Parabéns! Sua conta Nextcloud foi criada satisfatoriamente!</p>";
        $message .= "<p>Dados da sua conta:<br/>";
        $message .= "Usuário: " . $username . "<br/>";
        $message .= "Senha: " . $password . "</p>";
        $message .= "<p>Acesso à sua conta Nextcloud: <a href='" . $cloud_url . "'>" . $cloud_url . "</a></p>";
        $message .= "<p>Baixe o aplicativo <strong>Nextcloud Files</strong>:<br/>";
        $message .= "<a href='" . $google_play . "' >Google Play</a><br/>";
        $message .= "<a href='" . $f_droid . "' >F-Droid</a><br/>";
        $message .= "<a href='" . $app_store . "' >App Store</a><br/>";
        $message .= "Baixe o <strong>Nextcloud Desktop</strong> para Windows, macOS ou Linux: <a href='https://nextcloud.com/install'>Baixar</a><br/>";
        $message .= "Conecte o aplicativo utilizando o link <a href='" . $cloud_url . "'>" . $cloud_url . "</a>, com seu usuário e sua senha.</p>";
        $message .= "<p>Para conectar clientes WebDAV utilice: <a href='" . $client_cloud_url . "'>" . $client_cloud_url . "</a>, com seu usuário e sua senha.</p>";

        $message .= "<p>Seu plano: <b>" . $level->name . "</b></p>";
        $message .= "<p>Data do seu pedido: " . $fecha_pedido . "<br/>";
        $message .= "Valor " . $monthly_message . "do seu plano: <b>R$ " . number_format($morder->total, 2, ',', '.') . "</b><br/>";
        $message .= $date_message . date('d/m/Y', strtotime($fecha_pago_proximo_mes)) . "</p>";
        $message .= "<p><b>Por segurança, recomendamos manter guardada a senha do Nextcloud em um local seguro e excluir esse e-mail.</b> Você também pode alterar sua senha nas Configurações pessoais da sua conta Nextcloud.</p>";
        $message .= "<p>Se você tiver alguma dúvida, entre em contato conosco no e-mail: <a href='" . $mailto . "'>" . $brdrv_email . "</a>.</p>";
        $message .= "Atenciosamente,<br/>Equipe Brasdrive";

        $headers = array('Content-Type: text/html; charset=UTF-8');

        //enviar correo
        wp_mail($email, $subject, $message, $headers);
    }

    return true;
}
?>
