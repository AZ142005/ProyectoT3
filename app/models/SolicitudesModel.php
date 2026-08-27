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
        $clavesPermitidas = ['telefono', 'email', 'direccion', 'vehiculo_placa'];
        $payloadValido = [];

        foreach ($clavesPermitidas as $clave) {
            if (isset($datosNuevos[$clave]) && trim($datosNuevos[$clave]) !== '') {
                $payloadValido[$clave] = trim($datosNuevos[$clave]);
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
                    throw new Exception("Esta solicitud fue rechazada hace menos de 24 horas. Espere antes de reintentar.");
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
     * Obtiene el listado de solicitudes para administración.
     */
    public function obtenerTodasAdmin(int $pagina = 1, int $porPagina = 15): array {
        $baseSql = "
            SELECT s.*, CONCAT(p.nombre, ' ', p.apellido) AS residente_nombre, p.cedula AS residente_cedula,
                   p.email AS residente_email_actual, p.telefono AS residente_telefono_actual,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM solicitudes_cambio_datos s
            INNER JOIN personas p ON s.persona_id = p.id
            LEFT JOIN unidades u ON u.propietario_id = p.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
        ";

        $countSql = "SELECT COUNT(*) AS total FROM solicitudes_cambio_datos";

        return $this->paginate($baseSql, $countSql, [], $pagina, $porPagina, 's.fecha_solicitud DESC');
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
     * Procesa (Aprobar/Rechazar) una solicitud con revalidación estricta de formato y unicidad.
     */
    public function procesarSolicitud(int $solicitudId, string $nuevoEstado, ?string $motivoAdmin, int $adminId): bool {
        $estadosValidos = ['aprobado', 'rechazado'];
        if (!in_array($nuevoEstado, $estadosValidos)) {
            throw new Exception("Estado de resolución no válido.");
        }

        $db = $this->db();

        $stmt = $db->prepare("SELECT * FROM solicitudes_cambio_datos WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $solicitudId]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
            throw new Exception("La solicitud ya ha sido procesada o no existe.");
        }

        $personaId = intval($solicitud['persona_id']);

        if ($nuevoEstado === 'aprobado') {
            $datos = json_decode($solicitud['datos_nuevos_json'], true);
            if (!is_array($datos)) {
                throw new Exception("Payload JSON de solicitud corrupto.");
            }

            // Revalidación estricta de Email
            if (!empty($datos['email'])) {
                $nuevoEmail = trim($datos['email']);
                if (!filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($nuevoEmail) > 150) {
                    throw new Exception("El email proporcionado ('{$nuevoEmail}') no posee un formato RFC válido.");
                }

                $personasModel = new PersonasModel();
                if ($personasModel->emailExists($nuevoEmail, $personaId)) {
                    throw new Exception("El correo electrónico '{$nuevoEmail}' ya se encuentra registrado por otro usuario.");
                }
            }

            // Actualización de campos en personas
            $camposUpdate = [];
            $params = ['pid' => $personaId];

            if (!empty($datos['telefono'])) {
                $camposUpdate[] = "telefono = :telefono";
                $params['telefono'] = trim($datos['telefono']);
            }
            if (!empty($datos['email'])) {
                $camposUpdate[] = "email = :email";
                $params['email'] = trim($datos['email']);
            }

            if (!empty($camposUpdate)) {
                $sqlUpdate = "UPDATE personas SET " . implode(', ', $camposUpdate) . " WHERE id = :pid";
                $stmtPersonas = $db->prepare($sqlUpdate);
                $stmtPersonas->execute($params);
            }
        }

        // Actualizar el estado de la solicitud
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

        // Registrar notificación en la bandeja del residente
        $notifService = new \App\Services\NotificationService();
        $msg = ($nuevoEstado === 'aprobado')
            ? "Su solicitud de actualización de datos personales ha sido APROBADA exitosamente."
            : "Su solicitud de actualización de datos ha sido RECHAZADA. Motivo: " . $motivoAdmin;
        
        $notifService->registrarNotificacionResidente($personaId, "Solicitud de Datos " . ucfirst($nuevoEstado), $msg, ($nuevoEstado === 'aprobado' ? 'success' : 'danger'), "/perfil");

        return true;
    }
}
