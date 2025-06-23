<?php
// Crear usuario en plan Nextcloud Solo
function crear_usuario_nextcloud( $user_id, $morder ) {
    // Verificar si el usuario fue creado previamente en Nextcloud
    $created_in_nextcloud = get_user_meta($user_id, 'created_in_nextcloud', true);

    // Obtener información del usuario
    $user = get_userdata( $user_id );
    $email = $user->user_email;
    $username = $user->user_login;
    $displayname = $user->display_name;
    $level = $user->membership_level = pmpro_getMembershipLevelForUser($user_id);
    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
    $dt->setTimestamp($morder->timestamp);

    // Obtener la fecha del próximo pago
    $fecha_pedido = $dt->format('d/m/Y H:i:s');
    $fecha_pago_proximo_mes = ajustar_proxima_fecha_pago( $user_id );

    // Get the User Plan level
    $plan_level = $level->id;

    // Variables quota, status, curl, shell
    $quota = explode(" ", $level->name);
    $user_group = strtolower( $quota[1] ) . $user_id;
    $total_quota = ($quota[2] >= 1000) ? $quota[2]/1000 : $quota[2];
    $measure_quota = ($quota[2] >= 1000) ? "TB" : "GB";
    $user_quota = $total_quota . $measure_quota;
    $user_status = '{"statusType": "invisible"}';
    $locale = 'pt_BR';
    $curl_command = $output_shell_exec = [];

    if ($plan_level !== 5) {
        $date_message = "Data do próximo pagamento: ";
        $monthly_message = "mensal ";
    } else {
        $date_message = "Avaliação gratuita até: ";
        $monthly_message = "";
    }

    // Leer credenciales del administrador
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if ( ! $nextcloud_api_admin || ! $nextcloud_api_pass ) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return; // o lanzar una excepción.
    }

    $nextcloud_autentication = $nextcloud_api_admin . ':' . $nextcloud_api_pass;
    $nextcloud_header = "OCS-APIRequest: true";
    $notifications_link = "/ocs/v2.php/apps/notifications/api/v2/admin_notifications/";

    $notification_url = "https://cloud." . basename( get_site_url() ) . $notifications_link;

    // Si no se ha creado la cuenta en Nextcloud, proceder con su creación
    if (!$created_in_nextcloud) {
        // Generar password para la nueva cuenta Nextcloud
        $password = wp_generate_password( 12, false );
        $ncnewuser_autentication = $username . ":" . $password;

        $to_admin_subject = 'Nova conta criada';
        $to_admin_smessage = 'Foi criada a conta ' . $level->name . ' do ' . $username . '.';

        // Crear usuario en Nextcloud
        $curl_command[0] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'groupid=$user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups";
        $curl_command[1] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'userid=$username&password=$password&groups[]=$user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users?format=json";
        $curl_command[2] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=displayname&value=$displayname' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[3] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=email&value=$email' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[4] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=quota&value=$user_quota' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[5] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=profile_enabled&value=false' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[6] = "curl -H '" . $nextcloud_header .  "' -u " . $ncnewuser_autentication . " -X PUT -H 'Content-Type: application/json' --data-raw '" . $user_status . "' https://cloud.brasdrive.com.br/ocs/v2.php/apps/user_status/api/v1/user_status/status";
        $curl_command[7] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=locale&value=$locale' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[8] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST " . $notification_url . $nextcloud_api_admin . " -d shortMessage='$to_admin_subject' -d longMessage='$to_admin_smessage'";

        $count = count($curl_command);

        for ($i = 0; $i < $count; $i++) {
            $output_shell_exec[$i] = shell_exec($curl_command[$i]);
            sleep(1);
        }

        $new_account = true;

    } else {
        $short_message = 'Atualização do plano';
        $long_message = 'Seu plano foi atualizado para: ' . $level->name . '. Esperamos que você aproveite ao máximo.';
        $new_user_group = strtolower( $quota[1] ) . $user_id;
        $nextcloud_header_json = "Content-type: application/json";

        $response = shell_exec("curl -H '" . $nextcloud_header_json . "' -H '" . $nextcloud_header . "' -u " . $nextcloud_autentication . " -X GET https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username."/groups");

        $simplexml = simplexml_load_string($response);
        $json = json_encode($simplexml);
        $obj = json_decode($json);
        $old_user_group = $obj->data->groups->element;

        $curl_command[0] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=quota&value=$user_quota' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[1] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST " . $notification_url . $username." -d shortMessage='$short_message' -d longMessage='$long_message'";
        $curl_command[2] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'groupid=$new_user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups";
        $curl_command[3] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'groupid=$new_user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username."/groups";
        $curl_command[4] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X DELETE -d 'groupid=$old_user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username."/groups";
        $curl_command[5] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X DELETE https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups/".$old_user_group;

        $count = count($curl_command);

        for ($i = 0; $i < $count; $i++) {
            $output_shell_exec[$i] = shell_exec($curl_command[$i]);
        }

        $new_account = false;
    }

    // Verificar si hubo errores en las solicitudes de API
    $error_occurred = false;
    $error_message = '';

    $response = json_decode($output_shell_exec[1]);
    if ($response->ocs->meta->statuscode !== 100 && !$new_account) {
        // Registrar cualquier error que surja
        $error_occurred = true;
        $error_message = "Error en la solicitud de API: " . $response->ocs->meta->message . "\n";
    }

    if ($error_occurred) { // Si se produjo un error, enviar un correo electrónico al administrador

        $to = get_option('admin_email');

        if (!$created_in_nextcloud) {
            $subject = 'Error en la creación de usuario en Nextcloud';
            $message = 'Se ha producido un error al crear el usuario en Nextcloud. Los detalles del error son los siguientes:' . "\n\n" . $error_message;
        } else {
            $subject = 'Error al actualizar plan de usuario en Nextcloud';
            $message = 'Se ha producido un error al actualizar plan de usuario en Nextcloud. Los detalles del error son los siguientes:' . "\n\n" . $error_message;
        }

        wp_mail($to, $subject, $message);

    }

    if ($new_account) { // Agregar una clave meta al usuario y enviarle un e-mail con información de su nueva cuenta Nextcloud

        update_user_meta($user_id, 'created_in_nextcloud', true);

        // Cloud URL
        $cloud_url = 'https://cloud.' . basename( get_site_url() );
        $client_cloud_url = 'https://cloud.' . basename( get_site_url() ) . '/remote.php/dav/files/' . $username;

        // App links
        $google_play = "https://play.google.com/store/apps/details?id=com.nextcloud.client";
        $f_droid = "https://f-droid.org/pt_BR/packages/com.nextcloud.client/";
        $app_store = "https://itunes.apple.com/br/app/nextcloud/id1125420102?mt=8";

        // mailto
        $brdrv_email = "cloud@" . basename( get_site_url() );
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
        wp_mail( $email, $subject, $message, $headers );
    }
}
