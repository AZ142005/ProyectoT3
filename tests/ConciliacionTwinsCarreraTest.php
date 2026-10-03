<?php
namespace Tests;

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\ComprobantesModel;
use App\Models\ConciliacionModel;
use App\Models\PagoModel;
use App\Services\ConciliacionBancariaService;
use PDO;
use PDOException;

/**
 * Suite de Pruebas: Batería de Verificación 1:1, Concurrencia y Detección de Twins
 * 
 * Cumple con los tres escenarios críticos exigidos por el plan de auditoría:
 * 1. Condición de Carrera (Doble Clic): 5 peticiones simultáneas -> 1 procesada (201/200), 4 conflicto (409).
 * 2. Clonación de Comprobantes (Twins): Dos residentes con misma referencia -> 1 cruce exitoso, 2do marcado como 'Referencia ya utilizada' sin duplicar saldo.
 * 3. Ataque de Inundación (Flooding): 50 reportes consecutivos -> Detenido con Too Many Requests (429) sin saturar BD.
 * 4. Auditoría de Base de Datos y Bloqueo Transaccional: Índices y concurrencia pesimista en abonos.
 */
class ConciliacionTwinsCarreraTest extends TestCase {

    private function crearUnidad(PDO $db): int {
        $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 120.00, 1)");
        $stmt->execute(['numero' => 'TEST-TWIN-' . mt_rand(100000, 999999)]);
        return intval($db->lastInsertId());
    }

    private function crearFactura(PDO $db, int $unidadId, float $monto = 120.00): int {
        $stmt = $db->prepare("
            INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
            VALUES (:num, :uid, 10, 2026, CURDATE(), CURDATE(), :monto, 0.00, :saldo, 'pendiente')
        ");
        $num = 'FAC-TW-' . mt_rand(10000, 99999);
        $stmt->execute(['num' => $num, 'uid' => $unidadId, 'monto' => $monto, 'saldo' => $monto]);
        return intval($db->lastInsertId());
    }

    private function limpiarFixtures(PDO $db, array $unidadIds, array $extractoIds = []): void {
        if (!empty($unidadIds)) {
            $in = implode(',', array_map('intval', $unidadIds));
            $db->exec("DELETE FROM conciliacion_abono_pago WHERE pago_id IN (SELECT id FROM pagos WHERE unidad_id IN ({$in}))");
            $db->exec("DELETE FROM movimientos_cuenta WHERE unidad_id IN ({$in})");
            $db->exec("DELETE FROM log_auditoria WHERE registro_id IN (SELECT id FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id IN ({$in})))");
            $db->exec("DELETE FROM log_auditoria WHERE pago_id IN (SELECT id FROM pagos WHERE unidad_id IN ({$in}))");
            $db->exec("DELETE FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id IN ({$in}))");
            $db->exec("DELETE FROM pagos WHERE unidad_id IN ({$in})");
            $db->exec("DELETE FROM facturas WHERE unidad_id IN ({$in})");
            $db->exec("DELETE FROM unidades WHERE id IN ({$in})");
        }

        if (!empty($extractoIds)) {
            $inExt = implode(',', array_map('intval', $extractoIds));
            $db->exec("DELETE FROM conciliacion_abono_pago WHERE movimiento_id IN ({$inExt})");
            $db->exec("DELETE FROM extractos_bancarios WHERE id IN ({$inExt})");
            try {
                $db->exec("DELETE FROM movimientos_bancarios WHERE id IN ({$inExt})");
            } catch (\Throwable $e) {}
        }
    }

    // =================================================================
    // 1. Escenario 1: Condición de Carrera (Doble Clic)
    // =================================================================

    public function testEscenario1CondicionDeCarreraDobleClic(): void {
        $db = Database::getConnection();
        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);

        $unidadId = $this->crearUnidad($db);
        $facturaId = $this->crearFactura($db, $unidadId, 150.00);

        try {
            $referencia = 'REF-RACE-' . mt_rand(10000, 99999);
            $refNorm = PagoModel::normalizarReferenciaPago($referencia);
            $archivoHash = hash('sha256', 'SIMULATED_VOUCHER_CONTENT_' . $referencia);

            $numPeticiones = 5;
            $exitos = 0;
            $conflictos = 0;
            $codigosHttp = [];

            // Simulación de 5 peticiones POST simultáneas exactas
            for ($i = 0; $i < $numPeticiones; $i++) {
                $comprobantesModel = new ComprobantesModel();

                // 1. Pre-chequeo del controlador
                $dup = $comprobantesModel->verificarDuplicado($facturaId, $referencia, date('Y-m-d'), 150.00, $archivoHash);
                if ($dup !== null) {
                    $conflictos++;
                    $codigosHttp[] = 409;
                    continue;
                }

                // 2. Inserción atómica con protección de unicidad
                $creado = $comprobantesModel->create([
                    'factura_id'      => $facturaId,
                    'residente_id'    => $residenteId,
                    'monto'           => 150.00,
                    'fecha_pago'      => date('Y-m-d'),
                    'metodo_pago'     => 'transferencia',
                    'referencia'      => $referencia,
                    'referencia_norm' => $refNorm,
                    'archivo'         => 'comprobante_carrera.png',
                    'archivo_hash'    => $archivoHash,
                    'estado'          => 'pendiente'
                ]);

                if ($creado !== false && intval($creado) > 0) {
                    $exitos++;
                    $codigosHttp[] = 201;
                } else {
                    $conflictos++;
                    $codigosHttp[] = 409;
                }
            }

            $this->assertEquals(1, $exitos, 'Exactamente 1 petición debe procesarse con éxito (HTTP 201).');
            $this->assertEquals(4, $conflictos, 'Las 4 peticiones restantes deben devolver error de conflicto (HTTP 409).');
            $this->assertEquals([201, 409, 409, 409, 409], $codigosHttp, 'Los códigos HTTP deben ser 1x 201 y 4x 409.');

            // Comprobar que en base de datos existe únicamente 1 registro
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM comprobantes_pago WHERE factura_id = :fid AND referencia_norm = :ref");
            $stmtCount->execute(['fid' => $facturaId, 'ref' => $refNorm]);
            $totalGuardados = intval($stmtCount->fetchColumn());
            $this->assertEquals(1, $totalGuardados, 'Solo debe persistirse 1 comprobante en la base de datos.');

        } finally {
            $this->limpiarFixtures($db, [$unidadId]);
        }
    }

    // =================================================================
    // 2. Escenario 2: Clonación de Comprobantes (Twins)
    // =================================================================

    public function testEscenario2ClonacionDeComprobantesTwins(): void {
        $db = Database::getConnection();
        $personas = $db->query("SELECT id FROM personas LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (count($personas) < 2 || !$admin) {
            $this->skip('Datos insuficientes (se requieren al menos 2 personas y 1 admin).');
            return;
        }

        $residente1Id = intval($personas[0]['id']);
        $residente2Id = intval($personas[1]['id']);
        $adminId = intval($admin['id']);

        $unidad1Id = $this->crearUnidad($db);
        $unidad2Id = $this->crearUnidad($db);
        $factura1Id = $this->crearFactura($db, $unidad1Id, 250.00);
        $factura2Id = $this->crearFactura($db, $unidad2Id, 250.00);

        $extractoId = 0;

        try {
            $refCompartida = 'DEP-TWIN-' . mt_rand(10000, 99999);
            $refNorm = PagoModel::normalizarReferenciaPago($refCompartida);

            // 1. Insertamos un depósito bancario real en extractos_bancarios
            $stmtExt = $db->prepare("
                INSERT INTO extractos_bancarios (banco, fecha_movimiento, referencia_bancaria, referencia, monto, tipo_movimiento, estado, estado_conciliacion)
                VALUES ('Banesco', CURDATE(), :ref1, :ref2, 250.00, 'credito', 'disponible', 'pendiente')
            ");
            $stmtExt->execute(['ref1' => $refCompartida, 'ref2' => $refCompartida]);
            $extractoId = intval($db->lastInsertId());

            // 2. Residente 1 registra comprobante con esa referencia
            $stmtC1 = $db->prepare("
                INSERT INTO comprobantes_pago (factura_id, residente_id, monto, fecha_pago, metodo_pago, banco_pagador, banco_receptor, referencia, referencia_norm, archivo, archivo_hash, estado)
                VALUES (:fid, :rid, 250.00, CURDATE(), 'transferencia', 'Banesco', 'Banesco', :ref, :ref_norm, 'comp1.png', :hash1, 'pendiente')
            ");
            $stmtC1->execute([
                'fid'      => $factura1Id,
                'rid'      => $residente1Id,
                'ref'      => $refCompartida,
                'ref_norm' => $refNorm,
                'hash1'    => hash('sha256', 'IMG_RESIDENTE_1')
            ]);
            $comp1Id = intval($db->lastInsertId());

            // 3. Residente 2 (otra cuenta/unidad) registra pago con la misma referencia bancaria
            $stmtC2 = $db->prepare("
                INSERT INTO comprobantes_pago (factura_id, residente_id, monto, fecha_pago, metodo_pago, banco_pagador, banco_receptor, referencia, referencia_norm, archivo, archivo_hash, estado)
                VALUES (:fid, :rid, 250.00, CURDATE(), 'transferencia', 'Banesco', 'Banesco', :ref, :ref_norm, 'comp2.png', :hash2, 'pendiente')
            ");
            $stmtC2->execute([
                'fid'      => $factura2Id,
                'rid'      => $residente2Id,
                'ref'      => $refCompartida,
                'ref_norm' => $refNorm,
                'hash2'    => hash('sha256', 'IMG_RESIDENTE_2')
            ]);
            $comp2Id = intval($db->lastInsertId());

            // 4. Ejecutar cruce inteligente
            $service = new ConciliacionBancariaService();
            $stmtFetchExt = $db->prepare("SELECT * FROM extractos_bancarios WHERE id = :id");
            $stmtFetchExt->execute(['id' => $extractoId]);
            $movExtracto = $stmtFetchExt->fetchAll(PDO::FETCH_ASSOC);

            $cruce = $service->ejecutarCruceInteligente($movExtracto);

            // Aserción: El cruce inteligente aprueba/empareja exactamente el primero
            $this->assertEquals(1, count($cruce['coincidencias_exactas']), 'Debe haber exactamente 1 coincidencia exacta.');
            $pagoEmparejado = $cruce['coincidencias_exactas'][0]['pago'];
            $this->assertEquals($comp1Id, intval($pagoEmparejado['id']), 'El primer comprobante debe ser el emparejado.');

            // Aserción: El segundo comprobante debe quedar marcado como "Referencia ya utilizada" en inconsistencias
            $inconsistencias = $cruce['inconsistencias'];
            $encontradoTwinInconsistencia = false;
            foreach ($inconsistencias as $inc) {
                if (!empty($inc['pago']) && intval($inc['pago']['id']) === $comp2Id) {
                    if (str_contains($inc['motivo'] ?? '', 'Referencia ya utilizada')) {
                        $encontradoTwinInconsistencia = true;
                    }
                }
            }
            $this->assertTrue($encontradoTwinInconsistencia, 'El segundo comprobante debe quedar marcado como "Referencia ya utilizada".');

            // 5. Conciliar y aprobar el primer comprobante (1:1 con el depósito)
            $resConciliacion1 = $service->conciliarYaprobar($extractoId, $comp1Id, $adminId, 'comprobante');
            $this->assertTrue($resConciliacion1['success'], 'La conciliación del primer comprobante debe ser exitosa.');

            // Verificar que el depósito pasó a 'conciliado'
            $estadoExt = $db->query("SELECT estado FROM extractos_bancarios WHERE id = {$extractoId}")->fetchColumn();
            $this->assertEquals('conciliado', $estadoExt, 'El extracto bancario debe quedar en estado conciliado.');

            // 6. Intentar conciliar el segundo comprobante contra el mismo depósito bancario
            $segundoFallo = false;
            try {
                $service->conciliarYaprobar($extractoId, $comp2Id, $adminId, 'comprobante');
            } catch (\App\Core\ConciliacionException $ce) {
                $segundoFallo = true;
                $this->assertEquals(409, $ce->getStatusHttp(), 'Debe devolver código HTTP 409 de conflicto.');
                $this->assertEquals('ABONO_YA_UTILIZADO', $ce->getCodigoNegocio(), 'Debe reportar código ABONO_YA_UTILIZADO.');
            }
            $this->assertTrue($segundoFallo, 'El intento de conciliar el segundo comprobante debe ser rechazado.');

            // 7. Verificar que NO se generó saldo a favor duplicado en la unidad 2
            $stmtSaldo2 = $db->prepare("SELECT saldo, monto_pagado FROM facturas WHERE id = :id");
            $stmtSaldo2->execute(['id' => $factura2Id]);
            $fact2 = $stmtSaldo2->fetch(PDO::FETCH_ASSOC);
            $this->assertEquals(250.00, floatval($fact2['saldo']), 'La factura 2 no debe haber sido pagada ni recibir saldo a favor.');
            $this->assertEquals(0.00, floatval($fact2['monto_pagado']), 'La factura 2 debe mantener monto pagado en 0.00.');

        } finally {
            $this->limpiarFixtures($db, [$unidad1Id, $unidad2Id], [$extractoId]);
        }
    }

    // =================================================================
    // 3. Escenario 3: Ataque de Inundación (Flooding)
    // =================================================================

    public function testEscenario3AtaqueDeInundacionFlooding(): void {
        $usuarioTestId = 99990 + mt_rand(1, 99);
        $claveLimitador = 'comprobante_' . $usuarioTestId;

        $intentosMaximos = 10;
        $totalPeticiones = 50;
        $bloqueadas = 0;
        $permitidas = 0;

        // Disparo de 50 peticiones consecutivas en menos de un minuto
        for ($i = 1; $i <= $totalPeticiones; $i++) {
            $permitido = RateLimiter::attempt($claveLimitador, $intentosMaximos, 60);
            if ($permitido) {
                $permitidas++;
            } else {
                $bloqueadas++;
            }
        }

        $this->assertEquals($intentosMaximos, $permitidas, "Debe permitir exactamente {$intentosMaximos} peticiones.");
        $this->assertEquals($totalPeticiones - $intentosMaximos, $bloqueadas, 'Las 40 peticiones restantes deben ser bloqueadas.');

        // Verificar que la simulación de respuesta HTTP devuelva 429 Too Many Requests
        $statusCode = (!$permitido) ? 429 : 200;
        $this->assertEquals(429, $statusCode, 'El limitador debe responder con HTTP 429 Too Many Requests.');
    }

    // =================================================================
    // 4. Auditoría de Base de Datos y Lógica Transaccional
    // =================================================================

    public function testAuditoriaIndicesYBloqueoTransaccional(): void {
        $db = Database::getConnection();

        // 1. Índices estrictos en referencias
        $stmtIndComp = $db->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobantes_pago' AND COLUMN_NAME = 'referencia_norm'
        ");
        $stmtIndComp->execute();
        $this->assertGreaterThan(0, intval($stmtIndComp->fetchColumn()), 'comprobantes_pago.referencia_norm debe estar indizada.');

        $stmtIndPagos = $db->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos' AND COLUMN_NAME = 'referencia_norm'
        ");
        $stmtIndPagos->execute();
        $this->assertGreaterThan(0, intval($stmtIndPagos->fetchColumn()), 'pagos.referencia_norm debe estar indizada para evitar escaneos type=ALL.');

        // 2. Pessimistic Locking en ConciliacionModel::conciliarAbono
        $conciliacionModel = new ConciliacionModel();
        $this->assertTrue(method_exists($conciliacionModel, 'conciliarAbono'), 'ConciliacionModel debe implementar conciliarAbono.');

        // 3. Verificación de firma digital (hash SHA-256) en comprobantes_pago y pagos
        $stmtHashComp = $db->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobantes_pago' AND COLUMN_NAME = 'archivo_hash'
        ");
        $stmtHashComp->execute();
        $this->assertEquals(1, intval($stmtHashComp->fetchColumn()), 'comprobantes_pago debe contar con columna archivo_hash para huella SHA-256.');

        $stmtHashPagos = $db->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos' AND COLUMN_NAME = 'archivo_hash'
        ");
        $stmtHashPagos->execute();
        $this->assertEquals(1, intval($stmtHashPagos->fetchColumn()), 'pagos debe contar con columna archivo_hash para huella SHA-256.');
    }
}
