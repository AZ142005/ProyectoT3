<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\PersonasModel;
use App\Models\SolicitudesModel;

class PerfilController extends Controller {

    /**
     * Muestra el perfil personal del usuario/residente y el historial de solicitudes.
     */
    public function verPerfil() {
        Auth::requireLogin();

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? null;

        $persona = null;
        $solicitudes = [];

        if ($personaId) {
            $personasModel = new PersonasModel();
            $persona = $personasModel->findById($personaId);

            $solicitudesModel = new SolicitudesModel();
            $solicitudes = $solicitudesModel->obtenerPorPersona($personaId);
        }

        $this->render('perfil/index', [
            'user'        => $user,
            'persona'     => $persona,
            'solicitudes' => $solicitudes,
            'title'       => 'Mi Perfil'
        ]);
    }

    /**
     * Procesa la solicitud enviada por el residente para actualizar sus datos.
     */
    public function solicitarCambio() {
        Auth::requireRole('residente');

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? 0;

        if ($personaId <= 0) {
            Flash::set('danger', 'Su usuario no posee un registro de persona asociado.');
            $this->redirect('/perfil');
            return;
        }

        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        try {
            $solicitudesModel = new SolicitudesModel();
            $solicitudesModel->crearSolicitud($personaId, [
                'telefono'  => $telefono,
                'email'     => $email,
                'direccion' => $direccion
            ]);

            Flash::set('success', 'Su solicitud de actualización de datos ha sido enviada a la administración para revisión.');
        } catch (\Exception $e) {
            error_log("[PERFIL] Error enviar solicitud cambio datos: " . $e->getMessage());
            Flash::set('danger', 'Error al enviar la solicitud de cambio de datos.');
        }

        $this->redirect('/perfil');
    }

    /**
     * Muestra el panel de administración para gestionar solicitudes de datos de residentes.
     */
    public function listarSolicitudes() {
        Auth::requireRole('admin');

        $pagina = max(1, intval($_GET['page'] ?? 1));

        $solicitudesModel = new SolicitudesModel();
        $resultado = $solicitudesModel->obtenerTodasAdmin($pagina, 15);

        $paginacion = [
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'porPagina'    => $resultado['porPagina'],
            'totalPaginas' => $resultado['totalPaginas'],
        ];

        $this->render('admin/solicitudes_datos/index', [
            'solicitudes' => $resultado['datos'],
            'paginacion'  => $paginacion,
            'layout'      => 'admin',
            'title'       => 'Solicitudes de Actualización de Datos'
        ]);
    }

    /**
     * Procesa (Aprobar o Rechazar) una solicitud de cambio de datos por el administrador.
     */
    public function procesarSolicitud() {
        Auth::requireRole('admin');

        $solicitudId = intval($_POST['id'] ?? 0);
        $accion = strtolower($_POST['accion'] ?? '');
        $motivo = trim($_POST['motivo_admin'] ?? '');
        $adminId = Auth::id() ?? 1;

        if ($solicitudId <= 0 || !in_array($accion, ['aprobado', 'rechazado'])) {
            Flash::set('danger', 'Datos de solicitud o acción no válidos.');
            $this->redirect('/admin/solicitudes-datos');
            return;
        }

        try {
            $solicitudesModel = new SolicitudesModel();
            $solicitudesModel->procesarSolicitud($solicitudId, $accion, $motivo, $adminId);

            Flash::set('success', 'Solicitud ' . ($accion === 'aprobado' ? 'aprobada y datos actualizados' : 'rechazada') . ' exitosamente.');
        } catch (\Exception $e) {
            error_log("[PERFIL] Error procesar solicitud cambio datos: " . $e->getMessage());
            Flash::set('danger', 'Error al procesar la solicitud de cambio de datos.');
        }

        $this->redirect('/admin/solicitudes-datos');
    }

    /**
     * Activa o desactiva la verificación en dos pasos (2FA) para el usuario autenticado.
     */
    public function toggle2fa() {
        Auth::requireLogin();

        // Requerir contraseña actual para cambiar 2FA
        $password = trim($_POST['password'] ?? '');
        if (empty($password)) {
            Flash::set('danger', 'Debe ingresar su contraseña actual para cambiar la verificación en dos pasos.');
            $this->redirect('/perfil');
            return;
        }

        $user = Auth::user();
        $db = \App\Core\Database::getConnection();

        // Verificar contraseña
        if ($user['role'] === 'residente') {
            $stmt = $db->prepare("SELECT password FROM personas WHERE id = :id");
            $stmt->execute(['id' => $user['persona_id']]);
            $hash = $stmt->fetchColumn();
        } else {
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);
            $hash = $stmt->fetchColumn();
        }

        if (!$hash || !password_verify($password, $hash)) {
            Flash::set('danger', 'La contraseña ingresada es incorrecta.');
            $this->redirect('/perfil');
            return;
        }

        if ($user['role'] === 'residente') {
            $personaId = $user['persona_id'] ?? 0;
            $stmt = $db->prepare("SELECT two_factor_enabled FROM personas WHERE id = :id");
            $stmt->execute(['id' => $personaId]);
            $current = $stmt->fetchColumn();

            $newVal = $current ? 0 : 1;
            $stmtUp = $db->prepare("UPDATE personas SET two_factor_enabled = :val WHERE id = :id");
            $stmtUp->execute(['val' => $newVal, 'id' => $personaId]);
        } else {
            $usuarioId = $user['id'] ?? 0;
            $stmt = $db->prepare("SELECT two_factor_enabled FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $usuarioId]);
            $current = $stmt->fetchColumn();

            $newVal = $current ? 0 : 1;
            $stmtUp = $db->prepare("UPDATE usuarios SET two_factor_enabled = :val WHERE id = :id");
            $stmtUp->execute(['val' => $newVal, 'id' => $usuarioId]);
        }

        $msg = $newVal ? 'Verificación en Dos Pasos (2FA) activada con éxito.' : 'Verificación en Dos Pasos (2FA) desactivada.';
        Flash::set('success', $msg);
        $this->redirect('/perfil');
    }
}
