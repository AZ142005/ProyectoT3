<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\UsuariosModel;
use App\Models\PersonasModel;
use App\Models\SolicitudesRegistroModel;

class UsuarioAdminController extends Controller {

    /**
     * Muestra la sección unificada de usuarios y solicitudes de registro,
     * con pestañas y filtros/paginación independientes por pestaña.
     */
    public function index(): void {
        Auth::requireRole(UserRole::ADMIN);

        $tabActual = in_array($_GET['tab'] ?? '', ['usuarios', 'solicitudes'], true) ? $_GET['tab'] : 'usuarios';

        $buscar    = trim($_GET['buscar'] ?? '');
        $rol       = trim($_GET['rol'] ?? '');
        $pagina    = max(1, intval($_GET['page'] ?? 1));
        $porPagina = 25;

        $usuariosModel = new UsuariosModel();
        $resultado = $usuariosModel->obtenerListadoUnificado($buscar, $rol, $pagina, $porPagina);

        // Datos de la pestaña de solicitudes de registro (misma página compartida).
        $estadoSolicitud = isset($_GET['estado']) && in_array($_GET['estado'], ['pendiente', 'aprobada', 'rechazada'], true)
            ? $_GET['estado']
            : null;

        $solicitudesModel = new SolicitudesRegistroModel();
        $resultadoSolicitudes = $solicitudesModel->obtenerListado($pagina, $porPagina, $estadoSolicitud);
        $pendientesCount = $solicitudesModel->contarPendientes();

        $rawReseteada = Flash::get('password_reseteada');
        $passwordReseteada = !empty($rawReseteada) ? json_decode($rawReseteada, true) : null;

        $this->render('admin/usuarios/index', [
            'usuarios'          => $resultado['datos'],
            'paginacion'        => [
                'total'        => $resultado['total'],
                'pagina'       => $resultado['pagina'],
                'porPagina'    => $resultado['porPagina'],
                'totalPaginas' => $resultado['totalPaginas'],
            ],
            'solicitudes'       => $resultadoSolicitudes['datos'],
            'paginacionSolicitudes' => [
                'total'        => $resultadoSolicitudes['total'],
                'pagina'       => $resultadoSolicitudes['pagina'],
                'porPagina'    => $resultadoSolicitudes['porPagina'],
                'totalPaginas' => $resultadoSolicitudes['totalPaginas'],
            ],
            'estadoSolicitud'   => $estadoSolicitud,
            'pendientesCount'   => $pendientesCount,
            'tabActual'         => $tabActual,
            'buscar'            => $buscar,
            'rol'               => $rol,
            'passwordReseteada' => $passwordReseteada,
            'activeRoute'       => 'usuarios',
            'showNav'           => false,
            'title'             => 'Usuarios y Solicitudes - Administrador'
        ]);
    }

    /**
     * Procesa el reinicio de contraseña de un usuario o residente.
     */
    public function reiniciarPassword(): void {
        Auth::requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/usuarios');
            return;
        }

        $tipoEntidad = trim($_POST['tipo_entidad'] ?? '');
        $id          = intval($_POST['id'] ?? 0);
        $modo        = trim($_POST['modo_generacion'] ?? 'auto');
        $passManual  = trim($_POST['password_manual'] ?? '');

        if ($id <= 0 || !in_array($tipoEntidad, ['persona', 'usuario'], true)) {
            Flash::error('Identificador o tipo de cuenta no válido.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $usuariosModel = new UsuariosModel();
        $personasModel = new PersonasModel();

        $nombreUsuario = '';
        $cedulaUsuario = '';

        if ($tipoEntidad === 'persona') {
            $persona = $personasModel->getById($id);
            if (!$persona) {
                Flash::error('El residente seleccionado no existe.');
                $this->redirect('/admin/usuarios');
                return;
            }
            $nombreUsuario = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));
            $cedulaUsuario = $persona['cedula'] ?? '';
        } else {
            $usuario = $usuariosModel->getById($id);
            if (!$usuario) {
                Flash::error('El usuario del sistema seleccionado no existe.');
                $this->redirect('/admin/usuarios');
                return;
            }

            $currentAdminId = intval(Auth::id());
            $targetUserId   = intval($usuario['id']);
            $rolUsuario     = strtolower(trim($usuario['rol'] ?? ''));

            // Prevención de auto-reinicio
            if ($targetUserId === $currentAdminId) {
                Flash::error('No tienes permisos para reiniciar tu propia contraseña desde este módulo.');
                $this->redirect('/admin/usuarios');
                return;
            }

            // Protección entre administradores
            if ($rolUsuario === 'admin') {
                Flash::error('No tienes permisos para reiniciar la contraseña de una cuenta con rol de Administrador.');
                $this->redirect('/admin/usuarios');
                return;
            }

            $nombreUsuario = $usuario['nombre_completo'] ?? $usuario['usuario'] ?? 'Usuario';
            $cedulaUsuario = $usuario['cedula'] ?? $usuario['usuario'] ?? '';
        }

