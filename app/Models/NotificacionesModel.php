<?php
namespace App\Models;

use PDO;

class NotificacionesModel extends BaseModel {

    protected string $table = 'notificaciones';

    /**
     * Obtiene el número total de notificaciones no leídas para un residente.
     */
    public function contarNoLeidas(int $residenteId): int {
        $db = $this->db();
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM notificaciones WHERE residente_id = :residente_id AND leido = 0");
        $stmt->execute(['residente_id' => $residenteId]);
        return intval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * Obtiene las notificaciones no leídas de un residente.
     */
    public function obtenerNoLeidas(int $residenteId): array {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM notificaciones WHERE residente_id = :residente_id AND leido = 0 ORDER BY fecha_registro DESC LIMIT 20");
        $stmt->execute(['residente_id' => $residenteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el historial completo paginado de notificaciones de un residente.
     */
    public function obtenerHistorial(int $residenteId, int $pagina = 1, int $porPagina = 15): array {
        $baseSql = "SELECT * FROM notificaciones WHERE residente_id = :residente_id";
        $countSql = "SELECT COUNT(*) AS total FROM notificaciones WHERE residente_id = :residente_id";
        return $this->paginate($baseSql, $countSql, ['residente_id' => $residenteId], $pagina, $porPagina, 'fecha_registro DESC');
    }

    /**
     * Marca una notificación como leída garantizando la pertenencia al residente.
     */
    public function marcarComoLeida(int $id, int $residenteId): bool {
        $db = $this->db();
        $stmt = $db->prepare("UPDATE notificaciones SET leido = 1 WHERE id = :id AND residente_id = :residente_id");
        return $stmt->execute(['id' => $id, 'residente_id' => $residenteId]);
    }
}
