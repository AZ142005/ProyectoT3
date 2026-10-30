<?php
namespace Tests;

use App\Core\Database;
use App\Models\FacturasModel;
use App\Models\DeudasManualesModel;
use App\Models\MovimientosModel;
use PDO;

/**
 * Pruebas unitarias de unicidad e inmutabilidad de cobros mensuales.
 *
 * Valida:
 * 1. Protección estricta de facturas PAGADAS dentro del mismo mes:
 *    Si el cobro inicial fue pagado, un ajuste posterior NO lo modifica ni borra,
 *    sino que genera un nuevo cobro activo (PENDIENTE) con el delta.
 * 2. Unicidad de cobros activos:
 *    Si el cobro inicial sigue PENDIENTE (sin pagos en revisión), el ajuste posterior
 *    actualiza su monto sin duplicar facturas activas en el mes.
 * 3. Protección de facturas con pagos en revisión:
 *    Una factura con comprobante o pago en revisión no se sobreescribe.
 */
class CobrosUnicidadMensualTest extends TestCase {

    private const MES = 11;
    private const ANIO = 2028; // Período futuro de pruebas

    public function testFacturaPagadaNoSeAlteraYGeneraNuevoCobroActivo(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db, 0.0);
            $facturasModel = new FacturasModel();
            $deudasModel = new DeudasManualesModel();

            // 1. Día 1: Emisión inicial de 100.00 Bs
            $deudasModel->guardarLote(self::MES, self::ANIO, [$unidadId], 100.00, 1);
            $stats1 = $facturasModel->crearFacturasMasivas(
                [['id' => $unidadId, 'cuota_mensual' => 0.0]],
                self::MES,
                self::ANIO
            );
            $this->assertEquals(1, $stats1['generadas'], 'Día 1: Debe generarse la factura inicial');

            $numeroBase = $this->numeroFacturaBase($unidadId);
            $facturaDia1 = $this->obtenerFacturaPorNumero($db, $numeroBase);
            $this->assertNotNull($facturaDia1, 'La factura inicial debe existir');
            $this->assertEquals(100.00, (float)$facturaDia1['monto_total']);
            $this->assertEquals('vencida', $facturaDia1['estado']);

