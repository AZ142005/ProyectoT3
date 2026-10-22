<?php
namespace Tests;

use App\Core\Database;
use App\Models\ComprobantesModel;
use App\Models\PagoModel;
use App\Services\ConciliacionBancariaService;
use PDO;

/**
 * Pruebas de la guardia global anti duplicados entre unidades:
 * - misma referencia + mismo monto aprobada en otra unidad => se bloquea
 * - misma referencia + monto distinto => se permite con advertencia
 * - lote, comprobantes y conciliación cubiertos
 * - locks GET_LOCK serializan aprobaciones y subidas del mismo archivo
 */
class PagoDuplicadoCrossUnitTest extends TestCase {

    private function columnaExiste(PDO $db, string $tabla, string $columna): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function crearUnidad(PDO $db, string $sufijo = ''): int {
        $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 100.00, 1)");
        $stmt->execute(['numero' => 'TEST-XU' . $sufijo . '-' . mt_rand(100000, 999999)]);
        return intval($db->lastInsertId());
    }

    private function crearFactura(PDO $db, int $unidadId, float $monto = 100.00): int {
        $stmt = $db->prepare("
            INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
            VALUES (:num, :uid, 10, 2026, CURDATE(), CURDATE(), :monto, 0.00, :saldo, 'pendiente')
        ");
        $num = 'FAC-XU-' . mt_rand(10000, 99999);
        $stmt->execute(['num' => $num, 'uid' => $unidadId, 'monto' => $monto, 'saldo' => $monto]);
        return intval($db->lastInsertId());
    }

    private function crearPago(
        PDO $db,
        int $residenteId,
        int $unidadId,
        string $referencia,
        float $monto = 100.00,
        string $estado = 'PENDIENTE',
        ?string $archivoHash = null
    ): int {
        $stmt = $db->prepare("
            INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, archivo_hash, estado)
            VALUES (:residente_id, :unidad_id, :monto, CURDATE(), 'transferencia', :referencia, :referencia_norm, :archivo, :archivo_hash, :estado)
        ");
        $stmt->execute([
            'residente_id'    => $residenteId,
            'unidad_id'       => $unidadId,
            'monto'           => $monto,
            'referencia'      => $referencia,
            'referencia_norm' => PagoModel::normalizarReferenciaPago($referencia),
            'archivo'         => 'test_cross_' . mt_rand(100000, 999999) . '.png',
            'archivo_hash'    => $archivoHash,
            'estado'          => $estado
        ]);
        return intval($db->lastInsertId());
    }

    private function crearComprobante(
        PDO $db,
        int $residenteId,
        int $facturaId,
        string $referencia,
        float $monto = 100.00,
        string $estado = 'pendiente'
    ): int {
        $stmt = $db->prepare("
            INSERT INTO comprobantes_pago (factura_id, residente_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
            VALUES (:factura_id, :residente_id, :monto, CURDATE(), 'transferencia', :referencia, :referencia_norm, :archivo, :estado)
        ");
        $stmt->execute([
            'factura_id'      => $facturaId,
            'residente_id'    => $residenteId,
            'monto'           => $monto,
            'referencia'      => $referencia,
            'referencia_norm' => PagoModel::normalizarReferenciaPago($referencia),
            'archivo'         => 'test_cross_comp_' . mt_rand(100000, 999999) . '.png',
            'estado'          => $estado
        ]);
        return intval($db->lastInsertId());
    }

    private function contarMovimientosUnidad(PDO $db, int $unidadId): int {
        $stmt = $db->prepare("SELECT COUNT(*) FROM movimientos_cuenta WHERE unidad_id = :unidad_id");
        $stmt->execute(['unidad_id' => $unidadId]);
        return intval($stmt->fetchColumn());
    }

    private function estadoPago(PDO $db, int $pagoId): string {
        $stmt = $db->prepare("SELECT estado FROM pagos WHERE id = :id");
        $stmt->execute(['id' => $pagoId]);
        return strtoupper(trim((string)$stmt->fetchColumn()));
    }

    /**
     * Limpia todas las filas creadas por los fixtures. El orden respeta las
     * dependencias lógicas entre tablas (no hay FKs que lo exijan, pero evita
     * dejar auditoría o conciliaciones huérfanas).
     */
    private function limpiarFixtures(PDO $db, array $unidadIds, array $extractoIds = [], int $maxNotif = 0, int $maxCola = 0): void {
        if (empty($unidadIds)) {
            return;
        }
        $in = implode(',', array_map('intval', $unidadIds));
        $inExt = empty($extractoIds) ? '0' : implode(',', array_map('intval', $extractoIds));

        try {
            $db->exec("DELETE FROM conciliacion_abono_pago WHERE movimiento_id IN ({$inExt})");
            $db->exec("DELETE FROM conciliacion_abono_pago WHERE pago_id IN (SELECT id FROM pagos WHERE unidad_id IN ({$in}))");
        } catch (\Throwable $e) {
            // Tabla opcional en instalaciones antiguas.
        }

        $db->exec("DELETE FROM movimientos_cuenta WHERE unidad_id IN ({$in})");
        $db->exec("DELETE FROM log_auditoria WHERE pago_id IN (SELECT id FROM pagos WHERE unidad_id IN ({$in}))");
        $db->exec("DELETE FROM log_auditoria WHERE registro_id IN (SELECT id FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id IN ({$in})))");
        $db->exec("DELETE FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id IN ({$in}))");
        $db->exec("DELETE FROM pagos WHERE unidad_id IN ({$in})");
        $db->exec("DELETE FROM facturas WHERE unidad_id IN ({$in})");
        $db->exec("DELETE FROM unidades WHERE id IN ({$in})");
        $db->exec("DELETE FROM extractos_bancarios WHERE id IN ({$inExt})");
        $db->exec("DELETE FROM notificaciones WHERE id > {$maxNotif}");
        $db->exec("DELETE FROM notificaciones_cola WHERE id > {$maxCola}");
    }

    private function obtenerResidenteYAdmin(PDO $db): array {
        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return [$residente, $admin];
    }

    // =================================================================
    // Guardia global en aprobación individual
    // =================================================================

    public function testAprobacionCrossUnitMismaReferenciaYMontoSeBloquea(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadA = 0;
        $unidadB = 0;
        try {
            $unidadA = $this->crearUnidad($db, '-A');
            $unidadB = $this->crearUnidad($db, '-B');
            $referencia = 'CROSS-' . mt_rand(100000, 999999);

            $this->crearPago($db, $residenteId, $unidadA, $referencia, 100.00, 'APROBADO');
            $pagoB = $this->crearPago($db, $residenteId, $unidadB, $referencia, 100.00, 'PENDIENTE');

            $resultado = (new PagoModel())->cambiarEstado($pagoB, 'APROBADO', 'Prueba cross-unit', $adminId);

            $this->assertFalse(!empty($resultado['ok']), 'La aprobación cross-unit con mismo monto debe bloquearse');
            $this->assertEquals('duplicado_otra_unidad', $resultado['code'], 'Debe reportar duplicado_otra_unidad');
            $this->assertEquals('PENDIENTE', $this->estadoPago($db, $pagoB), 'El pago B debe permanecer PENDIENTE');
            $this->assertEquals(0, $this->contarMovimientosUnidad($db, $unidadB), 'No debe existir movimiento para la unidad B');
        } finally {
            $this->limpiarFixtures($db, [$unidadA, $unidadB], [], $maxNotif, $maxCola);
        }
    }

    public function testReferenciaCruzadaConMontoDistintoPermiteAprobarConAdvertencia(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadA = 0;
        $unidadB = 0;
        try {
            $unidadA = $this->crearUnidad($db, '-A');
            $unidadB = $this->crearUnidad($db, '-B');
            $referencia = 'CROSS-DIF-' . mt_rand(100000, 999999);

            $this->crearPago($db, $residenteId, $unidadA, $referencia, 100.00, 'APROBADO');
            $pagoB = $this->crearPago($db, $residenteId, $unidadB, $referencia, 50.00, 'PENDIENTE');

            $resultado = (new PagoModel())->cambiarEstado($pagoB, 'APROBADO', 'Prueba cross-unit distinto monto', $adminId);

            $this->assertTrue(!empty($resultado['ok']), 'Con monto distinto la aprobación debe permitirse');
            $this->assertTrue(!empty($resultado['warning']), 'Debe emitir una advertencia por referencia cruzada');
            $this->assertEquals('APROBADO', $this->estadoPago($db, $pagoB), 'El pago B debe quedar APROBADO');
            $this->assertEquals(1, $this->contarMovimientosUnidad($db, $unidadB), 'Debe existir exactamente un movimiento para B');
        } finally {
            $this->limpiarFixtures($db, [$unidadA, $unidadB], [], $maxNotif, $maxCola);
        }
    }

    // =================================================================
    // Guardia global en aprobación por lote
    // =================================================================

    public function testAprobacionLoteCrossUnitBloqueaElSegundo(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadA = 0;
        $unidadB = 0;
        try {
            $unidadA = $this->crearUnidad($db, '-A');
            $unidadB = $this->crearUnidad($db, '-B');
            $referencia = 'CROSS-LOTE-' . mt_rand(100000, 999999);

            $pagoA = $this->crearPago($db, $residenteId, $unidadA, $referencia, 100.00, 'PENDIENTE');
            $pagoB = $this->crearPago($db, $residenteId, $unidadB, $referencia, 100.00, 'PENDIENTE');

            $resultado = (new PagoModel())->aprobarLote([$pagoA, $pagoB], $adminId);

            $this->assertEquals(1, $resultado['procesados'], 'Solo un pago del lote debe procesarse');
            $this->assertEquals(1, $resultado['duplicados'], 'El segundo pago debe bloquearse como duplicado');
            $this->assertEquals(0, $resultado['advertencias'], 'No debe haber advertencias cuando el monto coincide');

            $aprobados = intval($db->query("SELECT COUNT(*) FROM pagos WHERE id IN (" . intval($pagoA) . ',' . intval($pagoB) . ") AND estado = 'APROBADO'")->fetchColumn());
            $this->assertEquals(1, $aprobados, 'Exactamente uno de los dos pagos debe quedar APROBADO');
        } finally {
            $this->limpiarFixtures($db, [$unidadA, $unidadB], [], $maxNotif, $maxCola);
        }
    }

    // =================================================================
    // Comprobante aprobado bloquea pago en otra unidad
    // =================================================================

    public function testComprobanteAprobadoBloqueaPagoCrossUnit(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm') || !$this->columnaExiste($db, 'comprobantes_pago', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadA = 0;
        $unidadB = 0;
        try {
            $unidadA = $this->crearUnidad($db, '-A');
            $unidadB = $this->crearUnidad($db, '-B');
            $facturaA = $this->crearFactura($db, $unidadA, 100.00);
            $referencia = 'CROSS-COMP-' . mt_rand(100000, 999999);

            $this->crearComprobante($db, $residenteId, $facturaA, $referencia, 100.00, 'aprobado');
            $pagoB = $this->crearPago($db, $residenteId, $unidadB, $referencia, 100.00, 'PENDIENTE');

            $resultado = (new PagoModel())->cambiarEstado($pagoB, 'APROBADO', 'Prueba comprobante cross-unit', $adminId);

            $this->assertFalse(!empty($resultado['ok']), 'El comprobante aprobado en A debe bloquear el pago de B');
            $this->assertEquals('duplicado_otra_unidad', $resultado['code'], 'Debe reportar duplicado_otra_unidad');
            $this->assertEquals('PENDIENTE', $this->estadoPago($db, $pagoB), 'El pago B debe permanecer PENDIENTE');
            $this->assertEquals(0, $this->contarMovimientosUnidad($db, $unidadB), 'No debe existir movimiento para la unidad B');
        } finally {
            $this->limpiarFixtures($db, [$unidadA, $unidadB], [], $maxNotif, $maxCola);
        }
    }

    public function testComprobanteCrossUnitBloqueaAprobacion(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm') || !$this->columnaExiste($db, 'comprobantes_pago', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadA = 0;
        $unidadB = 0;
        try {
            $unidadA = $this->crearUnidad($db, '-A');
            $unidadB = $this->crearUnidad($db, '-B');
            $facturaB = $this->crearFactura($db, $unidadB, 100.00);
            $referencia = 'CROSS-COMPB-' . mt_rand(100000, 999999);

            $this->crearPago($db, $residenteId, $unidadA, $referencia, 100.00, 'APROBADO');
            $compB = $this->crearComprobante($db, $residenteId, $facturaB, $referencia, 100.00, 'pendiente');

            $model = new ComprobantesModel();
            $ok = $model->aprobar($compB, 'Prueba comprobante cross-unit');

            $this->assertFalse($ok, 'El pago aprobado en A debe bloquear el comprobante de B');
            $this->assertStringContains('Duplicado económico en otra unidad', (string)$model->ultimoError, 'Debe explicar el bloqueo entre unidades');

            $stmt = $db->prepare("SELECT estado FROM comprobantes_pago WHERE id = :id");
            $stmt->execute(['id' => $compB]);
            $this->assertEquals('pendiente', $stmt->fetchColumn(), 'El comprobante B debe permanecer pendiente');
            $this->assertEquals(0, $this->contarMovimientosUnidad($db, $unidadB), 'No debe existir movimiento para la unidad B');
        } finally {
            $this->limpiarFixtures($db, [$unidadA, $unidadB], [], $maxNotif, $maxCola);
        }
    }

    // =================================================================
    // Conciliación bancaria (ruta directa, sin modelos)
    // =================================================================

    public function testConciliacionCrossUnitBloquea(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadA = 0;
        $unidadB = 0;
        $extractoId = 0;
        try {
            $unidadA = $this->crearUnidad($db, '-A');
            $unidadB = $this->crearUnidad($db, '-B');
            $referencia = 'CROSS-EXT-' . mt_rand(100000, 999999);

            $this->crearPago($db, $residenteId, $unidadA, $referencia, 100.00, 'APROBADO');
            $pagoB = $this->crearPago($db, $residenteId, $unidadB, $referencia, 100.00, 'PENDIENTE');

            $stmtExt = $db->prepare("
                INSERT INTO extractos_bancarios
                    (lote_importacion, banco, fecha_movimiento, referencia_bancaria, referencia, descripcion, monto, tipo_movimiento, estado, estado_conciliacion)
                VALUES
                    (:lote, 'BBVA Provincial', CURDATE(), :ref1, :ref2, 'PRUEBA CROSS UNIT', 100.00, 'credito', 'disponible', 'pendiente')
            ");
            $stmtExt->execute(['lote' => 'LOTE-CROSS-' . uniqid(), 'ref1' => $referencia, 'ref2' => $referencia]);
            $extractoId = intval($db->lastInsertId());

            $excepcion = null;
            try {
                (new ConciliacionBancariaService())->conciliarYaprobar($extractoId, $pagoB, $adminId, 'pago');
            } catch (\App\Core\ConciliacionException $e) {
                $excepcion = $e;
            }

            $this->assertNotNull($excepcion, 'La conciliación cross-unit debe lanzar ConciliacionException');
            if ($excepcion !== null) {
                $this->assertStringContains('Duplicado económico en otra unidad', $excepcion->getMessage(), 'El mensaje debe explicar el duplicado cross-unit');
            }
            $this->assertEquals('PENDIENTE', $this->estadoPago($db, $pagoB), 'El pago B debe permanecer PENDIENTE');
            $this->assertEquals(0, $this->contarMovimientosUnidad($db, $unidadB), 'No debe existir movimiento para la unidad B');

            $stmtExtEstado = $db->prepare("SELECT estado FROM extractos_bancarios WHERE id = :id");
            $stmtExtEstado->execute(['id' => $extractoId]);
            $this->assertEquals('disponible', $stmtExtEstado->fetchColumn(), 'El extracto debe volver a disponible tras el rollback');
        } finally {
            $this->limpiarFixtures($db, [$unidadA, $unidadB], [$extractoId], $maxNotif, $maxCola);
        }
    }

    // =================================================================
    // Locks de concurrencia
    // =================================================================

    public function testLockAprobacionSerializaAprobaciones(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm')) {
            $this->skip('Migración de referencia_norm no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $db2 = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $unidadId = 0;
        $lockDb2 = false;
        try {
            $unidadId = $this->crearUnidad($db, '-LOCK');
            $pagoId = $this->crearPago($db, $residenteId, $unidadId, 'CROSS-LOCK-APR-' . mt_rand(100000, 999999), 100.00, 'PENDIENTE');

            $lockDb2 = PagoModel::adquirirLockAprobacion($db2);
            $this->assertTrue($lockDb2 === true, 'La segunda conexión debe adquirir el lock de aprobación');

            $resultado = (new PagoModel())->cambiarEstado($pagoId, 'APROBADO', 'Prueba lock', $adminId);
            $this->assertFalse(!empty($resultado['ok']), 'La aprobación con el lock ocupado debe fallar');
            $this->assertEquals('aprobacion_en_curso', $resultado['code'], 'Debe reportar aprobacion_en_curso tras el timeout');

            PagoModel::liberarLockAprobacion($db2);
            $lockDb2 = false;

            $resultado2 = (new PagoModel())->cambiarEstado($pagoId, 'APROBADO', 'Prueba lock liberado', $adminId);
            $this->assertTrue(!empty($resultado2['ok']), 'Tras liberar el lock la aprobación debe completarse');
            $this->assertEquals('APROBADO', $this->estadoPago($db, $pagoId), 'El pago debe quedar APROBADO');
        } finally {
            if ($lockDb2) {
                PagoModel::liberarLockAprobacion($db2);
            }
            if ($unidadId > 0) {
                $this->limpiarFixtures($db, [$unidadId], [], $maxNotif, $maxCola);
            }
        }
    }

    public function testLockHashSerializaSubidas(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'archivo_hash')) {
            $this->skip('Migración de archivo_hash no aplicada');
            return;
        }

        [$residente, $admin] = $this->obtenerResidenteYAdmin($db);
        if (!$residente) {
            $this->skip('Datos insuficientes (personas)');
            return;
        }
        $residenteId = intval($residente['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $db2 = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $unidadId = 0;
        $lockDb2 = false;
        try {
            $unidadId = $this->crearUnidad($db, '-HASH');
            $hash = hash('sha256', 'cross-lock-' . mt_rand() . uniqid());
            $referencia = 'CROSS-LOCK-HASH-' . mt_rand(100000, 999999);

            $lockDb2 = PagoModel::adquirirLockHash($db2, $hash);
            $this->assertTrue($lockDb2 === true, 'La segunda conexión debe adquirir el lock del hash');

            $primero = (new PagoModel())->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'        => 100.00,
                    'fecha_pago'   => date('Y-m-d'),
                    'metodo_pago'  => 'transferencia',
                    'referencia'   => $referencia,
                    'archivo_hash' => $hash
                ],
                'test_lock.png'
            );
            $this->assertFalse($primero, 'La subida debe fallar mientras otra conexión retiene el hash');

            $conteo = $db->prepare("SELECT COUNT(*) FROM pagos WHERE archivo_hash = :hash");
            $conteo->execute(['hash' => $hash]);
            $this->assertEquals(0, intval($conteo->fetchColumn()), 'No debe existir fila para el hash bloqueado');

            PagoModel::liberarLockHash($db2, $hash);
            $lockDb2 = false;

            $segundo = (new PagoModel())->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'        => 100.00,
                    'fecha_pago'   => date('Y-m-d'),
                    'metodo_pago'  => 'transferencia',
                    'referencia'   => $referencia,
                    'archivo_hash' => $hash
                ],
                'test_lock.png'
            );
            $this->assertTrue($segundo, 'Tras liberar el hash la subida debe completarse');

            $conteo->execute(['hash' => $hash]);
            $this->assertEquals(1, intval($conteo->fetchColumn()), 'Debe existir exactamente una fila para el hash');
        } finally {
            if ($lockDb2) {
                PagoModel::liberarLockHash($db2, $hash ?? '');
            }
            if ($unidadId > 0) {
                $this->limpiarFixtures($db, [$unidadId], [], $maxNotif, $maxCola);
            }
        }
    }

    // =================================================================
    // Verificación estática del cableado de la guardia
    // =================================================================

    public function testGuardiaGlobalCableadaEnTodasLasRutas(): void {
        $pagoSrc = (string)file_get_contents(APP_PATH . '/Models/PagoModel.php');
        $compSrc = (string)file_get_contents(APP_PATH . '/Models/ComprobantesModel.php');
        $servSrc = (string)file_get_contents(APP_PATH . '/Services/ConciliacionBancariaService.php');

        $this->assertStringContains('function buscarConflictoAprobadoGlobal', $pagoSrc, 'PagoModel debe exponer la guardia global');
        $this->assertStringContains('function adquirirLockAprobacion', $pagoSrc, 'PagoModel debe exponer el lock de aprobación');
        $this->assertStringContains('function adquirirLockHash', $pagoSrc, 'PagoModel debe exponer el lock por hash');

        $this->assertStringContains('buscarConflictoAprobadoGlobal', $compSrc, 'ComprobantesModel debe usar la guardia global');
        $this->assertStringContains('adquirirLockAprobacion', $compSrc, 'ComprobantesModel debe usar el lock de aprobación');

        $this->assertStringContains('buscarConflictoAprobadoGlobal', $servSrc, 'ConciliacionBancariaService debe usar la guardia global');
        $this->assertStringContains('adquirirLockAprobacion', $servSrc, 'ConciliacionBancariaService debe usar el lock de aprobación');
    }
}
