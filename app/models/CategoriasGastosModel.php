<?php
namespace App\Models;

use PDO;

class CategoriasGastosModel extends BaseModel {

    protected string $table = 'categorias_gastos';

    /**
     * Obtiene todas las categorías de gastos activas.
     */
    public function getActivas(): array {
        $db = $this->db();
        $stmt = $db->query("SELECT * FROM categorias_gastos WHERE activo = 1 ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra una nueva categoría de gastos.
     */
    public function crearCategoria(array $datos): int {
        $db = $this->db();
        $stmt = $db->prepare("
            INSERT INTO categorias_gastos (nombre, descripcion, icono, color, activo)
            VALUES (:nombre, :descripcion, :icono, :color, 1)
        ");
        $stmt->execute([
            'nombre'      => trim($datos['nombre']),
            'descripcion' => !empty($datos['descripcion']) ? trim($datos['descripcion']) : null,
            'icono'       => !empty($datos['icono']) ? trim($datos['icono']) : 'receipt_long',
            'color'       => !empty($datos['color']) ? trim($datos['color']) : '#27ae60'
        ]);

        return intval($db->lastInsertId());
    }
}
