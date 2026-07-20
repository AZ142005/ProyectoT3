<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class EdificiosModel {
    /**
     * Obtiene todos los edificios activos con el conteo de unidades asociadas.
     *
     * @return array
     */
    public function getActivos() {
        $db = Database::getConnection();
        
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            WHERE e.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todos los edificios (activos e inactivos).
     *
     * @return array
     */
    public function getAll() {
        $db = Database::getConnection();
        
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un edificio por su ID.
     *
     * @param int $id
     * @return array|false
     */
    public function getById($id) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM edificios WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Verifica si ya existe un edificio con el mismo nombre.
     *
     * @param string $nombre
     * @param int|null $excludeId ID a excluir (útil en edicion)
     * @return bool
     */
    public function nombreExists($nombre, $excludeId = null) {
        $db = Database::getConnection();
        
        if ($excludeId) {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM edificios WHERE LOWER(nombre) = LOWER(:nombre) AND id != :id");
            $stmt->execute(['nombre' => $nombre, 'id' => $excludeId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM edificios WHERE LOWER(nombre) = LOWER(:nombre)");
            $stmt->execute(['nombre' => $nombre]);
        }
        
        $row = $stmt->fetch();
        return intval($row['total'] ?? 0) > 0;
    }

    /**
     * Crea un nuevo edificio.
     *
     * @param array $data ['nombre' => string, 'descripcion' => string]
     * @return bool
     */
    public function create($data) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO edificios (nombre, descripcion, estado) 
            VALUES (:nombre, :descripcion, 1)
        ");
        return $stmt->execute([
            'nombre'      => trim($data['nombre']),
            'descripcion' => trim($data['descripcion'] ?? '')
        ]);
    }

    /**
     * Actualiza los datos de un edificio.
     *
     * @param int $id
     * @param array $data ['nombre' => string, 'descripcion' => string]
     * @return bool
     */
    public function update($id, $data) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            UPDATE edificios 
            SET nombre = :nombre, descripcion = :descripcion 
            WHERE id = :id
        ");
        return $stmt->execute([
            'nombre'      => trim($data['nombre']),
            'descripcion' => trim($data['descripcion'] ?? ''),
            'id'          => $id
        ]);
    }

    /**
     * Alterna el estado (activar/desactivar) de un edificio.
     *
     * @param int $id
     * @return bool
     */
    public function toggleEstado($id) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("UPDATE edificios SET estado = IF(estado = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
