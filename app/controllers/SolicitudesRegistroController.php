<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\SolicitudesRegistroModel;

class SolicitudesRegistroController extends Controller {

    /**
     * Redirige la antigua bandeja autónoma a la pestaña de solicitudes
     * dentro de la sección unificada de usuarios.
     */
    public function index(): void {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        header('Location: /admin/usuarios?tab=solicitudes');
        exit;
    }

    /**
     * Procesa la aprobación de una solicitud de registro.
     */
    public function aprobar(): void {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::error("ID de solicitud no válido.");
            $this->redirect('/admin/usuarios?tab=solicitudes');
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

        $this->redirect('/admin/usuarios?tab=solicitudes');
    }

    /**
     * Procesa el rechazo de una solicitud de registro.
     */
    public function rechazar(): void {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        $id = intval($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo_rechazo'] ?? '');

        if ($id <= 0) {
            Flash::error("ID de solicitud no válido.");
            $this->redirect('/admin/usuarios?tab=solicitudes');
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

        $this->redirect('/admin/usuarios?tab=solicitudes');
    }
}
