<?php
namespace Tests;

class FiltrosCompactosTest extends TestCase {

    private function readFile(string $relativePath): string {
        $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
        $this->assertTrue(file_exists($path), "El archivo {$relativePath} debe existir");
        return file_get_contents($path);
    }

    /**
     * Verifica que en todas las vistas con listados los filtros usen el patrón de Toolbar ultra-compacta inline permanente.
     */
    public function testFiltrosUsanToolbarCompactaPermanente(): void {
        $vistas = [
            'app/views/admin/comprobantes.php',
            'app/views/pagos/admin/lista.php',
            'app/views/admin/usuarios/index.php',
            'app/views/admin/gastos/index.php',
            'app/views/admin/reportes/morosidad.php',
        ];

        foreach ($vistas as $vistaPath) {
            $content = $this->readFile($vistaPath);
            $this->assertStringContains(
                'flex flex-wrap items-center gap-2',
                $content,
                "La vista {$vistaPath} debe implementar el toolbar inline permanente 'flex flex-wrap items-center gap-2'"
            );
            $this->assertFalse(
                (bool)preg_match('/class="[^"]*collapse[^"]*"[^>]*id="[^"]*filtro/i', $content),
                "La vista {$vistaPath} no debe contener componentes colapsables para los filtros"
            );
        }
    }

    /**
     * Verifica que el Historial de Comprobantes tenga los filtros requeridos en línea plana.
     */
    public function testHistorialComprobantesCamposFiltro(): void {
        $content = $this->readFile('app/views/admin/comprobantes.php');
        $this->assertStringContains('name="buscar"', $content);
        $this->assertStringContains('name="edificio_id"', $content);
        $this->assertStringContains('name="unidad"', $content);
        $this->assertStringContains('name="fecha_desde"', $content);
        $this->assertStringContains('name="fecha_hasta"', $content);
        $this->assertStringContains('name="estado"', $content);
    }

    /**
     * Verifica que la Lista General de Pagos tenga los filtros requeridos en línea plana.
     */
    public function testListaPagosAdminCamposFiltro(): void {
        $content = $this->readFile('app/views/pagos/admin/lista.php');
        $this->assertStringContains('name="estado"', $content);
        $this->assertStringContains('name="edificio"', $content);
        $this->assertStringContains('name="fecha"', $content);
    }

    /**
     * Verifica que Usuarios y Solicitudes preserve el tab y campos de filtrado.
     */
    public function testUsuariosFiltrosPreservanTab(): void {
        $content = $this->readFile('app/views/admin/usuarios/index.php');
        $this->assertStringContains('name="tab"', $content);
        $this->assertStringContains('name="buscar"', $content);
        $this->assertStringContains('name="rol"', $content);
    }

    /**
     * Verifica que Gastos y Facturación preserve los selectores y tab.
     */
    public function testGastosFiltrosCampos(): void {
        $content = $this->readFile('app/views/admin/gastos/index.php');
        $this->assertStringContains('name="tab"', $content);
        $this->assertStringContains('name="mes"', $content);
        $this->assertStringContains('name="anio"', $content);
        $this->assertStringContains('name="categoria_id"', $content);
        $this->assertStringContains('name="tipo_gasto"', $content);
    }

    /**
     * Verifica que el selector de período del Dashboard sea visible en mobile y desktop (sin d-none).
     */
    public function testDashboardPeriodoEsPermanente(): void {
        $content = $this->readFile('app/views/admin/dashboard.php');
        $this->assertFalse(
            str_contains($content, 'd-none d-sm-flex align-items-center gap-2 mb-0'),
            "El formulario de período rápido del dashboard no debe ocultarse con d-none en ningún dispositivo"
        );
        $this->assertStringContains(
            '<form method="GET" action="/admin/dashboard" class="d-flex align-items-center gap-1.5 mb-0">',
            $content,
            "El formulario de período del dashboard debe estar siempre visible"
        );
    }
}
