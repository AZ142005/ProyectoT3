<?php
namespace App\Models;

use PDO;

class PersonasModel extends BaseModel {
    protected string $table = 'personas';

    public function getActiveByCedula($cedula) {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE cedula = :cedula AND estado = 1");
        $stmt->execute(['cedula' => $cedula]);
        return $stmt->fetch();
    }

    public function getActiveById($id) {
        return parent::getById($id);
    }

    public function getResidenteDetails($residente_id) {
        $sql = "
            SELECT p.*, u.numero as unidad_numero, e.nombre as torre 
            FROM personas p
            INNER JOIN unidades u ON p.unidad_id = u.id 
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE p.id = :id AND p.estado = 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $residente_id]);
        return $stmt->fetch();
    }

    public function getActiveByEmail($email) {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE LOWER(email) = LOWER(:email) AND estado = 1 LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Obtiene los residentes asociados a una unidad.
     */
    public function getByUnidadId(int $unidadId, bool $soloActivos = true): array {
        $sql = "SELECT * FROM personas WHERE unidad_id = :unidad_id";
        if ($soloActivos) {
            $sql .= " AND estado = 1";
        }
        $sql .= " ORDER BY CASE WHEN tipo IN ('propietario', 'ambos') THEN 1 ELSE 2 END, apellido ASC, nombre ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna el residente principal de una unidad: el primero de los activos
     * según el orden de getByUnidadId (propietario/ambos primero).
     * Null si la unidad no tiene personas activas.
     */
    public function getPrincipalByUnidadId(int $unidadId): ?array {
        $residentes = $this->getByUnidadId($unidadId);
        return $residentes[0] ?? null;
    }

    /**
     * Inserta un nuevo residente validando tipo y unidad activa.
     */
    public function createResidente(array $data): int|false {
        $tipo = $data['tipo'] ?? 'propietario';
        if (!in_array($tipo, ['propietario', 'inquilino', 'ambos'], true)) {
            throw new \InvalidArgumentException('Tipo de residente no válido.');
        }

        $unidadId = intval($data['unidad_id'] ?? 0);
        $stmtU = $this->db()->prepare("SELECT id FROM unidades WHERE id = :id AND estado = 1");
        $stmtU->execute(['id' => $unidadId]);
        if (!$stmtU->fetchColumn()) {
            throw new \InvalidArgumentException('La unidad seleccionada no existe o se encuentra inactiva.');
        }

        $sql = "INSERT INTO personas (cedula, nombre, apellido, telefono, email, unidad_id, tipo, estado) 
                VALUES (:cedula, :nombre, :apellido, :telefono, :email, :unidad_id, :tipo, 1)";
        $stmt = $this->db()->prepare($sql);
        $ok = $stmt->execute([
            'cedula'    => $data['cedula'],
            'nombre'    => $data['nombre'],
            'apellido'  => $data['apellido'],
            'telefono'  => !empty($data['telefono']) ? $data['telefono'] : null,
            'email'     => !empty($data['email']) ? $data['email'] : null,
            'unidad_id' => $unidadId,
            'tipo'      => $tipo
        ]);
        return $ok ? (int)$this->db()->lastInsertId() : false;
    }

    /**
     * Actualiza o reactiva los datos de un residente.
     */
    public function updateResidente(int $id, array $data): bool {
        $tipo = $data['tipo'] ?? 'propietario';
        if (!in_array($tipo, ['propietario', 'inquilino', 'ambos'], true)) {
            throw new \InvalidArgumentException('Tipo de residente no válido.');
        }

        $unidadId = intval($data['unidad_id'] ?? 0);
        $stmtU = $this->db()->prepare("SELECT id FROM unidades WHERE id = :id AND estado = 1");
        $stmtU->execute(['id' => $unidadId]);
        if (!$stmtU->fetchColumn()) {
            throw new \InvalidArgumentException('La unidad seleccionada no existe o se encuentra inactiva.');
        }

        $sql = "UPDATE personas SET nombre = :nombre, apellido = :apellido, telefono = :telefono, 
                       email = :email, unidad_id = :unidad_id, tipo = :tipo, estado = 1 
                WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'nombre'    => $data['nombre'],
            'apellido'  => $data['apellido'],
            'telefono'  => !empty($data['telefono']) ? $data['telefono'] : null,
            'email'     => !empty($data['email']) ? $data['email'] : null,
            'unidad_id' => $unidadId,
            'tipo'      => $tipo,
            'id'        => $id
        ]);
    }

    /**
     * Desvinculación lógica de un residente.
     */
    public function desvincularResidente(int $id): bool {
        $stmt = $this->db()->prepare("UPDATE personas SET unidad_id = NULL, estado = 0 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Consulta un registro por cédula.
     */
    public function getByCedula(string $cedula): ?array {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE cedula = :cedula");
        $stmt->execute(['cedula' => $cedula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Consulta un registro por correo electrónico (cualquier estado).
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->db()->prepare("SELECT * FROM personas WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica si un correo electrónico ya está en uso por otra persona activa.
     */
    public function emailExistsActive(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM personas WHERE LOWER(email) = LOWER(:email) AND estado = 1";
        $params = ['email' => $email];
        if ($excludeId && $excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function register($cedula, $email, $hashedPassword) {
        $stmt = $this->db()->prepare("UPDATE personas SET email = :email, password = :password WHERE cedula = :cedula AND estado = 1");
        return $stmt->execute([
            'email'    => $email,
            'password' => $hashedPassword,
            'cedula'   => $cedula
        ]);
    }

    public function emailExists($email, ?int $excludeId = null) {
        return $this->exists('personas', 'email', $email, $excludeId);
    }

    /**
     * Incrementa el contador de intentos fallidos de login.
     * A los 5 intentos, bloquea la cuenta por 30 minutos.
     */
    public function incrementarIntentosFallidos(int $personaId): void {
        $stmt = $this->db()->prepare(
            "UPDATE personas SET intentos_fallidos = COALESCE(intentos_fallidos, 0) + 1,
             bloqueado_hasta = CASE WHEN COALESCE(intentos_fallidos, 0) + 1 >= 5
             THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE) ELSE bloqueado_hasta END
             WHERE id = :id"
        );
        $stmt->execute(['id' => $personaId]);
    }

    /**
     * Resetea el contador de intentos fallidos tras login exitoso.
     */
    public function resetIntentosFallidos(int $personaId): void {
        $stmt = $this->db()->prepare(
            "UPDATE personas SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id"
        );
        $stmt->execute(['id' => $personaId]);
    }

    /**
     * Verifica si la cuenta está bloqueada por intentos fallidos.
     */
    public function estaBloqueado(int $personaId): bool {
        $stmt = $this->db()->prepare(
            "SELECT bloqueado_hasta FROM personas WHERE id = :id"
        );
        $stmt->execute(['id' => $personaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['bloqueado_hasta'])) {
            return false;
        }
        return strtotime($row['bloqueado_hasta']) > time();
    }

    /**
     * Reinicia la contraseña de un residente y restablece bloqueos.
     *
     * @param int $personaId
     * @param string $nuevoHash
     * @return bool
     */
    public function reiniciarPassword(int $personaId, string $nuevoHash): bool {
        $stmt = $this->db()->prepare(
            "UPDATE personas 
             SET password = :password, intentos_fallidos = 0, bloqueado_hasta = NULL 
             WHERE id = :id"
        );
        return $stmt->execute([
            'password' => $nuevoHash,
            'id'       => $personaId
        ]);
    }

    /**
     * Actualiza el número de teléfono de una persona/residente.
     *
     * @param int $personaId
     * @param string|null $telefono
     * @return bool
     */
    public function actualizarTelefono(int $personaId, ?string $telefono): bool {
        $stmt = $this->db()->prepare("UPDATE personas SET telefono = :telefono WHERE id = :id");
        return $stmt->execute([
            'telefono' => !empty($telefono) ? $telefono : null,
            'id'       => $personaId
        ]);
    }

    /**
     * Actualiza datos de contacto de una persona/residente (teléfono y opcionalmente correo).
     *
     * @param int $personaId
     * @param string|null $telefono
     * @param string|null $email
     * @return bool
     */
    public function actualizarContacto(int $personaId, ?string $telefono, ?string $email = null): bool {
        $fields = ['telefono = :telefono'];
        $params = [
            'telefono' => !empty($telefono) ? $telefono : null,
            'id'       => $personaId
        ];
        if ($email !== null) {
            $fields[] = 'email = :email';
            $params['email'] = !empty($email) ? trim($email) : null;
        }
        $sql = "UPDATE personas SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina lógicamente a un residente preservando la integridad histórica y contable.
     * Desvincula la unidad (propietario_id = NULL) y desactiva credenciales (estado = 0, unidad_id = NULL, password = NULL).
     *
     * @param int $personaId
     * @return bool
     */
    public function eliminarResidente(int $personaId): bool {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // 1. Desvincular de unidades donde figure como propietario
            $stmtU = $db->prepare("UPDATE unidades SET propietario_id = NULL WHERE propietario_id = :id");
            $stmtU->execute(['id' => $personaId]);

            // 2. Desvincular como residente y desactivar credenciales
            $stmtP = $db->prepare("
                UPDATE personas 
                SET unidad_id = NULL, 
                    estado = 0, 
                    password = NULL, 
                    bloqueado_hasta = NULL, 
                    intentos_fallidos = 0 
                WHERE id = :id
            ");
            $stmtP->execute(['id' => $personaId]);

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[PersonasModel::eliminarResidente] Error: " . $e->getMessage());
            return false;
        }
    }
}