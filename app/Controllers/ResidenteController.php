<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\FacturasModel;
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
     * Historial unificado: "Mis Pagos" muestra tanto los pagos (portal público /
     * administración) como los comprobantes del formulario residente de la unidad.
     */
    public function historial() {
        Auth::requireRole('residente');
        $this->redirect('/pagos');
    }
}
