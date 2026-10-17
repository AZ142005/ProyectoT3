<?php
namespace Tests;

use App\Services\DataRetentionService;

/**
 * Tests del servicio de retención de datos acumulados.
 *
 * La serialización SQL, la whitelist y el cálculo de cortes son puros:
 * se prueban sin base de datos. Las estadísticas requieren BD y se saltan
 * (skip) con motivo claro si no está disponible.
 */
class RetencionDatosTest extends TestCase {

    // =====================================================================
    // SERIALIZACIÓN SQL (pura, sin BD)
    // =====================================================================

    public function testLiteralSqlConvierteEscalaresYNull(): void {
        $this->assertEquals('NULL', DataRetentionService::literalSql(null), 'null debe ser NULL sin comillas');
        $this->assertEquals('123', DataRetentionService::literalSql(123), 'entero sin comillas');
        $this->assertEquals('2.5', DataRetentionService::literalSql(2.5), 'flotante sin comillas');
        $this->assertEquals('1', DataRetentionService::literalSql(true), 'true debe ser 1');
        $this->assertEquals('0', DataRetentionService::literalSql(false), 'false debe ser 0');
        $this->assertEquals("'admin'", DataRetentionService::literalSql('admin'), 'texto entre comillas simples');
    }

    public function testLiteralSqlEscapaComillasYBackslash(): void {
        $this->assertEquals("'O''Brien'", DataRetentionService::literalSql("O'Brien"), 'comilla simple duplicada');
        $this->assertEquals("'a\\\\b'", DataRetentionService::literalSql('a\\b'), 'backslash duplicado (escape MySQL)');
        $this->assertEquals("'línea 1\nlínea 2'", DataRetentionService::literalSql("línea 1\nlínea 2"), 'saltos de línea se conservan dentro de comillas');
    }

    public function testSerializarFilasGeneraUnInsertPorFila(): void {
        $filas = [
            ['id' => 7, 'key' => 'login', 'ip' => '::1', 'attempts' => 2, 'window_start' => '2026-08-27 16:03:39'],
            ['id' => 8, 'key' => 'register', 'ip' => '127.0.0.1', 'attempts' => 1, 'window_start' => '2026-08-27 16:05:59'],
        ];

        $sql = DataRetentionService::serializarFilas('rate_limits', $filas);

        $esperado = "INSERT INTO `rate_limits` (`id`, `key`, `ip`, `attempts`, `window_start`) VALUES (7, 'login', '::1', 2, '2026-08-27 16:03:39');\n"
            . "INSERT INTO `rate_limits` (`id`, `key`, `ip`, `attempts`, `window_start`) VALUES (8, 'register', '127.0.0.1', 1, '2026-08-27 16:05:59');\n";
        $this->assertEquals($esperado, $sql, 'dos filas deben producir dos INSERT consecutivos');
    }

    public function testSerializarFilasVacioNoGeneraSql(): void {
        $this->assertEquals('', DataRetentionService::serializarFilas('rate_limits', []), 'sin filas no hay SQL');
    }

    public function testSerializarFilasManejaNullYTextoEspecial(): void {
        $filas = [
            ['id' => 1, 'pago_id' => null, 'motivo' => "Rechazado: no dijo 'nada'"],
        ];

        $sql = DataRetentionService::serializarFilas('log_auditoria', $filas);

        $this->assertStringContains('`pago_id`', $sql, 'la columna null debe estar presente');
        $this->assertStringContains('NULL', $sql, 'el valor null debe serializarse como NULL');
        $this->assertStringContains("'Rechazado: no dijo ''nada'''", $sql, 'comillas simples escapadas dentro del texto');
    }

    public function testSerializarFilasRechazaTablaInvalida(): void {
        $this->expectException(\InvalidArgumentException::class, function () {
            DataRetentionService::serializarFilas('tabla; DROP TABLE x', [['a' => 1]]);
        }, 'un identificador con caracteres extraños debe rechazarse');
    }

    public function testSerializarFilasRechazaColumnaInvalida(): void {
        $this->expectException(\InvalidArgumentException::class, function () {
            DataRetentionService::serializarFilas('rate_limits', [['col`rara' => 1]]);
        }, 'un nombre de columna con backtick debe rechazarse');
    }

    // =====================================================================
    // WHITELIST
    // =====================================================================

