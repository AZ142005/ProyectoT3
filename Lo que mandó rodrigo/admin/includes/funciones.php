<?php
// ============================================
// FUNCIONES DE UTILIDAD
// ============================================

function formatearMoneda($valor) {
    if (is_null($valor) || $valor === '') {
        return 'Bs. 0,00';
    }
    return 'Bs. ' . number_format(floatval($valor), 2, ',', '.');
}

function formatearFecha($fecha, $formato = 'd/m/Y') {
    if (empty($fecha)) return '-';
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return '-';
    return date($formato, $timestamp);
}

function nombreMes($numero) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[intval($numero)] ?? '';
}

function diasHastaVencimiento($fechaVencimiento) {
    if (empty($fechaVencimiento)) return null;
    
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    
    $vencimiento = new DateTime($fechaVencimiento);
    $vencimiento->setTime(0, 0, 0);
    
    $diff = $hoy->diff($vencimiento);
    return intval($diff->format('%r%a'));
}

function validarCedula($cedula) {
    $cedula = preg_replace('/^[VEve]/', '', $cedula);
    return preg_match('/^\d{7,8}$/', $cedula);
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarTelefono($telefono) {
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    return preg_match('/^(0?4(1|2|4|6)\d{7})$/', $telefono);
}
?>