<?php
namespace Tests;

use App\Models\PagoModel;

class PagoDetalleNavegacionTest extends TestCase {

    /**
     * Verifica que PagoModel::obtenerTodosPagos unifique los pagos de 'pagos'
     * y 'comprobantes_pago' para que la lista de pagos cargue correctamente.
     */
    public function testObtenerTodosPagosCargaPagosUnificados(): void {
        $pagoModel = new PagoModel();
        $resultado = $pagoModel->obtenerTodosPagos([], 1, 20);

        $this->assertTrue(is_array($resultado), "El resultado debe ser un array paginado");
        $this->assertTrue(isset($resultado['total']) && $resultado['total'] > 0, "Debe cargar pagos existentes en el sistema");
        $this->assertTrue(isset($resultado['datos']) && count($resultado['datos']) > 0, "La lista de datos no debe estar vacía");

        $primerPago = $resultado['datos'][0];
        $this->assertTrue(isset($primerPago['id']), "Cada pago debe contener su ID");
        $this->assertTrue(isset($primerPago['monto']), "Cada pago debe contener su monto");
        $this->assertTrue(isset($primerPago['estado']), "Cada pago debe contener su estado");
        $this->assertTrue(isset($primerPago['residente_nombre']), "Cada pago debe contener el nombre del residente");
        $this->assertTrue(isset($primerPago['tipo_origen']), "Cada pago debe especificar su tipo de origen");
    }

    /**
     * Verifica que PagoModel::obtenerPagoPorId resuelva pagos tanto de 'pagos'
     * como de 'comprobantes_pago' con normalización polimórfica.
     */
    public function testObtenerPagoPorIdResuelvePagosYComprobantes(): void {
        $pagoModel = new PagoModel();
        
        // Obtener un pago del listado
        $resultado = $pagoModel->obtenerTodosPagos([], 1, 1);
        $this->assertTrue(count($resultado['datos']) > 0, "Debe existir al menos un pago");

        $id = $resultado['datos'][0]['id'];
        $pago = $pagoModel->obtenerPagoPorId($id);

        $this->assertTrue(is_array($pago), "obtenerPagoPorId debe retornar array para ID válido");
        $this->assertEquals((int)$id, (int)$pago['id'], "El ID retornado debe coincidir");
        $this->assertTrue(!empty($pago['residente_nombre']), "Debe resolver el nombre del residente");
        $this->assertTrue(!empty($pago['unidad_numero']), "Debe resolver el número de unidad");
    }

    /**
     * Verifica que la vista detalle.php no tenga el botón estático obsoleto "Volver a Pagos"
     * y disponga de la infraestructura de navegación dinámica.
     */
    public function testVistaDetalleNavegacionDinamicaYRetiroBotonEstatico(): void {
        $viewPath = VIEWS_PATH . '/pagos/detalle.php';
        $this->assertTrue(file_exists($viewPath), "La vista detalle.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. No debe existir el texto estático "Volver a Pagos"
        $this->assertFalse(
            str_contains($content, '<span>Volver a Pagos</span>'),
            "La vista no debe tener el botón estático 'Volver a Pagos'"
        );

        // 2. Debe implementar variables dinámicas de retorno
        $this->assertStringContains('$volverUrl', $content, "La vista debe definir y usar \$volverUrl");
        $this->assertStringContains('$volverTexto', $content, "La vista debe definir y usar \$volverTexto");
        $this->assertStringContains('$volverIcono', $content, "La vista debe definir y usar \$volverIcono");

        // 3. Debe mapear adecuadamente los destinos
        $this->assertStringContains("'/admin/conciliacion'", $content, "Debe soportar retorno a conciliación");
        $this->assertStringContains("'/admin/comprobantes'", $content, "Debe soportar retorno a historial de pagos");
        $this->assertStringContains("'/admin/dashboard'", $content, "Debe soportar retorno al dashboard");
    }

    /**
     * Verifica la lógica de resolución de URL y texto del botón de retroceso dinámico.
     */
    public function testContratoNavegacionRetornoDinamico(): void {
        $resolver = function(string $fromParam, bool $isAdmin = true, bool $isAuditor = false) {
            if ($fromParam === 'conciliacion') {
                return ['url' => '/admin/conciliacion', 'texto' => 'Volver a Conciliación'];
            } elseif ($fromParam === 'dashboard') {
                return ['url' => '/admin/dashboard', 'texto' => 'Volver al Dashboard'];
            } elseif ($isAdmin || $isAuditor) {
                return ['url' => '/admin/comprobantes', 'texto' => 'Volver a Historial de Pagos'];
            } else {
                return ['url' => '/pagos', 'texto' => 'Volver a Mis Pagos'];
            }
        };

        // Origen: Conciliación
        $resConciliacion = $resolver('conciliacion', true);
        $this->assertEquals('/admin/conciliacion', $resConciliacion['url']);
        $this->assertEquals('Volver a Conciliación', $resConciliacion['texto']);

        // Origen: Historial de Pagos (comprobantes)
        $resComprobantes = $resolver('comprobantes', true);
        $this->assertEquals('/admin/comprobantes', $resComprobantes['url']);
        $this->assertEquals('Volver a Historial de Pagos', $resComprobantes['texto']);

        // Origen: Historial de Pagos por omisión en Administrador
        $resAdminDefault = $resolver('', true);
        $this->assertEquals('/admin/comprobantes', $resAdminDefault['url']);
        $this->assertEquals('Volver a Historial de Pagos', $resAdminDefault['texto']);

        // Origen: Dashboard
        $resDashboard = $resolver('dashboard', true);
        $this->assertEquals('/admin/dashboard', $resDashboard['url']);
        $this->assertEquals('Volver al Dashboard', $resDashboard['texto']);

        // Origen: Residente
        $resResidente = $resolver('', false, false);
        $this->assertEquals('/pagos', $resResidente['url']);
        $this->assertEquals('Volver a Mis Pagos', $resResidente['texto']);
    }
}
