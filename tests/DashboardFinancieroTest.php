<?php
namespace Tests;

use App\Models\ReportesModel;

class DashboardFinancieroTest extends TestCase {

    /**
     * Verifica que la vista del Dashboard contenga los indicadores clave
     * de resumen financiero (balances, ingresos, egresos, morosidad vs solvencia).
     */
    public function testDashboardFinancieroMetricasYComponentes(): void {
        $viewPath = VIEWS_PATH . '/admin/dashboard.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/dashboard.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Título e indicadores principales
        $this->assertStringContains('Panel de Control Financiero', $content, "La vista debe titularse Panel de Control Financiero");
        $this->assertStringContains('Total por Cobrar', $content, "Debe contener tarjeta de Total por Cobrar");
        $this->assertStringContains('Ingresos (', $content, "Debe contener tarjeta de Ingresos del mes");
        $this->assertStringContains('Egresos (', $content, "Debe contener tarjeta de Egresos del mes");
        $this->assertStringContains('Balance Operativo', $content, "Debe contener tarjeta de Balance Operativo");

        // 2. Sección de Salud de Cartera (Morosidad vs Solvencia)
        $this->assertStringContains('Salud de Cartera (Morosidad vs Solvencia)', $content,
            "Debe contener bloque de Salud de Cartera");
        $this->assertStringContains('Unidades Solventes', $content, "Debe mostrar desglose de Unidades Solventes");
        $this->assertStringContains('Unidades Deudoras', $content, "Debe mostrar desglose de Unidades Deudoras");

        // 3. Distribución de Egresos por Categoría
        $this->assertStringContains('Egresos por Categoría', $content,
            "Debe contener bloque de Egresos por Categoría");

        // 4. Cuentas Bancarias Autorizadas
        $this->assertStringContains('Cuentas Bancarias Autorizadas', $content,
            "Debe contener bloque de Cuentas Bancarias Autorizadas");

        // 5. Tabla de últimos movimientos procesados (solo lectura)
        $this->assertStringContains('Últimos Movimientos Financieros', $content,
            "Debe contener tabla de últimos movimientos financieros");
    }

    /**
     * Verifica la eliminación de cualquier acción operativa o transaccional de pagos
     * en el Dashboard (deprecación de verificación en esta vista).
     */
    public function testDashboardDeprecacionAccionesDeVerificacion(): void {
        $viewPath = VIEWS_PATH . '/admin/dashboard.php';
        $content = file_get_contents($viewPath);

        // 1. Cero links hacia verificación desde el dashboard
        $this->assertFalse(str_contains($content, 'from=dashboard'),
            "No debe haber enlaces que redirijan a verificación desde el dashboard (from=dashboard)");

        // 2. Cero botones de conciliar pagos dentro de tablas del dashboard
        $this->assertFalse(str_contains($content, 'class="py-4 px-4 font-mono text-xs">'),
            "La tabla anterior de comprobantes pendientes con acción de conciliar debe estar removida");
        $this->assertFalse(str_contains($content, 'title="Verificar en Conciliación"'),
            "No debe existir botón de verificar en conciliación dentro de las filas de tabla");

        // 3. Cero formularios de verificación o acciones POST de pagos
        $this->assertFalse(str_contains($content, '<form method="POST" action="/admin/comprobante/verificar"'),
            "El dashboard no debe contener formularios para verificar comprobantes");

        // 4. Aviso explícito de separación de responsabilidades hacia Conciliación
        $this->assertStringContains('Conciliación', $content,
            "Debe existir mención clara de que la conciliación de pagos se gestiona en dicho módulo");
    }

    /**
     * Verifica que el modelo ReportesModel genere correctamente el resumen
     * financiero para el Dashboard con estructura e integridad de tipos.
     */
    public function testReportesModelResumenFinancieroDashboard(): void {
        $reportesModel = new ReportesModel();
        $mes = intval(date('n'));
        $anio = intval(date('Y'));

        $resumen = $reportesModel->obtenerResumenFinancieroDashboard($mes, $anio);

        $this->assertTrue(is_array($resumen), "El resumen financiero debe ser un arreglo asociativo");
        $this->assertTrue(isset($resumen['kpis_morosidad']), "Debe incluir clave kpis_morosidad");
        $this->assertTrue(isset($resumen['total_por_cobrar']), "Debe incluir clave total_por_cobrar");
        $this->assertTrue(isset($resumen['total_ingresos_mes']), "Debe incluir clave total_ingresos_mes");
        $this->assertTrue(isset($resumen['total_recaudado_historico']), "Debe incluir clave total_recaudado_historico");
        $this->assertTrue(isset($resumen['total_gastos_mes']), "Debe incluir clave total_gastos_mes");
        $this->assertTrue(isset($resumen['total_gastos_historico']), "Debe incluir clave total_gastos_historico");
        $this->assertTrue(isset($resumen['balance_neto_mes']), "Debe incluir clave balance_neto_mes");
        $this->assertTrue(isset($resumen['balance_historico']), "Debe incluir clave balance_historico");
        $this->assertTrue(isset($resumen['total_pendientes']), "Debe incluir clave total_pendientes");

        // Validar tipos numéricos
        $this->assertTrue(is_numeric($resumen['total_por_cobrar']));
        $this->assertTrue(is_numeric($resumen['total_ingresos_mes']));
        $this->assertTrue(is_numeric($resumen['total_gastos_mes']));
        $this->assertTrue(is_numeric($resumen['balance_neto_mes']));
    }
}
