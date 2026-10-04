<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Database;
use App\Core\UserRole;
use App\Models\UsuariosModel;
use App\Models\PersonasModel;
use App\Models\SolicitudesRegistroModel;
use App\Models\SolicitudesModel;

class UsuarioAdminController extends Controller {

    /**
     * Muestra la sección unificada de usuarios y solicitudes de registro,
     * con pestañas y filtros/paginación independientes por pestaña.
     */
    public function index(): void {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        $tabActual = in_array($_GET['tab'] ?? '', ['usuarios', 'solicitudes', 'cambios'], true) ? $_GET['tab'] : 'usuarios';

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

        // Datos de la pestaña de cambios de datos de residentes.
        $estadoCambio = isset($_GET['estado']) && in_array($_GET['estado'], ['pendiente', 'aprobado', 'rechazado'], true)
            ? $_GET['estado']
            : null;

        $solicitudesCambioModel = new SolicitudesModel();
        $solicitudesCambioModel->eliminarExpiradas();
        $resultadoCambios = $solicitudesCambioModel->obtenerTodasAdmin($pagina, $porPagina, $estadoCambio);
        $cambiosPendientesCount = $solicitudesCambioModel->contarPendientes();

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
            'solicitudesCambio' => $resultadoCambios['datos'],
            'paginacionCambios' => [
                'total'        => $resultadoCambios['total'],
                'pagina'       => $resultadoCambios['pagina'],
                'porPagina'    => $resultadoCambios['porPagina'],
                'totalPaginas' => $resultadoCambios['totalPaginas'],
            ],
            'estadoCambio'      => $estadoCambio,
            'cambiosPendientesCount' => $cambiosPendientesCount,
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
     * Procesa (aprueba/rechaza) una solicitud de cambio de datos de un residente.
     * Al aprobar, el modelo aplica los cambios sobre personas con revalidación.
     */
    public function procesarSolicitudCambio(): void {
        Auth::requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/usuarios?tab=cambios');
            return;
        }

        $id     = intval($_POST['id'] ?? 0);
        $accion = trim($_POST['accion'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($id <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
            Flash::error('La solicitud o la acción seleccionada no es válida.');
            $this->redirect('/admin/usuarios?tab=cambios');
            return;
        }

        if ($accion === 'rechazar' && $motivo === '') {
            Flash::error('Debe indicar el motivo para rechazar la solicitud de cambio de datos.');
            $this->redirect('/admin/usuarios?tab=cambios');
            return;
        }

        $estado = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';

        try {
            $solicitudesModel = new SolicitudesModel();
            $solicitudesModel->procesarSolicitud($id, $estado, $motivo !== '' ? $motivo : null, intval(Auth::id()));

            Flash::success($accion === 'aprobar'
                ? 'Solicitud de cambio de datos aprobada y aplicada correctamente.'
                : 'Solicitud de cambio de datos rechazada correctamente.');
        } catch (\Throwable $e) {
            Flash::error('No se pudo procesar la solicitud: ' . $e->getMessage());
        }

        $this->redirect('/admin/usuarios?tab=cambios');
    }

    /**
     * Procesa el alta de un nuevo usuario del sistema (rol auditor).
     */
    public function crearUsuario(): void {
        Auth::requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/usuarios');
            return;
        }

        $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
        $usuario        = trim($_POST['usuario'] ?? '');
        $email          = strtolower(trim($_POST['email'] ?? ''));
        $cedula         = trim($_POST['cedula'] ?? '');
        $telefono       = trim($_POST['telefono'] ?? '');
        $password       = (string)($_POST['password'] ?? '');
        $rol            = trim($_POST['rol'] ?? '');

        if (empty($nombreCompleto)) {
            Flash::error('El nombre completo es obligatorio.');
            $this->redirect('/admin/usuarios');
            return;
        }

        if (empty($usuario)) {
            Flash::error('El nombre de usuario es obligatorio.');
            $this->redirect('/admin/usuarios');
            return;
        }

        if (empty($email)) {
            Flash::error('El correo electrónico es obligatorio.');
            $this->redirect('/admin/usuarios');
            return;
        }

        if (!validarEmail($email)) {
            Flash::error('El formato del correo electrónico no es válido.');
            $this->redirect('/admin/usuarios');
            return;
        }

        if ($rol !== 'auditor') {
            Flash::error('El alta de usuarios está limitada al rol Auditor.');
            $this->redirect('/admin/usuarios');
            return;
        }

        if (!validarPassword($password)) {
            Flash::error('La contraseña debe tener al menos 8 caracteres y contener al menos una letra y un número.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Validación de Cédula (opcional, pero si se provee debe cumplir formato)
        $cedulaNormalizada = null;
        if ($cedula !== '') {
            if (!validarCedula($cedula)) {
                Flash::error('El formato del número de cédula no es válido (debe tener entre 5 y 8 dígitos).');
                $this->redirect('/admin/usuarios');
                return;
            }
            $cedulaNormalizada = normalizarCedula($cedula);
        }

        // Validación de Teléfono (opcional, pero si se provee debe cumplir formato venezolano)
        $telefonoLimpio = null;
        if ($telefono !== '') {
            $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
            if (!validarTelefono($telefonoLimpio)) {
                Flash::error('El formato del teléfono no es válido (use una operadora venezolana válida: 0412, 0414, 0424, 0416 o 0426).');
                $this->redirect('/admin/usuarios');
                return;
            }
        }

        $usuariosModel = new UsuariosModel();
        $db = Database::getConnection();

        // Unicidad del nombre de usuario
        $stmtUsuario = $db->prepare("SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1");
        $stmtUsuario->execute(['usuario' => $usuario]);
        if ($stmtUsuario->fetchColumn()) {
            Flash::error('El nombre de usuario ya se encuentra registrado.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Unicidad del correo electrónico
        if ($usuariosModel->emailExists($email)) {
            Flash::error('El correo electrónico ya se encuentra registrado por otro usuario.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Unicidad de la cédula (si fue suministrada)
        if ($cedulaNormalizada !== null && $usuariosModel->cedulaExisteEnOtroUsuario($cedulaNormalizada, 0)) {
            Flash::error('El número de cédula ya se encuentra registrado por otro usuario.');
            $this->redirect('/admin/usuarios');
            return;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO usuarios (usuario, email, cedula, telefono, password, nombre_completo, rol, estado)
                VALUES (:usuario, :email, :cedula, :telefono, :password, :nombre_completo, :rol, 1)
            ");
            $exito = $stmt->execute([
                'usuario'         => $usuario,
                'email'           => $email,
                'cedula'          => $cedulaNormalizada,
                'telefono'        => $telefonoLimpio,
                'password'        => password_hash($password, PASSWORD_BCRYPT),
                'nombre_completo' => $nombreCompleto,
                'rol'             => $rol
            ]);
        } catch (\PDOException $e) {
            error_log("[USUARIOS] Error al crear usuario: " . sanitize_exception_message($e));
            $exito = false;
        }

        if ($exito) {
            $rolTexto = 'Auditor';
            Flash::success("Usuario {$usuario} creado correctamente con rol {$rolTexto}.");
        } else {
            Flash::error('Ocurrió un error al crear el usuario en la base de datos.');
        }

        $this->redirect('/admin/usuarios');
    }

    /**
     * Procesa el cambio de rol (admin/auditor) de un usuario del sistema.
     * Protege la degradación del último administrador activo.
     */
    public function cambiarRol(): void {
        Auth::requireRole(UserRole::ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/usuarios');
            return;
        }

        $id       = intval($_POST['id'] ?? 0);
        $nuevoRol = trim($_POST['nuevo_rol'] ?? '');

        if ($id <= 0 || !in_array($nuevoRol, ['admin', 'auditor'], true)) {
            Flash::error('El usuario o el rol seleccionado no es válido.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $usuariosModel = new UsuariosModel();
        $usuario = $usuariosModel->getById($id);

        if (!$usuario) {
            Flash::error('El usuario del sistema seleccionado no existe.');
            $this->redirect('/admin/usuarios');
            return;
        }

        $rolActual = strtolower(trim($usuario['rol'] ?? ''));

        if ($rolActual === $nuevoRol) {
            Flash::info('El usuario ya posee el rol seleccionado.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Un administrador no puede cambiar el rol de su propio usuario.
        if ($id === (int)Auth::id()) {
            Flash::error('No es posible cambiar el rol de su propio usuario.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // No se puede ascender a un auditor al rol de Administrador.
        if ($rolActual === 'auditor' && $nuevoRol === 'admin') {
            Flash::error('No es posible ascender a un auditor al rol de Administrador.');
            $this->redirect('/admin/usuarios');
            return;
        }

        // Protección clave: no dejar al sistema sin administradores activos.
        if ($rolActual === 'admin' && $nuevoRol === 'auditor' && (int)($usuario['estado'] ?? 0) === 1) {
            $db = Database::getConnection();
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND estado = 1 AND id != :id");
            $stmtCount->execute(['id' => $id]);

            if ((int)$stmtCount->fetchColumn() === 0) {
                Flash::error('No es posible degradar al último administrador activo del sistema. Asigne otro administrador activo antes de cambiar este rol.');
                $this->redirect('/admin/usuarios');
                return;
            }
        }

        $nombre = $usuario['nombre_completo'] ?? $usuario['usuario'] ?? 'Usuario';

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE usuarios SET rol = :rol WHERE id = :id");
            $exito = $stmt->execute(['rol' => $nuevoRol, 'id' => $id]);
        } catch (\PDOException $e) {
            error_log("[USUARIOS] Error al cambiar rol: " . sanitize_exception_message($e));
            $exito = false;
        }

        if ($exito) {
            $rolTexto = $nuevoRol === 'admin' ? 'Administrador' : 'Auditor';
            Flash::success("Rol de {$nombre} actualizado a {$rolTexto} correctamente.");
        } else {
            Flash::error('Ocurrió un error al actualizar el rol en la base de datos.');
        }

        $this->redirect('/admin/usuarios');
    }

    /**
     * Procesa el reinicio de contraseña de un usuario o residente.
     */
    public function reiniciarPassword(): void {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

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
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

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
     * Procesa la eliminación lógica de una cuenta: residentes (desvincula la
     * unidad) o usuarios del sistema con rol auditor (revoca el acceso).
     * Bloquea la eliminación de administradores.
     */
    public function eliminar(): void {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

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

        if ($tipoEntidad === 'persona') {
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
        } elseif ($tipoEntidad === 'usuario') {
            $usuariosModel = new UsuariosModel();
            $usuario = $usuariosModel->getById($id);

            if (!$usuario) {
                Flash::error('El usuario del sistema seleccionado no existe.');
                $this->redirect('/admin/usuarios');
                return;
            }

            // Restricción estricta de Backend: no se permite eliminar cuentas administrativas.
            if (strtolower(trim($usuario['rol'] ?? '')) === 'admin') {
                Flash::error('No está permitido eliminar cuentas administrativas del sistema.');
                $this->redirect('/admin/usuarios');
                return;
            }

            if ($id === (int)Auth::id()) {
                Flash::error('No es posible eliminar su propia cuenta.');
                $this->redirect('/admin/usuarios');
                return;
            }

            $nombre = $usuario['nombre_completo'] ?? $usuario['usuario'] ?? 'Usuario';

            if ($usuariosModel->eliminarUsuario($id)) {
                Flash::success("El usuario {$nombre} ha sido eliminado correctamente.");
            } else {
                Flash::error('Ocurrió un error al procesar la eliminación del usuario.');
            }
        } else {
            Flash::error('Identificador o tipo de cuenta no válido.');
        }

        $this->redirect('/admin/usuarios');
    }
}
