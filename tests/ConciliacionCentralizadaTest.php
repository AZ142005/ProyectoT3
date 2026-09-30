<?php
namespace Tests;

use App\Models\ConciliacionModel;
use App\Core\Database;

class ConciliacionCentralizadaTest extends TestCase {

    /**
     * Verifica que la vista admin/conciliacion/index.php contenga la nueva lista
     * general de "Pagos por Verificar" con todas sus columnas y acciones requeridas.
     */
    public function testVistaContieneListaPagosPorVerificar(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/conciliacion/index.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Título de sección de pagos por verificar
        $this->assertStringContains('Pagos por Verificar', $content,
            "La vista debe incluir la sección de 'Pagos por Verificar'");

        // 2. Encabezados de tabla requeridos
        $this->assertStringContains('>Residente<', $content, "Debe tener columna Residente");
        $this->assertStringContains('>Inmueble<', $content, "Debe tener columna Inmueble");
        $this->assertStringContains('>Factura<', $content, "Debe tener columna Factura");
        $this->assertStringContains('>Referencia<', $content, "Debe tener columna Referencia");
        $this->assertStringContains('>Monto (Bs.)<', $content, "Debe tener columna Monto");
        $this->assertStringContains('>Fecha Pago<', $content, "Debe tener columna Fecha Pago");
        $this->assertStringContains('>Comprobante<', $content, "Debe tener columna Comprobante");
        $this->assertStringContains('>Acción<', $content, "Debe tener columna Acción");

        // 3. Descarga directa con download=1
        $this->assertStringContains('download=1', $content,
            "La vista debe incluir enlaces de descarga con parámetro download=1");

        // 4. Formulario de verificación/aprobación directa
        $this->assertStringContains('/admin/conciliacion/verificar', $content,
            "La vista debe incluir acción hacia /admin/conciliacion/verificar");
    }

    /**
     * Verifica que la vista mantenga intactas las 4 listas del Cruce Inteligente:
     * Exactas, Sugeridas, Inconsistencias y Sin Coincidencia.
     */
    public function testVistaMantieneCruceInteligenteYCuatroListas(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $content = file_get_contents($viewPath);

        // 1. Pestañas de los 4 estados de coincidencia
        $this->assertStringContains('id="exactas-tab"', $content, "Debe existir pestaña de Exactas");
        $this->assertStringContains('id="sugeridas-tab"', $content, "Debe existir pestaña de Sugeridas");
        $this->assertStringContains('id="inconsistencias-tab"', $content, "Debe existir pestaña de Inconsistencias");
        $this->assertStringContains('id="sin-coincidencia-tab"', $content, "Debe existir pestaña de Sin Coincidencia");

        // 2. Paneles de contenido asociados
        $this->assertStringContains('id="exactas"', $content, "Debe existir panel de contenido de Exactas");
        $this->assertStringContains('id="sugeridas"', $content, "Debe existir panel de contenido de Sugeridas");
        $this->assertStringContains('id="inconsistencias"', $content, "Debe existir panel de contenido de Inconsistencias");
        $this->assertStringContains('id="sin-coincidencia"', $content, "Debe existir panel de contenido de Sin Coincidencia");
    }

    /**
     * Verifica la eliminación estricta de las etiquetas descriptivas deprecadas:
     * '100% Match (Referencia + Monto)' y 'Fuzzy Match (Fecha + Monto)'.
     */
    public function testVistaDeprecaEtiquetasDeCoincidencia(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $content = file_get_contents($viewPath);

        $this->assertFalse(
            str_contains($content, '100% Match (Referencia + Monto)'),
            "La vista no debe contener la etiqueta deprecada '100% Match (Referencia + Monto)'"
        );

        $this->assertFalse(
            str_contains($content, 'Fuzzy Match (Fecha + Monto)'),
            "La vista no debe contener la etiqueta deprecada 'Fuzzy Match (Fecha + Monto)'"
        );

        $this->assertFalse(
            str_contains($content, '100% Match'),
            "La vista no debe contener el texto '100% Match'"
        );

        $this->assertFalse(
            str_contains($content, 'Fuzzy Match'),
            "La vista no debe contener el texto 'Fuzzy Match'"
        );
    }

    /**
     * Verifica que los colores semánticos estén restaurados en badges, botones y componentes,
     * pero manteniendo los montos y números sin clases de color (text-dark o font-monospace).
     */
    public function testVistaRestauraColoresSemanticosExceptoNumeros(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $content = file_get_contents($viewPath);

        // Los montos no deben tener clases de texto de color
        $this->assertFalse(
            (bool)preg_match('/<td[^>]*font-monospace[^>]*text-(success|danger|warning|info|primary)/i', $content),
            "Las celdas de números/montos no deben llevar clases de color de texto"
        );

        // La vista debe tener botones semánticos restaurados
        $this->assertTrue(str_contains($content, 'btn-success'), "La vista debe incluir botones de éxito (btn-success)");
        $this->assertTrue(str_contains($content, 'btn-primary'), "La vista debe incluir botones primarios (btn-primary)");
        $this->assertTrue(str_contains($content, 'btn-outline-danger') || str_contains($content, 'btn-danger'), "La vista debe incluir botones de peligro");

        // Debe utilizar text-dark para la legibilidad de números y textos base
        $this->assertTrue(
            str_contains($content, 'text-dark'),
            "La vista debe usar text-dark para textos y números"
        );
    }

    /**
     * Verifica que la ruta de verificación directa esté registrada en el router y restringida a Admin.
     */
    public function testRutaVerificarPagoDirectoRegistradaYProtegida(): void {
        $indexPath = BASE_PATH . '/public/index.php';
        $content = file_get_contents($indexPath);

        $this->assertStringContains("'/admin/conciliacion/verificar'", $content,
            "La ruta POST /admin/conciliacion/verificar debe estar en public/index.php");
        $this->assertStringContains("'verificarPagoDirecto'", $content,
            "La ruta debe apuntar al método verificarPagoDirecto");
        $this->assertStringContains("[UserRole::ADMIN]", $content,
            "La ruta debe estar protegida para UserRole::ADMIN");
    }

    /**
     * Verifica que ConciliacionModel::obtenerTodosPagosPendientes() devuelva la estructura correcta.
     */
    public function testModeloObtenerTodosPagosPendientes(): void {
        $model = new ConciliacionModel();
        $pendientes = $model->obtenerTodosPagosPendientes();

        $this->assertTrue(is_array($pendientes), "El método debe retornar un array de pagos pendientes");

        if (!empty($pendientes)) {
            $primerPago = $pendientes[0];
            $this->assertTrue(array_key_exists('id', $primerPago), "Debe contener campo id");
            $this->assertTrue(array_key_exists('monto', $primerPago), "Debe contener campo monto");
            $this->assertTrue(array_key_exists('estado', $primerPago), "Debe contener campo estado");
            $this->assertTrue(array_key_exists('referencia', $primerPago), "Debe contener campo referencia");
            $this->assertTrue(array_key_exists('residente_nombre', $primerPago), "Debe contener campo residente_nombre");
            $this->assertTrue(array_key_exists('origen_tabla', $primerPago), "Debe contener campo origen_tabla");
            $this->assertTrue(in_array($primerPago['origen_tabla'], ['pago', 'comprobante']), "El origen debe ser 'pago' o 'comprobante'");
        }
    }
}
