<?php
namespace Tests;

/**
 * Verifica el contrato visual de admin/conciliacion/index.php:
 * tarjetas de métricas restauradas, filtros de coincidencia fuera de la lista,
 * bandeja con estilo del Historial de Pagos, acciones directas por fila
 * (Conciliar/Verificar/Rechazar) y detalle en ícono, sin descargas por fila.
 */
class ConciliacionSimplificadaTest extends TestCase {

    public function testVistaRestauraBloqueMetricasRapidas(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertStringContains('Métricas Rápidas', $content,
            "La vista debe restaurar el bloque de Métricas Rápidas");
        $this->assertStringContains('Indicadores Clave', $content,
            "La vista debe incluir el comentario de Indicadores Clave");
        $this->assertStringContains('pending_actions', $content,
            "La tarjeta de Pagos por Verificar debe conservar su ícono");
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

    public function testVistaConservaAccionesFiltrosYTablasCompactas(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertStringContains('id="filtro-todas"', $content,
            "Debe existir el filtro 'Todas', activo por defecto");
        $this->assertStringContains('id="filtro-sin-extracto"', $content,
            "Debe existir el filtro de pagos sin extracto");
        $this->assertStringContains('title="Ver Detalle"', $content,
            "Cada fila debe ofrecer el botón de detalle del historial ('Ver Detalle')");
        $this->assertStringContains('Conciliar Todas las Exactas', $content,
            "Debe conservarse la conciliación en lote (1-Clic)");
        $this->assertStringContains('border-collapse', $content,
            "La tabla de la bandeja debe usar el estilo del historial (border-collapse)");
        $this->assertStringContains('text-sm', $content,
            "La tabla de la bandeja debe usar el estilo del historial (text-sm)");
        $this->assertStringContains('py-2.5 px-4 text-end font-bold text-on-surface', $content,
            "El monto de la bandeja debe quedar alineado a la derecha con su encabezado");
        $this->assertFalse(str_contains($content, 'title="Descargar comprobante"'),
            "No debe existir descarga de comprobante por fila");

        // Acciones directas por fila dentro de la tabla de la bandeja
        $inicioTabla = strpos($content, 'id="tablaConciliacion"');
        $this->assertTrue($inicioTabla !== false, "Debe existir la tabla de la bandeja de conciliación");
        $finTabla = strpos($content, '</table>', $inicioTabla);
        $this->assertTrue($finTabla !== false, "La tabla de la bandeja debe cerrar correctamente");
        $tabla = substr($content, $inicioTabla, $finTabla - $inicioTabla);

        $this->assertFalse(str_contains($tabla, 'table-sm'),
            "La tabla de la bandeja no debe usar clases Bootstrap de tabla (table-sm)");
        $this->assertStringContains('action="/admin/conciliacion/conciliar"', $tabla,
            "Las filas con pago y extracto deben ofrecer la conciliación directa");
        $this->assertStringContains('action="/admin/conciliacion/verificar"', $tabla,
            "Las filas de pago sin extracto deben ofrecer la verificación directa");

        foreach (['>Conciliar<', '>Verificar<', '>Rechazar<'] as $botonDirecto) {
            $this->assertStringContains($botonDirecto, $tabla,
                "La tabla debe incluir el botón directo {$botonDirecto}");
        }
    }

    public function testVistaEliminaColumnaLoteCondicional(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertFalse(str_contains($content, '>Lote<'),
            "La columna Lote debe eliminarse de la bandeja unificada");
        $this->assertFalse(str_contains($content, 'empty($loteActual) ? 5 : 4'),
            "Ya no debe existir el colspan condicional por lote");
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
