<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\UserRole;
use App\Core\RateLimiter;
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
            // Rate limiting: máximo 5 intentos cada 15 minutos
            if (!RateLimiter::attempt('login', 5, 900)) {
                $segundos = RateLimiter::secondsUntilAvailable('login', 900);
                $minutos = ceil($segundos / 60);
                $error = "Demasiados intentos de inicio de sesión. Intente de nuevo en {$minutos} minuto(s).";
            } else {
                $identificador = trim($_POST['email'] ?? '');
                $password      = trim($_POST['password'] ?? '');

                if (empty($identificador) || empty($password)) {
                    $error = 'Por favor, ingresa tu correo o cédula y contraseña.';
                } elseif (mb_strlen($identificador) > 255) {
                    $error = 'El correo o cédula ingresado es demasiado largo.';
                } else {
                    $esEmail = filter_var($identificador, FILTER_VALIDATE_EMAIL);
                    $loginExitoso = false;
                    $foundUserId = null;
                    $foundType = null; // 'admin' or 'residente'

                    $usuariosModel = new UsuariosModel();
                    $personasModel = new PersonasModel();

                    // 1. Buscar en la tabla de usuarios (Admin / Auditor)
                    $usuario = $esEmail ? $usuariosModel->getActiveByEmail($identificador) : null;

                    if ($usuario) {
                        if ($usuariosModel->estaBloqueado((int)$usuario['id'])) {
                            $error = 'Su cuenta ha sido bloqueada temporalmente por demasiados intentos fallidos. Espere 30 minutos.';
                        } elseif (password_verify($password, $usuario['password'])) {
                            $usuariosModel->resetIntentosFallidos((int)$usuario['id']);
                            $loginExitoso = true;
                            $foundUserId = (int)$usuario['id'];
                            $foundType = 'admin';

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
                        } else {
                            // Admin found but password wrong → increment admin counter
                            $usuariosModel->incrementarIntentosFallidos((int)$usuario['id']);
                            $error = 'Credenciales incorrectas. Verifica tu correo/cédula y contraseña.';
                        }
                    }

                    // 2. Buscar en la tabla de residentes (Personas) — only if admin wasn't found or wasn't locked
                    if (!$usuario && empty($error)) {
                        $residente = $esEmail
                            ? $personasModel->getActiveByEmail($identificador)
                            : $personasModel->getActiveByCedula($identificador);

                        if ($residente) {
                            if ($personasModel->estaBloqueado((int)$residente['id'])) {
                                $error = 'Su cuenta ha sido bloqueada temporalmente por demasiados intentos fallidos. Espere 30 minutos.';
                            } elseif (!empty($residente['password']) && password_verify($password, $residente['password'])) {
                                $personasModel->resetIntentosFallidos((int)$residente['id']);
                                $loginExitoso = true;
                                $foundUserId = (int)$residente['id'];
                                $foundType = 'residente';

                                if (!empty($residente['two_factor_enabled'])) {
                                    return $this->iniciarFlujo2fa($residente, UserRole::RESIDENTE);
                                }

                                Auth::loginAsResidente($residente);
                                $this->redirect('/residente/dashboard');
                                return;
                            } else {
                                // Residente found but password wrong → increment residente counter
                                $personasModel->incrementarIntentosFallidos((int)$residente['id']);
                                $error = 'Credenciales incorrectas. Verifica tu correo/cédula y contraseña.';
                            }
                        } else {
                            // 3. Ninguna coincidencia
                            $error = 'Credenciales incorrectas. Verifica tu correo/cédula y contraseña.';
                        }
                    }
                }
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
            'role'    => $rol
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

        // OTP Válido: Recargar usuario desde la base de datos (no usar sesión temporal)
        $rol = $pending['role'];
        $usuarioId = $pending['user_id'];
        unset($_SESSION['2fa_pending']);

        if ($rol === UserRole::ADMIN || $rol === UserRole::AUDITOR) {
            $usuariosModel = new UsuariosModel();
            $rawUser = $usuariosModel->getActiveById($usuarioId);
            if (!$rawUser) {
                Flash::error('La cuenta ya no está disponible.');
                $this->redirect('/auth/login');
                return;
            }
            if ($rol === UserRole::AUDITOR) {
                Auth::loginAsAuditor($rawUser);
                $this->redirect('/auditor/dashboard');
            } else {
                Auth::loginAsAdmin($rawUser);
                $this->redirect('/admin/dashboard');
            }
        } else {
            $personasModel = new PersonasModel();
            $rawUser = $personasModel->getActiveById($usuarioId);
            if (!$rawUser) {
                Flash::error('La cuenta ya no está disponible.');
                $this->redirect('/auth/login');
                return;
            }
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

        // Rate limiting: máximo 3 reenvíos cada 5 minutos, vinculado al user_id
        $otpRateKey = 'otp_resend_' . $pending['user_id'];
        if (!RateLimiter::attempt($otpRateKey, 3, 300)) {
            $segundos = RateLimiter::secondsUntilAvailable($otpRateKey, 300);
            $minutos = ceil($segundos / 60);
            Flash::set('danger', "Debe esperar {$minutos} minuto(s) antes de solicitar otro código.");
            $this->redirect('/auth/verificar-2fa');
            return;
        }

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
            if (!RateLimiter::attempt('register', 5, 3600)) {
                $segundos = RateLimiter::secondsUntilAvailable('register', 3600);
                $minutos = ceil($segundos / 60);
                $error = "Demasiados intentos de registro. Intente de nuevo en {$minutos} minuto(s).";
            } else {
                $cedula            = trim($_POST['cedula'] ?? '');
                $email             = trim($_POST['email'] ?? '');
                $password          = trim($_POST['password'] ?? '');
                $password_confirm  = trim($_POST['password_confirm'] ?? '');

                if (empty($cedula) || empty($email) || empty($password) || empty($password_confirm)) {
                    $error = 'Todos los campos son obligatorios.';
                } elseif (!validarCedula($cedula)) {
                    $error = 'El formato de la cédula no es válido. Use el formato V-12345678 o E-12345678.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'El formato de correo electrónico no es válido.';
                } elseif (strlen($password) < 8) {
                    $error = 'La contraseña debe tener al menos 8 caracteres.';
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
