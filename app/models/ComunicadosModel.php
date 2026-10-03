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

        $contenido = $this->sanitizarContenido($datos['contenido'] ?? '');

        $db = $this->db();
        $sql = "
            INSERT INTO comunicados 
            (titulo, contenido, nivel_urgencia, edificio_id, unidad_id, admin_id, fecha_publicacion, fecha_expiracion) 
            VALUES (:titulo, :contenido, :urgencia, :edificio_id, :unidad_id, :admin_id, :fecha_publicacion, :fecha_expiracion)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'titulo'            => trim($datos['titulo']),
            'contenido'         => $contenido,
            'urgencia'          => $nivelUrgencia,
            'edificio_id'       => !empty($datos['edificio_id']) ? intval($datos['edificio_id']) : null,
            'unidad_id'         => !empty($datos['unidad_id']) ? intval($datos['unidad_id']) : null,
            'admin_id'          => intval($datos['admin_id']),
            'fecha_publicacion' => !empty($datos['fecha_publicacion']) ? $datos['fecha_publicacion'] : date('Y-m-d H:i:s'),
            'fecha_expiracion'  => !empty($datos['fecha_expiracion']) ? $datos['fecha_expiracion'] : null
        ]);

        return intval($db->lastInsertId());
    }

    /**
     * Sanitiza el contenido permitiendo solo etiquetas de formato básico.
     * Se excluyen explícitamente los enlaces (tag 'a') para prevenir XSS via javascript: o data: URI.
     */
    private function sanitizarContenido(string $contenido): string {
        $allowedTags = ['b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'p', 'br'];
        $allowedString = '<' . implode('><', $allowedTags) . '>';
        return strip_tags($contenido, $allowedString);
    }

    /**
     * Asigna unidades específicas como destino de un comunicado, permitiendo
     * abarcar unidades de uno o varios edificios. Deduplica y sanitiza los IDs;
     * ignora valores no positivos.
     *
     * @param int   $comunicadoId
     * @param int[] $unidadIds
     */
    public function asignarDestinosUnidades(int $comunicadoId, array $unidadIds): void {
        $unidadIds = array_values(array_unique(array_filter(array_map('intval', $unidadIds), function ($id) {
            return $id > 0;
        })));

        if ($comunicadoId <= 0 || empty($unidadIds)) {
            return;
        }

        $db = $this->db();
        $stmt = $db->prepare("
            INSERT INTO comunicados_destinos (comunicado_id, unidad_id)
            VALUES (:comunicado_id, :unidad_id)
        ");

        foreach ($unidadIds as $unidadId) {
            $stmt->execute([
                'comunicado_id' => $comunicadoId,
                'unidad_id'     => $unidadId,
            ]);
        }
    }

    /**
     * Detecta un comunicado reciente idéntico (mismo admin, título, contenido y
     * destinos) dentro de la ventana indicada, para evitar publicaciones
     * duplicadas por doble envío. Los destinos se comparan de forma null-safe.
     *
     * @param string $titulo
     * @param string $contenido Contenido tal como llega del formulario (se sanitiza igual que en crearComunicado)
     * @param int|null $edificioId
     * @param int|null $unidadId
     * @param int $adminId
     * @param int $ventanaMinutos
     * @return bool
     */
    public function existeDuplicadoReciente(string $titulo, string $contenido, ?int $edificioId, ?int $unidadId, int $adminId, int $ventanaMinutos = 10): bool {
        $contenidoSanitizado = $this->sanitizarContenido($contenido);
        $desde = date('Y-m-d H:i:s', time() - (max(1, $ventanaMinutos) * 60));

        $db = $this->db();
        $stmt = $db->prepare("
            SELECT id FROM comunicados
            WHERE deleted_at IS NULL
              AND admin_id = :admin_id
              AND titulo = :titulo
              AND contenido = :contenido
              AND edificio_id <=> :edificio_id
              AND unidad_id <=> :unidad_id
              AND fecha_publicacion >= :desde
            LIMIT 1
        ");
        $stmt->execute([
            'admin_id'    => $adminId,
            'titulo'      => $titulo,
            'contenido'   => $contenidoSanitizado,
            'edificio_id' => $edificioId,
            'unidad_id'   => $unidadId,
            'desde'       => $desde
        ]);

        return (bool)$stmt->fetch();
    }

    /**
     * Obtiene los comunicados segmentados para un residente (Globales, por Edificio o por Unidad).
     */
    public function obtenerPorResidente(?int $edificioId = null, ?int $unidadId = null, int $pagina = 1, int $porPagina = 10): array {
        $where = "WHERE c.deleted_at IS NULL AND c.fecha_publicacion <= :ahora";
        $params = ['ahora' => date('Y-m-d H:i:s')];

        $where .= " AND (c.fecha_expiracion IS NULL OR c.fecha_expiracion > :ahora_exp)";
        $params['ahora_exp'] = date('Y-m-d H:i:s');

        $where .= " AND (
            (c.edificio_id IS NULL AND c.unidad_id IS NULL
                AND NOT EXISTS (SELECT 1 FROM comunicados_destinos cdg WHERE cdg.comunicado_id = c.id))";

        if ($edificioId) {
            $where .= " OR (c.edificio_id = :edificio_id AND c.unidad_id IS NULL)";
            $params['edificio_id'] = $edificioId;
        }

        if ($unidadId) {
            $where .= " OR (c.unidad_id = :unidad_id)";
            $where .= " OR EXISTS (SELECT 1 FROM comunicados_destinos cd WHERE cd.comunicado_id = c.id AND cd.unidad_id = :unidad_destino)";
            $params['unidad_id'] = $unidadId;
            $params['unidad_destino'] = $unidadId;
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
                   usr.nombre_completo AS admin_nombre,
                   (SELECT COUNT(*) FROM comunicados_destinos cd WHERE cd.comunicado_id = c.id) AS destinos_count
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

    /**
     * Elimina lógicamente (soft delete) los comunicados vencidos.
     * "Vencido" = fecha_expiracion no nula y menor o igual al reloj de PHP
     * (mismo reloj con el que se escriben las fechas de expiración).
     *
     * @return int Número de comunicados eliminados
     */
    public function eliminarExpirados(): int {
        $corte = date('Y-m-d H:i:s');
        $db = $this->db();
        $stmt = $db->prepare("
            UPDATE comunicados
            SET deleted_at = :marca
            WHERE deleted_at IS NULL
              AND fecha_expiracion IS NOT NULL
              AND fecha_expiracion <= :corte
        ");
        $stmt->execute(['marca' => $corte, 'corte' => $corte]);
        return $stmt->rowCount();
    }
}
