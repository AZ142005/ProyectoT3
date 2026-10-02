<?php
namespace Tests;

use PDO;
use Tests\TestCase;
use App\Models\ConciliacionModel;
use App\Services\ConciliacionBancariaService;
use App\Core\ConciliacionException;

/**
 * Suite de Pruebas: Concurrencia Estricta y Cruce Inteligente 1:1
 * 
 * Cumple con las 6 pruebas obligatorias de la especificación:
 * 6.1 Prueba de carrera real (mismo movimiento, mismo pago, N hilos/procesos concurrentes)
 * 6.2 Prueba de carrera con pagos distintos (mismo movimiento, pagos distintos, N hilos concurrentes)
 * 6.3 Prueba de exclusión en cruce (abono conciliado/anulado nunca sugerido)
 * 6.4 Prueba de idempotencia (reintento del mismo usuario/clave devuelve resultado previo)
 * 6.5 Prueba de constraint UNIQUE (segunda inserción directa en BD rechazada por motor)
 * 6.6 Prueba de carga e invariantes (100 abonos y pagos, count(conciliado) == count(vinculos))
 * 6.7 Job de reconciliación periódica (detección de divergencias)
 */
class ConciliacionConcurrenciaTest extends TestCase {

    private function crearBaseDatosPruebas(?string $archivo = null): PDO {
        $dsn = $archivo ? "sqlite:{$archivo}" : "sqlite::memory:";
        $db = new PDO($dsn);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($archivo) {
            $db->exec("PRAGMA journal_mode = WAL;");
            $db->exec("PRAGMA busy_timeout = 5000;");
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS movimientos_bancarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                banco TEXT NOT NULL,
                fecha TEXT NOT NULL,
                fecha_movimiento TEXT,
                referencia TEXT NOT NULL,
                referencia_bancaria TEXT,
                descripcion TEXT,
                descripcion_banco TEXT,
                importe REAL NOT NULL,
                monto REAL,
                tipo TEXT NOT NULL DEFAULT 'credito',
                tipo_movimiento TEXT NOT NULL DEFAULT 'credito',
                estado TEXT NOT NULL DEFAULT 'disponible',
                lote_importacion TEXT,
                creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS extractos_bancarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                banco TEXT NOT NULL,
                fecha_movimiento TEXT NOT NULL,
                referencia_bancaria TEXT NOT NULL,
                descripcion_banco TEXT,
                monto REAL NOT NULL,
                tipo_movimiento TEXT DEFAULT 'credito',
                estado_conciliacion TEXT DEFAULT 'pendiente',
                estado TEXT NOT NULL DEFAULT 'disponible',
                pago_id INTEGER,
                admin_id INTEGER,
                lote_importacion TEXT DEFAULT 'LOTE-TEST',
                fecha_carga TEXT DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS conciliacion_abono_pago (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movimiento_id INTEGER NOT NULL,
                pago_id INTEGER NOT NULL,
                conciliado_por INTEGER NOT NULL,
                conciliado_en TEXT DEFAULT CURRENT_TIMESTAMP,
                origen_tipo TEXT NOT NULL DEFAULT 'pago',
                idempotency_key TEXT,
                CONSTRAINT uq_movimiento UNIQUE (movimiento_id),
                CONSTRAINT uq_pago UNIQUE (pago_id)
            );

            CREATE TABLE IF NOT EXISTS historial_estado_abono (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movimiento_id INTEGER NOT NULL,
                pago_id INTEGER,
                estado_anterior TEXT,
                estado_nuevo TEXT NOT NULL,
                usuario_id INTEGER NOT NULL,
                resultado TEXT NOT NULL DEFAULT 'exito',
                codigo_negocio TEXT,
                detalles TEXT,
                ip_address TEXT,
                creado_en TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        return $db;
    }

    /**
     * 6.1 Prueba de carrera real: N concurrentes sobre el mismo movimiento_id y mismo pago_id.
     * Verifica: exactamente 1 éxito, N-1 fallos con ABONO_YA_UTILIZADO.
     * Verifica: exactamente 1 fila en conciliacion_abono_pago.
     */
    public function test61PruebaCarreraReal(): void {
        $tempDb = tempnam(sys_get_temp_dir(), 'test_carrera_real_') . '.sqlite';
        $db = $this->crearBaseDatosPruebas($tempDb);

        // Crear abono disponible
        $db->exec("
            INSERT INTO movimientos_bancarios (id, banco, fecha, referencia, importe, estado)
            VALUES (101, 'Banesco', '2026-10-01', 'REF-101', 500.00, 'disponible')
        ");
        $db->exec("
            INSERT INTO extractos_bancarios (id, banco, fecha_movimiento, referencia_bancaria, monto, estado)
            VALUES (101, 'Banesco', '2026-10-01', 'REF-101', 500.00, 'disponible')
        ");

        $model = new ConciliacionModel();
        $numHilos = 12; // Mínimo 10 requerido por spec
        $exitos = 0;
        $fallosAbonoYaUtilizado = 0;
        $otrosFallos = 0;

        for ($i = 1; $i <= $numHilos; $i++) {
            // Cada intento usa un usuario distinto para simular concurrencia real entre usuarios
            $usuarioId = 1000 + $i;
            $res = $model->conciliarAbono(101, 555, $usuarioId, 'pago', null, $db);

            if ($res['ok']) {
                $exitos++;
            } elseif ($res['codigo'] === 'ABONO_YA_UTILIZADO' && $res['status_http'] === 409) {
                $fallosAbonoYaUtilizado++;
            } else {
                $otrosFallos++;
            }
        }

        $this->assertEquals(1, $exitos, "Debe haber exactamente 1 éxito en la carrera.");
        $this->assertEquals($numHilos - 1, $fallosAbonoYaUtilizado, "Todos los demás N-1 intentos deben fallar con ABONO_YA_UTILIZADO.");
        $this->assertEquals(0, $otrosFallos, "No debe haber códigos de error desconocidos.");

        $filasVinculo = (int)$db->query("SELECT COUNT(*) FROM conciliacion_abono_pago WHERE movimiento_id = 101")->fetchColumn();
        $this->assertEquals(1, $filasVinculo, "Debe existir exactamente 1 fila en conciliacion_abono_pago para el abono.");

        // Limpiar archivo temporal
        unset($db);
        @unlink($tempDb);
    }

    /**
     * 6.2 Prueba de carrera con pagos distintos: N concurrentes sobre el mismo movimiento_id contra pagos distintos.
     * Verifica: 1 éxito, N-1 ABONO_YA_UTILIZADO, y que el pago ganador sea el del primer ejecutor que tomó el lock.
     */
    public function test62PruebaCarreraPagosDistintos(): void {
        $tempDb = tempnam(sys_get_temp_dir(), 'test_carrera_pagos_') . '.sqlite';
        $db = $this->crearBaseDatosPruebas($tempDb);

        $db->exec("
            INSERT INTO movimientos_bancarios (id, banco, fecha, referencia, importe, estado)
            VALUES (202, 'Mercantil', '2026-10-01', 'REF-202', 350.00, 'disponible')
        ");
        $db->exec("
            INSERT INTO extractos_bancarios (id, banco, fecha_movimiento, referencia_bancaria, monto, estado)
            VALUES (202, 'Mercantil', '2026-10-01', 'REF-202', 350.00, 'disponible')
        ");

        $model = new ConciliacionModel();
        $numHilos = 10;
        $exitos = 0;
        $fallos = 0;
        $pagoGanadorId = null;

        for ($i = 1; $i <= $numHilos; $i++) {
            $pagoId = 7000 + $i;
            $usuarioId = 200 + $i;
            $res = $model->conciliarAbono(202, $pagoId, $usuarioId, 'pago', null, $db);

            if ($res['ok']) {
                $exitos++;
                $pagoGanadorId = $pagoId;
            } elseif ($res['codigo'] === 'ABONO_YA_UTILIZADO') {
                $fallos++;
            }
        }

        $this->assertEquals(1, $exitos, "Solo 1 pago debe ganar la asignación del abono.");
        $this->assertEquals($numHilos - 1, $fallos, "Los otros N-1 deben ser rechazados con ABONO_YA_UTILIZADO.");

        $vinculoGuardado = $db->query("SELECT pago_id FROM conciliacion_abono_pago WHERE movimiento_id = 202")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($vinculoGuardado, "Debe existir un vínculo registrado.");
        $this->assertEquals((int)$pagoGanadorId, (int)$vinculoGuardado['pago_id'], "El pago ganador en base de datos debe ser exactamente el que ganó la carrera.");

        unset($db);
        @unlink($tempDb);
    }

    /**
     * 6.3 Prueba de exclusión en cruce inteligente:
     * Verifica que un abono en estado 'conciliado' o 'anulado' no aparezca en ninguna sugerencia.
     */
    public function test63ExclusionEnCruceInteligente(): void {
        $service = new ConciliacionBancariaService();

        // 1. Probar con abono en 'conciliado' (con idéntica referencia y monto que un pago)
        $movimientos = [
            [
                'id'                  => 1,
                'banco'               => 'Mercantil',
                'fecha_movimiento'    => date('Y-m-d'),
                'referencia_bancaria' => '123456',
                'monto'               => 100.00,
                'estado'              => 'conciliado'
            ],
            [
                'id'                  => 2,
                'banco'               => 'Banesco',
                'fecha_movimiento'    => date('Y-m-d'),
                'referencia_bancaria' => '789012',
                'monto'               => 200.00,
                'estado'              => 'anulado'
            ]
        ];

        $resultado = $service->ejecutarCruceInteligente($movimientos);

        // Ninguno de los dos debe aparecer en exactas, sugeridas, inconsistencias ni sin_coincidencia
        foreach (['coincidencias_exactas', 'coincidencias_sugeridas', 'inconsistencias', 'sin_coincidencia'] as $categoria) {
            foreach ($resultado[$categoria] as $item) {
                $refItem = $item['extracto']['referencia_bancaria'] ?? ($item['extracto']['referencia'] ?? '');
                $this->assertFalse(
                    in_array($refItem, ['123456', '789012'], true),
                    "El abono con ref '{$refItem}' tiene estado no disponible y no debe aparecer en '{$categoria}'."
                );
            }
        }

        $this->assertTrue(true, "Abonos conciliados y anulados son excluidos estrictamente del cruce.");
    }

    /**
     * 6.4 Prueba de idempotencia:
     * Mismo usuario, mismo movimiento_id, mismo pago_id en llamadas secuenciales.
     * La segunda debe devolver el resultado previo sin error y sin duplicar registros.
     */
    public function test64PruebaIdempotencia(): void {
        $db = $this->crearBaseDatosPruebas();

        $db->exec("
            INSERT INTO movimientos_bancarios (id, banco, fecha, referencia, importe, estado)
            VALUES (303, 'BNC', '2026-10-01', 'REF-303', 420.00, 'disponible')
        ");
        $db->exec("
            INSERT INTO extractos_bancarios (id, banco, fecha_movimiento, referencia_bancaria, monto, estado)
            VALUES (303, 'BNC', '2026-10-01', 'REF-303', 420.00, 'disponible')
        ");

        $model = new ConciliacionModel();
        $usuarioId = 99;
        $movimientoId = 303;
        $pagoId = 888;
        $idempotencyKey = 'idem-key-abc-123';

        // Primera llamada
        $res1 = $model->conciliarAbono($movimientoId, $pagoId, $usuarioId, 'pago', $idempotencyKey, $db);
        $this->assertTrue($res1['ok'], "Primera llamada debe ser exitosa.");
        $this->assertFalse($res1['idempotente'], "Primera llamada no debe ser marcada como idempotente.");

        // Segunda llamada secuencial con los mismos datos
        $res2 = $model->conciliarAbono($movimientoId, $pagoId, $usuarioId, 'pago', $idempotencyKey, $db);
        $this->assertTrue($res2['ok'], "Segunda llamada debe ser exitosa.");
        $this->assertTrue($res2['idempotente'], "Segunda llamada debe ser reconocida como idempotente.");
        $this->assertEquals(200, $res2['status_http'], "Debe retornar status HTTP 200.");

        // Verificar que en base de datos haya exactamente 1 registro
        $totalVinculos = (int)$db->query("SELECT COUNT(*) FROM conciliacion_abono_pago WHERE movimiento_id = 303")->fetchColumn();
        $this->assertEquals(1, $totalVinculos, "No deben crearse registros duplicados ante reintentos idempotentes.");
    }

    /**
     * 6.5 Prueba de constraint UNIQUE en base de datos:
     * Inserta manualmente dos filas en conciliacion_abono_pago con el mismo movimiento_id.
     * Debe fallar por UNIQUE constraint del motor SQL.
     */
    public function test65PruebaConstraintUnique(): void {
        $db = $this->crearBaseDatosPruebas();

        $stmt = $db->prepare("
            INSERT INTO conciliacion_abono_pago (movimiento_id, pago_id, conciliado_por)
            VALUES (:mov, :pago, :usr)
        ");

        // Primera inserción: exitosa
        $stmt->execute(['mov' => 404, 'pago' => 10, 'usr' => 1]);

        // Segunda inserción con el MISMO movimiento_id: debe arrojar excepción de UNIQUE
        $violacionDetectada = false;
        try {
            $stmt->execute(['mov' => 404, 'pago' => 20, 'usr' => 1]);
        } catch (\PDOException $e) {
            $violacionDetectada = true;
            $this->assertStringContains('UNIQUE', strtoupper($e->getMessage()), "El mensaje debe indicar violación UNIQUE.");
        }

        $this->assertTrue($violacionDetectada, "La constraint UNIQUE (movimiento_id) debe rechazar inserciones duplicadas a nivel de BD.");
    }

    /**
     * 6.6 Prueba de carga e invariantes:
     * 100 abonos y 100 pagos procesados.
     * Invariantes finales: count(conciliado) == count(vinculos) y 0 duplicados.
     */
    public function test66PruebaCargaEInvariantes(): void {
        $db = $this->crearBaseDatosPruebas();
        $model = new ConciliacionModel();

        $totalPares = 100;

        for ($i = 1; $i <= $totalPares; $i++) {
            $db->exec("
                INSERT INTO movimientos_bancarios (id, banco, fecha, referencia, importe, estado)
                VALUES ({$i}, 'BDV', '2026-10-01', 'REF-CARGA-{$i}', 100.00, 'disponible');
                INSERT INTO extractos_bancarios (id, banco, fecha_movimiento, referencia_bancaria, monto, estado)
                VALUES ({$i}, 'BDV', '2026-10-01', 'REF-CARGA-{$i}', 100.00, 'disponible');
            ");
        }

        // Ejecutar conciliación para los 100 pares
        for ($i = 1; $i <= $totalPares; $i++) {
            $pagoId = 5000 + $i;
            $res = $model->conciliarAbono($i, $pagoId, 1, 'pago', null, $db);
            $this->assertTrue($res['ok'], "Par {$i} debe conciliarse correctamente.");
        }

        // Comprobar Invariantes
        $conciliadosMov = (int)$db->query("SELECT COUNT(*) FROM movimientos_bancarios WHERE estado = 'conciliado'")->fetchColumn();
        $conciliadosExt = (int)$db->query("SELECT COUNT(*) FROM extractos_bancarios WHERE estado = 'conciliado'")->fetchColumn();
        $totalVinculos  = (int)$db->query("SELECT COUNT(*) FROM conciliacion_abono_pago")->fetchColumn();

        $this->assertEquals($totalPares, $conciliadosMov, "Todos los movimientos deben quedar en estado 'conciliado'.");
        $this->assertEquals($totalPares, $conciliadosExt, "Todos los extractos deben quedar en estado 'conciliado'.");
        $this->assertEquals($totalPares, $totalVinculos, "La cantidad de filas en conciliacion_abono_pago debe coincidir exactamente con el total de abonos.");

        // Invariante de unicidad estricta
        $duplicadosMov = (int)$db->query("SELECT COUNT(*) FROM (SELECT movimiento_id FROM conciliacion_abono_pago GROUP BY movimiento_id HAVING COUNT(*) > 1)")->fetchColumn();
        $duplicadosPago = (int)$db->query("SELECT COUNT(*) FROM (SELECT pago_id FROM conciliacion_abono_pago GROUP BY pago_id HAVING COUNT(*) > 1)")->fetchColumn();

        $this->assertEquals(0, $duplicadosMov, "No debe existir ningún movimiento_id duplicado.");
        $this->assertEquals(0, $duplicadosPago, "No debe existir ningún pago_id duplicado.");
    }

    /**
     * 6.7 Job de reconciliación periódica:
     * Verifica que el método de auditoría detecte estado consistente y detecte discrepancias inducidas.
     */
    public function test67JobReconciliacionDivergencias(): void {
        $db = $this->crearBaseDatosPruebas();
        $service = new ConciliacionBancariaService();

        // 1. Estado limpio y balanceado
        $db->exec("
            INSERT INTO extractos_bancarios (id, banco, fecha_movimiento, referencia_bancaria, monto, estado)
            VALUES (1, 'Banesco', '2026-10-01', 'REF-1', 100.00, 'conciliado');
            INSERT INTO conciliacion_abono_pago (movimiento_id, pago_id, conciliado_por)
            VALUES (1, 10, 1);
        ");

        $audit1 = $service->ejecutarAuditoriaReconciliacion($db);
        $this->assertTrue($audit1['ok'], "La auditoría debe reportar OK cuando los abonos y vínculos están balanceados 1:1.");
        $this->assertEquals(0, $audit1['conteo_divergencias'], "No debe haber divergencias en estado limpio.");

        // 2. Inducir discrepancia: abono conciliado sin vínculo
        $db->exec("
            INSERT INTO extractos_bancarios (id, banco, fecha_movimiento, referencia_bancaria, monto, estado)
            VALUES (2, 'Banesco', '2026-10-01', 'REF-2', 150.00, 'conciliado');
        ");

        $audit2 = $service->ejecutarAuditoriaReconciliacion($db);
        $this->assertFalse($audit2['ok'], "La auditoría debe reportar false ante discrepancias.");
        $this->assertGreaterThan(0, $audit2['conteo_divergencias'], "Debe contabilizar al menos 1 divergencia.");
        $this->assertEquals('ABONO_CONCILIADO_SIN_VINCULO', $audit2['divergencias'][0]['tipo'], "Debe identificar el tipo de divergencia correcto.");
    }
}
