<?php
// Set default currency
function pmpro_brl_currency_format( $pmpro_currencies ) {
    $pmpro_currencies['BRL'] = array(
        'name' => __( 'Real Brasileiro', 'paid-memberships-pro' ),
        'decimals' => '2',
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'symbol' => 'R$ ',
        'position' => 'left',
    );
    return $pmpro_currencies;
}
add_filter( 'pmpro_currencies', 'pmpro_brl_currency_format' );
