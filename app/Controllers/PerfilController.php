<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\PersonasModel;
use App\Models\SolicitudesModel;
use App\Models\UsuariosModel;
use App\Models\VehiculosModel;
use App\Models\EstacionamientosModel;

class PerfilController extends Controller {

    /**
     * Muestra el perfil personal del usuario/residente y el historial de solicitudes.
     */
    public function verPerfil() {
        Auth::requireLogin();

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? null;

        $persona = null;
        $usuarioAdmin = null;
        $solicitudes = [];
        $vehiculos = [];
        $tieneEstacionamiento = false;

        if (in_array(($user['role'] ?? ''), ['admin', 'auditor'], true)) {
            $usuariosModel = new UsuariosModel();
            $rawAdmin = $usuariosModel->getById((int)$user['id']);
            if ($rawAdmin) {
                $permitidosAdmin = ['id', 'nombre_completo', 'usuario', 'email', 'rol', 'telefono', 'cedula', 'two_factor_enabled'];
                $usuarioAdmin = array_intersect_key($rawAdmin, array_flip($permitidosAdmin));
            }
        } elseif ($personaId) {
            $personasModel = new PersonasModel();
            $rawPersona = $personasModel->getActiveById($personaId);
            if ($rawPersona) {
                $permitidosPersona = ['id', 'unidad_id', 'nombre', 'apellido', 'email', 'telefono', 'cedula', 'two_factor_enabled'];
                $persona = array_intersect_key($rawPersona, array_flip($permitidosPersona));
            }

            $solicitudesModel = new SolicitudesModel();
            $solicitudesModel->eliminarExpiradas((int)$personaId);
            $solicitudes = $solicitudesModel->obtenerPorPersona($personaId);

            if ($persona && !empty($persona['unidad_id'])) {
                $vehiculos = (new VehiculosModel())->obtenerPorUnidad((int)$persona['unidad_id']);
                $tieneEstacionamiento = !empty((new EstacionamientosModel())->obtenerPorUnidad((int)$persona['unidad_id']));
            }
        }

        $this->render('perfil/index', [
            'user'         => $user,
            'persona'      => $persona,
            'usuarioAdmin' => $usuarioAdmin,
            'solicitudes'  => $solicitudes,
            'vehiculos'    => $vehiculos,
            'tieneEstacionamiento' => $tieneEstacionamiento,
            'title'        => 'Mi Perfil'
        ]);
    }

