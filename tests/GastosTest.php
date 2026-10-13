<?php
namespace Tests;

use App\Models\CategoriasGastosModel;
use App\Models\GastosModel;
use App\Models\ConciliacionModel;
use App\Models\MovimientosModel;
use Exception;

class GastosTest extends TestCase {

    public function testModelClassesExist() {
        $this->assertTrue(class_exists(CategoriasGastosModel::class));
        $this->assertTrue(class_exists(GastosModel::class));
        $this->assertTrue(class_exists(ConciliacionModel::class));
        $this->assertTrue(class_exists(MovimientosModel::class));
    }

    public function testCrearGastoValidaPeriodoObligatorioYLanzamientos() {
        $model = new GastosModel();

        // Mes inválido (0)
        $this->expectExceptionMessage("El período (mes/año) especificado es inválido.", function () use ($model) {
            $model->crearGasto([
                'mes'         => 0,
                'anio'        => 2026,
                'proveedor'   => 'Hidrocapital',
                'descripcion' => 'Servicio de agua',
                'monto_total' => 150.00
            ]);
        });

        // Mes inválido (13)
        $this->expectExceptionMessage("El período (mes/año) especificado es inválido.", function () use ($model) {
            $model->crearGasto([
                'mes'         => 13,
                'anio'        => 2026,
                'proveedor'   => 'Hidrocapital',
                'descripcion' => 'Servicio de agua',
                'monto_total' => 150.00
            ]);
        });

        // Año inválido (< 2000)
        $this->expectExceptionMessage("El período (mes/año) especificado es inválido.", function () use ($model) {
            $model->crearGasto([
                'mes'         => 6,
                'anio'        => 1999,
                'proveedor'   => 'Hidrocapital',
                'descripcion' => 'Servicio de agua',
                'monto_total' => 150.00
            ]);
        });
    }

    public function testCrearGastoValidaProveedorDescripcionYMonto() {
        $model = new GastosModel();

        // Proveedor vacío
        $this->expectExceptionMessage("El proveedor y la descripción del gasto son obligatorios.", function () use ($model) {
            $model->crearGasto([
                'mes'         => 8,
                'anio'        => 2026,
                'proveedor'   => '   ',
                'descripcion' => 'Reparación de bomba',
                'monto_total' => 200.00
            ]);
        });

        // Monto <= 0
        $this->expectExceptionMessage("El monto total del gasto debe ser superior a 0.", function () use ($model) {
            $model->crearGasto([
                'mes'         => 8,
                'anio'        => 2026,
                'proveedor'   => 'Cuerpoelec',
                'descripcion' => 'Electricidad áreas comunes',
                'monto_total' => 0
            ]);
        });

        // Gasto individual sin edificio_id válido
        $this->expectExceptionMessage("Debe seleccionar un edificio válido para un gasto individual.", function () use ($model) {
            $model->crearGasto([
                'mes'         => 8,
                'anio'        => 2026,
                'proveedor'   => 'Ascensores C.A.',
                'descripcion' => 'Mantenimiento preventivo',
                'monto_total' => 350.00,
                'tipo_gasto'  => 'individual',
                'edificio_id' => 0
            ]);
        });
    }
}
