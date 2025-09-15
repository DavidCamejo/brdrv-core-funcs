<?php
// Redirigir copias de emails enviados al admin a un email secundario

if (!defined('ABSPATH')) exit;

function bcc_admin_emails($args) {
    $bcc_email = 'jdavidcamejo@gmail.com';
    if (!isset($args['headers'])) {
        $args['headers'] = '';
    }
    $args['headers'] .= "Bcc: $bcc_email\r\n";
    return $args;
}
add_filter('wp_mail', 'bcc_admin_emails');
