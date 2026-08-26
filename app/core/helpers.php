<?php

if (!function_exists('e')) {
    /**
     * Escapa caracteres HTML especiales para prevenir XSS.
     *
     * @param string|null $value
     * @return string
     */
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Obtiene el token CSRF de la sesión actual.
     *
     * @return string
     */
    function csrf_token() {
        return $_SESSION['csrf_token'] ?? '';
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Retorna el input oculto HTML con el token CSRF para formularios POST.
     *
     * @return string
     */
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('formatearMoneda')) {
    /**
     * Formatea un número a moneda local (Bolívares - Bs.).
     *
     * @param float|string|null $valor
     * @return string
     */
    function formatearMoneda($valor) {
        if (is_null($valor) || $valor === '') {
            return 'Bs. 0,00';
        }
        return 'Bs. ' . number_format(floatval($valor), 2, ',', '.');
    }
}

if (!function_exists('nombreMes')) {
    /**
     * Retorna el nombre en español de un número de mes (1-12).
     *
     * @param int|string $numero
     * @return string
     */
    function nombreMes($numero) {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        return $meses[intval($numero)] ?? '';
    }
}

if (!function_exists('diasHastaVencimiento')) {
    /**
     * Calcula los días restantes hasta la fecha de vencimiento.
     * Si ya venció, retorna un valor negativo.
     *
     * @param string $fechaVencimiento
     * @return int|null
     */
    function diasHastaVencimiento($fechaVencimiento) {
        if (empty($fechaVencimiento)) return null;
        
        $hoy = new DateTime();
        $hoy->setTime(0, 0, 0);
        
        $vencimiento = new DateTime($fechaVencimiento);
        $vencimiento->setTime(0, 0, 0);
        
        $diff = $hoy->diff($vencimiento);
        return intval($diff->format('%r%a'));
    }
}

if (!function_exists('validarCedula')) {
    /**
     * Valida el formato de una cédula venezolana (V/E + número).
     *
     * @param string $cedula
     * @return bool
     */
    function validarCedula($cedula) {
        $cedula = preg_replace('/^[VEve]/', '', $cedula);
        return preg_match('/^\d{7,8}$/', $cedula);
    }
}

if (!function_exists('validarEmail')) {
    /**
     * Valida una dirección de correo electrónico.
     *
     * @param string $email
     * @return bool
     */
    function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validarTelefono')) {
    /**
     * Valida el formato de un teléfono venezolano.
     *
     * @param string $telefono
     * @return bool
     */
    function validarTelefono($telefono) {
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        return preg_match('/^(0?4(1|2|4|6)\d{8})$/', $telefono);
    }
}

if (!function_exists('badgeEstado')) {
    function badgeEstado($estado) {
        $map = [
            'pendiente'  => ['bg-yellow-50 text-yellow-700 border-yellow-200', 'schedule', 'Pendiente'],
            'aprobado'   => ['bg-green-50 text-green-700 border-green-200', 'check_circle', 'Aprobado'],
            'rechazado'  => ['bg-red-50 text-red-700 border-red-200', 'cancel', 'Rechazado'],
            'pagado'     => ['bg-blue-50 text-blue-700 border-blue-200', 'payments', 'Pagado'],
            'vencido'    => ['bg-red-50 text-red-700 border-red-200', 'error', 'Vencido'],
            'activo'     => ['bg-green-50 text-green-700 border-green-200', 'check_circle', 'Activo'],
            'inactivo'   => ['bg-slate-100 text-slate-600 border-slate-200', 'block', 'Inactivo'],
        ];
        $key = strtolower(trim($estado ?? ''));
        [$cls, $icon, $label] = $map[$key] ?? ['bg-slate-100 text-slate-600 border-slate-200', 'help', $key ?: 'N/A'];
        return '<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border ' . e($cls) . '"><span class="material-symbols-outlined text-[14px]">' . e($icon) . '</span>' . e($label) . '</span>';
    }
}