        $nuevaPassword = '';
        if ($modo === 'manual') {
            if (empty($passManual)) {
                Flash::error('Debe ingresar una contraseña manual.');
                $this->redirect('/admin/usuarios');
                return;
            }
            if (strlen($passManual) < 8 || !validarPassword($passManual)) {
                Flash::error('La contraseña manual debe tener al menos 8 caracteres y contener al menos una letra y un número.');
                $this->redirect('/admin/usuarios');
                return;
            }
            $nuevaPassword = $passManual;
        } else {
            // Generación automática: 10 caracteres criptográficamente seguros
            // Garantiza mayúsculas, minúsculas, dígitos y símbolo
            $bytes = bin2hex(random_bytes(3));
            $num   = random_int(10, 99);
            $nuevaPassword = 'C' . $bytes . $num . '!';
        }

        $hash = password_hash($nuevaPassword, PASSWORD_BCRYPT);
        $exito = false;

        if ($tipoEntidad === 'persona') {
            $exito = $personasModel->reiniciarPassword($id, $hash);
        } else {
            $exito = $usuariosModel->reiniciarPassword($id, $hash);
        }

        if ($exito) {
            Flash::success("Contraseña reiniciada correctamente para {$nombreUsuario}.");
            Flash::set('password_reseteada', json_encode([
                'nombre'   => $nombreUsuario,
                'cedula'   => $cedulaUsuario,
                'password' => $nuevaPassword
            ]));
        } else {
            Flash::error('Ocurrió un error al actualizar la contraseña en la base de datos.');
        }

        $this->redirect('/admin/usuarios');
    }

    /**
     * Procesa la actualización de datos de contacto (teléfono y correo) de un residente.
     * Restringe estrictamente la modificación de cuentas de administrador.
     */
    public function actualizarDatos(): void {
        Auth::requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/usuarios');
            return;
        }

        $tipoEntidad = trim($_POST['tipo_entidad'] ?? '');
        $id          = intval($_POST['id'] ?? 0);
        $telefono    = trim($_POST['telefono'] ?? '');
        $email       = trim($_POST['email'] ?? '');

        if ($id <= 0 || empty($tipoEntidad)) {
            Flash::error('Identificador o tipo de cuenta no válido.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Restricción estricta de Backend: No se permite modificar datos de cuentas admin
        if ($tipoEntidad === 'usuario') {
            $usuariosModel = new UsuariosModel();
            $usuario = $usuariosModel->getById($id);
            $rolUsuario = strtolower(trim($usuario['rol'] ?? ''));

            if ($rolUsuario === 'admin' || intval($usuario['id'] ?? 0) === intval(Auth::id())) {
                Flash::error('No tienes permisos para modificar los datos de un usuario con rol de Administrador.');
                $this->redirect('/admin/usuarios');
                return;
            }

            Flash::error('Solo se permite la actualización directa de datos de contacto para usuarios residentes.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $personasModel = new PersonasModel();
        $persona = $personasModel->getById($id);

        if (!$persona) {
            Flash::error('El residente seleccionado no existe.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Validación de Teléfono (obligatorio)
        if (empty($telefono)) {
            Flash::error('El número de teléfono es obligatorio.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $telLimpio = preg_replace('/[^0-9]/', '', $telefono);
        if (!validarTelefono($telLimpio)) {
            Flash::error('El formato del teléfono no es válido (use una operadora venezolana válida: 0412, 0414, 0424, 0416 o 0426).');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Validación de Correo (obligatorio y único)
        if (empty($email)) {
            Flash::error('El correo electrónico es obligatorio.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $email = strtolower(trim($email));
        if (!validarEmail($email)) {
            Flash::error('El formato del correo electrónico no es válido.');
            $this->redirect('/admin/usuarios');
            return;
        }

        if ($personasModel->emailExistsActive($email, $id)) {
            Flash::error('El correo electrónico ya se encuentra registrado por otro residente.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $nombre = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));
        $exito = $personasModel->actualizarContacto($id, $telLimpio, $email);

        if ($exito) {
            Flash::success("Datos de contacto actualizados correctamente para {$nombre}.");
        } else {
            Flash::error('Ocurrió un error al actualizar los datos en la base de datos.');
        }

        $this->redirect('/admin/usuarios');
    }

    /**
     * Procesa la eliminación (soft-delete) de una cuenta de residente.
     * Desvincula la unidad y conserva el historial contable.
     * Bloquea terminantemente la eliminación de cuentas administrativas.
     */
    public function eliminar(): void {
        Auth::requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/usuarios');
            return;
        }

        $tipoEntidad = trim($_POST['tipo_entidad'] ?? '');
        $id          = intval($_POST['id'] ?? 0);

        if ($id <= 0 || empty($tipoEntidad)) {
            Flash::error('Identificador o tipo de cuenta no válido.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Restricción estricta de Backend: No se permite eliminar administradores ni usuarios del sistema
        if ($tipoEntidad !== 'persona') {
            Flash::error('No está permitido eliminar cuentas administrativas ni usuarios del sistema.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $personasModel = new PersonasModel();
        $persona = $personasModel->getById($id);

        if (!$persona) {
            Flash::error('El residente seleccionado no existe.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $nombre = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));
        $exito = $personasModel->eliminarResidente($id);

        if ($exito) {
            Flash::success("El residente {$nombre} ha sido eliminado y desvinculado de la unidad correctamente.");
        } else {
            Flash::error('Ocurrió un error al procesar la eliminación del residente.');
        }

        $this->redirect('/admin/usuarios');
    }
}
