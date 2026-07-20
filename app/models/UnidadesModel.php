<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class UnidadesModel {
    /**
     * Obtiene todas las unidades activas incluyendo el nombre del edificio.
     *
     * @return array
     */
    public function getActivas() {
        $db = Database::getConnection();
        
        $sql = "
            SELECT u.id, u.numero, u.cuota_mensual, u.edificio_id, e.nombre as edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.estado = 1
            ORDER BY e.nombre ASC, u.numero ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todas las unidades con datos del edificio, filtrables por edificio_id.
     *
     * @param int|null $edificioId
     * @return array
     */
    public function getAllWithEdificio($edificioId = null) {
        $db = Database::getConnection();
        
        if ($edificioId && $edificioId > 0) {
            $sql = "
                SELECT u.*, e.nombre as edificio_nombre,
                       (SELECT COUNT(*) FROM personas p WHERE p.unidad_id = u.id AND p.estado = 1) as total_residentes
                FROM unidades u
                LEFT JOIN edificios e ON u.edificio_id = e.id
                WHERE u.edificio_id = :edificio_id
                ORDER BY u.numero ASC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['edificio_id' => $edificioId]);
            return $stmt->fetchAll();
        } else {
            $sql = "
                SELECT u.*, e.nombre as edificio_nombre,
                       (SELECT COUNT(*) FROM personas p WHERE p.unidad_id = u.id AND p.estado = 1) as total_residentes
                FROM unidades u
                LEFT JOIN edificios e ON u.edificio_id = e.id
                ORDER BY e.nombre ASC, u.numero ASC
            ";
            $stmt = $db->query($sql);
            return $stmt->fetchAll();
        }
    }

    /**
     * Obtiene los datos de una unidad por su ID.
     *
     * @param int $id
     * @return array|false
     */
    public function getById($id) {
        $db = Database::getConnection();
        
        $sql = "
            SELECT u.*, e.nombre as edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.id = :id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Verifica si el número/código de unidad ya existe.
     *
     * @param string $numero
     * @param int|null $excludeId
     * @return bool
     */
    public function numeroExists($numero, $excludeId = null) {
        $db = Database::getConnection();
        
        if ($excludeId) {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM unidades WHERE LOWER(numero) = LOWER(:numero) AND id != :id");
            $stmt->execute(['numero' => $numero, 'id' => $excludeId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM unidades WHERE LOWER(numero) = LOWER(:numero)");
            $stmt->execute(['numero' => $numero]);
        }
        
        $row = $stmt->fetch();
        return intval($row['total'] ?? 0) > 0;
    }

    /**
     * Registra una nueva unidad.
     *
     * @param array $data ['numero' => string, 'edificio_id' => int, 'cuota_mensual' => float]
     * @return bool
     */
    public function create($data) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO unidades (numero, edificio_id, cuota_mensual, estado) 
            VALUES (:numero, :edificio_id, :cuota_mensual, 1)
        ");
        return $stmt->execute([
            'numero'        => trim($data['numero']),
            'edificio_id'   => intval($data['edificio_id']),
            'cuota_mensual' => floatval($data['cuota_mensual'])
        ]);
    }

    /**
     * Actualiza los datos de una unidad existente.
     *
     * @param int $id
     * @param array $data ['numero' => string, 'edificio_id' => int, 'cuota_mensual' => float]
     * @return bool
     */
    public function update($id, $data) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            UPDATE unidades 
            SET numero = :numero, edificio_id = :edificio_id, cuota_mensual = :cuota_mensual 
            WHERE id = :id
        ");
        return $stmt->execute([
            'numero'        => trim($data['numero']),
            'edificio_id'   => intval($data['edificio_id']),
            'cuota_mensual' => floatval($data['cuota_mensual']),
            'id'            => $id
        ]);
    }

    /**
     * Cambia el estado (activar/desactivar) de una unidad.
     *
     * @param int $id
     * @return bool
     */
    public function toggleEstado($id) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("UPDATE unidades SET estado = IF(estado = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
