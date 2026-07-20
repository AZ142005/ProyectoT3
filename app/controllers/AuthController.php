<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\PersonasModel;
use App\Models\UsuariosModel;

class AuthController extends Controller {
    /**
     * Login unificado: detecta automáticamente si el email
     * pertenece a un administrador o a un residente.
     */
    public function login() {
        // Si ya está autenticado, redirigir según su rol
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identificador = trim($_POST['email'] ?? '');
            $password      = trim($_POST['password'] ?? '');

            if (empty($identificador) || empty($password)) {
                $error = 'Por favor, ingresa tu correo o cédula y contraseña.';
            } else {
                $esEmail = filter_var($identificador, FILTER_VALIDATE_EMAIL);

                // 1. Buscar en la tabla de administradores (por email)
                if ($esEmail) {
                    $usuariosModel = new UsuariosModel();
                    $admin = $usuariosModel->getActiveByEmail($identificador);

                    if ($admin && password_verify($password, $admin['password'])) {
                        Auth::loginAsAdmin($admin);
                        $usuariosModel->updateUltimoAcceso($admin['id']);
                        $this->redirect('/admin/dashboard');
                    }
                }

                // 2. Buscar en la tabla de residentes (por email o cédula)
                $personasModel = new PersonasModel();
                $residente = $esEmail
                    ? $personasModel->getActiveByEmail($identificador)
                    : $personasModel->getActiveByCedula($identificador);

                if ($residente && !empty($residente['password']) && password_verify($password, $residente['password'])) {
                    Auth::loginAsResidente($residente);
                    $this->redirect('/residente/dashboard');
                }

                // 3. Ninguna coincidencia
                $error = 'Credenciales incorrectas. Verifica tu correo/cédula y contraseña.';
            }
        }

        $this->render('auth/login', [
            'error'   => $error,
            'showNav' => false,
            'title'   => 'Iniciar Sesión - Condominio Digital'
        ]);
    }

    /**
     * Registro de residentes: la cédula debe existir previamente
     * en la tabla personas (pre-registrado por administración).
     */
    public function register() {
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cedula            = trim($_POST['cedula'] ?? '');
            $email             = trim($_POST['email'] ?? '');
            $password          = trim($_POST['password'] ?? '');
            $password_confirm  = trim($_POST['password_confirm'] ?? '');

            if (empty($cedula) || empty($email) || empty($password) || empty($password_confirm)) {
                $error = 'Todos los campos son obligatorios.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'El formato de correo electrónico no es válido.';
            } elseif (strlen($password) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres.';
            } elseif ($password !== $password_confirm) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                $personasModel = new PersonasModel();

                // Verificar que la cédula existe en el sistema
                $persona = $personasModel->getActiveByCedula($cedula);
                if (!$persona) {
                    $error = 'La cédula ingresada no está registrada en el sistema del condominio. Consulta con la administración.';
                } elseif (!empty($persona['email']) && !empty($persona['password'])) {
                    $error = 'Esta cédula ya tiene una cuenta registrada. Usa el formulario de inicio de sesión.';
                } elseif ($personasModel->emailExists($email)) {
                    $error = 'Este correo electrónico ya está registrado.';
                } else {
                    // Registrar: actualizar persona con email y password hasheado
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $result = $personasModel->register($cedula, $email, $hashedPassword);

                    if ($result) {
                        $success = '¡Cuenta creada exitosamente! Ya puedes iniciar sesión.';
                    } else {
                        $error = 'Ocurrió un error al registrar tu cuenta. Intenta de nuevo.';
                    }
                }
            }
        }

        $this->render('auth/register', [
            'error'   => $error,
            'success' => $success,
            'showNav' => false,
            'title'   => 'Crear Cuenta - Condominio Digital'
        ]);
    }

    /**
     * Cierra la sesión activa (admin o residente).
     */
    public function logout() {
        Auth::logout();
        $this->redirect('/auth/login');
    }

    /**
     * Redirige al dashboard correspondiente según el rol.
     */
    private function redirectByRole() {
        if (Auth::hasRole('admin')) {
            $this->redirect('/admin/dashboard');
        } else {
            $this->redirect('/residente/dashboard');
        }
    }
}
