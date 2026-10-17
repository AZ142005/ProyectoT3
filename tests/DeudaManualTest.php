<?php
namespace Tests;

use App\Core\Database;
use App\Models\DeudasManualesModel;
use App\Models\FacturasModel;
use App\Models\MovimientosModel;
use PDO;

/**
 * Tests live-DB de la deuda manual por unidad/período y de la emisión con upsert.
 *
 * Cada caso envuelve sus datos en una transacción y hace rollback en finally
 * (patrón SaldoFavorTest): todos los modelos respetan la transacción ambiente
 * (no inician/confirmar transacciones propias cuando ya hay una activa).
 * El período de prueba 11/2027 no debe tener gastos declarados ni datos reales,
 * de modo que el fallback de cuota_mensual sea determinista. El caso de CART
 * reutiliza el período real 10/2026 solo para verificar que no se tocan.
 */
class DeudaManualTest extends TestCase {

    private const MES = 11;
    private const ANIO = 2027;

    // =====================================================================
    // 1. Clases, métodos y esquema
    // =====================================================================

    public function testClasesMetodosYTablaExisten(): void {
        $this->assertTrue(class_exists(DeudasManualesModel::class), 'DeudasManualesModel debe existir');

        $metodos = ['guardarLote', 'obtenerPorPeriodo', 'obtenerMapaPorPeriodo', 'eliminar', 'eliminarPeriodo'];
        foreach ($metodos as $metodo) {
            $this->assertTrue(method_exists(DeudasManualesModel::class, $metodo),
                "DeudasManualesModel::{$metodo} debe existir");
        }
        $this->assertTrue(method_exists(FacturasModel::class, 'countByPeriod'), 'FacturasModel::countByPeriod debe existir');

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deudas_manuales'
        ");
        $stmt->execute();
        $this->assertTrue((int)$stmt->fetchColumn() > 0, 'La tabla deudas_manuales debe existir');

        $stmtIdx = $db->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deudas_manuales'
              AND INDEX_NAME = 'uk_deuda_manual_unidad_periodo'
        ");
        $stmtIdx->execute();
        $this->assertEquals(3, (int)$stmtIdx->fetchColumn(),
            'La guardia única debe cubrir (unidad_id, mes, anio)');
    }

    // =====================================================================
    // 2. guardarLote: guarda y reemplaza (upsert por unidad+período)
    // =====================================================================

    public function testGuardarLoteGuardaYReemplaza(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db, 10.00);
            $modelo = new DeudasManualesModel();
            $maxLog = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM log_auditoria")->fetchColumn();

            $guardadas = $modelo->guardarLote(self::MES, self::ANIO, [$unidadId], 50.00, 1);
            $this->assertEquals(1, $guardadas, 'guardarLote debe reportar 1 unidad guardada');

            $mapa = $modelo->obtenerMapaPorPeriodo(self::MES, self::ANIO);
            $this->assertEquals(50.00, $mapa[$unidadId] ?? null,
                'El mapa del período debe traer el monto manual de la unidad');

            // Reemplazo: guardar de nuevo sobre la misma unidad+período
            $modelo->guardarLote(self::MES, self::ANIO, [$unidadId], 75.50, 1);

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM deudas_manuales
                WHERE unidad_id = :id AND mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmt->execute(['id' => $unidadId, 'mes' => self::MES, 'anio' => self::ANIO]);
            $this->assertEquals(1, (int)$stmt->fetchColumn(),
                'Guardar sobre la misma unidad+período no debe duplicar filas');

