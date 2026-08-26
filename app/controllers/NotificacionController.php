<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\NotificacionesModel;

class NotificacionController extends Controller {

    /**
     * Muestra la bandeja de notificaciones personal del residente.
     */
    public function index() {
        Auth::requireRole('residente');

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? 0;
        $pagina = max(1, intval($_GET['page'] ?? 1));

        $notificacionesModel = new NotificacionesModel();
        $resultado = $notificacionesModel->obtenerHistorial($personaId, $pagina, 15);
        $noLeidas = $notificacionesModel->contarNoLeidas($personaId);

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('residente/notificaciones', [
            'notificaciones' => $resultado['datos'],
            'noLeidas'       => $noLeidas,
            'paginacion'     => $paginacion,
            'title'          => 'Bandeja de Notificaciones'
        ]);
    }

    /**
     * Endpoint AJAX GET para consultar la cantidad de notificaciones no leídas.
     */
    public function cantidadNoLeidas() {
        Auth::requireRole('residente');

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? 0;

        $notificacionesModel = new NotificacionesModel();
        $total = $notificacionesModel->contarNoLeidas($personaId);

        $this->json([
            'success'   => true,
            'no_leidas' => $total
        ]);
    }

    /**
     * Endpoint POST/AJAX para marcar una notificación como leída.
     */
    public function marcarLeida() {
        Auth::requireRole('residente');

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? 0;
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
        }

        $notificacionesModel = new NotificacionesModel();
        $actualizado = $notificacionesModel->marcarComoLeida($id, $personaId);
        $nuevoTotal = $notificacionesModel->contarNoLeidas($personaId);

        $this->json([
            'success'   => $actualizado,
            'no_leidas' => $nuevoTotal
        ]);
    }
}
