<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Models\UsuariosModel;
use App\Models\PersonasModel;

class UsuarioAdminController extends Controller {

    /**
     * Muestra el listado unificado de usuarios y residentes con filtros y paginación.
     */
    public function index(): void {
        Auth::requireRole(UserRole::ADMIN);

        $buscar    = trim($_GET['buscar'] ?? '');
        $rol       = trim($_GET['rol'] ?? '');
        $pagina    = max(1, intval($_GET['page'] ?? 1));
        $porPagina = 15;

        $usuariosModel = new UsuariosModel();
        $resultado = $usuariosModel->obtenerListadoUnificado($buscar, $rol, $pagina, $porPagina);

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
            'buscar'            => $buscar,
            'rol'               => $rol,
            'passwordReseteada' => $passwordReseteada,
            'activeRoute'       => 'usuarios',
            'showNav'           => false,
            'title'             => 'Gestión de Usuarios - Administrador'
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
}
