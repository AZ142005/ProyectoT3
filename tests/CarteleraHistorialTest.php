<?php
namespace Tests;

/**
 * Verifica el Historial de Reportes de /admin/gastos: la vista, la consulta
 * limitada al último mes y el registro (upsert por hash) de cada carga del
 * Reporte de Cartelera.
 */
class CarteleraHistorialTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testVistaMuestraHistorialDeReportesSinFiltrosDeGastos(): void {
        $view = $this->readFile('app/views/admin/gastos/index.php');

        $this->assertStringContains('Historial de Reportes', $view,
            "La vista debe mostrar el 'Historial de Reportes'");
        $this->assertStringContains('último mes', $view,
            "La vista debe indicar que se muestran las cargas del último mes");
        $this->assertStringContains('components/pagination.php', $view,
            "El historial debe conservar la paginación");
        $this->assertFalse(str_contains($view, 'Historial de Gastos del Período'),
            "No debe quedar el título del historial de gastos");
        $this->assertFalse(str_contains($view, 'Limpiar filtros'),
            "La barra de filtros de gastos debe haberse retirado");
    }

    public function testControladorConsultaElHistorialDelUltimoMes(): void {
        $controller = $this->readFile('app/Controllers/GastoController.php');

        $this->assertStringContains('obtenerHistorialUltimoMes($pagina, 25)', $controller,
            "GastoController debe consultar el historial con límite de 25");
        $this->assertFalse(str_contains($controller, 'obtenerGastosAdmin'),
            "El listado de gastos ya no debe consultarse");
    }

    public function testHistorialFiltraLaVentanaDeUnMes(): void {
        $db = \App\Core\Database::getConnection();
        $marca = 'test_hist_' . bin2hex(random_bytes(6));

        try {
            $ins = $db->prepare("
                INSERT INTO reportes_cartelera
                    (archivo, nombre_original, hash, fecha_emision, fecha_carga, facturas_creadas, usuario_id)
                VALUES (:archivo, :nombre, NULL, '2026-10-01', :fecha, :creadas, 1)
            ");
            $ins->execute(['archivo' => $marca . '_reciente.pdf', 'nombre' => 'Reciente.pdf', 'fecha' => date('Y-m-d H:i:s'), 'creadas' => 10]);
            $ins->execute(['archivo' => $marca . '_antiguo.pdf', 'nombre' => 'Antiguo.pdf', 'fecha' => date('Y-m-d H:i:s', strtotime('-40 days')), 'creadas' => 99]);

            $resultado = (new \App\Models\CarteleraModel())->obtenerHistorialUltimoMes(1, 100);
            $archivos = array_column($resultado['datos'], 'archivo');

            $this->assertTrue(in_array($marca . '_reciente.pdf', $archivos, true),
                'El reporte reciente debe aparecer en el historial');
            $this->assertFalse(in_array($marca . '_antiguo.pdf', $archivos, true),
                'Un reporte de hace más de un mes no debe listarse');
            $this->assertTrue(in_array('usuario_nombre', array_keys($resultado['datos'][0] ?? [])),
                'La consulta debe incluir el nombre del usuario que cargó');
        } finally {
            $db->prepare("DELETE FROM reportes_cartelera WHERE archivo LIKE :marca")->execute(['marca' => $marca . '%']);
        }
    }

    public function testImportarLoteRegistraLaCargaConUpsertPorHash(): void {
        $db = \App\Core\Database::getConnection();
        $hash = 'testhash_' . bin2hex(random_bytes(8));
        $archivo = 'test_historial_' . bin2hex(random_bytes(4)) . '.pdf';
        $filas = [
            ['unidad' => '9-99', 'saldo_anterior' => 0, 'meses' => [10 => 123.45], 'total' => 123.45],
        ];

        try {
            $modelo = new \App\Models\CarteleraModel();
            $r1 = $modelo->importarLote($filas, '2026-10-05', 1, $archivo, $hash, 'Reporte Prueba.pdf');
            $this->assertTrue($r1['facturas_creadas'] === 1, 'La primera carga debe crear la deuda');

            $stmt = $db->prepare("SELECT * FROM reportes_cartelera WHERE hash = :hash");
            $stmt->execute(['hash' => $hash]);
            $fila = $stmt->fetch();
            $this->assertTrue($fila !== false, 'La carga debe quedar registrada en el historial');
            $this->assertTrue((int)$fila['facturas_creadas'] === 1, 'El historial debe registrar las facturas creadas');
            $this->assertTrue($fila['nombre_original'] === 'Reporte Prueba.pdf', 'El historial debe conservar el nombre original del PDF');

            $r2 = $modelo->importarLote($filas, '2026-10-06', 1, $archivo, $hash, 'Reporte Prueba.pdf');
            $this->assertTrue($r2['facturas_actualizadas'] === 1, 'La recarga debe actualizar la deuda existente');

            $conteo = (int)$db->query("SELECT COUNT(*) FROM reportes_cartelera WHERE hash = " . $db->quote($hash))->fetchColumn();
            $this->assertTrue($conteo === 1, 'Recargar el mismo PDF no debe duplicar la entrada del historial');
        } finally {
            $db->prepare("DELETE FROM reportes_cartelera WHERE hash = :hash")->execute(['hash' => $hash]);
            $db->prepare("DELETE FROM log_auditoria WHERE accion IN ('importar_cartelera','actualizar_cartelera') AND detalles LIKE :archivo")
               ->execute(['archivo' => '%' . $archivo . '%']);
            $db->prepare("DELETE FROM facturas WHERE numero_factura LIKE 'CART-9-99-%'")->execute();
            $db->prepare("DELETE FROM unidades WHERE numero = '9-99'")->execute();
            $db->prepare("DELETE FROM edificios WHERE nombre = 'Torre 9'")->execute();
        }
    }
}
