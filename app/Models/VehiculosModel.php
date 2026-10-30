<?php
namespace App\Models;

use PDO;
use PDOException;
use Exception;

class VehiculosModel extends BaseModel {
    protected string $table = 'vehiculos';

    /**
     * Tipos de vehículo admitidos por el condominio.
     */
    public const TIPOS_VEHICULO = [
        'pequeno'     => 'Pequeño',
        'camioneta'   => 'Camioneta (mediano)',
        'camion_f350' => 'Camión tipo F-350',
    ];

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
     * Registra un vehículo con tipo y color obligatorios y marca opcional.
     * La placa es opcional (se conserva para datos heredados).
     *
     * @param array $datos ['unidad_id', 'persona_id', 'estacionamiento_id', 'tipo_vehiculo', 'color', 'marca', 'placa']
     * @return int ID del vehículo creado
     * @throws Exception Si el tipo/color son inválidos, la placa es inválida o duplicada, o falla la inserción
     */
    public function crearVehiculo(array $datos): int {
        $tipo = trim((string)($datos['tipo_vehiculo'] ?? ''));
        if (!array_key_exists($tipo, self::TIPOS_VEHICULO)) {
            throw new Exception("El tipo de vehículo es obligatorio y debe ser Pequeño, Camioneta mediano o Camión tipo F-350.");
        }

        $color = trim((string)($datos['color'] ?? ''));
        if ($color === '') {
            throw new Exception("El color del vehículo es obligatorio.");
        }

        $marca = trim((string)($datos['marca'] ?? ''));

        $placaNorm = $this->normalizarPlaca((string)($datos['placa'] ?? ''));
        if ($placaNorm !== '') {
            // G3-O4: Validate Venezuelan plate format (ABC1234 or ABC123D)
            // 3 letters + 3-4 digits + optional trailing letter
            if (!preg_match('/^[A-Z]{3}[0-9]{3,4}[A-Z]?$/', $placaNorm)) {
                throw new Exception("La placa '{$placaNorm}' no tiene formato válido. Use 3 letras + 3-4 números (ej: ABC1234 o ABC123D).");
            }

            if ($this->existePlaca($placaNorm)) {
                throw new Exception("La placa '{$placaNorm}' ya se encuentra registrada en el sistema.");
            }
        } else {
            $placaNorm = null;
        }

        $sql = "INSERT INTO vehiculos (unidad_id, persona_id, estacionamiento_id, tipo_vehiculo, placa, marca, modelo, color, observaciones)
                VALUES (:unidad_id, :persona_id, :estacionamiento_id, :tipo_vehiculo, :placa, :marca, NULL, :color, NULL)";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'unidad_id'          => intval($datos['unidad_id']),
                'persona_id'         => intval($datos['persona_id']),
                'estacionamiento_id' => !empty($datos['estacionamiento_id']) ? intval($datos['estacionamiento_id']) : null,
                'tipo_vehiculo'      => $tipo,
                'placa'              => $placaNorm,
                'marca'              => $marca !== '' ? $marca : null,
                'color'              => $color,
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
                WHERE v.unidad_id = :unidad_id AND (v.deleted_at IS NULL OR v.deleted_at = '')
                ORDER BY v.fecha_registro DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['unidad_id' => $unidadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un vehículo por ID (incluye los dados de baja para validar pertenencia).
     */
    public function obtenerPorId(int $id): array|false {
        $stmt = $this->db()->prepare("SELECT * FROM vehiculos WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Actualiza el tipo, color y marca de un vehículo existente.
     * No modifica la placa heredada ni el modelo/observaciones.
     */
    public function actualizarVehiculo(int $id, array $datos): bool {
        $tipo = trim((string)($datos['tipo_vehiculo'] ?? ''));
        if (!array_key_exists($tipo, self::TIPOS_VEHICULO)) {
            throw new Exception("El tipo de vehículo es obligatorio y debe ser Pequeño, Camioneta mediano o Camión tipo F-350.");
        }

        $color = trim((string)($datos['color'] ?? ''));
        if ($color === '') {
            throw new Exception("El color del vehículo es obligatorio.");
        }

        $marca = trim((string)($datos['marca'] ?? ''));

        $sql = "UPDATE vehiculos
                SET tipo_vehiculo = :tipo_vehiculo, marca = :marca, color = :color
                WHERE id = :id AND (deleted_at IS NULL OR deleted_at = '')";

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'tipo_vehiculo' => $tipo,
            'marca'         => $marca !== '' ? $marca : null,
            'color'         => $color,
            'id'            => $id,
        ]);
    }

    /**
     * Elimina un vehículo verificando propiedad de la unidad o permisos administrativos.
     */
    public function eliminarVehiculo(int $id, ?int $unidadId = null): bool {
        // Soft delete: set deleted_at instead of physically removing the record
        $sql = "UPDATE vehiculos SET deleted_at = NOW() WHERE id = :id AND (deleted_at IS NULL OR deleted_at = '')";
        $params = ['id' => $id];

        if ($unidadId !== null) {
            $sql .= " AND unidad_id = :unidad_id";
            $params['unidad_id'] = $unidadId;
        }

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute($params);
    }
}
