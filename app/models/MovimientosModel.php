<?php
namespace App\Models;

use PDO;
use Exception;

class MovimientosModel extends BaseModel {

    protected string $table = 'movimientos_cuenta';

    /**
     * Registra un movimiento inmutable en el libro mayor de una unidad habitacional con bloqueo pesimista.
     *
     * Idempotencia: cuando se provee $referenciaId, se calcula una huella
     * SHA-256 sobre (unidad, tipo, referencia_tipo, referencia_id, monto). Si
     * ya existe un movimiento con esa huella (uk_mov_huella), se retorna su ID
     * sin insertar de nuevo. Los movimientos sin referencia_id no se deduplican.
     *
     * @param int $unidadId
     * @param string $tipo 'cargo_factura' | 'abono_pago' | 'ajuste'
     * @param float $monto
     * @param string $descripcion
     * @param int|null $referenciaId
     * @param string|null $referenciaTipo 'pago' | 'comprobante' | null
     * @return int ID del movimiento insertado (o existente si la huella ya estaba registrada)
     * @throws Exception
     */
    public function registrarMovimiento(int $unidadId, string $tipo, float $monto, string $descripcion, ?int $referenciaId = null, ?string $referenciaTipo = null): int {
        $tiposValidos = ['cargo_factura', 'abono_pago', 'ajuste'];
        if (!in_array($tipo, $tiposValidos)) {
            throw new Exception("Tipo de movimiento inválido.");
        }

        $monto = floatval($monto);
        if ($monto <= 0 && $tipo !== 'ajuste') {
            throw new Exception("El monto del movimiento debe ser superior a 0.");
        }

        // 6D.3: Límite de 20 ajustes manuales por unidad por día
        if ($tipo === 'ajuste') {
            $db = $this->db();
            $stmtCount = $db->prepare(
                "SELECT COUNT(*) as cnt FROM movimientos_cuenta 
                 WHERE unidad_id = :uid AND tipo = 'ajuste' 
                 AND DATE(fecha_movimiento) = CURDATE()"
            );
            $stmtCount->execute(['uid' => $unidadId]);
            $cnt = intval($stmtCount->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            if ($cnt >= 10) {
                throw new Exception("Se ha alcanzado el límite de 10 ajustes por día para esta unidad.");
            }
        }

        // Huella de idempotencia: solo para movimientos con referencia explícita.
        $huella = null;
        if ($referenciaId !== null) {
            $huella = hash('sha256', implode('|', [
                $unidadId,
                $tipo,
                $referenciaTipo ?? '',
                $referenciaId,
                number_format($monto, 2, '.', '')
            ]));
        }

        $db = $this->db();
        $iniciaTransaccionInterna = !$db->inTransaction();

        try {
            if ($iniciaTransaccionInterna) {
                $db->beginTransaction();
            }

            // Bloqueo pesimista para obtener el último saldo de la unidad
            $stmtSaldo = $db->prepare("
                SELECT saldo_posterior FROM movimientos_cuenta 
                WHERE unidad_id = :unidad_id 
                ORDER BY id DESC LIMIT 1 FOR UPDATE
            ");
            $stmtSaldo->execute(['unidad_id' => $unidadId]);
            $fila = $stmtSaldo->fetch(PDO::FETCH_ASSOC);

            $saldoAnterior = $fila ? round(floatval($fila['saldo_posterior']), 2) : 0.00;

            // En contabilidad de condominios:
            // - 'cargo_factura': incrementa el saldo deudor (+monto)
            // - 'abono_pago': reduce el saldo deudor (-monto)
            // - 'ajuste': ajuste manual
            if ($tipo === 'cargo_factura') {
                $saldoPosterior = round($saldoAnterior + $monto, 2);
            } elseif ($tipo === 'abono_pago') {
                $saldoPosterior = round($saldoAnterior - $monto, 2);
            } else {
                $saldoPosterior = round($saldoAnterior + $monto, 2);
            }

            $sqlInsert = "
                INSERT INTO movimientos_cuenta 
                (unidad_id, tipo, monto, saldo_anterior, saldo_posterior, referencia_id, referencia_tipo, huella, descripcion)
                VALUES (:unidad_id, :tipo, :monto, :saldo_ant, :saldo_post, :ref_id, :ref_tipo, :huella, :desc)
            ";

            $stmtInsert = $db->prepare($sqlInsert);
            $stmtInsert->execute([
                'unidad_id'  => $unidadId,
                'tipo'       => $tipo,
                'monto'      => $monto,
                'saldo_ant'  => $saldoAnterior,
                'saldo_post' => $saldoPosterior,
                'ref_id'     => $referenciaId,
                'ref_tipo'   => $referenciaTipo,
                'huella'     => $huella,
                'desc'       => trim($descripcion)
            ]);

            $movimientoId = intval($db->lastInsertId());

            if ($iniciaTransaccionInterna) {
                $db->commit();
            }

            return $movimientoId;
        } catch (\PDOException $e) {
            // Duplicado de huella: movimiento ya registrado → no-op idempotente.
            // Detección estructurada, sin depender del texto del mensaje:
            // SQLSTATE 23000 (integridad) + ER_DUP_ENTRY = 1062 (MySQL/MariaDB).
            // La confirmación real es la fila existente por huella: si no
            // existe, la violación es de otra restricción y la excepción se re-lanza.
            $esDuplicadoHuella = ($e->getCode() == 23000)
                && $huella !== null
                && (int)($e->errorInfo[1] ?? 0) === 1062;

            if ($esDuplicadoHuella) {
                $stmtExistente = $db->prepare("SELECT id FROM movimientos_cuenta WHERE huella = :huella LIMIT 1");
                $stmtExistente->execute(['huella' => $huella]);
                $existente = $stmtExistente->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    if ($iniciaTransaccionInterna && $db->inTransaction()) {
                        $db->commit();
                    }
                    return intval($existente['id']);
                }
            }

            if ($iniciaTransaccionInterna && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al registrar movimiento en libro mayor: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            if ($iniciaTransaccionInterna && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al registrar movimiento en libro mayor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene el historial cronológico paginado del libro mayor para una unidad habitacional.
     */
    public function obtenerHistorialUnidad(int $unidadId, int $pagina = 1, int $porPagina = 20): array {
        $baseSql = "
            SELECT m.*, u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM movimientos_cuenta m
            INNER JOIN unidades u ON m.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE m.unidad_id = :unidad_id
        ";

        $countSql = "SELECT COUNT(*) AS total FROM movimientos_cuenta WHERE unidad_id = :unidad_id";

        return $this->paginate($baseSql, $countSql, ['unidad_id' => $unidadId], $pagina, $porPagina, 'm.fecha_movimiento DESC, m.id DESC');
    }

    /**
     * Obtiene el saldo actual consolidado de una unidad.
     */
    public function obtenerSaldoActualUnidad(int $unidadId): float {
        $db = $this->db();
        $stmt = $db->prepare("SELECT saldo_posterior FROM movimientos_cuenta WHERE unidad_id = :uid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $unidadId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? round(floatval($fila['saldo_posterior']), 2) : 0.00;
    }
}
