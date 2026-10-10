<?php
namespace App\Models;

/**
 * Importación del Reporte de Cartelera (Dotwood PC Condominios).
 *
 * Convierte las filas parseadas (saldo anterior + cuotas por mes) en facturas
 * del sistema, asegurando previamente los edificios ("Torre N") y las unidades
 * ("N-NN") que falten. Es idempotente por `numero_factura` en modo upsert: las
 * deudas ya existentes se actualizan con los valores del reporte en lugar de
 * duplicarse, respetando el soft-delete. Audita cada factura creada y agrega una
 * fila resumen por lote cuando hubo actualizaciones.
 */
class CarteleraModel extends BaseModel
{
    /**
     * Importa un lote de filas del Reporte de Cartelera dentro de una transacción.
     *
     * Upsert por `numero_factura` (CART-…): si la deuda ya existe activa se actualiza
     * (`saldo` = valor del reporte, `monto_total` = saldo + lo ya pagado, fechas = fecha
     * del reporte, estado `vencida`/`pagada` según el saldo); si está soft-deleted se
     * omite. Las deudas que desaparecen del reporte NO se eliminan (se protege el
     * historial de pagos); el total refleja el reporte para las unidades presentes.
     *
     * @param array  $filas         Filas del parser: [['unidad','saldo_anterior','meses'=>[mes=>monto],'total'], ...]
     * @param string $fechaEmision  Fecha de emisión del reporte (Y-m-d); se usa como emisión y vencimiento.
     * @param int    $adminId       Usuario que ejecuta la importación.
     * @param string $archivo       Nombre del PDF soporte almacenado en uploads/soportes/.
     * @param string|null $hash     SHA-256 del PDF (trazabilidad en auditoría).
     * @return array{edificios_creados: int, unidades_creadas: int, facturas_creadas: int, facturas_actualizadas: int, facturas_omitidas: int, total_importado: int}
     */
    public function importarLote(array $filas, string $fechaEmision, int $adminId, string $archivo, ?string $hash): array
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $fechaEmision);
        if ($fecha === false || $fecha->format('Y-m-d') !== $fechaEmision) {
            throw new \InvalidArgumentException('Fecha de emisión inválida para la importación: ' . $fechaEmision);
        }
        $anio = (int)$fecha->format('Y');

        $archivo = basename(str_replace('\\', '/', str_replace("\0", '', trim($archivo))));
        $hashReferencia = !empty($hash) ? substr($hash, 0, 12) : 'sin-hash';

        $resultado = [
            'edificios_creados' => 0,
            'unidades_creadas' => 0,
            'facturas_creadas' => 0,
            'facturas_actualizadas' => 0,
            'facturas_omitidas' => 0,
            'total_importado' => 0,
        ];
        if (empty($filas)) {
            return $resultado;
        }

        $db = $this->db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmtEdificioBuscar = $db->prepare("SELECT id FROM edificios WHERE nombre = :nombre LIMIT 1");
        $stmtEdificioCrear = $db->prepare("INSERT INTO edificios (nombre, descripcion, estado) VALUES (:nombre, :descripcion, 1)");
        $stmtUnidadBuscar = $db->prepare("SELECT id FROM unidades WHERE numero = :numero LIMIT 1");
        $stmtUnidadCrear = $db->prepare("INSERT INTO unidades (edificio_id, propietario_id, numero, cuota_mensual, estado) VALUES (:edificio_id, NULL, :numero, 0, 1)");
        $stmtFacturaBuscar = $db->prepare("SELECT id, deleted_at, monto_pagado FROM facturas WHERE numero_factura = :numero LIMIT 1");
        $stmtFacturaCrear = $db->prepare("
            INSERT INTO facturas
            (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado, observaciones)
            VALUES (:numero, :unidad_id, :mes, :anio, :fecha_emision, :fecha_vencimiento, :monto_total, 0, :saldo, 'vencida', :observaciones)
        ");
        $stmtFacturaActualizar = $db->prepare("
            UPDATE facturas
            SET monto_total = :total, saldo = :saldo, fecha_emision = :fe, fecha_vencimiento = :fv, estado = :estado
            WHERE id = :id
        ");
        $stmtLog = $db->prepare("
            INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_nuevo, detalles, ip_address)
            VALUES (:usuario_id, :admin_id, 'importar_cartelera', 'facturas', :registro_id, 'activa', :detalles, :ip)
        ");
        $stmtLogResumen = $db->prepare("
            INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_nuevo, detalles, ip_address)
            VALUES (:usuario_id, :admin_id, 'actualizar_cartelera', 'facturas', NULL, 'actualizada', :detalles, :ip)
        ");

        $cacheEdificios = []; // prefijo => id
        $cacheUnidades = [];  // etiqueta => id

        $db->beginTransaction();
        try {
            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    $resultado['facturas_omitidas']++;
                    continue;
                }

                $unidad = trim((string)($fila['unidad'] ?? ''));
                if (!preg_match('/^(\d{1,2})-(\d{1,2})$/', $unidad, $m)) {
                    $resultado['facturas_omitidas']++;
                    continue;
                }
                $prefijo = (int)$m[1];
                $nombreEdificio = 'Torre ' . $prefijo;

                // 1. Edificio
                if (!isset($cacheEdificios[$prefijo])) {
                    $stmtEdificioBuscar->execute(['nombre' => $nombreEdificio]);
                    $edificioId = $stmtEdificioBuscar->fetchColumn();
                    if ($edificioId === false) {
                        try {
                            $stmtEdificioCrear->execute([
                                'nombre' => $nombreEdificio,
                                'descripcion' => 'Importado de Reporte de Cartelera',
                            ]);
                            $edificioId = $db->lastInsertId();
                            $resultado['edificios_creados']++;
                        } catch (\PDOException $e) {
                            // Carrera con otra importación: reutilizar el existente
                            if ($e->getCode() != 23000 && ($e->errorInfo[1] ?? 0) !== 1062) {
                                throw $e;
                            }
                            $stmtEdificioBuscar->execute(['nombre' => $nombreEdificio]);
                            $edificioId = $stmtEdificioBuscar->fetchColumn();
                        }
                    }
                    $cacheEdificios[$prefijo] = (int)$edificioId;
                }
                $edificioId = $cacheEdificios[$prefijo];

                // 2. Unidad (numero es UNIQUE global: se usa la etiqueta completa "N-NN")
                if (!isset($cacheUnidades[$unidad])) {
                    $stmtUnidadBuscar->execute(['numero' => $unidad]);
                    $unidadId = $stmtUnidadBuscar->fetchColumn();
                    if ($unidadId === false) {
                        try {
                            $stmtUnidadCrear->execute([
                                'edificio_id' => $edificioId,
                                'numero' => $unidad,
                            ]);
                            $unidadId = $db->lastInsertId();
                            $resultado['unidades_creadas']++;
                        } catch (\PDOException $e) {
                            if ($e->getCode() != 23000 && ($e->errorInfo[1] ?? 0) !== 1062) {
                                throw $e;
                            }
                            $stmtUnidadBuscar->execute(['numero' => $unidad]);
                            $unidadId = $stmtUnidadBuscar->fetchColumn();
                        }
                    }
                    $cacheUnidades[$unidad] = (int)$unidadId;
                }
                $unidadId = $cacheUnidades[$unidad];

                // 3. Facturas del lote
                $items = [];
                $saldoAnterior = round((float)($fila['saldo_anterior'] ?? 0), 2);
                if ($saldoAnterior > 0) {
                    $items[] = ['mes' => 7, 'monto' => $saldoAnterior, 'concepto' => 'Saldo anterior'];
                }
                $meses = is_array($fila['meses'] ?? null) ? $fila['meses'] : [];
                foreach ($meses as $mes => $monto) {
                    $mes = (int)$mes;
                    $monto = round((float)$monto, 2);
                    if ($mes >= 1 && $mes <= 12 && $monto > 0) {
                        $items[] = ['mes' => $mes, 'monto' => $monto, 'concepto' => 'Cuota ' . nombreMes($mes) . '-' . $anio];
                    }
                }

                foreach ($items as $item) {
                    $numeroFactura = sprintf('CART-%s-%04d%02d', $unidad, $anio, $item['mes']);

                    $stmtFacturaBuscar->execute(['numero' => $numeroFactura]);
                    $existente = $stmtFacturaBuscar->fetch();
                    if ($existente !== false) {
                        // Soft-deleted: se respeta la baja lógica y no se resucita la deuda
                        if ($existente['deleted_at'] !== null) {
                            $resultado['facturas_omitidas']++;
                            continue;
                        }

                        // Upsert: el saldo refleja el reporte (no acumula); se conserva lo ya pagado
                        $saldo = round((float)$item['monto'], 2);
                        $stmtFacturaActualizar->execute([
                            'total' => round($saldo + (float)$existente['monto_pagado'], 2),
                            'saldo' => $saldo,
                            'fe' => $fechaEmision,
                            'fv' => $fechaEmision,
                            'estado' => $saldo > 0 ? 'vencida' : 'pagada',
                            'id' => (int)$existente['id'],
                        ]);
                        $resultado['facturas_actualizadas']++;
                        continue;
                    }

                    try {
                        $stmtFacturaCrear->execute([
                            'numero' => $numeroFactura,
                            'unidad_id' => $unidadId,
                            'mes' => $item['mes'],
                            'anio' => $anio,
                            'fecha_emision' => $fechaEmision,
                            'fecha_vencimiento' => $fechaEmision,
                            'monto_total' => $item['monto'],
                            'saldo' => $item['monto'],
                            'observaciones' => 'Migración Reporte de Cartelera (Dotwood)',
                        ]);
                    } catch (\PDOException $e) {
                        // Carrera con otra importación: la guarda única rechaza la fila
                        if ($e->getCode() == 23000 || ($e->errorInfo[1] ?? 0) === 1062) {
                            $resultado['facturas_omitidas']++;
                            continue;
                        }
                        throw $e;
                    }

                    $facturaId = (int)$db->lastInsertId();
                    $resultado['facturas_creadas']++;

                    $stmtLog->execute([
                        'usuario_id' => $adminId,
                        'admin_id' => $adminId,
                        'registro_id' => $facturaId,
                        'detalles' => sprintf(
                            'Reporte de Cartelera: %s | Unidad %s | %s | Bs. %s | PDF %s',
                            $archivo,
                            $unidad,
                            $item['concepto'],
                            number_format($item['monto'], 2, ',', '.'),
                            $hashReferencia
                        ),
                        'ip' => $ip,
                    ]);
                }
            }

            if ($resultado['facturas_actualizadas'] > 0) {
                $stmtLogResumen->execute([
                    'usuario_id' => $adminId,
                    'admin_id' => $adminId,
                    'detalles' => sprintf(
                        'Reporte de Cartelera: %s | %d deudas actualizadas (upsert sin acumulacion) | PDF %s',
                        $archivo,
                        $resultado['facturas_actualizadas'],
                        $hashReferencia
                    ),
                    'ip' => $ip,
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CarteleraModel] Error en importarLote: ' . $e->getMessage());
            throw $e;
        }

        $resultado['total_importado'] = $resultado['facturas_creadas'];
        return $resultado;
    }
}
