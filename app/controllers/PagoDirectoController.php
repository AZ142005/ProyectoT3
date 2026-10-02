<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\EstadoPago;
use App\Core\Flash;
use App\Core\RateLimiter;
use App\Models\CuentasBancariasModel;
use App\Models\EdificiosModel;
use App\Models\FacturasModel;
use App\Models\PagoModel;
use App\Models\PersonasModel;
use App\Models\UnidadesModel;
use App\Services\ComprobanteParserService;
use App\Services\ConciliacionBancariaService;
use App\Services\FileUploader;

/**
 * Controlador público del portal de pago directo (sin sesión).
 *
 * Permite a cualquier visitante seleccionar una unidad activa, consultar su
 * deuda y reportar un pago con comprobante. Si la unidad tiene personas
 * activas, el pago se atribuye al residente principal; si no, queda a nivel
 * de unidad (residente_id NULL). Se registra en estado EN REVISIÓN y sigue el
 * flujo administrativo existente de verificación, conciliación y aprobación.
 */
class PagoDirectoController extends Controller {

    /**
     * Muestra la página pública de pago directo.
     */
    public function index(): void {
        $this->renderFormulario();
    }

    /**
     * Endpoint público JSON con la deuda de una unidad activa.
     * No expone datos personales: solo periodos, vencimientos y saldos.
     */
    public function deuda(): void {
        // Rate limiting por IP: máximo 60 consultas por hora
        if (!RateLimiter::attempt('pago_directo_deuda', 60, 3600)) {
            $this->json([
                'success' => false,
                'message' => 'Ha excedido el límite de consultas. Intente de nuevo más tarde.'
            ], 429);
        }

        // Sanea el parámetro: un array en el query string no debe romper la respuesta JSON.
        $unidadId = is_scalar($_GET['unidad_id'] ?? null) ? intval($_GET['unidad_id']) : 0;
        if ($unidadId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Debe indicar una unidad válida.'
            ], 400);
        }

        $unidad = (new UnidadesModel())->getById($unidadId);
        if (!$unidad || intval($unidad['estado']) !== 1) {
            $this->json([
                'success' => false,
                'message' => 'La unidad indicada no existe o no está activa.'
            ], 404);
        }

        $facturasModel = new FacturasModel();
        $resumen = $facturasModel->getResumenFinancieroUnidad($unidadId);
        $pendientes = $facturasModel->getPendientesByUnidad($unidadId);

        $facturas = [];
        foreach ($pendientes as $f) {
            $facturas[] = [
                'numero_factura'    => $f['numero_factura'],
                'mes'               => intval($f['mes']),
                'anio'              => intval($f['anio']),
                'fecha_vencimiento' => $f['fecha_vencimiento'],
                'saldo'             => round(floatval($f['saldo']), 2)
            ];
        }

        $this->json([
            'success'     => true,
            'unidad'      => [
                'id'     => intval($unidad['id']),
                'numero' => $unidad['numero']
            ],
            'edificio'    => $unidad['edificio_nombre'] ?? '',
            'total_deuda' => $resumen['total_deuda'],
            'saldo_favor' => $resumen['saldo_favor'],
            'facturas'    => $facturas
        ]);
    }

    /**
     * Endpoint público de extracción y análisis de datos del comprobante (POST).
     *
     * Soporta texto pre-extraído vía OCR en cliente (Tesseract.js) o extracción
     * nativa desde PDF en el backend. No expone datos personales: solo sugiere
     * campos del formulario a partir del comprobante analizado.
     * CSRF ya validado globalmente en public/index.php.
     */
    public function extraer(): void {
        // Rate limiting por IP: máximo 20 análisis por hora (cooldown de la
        // extracción automática de comprobantes).
        if (!RateLimiter::attempt('pago_directo_extraer', 20, 3600)) {
            $this->json([
                'success'    => false,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error'      => 'Demasiadas solicitudes de análisis. Intente de nuevo más tarde.'
            ], 429);
        }

        $parser = new ComprobanteParserService();
        $resultado = [
            'banco'      => null,
            'referencia' => null,
            'monto'      => null,
            'fecha'      => null,
            'detectado'  => false
        ];

        // 1. Caso A: Texto extraído vía OCR en cliente (Tesseract.js para imágenes)
        $textoExtraido = trim($_POST['texto_extraido'] ?? '');
        if (!empty($textoExtraido)) {
            if (mb_strlen($textoExtraido) > 50000) {
                $textoExtraido = mb_substr($textoExtraido, 0, 50000);
            }
            $resultado = $parser->analizarTexto($textoExtraido);
        }
        // 2. Caso B: Archivo PDF subido directamente para extracción nativa en backend
        elseif (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['comprobante']['tmp_name'];
            $nombreOriginal = $_FILES['comprobante']['name'] ?? '';
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $resultado = $parser->procesarArchivo($tmpPath, 'pdf');
            } else {
                $this->json([
                    'success'    => false,
                    'detectado'  => false,
                    'csrf_token' => $_SESSION['csrf_token'] ?? '',
                    'error'      => 'Para imágenes, la extracción se procesa mediante el motor de reconocimiento en el navegador.'
                ], 400);
            }
        } else {
            $this->json([
                'success'    => false,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error'      => 'No se proporcionó texto de OCR ni archivo válido para analizar.'
            ], 400);
        }

        $nombresBancos = [
            'mercantil'  => 'Banco Mercantil',
            'banesco'    => 'Banesco',
            'venezuela'  => 'Banco de Venezuela',
            'provincial' => 'BBVA Provincial',
            'bancamiga'  => 'Bancamiga',
            'bnc'        => 'Banco Nacional de Crédito',
            'bancaribe'  => 'Bancaribe',
            'tesoro'     => 'Banco del Tesoro',
            'exterior'   => 'Banco Exterior',
            'plaza'      => 'Banco Plaza',
            'activo'     => 'Banco Activo',
            'sofitasa'   => 'Banco Sofitasa',
            '100banco'   => '100% Banco',
            'bfc'        => 'Banco Fondo Común'
        ];

        $bancoPagador = $resultado['banco'] ? ($nombresBancos[$resultado['banco']] ?? ucfirst($resultado['banco'])) : '';

        // Resolver dinámicamente la cuenta bancaria autorizada receptora
        $cuentasActivas = (new CuentasBancariasModel())->getActivas();
        $cuentaSugeridaId = null;
        $bancoReceptor = '';

        // 1. Por prefijo de 4 dígitos de cuenta destino (ej. '0102', '0105', etc.)
        if (!empty($resultado['cuenta_destino_prefijo'])) {
            foreach ($cuentasActivas as $ca) {
                if (str_starts_with($ca['numero_cuenta'], $resultado['cuenta_destino_prefijo'])) {
                    $cuentaSugeridaId = (int)$ca['id'];
                    $bancoReceptor = $ca['banco'];
                    break;
                }
            }
        }

        // 2. Por coincidencia de nombre de banco receptor detectado en comprobante
        if (!$cuentaSugeridaId && !empty($resultado['banco_receptor'])) {
            $conciliacionService = new ConciliacionBancariaService();
            $normBcoRec = $conciliacionService->normalizarNombreBanco($resultado['banco_receptor']);
            foreach ($cuentasActivas as $ca) {
                if ($conciliacionService->normalizarNombreBanco($ca['banco']) === $normBcoRec) {
                    $cuentaSugeridaId = (int)$ca['id'];
                    $bancoReceptor = $ca['banco'];
                    break;
                }
            }
        }

        // 3. Fallback: si existe una sola cuenta autorizada activa, asociarla por defecto
        if (!$cuentaSugeridaId && count($cuentasActivas) === 1) {
            $cuentaSugeridaId = (int)$cuentasActivas[0]['id'];
            $bancoReceptor = $cuentasActivas[0]['banco'];
        }

        $this->json([
            'success'            => true,
            'csrf_token'         => $_SESSION['csrf_token'] ?? '',
            'detectado'          => (bool)$resultado['detectado'],
            'banco_pagador'      => $bancoPagador,
            'banco_receptor'     => $bancoReceptor,
            'cuenta_bancaria_id' => $cuentaSugeridaId,
            'metodo_pago'        => $resultado['metodo_pago'] ?? '',
            'referencia'         => $resultado['referencia'] ?? '',
            'monto'              => $resultado['monto'] !== null ? number_format($resultado['monto'], 2, '.', '') : '',
            'fecha_pago'         => $resultado['fecha'] ?? '',
            'mensaje'            => $resultado['detectado']
                ? 'Datos del comprobante detectados exitosamente.'
                : 'No se pudieron detectar todos los datos con certeza. Por favor verifique los campos.'
        ]);
    }

    /**
     * Recibe el POST del portal público y registra el pago a nivel de unidad.
     * CSRF ya validado globalmente en public/index.php.
     */
    public function reportar(): void {
        // Rate limiting por IP: máximo 5 reportes por hora
        if (!RateLimiter::attempt('pago_directo', 5, 3600)) {
            $this->renderFormulario('Ha excedido el límite de reportes. Intente de nuevo más tarde.', $_POST);
            return;
        }

        $unidadId         = $this->postInt('unidad_id');
        $cuentaBancariaId = $this->postInt('cuenta_bancaria_id');
        $bancoPagador     = $this->postString('banco_pagador');
        $bancoPagadorOtro = $this->postString('banco_pagador_otro');
        $monto            = round($this->postFloat('monto'), 2);
        $metodoPago       = $this->postString('metodo_pago');
        $referencia       = $this->postString('referencia');
        $fechaPago        = $this->postString('fecha_pago');
        $observaciones    = $this->postString('observaciones');

        $esBancoOtro = (strtoupper($bancoPagador) === 'OTRO');

        $unidad           = ($unidadId > 0) ? (new UnidadesModel())->getById($unidadId) : false;
        $cuentaReceptora  = ($cuentaBancariaId > 0) ? (new CuentasBancariasModel())->getActivaById($cuentaBancariaId) : false;

        $error = '';

        if (!$unidad || intval($unidad['estado']) !== 1) {
            $error = 'Debe seleccionar una unidad activa válida.';
        } elseif (!$cuentaReceptora) {
            $error = 'Debe seleccionar una cuenta bancaria receptora autorizada y activa.';
        } elseif ($monto < 0.01) {
            $error = 'Ingrese un monto válido (mínimo Bs. 0,01).';
        } elseif ($monto > 999999.99) {
            $error = 'El monto no puede exceder Bs. 999.999,99.';
        } elseif (!in_array($metodoPago, ['transferencia', 'pago_movil'], true)) {
            $error = 'Seleccione un método de pago válido.';
        } elseif ($referencia === '') {
            $error = 'Ingrese el número de referencia del pago.';
        } elseif ($esBancoOtro && $bancoPagadorOtro === '') {
            $error = 'Especifique el nombre del banco emisor.';
        } elseif (!\DateTime::createFromFormat('Y-m-d', $fechaPago) || date('Y-m-d', strtotime($fechaPago)) !== $fechaPago) {
            $error = 'El formato de fecha no es válido. Use AAAA-MM-DD.';
        } elseif (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            $error = 'El comprobante de pago (archivo) es obligatorio y debe ser válido.';
        }

        $nombreArchivo = null;
        if ($error === '') {
            $nombreArchivo = (new FileUploader())->upload($_FILES['comprobante']);
            if (!$nombreArchivo) {
                $error = 'Formato o tamaño de archivo no permitido. Solo se aceptan JPG, PNG y PDF (Máx. 5MB).';
            }
        }

        if ($error !== '') {
            $this->renderFormulario($error, $_POST);
            return;
        }

        // Ruta A: atribuir el pago al residente principal de la unidad (si existe).
        // Sin personas activas, el pago queda a nivel de unidad (residente_id NULL).
        $residentePrincipal = (new PersonasModel())->getPrincipalByUnidadId($unidadId);
        $residenteId = $residentePrincipal ? intval($residentePrincipal['id']) : null;

        // Si el usuario eligió "OTRO", se registra el nombre de banco especificado.
        if ($esBancoOtro) {
            $bancoPagador = $bancoPagadorOtro;
        }

        $observacionesCompletas = trim('Pago directo sin sesión (portal público). ' . $observaciones);

        $datos = [
            'monto'              => $monto,
            'fecha_pago'         => $fechaPago,
            'metodo_pago'        => $metodoPago,
            'referencia'         => $referencia,
            'observaciones'      => $observacionesCompletas,
            'banco_pagador'      => $bancoPagador,
            'banco_receptor'     => $cuentaReceptora['banco'],
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'estado'             => EstadoPago::EN_REVISION,
        ];

        $resultado = (new PagoModel())->crearPago($residenteId, $unidadId, $datos, $nombreArchivo);

        if ($resultado) {
            Flash::set('success', 'Su pago fue registrado exitosamente. La administración verificará la información.');
            $this->redirect('/pago-directo/exito');
            return;
        }

        $this->renderFormulario('Ya existe un pago registrado con el mismo monto y fecha para esta unidad. Si ya lo reportó, espere la verificación de la administración.', $_POST);
    }

    /**
     * Página de confirmación tras registrar el pago.
     */
    public function exito(): void {
        $this->render('pago_directo/exito', [
            'showNav' => false,
            'title'   => 'Pago Registrado - Condominio Digital'
        ]);
    }

    /**
     * Retorna un valor string de $_POST ya saneado (trim).
     * Devuelve '' si el valor no existe o no es escalar.
     */
    private function postString(string $key): string {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    /**
     * Retorna un valor entero de $_POST.
     * Devuelve 0 si el valor no existe o no es escalar.
     */
    private function postInt(string $key): int {
        $v = $_POST[$key] ?? 0;
        return is_scalar($v) ? intval($v) : 0;
    }

    /**
     * Retorna un valor decimal de $_POST.
     * Devuelve 0.0 si el valor no existe o no es escalar.
     */
    private function postFloat(string $key): float {
        $v = $_POST[$key] ?? 0;
        return is_scalar($v) ? floatval($v) : 0.0;
    }

    /**
     * Renderiza la vista pública del formulario con su data y valores previos.
     *
     * @param string $error Mensaje de error a mostrar (vacío si no hay)
     * @param array $old Valores enviados por POST para repoblar el formulario
     */
    private function renderFormulario(string $error = '', array $old = []): void {
        $this->render('pago_directo/index', [
            'edificios'        => (new EdificiosModel())->getActivos(),
            'cuentasBancarias' => (new CuentasBancariasModel())->getActivas(),
            'unidades'         => (new UnidadesModel())->getActivas(),
            'error'            => $error,
            'old'              => $old,
            'showNav'          => false,
            'title'            => 'Pagar sin Iniciar Sesión - Condominio Digital'
        ]);
    }
}
