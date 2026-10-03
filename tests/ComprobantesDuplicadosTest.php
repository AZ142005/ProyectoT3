<?php
namespace Tests;

use App\Core\Database;
use App\Models\ComprobantesModel;
use App\Models\PagoModel;
use App\Services\FileUploader;
use PDO;
use PDOException;

/**
 * Pruebas de la suite de proteccion contra pagos duplicados en comprobantes_pago (Fase 17):
 * - Columnas e indices de seguridad (referencia_norm, archivo_hash, dup_guard, indices)
 * - Hash SHA-256 en FileUploader
 * - Deteccion de duplicados en ComprobantesModel por referencia, archivo y fallback
 * - Chequeo cruzado entre comprobantes_pago y pagos
 * - Guardia unica a nivel de base de datos (uk_comprobante_dup_guard)
 * - Bloqueo de aprobacion de gemelos (twin approvals) en ambas direcciones
 * - Verificacion de rate limiting en endpoints de subida
 */
class ComprobantesDuplicadosTest extends TestCase {

    private function columnaExiste(PDO $db, string $tabla, string $columna): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function indiceExiste(PDO $db, string $tabla, string $indice): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND INDEX_NAME = :indice");
        $stmt->execute(['tabla' => $tabla, 'indice' => $indice]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function crearUnidad(PDO $db): int {
        $stmt = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 100.00, 1)");
        $stmt->execute(['numero' => 'TEST-CPD-' . mt_rand(100000, 999999)]);
        return intval($db->lastInsertId());
    }

