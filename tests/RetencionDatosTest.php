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

    // =====================================================================
    // RUTAS, CONTROLADOR Y UI (estático, sin ejecución HTTP)
    // =====================================================================

    /** Extrae la línea exacta donde se declara una ruta en public/index.php. */
    private function lineaRuta(string $uri): string {
        foreach (explode("\n", (string)file_get_contents(dirname(__DIR__) . '/public/index.php')) as $linea) {
            if (str_contains($linea, "'{$uri}'") || str_contains($linea, "\"{$uri}\"")) {
                return $linea;
            }
        }
        return '';
    }

    public function testRutasDeRetencionConRolesCorrectos(): void {
        $exportar = $this->lineaRuta('/admin/respaldos/acumulados/exportar');
        $this->assertStringContains('UserRole::ADMIN', $exportar, 'la ruta de exportación debe exigir ADMIN');
        $this->assertFalse(str_contains($exportar, 'UserRole::AUDITOR'), 'la ruta de exportación no debe habilitar AUDITOR');

        $purgar = $this->lineaRuta('/admin/respaldos/acumulados/purgar');
        $this->assertStringContains('UserRole::ADMIN', $purgar, 'la ruta de purga debe exigir ADMIN');
        $this->assertFalse(str_contains($purgar, 'UserRole::AUDITOR'), 'la ruta de purga no debe habilitar AUDITOR');

        $descarga = $this->lineaRuta('/admin/respaldos/purgas/descargar/{id}');
        $this->assertStringContains('UserRole::ADMIN', $descarga, 'la descarga de exportes debe permitir ADMIN');
        $this->assertStringContains('UserRole::AUDITOR', $descarga, 'la descarga de exportes debe permitir AUDITOR (contrato del rol)');
    }

    public function testSidebarsIncluyenItemRespaldos(): void {
        $admin = (string)file_get_contents(dirname(__DIR__) . '/app/views/layouts/admin_sidebar.php');
        $auditor = (string)file_get_contents(dirname(__DIR__) . '/app/views/layouts/auditor_sidebar.php');

        $this->assertEquals(2, substr_count($admin, "'route' => 'respaldos'"), 'el sidebar admin declara el ítem en sus DOS listas (base y variante pagos)');
        $this->assertEquals(2, substr_count($admin, "'url' => '/admin/respaldos'"), 'el sidebar admin enlaza /admin/respaldos en ambas listas');
        $this->assertEquals(1, substr_count($auditor, "'route' => 'respaldos'"), 'el sidebar auditor declara el ítem');
        $this->assertStringContains("'icon' => 'backup'", $admin, 'el ítem usa el icono backup');
        $this->assertStringContains("'label' => 'Respaldos'", $auditor, 'el ítem se rotula Respaldos');
    }

    public function testVistaRespaldosIncluyePanelDeRetencion(): void {
        $vista = (string)file_get_contents(dirname(__DIR__) . '/app/views/admin/respaldos/index.php');

        $this->assertStringContains('Datos acumulados', $vista, 'la card de retención debe estar presente');
        $this->assertStringContains('Purgas y exportaciones', $vista, 'la card de bitácora debe estar presente');
        $this->assertStringContains('/admin/respaldos/acumulados/exportar', $vista, 'form de exportación en la vista');
        $this->assertStringContains('/admin/respaldos/acumulados/purgar', $vista, 'form de purga en la vista');
        $this->assertStringContains('/admin/respaldos/purgas/descargar/', $vista, 'enlace de descarga de exportes');
        $this->assertStringContains('csrf_field()', $vista, 'todo POST debe llevar CSRF');
        $this->assertStringContains('return confirm(', $vista, 'la purga debe confirmarse en JS (operación destructiva)');
        $this->assertStringContains("Auth::role() !== 'auditor'", $vista, 'los botones de acción se ocultan al auditor');
    }

    public function testControladorRespaldoExponeAccionesDeRetencion(): void {
        $ctrl = (string)file_get_contents(dirname(__DIR__) . '/app/Controllers/RespaldoController.php');
        foreach (['exportarAcumulados', 'purgarAcumulados', 'descargarPurga'] as $metodo) {
            $pos = strpos($ctrl, "function {$metodo}");
            $this->assertTrue($pos !== false, "RespaldoController::{$metodo} debe existir");
            if ($pos !== false) {
                $this->assertStringContains('Auth::requireRole', substr($ctrl, $pos, 400), "{$metodo} debe exigir rol en sus primeras líneas");
            }
        }
    }
}
