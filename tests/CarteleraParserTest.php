<?php
namespace Tests;

use App\Services\CarteleraParserService;

/**
 * Tests del parser del Reporte de Cartelera (Dotwood PC Condominios).
 *
 * Usa el fixture real `tests/fixtures/cartelera_dotwood.pdf` (dato no versionado).
 * Si el fixture no está presente, el test principal se marca como skipped.
 */
class CarteleraParserTest extends TestCase {

    private const FIXTURE = '/tests/fixtures/cartelera_dotwood.pdf';

    public function testClasesYMetodosExisten() {
        $this->assertTrue(class_exists(CarteleraParserService::class), "CarteleraParserService debe existir");
        $this->assertTrue(
            method_exists(CarteleraParserService::class, 'parsear'),
            "CarteleraParserService debe tener el método parsear"
        );
    }

    public function testParseoReporteCarteleraDotwood() {
        $ruta = BASE_PATH . self::FIXTURE;
        if (!file_exists($ruta)) {
            $this->skip('Fixture tests/fixtures/cartelera_dotwood.pdf no disponible (dato real no versionado)');
            return;
        }

        $resultado = (new CarteleraParserService())->parsear($ruta);

        $this->assertTrue(
            $resultado['ok'],
            'El parseo debe completar sin errores de validación. Errores: ' . implode(' | ', $resultado['errores'])
        );

        // Estructura general del reporte
        $this->assertEquals(216, count($resultado['filas']), "Debe haber 216 unidades con deuda");
        $this->assertEquals(5, $resultado['paginas'], "El PDF real tiene 5 páginas");
        $this->assertEquals('2026-10-09', $resultado['fecha_emision'], "Fecha de emisión del reporte");
        $this->assertEquals(2026, $resultado['anio'], "Año del reporte");
        $this->assertEquals([8, 9, 10, 11], $resultado['meses'], "Meses detectados por etiquetas de cabecera");

        // Totales impresos (verificación al centavo)
        $totales = $resultado['totales'];
        $this->assertEquals('488257.99', $this->formato($totales['saldo_anterior']), "Total S. Anterior");
        $this->assertEquals('889225.85', $this->formato($totales['meses'][8]), "Total Ago-2026");
        $this->assertEquals('2057568.00', $this->formato($totales['meses'][9]), "Total Sep-2026");
        $this->assertEquals('5456080.67', $this->formato($totales['meses'][10]), "Total Oct-2026");
        $this->assertEquals('0.00', $this->formato($totales['meses'][11]), "Total Nov-2026");
        $this->assertEquals('8891132.51', $this->formato($totales['total']), "Deuda total del reporte");

        // Conteo por edificio
        $conteo = $resultado['conteo_por_edificio'];
        $this->assertEquals(30, $conteo[1] ?? null, "Edificio 1: 30 unidades");
        $this->assertEquals(39, $conteo[2] ?? null, "Edificio 2: 39 unidades");
        $this->assertEquals(33, $conteo[3] ?? null, "Edificio 3: 33 unidades");
        $this->assertEquals(38, $conteo[4] ?? null, "Edificio 4: 38 unidades");
        $this->assertEquals(38, $conteo[5] ?? null, "Edificio 5: 38 unidades");
        $this->assertEquals(38, $conteo[6] ?? null, "Edificio 6: 38 unidades");

        // Filas de muestra verificadas contra el PDF
        $muestras = [
            '1-04' => ['844.27', '24598.65', '26650.49', '25767.48', '77860.89'],
            '5-63' => ['19511.94', '24793.27', '26782.07', '25943.86', '97031.14'],
            '6-66' => ['27645.94', '24931.25', '27265.59', '26525.51', '106368.29'],
        ];
        foreach ($muestras as $unidad => $esperado) {
            $fila = $this->filaPorUnidad($resultado['filas'], $unidad);
            $this->assertNotNull($fila, "Debe existir la fila {$unidad}");
            if ($fila === null) {
                continue;
            }
            $this->assertEquals($esperado[0], $this->formato($fila['saldo_anterior']), "{$unidad} S. Anterior");
            $this->assertEquals($esperado[1], $this->formato($fila['meses'][8] ?? 0), "{$unidad} Ago");
            $this->assertEquals($esperado[2], $this->formato($fila['meses'][9] ?? 0), "{$unidad} Sep");
            $this->assertEquals($esperado[3], $this->formato($fila['meses'][10] ?? 0), "{$unidad} Oct");
            $this->assertEquals('0.00', $this->formato($fila['meses'][11] ?? 0), "{$unidad} Nov");
            $this->assertEquals($esperado[4], $this->formato($fila['total']), "{$unidad} Total");
        }

        // La suma de las filas debe cuadrar con la deuda total del reporte
        $suma = 0.0;
        foreach ($resultado['filas'] as $fila) {
            $suma += $fila['total'];
        }
        $this->assertEquals('8891132.51', $this->formato($suma), "Suma de deudas por unidad");
    }

    public function testParseoArchivoInvalido() {
        $tmp = tempnam(sys_get_temp_dir(), 'cartelera_test_');
        file_put_contents($tmp, 'esto no es un PDF');

        try {
            $resultado = (new CarteleraParserService())->parsear($tmp);
            $this->assertFalse($resultado['ok'], "Un archivo no-PDF debe rechazarse");
            $this->assertTrue(count($resultado['errores']) > 0, "Debe reportar el detalle del error");
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Formatea un monto con 2 decimales y punto decimal (independiente del locale).
     */
    private function formato(float $valor): string {
        return number_format($valor, 2, '.', '');
    }

    /**
     * Busca una fila por etiqueta de unidad.
     */
    private function filaPorUnidad(array $filas, string $unidad): ?array {
        foreach ($filas as $fila) {
            if ($fila['unidad'] === $unidad) {
                return $fila;
            }
        }
        return null;
    }
}
