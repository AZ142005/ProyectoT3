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
use App\Models\SolicitudesRegistroModel;
use App\Models\UnidadesModel;
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
                        $residente = null;
                        $variantesCedula = [];

                        if ($esEmail) {
                            $residente = $personasModel->getActiveByEmail($identificador);
                        } else {
                            $cedulaNorm = normalizarCedula($identificador);
                            $soloDigitos = preg_replace('/\D/', '', $identificador);
                            $variantesCedula = array_values(array_unique(array_filter([
                                $identificador,
                                $cedulaNorm,
                                $soloDigitos,
                                strlen($soloDigitos) >= 4 ? ('V' . $soloDigitos) : null,
                                strlen($soloDigitos) >= 4 ? ('E' . $soloDigitos) : null,
                                strlen($soloDigitos) >= 4 ? ('V-' . $soloDigitos) : null,
                                strlen($soloDigitos) >= 4 ? ('E-' . $soloDigitos) : null,
                            ])));

                            foreach ($variantesCedula as $vCed) {
                                $residente = $personasModel->getActiveByCedula($vCed);
                                if ($residente) {
                                    break;
                                }
                            }
                        }

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
                            // 3. Si no se encuentra un usuario administrador activo ni residente activo:
                            $solicitudesModel = new SolicitudesRegistroModel();
                            $solicitud = $solicitudesModel->buscarUltimaPorIdentificador($identificador);

                            if ($solicitud && $solicitud['estado'] === 'pendiente') {
                                $error = 'Su cuenta se encuentra en proceso de revisión y aún no ha sido verificada por la administración. No podrá iniciar sesión hasta que su solicitud sea validada y aprobada.';
                            } elseif ($solicitud && $solicitud['estado'] === 'rechazada') {
                                $motivo = !empty($solicitud['motivo_rechazo']) ? ': ' . $solicitud['motivo_rechazo'] : '.';
                                $error = "Su solicitud de registro fue rechazada por la administración{$motivo}";
                            } else {
                                // b. Si no hay solicitud pero existe en personas con estado != 1:
                                $personaInactiva = null;
                                if ($esEmail) {
                                    $personaInactiva = $personasModel->getByEmail($identificador);
                                } else {
                                    foreach ($variantesCedula as $vCed) {
                                        $personaInactiva = $personasModel->getByCedula($vCed);
                                        if ($personaInactiva) {
                                            break;
                                        }
                                    }
                                }

                                if ($personaInactiva && (int)($personaInactiva['estado'] ?? 0) !== 1) {
                                    $error = 'Su cuenta de residente se encuentra inactiva o aún no ha sido verificada. Por favor, comuníquese con la administración.';
                                } else {
                                    // c. Si no hay lo anterior pero existe en usuarios con estado != 1:
                                    $usuarioInactivo = $usuariosModel->getByEmailOrUsuario($identificador);
                                    if ($usuarioInactivo && (int)($usuarioInactivo['estado'] ?? 0) !== 1) {
                                        $error = 'Su cuenta de usuario se encuentra inactiva o aún no ha sido verificada. Por favor, contacte a la administración.';
                                    } else {
                                        // d. Si nada de lo anterior coincide, mantener el error genérico:
                                        $error = 'Credenciales incorrectas. Verifica tu correo/cédula y contraseña.';
                                    }
                                }
                            }
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
        // G1-03: Rate limit OTP verification — max 10 attempts per 5 min per user
        $pending = $_SESSION['2fa_pending'] ?? null;
        if ($pending && !RateLimiter::attempt('otp_verify_' . $pending['user_id'], 10, 300)) {
            $segundos = RateLimiter::secondsUntilAvailable('otp_verify_' . $pending['user_id'], 300);
            $minutos = ceil($segundos / 60);
            Flash::set('danger', "Demasiados intentos de verificación. Espere {$minutos} minuto(s).");
            $this->redirect('/auth/verificar-2fa');
            return;
        }

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

        // Proactively clean ALL expired OTP tokens for this user before generating new one
        try {
            $db = \App\Core\Database::getConnection();
            $cleanup = $db->prepare("DELETE FROM auth_otp_tokens WHERE usuario_id = :uid AND expires_at < NOW()");
            $cleanup->execute(['uid' => $pending['user_id']]);
        } catch (\Exception $e) {
            // Non-critical — OTP generation still proceeds
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
        $unidadesModel = new UnidadesModel();
        $apartamentosDisponibles = $unidadesModel->getDisponibles();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!RateLimiter::attempt('register', 5, 3600)) {
                $segundos = RateLimiter::secondsUntilAvailable('register', 3600);
                $minutos = ceil($segundos / 60);
                $error = "Demasiados intentos de registro. Intente de nuevo en {$minutos} minuto(s).";
            } else {
                $nombre           = trim($_POST['nombre'] ?? '');
                $apellido         = trim($_POST['apellido'] ?? '');
                $cedulaTipo       = strtoupper(trim($_POST['cedula_tipo'] ?? 'V'));
                $cedulaNumero     = preg_replace('/[^0-9]/', '', trim($_POST['cedula_numero'] ?? ''));

                if (empty($cedulaNumero) && !empty($_POST['cedula'])) {
                    $raw = normalizarCedula($_POST['cedula']);
                    if (in_array(substr($raw, 0, 1), ['V', 'E'], true)) {
                        $cedulaTipo = substr($raw, 0, 1);
                        $cedulaNumero = substr($raw, 1);
                    } else {
                        $cedulaNumero = $raw;
                    }
                }

                $telCodigo         = trim($_POST['telefono_codigo'] ?? '');
                $telNumero         = trim($_POST['telefono_numero'] ?? '');
                $telefono          = !empty($telNumero) ? ($telCodigo . $telNumero) : trim($_POST['telefono'] ?? '');
                $email             = trim($_POST['email'] ?? '');
                $unidadId          = intval($_POST['unidad_id'] ?? 0);
                $numeroResidentes  = intval($_POST['numero_residentes'] ?? 1);
                $password          = trim($_POST['password'] ?? '');
                $password_confirm  = trim($_POST['password_confirm'] ?? '');

                if (empty($nombre) || empty($apellido) || empty($cedulaNumero) || empty($email) || empty($password) || empty($password_confirm) || $unidadId <= 0) {
                    $error = 'Todos los campos marcados con (*) son obligatorios.';
                } elseif (!in_array($cedulaTipo, ['V', 'E'], true)) {
                    $error = 'Tipo de documento no válido (debe seleccionar V o E).';
                } elseif (strlen($cedulaNumero) < 5 || strlen($cedulaNumero) > 8 || !ctype_digit($cedulaNumero)) {
                    $error = 'El número de cédula debe contener entre 5 y 8 dígitos numéricos.';
                } elseif (!validarCedula($cedulaTipo . $cedulaNumero)) {
                    $error = 'El formato de la cédula no es válido.';
                } elseif (!empty($telNumero) && (strlen($telNumero) !== 7 || !ctype_digit($telNumero))) {
                    $error = 'El número de teléfono debe contener exactamente 7 dígitos tras la operadora.';
                } elseif (!empty($telefono) && !validarTelefono($telefono)) {
                    $error = 'El formato del teléfono no es válido (use operadoras 0412, 0422, 0414, 0424, 0416 o 0426).';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'El formato de correo electrónico no es válido.';
                } elseif ($numeroResidentes < 1 || $numeroResidentes > 20) {
                    $error = 'El número de residentes debe ser entre 1 y 20 personas.';
                } elseif (strlen($password) < 8 || !validarPassword($password)) {
                    $error = 'La contraseña debe tener al menos 8 caracteres y contener al menos una letra y un número.';
                } elseif ($password !== $password_confirm) {
                    $error = 'Las contraseñas no coinciden.';
                } else {
                    $cedula = $cedulaTipo . $cedulaNumero;
                    $personasModel = new PersonasModel();
                    $usuariosModel = new UsuariosModel();
                    $solicitudesModel = new SolicitudesRegistroModel();

                    $persona = $personasModel->getByCedula($cedula) ?: $personasModel->getByCedula($cedulaTipo . '-' . $cedulaNumero);
                    if ($persona && (int)($persona['estado'] ?? 0) === 1) {
                        $error = 'Esta cédula ya se encuentra registrada como residente activo en el condominio. Use el formulario de inicio de sesión.';
                    } elseif ($persona && $personasModel->emailExistsActive($email, (int)$persona['id'])) {
                        $error = 'Este correo electrónico ya está registrado por otro residente activo.';
                    } elseif (!$persona && $personasModel->emailExistsActive($email)) {
                        $error = 'Este correo electrónico ya está registrado por otro residente activo.';
                    } elseif ($usuariosModel->getActiveByEmail($email)) {
                        $error = 'Este correo electrónico ya está registrado en el sistema.';
                    } else {
                        try {
                            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                            $solicitudId = $solicitudesModel->crearSolicitud([
                                'cedula'            => $cedula,
                                'nombre'            => $nombre,
                                'apellido'          => $apellido,
                                'telefono'          => $telefono,
                                'email'             => $email,
                                'unidad_id'         => $unidadId,
                                'numero_residentes' => $numeroResidentes,
                                'password_hash'     => $hashedPassword,
                            ]);

                            if ($solicitudId > 0) {
                                $this->redirect('/auth/registro-exitoso');
                                return;
                            } else {
                                $error = 'Ocurrió un error al procesar la solicitud de registro. Intente de nuevo.';
                            }
                        } catch (\RuntimeException $re) {
                            $error = $re->getMessage();
                        } catch (\Exception $e) {
                            error_log("[AUTH REGISTRO] Error: " . $e->getMessage());
                            $error = 'Error interno al procesar el registro. Intente más tarde.';
                        }
                    }
                }
            }
        }

        $this->render('auth/register', [
            'error'                   => $error,
            'apartamentosDisponibles' => $apartamentosDisponibles,
            'showNav'                 => false,
            'title'                   => 'Crear Cuenta - Condominio Digital'
        ]);
    }

    /**
     * Pantalla de confirmación tras un registro exitoso de residente.
     */
    public function registroExitoso() {
        if (Auth::check()) {
            $this->redirectByRole();
            return;
        }

        $this->render('auth/registro_exitoso', [
            'showNav' => false,
            'title'   => 'Registro Exitoso - Condominio Digital'
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
