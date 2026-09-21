<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\SolicitudesRegistroModel;

class SolicitudesRegistroController extends Controller {

    /**
     * Muestra la bandeja de gestión de solicitudes de registro de residentes.
     */
    public function index(): void {
        Auth::requireRole(UserRole::ADMIN);

        $pagina = max(1, intval($_GET['page'] ?? 1));
        $filtroEstado = isset($_GET['estado']) && in_array($_GET['estado'], ['pendiente', 'aprobada', 'rechazada'], true)
            ? $_GET['estado']
            : null;

        $model = new SolicitudesRegistroModel();
        $resultado = $model->obtenerListado($pagina, 15, $filtroEstado);
        $pendientesCount = $model->contarPendientes();

        $this->render('admin/solicitudes_registro/index', [
            'solicitudes'     => $resultado['datos'],
            'paginacion'      => [
                'total'        => $resultado['total'],
                'pagina'       => $resultado['pagina'],
                'porPagina'    => $resultado['porPagina'],
                'totalPaginas' => $resultado['totalPaginas'],
            ],
            'filtroEstado'    => $filtroEstado,
            'filtros'         => ['estado' => $filtroEstado ?? ''],
            'pendientesCount' => $pendientesCount,
            'showNav'         => false,
            'layout'          => 'admin',
            'title'           => 'Gestión de Solicitudes de Registro - Condominio'
        ]);
    }

    /**
     * Procesa la aprobación de una solicitud de registro.
     */
    public function aprobar(): void {
        Auth::requireRole(UserRole::ADMIN);

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::error("ID de solicitud no válido.");
            $this->redirect('/admin/solicitudes-registro');
            return;
        }

        $adminId = (int)(Auth::id() ?? 1);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $model = new SolicitudesRegistroModel();
        try {
            $model->aprobar($id, $adminId, $ip);
            Flash::success("Solicitud aprobada exitosamente. El usuario ha sido activado y asignado a su apartamento.");
        } catch (\Exception $e) {
            Flash::error($e->getMessage());
        }

        $this->redirect('/admin/solicitudes-registro');
    }

    /**
     * Procesa el rechazo de una solicitud de registro.
     */
    public function rechazar(): void {
        Auth::requireRole(UserRole::ADMIN);

        $id = intval($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo_rechazo'] ?? '');

        if ($id <= 0) {
            Flash::error("ID de solicitud no válido.");
            $this->redirect('/admin/solicitudes-registro');
            return;
        }

        $adminId = (int)(Auth::id() ?? 1);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $model = new SolicitudesRegistroModel();
        try {
            $model->rechazar($id, $adminId, $motivo ?: null, $ip);
            Flash::success("Solicitud rechazada exitosamente.");
        } catch (\Exception $e) {
            Flash::error($e->getMessage());
        }

        $this->redirect('/admin/solicitudes-registro');
    }
}
