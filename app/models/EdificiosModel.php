<?php
namespace App\Models;

use App\Core\Auth;

class EdificiosModel extends BaseModel {
    protected string $table = 'edificios';

    /** Motivo de rechazo: unidades con propietario/residentes o deudas asociadas. */
    private const MOTIVO_PROPIETARIO_O_DEUDAS = 'tiene unidades con propietario o deudas asociadas.';

    /** Motivo de rechazo: gastos del condominio imputados al edificio. */
    private const MOTIVO_GASTOS_ASOCIADOS = 'tiene gastos del condominio asociados.';

    public function getActivos(int $limite = 500) {
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            WHERE e.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
            LIMIT {$limite}
        ";
        return $this->db()->query($sql)->fetchAll();
    }

    public function getAll() {
        $sql = "
            SELECT e.*, COUNT(u.id) as total_unidades
            FROM edificios e
            LEFT JOIN unidades u ON u.edificio_id = e.id AND u.estado = 1
            GROUP BY e.id
            ORDER BY e.nombre ASC
            LIMIT 500
        ";
        return $this->db()->query($sql)->fetchAll();
    }

    public function nombreExists($nombre, $excludeId = null) {
        return $this->exists('edificios', 'nombre', $nombre, $excludeId);
    }

    public function create($tableTablaOData, ?array $data = null): string|false {
        $actualData = is_array($tableTablaOData) ? $tableTablaOData : ($data ?? []);
        $actualData['estado'] = 1;
        return parent::create('edificios', $actualData);
    }

    public function update($tableTablaOId, $idOData = null, ?array $data = null): bool {
        $id = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$idOData;
        $actualData = is_array($idOData) ? $idOData : ($data ?? []);
        return parent::update('edificios', $id, $actualData);
    }

    public function getById($tableTablaOId, ?int $id = null): array|false {
        $actualId = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        return parent::getById('edificios', $actualId);
    }

    public function toggleEstado($tableTablaOId, ?int $id = null): bool {
        $actualId = (is_int($tableTablaOId) || is_numeric($tableTablaOId)) ? (int)$tableTablaOId : (int)$id;
        return parent::toggleEstado('edificios', $actualId);
    }

    /**
     * Indica si una consulta COUNT(*) con parámetro :id devuelve al menos un registro.
     */
    private function existeDependencia(string $sql, int $id): bool {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Elimina un edificio solo si está "limpio": sus unidades no tienen
     * propietario, no hay personas registradas en ellas y no existen registros
     * financieros (facturas, movimientos de cuenta o pagos) asociados a sus
     * unidades. Tampoco debe tener gastos del condominio imputados al edificio.
     *
     * La operación es transaccional: valida dependencias, elimina los registros
     * hijos no financieros de sus unidades (estacionamientos, solicitudes de
     * registro, vehículos y destinos de comunicados), luego las unidades y,
     * finalmente, el edificio. Registra el evento en log_auditoria dentro de la
     * misma transacción.
     *
     * @param int $id Identificador del edificio.
     * @return array{ok: bool, motivo: ?string} motivo describe el bloqueo cuando ok=false.
     */
    public function eliminarSiLimpio(int $id): array {
        $db = $this->db();
        $id = (int)$id;

        $edificio = $id > 0 ? $this->getById($id) : false;
        if (!$edificio) {
            return ['ok' => false, 'motivo' => 'Edificio no encontrado.'];
        }

        $db->beginTransaction();
        try {
            // Unidades con propietario asignado o con personas (residentes) vinculadas.
            $conPropietarioOResidentes = $this->existeDependencia(
                "SELECT COUNT(*) FROM unidades u
                 LEFT JOIN personas p ON p.unidad_id = u.id
                 WHERE u.edificio_id = :id
                   AND (u.propietario_id IS NOT NULL OR p.id IS NOT NULL)",
                $id
            );
            if ($conPropietarioOResidentes) {
                $db->rollBack();
                return ['ok' => false, 'motivo' => self::MOTIVO_PROPIETARIO_O_DEUDAS];
            }

            // Registros financieros vinculados a sus unidades (incluye soft-deleted:
            // los FK siguen apuntando a la unidad).
            $conDeudas = $this->existeDependencia(
                    "SELECT COUNT(*) FROM facturas f INNER JOIN unidades u ON u.id = f.unidad_id WHERE u.edificio_id = :id", $id)
                || $this->existeDependencia(
                    "SELECT COUNT(*) FROM movimientos_cuenta m INNER JOIN unidades u ON u.id = m.unidad_id WHERE u.edificio_id = :id", $id)
                || $this->existeDependencia(
                    "SELECT COUNT(*) FROM pagos pg INNER JOIN unidades u ON u.id = pg.unidad_id WHERE u.edificio_id = :id", $id);
            if ($conDeudas) {
                $db->rollBack();
                return ['ok' => false, 'motivo' => self::MOTIVO_PROPIETARIO_O_DEUDAS];
            }

            // Gastos del condominio imputados al edificio (FK gastos_comunes.edificio_id
            // los desvincularía en silencio con SET NULL).
            if ($this->existeDependencia("SELECT COUNT(*) FROM gastos_comunes g WHERE g.edificio_id = :id", $id)) {
                $db->rollBack();
                return ['ok' => false, 'motivo' => self::MOTIVO_GASTOS_ASOCIADOS];
            }

            // 1) Registros hijos no financieros de sus unidades.
            $stmt = $db->prepare("DELETE est FROM estacionamientos est INNER JOIN unidades u ON u.id = est.unidad_id WHERE u.edificio_id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $db->prepare("DELETE sr FROM solicitudes_registro sr INNER JOIN unidades u ON u.id = sr.unidad_id WHERE u.edificio_id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $db->prepare("DELETE v FROM vehiculos v INNER JOIN unidades u ON u.id = v.unidad_id WHERE u.edificio_id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $db->prepare("DELETE cd FROM comunicados_destinos cd INNER JOIN unidades u ON u.id = cd.unidad_id WHERE u.edificio_id = :id");
            $stmt->execute(['id' => $id]);

            // 2) Unidades del edificio.
            $stmt = $db->prepare("DELETE FROM unidades WHERE edificio_id = :id");
            $stmt->execute(['id' => $id]);
            $unidadesEliminadas = $stmt->rowCount();

            // 3) Edificio.
            $stmt = $db->prepare("DELETE FROM edificios WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // 4) Auditoría en la misma transacción.
            $adminId = Auth::id();
            $stmt = $db->prepare("
                INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_nuevo, detalles, ip_address)
                VALUES (:usuario_id, :admin_id, 'eliminar_edificio', 'edificios', :registro_id, 'eliminado', :detalles, :ip)
            ");
            $stmt->execute([
                'usuario_id'  => $adminId,
                'admin_id'    => $adminId,
                'registro_id' => $id,
                'detalles'    => 'Edificio eliminado: «' . $edificio['nombre'] . '» | Unidades eliminadas: ' . $unidadesEliminadas,
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $db->commit();
            return ['ok' => true, 'motivo' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[EDIFICIOS] Error al eliminar el edificio ' . $id . ': ' . $e->getMessage());
            return ['ok' => false, 'motivo' => 'no se pudo completar la eliminación.'];
        }
    }
}