            // 2. Simular pago completo del Día 1: Factura queda PAGADA / CERRADA
            $db->prepare("
                UPDATE facturas 
                SET monto_pagado = 100.00, saldo = 0.00, estado = 'pagada' 
                WHERE id = :id
            ")->execute(['id' => $facturaDia1['id']]);

            // 3. Día 20: Administrador carga ajuste o nuevo total de 140.00 Bs en el mismo mes
            $deudasModel->guardarLote(self::MES, self::ANIO, [$unidadId], 140.00, 1);
            $stats2 = $facturasModel->crearFacturasMasivas(
                [['id' => $unidadId, 'cuota_mensual' => 0.0]],
                self::MES,
                self::ANIO
            );

            // Debe generarse una nueva factura para el ajuste (delta de 40 Bs)
            $this->assertEquals(1, $stats2['generadas'], 'Día 20: Debe generarse una nueva factura para el ajuste');
            $this->assertEquals(0, $stats2['actualizadas'], 'Día 20: No debe actualizar la factura pagada');

            // 4. Verificar INMUTABILIDAD de la factura del Día 1
            $facturaDia1Post = $this->obtenerFacturaPorNumero($db, $numeroBase);
            $this->assertEquals((int)$facturaDia1['id'], (int)$facturaDia1Post['id']);
            $this->assertEquals(100.00, (float)$facturaDia1Post['monto_total'], 'Factura Día 1: monto_total NO debe cambiar');
            $this->assertEquals(100.00, (float)$facturaDia1Post['monto_pagado'], 'Factura Día 1: monto_pagado NO debe cambiar');
            $this->assertEquals(0.00, (float)$facturaDia1Post['saldo'], 'Factura Día 1: saldo debe seguir en 0');
            $this->assertEquals('pagada', $facturaDia1Post['estado'], 'Factura Día 1: estado debe seguir siendo pagada');

            // 5. Verificar la NUEVA factura de ajuste del Día 20
            $numeroAjuste = $numeroBase . '-A1';
            $facturaAjuste = $this->obtenerFacturaPorNumero($db, $numeroAjuste);
            $this->assertNotNull($facturaAjuste, "Debe existir la factura de ajuste {$numeroAjuste}");
            $this->assertEquals(40.00, (float)$facturaAjuste['monto_total'], 'El monto del ajuste debe ser el delta (140 - 100 = 40)');
            $this->assertEquals(0.00, (float)$facturaAjuste['monto_pagado'], 'El ajuste inicia sin pagos');
            $this->assertEquals(40.00, (float)$facturaAjuste['saldo'], 'El saldo de la nueva factura es 40');
            $this->assertEquals('vencida', $facturaAjuste['estado'], 'La nueva factura debe quedar vencida');

            // 6. Verificar deuda total de la unidad
            $deudaTotal = $facturasModel->getTotalDeudaByUnidad($unidadId);
            $this->assertEquals(40.00, $deudaTotal, 'La deuda total activa debe ser exactamente el ajuste de 40 Bs');

        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    public function testFacturaPendienteSinPagosSeActualizaSinDuplicar(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db, 0.0);
            $facturasModel = new FacturasModel();
            $deudasModel = new DeudasManualesModel();

            // 1. Día 1: Emisión inicial de 100.00 Bs (queda pendiente y no se paga)
            $deudasModel->guardarLote(self::MES, self::ANIO, [$unidadId], 100.00, 1);
            $stats1 = $facturasModel->crearFacturasMasivas(
                [['id' => $unidadId, 'cuota_mensual' => 0.0]],
                self::MES,
                self::ANIO
            );
            $this->assertEquals(1, $stats1['generadas']);

            $numeroBase = $this->numeroFacturaBase($unidadId);
            $facturaInicial = $this->obtenerFacturaPorNumero($db, $numeroBase);
            $this->assertEquals(100.00, (float)$facturaInicial['monto_total']);
            $this->assertEquals('vencida', $facturaInicial['estado']);

            // 2. Día 20: Administrador sube a 150.00 Bs
            $deudasModel->guardarLote(self::MES, self::ANIO, [$unidadId], 150.00, 1);
            $stats2 = $facturasModel->crearFacturasMasivas(
                [['id' => $unidadId, 'cuota_mensual' => 0.0]],
                self::MES,
                self::ANIO
            );

            // Al estar pendiente y sin pagos asociados, DEBE ACTUALIZARSE sin crear una segunda factura
            $this->assertEquals(0, $stats2['generadas'], 'No debe generar una factura nueva');
            $this->assertEquals(1, $stats2['actualizadas'], 'Debe actualizar la factura pendiente existente');

            // 3. Verificar que solo existe UNA factura para esta unidad en el mes
            $stmtCount = $db->prepare("
                SELECT COUNT(*) FROM facturas 
                WHERE unidad_id = :uid AND mes = :mes AND anio = :anio AND deleted_at IS NULL
            ");
            $stmtCount->execute(['uid' => $unidadId, 'mes' => self::MES, 'anio' => self::ANIO]);
            $this->assertEquals(1, (int)$stmtCount->fetchColumn(), 'Solo debe existir una factura en el período');

            $facturaFinal = $this->obtenerFacturaPorNumero($db, $numeroBase);
            $this->assertEquals((int)$facturaInicial['id'], (int)$facturaFinal['id'], 'Debe ser la misma fila');
            $this->assertEquals(150.00, (float)$facturaFinal['monto_total'], 'El monto_total debe ser 150.00');
            $this->assertEquals(150.00, (float)$facturaFinal['saldo'], 'El saldo debe ser 150.00');
            $this->assertEquals('vencida', $facturaFinal['estado']);

        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    public function testFacturaConPagoEnRevisionNoSeSobreescribe(): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        $unidadId = 0;

        try {
            $unidadId = $this->crearUnidad($db, 0.0);
            $facturasModel = new FacturasModel();
            $deudasModel = new DeudasManualesModel();

            // Día 1: Factura de 100.00 Bs
            $deudasModel->guardarLote(self::MES, self::ANIO, [$unidadId], 100.00, 1);
            $facturasModel->crearFacturasMasivas([['id' => $unidadId, 'cuota_mensual' => 0.0]], self::MES, self::ANIO);
            $numeroBase = $this->numeroFacturaBase($unidadId);
            $factura = $this->obtenerFacturaPorNumero($db, $numeroBase);

            // Simular pago reportado en revisión para esta unidad (en tabla pagos)
            $db->prepare("
                INSERT INTO pagos 
                (residente_id, unidad_id, monto, fecha_pago, metodo_pago, banco_pagador, referencia, archivo, estado)
                VALUES (NULL, :uid, 100.00, CURDATE(), 'transferencia', 'Banco de Venezuela', 'REF123456', 'dummy.png', 'PENDIENTE')
            ")->execute(['uid' => $unidadId]);

            // Día 20: Ajuste de cuota a 130.00 Bs
            $deudasModel->guardarLote(self::MES, self::ANIO, [$unidadId], 130.00, 1);
            $stats = $facturasModel->crearFacturasMasivas([['id' => $unidadId, 'cuota_mensual' => 0.0]], self::MES, self::ANIO);

            // La factura bajo revisión no se sobreescribe, el delta de 30 Bs va a nueva factura
            $this->assertEquals(1, $stats['generadas'], 'Debe generar nueva factura para el ajuste complementario');

            $facturaOriginal = $this->obtenerFacturaPorNumero($db, $numeroBase);
            $this->assertEquals(100.00, (float)$facturaOriginal['monto_total'], 'La factura original con comprobante pendiente conserva su monto original');

        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($unidadId > 0) {
                $this->limpiarResiduos($db, $unidadId);
            }
        }
    }

    // --- Helpers auxiliares ---

    private function crearUnidad(PDO $db, float $cuota = 0.0): int {
        $stmt = $db->prepare("
            INSERT INTO unidades (numero, cuota_mensual, estado)
            VALUES (:num, :cuota, 1)
        ");
        $stmt->execute([
            'num'   => 'TEST-U-' . mt_rand(100000, 999999),
            'cuota' => $cuota,
        ]);
        return (int)$db->lastInsertId();
    }

    private function numeroFacturaBase(int $unidadId): string {
        return 'FAC-' . self::ANIO . '-' . str_pad((string)self::MES, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$unidadId, 4, '0', STR_PAD_LEFT);
    }

    private function obtenerFacturaPorNumero(PDO $db, string $numero): ?array {
        $stmt = $db->prepare("SELECT * FROM facturas WHERE numero_factura = :num LIMIT 1");
        $stmt->execute(['num' => $numero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function limpiarResiduos(PDO $db, int $unidadId): void {
        try {
            $db->prepare("DELETE FROM pagos WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM comprobantes_pago WHERE factura_id IN (SELECT id FROM facturas WHERE unidad_id = :id)")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM movimientos_cuenta WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM deudas_manuales WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM facturas WHERE unidad_id = :id")->execute(['id' => $unidadId]);
            $db->prepare("DELETE FROM unidades WHERE id = :id")->execute(['id' => $unidadId]);
        } catch (\Throwable $e) {
            // Ignorar en limpieza
        }
    }
}
