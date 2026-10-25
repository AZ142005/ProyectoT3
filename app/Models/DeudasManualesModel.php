<?php
namespace App\Models;

/**
 * Deudas manuales por unidad y período.
 *
 * Registra montos de deuda elegidos manualmente para unidades específicas
 * (alcance libre: unidades y/o edificios, o todo el condominio). Se aplican
 * al emitir la deuda del período: el monto manual sustituye la cuota
 * prorrateada de la unidad y se aplica completo por unidad (no se reparte
 * entre edificios ni unidades), materializándose en la factura FAC-… del
 * período vía el upsert de FacturasModel::crearFacturasMasivas.
 *
 * Upsert por (unidad_id, mes, anio): guardar un nuevo monto para la misma
 * unidad/período reemplaza el anterior (deleted_at = NULL). Las bajas son
 * lógicas (soft delete) y cada operación deja auditoría en log_auditoria.
 */
class DeudasManualesModel extends BaseModel
{
    protected string $table = 'deudas_manuales';

    /**
     * Guarda (upsert) el monto de deuda manual de un lote de unidades en un período.
     *
     * Por cada unidad: si no existía fila se inserta; si existía (activa o con
     * baja lógica) se reemplaza el monto y se reactiva. Se registra una fila de
     * auditoría por lote.
     *
     * @param int   $mes       Mes del período (1-12)
     * @param int   $anio      Año del período
     * @param int[] $unidadIds Unidades destino (se deduplican)
     * @param float $monto     Monto exacto por unidad (sin repartir)
     * @param int   $adminId   Usuario que ejecuta la acción
     * @return int Unidades guardadas
     */
    public function guardarLote(int $mes, int $anio, array $unidadIds, float $monto, int $adminId): int
    {
        $unidadIds = array_values(array_unique(array_map('intval', $unidadIds)));
        $monto = round($monto, 2);
        if (empty($unidadIds) || $monto <= 0) {
            return 0;
        }

        $db = $this->db();
        $iniciaTransaccion = !$db->inTransaction();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            if ($iniciaTransaccion) {
                $db->beginTransaction();
            }

            $stmtUpsert = $db->prepare("
                INSERT INTO deudas_manuales (unidad_id, mes, anio, monto, admin_id)
                VALUES (:unidad_id, :mes, :anio, :monto, :admin_id)
                ON DUPLICATE KEY UPDATE
                    monto = VALUES(monto),
                    admin_id = VALUES(admin_id),
                    deleted_at = NULL,
                    updated_at = NOW()
            ");

            foreach ($unidadIds as $unidadId) {
                $stmtUpsert->execute([
                    'unidad_id' => $unidadId,
                    'mes'       => $mes,
                    'anio'      => $anio,
                    'monto'     => $monto,
                    'admin_id'  => $adminId,
                ]);
            }

            $this->registrarAuditoria(
                $db,
                $adminId,
                'deuda_manual',
                sprintf(
                    'Deudas manuales guardadas: %d unidad(es) a Bs. %s c/u | Período %02d/%d',
                    count($unidadIds),
                    number_format($monto, 2, ',', '.'),
                    $mes,
                    $anio
                ),
                $ip
            );

            if ($iniciaTransaccion) {
                $db->commit();
            }
            return count($unidadIds);
        } catch (\Throwable $e) {
            if ($iniciaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[DeudasManualesModel] Error en guardarLote: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deudas manuales activas de un período, ordenadas por edificio y unidad.
     *
     * @return array Filas con id, unidad_id, mes, anio, monto, unidad_numero, edificio_nombre
     */
    public function obtenerPorPeriodo(int $mes, int $anio): array
    {
        $stmt = $this->db()->prepare("
            SELECT d.id, d.unidad_id, d.mes, d.anio, d.monto,
                   u.numero AS unidad_numero, e.nombre AS edificio_nombre
            FROM deudas_manuales d
            INNER JOIN unidades u ON u.id = d.unidad_id
            LEFT JOIN edificios e ON e.id = u.edificio_id
            WHERE d.mes = :mes AND d.anio = :anio AND d.deleted_at IS NULL
            ORDER BY e.nombre ASC, u.numero ASC
        ");
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        return $stmt->fetchAll();
    }

    /**
     * Mapa unidad_id => monto de las deudas manuales activas de un período.
     *
     * @return float[]
     */
    public function obtenerMapaPorPeriodo(int $mes, int $anio): array
    {
        $stmt = $this->db()->prepare("
            SELECT unidad_id, monto
            FROM deudas_manuales
            WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL
        ");
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);

        $mapa = [];
        foreach ($stmt->fetchAll() as $fila) {
            $mapa[(int)$fila['unidad_id']] = round(floatval($fila['monto']), 2);
        }
        return $mapa;
    }

    /**
     * Elimina lógicamente la deuda manual de una unidad en un período.
     *
     * @return bool true si había una fila activa y quedó dada de baja
     */
    public function eliminar(int $unidadId, int $mes, int $anio): bool
    {
        $db = $this->db();
        $iniciaTransaccion = !$db->inTransaction();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            if ($iniciaTransaccion) {
                $db->beginTransaction();
            }

            $stmt = $db->prepare("
                UPDATE deudas_manuales
                SET deleted_at = NOW()
                WHERE unidad_id = :unidad_id AND mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmt->execute(['unidad_id' => $unidadId, 'mes' => $mes, 'anio' => $anio]);
            $eliminadas = $stmt->rowCount();

            if ($eliminadas > 0) {
                $this->registrarAuditoria(
                    $db,
                    \App\Core\Auth::id() ?? 1,
                    'eliminar_deuda_manual',
                    sprintf('Deuda manual eliminada: unidad %d | Período %02d/%d', $unidadId, $mes, $anio),
                    $ip
                );
            }

            if ($iniciaTransaccion) {
                $db->commit();
            }
            return $eliminadas > 0;
        } catch (\Throwable $e) {
            if ($iniciaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[DeudasManualesModel] Error en eliminar: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Elimina lógicamente todas las deudas manuales activas de un período.
     *
     * @return int Filas dadas de baja
     */
    public function eliminarPeriodo(int $mes, int $anio): int
    {
        $db = $this->db();
        $iniciaTransaccion = !$db->inTransaction();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            if ($iniciaTransaccion) {
                $db->beginTransaction();
            }

            $stmt = $db->prepare("
                UPDATE deudas_manuales
                SET deleted_at = NOW()
                WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmt->execute(['mes' => $mes, 'anio' => $anio]);
            $eliminadas = $stmt->rowCount();

            if ($eliminadas > 0) {
                $this->registrarAuditoria(
                    $db,
                    \App\Core\Auth::id() ?? 1,
                    'limpiar_deudas_manuales',
                    sprintf('Deudas manuales del período eliminadas: %d unidad(es) | Período %02d/%d', $eliminadas, $mes, $anio),
                    $ip
                );
            }

            if ($iniciaTransaccion) {
                $db->commit();
            }
            return $eliminadas;
        } catch (\Throwable $e) {
            if ($iniciaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[DeudasManualesModel] Error en eliminarPeriodo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra una fila de auditoría para el lote u operación ejecutada.
     */
    private function registrarAuditoria(\PDO $db, int $adminId, string $accion, string $detalles, ?string $ip): void
    {
        $stmt = $db->prepare("
            INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_nuevo, detalles, ip_address)
            VALUES (:usuario_id, :admin_id, :accion, 'deudas_manuales', NULL, 'activa', :detalles, :ip)
        ");
        $stmt->execute([
            'usuario_id' => $adminId,
            'admin_id'   => $adminId,
            'accion'     => $accion,
            'detalles'   => $detalles,
            'ip'         => $ip,
        ]);
    }
}
