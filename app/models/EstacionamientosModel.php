<?php
namespace App\Models;

use PDO;
use Exception;

class EstacionamientosModel extends BaseModel {
    protected string $table = 'estacionamientos';

    public const TIPOS_VALIDOS = ['techado', 'descubierto', 'visitante'];

    /**
     * Aplica el filtro por defecto WHERE deleted_at IS NULL
     */
    protected function baseQuery(string $sql, bool $incluirEliminados = false): string {
        if (!$incluirEliminados) {
            $prefix = (strpos($sql, 'WHERE') !== false) ? ' AND ' : ' WHERE ';
            $sql .= "{$prefix} (e.deleted_at IS NULL OR e.deleted_at = '')";
        }
        return $sql;
    }

    /**
     * Listado completo con JOIN a edificios, unidades y datos del vehículo asignado.
     */
    public function listarConDetalles(bool $incluirEliminados = false): array {
        $sql = "SELECT e.*, 
                       ed.nombre AS edificio_nombre, 
                       u.numero AS unidad_numero,
                       v.id AS vehiculo_id,
                       v.placa AS vehiculo_placa,
                       v.marca AS vehiculo_marca,
                       v.modelo AS vehiculo_modelo
                FROM estacionamientos e
                LEFT JOIN edificios ed ON e.edificio_id = ed.id
                LEFT JOIN unidades u ON e.unidad_id = u.id
                LEFT JOIN vehiculos v ON v.estacionamiento_id = e.id";
        
        $sql = $this->baseQuery($sql, $incluirEliminados);
        $sql .= " ORDER BY e.numero ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los puestos asignados a una unidad específica.
     */
    public function obtenerPorUnidad(int $unidadId): array {
        $sql = "SELECT e.*, ed.nombre AS edificio_nombre
                FROM estacionamientos e
                LEFT JOIN edificios ed ON e.edificio_id = ed.id
                WHERE e.unidad_id = :unidad_id AND (e.deleted_at IS NULL OR e.deleted_at = '')
                ORDER BY e.numero ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asigna o desasigna un puesto de estacionamiento a una unidad habitacional con bloqueo transaccional.
     *
     * @param int $puestoId
     * @param int|null $unidadId Null para liberar el puesto
     * @return bool
     * @throws Exception Si el puesto ya está asignado a otra unidad activa
     */
    public function asignarAUnidad(int $puestoId, ?int $unidadId): bool {
        $db = $this->db();
        try {
            $db->beginTransaction();

            // Bloquear el registro del puesto para evitar condiciones de carrera
            $stmtLock = $db->prepare("SELECT id, unidad_id, deleted_at FROM estacionamientos WHERE id = :id FOR UPDATE");
            $stmtLock->execute(['id' => $puestoId]);
            $puesto = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$puesto || !empty($puesto['deleted_at'])) {
                $db->rollBack();
                throw new Exception("El puesto de estacionamiento no existe o ha sido dado de baja.");
            }

            // Si se intenta asignar a una unidad y ya está ocupado por otra unidad diferente
            if ($unidadId !== null && !empty($puesto['unidad_id']) && (int)$puesto['unidad_id'] !== $unidadId) {
                $db->rollBack();
                throw new Exception("El puesto ya se encuentra asignado a otra unidad habitacional.");
            }

            // Actualizar la asignación
            $stmtUpdate = $db->prepare("UPDATE estacionamientos SET unidad_id = :unidad_id WHERE id = :id");
            $stmtUpdate->execute([
                'unidad_id' => $unidadId,
                'id'        => $puestoId
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Da de baja (Soft Delete) a un puesto de estacionamiento.
     */
    public function softDelete(int $id): bool {
        $stmt = $this->db()->prepare("UPDATE estacionamientos SET deleted_at = NOW(), unidad_id = NULL WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
