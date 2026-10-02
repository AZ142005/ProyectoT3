<?php
namespace App\Models;

class UnidadesModel extends BaseModel {
    protected string $table = 'unidades';

    public function getActivas(int $limite = 500) {
        $sql = "
            SELECT u.id, u.numero, u.cuota_mensual, u.edificio_id, e.nombre as edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.estado = 1
            ORDER BY e.nombre ASC, u.numero ASC
            LIMIT {$limite}
        ";
        return $this->db()->query($sql)->fetchAll();
    }

    /**
     * Obtiene los apartamentos actualmente disponibles para registro.
     * Criterio: Unidad activa, sin propietario asignado, sin residentes activos
     * y sin solicitudes de registro en estado 'pendiente'.
     *
     * @param int $limite
     * @return array
     */
    public function getDisponibles(int $limite = 500): array {
        $sql = "
            SELECT u.id, u.numero, u.cuota_mensual, u.edificio_id, e.nombre as edificio_nombre
            FROM unidades u
            INNER JOIN edificios e ON u.edificio_id = e.id
            WHERE u.estado = 1
              AND u.propietario_id IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM personas p 
                  WHERE p.unidad_id = u.id AND p.estado = 1
              )
              AND NOT EXISTS (
                  SELECT 1 FROM solicitudes_registro sr 
                  WHERE sr.unidad_id = u.id AND sr.estado = 'pendiente'
              )
            ORDER BY e.nombre ASC, u.numero ASC
            LIMIT {$limite}
        ";
        return $this->db()->query($sql)->fetchAll();
    }

    public function getAllWithEdificio($edificioId = null) {
        $sql = "
            SELECT u.*, e.nombre as edificio_nombre,
                   COUNT(p.id) as total_residentes
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON p.unidad_id = u.id AND p.estado = 1
        ";

        $params = [];
        if ($edificioId && $edificioId > 0) {
            $sql .= " WHERE u.edificio_id = :edificio_id";
            $params['edificio_id'] = $edificioId;
        }

        $sql .= " GROUP BY u.id ORDER BY " . ($edificioId ? "u.numero ASC" : "e.nombre ASC, u.numero ASC");
        $sql .= " LIMIT 500";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById($tableTablaOId, ?int $id = null): array|false {
        $actualId = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        $sql = "
            SELECT u.*, e.nombre as edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.id = :id
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $actualId]);
        return $stmt->fetch() ?: false;
    }

    public function numeroExists($numero, $excludeId = null) {
        return $this->exists('unidades', 'numero', $numero, $excludeId);
    }

    public function create($tableTablaOData, ?array $data = null): string|false {
        $actualData = is_array($tableTablaOData) ? $tableTablaOData : ($data ?? []);
        $actualData['estado'] = 1;
        return parent::create('unidades', $actualData);
    }

    /**
     * Crea una unidad habitacional y opcionalmente genera y asigna un puesto
     * de estacionamiento de forma atómica en una única transacción de base de datos.
     *
     * @param array $data ['numero' => ..., 'edificio_id' => ...]
     * @param bool $asignarEstacionamiento
     * @return string|false ID de la unidad creada o false si falla
     */
    public function createWithEstacionamiento(array $data, bool $asignarEstacionamiento = false): string|false {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $data['estado'] = 1;
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = sprintf("INSERT INTO unidades (%s) VALUES (%s)", $columns, $placeholders);

            $stmtUnidad = $db->prepare($sql);
            if (!$stmtUnidad->execute($data)) {
                $db->rollBack();
                return false;
            }

            $unidadId = (int)$db->lastInsertId();

            if ($asignarEstacionamiento) {
                $numeroPuesto = 'Puesto - ' . $data['numero'];

                // Verificar si existe un puesto con el mismo número que esté desocupado para vincularlo
                $stmtCheck = $db->prepare("
                    SELECT id, unidad_id 
                    FROM estacionamientos 
                    WHERE numero = :numero AND (deleted_at IS NULL OR deleted_at = '')
                    LIMIT 1
                ");
                $stmtCheck->execute(['numero' => $numeroPuesto]);
                $puestoExistente = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

                if ($puestoExistente) {
                    if (empty($puestoExistente['unidad_id'])) {
                        // Si existe y no está asignado, vincularlo a esta unidad
                        $stmtUpd = $db->prepare("
                            UPDATE estacionamientos 
                            SET unidad_id = :unidad_id, edificio_id = :edificio_id 
                            WHERE id = :id
                        ");
                        $ok = $stmtUpd->execute([
                            'unidad_id'   => $unidadId,
                            'edificio_id' => $data['edificio_id'] ?? null,
                            'id'          => $puestoExistente['id']
                        ]);
                        if (!$ok) {
                            $db->rollBack();
                            return false;
                        }
                    } else {
                        // Ya ocupado por otra unidad; registrar uno alternativo con sufijo para evitar duplicidad
                        $numeroPuestoAlt = $numeroPuesto . '-' . $unidadId;
                        $stmtIns = $db->prepare("
                            INSERT INTO estacionamientos (numero, tipo, edificio_id, unidad_id, estado)
                            VALUES (:numero, 'descubierto', :edificio_id, :unidad_id, 1)
                        ");
                        $ok = $stmtIns->execute([
                            'numero'      => $numeroPuestoAlt,
                            'edificio_id' => $data['edificio_id'] ?? null,
                            'unidad_id'   => $unidadId
                        ]);
                        if (!$ok) {
                            $db->rollBack();
                            return false;
                        }
                    }
                } else {
                    // Puesto nuevo no existente
                    $stmtIns = $db->prepare("
                        INSERT INTO estacionamientos (numero, tipo, edificio_id, unidad_id, estado)
                        VALUES (:numero, 'descubierto', :edificio_id, :unidad_id, 1)
                    ");
                    $ok = $stmtIns->execute([
                        'numero'      => $numeroPuesto,
                        'edificio_id' => $data['edificio_id'] ?? null,
                        'unidad_id'   => $unidadId
                    ]);
                    if (!$ok) {
                        $db->rollBack();
                        return false;
                    }
                }
            }

            $db->commit();
            return (string)$unidadId;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[ERROR ATOMIC TRANSACTION UNIDAD/ESTACIONAMIENTO] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si una unidad habitacional ya tiene un puesto de estacionamiento asignado.
     */
    public function tieneEstacionamientoAsignado(int $unidadId): bool {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) FROM estacionamientos 
            WHERE unidad_id = :uid AND (deleted_at IS NULL OR deleted_at = '')
        ");
        $stmt->execute(['uid' => $unidadId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function update($tableTablaOId, $idOData = null, ?array $data = null): bool {
        $id = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$idOData;
        $actualData = is_array($idOData) ? $idOData : ($data ?? []);
        return parent::update('unidades', $id, $actualData);
    }

    public function toggleEstado($tableTablaOId, ?int $id = null): bool {
        $actualId = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        return parent::toggleEstado('unidades', $actualId);
    }

    /**
     * Asigna el propietario oficial de la unidad.
     */
    public function setPropietario(int $unidadId, ?int $propietarioId): bool {
        $stmt = $this->db()->prepare("UPDATE unidades SET propietario_id = :pid WHERE id = :uid");
        return $stmt->execute(['pid' => $propietarioId, 'uid' => $unidadId]);
    }

    /**
     * Gestiona la baja de un propietario:
     * Si la persona era el titular actual, promueve al siguiente co-propietario activo en la unidad;
     * si no hay otro, establece NULL.
     */
    public function gestionarBajaPropietario(int $unidadId, int $personaIdDesvinculada): bool {
        $stmt = $this->db()->prepare("SELECT propietario_id FROM unidades WHERE id = :uid");
        $stmt->execute(['uid' => $unidadId]);
        $propietarioActual = $stmt->fetchColumn();

        if ((int)$propietarioActual === $personaIdDesvinculada) {
            // Buscar otro propietario activo en la misma unidad
            $stmtOtro = $this->db()->prepare("
                SELECT id FROM personas 
                WHERE unidad_id = :uid AND id != :pid AND estado = 1 AND tipo IN ('propietario', 'ambos') 
                ORDER BY id ASC LIMIT 1
            ");
            $stmtOtro->execute(['uid' => $unidadId, 'pid' => $personaIdDesvinculada]);
            $nuevoPropietario = $stmtOtro->fetchColumn();

            $nuevoId = $nuevoPropietario ? (int)$nuevoPropietario : null;
            $stmtUpd = $this->db()->prepare("UPDATE unidades SET propietario_id = :npid WHERE id = :uid");
            return $stmtUpd->execute(['npid' => $nuevoId, 'uid' => $unidadId]);
        }
        return true;
    }
}