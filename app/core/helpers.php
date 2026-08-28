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

if (!function_exists('normalizarCedula')) {
    /**
     * Normaliza una cédula eliminando guiones, puntos, espacios y convirtiendo a mayúsculas.
     *
     * @param string|null $cedula
     * @return string
     */
    function normalizarCedula($cedula) {
        $cedula = strtoupper(trim($cedula ?? ''));
        return preg_replace('/[\s\.\-]/', '', $cedula);
    }
}

if (!function_exists('validarCedula')) {
    /**
     * Valida el formato de una cédula venezolana (V/E opcional + 5 a 8 dígitos numéricos).
     * Rechaza iniciales inválidas (ej: Z12345678) o longitudes menores a 5 o mayores a 8 dígitos.
     *
     * @param string $cedula
     * @return bool
     */
    function validarCedula($cedula) {
        $cedula = normalizarCedula($cedula);
        if (empty($cedula)) {
            return false;
        }
        return (bool)preg_match('/^(?:[VE])?\d{5,8}$/', $cedula);
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
            'pendiente'   => ['bg-warning text-dark', 'schedule', 'Pendiente'],
            'en revisión' => ['bg-info text-white', 'info', 'En Revisión'],
            'aprobado'    => ['bg-success text-white', 'check_circle', 'Aprobado'],
            'rechazado'   => ['bg-danger text-white', 'cancel', 'Rechazado'],
            'pagada'      => ['bg-success text-white', 'payments', 'Pagada'],
            'pagado'      => ['bg-success text-white', 'payments', 'Pagado'],
            'vencido'     => ['bg-danger text-white', 'error', 'Vencido'],
            'activo'      => ['bg-success text-white', 'check_circle', 'Activo'],
            'inactivo'    => ['bg-secondary text-white', 'block', 'Inactivo'],
            'anulada'     => ['bg-secondary text-white', 'block', 'Anulada'],
        ];
        $raw = trim($estado ?? '');
        $key = strtolower($raw);
        [$cls, $icon, $label] = $map[$key] ?? ['bg-secondary text-white', 'help', $raw ?: 'N/A'];
        
        return '<span class="badge rounded-pill ' . e($cls) . ' d-inline-flex align-items-center gap-1 px-3 py-1 text-xs font-semibold">'
             . '<span class="material-symbols-outlined" style="font-size: 14px;">' . e($icon) . '</span>'
             . e($label)
             . '</span>';
    }
}

if (!function_exists('sanitize_exception_message')) {
    /**
     * Limpia mensajes de excepciones PDO para logging seguro.
     * Elimina paths del servidor, datos de conexión y fragmentos SQL sensibles.
     *
     * @param \Throwable $e
     * @return string Mensaje sanitizado para log
     */
    function sanitize_exception_message(\Throwable $e): string {
        $msg = $e->getMessage();
        // Eliminar paths del sistema de archivos
        $msg = preg_replace('#[A-Za-z]:\\\\[^ ]*#', '[path]', $msg);
        $msg = preg_replace('#/[^ ]*\.php#', '[script]', $msg);
        // Eliminar credenciales de BD que PDO pueda filtrar
        $msg = preg_replace('#(password|passwd|pwd)\s*[=:]\s*\S+#i', 'password=[redacted]', $msg);
        // Truncar si es muy largo
        if (strlen($msg) > 200) {
            $msg = substr($msg, 0, 200) . '...';
        }
        return $msg;
    }
}
