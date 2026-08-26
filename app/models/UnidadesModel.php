<?php
namespace App\Models;

class UnidadesModel extends BaseModel {
    protected string $table = 'unidades';

    public function getActivas() {
        $sql = "
            SELECT u.id, u.numero, u.cuota_mensual, u.edificio_id, e.nombre as edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.estado = 1
            ORDER BY e.nombre ASC, u.numero ASC
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

    public function getById($id) {
        $sql = "
            SELECT u.*, e.nombre as edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.id = :id
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function numeroExists($numero, $excludeId = null) {
        return $this->exists('unidades', 'numero', $numero, $excludeId);
    }

    public function create($data) {
        $data['estado'] = 1;
        return parent::create('unidades', $data);
    }

    public function update($id, $data): bool {
        return parent::update('unidades', $id, $data);
    }

    public function toggleEstado($id): bool {
        return parent::toggleEstado('unidades', $id);
    }
}