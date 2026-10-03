<?php
namespace Tests;

use App\Core\Database;
use App\Models\ComprobantesModel;
use App\Models\PagoModel;
use PDO;
use PDOException;

/**
 * Pruebas Fase 18 — hash cruzado de comprobantes (pagos <-> comprobantes_pago):
 * - Un pago vigente con hash H bloquea un comprobante con H (criterio archivo_hash).
 * - Un comprobante vigente con hash H bloquea un pago con H.
 * - RECHAZADO/rechazado liberan el hash; hash distinto y ausencia de hash no bloquean.
 * - PagoController::analizarComprobante aplica rate limit 20/60 (OCR).
 */
class HashCruzadoDuplicadosTest extends TestCase {

    private function columnaExiste(PDO $db, string $tabla, string $columna): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function crearUnidad(PDO $db): int {
        $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 100.00, 1)");
        $stmt->execute(['numero' => 'TEST-HASH18-' . mt_rand(100000, 999999)]);
        return intval($db->lastInsertId());
    }

    private function crearFactura(PDO $db, int $unidadId, float $monto = 100.00): int {
        $stmt = $db->prepare("
            INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
            VALUES (:num, :uid, 10, 2026, CURDATE(), CURDATE(), :monto, 0.00, :saldo, 'pendiente')
        ");
        $num = 'FAC-HASH18-' . mt_rand(10000, 99999);
        $stmt->execute(['num' => $num, 'uid' => $unidadId, 'monto' => $monto, 'saldo' => $monto]);
        return intval($db->lastInsertId());
    }

    private function limpiarTodo(PDO $db, array $unidadIds): void {
        if (empty($unidadIds)) return;
        $in = implode(',', array_map('intval', $unidadIds));

        $db->exec("DELETE FROM movimientos_cuenta WHERE unidad_id IN ({$in})");
        $db->exec("DELETE FROM log_auditoria WHERE registro_id IN (SELECT id FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id IN ({$in})))");
        $db->exec("DELETE FROM log_auditoria WHERE pago_id IN (SELECT id FROM pagos WHERE unidad_id IN ({$in}))");
        $db->exec("DELETE FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id IN ({$in}))");
        $db->exec("DELETE FROM pagos WHERE unidad_id IN ({$in})");
        $db->exec("DELETE FROM facturas WHERE unidad_id IN ({$in})");
        $db->exec("DELETE FROM unidades WHERE id IN ({$in})");
    }

    private function insertarPago(PDO $db, int $residenteId, int $unidadId, ?string $referencia, string $estado, ?string $hash, ?string $deletedAt = null): void {
        $stmt = $db->prepare("
            INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, archivo_hash, estado, deleted_at)
            VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'hash18.png', :hash, :estado, :deleted_at)
        ");
        $stmt->execute([
            'residente_id'    => $residenteId,
            'unidad_id'       => $unidadId,
            'referencia'      => $referencia,
            'referencia_norm' => PagoModel::normalizarReferenciaPago($referencia),
            'hash'            => $hash,
            'estado'          => $estado,
            'deleted_at'      => $deletedAt
        ]);
    }

    public function testPagoConHashBloqueaComprobante(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'archivo_hash') || !$this->columnaExiste($db, 'comprobantes_pago', 'archivo_hash')) {
            $this->skip('Fase 17 no aplicada (archivo_hash)');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $model = new ComprobantesModel();

        $unidadId = 0;
        try {
            $unidadId = $this->crearUnidad($db);
            $facturaId = $this->crearFactura($db, $unidadId, 100.00);
            $hash = hash('sha256', 'pago-bloquea-comp-' . mt_rand());

            $referencia = 'F18HASH-PC-' . mt_rand(100000, 999999);
            $this->insertarPago($db, $residenteId, $unidadId, $referencia, 'PENDIENTE', $hash);

            $dup = $model->verificarDuplicado($facturaId, null, date('Y-m-d'), 100.00, $hash);
            $this->assertNotNull($dup, 'El hash de un pago vigente debe detectarse como duplicado');
            $this->assertEquals('pago', $dup['tipo'], 'El duplicado debe reportarse como pago');
            $this->assertEquals('archivo_hash', $dup['criterio'], 'El criterio debe ser archivo_hash');
        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    public function testComprobanteConHashBloqueaPago(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'archivo_hash') || !$this->columnaExiste($db, 'comprobantes_pago', 'archivo_hash')) {
            $this->skip('Fase 17 no aplicada (archivo_hash)');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $compModel = new ComprobantesModel();
        $pagoModel = new PagoModel();

        $unidadId = 0;
        try {
            $unidadId = $this->crearUnidad($db);
            $facturaId = $this->crearFactura($db, $unidadId, 100.00);
            $hash = hash('sha256', 'comp-bloquea-pago-' . mt_rand());

            $compId = $compModel->create([
                'factura_id'      => $facturaId,
                'residente_id'    => $residenteId,
                'monto'           => 100.00,
                'fecha_pago'      => date('Y-m-d'),
                'metodo_pago'     => 'transferencia',
                'referencia'      => 'F18HASH-CP-' . mt_rand(100000, 999999),
                'archivo'         => 'hash18_comp.png',
                'archivo_hash'    => $hash,
                'estado'          => 'pendiente'
            ]);
            $this->assertTrue($compId > 0, 'El comprobante fixture debe crearse');

            $creado = $pagoModel->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'       => 50.00,
                    'fecha_pago'  => date('Y-m-d'),
                    'metodo_pago' => 'transferencia',
                    'referencia'  => 'F18HASH-NUEVO-' . mt_rand(100000, 999999),
                    'archivo_hash' => $hash
                ],
                'hash18_pago.png'
            );
            $this->assertFalse($creado, 'Un comprobante vigente con el mismo hash debe bloquear el pago');
        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    public function testPagoRechazadoLiberaHashYHashDistintoPasa(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'archivo_hash')) {
            $this->skip('Fase 17 no aplicada (archivo_hash)');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $compModel = new ComprobantesModel();
        $pagoModel = new PagoModel();

        $unidadId = 0;
        try {
            $unidadId = $this->crearUnidad($db);
            $facturaId = $this->crearFactura($db, $unidadId, 100.00);
            $hash = hash('sha256', 'pago-rechazado-' . mt_rand());

            // Pago RECHAZADO (sin crédito): libera el hash
            $this->insertarPago($db, $residenteId, $unidadId, 'F18HASH-RECH-' . mt_rand(100000, 999999), 'RECHAZADO', $hash);

            $dup = $compModel->verificarDuplicado($facturaId, null, date('Y-m-d'), 100.00, $hash);
            $this->assertNull($dup, 'Un pago RECHAZADO no debe bloquear por hash');

            // Hash distinto tampoco bloquea
            $hashDistinto = hash('sha256', 'otro-archivo-' . mt_rand());
            $dup2 = $compModel->verificarDuplicado($facturaId, null, date('Y-m-d'), 100.00, $hashDistinto);
            $this->assertNull($dup2, 'Un hash distinto no debe detectarse como duplicado');

            // Crear un pago con el hash liberado debe funcionar
            $creado = $pagoModel->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'       => 60.00,
                    'fecha_pago'  => date('Y-m-d'),
                    'metodo_pago' => 'transferencia',
                    'referencia'  => 'F18HASH-LIBRE-' . mt_rand(100000, 999999),
                    'archivo_hash' => $hash
                ],
                'hash18_libre.png'
            );
            $this->assertTrue($creado, 'El hash liberado por un RECHAZADO debe permitir crear el pago');
        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    public function testComprobanteRechazadoLiberaHash(): void {
        $db = Database::getConnection();
        if (!$this->columnaExiste($db, 'pagos', 'archivo_hash') || !$this->columnaExiste($db, 'comprobantes_pago', 'archivo_hash')) {
            $this->skip('Fase 17 no aplicada (archivo_hash)');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $pagoModel = new PagoModel();

        $unidadId = 0;
        try {
            $unidadId = $this->crearUnidad($db);
            $facturaId = $this->crearFactura($db, $unidadId, 100.00);
            $hash = hash('sha256', 'comp-rechazado-' . mt_rand());
            $referencia = 'F18HASH-RC-' . mt_rand(100000, 999999);

            // Inserción directa: ComprobantesModel::create() no persiste 'estado'
            // (siempre nace 'pendiente'), y aquí se necesita un rechazado real.
            $stmt = $db->prepare("
                INSERT INTO comprobantes_pago
                (residente_id, factura_id, monto, metodo_pago, referencia, referencia_norm, fecha_pago, archivo, archivo_hash, estado)
                VALUES (:residente_id, :factura_id, 100.00, 'transferencia', :referencia, :referencia_norm, CURDATE(), 'hash18_rech.png', :hash, 'rechazado')
            ");
            $stmt->execute([
                'residente_id'    => $residenteId,
                'factura_id'      => $facturaId,
                'referencia'      => $referencia,
                'referencia_norm' => PagoModel::normalizarReferenciaPago($referencia),
                'hash'            => $hash
            ]);
            $this->assertTrue(intval($db->lastInsertId()) > 0, 'El comprobante rechazado fixture debe crearse');

            $creado = $pagoModel->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'       => 70.00,
                    'fecha_pago'  => date('Y-m-d'),
                    'metodo_pago' => 'transferencia',
                    'referencia'  => 'F18HASH-RC-NUEVO-' . mt_rand(100000, 999999),
                    'archivo_hash' => $hash
                ],
                'hash18_rech_pago.png'
            );
            $this->assertTrue($creado, 'Un comprobante rechazado no debe bloquear el hash');
        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    public function testSinHashNoBloqueaPagosLegitimos(): void {
        $db = Database::getConnection();
        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $pagoModel = new PagoModel();

        $unidadId = 0;
        try {
            $unidadId = $this->crearUnidad($db);

            $primero = $pagoModel->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'       => 40.00,
                    'fecha_pago'  => date('Y-m-d'),
                    'metodo_pago' => 'transferencia',
                    'referencia'  => 'F18HASH-SINH1-' . mt_rand(100000, 999999),
                ],
                'hash18_sin1.png'
            );
            $segundo = $pagoModel->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'       => 41.00,
                    'fecha_pago'  => date('Y-m-d'),
                    'metodo_pago' => 'transferencia',
                    'referencia'  => 'F18HASH-SINH2-' . mt_rand(100000, 999999),
                ],
                'hash18_sin2.png'
            );

            $this->assertTrue($primero, 'Un pago legítimo sin hash debe registrarse');
            $this->assertTrue($segundo, 'Un segundo pago legítimo sin hash y referencia distinta debe registrarse');
        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    public function testControladorAnalizarComprobanteAplicaRateLimit(): void {
        $contenido = (string)file_get_contents(dirname(__DIR__) . '/app/controllers/PagoController.php');

        $this->assertStringContains("RateLimiter::attempt('ocr_analisis_' . Auth::id(), 20, 60)", $contenido,
            'analizarComprobante debe aplicar rate limit 20/60 por usuario');
        $this->assertStringContains('429', $contenido,
            'El exceso de rate limit debe responder JSON 429');
    }
}
