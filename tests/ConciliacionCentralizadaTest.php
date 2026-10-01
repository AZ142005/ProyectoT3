<?php
namespace Tests;

use App\Models\ConciliacionModel;
use App\Core\Database;

class ConciliacionCentralizadaTest extends TestCase {

    /**
     * Verifica que la vista admin/conciliacion/index.php contenga la bandeja unificada
     * de "Pagos por Verificar y Conciliar" con sus columnas y la verificación directa.
     */
    public function testVistaContieneListaPagosPorVerificar(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/conciliacion/index.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Título de la bandeja de pagos por verificar
        $this->assertStringContains('Pagos por Verificar', $content,
            "La vista debe incluir la bandeja de 'Pagos por Verificar'");

        // 2. Encabezados de tabla requeridos por el contrato de la bandeja unificada
        $this->assertStringContains('>Residente<', $content, "Debe tener columna Residente");
        $this->assertStringContains('>Inmueble<', $content, "Debe tener columna Inmueble");
        $this->assertStringContains('>Referencia<', $content, "Debe tener columna Referencia");
        $this->assertStringContains('>Monto (Bs.)<', $content, "Debe tener columna Monto");
        $this->assertStringContains('>Fecha<', $content, "Debe tener columna Fecha");
        $this->assertStringContains('>Acción<', $content, "Debe tener columna Acción");

        // 3. Formulario de verificación/aprobación directa (acciones del modal de detalle)
        $this->assertStringContains('/admin/conciliacion/verificar', $content,
            "La vista debe incluir acción hacia /admin/conciliacion/verificar");
    }

    /**
     * Verifica que la vista mantenga las 4 categorías del Cruce Inteligente
     * (Exactas, Sugeridas, Inconsistencias y Sin Coincidencia) más "Sin Extracto"
     * como filtros de una tabla única de conciliación.
     */
    public function testVistaMantieneCruceInteligenteYCuatroListas(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $content = file_get_contents($viewPath);

        // 1. Tabla única de la bandeja de conciliación
        $this->assertStringContains('id="tablaConciliacion"', $content,
            "Debe existir la tabla única de conciliación");

        // 2. Pills de filtro por categoría (4 del cruce + Sin Extracto)
        $this->assertStringContains('id="filtro-todas"', $content, "Debe existir filtro 'Todas' por defecto");
        $this->assertStringContains('id="filtro-exactas"', $content, "Debe existir filtro de Exactas");
        $this->assertStringContains('id="filtro-sugeridas"', $content, "Debe existir filtro de Sugeridas");
        $this->assertStringContains('id="filtro-inconsistencias"', $content, "Debe existir filtro de Inconsistencias");
        $this->assertStringContains('id="filtro-sin-coincidencia"', $content, "Debe existir filtro de Sin Coincidencia");
        $this->assertStringContains('id="filtro-sin-extracto"', $content, "Debe existir filtro de Sin Extracto");

        // 3. Contadores por categoría alimentados por el controlador
        $this->assertMatchesRegex('/id="filtro-exactas"[\s\S]{0,200}\$conteosConciliacion\[\'exacta\'\]/', $content,
            "El filtro de Exactas debe mostrar su contador");
        $this->assertMatchesRegex('/id="filtro-sugeridas"[\s\S]{0,200}\$conteosConciliacion\[\'sugerida\'\]/', $content,
            "El filtro de Sugeridas debe mostrar su contador");
        $this->assertMatchesRegex('/id="filtro-inconsistencias"[\s\S]{0,200}\$conteosConciliacion\[\'inconsistencia\'\]/', $content,
            "El filtro de Inconsistencias debe mostrar su contador");
        $this->assertMatchesRegex('/id="filtro-sin-coincidencia"[\s\S]{0,200}\$conteosConciliacion\[\'sin_coincidencia\'\]/', $content,
            "El filtro de Sin Coincidencia debe mostrar su contador");
        $this->assertMatchesRegex('/id="filtro-sin-extracto"[\s\S]{0,200}\$conteosConciliacion\[\'sin_extracto\'\]/', $content,
            "El filtro de Sin Extracto debe mostrar su contador");
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
