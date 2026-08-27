<?php
namespace App\Models;

class EdificiosModel extends BaseModel {
    protected string $table = 'edificios';

    public function getActivos(int $limite = 500) {
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            WHERE e.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
            LIMIT {$limite}
        ";
        return $this->db()->query($sql)->fetchAll();
    }

    public function getAll() {
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
            LIMIT 500
        ";
        return $this->db()->query($sql)->fetchAll();
    }

    public function nombreExists($nombre, $excludeId = null) {
        return $this->exists('edificios', 'nombre', $nombre, $excludeId);
    }

    public function create($tableTablaOData, ?array $data = null): string|false {
        $actualData = is_array($tableTablaOData) ? $tableTablaOData : ($data ?? []);
        $actualData['estado'] = 1;
        return parent::create('edificios', $actualData);
    }

    public function update($tableTablaOId, $idOData = null, ?array $data = null): bool {
        $id = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$idOData;
        $actualData = is_array($idOData) ? $idOData : ($data ?? []);
        return parent::update('edificios', $id, $actualData);
    }

    public function getById($tableTablaOId, ?int $id = null): array|false {
        $actualId = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        return parent::getById('edificios', $actualId);
    }

    public function toggleEstado($tableTablaOId, ?int $id = null): bool {
        $actualId = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        return parent::toggleEstado('edificios', $actualId);
    }
}