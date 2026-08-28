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