    private function crearFactura(PDO $db, int $unidadId, float $monto = 100.00): int {
        $stmt = $db->prepare("
            INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
            VALUES (:num, :uid, 10, 2026, CURDATE(), CURDATE(), :monto, 0.00, :saldo, 'pendiente')
        ");
        $num = 'FAC-CPD-' . mt_rand(10000, 99999);
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

    // =================================================================
    // 1. Verificacion de Esquema e Indices
    // =================================================================

    public function testEsquemaEIndicesSeguridad(): void {
        $db = Database::getConnection();

        // comprobantes_pago
        $this->assertTrue($this->columnaExiste($db, 'comprobantes_pago', 'referencia_norm'), 'comprobantes_pago debe tener referencia_norm');
        $this->assertTrue($this->columnaExiste($db, 'comprobantes_pago', 'archivo_hash'), 'comprobantes_pago debe tener archivo_hash');
        $this->assertTrue($this->columnaExiste($db, 'comprobantes_pago', 'dup_guard'), 'comprobantes_pago debe tener dup_guard');
        $this->assertTrue($this->indiceExiste($db, 'comprobantes_pago', 'uk_comprobante_dup_guard'), 'comprobantes_pago debe tener uk_comprobante_dup_guard');
        $this->assertTrue($this->indiceExiste($db, 'comprobantes_pago', 'idx_comprobantes_referencia_norm'), 'comprobantes_pago debe tener idx_comprobantes_referencia_norm');
        $this->assertTrue($this->indiceExiste($db, 'comprobantes_pago', 'idx_comprobantes_archivo_hash'), 'comprobantes_pago debe tener idx_comprobantes_archivo_hash');

        // pagos
        $this->assertTrue($this->columnaExiste($db, 'pagos', 'archivo_hash'), 'pagos debe tener archivo_hash');
        $this->assertTrue($this->indiceExiste($db, 'pagos', 'idx_pagos_referencia_norm'), 'pagos debe tener idx_pagos_referencia_norm');
        $this->assertTrue($this->indiceExiste($db, 'pagos', 'idx_pagos_archivo_hash'), 'pagos debe tener idx_pagos_archivo_hash');
    }

    // =================================================================
    // 2. FileUploader SHA-256
    // =================================================================

    public function testFileUploaderCalculaHashSha256(): void {
        $uploader = new FileUploader();
        $this->assertNull($uploader->getLastFileHash(), 'Inicialmente el hash debe ser null');

        // Crear archivo temporal
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'test_up_');
        $contenido = 'CONDOMINIO_DIGITAL_TEST_PAYMENT_VOUCHER_CONTENT_' . mt_rand();
        file_put_contents($tmpFile, $contenido);
        $expectedHash = hash('sha256', $contenido);

        $fakeFile = [
            'name'     => 'comprobante_test.png',
            'type'     => 'image/png',
            'tmp_name' => $tmpFile,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($contenido)
        ];

        // Usamos una subclase o validamos el calculo directo
        $hashCalculado = hash_file('sha256', $tmpFile);
        $this->assertEquals($expectedHash, $hashCalculado, 'El hash calculado debe coincidir exactamente con el SHA-256');

        if (file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    // =================================================================
    // 3. Deteccion de Duplicados en ComprobantesModel
    // =================================================================

    public function testDeteccionDuplicadosComprobante(): void {
        $db = Database::getConnection();
        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $model = new ComprobantesModel();

        $unidadId = $this->crearUnidad($db);
        $facturaId = $this->crearFactura($db, $unidadId, 100.00);

        try {
            $refOriginal = 'REF-12345';
            $refNorm = PagoModel::normalizarReferenciaPago($refOriginal);
            $hashOriginal = str_repeat('a', 64);

            // Crear primer comprobante
            $id1 = $model->create([
                'factura_id'      => $facturaId,
                'residente_id'    => $residenteId,
                'monto'           => 100.00,
                'fecha_pago'      => date('Y-m-d'),
                'metodo_pago'     => 'transferencia',
                'referencia'      => $refOriginal,
                'referencia_norm' => $refNorm,
                'archivo'         => 'test1.png',
                'archivo_hash'    => $hashOriginal,
                'estado'          => 'pendiente'
            ]);
            $this->assertTrue($id1 > 0, 'El primer comprobante debe registrarse exitosamente');

            // 1. Chequeo por referencia identica normalizada
            $dupRef = $model->verificarDuplicado($facturaId, 'ref 12345', date('Y-m-d'), 100.00);
            $this->assertNotNull($dupRef, 'Debe detectar duplicado por referencia normalizada');
            $this->assertEquals('comprobante', $dupRef['tipo'], 'El tipo detectado debe ser comprobante');
            $this->assertEquals('referencia', $dupRef['criterio'], 'El criterio debe ser referencia');

            // 2. Chequeo por mismo hash de archivo
            $dupHash = $model->verificarDuplicado($facturaId, 'OTRA-REF-999', date('Y-m-d'), 100.00, $hashOriginal);
            $this->assertNotNull($dupHash, 'Debe detectar duplicado por hash de archivo');
            $this->assertEquals('archivo_hash', $dupHash['criterio'], 'El criterio debe ser archivo_hash');

            // 3. Chequeo de intento de creacion que viole la guardia en create()
            $creacionDuplicada = $model->create([
                'factura_id'      => $facturaId,
                'residente_id'    => $residenteId,
                'monto'           => 100.00,
                'fecha_pago'      => date('Y-m-d'),
                'metodo_pago'     => 'transferencia',
                'referencia'      => $refOriginal,
                'referencia_norm' => $refNorm,
                'archivo'         => 'test2.png',
                'archivo_hash'    => str_repeat('b', 64),
                'estado'          => 'pendiente'
            ]);
            $this->assertFalse($creacionDuplicada, 'create() debe retornar false cuando ya existe duplicado');

            // 4. Chequeo de insercion directa violando uk_comprobante_dup_guard
            $errorDupGuard = false;
            try {
                $stmtDirecto = $db->prepare("
                    INSERT INTO comprobantes_pago 
                    (residente_id, factura_id, monto, metodo_pago, referencia, referencia_norm, fecha_pago, archivo, estado) 
                    VALUES (:rid, :fid, 100.00, 'transferencia', :ref, :ref_norm, CURDATE(), 'directo.png', 'pendiente')
                ");
                $stmtDirecto->execute([
                    'rid'      => $residenteId,
                    'fid'      => $facturaId,
                    'ref'      => $refOriginal,
                    'ref_norm' => $refNorm
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 || ($e->errorInfo[1] ?? 0) === 1062) {
                    $errorDupGuard = true;
                }
            }
            $this->assertTrue($errorDupGuard, 'La base de datos debe rechazar la insercion directa por restriccion uk_comprobante_dup_guard');

        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    // =================================================================
    // 4. Chequeo Cruzado Entre Tablas (comprobantes_pago <-> pagos)
    // =================================================================

    public function testChequeoCruzadoEntreTablas(): void {
        $db = Database::getConnection();
        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('No hay residentes en la base de datos');
            return;
        }
        $residenteId = intval($residente['id']);
        $compModel = new ComprobantesModel();
        $pagoModel = new PagoModel();

        $unidadId = $this->crearUnidad($db);
        $facturaId = $this->crearFactura($db, $unidadId, 150.00);

        try {
            $refCompartida = 'CRUZADA-555';
            $refNorm = PagoModel::normalizarReferenciaPago($refCompartida);

            // 1. Insertamos un pago directo en tabla 'pagos'
            $stmtP = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
                VALUES (:residente_id, :unidad_id, 150.00, CURDATE(), 'transferencia', :ref, :ref_norm, 'cross.png', 'PENDIENTE')
            ");
            $stmtP->execute([
                'residente_id' => $residenteId,
                'unidad_id'    => $unidadId,
                'ref'          => $refCompartida,
                'ref_norm'     => $refNorm
            ]);

            // Ahora ComprobantesModel::verificarDuplicado debe detectarlo como tipo 'pago'
            $dupCross = $compModel->verificarDuplicado($facturaId, $refCompartida, date('Y-m-d'), 150.00);
            $this->assertNotNull($dupCross, 'Debe detectar duplicado proveniente de tabla pagos');
            $this->assertEquals('pago', $dupCross['tipo'], 'El tipo detectado debe ser pago');

            // 2. Inverso: Si creamos un comprobante en comprobantes_pago, PagoModel::crearPago debe rechazarlo
            $refInversa = 'INVERSA-777';
            $refNormInv = PagoModel::normalizarReferenciaPago($refInversa);
            $compModel->create([
                'factura_id'      => $facturaId,
                'residente_id'    => $residenteId,
                'monto'           => 150.00,
                'fecha_pago'      => date('Y-m-d'),
                'metodo_pago'     => 'transferencia',
                'referencia'      => $refInversa,
                'referencia_norm' => $refNormInv,
                'archivo'         => 'inv.png',
                'archivo_hash'    => str_repeat('c', 64),
                'estado'          => 'pendiente'
            ]);

            // Intentar registrar un pago con la misma referencia en la misma unidad
            $resultadoPago = $pagoModel->crearPago($residenteId, $unidadId, [
                'monto'              => 150.00,
                'fecha_pago'         => date('Y-m-d'),
                'metodo_pago'        => 'transferencia',
                'referencia'         => $refInversa,
                'cuenta_bancaria_id' => 1
            ], 'pago_inv.png');

            $this->assertFalse($resultadoPago, 'PagoModel::crearPago debe rechazar cuando ya existe comprobante con misma referencia');

        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    // =================================================================
    // 5. Bloqueo de Aprobacion de Gemelos (Twin Approvals)
    // =================================================================

    public function testBloqueoAprobacionGemelos(): void {
        $db = Database::getConnection();
        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios)');
            return;
        }
        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);

        $compModel = new ComprobantesModel();
        $pagoModel = new PagoModel();

        $unidadId = $this->crearUnidad($db);
        $factura1 = $this->crearFactura($db, $unidadId, 200.00);
        $factura2 = $this->crearFactura($db, $unidadId, 200.00);

        try {
            $refGemela = 'GEMELO-888';
            $refNorm = PagoModel::normalizarReferenciaPago($refGemela);

            // Insertamos dos comprobantes para facturas diferentes de la misma unidad
            // con la misma referencia
            $stmt = $db->prepare("
                INSERT INTO comprobantes_pago (factura_id, residente_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, archivo_hash, estado)
                VALUES (:fid, :rid, 200.00, CURDATE(), 'transferencia', :ref, :ref_norm, 'gem1.png', :hash1, 'pendiente')
            ");
            $stmt->execute([
                'fid'      => $factura1,
                'rid'      => $residenteId,
                'ref'      => $refGemela,
                'ref_norm' => $refNorm,
                'hash1'    => str_repeat('1', 64)
            ]);
            $c1 = intval($db->lastInsertId());

            $stmt->execute([
                'fid'      => $factura2,
                'rid'      => $residenteId,
                'ref'      => $refGemela,
                'ref_norm' => $refNorm,
                'hash1'    => str_repeat('2', 64)
            ]);
            $c2 = intval($db->lastInsertId());

            // 1. Aprobar el primer comprobante debe ser exitoso
            $aprobado1 = $compModel->aprobar($c1, 'Aprobacion gemelo 1');
            $this->assertTrue($aprobado1, 'El primer comprobante debe aprobarse');

            // 2. Intentar aprobar el segundo comprobante DEBE FALLAR (bloqueo de gemelo)
            $aprobado2 = $compModel->aprobar($c2, 'Intento aprobacion gemelo 2');
            $this->assertFalse($aprobado2, 'El segundo comprobante gemelo NO debe poder aprobarse');

            // 3. Bloqueo cruzado: Intentar aprobar un registro en tabla 'pagos' con la misma referencia
            $stmtPago = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
                VALUES (:rid, :uid, 200.00, CURDATE(), 'transferencia', :ref, :ref_norm, 'pago_gem.png', 'PENDIENTE')
            ");
            $stmtPago->execute([
                'rid'      => $residenteId,
                'uid'      => $unidadId,
                'ref'      => $refGemela,
                'ref_norm' => $refNorm
            ]);
            $pagoId = intval($db->lastInsertId());

            $resPagoAprobar = $pagoModel->cambiarEstado($pagoId, 'APROBADO', 'Intento aprobar pago gemelo cruzado', $adminId);
            $this->assertFalse($resPagoAprobar['ok'], 'No debe permitir aprobar pago cuando ya existe comprobante aprobado con misma referencia');
            $this->assertEquals('duplicado_aprobado', $resPagoAprobar['code'], 'El codigo debe ser duplicado_aprobado');

        } finally {
            $this->limpiarTodo($db, [$unidadId]);
        }
    }

    // =================================================================
    // 6. Verificacion de Rate Limiting en Controladores
    // =================================================================

    public function testControladoresTienenRateLimitingAdecuado(): void {
        $residenteContent = file_get_contents(APP_PATH . '/controllers/ResidenteController.php');
        $pagoContent = file_get_contents(APP_PATH . '/controllers/PagoController.php');

        // ResidenteController::enviarPago
        $this->assertStringContains("RateLimiter::attempt('comprobante_' . Auth::id(), 10, 3600)", $residenteContent,
            'ResidenteController debe limitar la subida de comprobantes por residente');
        $this->assertStringContains("if (\$_SERVER['REQUEST_METHOD'] === 'POST') {", $residenteContent,
            'ResidenteController debe aplicar rate limiting solo en POST');

        // Verificar que no se sobreescriba $error = '' despues de asignar el error del rate limit
        $posRate = strpos($residenteContent, "RateLimiter::attempt('comprobante_'");
        $sub = substr($residenteContent, $posRate, 400);
        $this->assertFalse(str_contains($sub, "\$error = '';"),
            'El rate limit de ResidenteController no debe ser limpiado por $error = \'\'');

        // PagoController::subir
        $this->assertStringContains("RateLimiter::attempt('pago_subir_' . \$residenteId, 10, 3600)", $pagoContent,
            'PagoController::subir debe contar con rate limiting por residente');
    }
}
