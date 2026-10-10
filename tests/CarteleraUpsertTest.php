<?php
namespace Tests;

use App\Core\Database;
use App\Models\CarteleraModel;
use App\Services\CarteleraParserService;

/**
 * Test live-DB del upsert de importación del Reporte de Cartelera.
 *
 * Reimportar un reporte no acumula: las deudas existentes (mismo numero_factura
 * CART-*) se actualizan con el saldo del reporte. El test modifica el mes 10 de
 * la unidad 1-01 (25.152,28 -> 25.252,28), importa, verifica el upsert y restaura
 * las filas originales incluso si un assert falla (try/finally).
 *
 * Usa el fixture real `tests/fixtures/cartelera_dotwood.pdf` (dato no versionado).
 * Si el fixture no está presente, el test se marca como skipped.
 */
class CarteleraUpsertTest extends TestCase {

    private const FIXTURE = '/tests/fixtures/cartelera_dotwood.pdf';
    private const NUMERO = 'CART-1-01-202610';
    private const SALDO_ORIGINAL = 25152.28;
    private const SALDO_MODIFICADO = 25252.28;
    private const SUMA_ORIGINAL = 8891132.51;
    private const SUMA_MODIFICADA = 8891232.51;
    private const ARCHIVO_TEST = 'test-upsert.pdf';

    public function testReimportarActualizaDeudasSinAcumular() {
        $ruta = BASE_PATH . self::FIXTURE;
        if (!file_exists($ruta)) {
            $this->skip('Fixture tests/fixtures/cartelera_dotwood.pdf no disponible (dato real no versionado)');
            return;
        }

        $parseo = (new CarteleraParserService())->parsear($ruta);
        $this->assertTrue($parseo['ok'], 'El parseo del fixture debe completar sin errores');
        if (!$parseo['ok']) {
            return;
        }

        $filasOriginales = $parseo['filas'];
        $fecha = (string)$parseo['fecha_emision'];
        $this->assertNotNull($this->filaPorUnidad($filasOriginales, '1-01'), 'El fixture debe contener la unidad 1-01');

        $modelo = new CarteleraModel();
        $db = Database::getConnection();
        $restaurado = false;

        try {
            // Snapshot inicial (estado esperado del reporte real ya importado)
            $this->assertEquals(self::SALDO_ORIGINAL, $this->saldoDe($db, self::NUMERO),
                'Saldo inicial de ' . self::NUMERO . ' debe ser el del reporte');
            $this->assertEquals(self::SUMA_ORIGINAL, $this->sumaCartelera($db),
                'Suma inicial de saldos CART debe ser la del reporte');

            // 1. Modificar el mes 10 de 1-01 y reimportar: upsert sin acumulación
            $filasModificadas = $this->conMes10Modificado($filasOriginales, self::SALDO_MODIFICADO);
            $resultado = $modelo->importarLote($filasModificadas, $fecha, 1, self::ARCHIVO_TEST, null);

            $this->assertTrue(($resultado['facturas_actualizadas'] ?? 0) >= 1,
                'La reimportación debe contar deudas actualizadas');
            $this->assertEquals(0, $resultado['facturas_creadas'],
                'La reimportación no debe crear deudas nuevas');
            $this->assertEquals(self::SALDO_MODIFICADO, $this->saldoDe($db, self::NUMERO),
                'El saldo en BD debe reflejar el valor del reporte modificado');
            $this->assertEquals(self::SUMA_MODIFICADA, $this->sumaCartelera($db),
                'La suma CART debe crecer solo por el delta del reporte (sin acumular)');

            // 2. Reimportar las filas originales: restauración de valores
            $resultadoRestore = $modelo->importarLote($filasOriginales, $fecha, 1, self::ARCHIVO_TEST, null);
            $restaurado = true;
            $this->assertTrue(($resultadoRestore['facturas_actualizadas'] ?? 0) >= 1,
                'La reimportación original también debe actualizar las deudas existentes');
            $this->assertEquals(self::SALDO_ORIGINAL, $this->saldoDe($db, self::NUMERO),
                'El saldo debe volver al valor original del reporte');
            $this->assertEquals(self::SUMA_ORIGINAL, $this->sumaCartelera($db),
                'La suma CART debe quedar restaurada al valor original');
        } finally {
            // Restauración defensiva: si algo falló, reimportar las filas originales
            if (!$restaurado) {
                try {
                    $modelo->importarLote($filasOriginales, $fecha, 1, self::ARCHIVO_TEST, null);
                } catch (\Throwable $e) {
                    error_log('[CarteleraUpsertTest] No se pudo restaurar el lote original: ' . $e->getMessage());
                }
            }
            // Limpieza de la auditoría generada por el test
            try {
                $db->exec("DELETE FROM log_auditoria WHERE detalles LIKE '%test-upsert%'");
            } catch (\Throwable $e) {
                error_log('[CarteleraUpsertTest] No se pudo limpiar la auditoría del test: ' . $e->getMessage());
            }
        }
    }

    /**
     * Devuelve las filas con el monto del mes 10 de la unidad 1-01 reemplazado.
     */
    private function conMes10Modificado(array $filas, float $nuevoMonto): array {
        foreach ($filas as $i => $fila) {
            if (($fila['unidad'] ?? null) === '1-01') {
                $filas[$i]['meses'][10] = $nuevoMonto;
                return $filas;
            }
        }
        return $filas;
    }

    private function filaPorUnidad(array $filas, string $unidad): ?array {
        foreach ($filas as $fila) {
            if (($fila['unidad'] ?? null) === $unidad) {
                return $fila;
            }
        }
        return null;
    }

    private function saldoDe(\PDO $db, string $numero): float {
        $stmt = $db->prepare("SELECT saldo FROM facturas WHERE numero_factura = :numero LIMIT 1");
        $stmt->execute(['numero' => $numero]);
        return (float)$stmt->fetchColumn();
    }

    private function sumaCartelera(\PDO $db): float {
        return (float)$db->query("SELECT SUM(saldo) FROM facturas WHERE numero_factura LIKE 'CART-%'")->fetchColumn();
    }
}
