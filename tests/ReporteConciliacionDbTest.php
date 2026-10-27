<?php
namespace Tests;

use App\Core\Database;
use App\Models\ConciliacionModel;

/**
 * Pruebas T2 contra MySQL real para las consultas de reportes de conciliación.
 *
 * No crean fixtures: operan sobre el lote activo existente. Si MySQL o el lote
 * activo no están disponibles, la prueba falla con RuntimeException deliberada
 * (no se omite), para que el entorno roto no pase como éxito.
 */
class ReporteConciliacionDbTest extends TestCase {

    /**
     * @return array{0: ConciliacionModel, 1: string}
     */
    private function modeloConLoteActivo(): array {
        try {
            Database::getConnection();
        } catch (\Throwable $e) {
            throw new \RuntimeException('MySQL no disponible: ' . $e->getMessage());
        }

        $model = new ConciliacionModel();
        $lote = $model->obtenerLoteActivo();
        if ($lote === null) {
            throw new \RuntimeException('No hay un lote activo para verificar los reportes de conciliación.');
        }

        return [$model, $lote];
    }

    public function testDatosDelLoteActivoDevuelveBancoYConteo(): void {
        [$model, $lote] = $this->modeloConLoteActivo();

        $datos = $model->obtenerDatosLote($lote);

        $this->assertNotNull($datos, 'obtenerDatosLote debe devolver el lote activo');
        if ($datos === null) {
            return;
        }

        $this->assertEquals($lote, $datos['lote_importacion'], 'El lote devuelto debe ser el lote activo consultado');
        $this->assertTrue(
            is_string($datos['banco'] ?? null) && $datos['banco'] !== '',
            'El lote activo debe tener al menos un banco concatenado'
        );
        $this->assertTrue(
            (int)($datos['total_movimientos'] ?? 0) > 0,
            'El lote activo debe reportar un total de movimientos positivo'
        );
    }

    public function testDatosDeLoteInexistenteDevuelveNull(): void {
        [$model] = $this->modeloConLoteActivo();

        $this->assertNull(
            $model->obtenerDatosLote('LOTE-INEXISTENTE-REPORTE'),
            'Un lote inexistente debe devolver null'
        );
    }

    public function testMovimientosConciliadosNoDuplicanExtractos(): void {
        [$model] = $this->modeloConLoteActivo();

        $filas = $model->obtenerMovimientosConciliados();

        $ids = [];
        foreach ($filas as $fila) {
            $ids[] = (int)($fila['id'] ?? 0);
        }

        $this->assertEquals(
            count($ids),
            count(array_unique($ids)),
            'La consulta de conciliados no debe repetir el id de un extracto'
        );
    }

    public function testMovimientosConciliadosRespetaFechaDeCorteYColumnas(): void {
        [$model] = $this->modeloConLoteActivo();

        $antiguos = $model->obtenerMovimientosConciliados('2000-01-01 00:00:00');
        $this->assertEquals(0, count($antiguos), 'Un corte anterior a todos los datos debe devolver cero filas');

        $filas = $model->obtenerMovimientosConciliados();
        $this->assertTrue(count($filas) > 0, 'El historial local debe tener al menos un movimiento conciliado');
        if (empty($filas)) {
            return;
        }

        foreach (['id', 'monto', 'unidad_numero', 'residente_nombre', 'referencia_bancaria'] as $clave) {
            $this->assertTrue(
                array_key_exists($clave, $filas[0]),
                "La fila de conciliados debe incluir la columna {$clave}"
            );
        }
    }

    public function testPagosPendientesNoDuplicanOrigenId(): void {
        [$model] = $this->modeloConLoteActivo();

        $claves = [];
        foreach ($model->obtenerTodosPagosPendientes() as $fila) {
            $claves[] = (string)($fila['origen_tabla'] ?? '') . ':' . (string)($fila['id'] ?? '');
        }

        $this->assertEquals(
            count($claves),
            count(array_unique($claves)),
            'La consulta de pendientes no debe repetir claves origen_tabla:id'
        );
    }
}
