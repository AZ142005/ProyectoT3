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
}