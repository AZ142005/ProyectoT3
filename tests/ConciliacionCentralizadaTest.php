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
        $this->assertMatchesRegex('/max-h-\[600px\][^>]*overflow-y-auto/', $content,
            "La bandeja de pagos por verificar debe tener scroll vertical propio");

        // 2. Pills de filtro por categoría (4 del cruce + Sin Extracto)
        $this->assertStringContains('id="filtro-todas"', $content, "Debe existir filtro 'Todas' por defecto");
        $this->assertStringContains('id="filtro-exactas"', $content, "Debe existir filtro de Exactas");
        $this->assertStringContains('id="filtro-sugeridas"', $content, "Debe existir filtro de Sugeridas");
        $this->assertStringContains('id="filtro-inconsistencias"', $content, "Debe existir filtro de Inconsistencias");
        $this->assertStringContains('id="filtro-sin-coincidencia"', $content, "Debe existir filtro de Sin Coincidencia");
        $this->assertStringContains('id="filtro-sin-extracto"', $content, "Debe existir filtro de Sin Extracto");

        // 3. Visual del filtro sin números ni paréntesis: cada pill muestra solo su etiqueta
        $this->assertMatchesRegex('/id="filtro-todas">\s*Todas\s*<\/button>/', $content,
            "El filtro de Todas debe mostrar solo su etiqueta, sin contador");
        $this->assertMatchesRegex('/id="filtro-exactas">\s*Exactas\s*<\/button>/', $content,
            "El filtro de Exactas debe mostrar solo su etiqueta, sin contador");
        $this->assertMatchesRegex('/id="filtro-sugeridas">\s*Sugeridas\s*<\/button>/', $content,
            "El filtro de Sugeridas debe mostrar solo su etiqueta, sin contador");
        $this->assertMatchesRegex('/id="filtro-inconsistencias">\s*Inconsistencias\s*<\/button>/', $content,
            "El filtro de Inconsistencias debe mostrar solo su etiqueta, sin contador");
        $this->assertMatchesRegex('/id="filtro-sin-coincidencia">\s*Sin Coincidencia\s*<\/button>/', $content,
            "El filtro de Sin Coincidencia debe mostrar solo su etiqueta, sin contador");
        $this->assertMatchesRegex('/id="filtro-sin-extracto">\s*Sin Extracto\s*<\/button>/', $content,
            "El filtro de Sin Extracto debe mostrar solo su etiqueta, sin contador");
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
     * Verifica que los montos de la bandeja permanezcan neutrales mientras las
     * acciones por fila conservan su semántica visual: verde para conciliar/verificar,
     * rojo para rechazar y gris para ver el detalle.
     */
    public function testVistaRestauraColoresSemanticosExceptoNumeros(): void {
        $viewPath = VIEWS_PATH . '/admin/conciliacion/index.php';
        $content = file_get_contents($viewPath);

        // Los montos permanecen neutrales: la celda de monto usa text-on-surface y no colores semánticos
        $this->assertFalse(
            (bool)preg_match('/<td[^>]*font-bold[^>]*text-(green|red|success|danger|warning|info|primary)/i', $content),
            "La celda de monto no debe combinar número con clases de color semántico"
        );

        // Las acciones directas por fila sí son semánticas: verde para conciliar/verificar
        $this->assertTrue(str_contains($content, 'bg-green-50'), "Las acciones de conciliar/verificar deben usar verde (bg-green-50)");
        $this->assertTrue(str_contains($content, 'text-green-700'), "Las acciones de conciliar/verificar deben usar verde (text-green-700)");

        // Rojo para rechazar
        $this->assertTrue(str_contains($content, 'bg-red-50'), "La acción de rechazo debe usar rojo (bg-red-50)");
        $this->assertTrue(str_contains($content, 'text-red-700'), "La acción de rechazo debe usar rojo (text-red-700)");

        // El botón de detalle conserva el gris neutro del historial
        $this->assertTrue(str_contains($content, 'bg-slate-100'), "El botón de detalle debe usar el gris del historial (bg-slate-100)");

        // La base textual del estilo nuevo es on-surface
        $this->assertTrue(str_contains($content, 'text-on-surface'), "La vista debe usar text-on-surface para textos y números");
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
