<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Database;
use App\Core\UserRole;
use App\Models\PersonasModel;
use App\Models\UsuariosModel;
use App\Models\OtpModel;
use App\Services\NotificationService;

class AuthController extends Controller {

    /**
     * Login unificado: detecta automáticamente si el email/cédula
     * pertenece a un administrador, auditor o residente.
     */
    public function login() {
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

                // 1. Buscar en la tabla de usuarios (Admin / Auditor)
                if ($esEmail) {
                    $usuariosModel = new UsuariosModel();
                    $usuario = $usuariosModel->getActiveByEmail($identificador);

                    if ($usuario && password_verify($password, $usuario['password'])) {
                        // Comprobar si tiene 2FA habilitado
                        if (!empty($usuario['two_factor_enabled'])) {
                            return $this->iniciarFlujo2fa($usuario, $usuario['rol'] ?? UserRole::ADMIN);
                        }

                        if (($usuario['rol'] ?? '') === UserRole::AUDITOR) {
                            Auth::loginAsAuditor($usuario);
                            $this->redirect('/auditor/dashboard');
                        } else {
                            Auth::loginAsAdmin($usuario);
                            $this->redirect('/admin/dashboard');
                        }
                        return;
                    }
                }

                // 2. Buscar en la tabla de residentes (Personas)
                $personasModel = new PersonasModel();
                $residente = $esEmail
                    ? $personasModel->getActiveByEmail($identificador)
                    : $personasModel->getActiveByCedula($identificador);

                if ($residente && !empty($residente['password']) && password_verify($password, $residente['password'])) {
                    // Comprobar si tiene 2FA habilitado
                    if (!empty($residente['two_factor_enabled'])) {
                        return $this->iniciarFlujo2fa($residente, UserRole::RESIDENTE);
                    }

                    Auth::loginAsResidente($residente);
                    $this->redirect('/residente/dashboard');
                    return;
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
     * Inicia el flujo temporal de 2FA generando y despachando el OTP.
     */
    private function iniciarFlujo2fa(array $user, string $rol) {
        $usuarioId = (int)$user['id'];
        $email = $user['email'] ?? ($user['usuario'] ?? '');
        $nombre = $user['nombre_completo'] ?? ($user['nombre'] ?? 'Usuario');

        $_SESSION['2fa_pending'] = [
            'user_id' => $usuarioId,
            'email'   => $email,
            'nombre'  => $nombre,
            'role'    => $rol,
            'raw_user'=> $user
        ];

        $otpModel = new OtpModel();
        $otp = $otpModel->generarOtp($usuarioId);

        $notificationService = new NotificationService();
        $notificationService->enviarOtp($email, $otp, $nombre);

        Flash::set('info', 'Hemos enviado un código de verificación de 6 dígitos a su correo electrónico. Por favor, revíselo e ingréselo a continuación.');
        $this->redirect('/auth/verificar-2fa');
    }

    /**
     * Muestra la vista de verificación del código OTP de 2FA.
     */
    public function verificar2faView() {
        if (!isset($_SESSION['2fa_pending'])) {
            $this->redirect('/auth/login');
            return;
        }

        $this->render('auth/verificar_2fa', [
            'email'   => $_SESSION['2fa_pending']['email'],
            'showNav' => false,
            'title'   => 'Verificación en Dos Pasos (2FA)'
        ]);
    }

    /**
     * Procesa la validación del código OTP ingresado.
     */
    public function procesar2fa() {
        if (!isset($_SESSION['2fa_pending'])) {
            $this->redirect('/auth/login');
            return;
        }

        $codigo = trim($_POST['codigo_otp'] ?? '');
        $pending = $_SESSION['2fa_pending'];

        if (empty($codigo) || strlen($codigo) !== 6 || !ctype_digit($codigo)) {
            Flash::set('danger', 'Por favor, ingrese un código numérico válido de 6 dígitos.');
            $this->redirect('/auth/verificar-2fa');
            return;
        }

        $otpModel = new OtpModel();
        $resultado = $otpModel->verificarOtp($pending['user_id'], $codigo);

        if (!$resultado['valido']) {
            Flash::set('danger', $resultado['error']);
            $this->redirect('/auth/verificar-2fa');
            return;
        }

        // OTP Válido: Completar Login
        $rawUser = $pending['raw_user'];
        $rol = $pending['role'];
        unset($_SESSION['2fa_pending']);

        if ($rol === UserRole::ADMIN) {
            Auth::loginAsAdmin($rawUser);
            $this->redirect('/admin/dashboard');
        } elseif ($rol === UserRole::AUDITOR) {
            Auth::loginAsAuditor($rawUser);
            $this->redirect('/auditor/dashboard');
        } else {
            Auth::loginAsResidente($rawUser);
            $this->redirect('/residente/dashboard');
        }
    }

    /**
     * Reenvía un nuevo código OTP invalidando el anterior y reiniciando el contador.
     */
    public function reenviarOtp() {
        if (!isset($_SESSION['2fa_pending'])) {
            $this->redirect('/auth/login');
            return;
        }

        $pending = $_SESSION['2fa_pending'];
        $otpModel = new OtpModel();
        $nuevoOtp = $otpModel->generarOtp($pending['user_id']);

        $notificationService = new NotificationService();
        $notificationService->enviarOtp($pending['email'], $nuevoOtp, $pending['nombre']);

        Flash::set('success', 'Se ha generado y enviado un nuevo código de verificación a su correo.');
        $this->redirect('/auth/verificar-2fa');
    }

    /**
     * Registro de residentes.
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

                $persona = $personasModel->getActiveByCedula($cedula);
                if (!$persona) {
                    $error = 'La cédula ingresada no está registrada en el sistema del condominio. Consulta con la administración.';
                } elseif (!empty($persona['email']) && !empty($persona['password'])) {
                    $error = 'Esta cédula ya tiene una cuenta registrada. Usa el formulario de inicio de sesión.';
                } elseif ($personasModel->emailExists($email)) {
                    $error = 'Este correo electrónico ya está registrado.';
                } else {
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
     * Cierra la sesión activa (admin, residente o auditor).
     */
    public function logout() {
        Auth::logout();
        $this->redirect('/auth/login');
    }

    /**
     * Redirige al dashboard correspondiente según el rol.
     */
    private function redirectByRole() {
        if (Auth::hasRole(UserRole::ADMIN)) {
            $this->redirect('/admin/dashboard');
        } elseif (Auth::hasRole(UserRole::AUDITOR)) {
            $this->redirect('/auditor/dashboard');
        } else {
            $this->redirect('/residente/dashboard');
        }
    }
}
