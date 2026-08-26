<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\FacturasModel;
use App\Models\ComprobantesModel;

class ResidenteController extends Controller {
    /**
     * Muestra el panel principal del residente (estado de cuenta).
     */
    public function dashboard() {
        Auth::requireRole('residente');
        $residente = $this->getAuthenticatedResidente();
        $residente_id = Auth::id();

        $facturasModel = new FacturasModel();
        $comprobantesModel = new ComprobantesModel();

        $unidad_id = $residente['unidad_id'];

        // Obtener datos financieros
        $facturas_pendientes = $facturasModel->getPendientesByUnidad($unidad_id);
        $total_deuda = $facturasModel->getTotalDeudaByUnidad($unidad_id);
        $saldo_a_favor = $facturasModel->getSaldoFavorByUnidad($unidad_id);
        $saldo_a_favor_mostrar = abs($saldo_a_favor);

        // Obtener comprobantes de pago recientes (límite 10)
        $comprobantes = $comprobantesModel->getRecientesByResidente($residente_id, 10);

        // Renderizar la vista pasando los datos estructurados
        $this->render('residente/dashboard', [
            'residente' => $residente,
            'facturas_pendientes' => $facturas_pendientes,
            'total_deuda' => $total_deuda,
            'saldo_a_favor_mostrar' => $saldo_a_favor_mostrar,
            'comprobantes' => $comprobantes,
            'showNav' => true,
            'title' => 'Estado de Cuenta - Residente'
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

        // Buscar factura seleccionada por defecto si aplica
        $factura = null;
        if ($selected_factura_id > 0) {
            $factura = $facturasModel->getByIdAndUnidad($selected_factura_id, $unidad_id);
        }

        // Obtener facturas pendientes para el dropdown
        $facturas_pendientes = $facturasModel->getPendientesByUnidad($unidad_id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF ya validado por el middleware global
            $factura_id = $_POST['factura_id'] ?? 0;
            $monto = floatval($_POST['monto'] ?? 0);
            $metodo_pago = $_POST['metodo_pago'] ?? '';
            $referencia = trim($_POST['referencia'] ?? '');
            $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($factura_id <= 0) {
                $error = "Seleccione una factura válida";
            } elseif ($monto <= 0) {
                $error = "Ingrese un monto válido mayor a cero";
            } elseif (empty($metodo_pago)) {
                $error = "Seleccione un método de pago";
            } else {
                // Verificar que la factura pertenece a la unidad del residente
                $factura_valida = $facturasModel->getByIdAndUnidadGeneral($factura_id, $unidad_id);

                if (!$factura_valida) {
                    $error = "La factura seleccionada no es válida para su unidad.";
                } else {
                    $archivo = '';
                    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                        $uploader = new \App\Services\FileUploader();
                        $uploadedName = $uploader->upload($_FILES['comprobante']);

                        if (!$uploadedName) {
                            $error = "Formato o tamaño de archivo no permitido. Solo se aceptan JPG, PNG y PDF.";
                        } else {
                            $archivo = $uploadedName;
                        }
                    }

                    if (empty($error)) {
                        // Guardar comprobante
                        $result = $comprobantesModel->create([
                            'residente_id'  => $residente_id,
                            'factura_id'    => $factura_id,
                            'monto'         => $monto,
                            'metodo_pago'   => $metodo_pago,
                            'referencia'    => $referencia,
                            'fecha_pago'    => $fecha_pago,
                            'archivo'       => $archivo,
                            'observaciones' => $observaciones
                        ]);

                        if ($result) {
                            $mensaje = "Comprobante enviado exitosamente. Su pago será verificado por la administración.";
                            // Recargar las facturas pendientes para el dropdown tras guardar
                            $facturas_pendientes = $facturasModel->getPendientesByUnidad($unidad_id);
                            $selected_factura_id = 0;
                        } else {
                            $error = "Error al registrar el comprobante en la base de datos.";
                        }
                    }
                }
            }
        }

        $this->render('residente/enviar_pago', [
            'residente' => $residente,
            'factura_id' => $selected_factura_id,
            'facturas_pendientes' => $facturas_pendientes,
            'mensaje' => $mensaje,
            'error' => $error,
            'showNav' => true,
            'title' => 'Enviar Pago - Condominio Digital'
        ]);
    }

    /**
     * Muestra todo el historial de comprobantes del residente.
     */
    public function historial() {
        Auth::requireRole('residente');
        $residente = $this->getAuthenticatedResidente();
        $residente_id = Auth::id();

        $comprobantesModel = new ComprobantesModel();
        $comprobantes = $comprobantesModel->getAllByResidente($residente_id);

        $this->render('residente/historial', [
            'residente' => $residente,
            'comprobantes' => $comprobantes,
            'showNav' => true,
            'title' => 'Historial de Pagos - Condominio Digital'
        ]);
    }
}
