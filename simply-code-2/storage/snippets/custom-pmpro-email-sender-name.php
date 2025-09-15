<?php
// Remitente personalizado

if (!defined('ABSPATH')) exit;

function custom_pmpro_email_sender_name( $sender_name ) {
    return 'Brasdrive';
}
add_filter( 'pmpro_email_sender_name', 'custom_pmpro_email_sender_name' );
