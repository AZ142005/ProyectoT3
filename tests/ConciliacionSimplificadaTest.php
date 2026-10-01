<?php
namespace Tests;

/**
 * Verifica la simplificación visual de admin/conciliacion/index.php:
 * se eliminan duplicaciones y ruido, conservando tabs, acciones y utilidad operativa.
 */
class ConciliacionSimplificadaTest extends TestCase {

    public function testVistaEliminaBloqueMetricasRapidas(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertFalse(str_contains($content, 'Métricas Rápidas'),
            "La vista no debe contener el bloque de Métricas Rápidas");
        $this->assertFalse(str_contains($content, 'Indicadores Clave'),
            "La vista no debe contener el comentario de Indicadores Clave");
    }

    public function testVistaEliminaEncabezadoDuplicado(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertFalse(str_contains($content, 'Centro de Verificación y Conciliación'),
            "La vista no debe repetir el h4 duplicado del título principal");
    }

    public function testVistaEliminaColumnasEstadoCruce(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertFalse(str_contains($content, '>Estado Cruce<'),
            "Las columnas constantes 'Estado Cruce' deben eliminarse");
    }

    public function testVistaEliminaEmojisDeTabs(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertFalse(str_contains($content, '🟢'), "La etiqueta de Exactas no debe llevar emoji");
        $this->assertFalse(str_contains($content, '🟡'), "La etiqueta de Sugeridas no debe llevar emoji");
        $this->assertFalse(str_contains($content, '🔴'), "La etiqueta de Inconsistencias no debe llevar emoji");
        $this->assertFalse(str_contains($content, '⚪'), "La etiqueta de Sin Coincidencia no debe llevar emoji");
    }

    public function testVistaConservaTabsAccionesYTablasCompactas(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertStringContains('id="exactas-tab"', $content, "Debe conservarse la pestaña de Exactas");
        $this->assertStringContains('id="sugeridas-tab"', $content, "Debe conservarse la pestaña de Sugeridas");
        $this->assertStringContains('id="inconsistencias-tab"', $content, "Debe conservarse la pestaña de Inconsistencias");
        $this->assertStringContains('id="sin-coincidencia-tab"', $content, "Debe conservarse la pestaña de Sin Coincidencia");

        $this->assertStringContains('Conciliar Todas las Exactas', $content,
            "Debe conservarse la conciliación en lote (1-Clic)");
        $this->assertStringContains('download=1', $content,
            "Debe conservarse la descarga directa de comprobantes");
        $this->assertStringContains('table-sm', $content,
            "Las tablas de datos deben usar densidad compacta (table-sm)");
    }

    public function testVistaMuestraColumnaLoteSoloSinLoteSeleccionado(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertMatchesRegex('/empty\(\$loteActual\)[\s\S]{0,200}>\s*Lote\s*<\/th>/', $content,
            "El encabezado 'Lote' solo debe renderizarse cuando no hay lote seleccionado");

        $this->assertMatchesRegex('/empty\(\$loteActual\)[\s\S]{0,300}lote_importacion/', $content,
            "La celda de lote solo debe renderizarse cuando no hay lote seleccionado");

        $this->assertStringContains('colspan="<?= empty($loteActual) ? 5 : 4 ?>"', $content,
            "El colspan del estado vacío debe ajustarse a las columnas visibles");
    }

    public function testVistaProyectaPayloadsDeDetalleSinEmbeberEntidadesCompletas(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertFalse((bool)preg_match('/json_encode\(\s*\$p\s*,/', $content),
            "El detalle directo no debe embeber el pago completo en JSON");
        $this->assertFalse((bool)preg_match('/json_encode\(\s*\$match\s*,/', $content),
            "Los detalles de cruce no deben embeber el match completo en JSON");

        $this->assertStringContains('$proyectarPagoDetalle', $content,
            "Los payloads de pago deben proyectar solo los campos usados por el JS");
        $this->assertStringContains('$proyectarExtractoDetalle', $content,
            "Los payloads de extracto deben proyectar solo los campos usados por el JS");
    }
}
