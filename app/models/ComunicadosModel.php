<?php
namespace App\Models;

use PDO;
use Exception;

class ComunicadosModel extends BaseModel {

    protected string $table = 'comunicados';

    /**
     * Inserta un nuevo comunicado en el sistema sanitizando etiquetas peligrosas.
     *
     * @param array $datos ['titulo', 'contenido', 'nivel_urgencia', 'edificio_id', 'unidad_id', 'admin_id', 'fecha_publicacion']
     * @return int ID del comunicado creado
     */
    public function crearComunicado(array $datos): int {
        $urgenciasValidas = ['normal', 'importante', 'urgente'];
        $nivelUrgencia = strtolower($datos['nivel_urgencia'] ?? 'normal');
        if (!in_array($nivelUrgencia, $urgenciasValidas)) {
            throw new Exception("Nivel de urgencia no válido.");
        }

        $allowedTags = ['b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'p', 'br', 'a'];
        $allowedString = '<' . implode('><', $allowedTags) . '>';

        // Sanitización balanceada: permitir HTML seguro y neutralizar javascript/onclick
        $contenido = strip_tags($datos['contenido'] ?? '', $allowedString);

        $db = $this->db();
        $sql = "
            INSERT INTO comunicados 
            (titulo, contenido, nivel_urgencia, edificio_id, unidad_id, admin_id, fecha_publicacion) 
            VALUES (:titulo, :contenido, :urgencia, :edificio_id, :unidad_id, :admin_id, :fecha_publicacion)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'titulo'            => trim($datos['titulo']),
            'contenido'         => $contenido,
            'urgencia'          => $nivelUrgencia,
            'edificio_id'       => !empty($datos['edificio_id']) ? intval($datos['edificio_id']) : null,
            'unidad_id'         => !empty($datos['unidad_id']) ? intval($datos['unidad_id']) : null,
            'admin_id'          => intval($datos['admin_id']),
            'fecha_publicacion' => !empty($datos['fecha_publicacion']) ? $datos['fecha_publicacion'] : date('Y-m-d H:i:s')
        ]);

        return intval($db->lastInsertId());
    }

    /**
     * Obtiene los comunicados segmentados para un residente (Globales, por Edificio o por Unidad).
     */
    public function obtenerPorResidente(?int $edificioId = null, ?int $unidadId = null, int $pagina = 1, int $porPagina = 10): array {
        $where = "WHERE c.deleted_at IS NULL AND c.fecha_publicacion <= NOW()";
        $params = [];

        $where .= " AND (
            (c.edificio_id IS NULL AND c.unidad_id IS NULL)";

        if ($edificioId) {
            $where .= " OR (c.edificio_id = :edificio_id AND c.unidad_id IS NULL)";
            $params['edificio_id'] = $edificioId;
        }

        if ($unidadId) {
            $where .= " OR (c.unidad_id = :unidad_id)";
            $params['unidad_id'] = $unidadId;
        }

        $where .= ")";

        $baseSql = "
            SELECT c.*, COALESCE(e.nombre, 'Todos los Edificios') AS edificio_nombre, u.numero AS unidad_numero
            FROM comunicados c
            LEFT JOIN edificios e ON c.edificio_id = e.id
            LEFT JOIN unidades u ON c.unidad_id = u.id
            {$where}
        ";

        $countSql = "
            SELECT COUNT(*) AS total
            FROM comunicados c
            {$where}
        ";

        return $this->paginate($baseSql, $countSql, $params, $pagina, $porPagina, 'c.fecha_publicacion DESC');
    }

    /**
     * Obtiene todos los comunicados para la gestión administrativa.
     */
    public function obtenerTodosAdmin(int $pagina = 1, int $porPagina = 15): array {
        $baseSql = "
            SELECT c.*, COALESCE(e.nombre, 'Global') AS edificio_nombre, u.numero AS unidad_numero,
                   usr.nombre_completo AS admin_nombre
            FROM comunicados c
            LEFT JOIN edificios e ON c.edificio_id = e.id
            LEFT JOIN unidades u ON c.unidad_id = u.id
            INNER JOIN usuarios usr ON c.admin_id = usr.id
            WHERE c.deleted_at IS NULL
        ";

        $countSql = "SELECT COUNT(*) AS total FROM comunicados WHERE deleted_at IS NULL";

        return $this->paginate($baseSql, $countSql, [], $pagina, $porPagina, 'c.fecha_publicacion DESC');
    }

    /**
     * Borrado lógico (Soft Delete) de un comunicado.
     */
    public function softDelete(int $id): bool {
        $db = $this->db();
        $stmt = $db->prepare("UPDATE comunicados SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
        return $stmt->execute(['id' => $id]);
    }
}
