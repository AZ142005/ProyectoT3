<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\FacturasModel;
use App\Models\ComprobantesModel;
use App\Models\ComunicadosModel;

class ResidenteController extends Controller {
    /**
     * Muestra el panel principal del residente (estado de cuenta).
     */
    public function dashboard() {
        Auth::requireRole('residente');
        $residente = $this->getAuthenticatedResidente();

        $facturasModel = new FacturasModel();

        $unidad_id = $residente['unidad_id'];

        // Obtener datos financieros
        $total_deuda = $facturasModel->getTotalDeudaByUnidad($unidad_id);
        $saldo_a_favor = $facturasModel->getSaldoFavorByUnidad($unidad_id);
        $saldo_a_favor_mostrar = abs($saldo_a_favor);

        // Obtener comunicados visibles para la unidad/edificio del residente (solo visualización)
        $edificioId = !empty($residente['edificio_id']) ? intval($residente['edificio_id']) : null;
        $unidadId = !empty($residente['unidad_id']) ? intval($residente['unidad_id']) : null;
        $resultadoComunicados = (new ComunicadosModel())->obtenerPorResidente($edificioId, $unidadId, 1, 10);

        // Renderizar la vista pasando los datos estructurados
        $this->render('residente/dashboard', [
            'residente'             => $residente,
            'total_deuda'           => $total_deuda,
            'saldo_a_favor_mostrar' => $saldo_a_favor_mostrar,
            'comunicados'           => $resultadoComunicados['datos'],
            'showNav'               => true,
            'title'                 => 'Estado de Deuda - Residente'
        ]);
    }