    /**
     * Procesa la actualización de datos personales (directa para admin, vía solicitud para residentes).
     */
    public function solicitarCambio() {
        Auth::requireLogin();

        $user = Auth::user();
        $role = Auth::role();

        if ($role === 'auditor') {
            Flash::set('danger', 'El rol de auditor no envía solicitudes de cambio de datos desde este perfil.');
            $this->redirect('/perfil');
            return;
        }

        // G1-05: Max 10 change requests per hour per user
        if (!\App\Core\RateLimiter::attempt('solicitud_cambio_' . Auth::id(), 10, 3600)) {
            Flash::set('danger', 'Ha excedido el límite de 10 solicitudes de cambio por hora.');
            $this->redirect('/perfil');
            return;
        }

        // Manejo para Administradores: Actualización directa de su perfil
        if ($role === 'admin') {
            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $cedula   = trim($_POST['cedula'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $password = trim($_POST['password'] ?? '');

            $email = strtolower(trim($email));
            if (empty($email) || !validarEmail($email)) {
                Flash::set('danger', 'Debe proporcionar un correo electrónico válido.');
                $this->redirect('/perfil');
                return;
            }

            if (empty($nombre)) {
                Flash::set('danger', 'El nombre completo es obligatorio.');
                $this->redirect('/perfil');
                return;
            }

            // Validación de Cédula (opcional, pero si se provee debe cumplir formato)
            if (!empty($cedula)) {
                if (!validarCedula($cedula)) {
                    Flash::set('danger', 'El formato del número de cédula no es válido (debe tener entre 5 y 8 dígitos).');
                    $this->redirect('/perfil');
                    return;
                }
                $cedula = normalizarCedula($cedula);
            } else {
                $cedula = null;
            }

            // Validación de Teléfono (opcional, pero si se provee debe cumplir formato venezolano)
            if (!empty($telefono)) {
                $telLimpio = preg_replace('/[^0-9]/', '', $telefono);
                if (!validarTelefono($telLimpio)) {
                    Flash::set('danger', 'El formato del teléfono no es válido (use una operadora válida como 0412, 0414, 0424, 0416 o 0426).');
                    $this->redirect('/perfil');
                    return;
                }
                $telefono = $telLimpio;
            } else {
                $telefono = null;
            }

            if (!empty($password)) {
                if (strlen($password) < 8 || !validarPassword($password)) {
                    Flash::set('danger', 'La nueva contraseña debe tener al menos 8 caracteres y contener al menos una letra y un número.');
                    $this->redirect('/perfil');
                    return;
                }
            }

            $usuariosModel = new UsuariosModel();

            // Verificar unicidad de correo electrónico
            if ($usuariosModel->emailExists($email, (int)$user['id'])) {
                Flash::set('danger', 'El correo electrónico ya se encuentra registrado por otro usuario.');
                $this->redirect('/perfil');
                return;
            }

            // Verificar unicidad de cédula si fue suministrada
            if (!empty($cedula) && $usuariosModel->cedulaExisteEnOtroUsuario($cedula, (int)$user['id'])) {
                Flash::set('danger', 'El número de cédula ya se encuentra registrado por otro usuario.');
                $this->redirect('/perfil');
                return;
            }

            try {
                $exito = $usuariosModel->actualizarPerfil((int)$user['id'], [
                    'nombre_completo' => $nombre,
                    'email'           => $email,
                    'cedula'          => $cedula,
                    'telefono'        => $telefono,
                    'password'        => !empty($password) ? $password : null
                ]);

                if ($exito) {
                    $_SESSION['auth_user']['name'] = $nombre;
                    $_SESSION['auth_user']['email'] = $email;
                    Flash::set('success', 'Sus datos de administrador han sido actualizados correctamente.');
                } else {
                    Flash::set('danger', 'Error al actualizar sus datos.');
                }
            } catch (\Exception $e) {
                error_log("[PERFIL] Error al actualizar datos de admin: " . $e->getMessage());
                Flash::set('danger', 'Error al actualizar sus datos.');
            }

            $this->redirect('/perfil');
            return;
        }

        // Manejo para Residentes: Creación de solicitud formal para revisión
        $personaId = $user['persona_id'] ?? 0;

        if ($personaId <= 0) {
            Flash::set('danger', 'Su usuario no posee un registro de persona asociado.');
            $this->redirect('/perfil');
            return;
        }

        $telCodigo = trim($_POST['telefono_codigo'] ?? '');
        $telNumero = trim($_POST['telefono_numero'] ?? '');
        $telefono  = !empty($telNumero) ? ($telCodigo . $telNumero) : trim($_POST['telefono'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!empty($telNumero) && (strlen($telNumero) !== 7 || !ctype_digit($telNumero))) {
            Flash::set('danger', 'El número de teléfono debe contener exactamente 7 dígitos numéricos tras el código de operadora.');
            $this->redirect('/perfil');
            return;
        }

        if (!empty($telefono) && !validarTelefono($telefono)) {
            Flash::set('danger', 'El formato del teléfono no es válido (use una operadora válida como 0412, 0422, 0414, 0424, 0416 o 0426).');
            $this->redirect('/perfil');
            return;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'El formato del correo electrónico no es válido.');
            $this->redirect('/perfil');
            return;
        }

        try {
            $solicitudesModel = new SolicitudesModel();
            $solicitudesModel->eliminarExpiradas($personaId);
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
     * Crea una solicitud de registro/actualización de vehículo para revisión del administrador.
     */
    public function solicitarCambioVehiculo() {
        Auth::requireLogin();

        if (Auth::role() !== 'residente') {
            Flash::set('danger', 'Solo los residentes pueden solicitar cambios de vehículos desde este perfil.');
            $this->redirect('/perfil');
            return;
        }

        if (!\App\Core\RateLimiter::attempt('solicitud_vehiculo_' . Auth::id(), 10, 3600)) {
            Flash::set('danger', 'Ha excedido el límite de 10 solicitudes de vehículo por hora.');
            $this->redirect('/perfil');
            return;
        }

        $personaId = (int)(Auth::user()['persona_id'] ?? 0);
        if ($personaId <= 0) {
            Flash::set('danger', 'Su usuario no posee un registro de persona asociado.');
            $this->redirect('/perfil');
            return;
        }

        $persona = (new PersonasModel())->getActiveById($personaId);
        if (!$persona || empty($persona['unidad_id'])) {
            Flash::set('danger', 'Su usuario no tiene una unidad asociada para registrar el vehículo.');
            $this->redirect('/perfil');
            return;
        }
        $unidadId = (int)$persona['unidad_id'];

        $accion = (($_POST['accion_vehiculo'] ?? 'crear') === 'actualizar') ? 'actualizar' : 'crear';
        $vehiculoId = intval($_POST['vehiculo_id'] ?? 0);
        $vehiculosModel = new VehiculosModel();

        if ($accion === 'crear' && empty((new EstacionamientosModel())->obtenerPorUnidad($unidadId))) {
            Flash::set('danger', 'Su unidad no tiene una plaza de estacionamiento asignada. La administración debe asignarle una plaza antes de registrar un vehículo.');
            $this->redirect('/perfil');
            return;
        }

        if ($accion === 'actualizar') {
            $vehiculo = $vehiculosModel->obtenerPorId($vehiculoId);
            if (!$vehiculo || !empty($vehiculo['deleted_at']) || (int)$vehiculo['unidad_id'] !== $unidadId) {
                Flash::set('danger', 'El vehículo seleccionado no existe o no pertenece a su unidad.');
                $this->redirect('/perfil');
                return;
            }
        }

        $tipoVehiculo = trim((string)($_POST['tipo_vehiculo'] ?? ''));
        $color = trim((string)($_POST['color'] ?? ''));
        $marca = trim((string)($_POST['marca'] ?? ''));

        if (!array_key_exists($tipoVehiculo, VehiculosModel::TIPOS_VEHICULO)) {
            Flash::set('danger', 'El tipo de vehículo es obligatorio y debe ser Pequeño, Camioneta mediano o Camión tipo F-350.');
            $this->redirect('/perfil');
            return;
        }

        if ($color === '') {
            Flash::set('danger', 'El color del vehículo es obligatorio.');
            $this->redirect('/perfil');
            return;
        }

        try {
            $solicitudesModel = new SolicitudesModel();
            $solicitudesModel->eliminarExpiradas($personaId);
            $solicitudesModel->crearSolicitudVehiculo($personaId, [
                'accion_vehiculo' => $accion,
                'vehiculo_id'     => $vehiculoId,
                'vehiculo_tipo'   => $tipoVehiculo,
                'vehiculo_marca'  => $marca,
                'vehiculo_color'  => $color,
            ]);

            Flash::set('success', 'Su solicitud de vehículo ha sido enviada a la administración para revisión.');
        } catch (\Exception $e) {
            error_log('[PERFIL] Error enviar solicitud vehículo: ' . $e->getMessage());
            Flash::set('danger', 'No se pudo enviar la solicitud: ' . $e->getMessage());
        }

        $this->redirect('/perfil');
    }

    /**
     * @deprecated Módulo de solicitudes de datos deprecado. La gestión de residentes se realiza ahora en /admin/usuarios.
     */
    public function listarSolicitudes() {
        Auth::requireRole('admin');
        Flash::set('info', 'El módulo de Solicitudes de Datos ha sido deprecado. La gestión de residentes se realiza desde Gestión de Usuarios.');
        $this->redirect('/admin/usuarios');
    }

    /**
     * @deprecated Módulo de solicitudes de datos deprecado.
     */
    public function procesarSolicitud() {
        Auth::requireRole('admin');
        $this->redirect('/admin/usuarios');
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
