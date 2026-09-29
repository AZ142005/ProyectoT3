<?php
namespace Tests;

use App\Core\Database;
use App\Core\EstadoPago;
use App\Models\MovimientosModel;
use App\Models\PagoModel;
use PDO;

/**
 * Pruebas de la corrección de pagos duplicados:
 * - normalización de referencia de pago
 * - máquina de estados centralizada
 * - idempotencia de aprobación (sin doble crédito ni doble auditoría)
 * - guardia única en base de datos (pagos) e idempotencia del libro mayor (huella)
 * - política unificada de soft-delete: un APROBADO conserva su identidad económica en todas las capas
 */
class PagoDuplicadosTest extends TestCase {

    // =================================================================
    // Normalización de referencia (D6)
    // =================================================================

    public function testNormalizadorReferenciaPago(): void {
        $this->assertEquals('1234', PagoModel::normalizarReferenciaPago(' 00123-4 '), 'Debe limpiar separadores y ceros a la izquierda');
        $this->assertEquals('ABC123', PagoModel::normalizarReferenciaPago('ABC-123'), 'Debe conservar letras y quitar separadores');
        $this->assertEquals('AB12CD', PagoModel::normalizarReferenciaPago('ab.12_cd'), 'Debe pasar a mayúsculas y quitar separadores');
        $this->assertNull(PagoModel::normalizarReferenciaPago(''), 'Cadena vacía debe ser null');
        $this->assertNull(PagoModel::normalizarReferenciaPago('   '), 'Solo espacios debe ser null');
        $this->assertNull(PagoModel::normalizarReferenciaPago(null), 'null debe ser null');
        $this->assertEquals('0', PagoModel::normalizarReferenciaPago('000'), 'Todo ceros debe ser 0');
        $this->assertEquals('123', PagoModel::normalizarReferenciaPago('00123'), 'Debe quitar ceros a la izquierda');
        $this->assertEquals('123456', PagoModel::normalizarReferenciaPago('12/34#56'), 'Debe quitar / y #');
    }

    // =================================================================
    // Máquina de estados centralizada (D1)
    // =================================================================

    public function testEstadoPagoTransicionesValidasEInvalidas(): void {
        $this->assertTrue(EstadoPago::puedeTransicionar('PENDIENTE', 'APROBADO'), 'PENDIENTE → APROBADO');
        $this->assertTrue(EstadoPago::puedeTransicionar('PENDIENTE', 'EN REVISIÓN'), 'PENDIENTE → EN REVISIÓN');
        $this->assertTrue(EstadoPago::puedeTransicionar('PENDIENTE', 'RECHAZADO'), 'PENDIENTE → RECHAZADO');
        $this->assertTrue(EstadoPago::puedeTransicionar('EN REVISIÓN', 'APROBADO'), 'EN REVISIÓN → APROBADO');
        $this->assertTrue(EstadoPago::puedeTransicionar('EN REVISIÓN', 'RECHAZADO'), 'EN REVISIÓN → RECHAZADO');

        $this->assertFalse(EstadoPago::puedeTransicionar('APROBADO', 'RECHAZADO'), 'APROBADO es terminal');
        $this->assertFalse(EstadoPago::puedeTransicionar('APROBADO', 'EN REVISIÓN'), 'APROBADO es terminal');
        $this->assertFalse(EstadoPago::puedeTransicionar('RECHAZADO', 'APROBADO'), 'RECHAZADO es terminal');
        $this->assertFalse(EstadoPago::puedeTransicionar('DESCONOCIDO', 'APROBADO'), 'Estado desconocido no transiciona');
    }

