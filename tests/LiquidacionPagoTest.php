<?php
namespace Tests;

use App\Services\LiquidacionPagoService;
use App\Models\MovimientosModel;
use PDO;
use PDOStatement;

/**
 * Doble de prueba para PDOStatement sin dependencia de motor de base de datos externo.
 */
class FakeLiquidacionStatement extends PDOStatement {
    private string $sql;
    private FakeLiquidacionPDO $pdo;
    private array $resultSet = [];

    public function __construct(string $sql, FakeLiquidacionPDO $pdo) {
        $this->sql = $sql;
        $this->pdo = $pdo;
    }

    public function execute(?array $params = null): bool {
        $params = $params ?? [];
        $this->pdo->executedQueries[] = [
            'sql'    => $this->sql,
            'params' => $params
        ];

        if (str_contains($this->sql, 'SELECT id, numero_factura, saldo')) {
            $this->resultSet = $this->pdo->facturasPendientes;
        } elseif (str_contains($this->sql, 'UPDATE facturas')) {
            $this->pdo->updatedFacturas[] = $params;
        } elseif (str_contains($this->sql, 'INSERT INTO facturas')) {
            $this->pdo->insertedAbonos[] = $params;
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->resultSet;
    }
}

/**
 * Doble de prueba para PDO que captura operaciones SQL de LiquidacionPagoService.
 */
class FakeLiquidacionPDO extends PDO {
    public array $facturasPendientes = [];
    public array $updatedFacturas = [];
    public array $insertedAbonos = [];
    public array $executedQueries = [];

    public function __construct() {
        // Sin conexión externa
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new FakeLiquidacionStatement($query, $this);
    }
}

/**
 * Espía de MovimientosModel para verificar asientos en el libro mayor sin tocar la BD.
 */
class SpyMovimientosModel extends MovimientosModel {
    public array $movimientosRegistrados = [];

    public function __construct() {
        parent::__construct(null);
    }

    public function registrarMovimiento(
        int $unidadId,
        string $tipo,
        float $monto,
        string $descripcion,
        ?int $referenciaId = null,
        ?string $referenciaTipo = null
    ): int {
        $this->movimientosRegistrados[] = [
            'unidad_id'       => $unidadId,
            'tipo'            => $tipo,
            'monto'           => $monto,
            'descripcion'     => $descripcion,
            'referencia_id'   => $referenciaId,
            'referencia_tipo' => $referenciaTipo
        ];
        return count($this->movimientosRegistrados);
    }
}

class LiquidacionPagoTest extends TestCase {

    /**
     * TC-WB-01: Salida temprana cuando monto <= 0 o unidadId <= 0.
     */
    public function testMontoInvalidoOUnidadInvalidaRetornaCero() {
        $pdo = new FakeLiquidacionPDO();
        $spyMov = new SpyMovimientosModel();
        $service = new LiquidacionPagoService($spyMov);

        // Monto 0.00 (BV-01)
        $resCero = $service->aplicarPagoAUnidad($pdo, 10, 0.00, 'Ref Cero');
        $this->assertEquals(0.00, $resCero['total_aplicado_deuda']);
        $this->assertEquals(0.00, $resCero['saldo_a_favor_generado']);
        $this->assertEquals([], $resCero['facturas_afectadas']);

        // Monto negativo
        $resNeg = $service->aplicarPagoAUnidad($pdo, 10, -25.50, 'Ref Negativo');
        $this->assertEquals(0.00, $resNeg['total_aplicado_deuda']);

        // Unidad inválida (0)
        $resUnidadInvalida = $service->aplicarPagoAUnidad($pdo, 0, 100.00, 'Ref Unidad Cero');
        $this->assertEquals(0.00, $resUnidadInvalida['total_aplicado_deuda']);
        $this->assertEquals(0, count($spyMov->movimientosRegistrados), "No debe registrar movimientos cuando la entrada es inválida");
    }

