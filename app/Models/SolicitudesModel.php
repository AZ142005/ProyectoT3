<?php
namespace App\Models;

use PDO;
use Exception;

class SolicitudesModel extends BaseModel {

    protected string $table = 'solicitudes_cambio_datos';

    /**
     * Registra una nueva solicitud de cambio de datos personales enviada por un residente.
     *
     * @param int $personaId
     * @param array $datosNuevos
     * @return int ID de la solicitud creada
     */
    public function crearSolicitud(int $personaId, array $datosNuevos): int {
        // Filtrado por lista blanca estricta
        $clavesPermitidas = [
            'telefono', 'email', 'direccion',
            'tipo_solicitud', 'accion_vehiculo', 'vehiculo_id',
            'vehiculo_tipo', 'vehiculo_marca', 'vehiculo_color',
        ];
        $payloadValido = [];

        foreach ($clavesPermitidas as $clave) {
            if (isset($datosNuevos[$clave]) && trim((string)$datosNuevos[$clave]) !== '') {
                $payloadValido[$clave] = trim((string)$datosNuevos[$clave]);
            }
        }

        if (empty($payloadValido)) {
            throw new Exception("Debe proporcionar al menos un campo válido para actualizar (teléfono, email, dirección o vehículo).");
        }

        // G1-O5: Deduplicate — sort keys before encoding for consistent comparison
        ksort($payloadValido);
        $normalizedJson = json_encode($payloadValido, JSON_UNESCAPED_UNICODE);

        $stmtCheck = $this->db()->prepare(
            "SELECT id, estado, fecha_respuesta FROM solicitudes_cambio_datos
             WHERE persona_id = :pid AND datos_nuevos_json = :json
             ORDER BY id DESC LIMIT 1"
        );
        $stmtCheck->execute(['pid' => $personaId, 'json' => $normalizedJson]);
        $existing = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['estado'] === 'pendiente') {
                throw new Exception("Ya existe una solicitud pendiente con los mismos campos. Espere a que sea procesada.");
            }
            // Allow retry after 24 hours if the previous request was rejected
            if ($existing['estado'] === 'rechazado' && $existing['fecha_respuesta']) {
                $rechazada = strtotime($existing['fecha_respuesta']);
                if ((time() - $rechazada) < 86400) {
                    throw new Exception("Esta solicitud fue evaluada hace menos de 24 horas y no fue aprobada. Espere antes de reintentar.");
                }
            }
            // 'aprobado' or rejected > 24h → allow new request
        }

        $json = $normalizedJson;
        if (mb_strlen($json, '8bit') > 2048) {
            throw new Exception("El tamaño de la solicitud excede el límite máximo permitido (2 KB).");
        }

        $db = $this->db();
        $stmt = $db->prepare("
            INSERT INTO solicitudes_cambio_datos 
            (persona_id, datos_nuevos_json, estado) 
            VALUES (:persona_id, :json, 'pendiente')
        ");
        $stmt->execute([
            'persona_id' => $personaId,
            'json'       => $json
        ]);

        return intval($db->lastInsertId());
    }

    /**
     * Crea una solicitud de registro o actualización de vehículo para un residente.
     *
     * @param int   $personaId
     * @param array $datos accion_vehiculo (crear|actualizar), vehiculo_id, vehiculo_tipo,
     *                     vehiculo_marca, vehiculo_color
     * @return int ID de la solicitud
     */
    public function crearSolicitudVehiculo(int $personaId, array $datos): int {
        $accion = (($datos['accion_vehiculo'] ?? 'crear') === 'actualizar') ? 'actualizar' : 'crear';

        $payload = [
            'tipo_solicitud'  => 'vehiculo',
            'accion_vehiculo' => $accion,
            'vehiculo_tipo'   => trim((string)($datos['vehiculo_tipo'] ?? '')),
            'vehiculo_marca'  => trim((string)($datos['vehiculo_marca'] ?? '')),
            'vehiculo_color'  => trim((string)($datos['vehiculo_color'] ?? '')),
        ];

        if ($accion === 'actualizar') {
            $payload['vehiculo_id'] = (string)intval($datos['vehiculo_id'] ?? 0);
        }

        return $this->crearSolicitud($personaId, $payload);
    }

    /**
     * Obtiene el listado de solicitudes para administración.
     *
     * @param int $pagina
     * @param int $porPagina
     * @param string|null $estado 'pendiente', 'aprobado', 'rechazado' o null para todas
     */
    public function obtenerTodasAdmin(int $pagina = 1, int $porPagina = 15, ?string $estado = null): array {
        $baseSql = "
            SELECT s.*, CONCAT(p.nombre, ' ', p.apellido) AS residente_nombre, p.cedula AS residente_cedula,
                   p.email AS residente_email_actual, p.telefono AS residente_telefono_actual,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM solicitudes_cambio_datos s
            INNER JOIN personas p ON s.persona_id = p.id
            LEFT JOIN unidades u ON u.propietario_id = p.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
        ";

        $countSql = "SELECT COUNT(*) AS total FROM solicitudes_cambio_datos s";
        $params = [];

        if (!empty($estado) && in_array($estado, ['pendiente', 'aprobado', 'rechazado'], true)) {
            $baseSql  .= " WHERE s.estado = :estado";
            $countSql .= " WHERE s.estado = :estado";
            $params['estado'] = $estado;
        }

        return $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 's.fecha_solicitud DESC');
    }

    /**
     * Cuenta cuántas solicitudes de cambio de datos están pendientes.
     */
    public function contarPendientes(): int {
        return (int)$this->db()->query("SELECT COUNT(*) FROM solicitudes_cambio_datos WHERE estado = 'pendiente'")->fetchColumn();
    }

    /**
     * Obtiene las solicitudes enviadas por un residente específico.
     */
    public function obtenerPorPersona(int $personaId): array {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM solicitudes_cambio_datos WHERE persona_id = :pid ORDER BY fecha_solicitud DESC");
        $stmt->execute(['pid' => $personaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina las solicitudes vencidas: pendientes con más de 24 horas desde su
     * envío, y rechazadas con más de 24 horas desde su respuesta (o desde su
     * envío si no hay fecha de respuesta). Las aprobadas se conservan siempre.
     * Usa el reloj de MySQL porque ambas fechas las escribe MySQL.
     *
     * @param int|null $personaId Si se indica, limita la limpieza a esa persona
     * @return int Número de solicitudes eliminadas
     */
    public function eliminarExpiradas(?int $personaId = null): int {
        $sql = "DELETE FROM solicitudes_cambio_datos
                WHERE (
                    (estado = 'pendiente' AND fecha_solicitud <= DATE_SUB(NOW(), INTERVAL 1 DAY))
                    OR
                    (estado = 'rechazado' AND COALESCE(fecha_respuesta, fecha_solicitud) <= DATE_SUB(NOW(), INTERVAL 1 DAY))
                )";
        $params = [];
        if ($personaId !== null) {
            $sql .= " AND persona_id = :pid";
            $params['pid'] = $personaId;
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Procesa (Aprobar/Rechazar) una solicitud con revalidación estricta de formato y unicidad.
     */
    public function procesarSolicitud(int $solicitudId, string $nuevoEstado, ?string $motivoAdmin, int $adminId): bool {
        $estadosValidos = ['aprobado', 'rechazado'];
        if (!in_array($nuevoEstado, $estadosValidos)) {
            throw new Exception("Estado de resolución no válido.");
        }

        $db = $this->db();
        $datos = [];
        $esVehiculo = false;
        $personaId = 0;

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM solicitudes_cambio_datos WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $solicitudId]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
                throw new Exception("La solicitud ya ha sido procesada o no existe.");
            }

            $personaId = intval($solicitud['persona_id']);
            $decoded = json_decode((string)$solicitud['datos_nuevos_json'], true);
            $datos = is_array($decoded) ? $decoded : [];
            $esVehiculo = (($datos['tipo_solicitud'] ?? '') === 'vehiculo');

            if ($nuevoEstado === 'aprobado') {
                if (!is_array($decoded)) {
                    throw new Exception("Payload JSON de solicitud corrupto.");
                }

                if ($esVehiculo) {
                    $this->procesarSolicitudVehiculo($personaId, $datos);
                } else {
                    $this->aplicarCambioDatosPersona($personaId, $datos);
                }
            }

            $stmtStatus = $db->prepare("
                UPDATE solicitudes_cambio_datos 
                SET estado = :estado, motivo_admin = :motivo, admin_id = :admin_id, fecha_respuesta = NOW() 
                WHERE id = :id
            ");
            $stmtStatus->execute([
                'estado'   => $nuevoEstado,
                'motivo'   => !empty($motivoAdmin) ? trim($motivoAdmin) : null,
                'admin_id' => $adminId,
                'id'       => $solicitudId
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // La notificación se registra después del commit para no revertir la
        // aprobación si el servicio de notificaciones falla.
        try {
            $notifService = new \App\Services\NotificationService();
            if ($nuevoEstado === 'aprobado') {
                $msg = $esVehiculo
                    ? "Su solicitud de registro/actualización de vehículo ha sido APROBADA exitosamente."
                    : "Su solicitud de actualización de datos personales ha sido APROBADA exitosamente.";
                $titulo = $esVehiculo ? "Solicitud de Vehículo Aprobada" : "Solicitud de Datos Aprobada";
            } else {
                $msg = $esVehiculo
                    ? "Su solicitud de vehículo no fue aprobada. Motivo: " . $motivoAdmin
                    : "Su solicitud de actualización de datos no fue aprobada. Motivo: " . $motivoAdmin;
                $titulo = $esVehiculo ? "Solicitud de Vehículo No Aprobada" : "Solicitud de Datos No Aprobada";
            }

            $notifService->registrarNotificacionResidente(
                $personaId,
                $titulo,
                $msg,
                ($nuevoEstado === 'aprobado' ? 'success' : 'danger'),
                "/perfil"
            );
        } catch (\Throwable $e) {
            error_log('[SOLICITUDES] No se pudo registrar la notificación: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Aplica los cambios de datos personales (teléfono y email) de una solicitud aprobada.
     */
    private function aplicarCambioDatosPersona(int $personaId, array $datos): void {
        if (!empty($datos['email'])) {
            $nuevoEmail = trim((string)$datos['email']);
            if (!filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($nuevoEmail) > 150) {
                throw new Exception("El email proporcionado ('{$nuevoEmail}') no posee un formato RFC válido.");
            }

            $personasModel = new PersonasModel();
            if ($personasModel->emailExists($nuevoEmail, $personaId)) {
                throw new Exception("El correo electrónico '{$nuevoEmail}' ya se encuentra registrado por otro usuario.");
            }
        }

        $camposUpdate = [];
        $params = ['pid' => $personaId];

        if (!empty($datos['telefono'])) {
            $camposUpdate[] = "telefono = :telefono";
            $params['telefono'] = trim((string)$datos['telefono']);
        }
        if (!empty($datos['email'])) {
            $camposUpdate[] = "email = :email";
            $params['email'] = trim((string)$datos['email']);
        }

        if (!empty($camposUpdate)) {
            $sqlUpdate = "UPDATE personas SET " . implode(', ', $camposUpdate) . " WHERE id = :pid";
            $stmtPersonas = $this->db()->prepare($sqlUpdate);
            $stmtPersonas->execute($params);
        }
    }

    /**
     * Aplica una solicitud aprobada de registro o actualización de vehículo.
     */
    private function procesarSolicitudVehiculo(int $personaId, array $datos): void {
        $persona = (new PersonasModel())->getById($personaId);
        if (!$persona || empty($persona['unidad_id'])) {
            throw new Exception("El residente no tiene una unidad asociada para registrar el vehículo.");
        }
        $unidadId = (int)$persona['unidad_id'];

        $tipoVehiculo = trim((string)($datos['vehiculo_tipo'] ?? ''));
        $marca = trim((string)($datos['vehiculo_marca'] ?? ''));
        $color = trim((string)($datos['vehiculo_color'] ?? ''));

        if (!array_key_exists($tipoVehiculo, VehiculosModel::TIPOS_VEHICULO)) {
            throw new Exception("La solicitud de vehículo está incompleta (el tipo de vehículo es obligatorio y debe ser válido).");
        }
        if ($color === '') {
            throw new Exception("La solicitud de vehículo está incompleta (el color es obligatorio).");
        }

        $vehiculosModel = new VehiculosModel();
        $accion = (($datos['accion_vehiculo'] ?? 'crear') === 'actualizar') ? 'actualizar' : 'crear';

        if ($accion === 'crear') {
            $puestos = (new EstacionamientosModel())->obtenerPorUnidad($unidadId);
            if (empty($puestos)) {
                throw new Exception("La unidad no tiene una plaza de estacionamiento asignada. La administración debe asignar una plaza antes de aprobar el registro del vehículo.");
            }
            $estacionamientoId = (int)$puestos[0]['id'];

            $vehiculosModel->crearVehiculo([
                'unidad_id'          => $unidadId,
                'persona_id'         => $personaId,
                'estacionamiento_id' => $estacionamientoId,
                'tipo_vehiculo'      => $tipoVehiculo,
                'marca'              => $marca,
                'color'              => $color,
            ]);
            return;
        }

        $vehiculoId = (int)($datos['vehiculo_id'] ?? 0);
        $vehiculo = $vehiculosModel->obtenerPorId($vehiculoId);
        if (!$vehiculo || !empty($vehiculo['deleted_at']) || (int)$vehiculo['unidad_id'] !== $unidadId) {
            throw new Exception("El vehículo indicado no existe o no pertenece a su unidad.");
        }

        $actualizado = $vehiculosModel->actualizarVehiculo($vehiculoId, [
            'tipo_vehiculo' => $tipoVehiculo,
            'marca'         => $marca,
            'color'         => $color,
        ]);

        if (!$actualizado) {
            throw new Exception("No se pudo actualizar el vehículo.");
        }
    }
}
