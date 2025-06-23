<?php
// Calcular la próxima fecha de pago
function ajustar_proxima_fecha_pago($user_id) {
    // Obtener la fecha actual y la fecha original de registro del usuario
    $dia_fecha_registro = date( 'd', pmpro_getMemberStartdate($user_id) );
    $fecha_actual = date('Y-m');

    // Fecha para calcular la próxima fecha de pago
    $fecha_registro = $fecha_actual . '-' . $dia_fecha_registro;

    // Solicitar los feriados en Brasil para el año actual
    $url_feriados_brasil = 'https://date.nager.at/api/v3/PublicHolidays/' . date('Y') . '/BR';
    $feriados_brasil_json = file_get_contents($url_feriados_brasil);
    $feriados_brasil_array = json_decode($feriados_brasil_json, true);

    // Calcular la fecha de pago deseada para el siguiente mes
    $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_registro . '+1 month'));

    // Si la fecha de pago cae en Sábado o Domingo, ajustarla al Lunes siguiente
    $dia_semana_pago_siguiente_mes = date('N', strtotime($fecha_pago_siguiente_mes));
    if ($dia_semana_pago_siguiente_mes == 6) { // Sábado
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+2 days'));
    } elseif ($dia_semana_pago_siguiente_mes == 7) { // Domingo
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+1 day'));
    }

    // Si la fecha de pago cae en un feriado en Brasil, ajustarla al siguiente día hábil
    while (in_array($fecha_pago_siguiente_mes, array_column($feriados_brasil_array, 'date'))) {
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+1 day'));
        $dia_semana_pago_siguiente_mes = date('N', strtotime($fecha_pago_siguiente_mes));
        if ($dia_semana_pago_siguiente_mes == 6) { // Sábado
            $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+2 days'));
        } elseif ($dia_semana_pago_siguiente_mes == 7) { // Domingo
            $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+1 day'));
        }
    }

    // Verificar si la fecha de pago ajustada se desvía demasiado de la fecha original de registro y ajustarla si es necesario
    $dias_desviacion = abs(strtotime($fecha_registro) - strtotime($fecha_pago_siguiente_mes)) / (60 * 60 * 24);
    $max_dias_desviacion = 10; // Máxima cantidad de días permitida de desviación
    if ($dias_desviacion < $max_dias_desviacion) {
        $dias_ajuste = $dias_desviacion - $max_dias_desviacion;
        $ajuste = ($fecha_registro > $fecha_pago_siguiente_mes) ? '+ ' : '- ';
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($ajuste . $dias_ajuste . ' days', strtotime($fecha_pago_siguiente_mes)));
    }

    $fecha_pago_siguiente_mes = date('Y-m-d H:i:s', strtotime($fecha_pago_siguiente_mes));

    // Retornar la fecha de pago deseada para el siguiente mes
    return $fecha_pago_siguiente_mes;
}