    // =================================================================
    // Helpers de fixtures
    // =================================================================

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
        $stmt->execute(['numero' => 'TEST-DUP-' . mt_rand(100000, 999999)]);
        return intval($db->lastInsertId());
    }

    private function crearPago(PDO $db, int $residenteId, int $unidadId, ?string $referencia, string $estado = 'PENDIENTE'): int {
        $referenciaNorm = PagoModel::normalizarReferenciaPago($referencia);

        if ($this->columnaExiste($db, 'pagos', 'referencia_norm')) {
            $stmt = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
                VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'test_duplicados.png', :estado)
            ");
            $stmt->execute([
                'residente_id'    => $residenteId,
                'unidad_id'       => $unidadId,
                'referencia'      => $referencia,
                'referencia_norm' => $referenciaNorm,
                'estado'          => $estado
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, archivo, estado)
                VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, 'test_duplicados.png', :estado)
            ");
            $stmt->execute([
                'residente_id' => $residenteId,
                'unidad_id'    => $unidadId,
                'referencia'   => $referencia,
                'estado'       => $estado
            ]);
        }

        return intval($db->lastInsertId());
    }

    private function contarAuditoria(PDO $db, int $pagoId, string $accion, ?string $estadoNuevo = null): int {
        $sql = "SELECT COUNT(*) FROM log_auditoria WHERE pago_id = :pago_id AND accion = :accion";
        $params = ['pago_id' => $pagoId, 'accion' => $accion];
        if ($estadoNuevo !== null) {
            $sql .= " AND estado_nuevo = :estado_nuevo";
            $params['estado_nuevo'] = $estadoNuevo;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return intval($stmt->fetchColumn());
    }

    private function contarMovimientosUnidad(PDO $db, int $unidadId): int {
        $stmt = $db->prepare("SELECT COUNT(*) FROM movimientos_cuenta WHERE unidad_id = :unidad_id");
        $stmt->execute(['unidad_id' => $unidadId]);
        return intval($stmt->fetchColumn());
    }

    /**
     * Limpia todas las filas creadas por un fixture de pago (movimientos,
     * facturas de saldo a favor, auditoría, notificaciones, pago y unidad).
     */
    private function limpiarFixtures(PDO $db, int $pagoId, int $unidadId, int $maxNotif, int $maxCola): void {
        $db->prepare("DELETE FROM movimientos_cuenta WHERE unidad_id = :unidad_id")->execute(['unidad_id' => $unidadId]);
        $db->prepare("DELETE FROM facturas WHERE unidad_id = :unidad_id")->execute(['unidad_id' => $unidadId]);
        $db->prepare("DELETE FROM log_auditoria WHERE pago_id = :pago_id")->execute(['pago_id' => $pagoId]);
        $db->prepare("DELETE FROM pagos WHERE id = :pago_id")->execute(['pago_id' => $pagoId]);
        $db->prepare("DELETE FROM unidades WHERE id = :unidad_id")->execute(['unidad_id' => $unidadId]);
        $db->exec("DELETE FROM notificaciones WHERE id > {$maxNotif}");
        $db->exec("DELETE FROM notificaciones_cola WHERE id > {$maxCola}");
    }

    // =================================================================
    // Idempotencia de aprobación (D2)
    // =================================================================

    public function testAprobacionRepetidaNoGeneraDobleCredito(): void {
        $db = Database::getConnection();

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios) para la prueba.');
            return;
        }

        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadId = 0;
        $pagoId = 0;
        $model = new PagoModel();

        try {
            $unidadId = $this->crearUnidad($db);
            $pagoId = $this->crearPago($db, $residenteId, $unidadId, 'TESTDUP-' . mt_rand(1000, 9999));

            // Primera aprobación: actualiza y liquida una sola vez
            $primero = $model->cambiarEstado($pagoId, 'APROBADO', 'Prueba de aprobación', $adminId);
            $this->assertTrue(!empty($primero['ok']), 'La primera aprobación debe ser exitosa');
            $this->assertEquals('actualizado', $primero['code'], 'La primera aprobación debe actualizar');

            $this->assertEquals(1, $this->contarMovimientosUnidad($db, $unidadId), 'Debe existir exactamente un movimiento de abono');
            $this->assertEquals(1, $this->contarAuditoria($db, $pagoId, 'cambio_estado', 'APROBADO'), 'Debe existir exactamente un cambio_estado/APROBADO');

            $notifTrasPrimera = intval($db->query("SELECT COUNT(*) FROM notificaciones WHERE id > {$maxNotif}")->fetchColumn());
            $colaTrasPrimera = intval($db->query("SELECT COUNT(*) FROM notificaciones_cola WHERE id > {$maxCola}")->fetchColumn());

            // Segunda aprobación (reintento / doble clic): no-op idempotente
            $segundo = $model->cambiarEstado($pagoId, 'APROBADO', 'Reintento duplicado', $adminId);
            $this->assertEquals('sin_cambios', $segundo['code'], 'El reintento debe ser sin_cambios');
            $this->assertTrue(!empty($segundo['ok']), 'sin_cambios no es un error de operación');

            $this->assertEquals(1, $this->contarMovimientosUnidad($db, $unidadId), 'No debe crear un segundo movimiento (doble crédito)');
            $this->assertEquals(1, $this->contarAuditoria($db, $pagoId, 'cambio_estado', 'APROBADO'), 'No debe crear un segundo cambio_estado/APROBADO');
            $this->assertEquals(1, $this->contarAuditoria($db, $pagoId, 'intento_bloqueado'), 'El reintento debe auditarse exactamente una vez como intento_bloqueado');
            $this->assertEquals($notifTrasPrimera, intval($db->query("SELECT COUNT(*) FROM notificaciones WHERE id > {$maxNotif}")->fetchColumn()), 'El reintento no debe duplicar notificaciones en bandeja');
            $this->assertEquals($colaTrasPrimera, intval($db->query("SELECT COUNT(*) FROM notificaciones_cola WHERE id > {$maxCola}")->fetchColumn()), 'El reintento no debe duplicar correos en el outbox');
        } finally {
            if ($unidadId > 0) {
                $this->limpiarFixtures($db, $pagoId, $unidadId, $maxNotif, $maxCola);
            }
        }
    }

    public function testTransicionInvalidaBajoLockNoAltereaEstado(): void {
        $db = Database::getConnection();

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios) para la prueba.');
            return;
        }

        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadId = 0;
        $pagoId = 0;
        $model = new PagoModel();

        try {
            $unidadId = $this->crearUnidad($db);
            $pagoId = $this->crearPago($db, $residenteId, $unidadId, 'TESTINV-' . mt_rand(1000, 9999));

            $aprobado = $model->cambiarEstado($pagoId, 'APROBADO', 'Aprobación previa', $adminId);
            $this->assertEquals('actualizado', $aprobado['code'], 'Debe aprobarse primero');

            // APROBADO es terminal: intentar EN REVISIÓN debe bloquearse bajo el lock
            $invalido = $model->cambiarEstado($pagoId, 'EN REVISIÓN', 'Intento inválido', $adminId);
            $this->assertFalse(!empty($invalido['ok']), 'La transición inválida no debe ejecutarse');
            $this->assertEquals('transicion_invalida', $invalido['code'], 'Debe reportar transicion_invalida');
            $this->assertEquals('APROBADO', $invalido['estado_actual'], 'El estado actual debe seguir siendo APROBADO');

            $pagoActual = $model->obtenerPagoPorId($pagoId);
            $this->assertEquals('APROBADO', $pagoActual['estado'], 'El estado persistido no debe cambiar');
            $this->assertEquals(1, $this->contarAuditoria($db, $pagoId, 'intento_bloqueado'), 'El intento inválido debe auditarse una sola vez');
        } finally {
            if ($unidadId > 0) {
                $this->limpiarFixtures($db, $pagoId, $unidadId, $maxNotif, $maxCola);
            }
        }
    }

    public function testContencionAprobacionConcurrenteDosConexiones(): void {
        $db = Database::getConnection();

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios) para la prueba.');
            return;
        }

        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        // Segunda conexión independiente al mismo servidor MySQL/MariaDB.
        $db2 = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $unidadId = 0;
        $pagoId = 0;
        $model = new PagoModel();

        try {
            $unidadId = $this->crearUnidad($db);
            $pagoId = $this->crearPago($db, $residenteId, $unidadId, 'CONTEND-' . mt_rand(1000, 9999));

            // Conexión B retiene el lock de la fila con FOR UPDATE dentro de una
            // transacción, pero SIN commitear: si la aprobación no re-validara
            // bajo lock, la conexión A podría leer estado PENDIENTE y duplicar.
            $db2->beginTransaction();
            $stmtLock = $db2->prepare("SELECT estado FROM pagos WHERE id = :id FOR UPDATE");
            $stmtLock->execute(['id' => $pagoId]);
            $estadoBloqueado = (string)$stmtLock->fetchColumn();
            $this->assertEquals('PENDIENTE', strtoupper(trim($estadoBloqueado)), 'La conexión B debe ver el pago PENDIENTE antes de la contención');

            // La conexión A intenta aprobar mientras B retiene el lock: debe
            // bloquearse (el modelo reintenta deadlock 1213 y lock wait 1205) y
            // resolver sin doble crédito ni doble auditoría.
            $timeoutAnterior = intval($db->query("SELECT @@innodb_lock_wait_timeout")->fetchColumn());
            $db->exec("SET SESSION innodb_lock_wait_timeout = 1");
            try {
                $resultado = $model->cambiarEstado($pagoId, 'APROBADO', 'Prueba de contención', $adminId);
            } finally {
                $db->exec("SET SESSION innodb_lock_wait_timeout = " . $timeoutAnterior);
                $db2->commit();
            }

            // La conexión B libera el lock en el `finally`; si la contención se
            // resolvió como conflicto (lock wait timeout 1205 en A), el llamador
            // reintenta y la operación converge sin duplicar el crédito. Si ya
            // convergió, este reintento no se ejecuta.
            if ($resultado['code'] === 'conflicto') {
                $resultado = $model->cambiarEstado($pagoId, 'APROBADO', 'Prueba de contención (reintento)', $adminId);
            }

            // Tras liberar el lock de B, la aprobación de A converge:
            // exactamente un movimiento y exactamente un cambio de estado.
            $this->assertEquals(1, $this->contarMovimientosUnidad($db, $unidadId), 'No debe existir doble crédito tras la contención');
            $this->assertEquals(1, $this->contarAuditoria($db, $pagoId, 'cambio_estado', 'APROBADO'), 'Debe existir exactamente un cambio_estado/APROBADO');
            $this->assertTrue(in_array($resultado['code'], ['actualizado', 'sin_cambios', 'conflicto'], true), 'La contención debe resolverse de forma controlada, sin excepción inesperada');

            $pagoActual = $model->obtenerPagoPorId($pagoId);
            $this->assertEquals('APROBADO', strtoupper(trim($pagoActual['estado'])), 'El pago debe quedar aprobado exactamente una vez');
        } finally {
            if ($db2->inTransaction()) {
                $db2->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarFixtures($db, $pagoId, $unidadId, $maxNotif, $maxCola);
            }
        }
    }

    // =================================================================
    // Política unificada de soft-delete (R3-001)
    // =================================================================

    public function testSoftDeletedAprobadoConservaLaIdentidadEconomica(): void {
        $db = Database::getConnection();

        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm') || !$this->indiceExiste($db, 'pagos', 'uk_pago_dup_guard')) {
            $this->skip('migración no aplicada');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('Datos insuficientes (personas) para la prueba.');
            return;
        }
        $residenteId = intval($residente['id']);

        $unidadId = 0;
        $pagoTwinId = 0;

        try {
            $unidadId = $this->crearUnidad($db);
            $referencia = 'SOFTDEL-APR-' . mt_rand(100000, 999999);
            $referenciaNorm = PagoModel::normalizarReferenciaPago($referencia);

            // Twin APROBADO y soft-deleted: el crédito original persiste en el
            // libro mayor, por lo que conserva su identidad económica en TODAS
            // las capas (dup_guard, pre-check de crearPago y guardias de aprobación).
            $stmt = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado, deleted_at)
                VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'softdel_aprobado.png', 'APROBADO', NOW())
            ");
            $stmt->execute([
                'residente_id'    => $residenteId,
                'unidad_id'       => $unidadId,
                'referencia'      => $referencia,
                'referencia_norm' => $referenciaNorm
            ]);
            $pagoTwinId = intval($db->lastInsertId());

            // Inserción directa con la misma identidad: la guardia única debe rechazarla.
            $codigoError = null;
            try {
                $db->prepare("
                    INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
                    VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'softdel_dup.png', 'PENDIENTE')
                ")->execute([
                    'residente_id'    => $residenteId,
                    'unidad_id'       => $unidadId,
                    'referencia'      => $referencia,
                    'referencia_norm' => $referenciaNorm
                ]);
            } catch (\PDOException $e) {
                $codigoError = (string)$e->getCode();
            }
            $this->assertEquals('23000', $codigoError, 'Un APROBADO soft-deleted debe conservar la identidad (dup_guard no lo libera)');

            // El pre-check de crearPago aplica la misma política.
            $model = new PagoModel();
            $creado = $model->crearPago(
                $residenteId,
                $unidadId,
                [
                    'monto'       => 100.00,
                    'fecha_pago'  => date('Y-m-d'),
                    'metodo_pago' => 'transferencia',
                    'referencia'  => $referencia
                ],
                'softdel_precheck.png'
            );
            $this->assertFalse($creado, 'crearPago debe rechazar la identidad retenida por un APROBADO soft-deleted');
        } finally {
            if ($unidadId > 0) {
                $db->prepare("DELETE FROM log_auditoria WHERE pago_id = :pago_id")->execute(['pago_id' => $pagoTwinId]);
                $db->prepare("DELETE FROM pagos WHERE unidad_id = :unidad_id")->execute(['unidad_id' => $unidadId]);
                $db->prepare("DELETE FROM unidades WHERE id = :unidad_id")->execute(['unidad_id' => $unidadId]);
            }
        }
    }

    public function testSoftDeletedNoAprobadoLiberaLaIdentidad(): void {
        $db = Database::getConnection();

        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm') || !$this->indiceExiste($db, 'pagos', 'uk_pago_dup_guard')) {
            $this->skip('migración no aplicada');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('Datos insuficientes (personas) para la prueba.');
            return;
        }
        $residenteId = intval($residente['id']);

        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db);
            $referencia = 'SOFTDEL-PEN-' . mt_rand(100000, 999999);
            $referenciaNorm = PagoModel::normalizarReferenciaPago($referencia);

            // Twin PENDIENTE soft-deleted: no hubo crédito, la identidad se libera.
            $stmt = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado, deleted_at)
                VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'softdel_pendiente.png', 'PENDIENTE', NOW())
            ");
            $stmt->execute([
                'residente_id'    => $residenteId,
                'unidad_id'       => $unidadId,
                'referencia'      => $referencia,
                'referencia_norm' => $referenciaNorm
            ]);

            // Un nuevo pago con la misma identidad debe poder registrarse.
            $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
                VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'softdel_nuevo.png', 'PENDIENTE')
            ")->execute([
                'residente_id'    => $residenteId,
                'unidad_id'       => $unidadId,
                'referencia'      => $referencia,
                'referencia_norm' => $referenciaNorm
            ]);
            $this->assertTrue(true, 'Un soft-deleted sin crédito libera la identidad (dup_guard NULL)');
        } finally {
            if ($unidadId > 0) {
                $db->prepare("DELETE FROM pagos WHERE unidad_id = :unidad_id")->execute(['unidad_id' => $unidadId]);
                $db->prepare("DELETE FROM unidades WHERE id = :unidad_id")->execute(['unidad_id' => $unidadId]);
            }
        }
    }

    // =================================================================
    // Guardia única en base de datos (D7)
    // =================================================================

    public function testGuardiaUnicaRechazaPagoDuplicado(): void {
        $db = Database::getConnection();

        if (!$this->columnaExiste($db, 'pagos', 'referencia_norm') || !$this->indiceExiste($db, 'pagos', 'uk_pago_dup_guard')) {
            $this->skip('migración no aplicada');
            return;
        }

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente) {
            $this->skip('Datos insuficientes (personas) para la prueba.');
            return;
        }
        $residenteId = intval($residente['id']);

        $db->beginTransaction();
        try {
            $unidadId = $this->crearUnidad($db);
            $referencia = 'DUP-TEST-' . mt_rand(100000, 999999);
            $referenciaNorm = PagoModel::normalizarReferenciaPago($referencia);

            // Primer pago (PENDIENTE): permitido
            $stmt = $db->prepare("
                INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, referencia_norm, archivo, estado)
                VALUES (:residente_id, :unidad_id, 100.00, CURDATE(), 'transferencia', :referencia, :referencia_norm, 'dup.png', 'PENDIENTE')
            ");
            $stmt->execute([
                'residente_id'    => $residenteId,
                'unidad_id'       => $unidadId,
                'referencia'      => $referencia,
                'referencia_norm' => $referenciaNorm
            ]);

            // Segundo pago con la misma identidad: debe violar uk_pago_dup_guard (23000)
            $codigoError = null;
            try {
                $stmt->execute([
                    'residente_id'    => $residenteId,
                    'unidad_id'       => $unidadId,
                    'referencia'      => $referencia . ' ',
                    'referencia_norm' => $referenciaNorm
                ]);
            } catch (\PDOException $e) {
                $codigoError = (string)$e->getCode();
            }
            $this->assertEquals('23000', $codigoError, 'El INSERT duplicado debe fallar con SQLSTATE 23000');

            // Tras rechazar el primero, la guardia libera la identidad (re-subida permitida)
            $db->prepare("UPDATE pagos SET estado = 'RECHAZADO' WHERE unidad_id = :unidad_id AND referencia_norm = :referencia_norm")
               ->execute(['unidad_id' => $unidadId, 'referencia_norm' => $referenciaNorm]);

            $stmt->execute([
                'residente_id'    => $residenteId,
                'unidad_id'       => $unidadId,
                'referencia'      => $referencia,
                'referencia_norm' => $referenciaNorm
            ]);
            $this->assertTrue(true, 'Tras el rechazo, la misma identidad puede re-subirse');
        } finally {
            $db->rollBack();
        }
    }

    // =================================================================
    // Idempotencia del libro mayor por huella (D5)
    // =================================================================

    public function testMovimientoIdempotentePorHuella(): void {
        $db = Database::getConnection();

        if (!$this->columnaExiste($db, 'movimientos_cuenta', 'huella') || !$this->indiceExiste($db, 'movimientos_cuenta', 'uk_mov_huella')) {
            $this->skip('migración no aplicada');
            return;
        }

        $db->beginTransaction();
        try {
            $unidadId = $this->crearUnidad($db);
            $model = new MovimientosModel();

            $id1 = $model->registrarMovimiento($unidadId, 'abono_pago', 50.00, 'Test huella', 123456, 'pago');
            $id2 = $model->registrarMovimiento($unidadId, 'abono_pago', 50.00, 'Test huella duplicada', 123456, 'pago');

            $this->assertEquals($id1, $id2, 'La segunda inserción con la misma huella debe devolver el mismo movimiento');
            $this->assertEquals(1, $this->contarMovimientosUnidad($db, $unidadId), 'No debe existir un segundo movimiento');

            $id3 = $model->registrarMovimiento($unidadId, 'abono_pago', 60.00, 'Test huella distinta', 123456, 'pago');
            $this->assertNotEquals($id1, $id3, 'Un monto distinto produce una huella distinta');
            $this->assertEquals(2, $this->contarMovimientosUnidad($db, $unidadId), 'Debe existir un segundo movimiento con huella distinta');
        } finally {
            $db->rollBack();
        }
    }

    // =================================================================
    // Aprobación por lote (D3)
    // =================================================================

    public function testAprobarLoteOmitePagosYaProcesados(): void {
        $db = Database::getConnection();

        $residente = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $admin = $db->query("SELECT id FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$residente || !$admin) {
            $this->skip('Datos insuficientes (personas/usuarios) para la prueba.');
            return;
        }

        $residenteId = intval($residente['id']);
        $adminId = intval($admin['id']);
        $maxNotif = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones")->fetchColumn());
        $maxCola = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM notificaciones_cola")->fetchColumn());

        $unidadId = 0;
        $pagoAprobadoId = 0;
        $pagoPendienteId = 0;
        $model = new PagoModel();

        try {
            $unidadId = $this->crearUnidad($db);
            $pagoAprobadoId = $this->crearPago($db, $residenteId, $unidadId, 'LOTE-APR-' . mt_rand(1000, 9999), 'APROBADO');
            $pagoPendienteId = $this->crearPago($db, $residenteId, $unidadId, 'LOTE-PEN-' . mt_rand(1000, 9999), 'PENDIENTE');

            $resultado = $model->aprobarLote([$pagoAprobadoId, $pagoPendienteId], $adminId);

            $this->assertEquals(1, $resultado['procesados'], 'Solo el pago pendiente debe procesarse');
            $this->assertEquals(1, $resultado['omitidos'], 'El pago ya aprobado debe contarse como omitido');
            $this->assertTrue(array_key_exists('duplicados', $resultado), 'El resultado debe incluir el contador de duplicados');
            $this->assertEquals(0, $resultado['duplicados'], 'Sin duplicados legacy, el contador debe ser 0');

            $pagoActual = $model->obtenerPagoPorId($pagoPendienteId);
            $this->assertEquals('APROBADO', $pagoActual['estado'], 'El pago pendiente debe quedar aprobado');
        } finally {
            if ($unidadId > 0) {
                $db->prepare("DELETE FROM movimientos_cuenta WHERE unidad_id = :unidad_id")->execute(['unidad_id' => $unidadId]);
                $db->prepare("DELETE FROM facturas WHERE unidad_id = :unidad_id")->execute(['unidad_id' => $unidadId]);
                $db->prepare("DELETE FROM log_auditoria WHERE pago_id IN (:pago_a, :pago_b)")->execute(['pago_a' => $pagoAprobadoId, 'pago_b' => $pagoPendienteId]);
                $db->prepare("DELETE FROM pagos WHERE id IN (:pago_a, :pago_b)")->execute(['pago_a' => $pagoAprobadoId, 'pago_b' => $pagoPendienteId]);
                $db->prepare("DELETE FROM unidades WHERE id = :unidad_id")->execute(['unidad_id' => $unidadId]);
                $db->exec("DELETE FROM notificaciones WHERE id > {$maxNotif}");
                $db->exec("DELETE FROM notificaciones_cola WHERE id > {$maxCola}");
            }
        }
    }
}
