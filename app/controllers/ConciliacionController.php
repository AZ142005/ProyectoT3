<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\ConciliacionModel;
use App\Services\ConciliacionBancariaService;

class ConciliacionController extends Controller {

    /**
     * Muestra el panel interactivo del motor de conciliación bancaria.
     */
    public function index() {
        Auth::requireRole('admin');

        $conciliacionModel = new ConciliacionModel();
        $conciliacionService = new ConciliacionBancariaService();

        $lotes = $conciliacionModel->obtenerLotes();
        $loteSeleccionado = $_GET['lote'] ?? ($lotes[0]['lote_importacion'] ?? null);

        $extractosPendientes = $conciliacionModel->obtenerExtractosPendientes($loteSeleccionado);
        $resultadoCruce = $conciliacionService->ejecutarCruceInteligente($extractosPendientes);

        $this->render('admin/conciliacion/index', [
            'lotes'             => $lotes,
            'loteActual'        => $loteSeleccionado,
            'resultadoCruce'    => $resultadoCruce,
            'layout'            => 'admin',
            'title'             => 'Motor de Conciliación Bancaria Inteligente'
        ]);
    }

    /**
     * Procesa la importación de un archivo de extracto bancario CSV / TXT.
     */
    public function importarExtracto() {
        Auth::requireRole('admin');

        $banco = trim($_POST['banco'] ?? 'mercantil');

        if (empty($_FILES['archivo_extracto']) || $_FILES['archivo_extracto']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('danger', 'Debe seleccionar un archivo de extracto bancario válido.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        $file = $_FILES['archivo_extracto'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt'])) {
            Flash::set('danger', 'Formato no permitido. Solo se aceptan archivos .CSV o .TXT de extractos bancarios.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $conciliacionService = new ConciliacionBancariaService();
            $movimientos = $conciliacionService->parsearArchivo($file['tmp_name'], $banco);

            $lote = 'LOTE-' . date('Ymd-His');
            $conciliacionModel = new ConciliacionModel();
            $stats = $conciliacionModel->insertarExtracto($movimientos, $banco, $lote);

            $msg = "Extracto importado con éxito. Lote: {$lote}. Insertados: {$stats['insertados']} (Créditos), Débitos descartados: {$stats['debitos']}, Duplicados omitidos: {$stats['duplicados']}.";
            Flash::set('success', $msg);
            $this->redirect('/admin/conciliacion?lote=' . urlencode($lote));
        } catch (\Exception $e) {
            Flash::set('danger', 'Error al procesar extracto bancario: ' . $e->getMessage());
            $this->redirect('/admin/conciliacion');
        }
    }

    /**
     * Concilia y aprueba un pago individual de 1-clic.
     */
    public function conciliarPago() {
        Auth::requireRole('admin');

        $extractoId = intval($_POST['extracto_id'] ?? 0);
        $pagoId = intval($_POST['pago_id'] ?? 0);
        $adminId = Auth::id() ?? 1;

        if ($extractoId <= 0 || $pagoId <= 0) {
            Flash::set('danger', 'Parámetros de conciliación inválidos.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $conciliacionService = new ConciliacionBancariaService();
            $resultado = $conciliacionService->conciliarYaprobar($extractoId, $pagoId, $adminId);

            Flash::set('success', $resultado['mensaje']);
        } catch (\Exception $e) {
            Flash::set('danger', 'Error al conciliar pago: ' . $e->getMessage());
        }

        $this->redirect('/admin/conciliacion');
    }

    /**
     * Concilia un lote de pagos seleccionados de forma masiva (máx. 100).
     */
    public function conciliarLote() {
        Auth::requireRole('admin');

        $itemsJson = $_POST['items_json'] ?? '';
        $items = json_decode($itemsJson, true);
        $adminId = Auth::id() ?? 1;

        if (!is_array($items) || empty($items)) {
            Flash::set('danger', 'No se seleccionaron elementos válidos para conciliar.');
            $this->redirect('/admin/conciliacion');
            return;
        }

        try {
            $conciliacionService = new ConciliacionBancariaService();
            $stats = $conciliacionService->conciliarLote($items, $adminId);

            $msg = "Conciliación masiva completada: {$stats['procesados']} procesados, {$stats['omitidos']} omitidos.";
            if (!empty($stats['errores'])) {
                $msg .= " Errores: " . implode(', ', array_slice($stats['errores'], 0, 3));
            }

            Flash::set('success', $msg);
        } catch (\Exception $e) {
            Flash::set('danger', 'Error en conciliación masiva: ' . $e->getMessage());
        }

        $this->redirect('/admin/conciliacion');
    }
}
