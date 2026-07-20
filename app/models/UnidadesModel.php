<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class UnidadesModel {
    /**
     * Obtiene todas las unidades activas (estado = 1).
     *
     * @return array
     */
    public function getActivas() {
        $db = Database::getConnection();
        
        $stmt = $db->query("SELECT id, numero, cuota_mensual FROM unidades WHERE estado = 1");
        
        return $stmt->fetchAll();
    }
}