    /**
     * Permite enviar un comprobante de pago de una factura pendiente.
     */
    public function enviarPago() {
        Auth::requireRole('residente');

        $residente = $this->getAuthenticatedResidente();
        $residente_id = Auth::id();

        $facturasModel = new FacturasModel();
        $comprobantesModel = new ComprobantesModel();

        $unidad_id = $residente['unidad_id'];

        $selected_factura_id = $_GET['factura'] ?? 0;
        $mensaje = '';
        $error = '';

        // Rate limiting en POST: máximo 10 envíos por hora
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!\App\Core\RateLimiter::attempt('comprobante_' . Auth::id(), 10, 3600)) {
                http_response_code(429);
                $error = "Ha excedido el límite de envíos de comprobantes (máximo 10 por hora). Intente de nuevo más tarde.";
                if ($this->isAjax()) {
                    $this->json(['error' => $error, 'codigo' => 'RATE_LIMIT_EXCEEDED'], 429);
                    return;
                }
            }
        }

        // Buscar factura seleccionada por defecto si aplica
        $factura = null;
        if ($selected_factura_id > 0) {
            $factura = $facturasModel->getByIdAndUnidad($selected_factura_id, $unidad_id);
        }

        // Obtener facturas pendientes para el dropdown
        $facturas_pendientes = $facturasModel->getPendientesByUnidad($unidad_id);

        $cuentasModel = new \App\Models\CuentasBancariasModel();
        $cuentasBancarias = $cuentasModel->getActivas();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
            // CSRF ya validado por el middleware global
            $factura_id = $_POST['factura_id'] ?? 0;
            $cuenta_bancaria_id = intval($_POST['cuenta_bancaria_id'] ?? 0);
            $banco_pagador = trim($_POST['banco_pagador'] ?? '');
            if (strtoupper($banco_pagador) === 'OTRO' && !empty($_POST['banco_pagador_otro'])) {
                $banco_pagador = trim($_POST['banco_pagador_otro']);
            }
            $monto = floatval($_POST['monto'] ?? 0);
            $metodo_pago = $_POST['metodo_pago'] ?? '';
            $referencia = trim($_POST['referencia'] ?? '');
            $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
            $observaciones = trim($_POST['observaciones'] ?? '');

            $cuentaReceptora = ($cuenta_bancaria_id > 0) ? $cuentasModel->getActivaById($cuenta_bancaria_id) : null;
            $banco_receptor = $cuentaReceptora ? $cuentaReceptora['banco'] : null;

            if ($factura_id <= 0) {
                $error = "Seleccione una factura válida";
            } elseif (!$cuentaReceptora) {
                $error = "Debe seleccionar una cuenta bancaria receptora autorizada y activa.";
            } elseif ($monto <= 0) {
                $error = "Ingrese un monto válido mayor a cero";
            } elseif ($monto > 999999.99) {
                $error = "El monto no puede exceder Bs. 999.999,99";
            } elseif (empty($metodo_pago)) {
                $error = "Seleccione un método de pago";
            } else {
                // Verificar que la factura pertenece a la unidad del residente
                $factura_valida = $facturasModel->getByIdAndUnidadGeneral($factura_id, $unidad_id);

                if (!$factura_valida) {
                    $error = "La factura seleccionada no es válida para su unidad.";
                } elseif (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
                    $error = "El comprobante de pago (archivo) es obligatorio y debe ser válido.";
                } else {
                    $uploader = new \App\Services\FileUploader();
                    $uploadedName = $uploader->upload($_FILES['comprobante']);

                    if (!$uploadedName) {
                        $error = "Formato o tamaño de archivo no permitido. Solo se aceptan JPG, PNG y PDF (Máx. 5MB).";
                    } else {
                        $archivo = $uploadedName;
                        $archivoHash = $uploader->getLastFileHash();
                    }

                    if (empty($error)) {
                        // Pre-chequeo informativo de duplicados
                        $dupInfo = $comprobantesModel->verificarDuplicado($factura_id, $referencia, $fecha_pago, $monto, $archivoHash ?? null);
                        if ($dupInfo !== null) {
                            http_response_code(409);
                            if ($dupInfo['criterio'] === 'archivo_hash') {
                                $error = "Este archivo de comprobante ya fue subido previamente para su unidad.";
                            } else {
                                $error = "Ya existe un comprobante o pago registrado con esta referencia o datos de pago para su unidad.";
                            }
                            if ($this->isAjax()) {
                                $this->json(['error' => $error, 'codigo' => 'CONFLICTO_DUPLICADO', 'criterio' => $dupInfo['criterio']], 409);
                                return;
                            }
                        } else {
                            // Guardar comprobante
                            $obsCompleta = trim("Cuenta Destino: {$cuentaReceptora['banco']} ({$cuentaReceptora['numero_cuenta']}) | " . $observaciones);
                            $result = $comprobantesModel->create([
                                'residente_id'       => $residente_id,
                                'factura_id'         => $factura_id,
                                'monto'              => $monto,
                                'metodo_pago'        => $metodo_pago,
                                'banco_pagador'      => $banco_pagador,
                                'banco_receptor'     => $banco_receptor,
                                'cuenta_bancaria_id' => $cuenta_bancaria_id,
                                'referencia'         => $referencia,
                                'fecha_pago'         => $fecha_pago,
                                'archivo'            => $archivo,
                                'archivo_hash'       => $archivoHash ?? null,
                                'observaciones'      => $obsCompleta
                            ]);

                            if ($result) {
                                http_response_code(201);
                                $mensaje = "Comprobante enviado exitosamente. Su pago será verificado por la administración.";
                                if ($this->isAjax()) {
                                    $this->json(['mensaje' => $mensaje, 'id' => $result, 'codigo' => 'CREADO'], 201);
                                    return;
                                }
                                // Recargar las facturas pendientes para el dropdown tras guardar
                                $facturas_pendientes = $facturasModel->getPendientesByUnidad($unidad_id);
                                $selected_factura_id = 0;
                            } else {
                                http_response_code(409);
                                $error = "No se pudo registrar el comprobante. Verifique que no sea un pago duplicado o intente más tarde.";
                                if ($this->isAjax()) {
                                    $this->json(['error' => $error, 'codigo' => 'CONFLICTO_DUPLICADO'], 409);
                                    return;
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->render('residente/enviar_pago', [
            'residente'           => $residente,
            'factura_id'          => $selected_factura_id,
            'facturas_pendientes' => $facturas_pendientes,
            'cuentasBancarias'    => $cuentasBancarias,
            'mensaje'             => $mensaje,
            'error'               => $error,
            'showNav'             => true,
            'title'               => 'Enviar Pago - Condominio Digital'
        ]);
    }

    /**
     * Historial unificado: "Mis Pagos" muestra tanto los pagos (portal público /
     * administración) como los comprobantes del formulario residente de la unidad.
     */
    public function historial() {
        Auth::requireRole('residente');
        $this->redirect('/pagos');
    }
}