    public function testWhitelistContieneExactamenteLasTresTablas(): void {
        $this->assertEquals(
            ['log_auditoria', 'historial_estado_abono', 'rate_limits'],
            DataRetentionService::tablasPermitidas(),
            'la whitelist de retención debe ser exactamente esas 3 tablas'
        );
        $this->assertTrue(DataRetentionService::esTablaPermitida('rate_limits'), 'rate_limits es permitida');
        $this->assertFalse(DataRetentionService::esTablaPermitida('usuarios'), 'usuarios NO es permitida');
        $this->assertFalse(DataRetentionService::esTablaPermitida('purgas_log'), 'purgas_log NO es permitida');
        $this->assertFalse(DataRetentionService::esTablaPermitida(''), 'cadena vacía NO es permitida');
    }

    public function testConfiguracionExponeColumnaYDiasDeCadaTabla(): void {
        $this->assertEquals(['col' => 'created_at', 'dias' => 365], DataRetentionService::configuracion('log_auditoria'));
        $this->assertEquals(['col' => 'creado_en', 'dias' => 365], DataRetentionService::configuracion('historial_estado_abono'));
        $this->assertEquals(['col' => 'window_start', 'dias' => 30], DataRetentionService::configuracion('rate_limits'));
    }

    public function testConfiguracionRechazaTablaFueraDeLaWhitelist(): void {
        $this->expectException(\InvalidArgumentException::class, function () {
            DataRetentionService::configuracion('pagos');
        }, 'pagos no está en la whitelist y debe lanzar excepción');
    }

    // =====================================================================
    // CÁLCULO DE CORTES (puro)
    // =====================================================================

    public function testCalcularCorteRestaDiasCalendario(): void {
        $this->assertEquals('2026-09-17 13:00:00', DataRetentionService::calcularCorte('2026-10-17 13:00:00', 30), '30 días atrás');
        $this->assertEquals('2025-10-17 13:00:00', DataRetentionService::calcularCorte('2026-10-17 13:00:00', 365), '365 días atrás');
    }

    public function testCalcularCorteCruzaFebreroBisiesto(): void {
        $this->assertEquals('2024-02-29 00:00:00', DataRetentionService::calcularCorte('2024-03-01 00:00:00', 1), '1 día atrás en año bisiesto');
    }

    public function testCorteDeCadaTablaUsaSuRetencion(): void {
        $this->assertEquals('2026-10-02 12:00:00', DataRetentionService::calcularCorte('2026-11-01 12:00:00', DataRetentionService::configuracion('rate_limits')['dias']), 'rate_limits usa 30 días');
        $this->assertEquals('2025-11-01 12:00:00', DataRetentionService::calcularCorte('2026-11-01 12:00:00', DataRetentionService::configuracion('log_auditoria')['dias']), 'log_auditoria usa 365 días');
    }

    // =====================================================================
    // ESTADÍSTICAS CONTRA BD (skip si no hay conexión)
    // =====================================================================

    public function testEstadisticasDeLasTresTablas(): void {
        try {
            $servicio = new DataRetentionService();
            $stats = $servicio->estadisticas();
        } catch (\Throwable $e) {
            $this->skip('BD no disponible: ' . $e->getMessage());
            return;
        }

        $this->assertEquals(
            ['log_auditoria', 'historial_estado_abono', 'rate_limits'],
            array_keys($stats),
            'estadisticas() sin argumento debe cubrir las 3 tablas'
        );

        foreach ($stats as $tabla => $s) {
            $this->assertTrue($s['existe'], "La tabla {$tabla} debe existir");
            $this->assertTrue(is_int($s['filas']) && $s['filas'] >= 0, "{$tabla} debe exponer conteo de filas");
            $this->assertTrue(is_int($s['elegibles']) && $s['elegibles'] >= 0, "{$tabla} debe exponer elegibles");
            $this->assertNotNull($s['fecha_corte'], "{$tabla} debe exponer fecha de corte");
            $this->assertTrue(
                in_array($s['dias'], [30, 365], true),
                "{$tabla} debe usar una retención de la política"
            );
        }
    }

    public function testEstadisticasDeUnaSolaTabla(): void {
        try {
            $servicio = new DataRetentionService();
            $stats = $servicio->estadisticas('rate_limits');
        } catch (\Throwable $e) {
            $this->skip('BD no disponible: ' . $e->getMessage());
            return;
        }

        $this->assertEquals(['rate_limits'], array_keys($stats), 'estadisticas($tabla) debe limitarse a esa tabla');
    }

    public function testEstadisticasRechazaTablaNoPermitida(): void {
        try {
            $servicio = new DataRetentionService();
        } catch (\Throwable $e) {
            $this->skip('BD no disponible: ' . $e->getMessage());
            return;
        }

        $this->expectException(\InvalidArgumentException::class, function () use ($servicio) {
            $servicio->estadisticas('pagos');
        }, 'estadisticas() debe validar la whitelist');
    }
}