    /**
     * TC-WB-02: Liquidación en cascada de múltiples facturas pendientes con generación de saldo a favor.
     */
    public function testLiquidacionCascadaMultiplesFacturasConExcedente() {
        $pdo = new FakeLiquidacionPDO();
        $pdo->facturasPendientes = [
            [
                'id'             => 101,
                'numero_factura' => 'FAC-2026-01',
                'saldo'          => 100.00,
                'monto_total'    => 100.00,
                'monto_pagado'   => 0.00
            ],
            [
                'id'             => 102,
                'numero_factura' => 'FAC-2026-02',
                'saldo'          => 150.00,
                'monto_total'    => 150.00,
                'monto_pagado'   => 0.00
            ]
        ];

        $spyMov = new SpyMovimientosModel();
        $service = new LiquidacionPagoService($spyMov);

        $res = $service->aplicarPagoAUnidad($pdo, 5, 300.00, 'Ref. 998877', 42, 'pago');

        $this->assertEquals(250.00, $res['total_aplicado_deuda'], "Debe aplicar 250.00 a las dos facturas pendientes");
        $this->assertEquals(50.00, $res['saldo_a_favor_generado'], "Debe generar 50.00 de saldo a favor");
        $this->assertEquals(2, count($res['facturas_afectadas']), "Ambas facturas deben figurar como afectadas");
        $this->assertEquals('pagada', $res['facturas_afectadas'][0]['estado']);
        $this->assertEquals('pagada', $res['facturas_afectadas'][1]['estado']);

        // Verificar que se insertó la factura de crédito ABONO-* con saldo negativo
        $this->assertEquals(1, count($pdo->insertedAbonos), "Debe insertar un documento ABONO-* por el excedente");
        $this->assertEquals(-50.00, $pdo->insertedAbonos[0]['saldo'], "El saldo de la factura ABONO debe ser negativo (-50.00)");
        $this->assertStringContains('ABONO-', $pdo->insertedAbonos[0]['num']);

        // Verificar registro único en el libro mayor
        $this->assertEquals(1, count($spyMov->movimientosRegistrados), "Debe registrar exactamente 1 movimiento en el libro mayor");
        $this->assertEquals(300.00, $spyMov->movimientosRegistrados[0]['monto']);
        $this->assertEquals('abono_pago', $spyMov->movimientosRegistrados[0]['tipo']);
        $this->assertEquals(42, $spyMov->movimientosRegistrados[0]['referencia_id']);
    }

    /**
     * TC-WB-03: Pago parcial sobre una factura sin excedente.
     */
    public function testPagoParcialSinExcedente() {
        $pdo = new FakeLiquidacionPDO();
        $pdo->facturasPendientes = [
            [
                'id'             => 201,
                'numero_factura' => 'FAC-2026-03',
                'saldo'          => 100.00,
                'monto_total'    => 100.00,
                'monto_pagado'   => 0.00
            ]
        ];

        $spyMov = new SpyMovimientosModel();
        $service = new LiquidacionPagoService($spyMov);

        $res = $service->aplicarPagoAUnidad($pdo, 8, 40.00, 'Abono parcial', 77, 'comprobante');

        $this->assertEquals(40.00, $res['total_aplicado_deuda']);
        $this->assertEquals(0.00, $res['saldo_a_favor_generado']);
        $this->assertEquals(1, count($res['facturas_afectadas']));
        $this->assertEquals(60.00, $res['facturas_afectadas'][0]['saldo_restante']);
        $this->assertEquals('pendiente', $res['facturas_afectadas'][0]['estado']);
        $this->assertEquals(0, count($pdo->insertedAbonos), "No debe insertar factura ABONO-* en pago parcial");
    }

    /**
     * Análisis de Valores Límite (BVA): mínimo válido 0.01, máximo 999999.99 y frontera de excedente.
     */
    public function testValoresLimiteContablesYUmbralExcedente() {
        // BV-02: Mínimo monto válido (0.01)
        $pdoMin = new FakeLiquidacionPDO();
        $pdoMin->facturasPendientes = [
            [
                'id'             => 301,
                'numero_factura' => 'FAC-MIN',
                'saldo'          => 10.00,
                'monto_total'    => 10.00,
                'monto_pagado'   => 0.00
            ]
        ];
        $spyMin = new SpyMovimientosModel();
        $serviceMin = new LiquidacionPagoService($spyMin);
        $resMin = $serviceMin->aplicarPagoAUnidad($pdoMin, 3, 0.01, 'Minimo valido');
        $this->assertEquals(0.01, $resMin['total_aplicado_deuda']);
        $this->assertEquals(9.99, $resMin['facturas_afectadas'][0]['saldo_restante']);

        // BV-03: Máximo monto válido (999999.99) como pago anticipado sin deuda previa
        $pdoMax = new FakeLiquidacionPDO();
        $spyMax = new SpyMovimientosModel();
        $serviceMax = new LiquidacionPagoService($spyMax);
        $resMax = $serviceMax->aplicarPagoAUnidad($pdoMax, 3, 999999.99, 'Maximo valido');
        $this->assertEquals(0.00, $resMax['total_aplicado_deuda']);
        $this->assertEquals(999999.99, $resMax['saldo_a_favor_generado']);
        $this->assertEquals(1, count($pdoMax->insertedAbonos));

        // BV-05 / BV-06: Pago exacto vs excedente mínimo de 0.01
        $pdoExacto = new FakeLiquidacionPDO();
        $pdoExacto->facturasPendientes = [
            [
                'id'             => 302,
                'numero_factura' => 'FAC-EXACTA',
                'saldo'          => 50.00,
                'monto_total'    => 50.00,
                'monto_pagado'   => 0.00
            ]
        ];
        $spyExacto = new SpyMovimientosModel();
        $serviceExacto = new LiquidacionPagoService($spyExacto);
        $resExcedenteMin = $serviceExacto->aplicarPagoAUnidad($pdoExacto, 3, 50.01, 'Excedente de 1 centavo');
        $this->assertEquals(50.00, $resExcedenteMin['total_aplicado_deuda']);
        $this->assertEquals(0.01, $resExcedenteMin['saldo_a_favor_generado']);
        $this->assertEquals(1, count($pdoExacto->insertedAbonos));
    }
}