            $stmtMonto = $db->prepare("
                SELECT monto FROM deudas_manuales
                WHERE unidad_id = :id AND mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmtMonto->execute(['id' => $unidadId, 'mes' => self::MES, 'anio' => self::ANIO]);
            $this->assertEquals(75.50, (float)$stmtMonto->fetchColumn(),
                'El nuevo monto debe reemplazar al anterior');

            $filas = $modelo->obtenerPorPeriodo(self::MES, self::ANIO);
            $propias = array_values(array_filter($filas, fn($f) => intval($f['unidad_id']) === $unidadId));
            $this->assertEquals(1, count($propias), 'obtenerPorPeriodo debe listar una sola fila para la unidad');
            $this->assertEquals(75.50, (float)$propias[0]['monto'], 'La fila listada debe traer el monto nuevo');
            $this->assertTrue(!empty($propias[0]['unidad_numero']), 'La fila debe incluir el número de unidad (join)');

            // Una fila de auditoría por cada lote guardado
            $stmtLog = $db->prepare("
                SELECT COUNT(*) FROM log_auditoria
                WHERE id > :max AND accion = 'deuda_manual'
            ");
            $stmtLog->execute(['max' => $maxLog]);
            $this->assertEquals(2, (int)$stmtLog->fetchColumn(),
                'Cada guardarLote debe registrar una fila de auditoría');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    // =====================================================================
    // 3. Edificio seleccionado: monto completo (sin reparto) en cada unidad
    // =====================================================================

    public function testEdificioSeleccionadoAplicaMontoCompletoPorUnidad(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadIds = [];
        $edificioId = 0;

        try {
            $edificioId = $this->crearEdificio($db);
            $unidadIds[] = $this->crearUnidad($db, 0.0, $edificioId);
            $unidadIds[] = $this->crearUnidad($db, 0.0, $edificioId);

            // Resolución equivalente a la del controlador: edificio => sus unidades activas
            $stmt = $db->prepare("SELECT id, cuota_mensual FROM unidades WHERE edificio_id = :ed AND estado = 1 ORDER BY id ASC");
            $stmt->execute(['ed' => $edificioId]);
            $unidadesEdificio = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->assertEquals(2, count($unidadesEdificio), 'El edificio de prueba debe tener 2 unidades activas');

            $ids = array_map(fn($u) => intval($u['id']), $unidadesEdificio);
            (new DeudasManualesModel())->guardarLote(self::MES, self::ANIO, $ids, 55.00, 1);

            $stats = (new FacturasModel())->crearFacturasMasivas($unidadesEdificio, self::MES, self::ANIO);
            $this->assertTrue(is_array($stats), 'La emisión debe devolver estadísticas');
            $this->assertEquals(2, $stats['generadas'], 'Cada unidad del edificio debe generar su factura');

            foreach ($ids as $uid) {
                $factura = $this->facturaDe($db, $this->numeroFactura($uid));
                $this->assertTrue($factura !== false, "La unidad {$uid} debe tener factura del período");
                $this->assertEquals(55.00, (float)$factura['monto_total'],
                    'El monto elegido debe aplicarse completo a cada unidad (no se reparte entre ellas)');
                $this->assertEquals(55.00, (float)$factura['saldo'],
                    'La factura debe nacer pendiente por el monto completo');
            }
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($unidadIds as $uid) {
                $this->limpiarResiduos($db, $uid);
            }
            if ($edificioId > 0) {
                try {
                    $db->prepare("DELETE FROM edificios WHERE id = :id")->execute(['id' => $edificioId]);
                } catch (\Throwable $e) {
                    error_log('[DeudaManualTest] No se pudo limpiar el edificio de prueba: ' . $e->getMessage());
                }
            }
        }
    }

    // =====================================================================
    // 4. Emisión: deuda manual sustituye la cuota; cuota normal; cuota 0 sin factura
    // =====================================================================

    public function testEmisionConOverrideManualYCuotaNormal(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadIds = [];

        try {
            $stmtG = $db->prepare("SELECT COUNT(*) FROM gastos_comunes WHERE mes = :mes AND anio = :anio AND deleted_at IS NULL");
            $stmtG->execute(['mes' => self::MES, 'anio' => self::ANIO]);
            $this->assertEquals(0, (int)$stmtG->fetchColumn(),
                'El período de prueba no debe tener gastos declarados (fallback cuota_mensual determinista)');

            $unidadManual = $this->crearUnidad($db, 80.00);
            $unidadNormal = $this->crearUnidad($db, 80.00);
            $unidadSinCuota = $this->crearUnidad($db, 0.0);
            $unidadIds = [$unidadManual, $unidadNormal, $unidadSinCuota];

            (new DeudasManualesModel())->guardarLote(self::MES, self::ANIO, [$unidadManual], 33.33, 1);

            $stats = $this->emitir($db, $unidadIds);
            $this->assertTrue(is_array($stats), 'La emisión debe devolver estadísticas');
            $this->assertEquals(2, $stats['generadas'],
                'Deben generarse 2 facturas: la unidad de cuota 0 sin deuda manual no factura');

            $fManual = $this->facturaDe($db, $this->numeroFactura($unidadManual));
            $this->assertTrue($fManual !== false, 'La unidad con deuda manual debe tener factura');
            $this->assertEquals(33.33, (float)$fManual['monto_total'],
                'La deuda manual debe sustituir la cuota prorrateada por el monto exacto (sin reparto)');

            $fNormal = $this->facturaDe($db, $this->numeroFactura($unidadNormal));
            $this->assertTrue($fNormal !== false, 'La unidad sin deuda manual debe tener su factura de cuota normal');
            $this->assertEquals(80.00, (float)$fNormal['monto_total'],
                'Sin deuda manual se conserva el comportamiento de cuota prorrateada (fallback cuota_mensual)');

            $fCero = $this->facturaDe($db, $this->numeroFactura($unidadSinCuota));
            $this->assertTrue($fCero === false,
                'Una unidad con cuota 0 y sin deuda manual no debe generar factura');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($unidadIds as $uid) {
                $this->limpiarResiduos($db, $uid);
            }
        }
    }

    // =====================================================================
    // 5. Re-emisión: actualiza la misma factura, conserva lo pagado, ledger por delta
    // =====================================================================

    public function testReemisionActualizaSinAcumularYConservaPagado(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db, 0.0);
            $modelo = new DeudasManualesModel();
            $modelo->guardarLote(self::MES, self::ANIO, [$unidadId], 100.00, 1);

            $stats1 = $this->emitir($db, [$unidadId]);
            $this->assertEquals(1, $stats1['generadas'], 'La primera emisión debe crear la factura');

            $numero = $this->numeroFactura($unidadId);
            $facturaInicial = $this->facturaDe($db, $numero);
            $this->assertEquals(100.00, (float)$facturaInicial['monto_total'], 'La factura inicial debe valer 100.00');

            // Pago parcial simulado: lo pagado debe sobrevivir a la re-emisión
            $db->prepare("UPDATE facturas SET monto_pagado = 30.00, saldo = 70.00 WHERE id = :id")
               ->execute(['id' => $facturaInicial['id']]);

            $modelo->guardarLote(self::MES, self::ANIO, [$unidadId], 120.00, 1);
            $stats2 = $this->emitir($db, [$unidadId]);
            $this->assertEquals(0, $stats2['generadas'], 'La re-emisión no debe crear otra factura');
            $this->assertEquals(1, $stats2['actualizadas'], 'La re-emisión debe actualizar la factura existente');

            $stmtC = $db->prepare("SELECT COUNT(*) FROM facturas WHERE numero_factura = :numero");
            $stmtC->execute(['numero' => $numero]);
            $this->assertEquals(1, (int)$stmtC->fetchColumn(),
                'No deben acumularse facturas para el mismo número (upsert sin duplicar)');

            $facturaFinal = $this->facturaDe($db, $numero);
            $this->assertEquals((int)$facturaInicial['id'], (int)$facturaFinal['id'],
                'La re-emisión debe conservar la misma fila de factura');
            $this->assertEquals(120.00, (float)$facturaFinal['monto_total'], 'El monto debe reflejar el valor nuevo');
            $this->assertEquals(30.00, (float)$facturaFinal['monto_pagado'], 'Lo ya pagado debe conservarse');
            $this->assertEquals(90.00, (float)$facturaFinal['saldo'],
                'El saldo debe ser max(0, nuevo - pagado) = 120 - 30');
            $this->assertEquals('pendiente', $facturaFinal['estado'], 'Con saldo pendiente el estado debe ser pendiente');

            // Libro mayor: un único cargo por la creación + ajuste por el delta, sin inflarse
            $stmtCargo = $db->prepare("SELECT COUNT(*) FROM movimientos_cuenta WHERE unidad_id = :id AND tipo = 'cargo_factura'");
            $stmtCargo->execute(['id' => $unidadId]);
            $this->assertEquals(1, (int)$stmtCargo->fetchColumn(),
                'Debe existir un solo cargo_factura (la re-emisión no re-cobra)');

            $saldoMayor = (new MovimientosModel())->obtenerSaldoActualUnidad($unidadId);
            $this->assertEquals(120.00, $saldoMayor,
                'El libro mayor debe quedar en 120.00 (cargo 100 + ajuste 20), sin inflarse');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    // =====================================================================
    // 6. Factura soft-deleted: la re-emisión la reactiva con los valores nuevos
    // =====================================================================

    public function testReactivacionDeFacturaSoftDeleted(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db, 0.0);
            $modelo = new DeudasManualesModel();
            $modelo->guardarLote(self::MES, self::ANIO, [$unidadId], 40.00, 1);
            $this->emitir($db, [$unidadId]);

            $numero = $this->numeroFactura($unidadId);
            $inicial = $this->facturaDe($db, $numero);
            $this->assertTrue($inicial !== false, 'Debe existir la factura inicial');

            // Pago parcial simulado antes de la baja lógica: debe sobrevivir a la reactivación
            $db->prepare("UPDATE facturas SET monto_pagado = 10.00, saldo = 30.00 WHERE id = :id")
               ->execute(['id' => $inicial['id']]);

            $db->prepare("UPDATE facturas SET deleted_at = NOW() WHERE id = :id")
               ->execute(['id' => $inicial['id']]);

            $modelo->guardarLote(self::MES, self::ANIO, [$unidadId], 45.00, 1);
            $stats = $this->emitir($db, [$unidadId]);
            $this->assertEquals(1, $stats['reactivadas'], 'La emisión debe reactivar la factura soft-deleted');
            $this->assertEquals(0, $stats['generadas'], 'La reactivación no debe insertar una factura nueva');
            $this->assertEquals(0, $stats['actualizadas'], 'La reactivación no debe contar como actualización');

            $final = $this->facturaDe($db, $numero);
            $this->assertEquals((int)$inicial['id'], (int)$final['id'],
                'La reactivación debe reutilizar la misma fila, no crear otra');
            $this->assertNull($final['deleted_at'], 'La factura reactivada debe quedar activa');
            $this->assertEquals(45.00, (float)$final['monto_total'], 'La reactivación debe aplicar el monto nuevo');
            $this->assertEquals(10.00, (float)$final['monto_pagado'],
                'La reactivación debe conservar lo ya pagado (10.00)');
            $this->assertEquals(35.00, (float)$final['saldo'],
                'El saldo debe ser max(0, nuevo - pagado) = 45.00 - 10.00');
            $this->assertEquals('pendiente', $final['estado'], 'Con saldo pendiente el estado debe ser pendiente');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    // =====================================================================
    // 7. CART-… del período intactas; countByPeriod solo cuenta FAC-…
    // =====================================================================

    public function testCarteleraIntactaYCountByPeriodSoloFAC(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;
        $mesCart = 10;
        $anioCart = 2026;

        try {
            // Período real con deudas CART importadas (las que no deben tocarse)
            $stmtCart = $db->prepare("
                SELECT COUNT(*), COALESCE(SUM(saldo), 0)
                FROM facturas
                WHERE numero_factura LIKE 'CART-%' AND mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmtCart->execute(['mes' => $mesCart, 'anio' => $anioCart]);
            $filaCart = $stmtCart->fetch(PDO::FETCH_NUM);
            $cartCantidad = (int)$filaCart[0];
            $cartSaldo = (float)$filaCart[1];
            $this->assertGreaterThan(0, $cartCantidad,
                'El período 10/2026 debe tener deudas CART reales para verificar que no se tocan');

            $facturasModel = new FacturasModel();
            $stmtFac = $db->prepare("
                SELECT COUNT(*)
                FROM facturas
                WHERE numero_factura LIKE 'FAC-%' AND mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmtFac->execute(['mes' => $mesCart, 'anio' => $anioCart]);
            $facAntes = (int)$stmtFac->fetchColumn();
            $this->assertEquals($facAntes, $facturasModel->countByPeriod($mesCart, $anioCart),
                'countByPeriod debe contar solo las FAC-… activas (las CART no marcan el período como emitido)');

            $unidadId = $this->crearUnidad($db, 0.0);
            (new DeudasManualesModel())->guardarLote($mesCart, $anioCart, [$unidadId], 25.00, 1);

            $stats = $facturasModel->crearFacturasMasivas(
                [['id' => $unidadId, 'cuota_mensual' => 0.0]],
                $mesCart,
                $anioCart
            );
            $this->assertEquals(1, $stats['generadas'], 'La unidad con deuda manual debe emitir su FAC del período');

            $stmtCart->execute(['mes' => $mesCart, 'anio' => $anioCart]);
            $filaCartDespues = $stmtCart->fetch(PDO::FETCH_NUM);
            $this->assertEquals($cartCantidad, (int)$filaCartDespues[0], 'Las deudas CART del período no deben eliminarse ni duplicarse');
            $this->assertEquals($cartSaldo, (float)$filaCartDespues[1], 'Los saldos CART del período deben quedar intactos');

            $this->assertEquals($facAntes + 1, $facturasModel->countByPeriod($mesCart, $anioCart),
                'countByPeriod debe reflejar la nueva FAC y seguir ignorando las CART');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    // =====================================================================
    // 8. Bajas: eliminar (una unidad/período) y eliminarPeriodo (masiva)
    // =====================================================================

    public function testEliminarYEliminarPeriodoBajaLogica(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadIds = [];

        try {
            $modelo = new DeudasManualesModel();
            $unidadA = $this->crearUnidad($db, 0.0);
            $unidadB = $this->crearUnidad($db, 0.0);
            $unidadIds = [$unidadA, $unidadB];

            $modelo->guardarLote(self::MES, self::ANIO, [$unidadA, $unidadB], 20.00, 1);

            $this->assertTrue($modelo->eliminar($unidadA, self::MES, self::ANIO),
                'eliminar debe dar de baja la fila activa de la unidad');
            $mapa = $modelo->obtenerMapaPorPeriodo(self::MES, self::ANIO);
            $this->assertTrue(!array_key_exists($unidadA, $mapa), 'La unidad dada de baja no debe aparecer en el mapa');
            $this->assertTrue(array_key_exists($unidadB, $mapa), 'La otra unidad debe conservar su deuda manual');

            $this->assertFalse($modelo->eliminar($unidadA, self::MES, self::ANIO),
                'Eliminar una deuda ya dada de baja no debe reportar éxito');

            $this->assertEquals(1, $modelo->eliminarPeriodo(self::MES, self::ANIO),
                'eliminarPeriodo debe dar de baja solo las filas activas restantes');
            $this->assertEquals([], $modelo->obtenerMapaPorPeriodo(self::MES, self::ANIO),
                'Sin filas activas el mapa del período debe quedar vacío');
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($unidadIds as $uid) {
                $this->limpiarResiduos($db, $uid);
            }
        }
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function crearUnidad(PDO $db, float $cuota, ?int $edificioId = null): int {
        $stmt = $db->prepare("
            INSERT INTO unidades (numero, cuota_mensual, edificio_id, estado)
            VALUES (:numero, :cuota, :edificio_id, 1)
        ");
        $stmt->execute([
            'numero'      => 'TEST-DM-' . mt_rand(100000, 999999),
            'cuota'       => $cuota,
            'edificio_id' => $edificioId,
        ]);
        return intval($db->lastInsertId());
    }

    private function crearEdificio(PDO $db): int {
        $stmt = $db->prepare("INSERT INTO edificios (nombre, descripcion, estado) VALUES (:nombre, 'Test deuda manual', 1)");
        $stmt->execute(['nombre' => 'TEST-DM-TORRE-' . mt_rand(100000, 999999)]);
        return intval($db->lastInsertId());
    }

    private function numeroFactura(int $unidadId): string {
        return 'FAC-' . self::ANIO . '-' . str_pad((string)self::MES, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$unidadId, 4, '0', STR_PAD_LEFT);
    }

    private function facturaDe(PDO $db, string $numero): array|false {
        $stmt = $db->prepare("
            SELECT id, deleted_at, monto_total, monto_pagado, saldo, estado
            FROM facturas
            WHERE numero_factura = :numero
            LIMIT 1
        ");
        $stmt->execute(['numero' => $numero]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Emite las facturas del período de prueba para las unidades dadas,
     * cargando su cuota_mensual actual como haría el controlador.
     */
    private function emitir(PDO $db, array $unidadIds): array|false {
        $unidades = [];
        $stmt = $db->prepare("SELECT id, cuota_mensual FROM unidades WHERE id = :id");
        foreach ($unidadIds as $uid) {
            $stmt->execute(['id' => $uid]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fila !== false) {
                $unidades[] = $fila;
            }
        }
        return (new FacturasModel())->crearFacturasMasivas($unidades, self::MES, self::ANIO);
    }

    /**
     * Limpieza defensiva de residuos de una unidad de prueba (si algo llegara a
     * persistir fuera de la transacción del test).
     */
    private function limpiarResiduos(PDO $db, int $unidadId): void {
        try {
            $db->prepare("DELETE FROM deudas_manuales WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM movimientos_cuenta WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM facturas WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM unidades WHERE id = :id")->execute(['id' => $unidadId]);
        } catch (\Throwable $e) {
            error_log('[DeudaManualTest] Limpieza defensiva falló: ' . $e->getMessage());
        }
    }
}
