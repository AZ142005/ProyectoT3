<?php
namespace App\Models;

use PDO;
use PDOException;
use Exception;

class VehiculosModel extends BaseModel {
    protected string $table = 'vehiculos';

    /**
     * Normaliza la placa a mayúsculas y sin espacios.
     */
    public function normalizarPlaca(string $placa): string {
        return strtoupper(trim(preg_replace('/\s+/', '', $placa)));
    }

    /**
     * Verifica si una placa ya se encuentra registrada en el sistema.
     */
    public function existePlaca(string $placa, ?int $excluirId = null): bool {
        $placaNorm = $this->normalizarPlaca($placa);
        $sql = "SELECT COUNT(*) as total FROM vehiculos WHERE placa = :placa";
        $params = ['placa' => $placaNorm];

        if ($excluirId !== null) {
            $sql .= " AND id != :excluir_id";
            $params['excluir_id'] = $excluirId;
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return intval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0;
    }

    /**
     * Registra un vehículo con normalización de placa y captura de error de clave duplicada SQL 23000.
     *
     * @param array $datos ['unidad_id', 'persona_id', 'estacionamiento_id', 'placa', 'marca', 'modelo', 'color', 'observaciones']
     * @return int ID del vehículo creado
     * @throws Exception Si la placa ya existe o falla la inserción
     */
    public function crearVehiculo(array $datos): int {
        $placaNorm = $this->normalizarPlaca($datos['placa'] ?? '');
        if (empty($placaNorm)) {
            throw new Exception("La placa del vehículo no puede estar vacía.");
        }

        if ($this->existePlaca($placaNorm)) {
            throw new Exception("La placa '{$placaNorm}' ya se encuentra registrada en el sistema.");
        }

        $sql = "INSERT INTO vehiculos (unidad_id, persona_id, estacionamiento_id, placa, marca, modelo, color, observaciones)
                VALUES (:unidad_id, :persona_id, :estacionamiento_id, :placa, :marca, :modelo, :color, :observaciones)";
        
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'unidad_id'          => intval($datos['unidad_id']),
                'persona_id'         => intval($datos['persona_id']),
                'estacionamiento_id' => !empty($datos['estacionamiento_id']) ? intval($datos['estacionamiento_id']) : null,
                'placa'              => $placaNorm,
                'marca'              => trim($datos['marca'] ?? ''),
                'modelo'             => trim($datos['modelo'] ?? ''),
                'color'              => trim($datos['color'] ?? ''),
                'observaciones'      => !empty($datos['observaciones']) ? trim($datos['observaciones']) : null
            ]);
            return intval($this->db()->lastInsertId());
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || strpos($e->getMessage(), '23000') !== false) {
                throw new Exception("La placa '{$placaNorm}' ya se encuentra registrada en otro vehículo del condominio.");
            }
            throw $e;
        }
    }

    /**
     * Lista los vehículos asociados a una unidad habitacional.
     */
    public function obtenerPorUnidad(int $unidadId): array {
        $sql = "SELECT v.*, 
                       CONCAT(p.nombre, ' ', p.apellido) AS propietario_nombre,
                       e.numero AS puesto_numero
                FROM vehiculos v
                INNER JOIN personas p ON v.persona_id = p.id
                LEFT JOIN estacionamientos e ON v.estacionamiento_id = e.id
                WHERE v.unidad_id = :unidad_id
                ORDER BY v.fecha_registro DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina un vehículo verificando propiedad de la unidad o permisos administrativos.
     */
    public function eliminarVehiculo(int $id, ?int $unidadId = null): bool {
        $sql = "DELETE FROM vehiculos WHERE id = :id";
        $params = ['id' => $id];

        if ($unidadId !== null) {
            $sql .= " AND unidad_id = :unidad_id";
            $params['unidad_id'] = $unidadId;
        }

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute($params);
    }
}
