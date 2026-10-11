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
     * Formatea un número a moneda local (Bolívares - Bs.) en formato nacional venezolano (Ej: Bs. 15.583,96).
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

if (!function_exists('formatearMonto')) {
    /**
     * Formatea un valor numérico al formato nacional venezolano (Ej: 15.583,96) sin prefijo de moneda.
     *
     * @param float|string|null $valor
     * @param int $decimales
     * @return string
     */
    function formatearMonto($valor, int $decimales = 2) {
        if (is_null($valor) || $valor === '') {
            return $decimales > 0 ? ('0,' . str_repeat('0', $decimales)) : '0';
        }
        return number_format(floatval($valor), $decimales, ',', '.');
    }
}

if (!function_exists('formatearCantidad')) {
    /**
     * Formatea cantidades enteras con separador de miles nacional venezolano (punto).
     *
     * @param int|float|string|null $valor
     * @return string
     */
    function formatearCantidad($valor) {
        if (is_null($valor) || $valor === '') {
            return '0';
        }
        return number_format(intval($valor), 0, ',', '.');
    }
}

if (!function_exists('montoEnLetras')) {
    /**
     * Convierte un monto numérico a su expresión en letras para documentos oficiales
     * (Ej: 7548.05 → "SIETE MIL QUINIENTOS CUARENTA Y OCHO BOLÍVARES CON 05/100").
     *
     * Reglas:
     * - Redondea a 2 decimales y expresa los centavos como "XX/100".
     * - Usa apócopes (UN / VEINTIÚN) delante de BOLÍVAR(ES), MIL y MILLÓN.
     * - Agrega "DE" antes de BOLÍVARES solo cuando el entero es múltiplo exacto de 1.000.000.
     * - Soporta montos desde 0 hasta 999.999.999,99; valores negativos se tratan como cero.
     *
     * @param float|int|string|null $valor
     * @return string
     */
    function montoEnLetras($valor): string {
        if (!is_numeric($valor)) {
            $valor = 0;
        }

        // Redondeo a 2 decimales trabajando en centavos para evitar errores de flotante.
        $totalCentavos = (int) round(floatval($valor) * 100);
        if ($totalCentavos < 0) {
            $totalCentavos = 0;
        }

        $entero = intdiv($totalCentavos, 100);
        $centavos = $totalCentavos % 100;

        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $especiales = [
            10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE',
            15 => 'QUINCE', 16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
            20 => 'VEINTE', 21 => 'VEINTIÚN', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
        ];
        $decenas = [
            30 => 'TREINTA', 40 => 'CUARENTA', 50 => 'CINCUENTA',
            60 => 'SESENTA', 70 => 'SETENTA', 80 => 'OCHENTA', 90 => 'NOVENTA',
        ];
        $centenas = [
            100 => 'CIEN', 200 => 'DOSCIENTOS', 300 => 'TRESCIENTOS', 400 => 'CUATROCIENTOS', 500 => 'QUINIENTOS',
            600 => 'SEISCIENTOS', 700 => 'SETECIENTOS', 800 => 'OCHOCIENTOS', 900 => 'NOVECIENTOS',
        ];

        // Convierte un grupo de 0 a 999 a letras.
        $grupoALetras = function (int $grupo) use (&$grupoALetras, $unidades, $especiales, $decenas, $centenas): string {
            if ($grupo === 0) {
                return '';
            }
            if ($grupo < 10) {
                return $unidades[$grupo];
            }
            if ($grupo < 30) {
                return $especiales[$grupo];
            }
            if ($grupo < 100) {
                $resto = $grupo % 10;
                return $decenas[intdiv($grupo, 10) * 10] . ($resto > 0 ? ' Y ' . $unidades[$resto] : '');
            }
            if ($grupo === 100) {
                return 'CIEN';
            }
            $resto = $grupo % 100;
            // 101-199 se expresan como "CIENTO ..."; 200+ usan su centena (DOSCIENTOS, ...).
            $baseCentena = ($grupo < 200) ? 'CIENTO' : $centenas[intdiv($grupo, 100) * 100];
            return $baseCentena . ($resto > 0 ? ' ' . $grupoALetras($resto) : '');
        };

        // Convierte el entero completo a letras (MIL / MILLÓN / MILLONES).
        $enteroALetras = function (int $numero) use (&$enteroALetras, $grupoALetras): string {
            if ($numero < 1000) {
                return $grupoALetras($numero);
            }
            if ($numero < 1000000) {
                $miles = intdiv($numero, 1000);
                $resto = $numero % 1000;
                $parteMiles = ($miles === 1) ? 'MIL' : $grupoALetras($miles) . ' MIL';
                return $resto > 0 ? $parteMiles . ' ' . $grupoALetras($resto) : $parteMiles;
            }
            $millones = intdiv($numero, 1000000);
            $resto = $numero % 1000000;
            $parteMillones = $enteroALetras($millones) . ($millones === 1 ? ' MILLÓN' : ' MILLONES');
            return $resto > 0 ? $parteMillones . ' ' . $enteroALetras($resto) : $parteMillones;
        };

        $numeroLetras = ($entero === 0) ? 'CERO' : $enteroALetras($entero);
        $esMultiploDeMillon = ($entero > 0 && $entero % 1000000 === 0);
        $sustantivo = ($entero === 1) ? 'BOLÍVAR' : 'BOLÍVARES';
        $conector = $esMultiploDeMillon ? ' DE' : '';

        return $numeroLetras . $conector . ' ' . $sustantivo . ' CON ' . sprintf('%02d/100', $centavos);
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
     * Valida el formato de una cédula o documento de identidad venezolano (V/E/J/G opcional + 5 a 8 dígitos numéricos).
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
        return (bool)preg_match('/^(?:[VEJG])?\d{5,8}$/', $cedula);
    }
}

if (!function_exists('validarEmail')) {
    /**
     * Valida el formato de correo electrónico mediante expresiones regulares (Regex)
     * y verificación compatible con estándares RFC (filter_var).
     * Garantiza formato estricto: usuario@dominio.tld
     *
     * @param string|null $email
     * @return bool
     */
    function validarEmail($email) {
        if ($email === null || trim($email) === '') {
            return false;
        }
        $email = trim($email);
        $patron = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (!preg_match($patron, $email)) {
            return false;
        }
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
            'rechazado'   => ['bg-danger text-white', 'cancel', 'No Aprobado'],
            'pagada'      => ['bg-success text-white', 'payments', 'Pagada'],
            'pagado'      => ['bg-success text-white', 'payments', 'Pagado'],
            'vencido'     => ['bg-danger text-white', 'error', 'Vencido'],
            'activo'      => ['bg-success text-white', 'check_circle', 'Activo'],
            'inactivo'    => ['bg-secondary text-white', 'block', 'Inactivo'],
            'anulada'     => ['bg-secondary text-white', 'block', 'Anulada'],
        ];
        $raw = trim($estado ?? '');
        $key = mb_strtolower($raw, 'UTF-8');
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

if (!function_exists('validarPassword')) {
    /**
     * Valida los requisitos mínimos de complejidad y longitud de una contraseña:
     * - Al menos 8 caracteres de longitud.
     * - Al menos una letra (mayúscula o minúscula).
     * - Al menos un número.
     *
     * @param string|null $password
     * @return bool
     */
    function validarPassword(?string $password): bool {
        if ($password === null) {
            return false;
        }
        if (strlen($password) < 8) {
            return false;
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        return true;
    }
}
