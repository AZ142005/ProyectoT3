<?php
namespace App\Models;

class EdificiosModel extends BaseModel {
    protected string $table = 'edificios';

    public function getActivos() {
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            WHERE e.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
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

    public function create($data) {
        $data['estado'] = 1;
        return parent::create('edificios', $data);
    }

    public function update($id, $data): bool {
        return parent::update('edificios', $id, $data);
    }

    public function getById($id) {
        return parent::getById('edificios', $id);
    }

    public function toggleEstado($id): bool {
        return parent::toggleEstado('edificios', $id);
    }
}