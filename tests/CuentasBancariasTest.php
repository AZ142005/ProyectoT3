<?php
namespace Tests;

use App\Models\CuentasBancariasModel;
use App\Controllers\CuentaBancariaController;

class CuentasBancariasTest extends TestCase {

    public function testClassesAndMethodsExist() {
        $this->assertTrue(class_exists(CuentasBancariasModel::class), "CuentasBancariasModel debe existir");
        $this->assertTrue(class_exists(CuentaBancariaController::class), "CuentaBancariaController debe existir");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'getAll'), "Debe tener getAll");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'getActivas'), "Debe tener getActivas");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'getById'), "Debe tener getById");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'getActivaById'), "Debe tener getActivaById");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'crear'), "Debe tener crear");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'actualizar'), "Debe tener actualizar");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'toggleActiva'), "Debe tener toggleActiva");
        $this->assertTrue(method_exists(CuentasBancariasModel::class, 'eliminarODesactivar'), "Debe tener eliminarODesactivar");
    }

    public function testGetActivasDevuelveSoloCuentasActivas() {
        $model = new CuentasBancariasModel();
        $activas = $model->getActivas();

        $this->assertTrue(is_array($activas), "getActivas debe retornar un array");
        foreach ($activas as $c) {
            $this->assertEquals(1, (int)$c['activa'], "Cada cuenta devuelta por getActivas debe tener activa=1");
        }
    }

    public function testOperacionesCrudCuentasBancarias() {
        $model = new CuentasBancariasModel();

        // 1. Crear cuenta de prueba
        $numeroTest = '0134' . str_pad((string)mt_rand(1000000000000000, 9999999999999999), 16, '0', STR_PAD_LEFT);
        $datos = [
            'banco'                 => 'Banesco Banco Universal',
            'tipo_cuenta'           => 'corriente',
            'numero_cuenta'         => $numeroTest,
            'titular'               => 'Condominio Edificio Test',
            'tipo_identificacion'   => 'J',
            'identificacion'        => 'J-99999999-9',
            'telefono_pago_movil'   => '04149999999',
            'permite_transferencia' => 1,
            'permite_pago_movil'    => 1,
            'activa'                => 1
        ];

        $id = $model->crear($datos);
        $this->assertTrue($id > 0, "crear() debe retornar el ID autoincremental insertado");

        // 2. Leer cuenta
        $cuenta = $model->getById($id);
        $this->assertFalse(empty($cuenta), "getById() debe encontrar la cuenta recién creada");
        $this->assertEquals('Banesco Banco Universal', $cuenta['banco']);
        $this->assertEquals($numeroTest, $cuenta['numero_cuenta']);

        // 3. Alternar estado (toggle)
        $toggleOk = $model->toggleActiva($id);
        $this->assertTrue($toggleOk, "toggleActiva debe ejecutarse exitosamente");
        $cuentaDesactivada = $model->getById($id);
        $this->assertEquals(0, (int)$cuentaDesactivada['activa'], "La cuenta debe estar inactiva tras el toggle");
        $this->assertFalse($model->getActivaById($id), "getActivaById no debe retornar cuentas inactivas");

        // Reactivar
        $model->toggleActiva($id);
        $this->assertFalse(empty($model->getActivaById($id)), "getActivaById debe retornar la cuenta reactivada");

        // 4. Actualizar datos
        $datos['titular'] = 'Condominio Test Actualizado';
        $actualizado = $model->actualizar($id, $datos);
        $this->assertTrue($actualizado, "actualizar() debe retornar true");
        $cuentaActualizada = $model->getById($id);
        $this->assertEquals('Condominio Test Actualizado', $cuentaActualizada['titular']);

        // 5. Eliminar
        $resDel = $model->eliminarODesactivar($id);
        $this->assertTrue($resDel['exito'], "eliminarODesactivar() debe ser exitoso");
        $this->assertEquals('eliminada', $resDel['accion'], "Debe eliminarse físicamente si no tiene pagos vinculados");
        $this->assertFalse($model->getById($id), "La cuenta ya no debe existir");
    }

    public function testRutasAdminCuentasBancariasEstanRegistradas() {
        $indexContent = file_get_contents(BASE_PATH . '/public/index.php');
        $this->assertTrue(str_contains($indexContent, '/admin/cuentas-bancarias'), "Ruta /admin/cuentas-bancarias debe estar en index.php");
        $this->assertTrue(str_contains($indexContent, '/admin/cuentas-bancarias/guardar'), "Ruta /admin/cuentas-bancarias/guardar debe estar en index.php");
        $this->assertTrue(str_contains($indexContent, '/admin/cuentas-bancarias/toggle'), "Ruta /admin/cuentas-bancarias/toggle debe estar en index.php");
        $this->assertTrue(str_contains($indexContent, '/admin/cuentas-bancarias/eliminar'), "Ruta /admin/cuentas-bancarias/eliminar debe estar en index.php");
    }
}
