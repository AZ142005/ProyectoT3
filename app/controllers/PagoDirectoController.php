<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\RateLimiter;
use App\Models\CuentasBancariasModel;
use App\Models\EdificiosModel;
use App\Models\FacturasModel;
use App\Models\PagoModel;
use App\Models\UnidadesModel;
use App\Services\FileUploader;

/**
 * Controlador público del portal de pago directo (sin sesión).
 *
 * Permite a cualquier visitante seleccionar una unidad activa, consultar su
 * deuda y reportar un pago con comprobante. El pago se registra a nivel de
 * unidad (residente_id NULL) y sigue el flujo administrativo existente de
 * verificación, conciliación y aprobación.
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

        $unidadId = intval($_GET['unidad_id'] ?? 0);
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
     * Recibe el POST del portal público y registra el pago a nivel de unidad.
     * CSRF ya validado globalmente en public/index.php.
     */
    public function reportar(): void {
        // Rate limiting por IP: máximo 5 reportes por hora
        if (!RateLimiter::attempt('pago_directo', 5, 3600)) {
            $this->renderFormulario('Ha excedido el límite de reportes. Intente de nuevo más tarde.', $_POST);
            return;
        }

        $unidadId          = intval($_POST['unidad_id'] ?? 0);
        $cuentaBancariaId  = intval($_POST['cuenta_bancaria_id'] ?? 0);
        $bancoPagador      = trim($_POST['banco_pagador'] ?? '');
        if (strtoupper($bancoPagador) === 'OTRO' && !empty($_POST['banco_pagador_otro'])) {
            $bancoPagador = trim($_POST['banco_pagador_otro']);
        }
        $monto         = floatval($_POST['monto'] ?? 0);
        $metodoPago    = $_POST['metodo_pago'] ?? '';
        $referencia    = trim($_POST['referencia'] ?? '');
        $fechaPago     = $_POST['fecha_pago'] ?? '';
        $observaciones = trim($_POST['observaciones'] ?? '');

        $unidad           = ($unidadId > 0) ? (new UnidadesModel())->getById($unidadId) : false;
        $cuentaReceptora  = ($cuentaBancariaId > 0) ? (new CuentasBancariasModel())->getActivaById($cuentaBancariaId) : false;

        $error = '';

        if (!$unidad || intval($unidad['estado']) !== 1) {
            $error = 'Debe seleccionar una unidad activa válida.';
        } elseif (!$cuentaReceptora) {
            $error = 'Debe seleccionar una cuenta bancaria receptora autorizada y activa.';
        } elseif ($monto <= 0) {
            $error = 'Ingrese un monto válido mayor a cero.';
        } elseif ($monto > 999999.99) {
            $error = 'El monto no puede exceder Bs. 999.999,99.';
        } elseif (!in_array($metodoPago, ['transferencia', 'pago_movil'], true)) {
            $error = 'Seleccione un método de pago válido.';
        } elseif ($referencia === '') {
            $error = 'Ingrese el número de referencia del pago.';
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

        $observacionesCompletas = trim('Pago directo sin sesión (portal público). ' . $observaciones);

        $datos = [
            'monto'              => $monto,
            'fecha_pago'         => $fechaPago,
            'metodo_pago'        => $metodoPago,
            'referencia'         => $referencia,
            'observaciones'      => $observacionesCompletas,
            'banco_pagador'      => $bancoPagador,
            'banco_receptor'     => $cuentaReceptora['banco'],
            'cuenta_bancaria_id' => $cuentaBancariaId
        ];

        $resultado = (new PagoModel())->crearPago(null, $unidadId, $datos, $nombreArchivo);

        if ($resultado) {
            Flash::set('success', 'Su pago fue registrado exitosamente. La administración verificará la información.');
            $this->redirect('/pago-directo/exito');
            return;
        }

        $this->renderFormulario('Ya existe un pago registrado con la misma referencia, fecha y monto para esta unidad.', $_POST);
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
