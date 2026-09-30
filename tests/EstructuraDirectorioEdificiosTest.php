<?php
namespace Tests;

class EstructuraDirectorioEdificiosTest extends TestCase {

    /**
     * Verifica que en la tabla principal de Directorio y Visualización de Unidades
     * se hayan eliminado las columnas obsoletas ("Cuota Mensual", "Estado", "Código / Unidad", "Acciones")
     * y se haya renombrado "Edificio / Torre" a únicamente "Edificio".
     */
    public function testTablaPrincipalDirectorioEliminaColumnasObsoletasYRenombraEdificio(): void {
        $viewPath = VIEWS_PATH . '/admin/estructura.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/estructura.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Extraer la sección thead de la tabla del directorio
        $this->assertStringContains('id="tablaDirectorioEdificios"', $content,
            "La vista debe contener la tabla de directorio de edificios (id='tablaDirectorioEdificios')");

        preg_match('/<table id="tablaDirectorioEdificios"[^>]*>.*?<thead>(.*?)<\/thead>/s', $content, $matches);
        $this->assertTrue(!empty($matches[1]), "La tabla de directorio debe tener un thead");

        $thead = $matches[1];

        // 2. Columna renombrada a 'Edificio' únicamente
        $this->assertStringContains('>Edificio<', $thead, "El encabezado debe llamarse únicamente 'Edificio'");
        $this->assertFalse(str_contains($thead, 'Edificio / Torre'), "El encabezado no debe contener 'Edificio / Torre'");

        // 3. Columnas obsoletas eliminadas del thead principal
        $this->assertFalse(str_contains($thead, 'Cuota Mensual'), "El encabezado de la tabla principal no debe incluir 'Cuota Mensual'");
        $this->assertFalse(str_contains($thead, 'Estado'), "El encabezado de la tabla principal no debe incluir 'Estado'");
        $this->assertFalse(str_contains($thead, 'Código / Unidad'), "El encabezado de la tabla principal no debe incluir 'Código / Unidad'");
        $this->assertFalse(str_contains($thead, 'Acciones'), "El encabezado de la tabla principal no debe incluir 'Acciones'");
    }

    /**
     * Verifica que la jerarquía inicial muestre edificios y que el detalle de unidades
     * se presente vía navegación drill-down colapsable por edificio.
     */
    public function testJerarquiaVisualizacionEdificiosYDrillDown(): void {
        $viewPath = VIEWS_PATH . '/admin/estructura.php';
        $content = file_get_contents($viewPath);

        // 1. Filas de edificios en el directorio
        $this->assertStringContains('class="fila-edificio', $content,
            "La tabla debe contener filas representativas de edificios (.fila-edificio)");

        // 2. Elementos de colapso y drill-down vinculados a cada edificio
        $this->assertStringContains('data-bs-toggle="collapse"', $content,
            "Las filas o botones deben contar con data-bs-toggle='collapse' para navegación drill-down");
        $this->assertStringContains('id="collapse-edificio-', $content,
            "Cada edificio debe contar con su contenedor de colapso de unidades (id='collapse-edificio-...')");
        $this->assertStringContains('Ver Unidades', $content,
            "Debe existir la acción o indicador 'Ver Unidades' para desplegar el detalle");

        // 3. Sub-tabla de unidades dentro del drill-down
        $this->assertStringContains('Unidades pertenecientes a', $content,
            "El panel desplegable debe mostrar el título de las unidades pertenecientes al edificio");
    }

    /**
     * Verifica que el flujo viejo de registro y desvinculación de residentes esté totalmente deprecado
     * (sin modales viejos, sin botones de registro manual en la vista, sin métodos en EstructuraController).
     */
    public function testDeprecacionCompletaFlujoViejoResidentes(): void {
        $viewPath = VIEWS_PATH . '/admin/estructura.php';
        $content = file_get_contents($viewPath);

        // 1. No debe existir el modal viejo modalGestionResidentes
        $this->assertFalse(str_contains($content, 'id="modalGestionResidentes"'),
            "La vista no debe incluir el modal de gestión de residentes antiguo");
        $this->assertFalse(str_contains($content, 'openModalGestionResidentes'),
            "La vista no debe invocar la función JS openModalGestionResidentes");
        $this->assertFalse(str_contains($content, 'action="/admin/estructura/residente/guardar"'),
            "La vista no debe tener formularios hacia /admin/estructura/residente/guardar");
        $this->assertFalse(str_contains($content, 'action="/admin/estructura/residente/desvincular"'),
            "La vista no debe tener formularios hacia /admin/estructura/residente/desvincular");

        // 2. En EstructuraController no deben existir guardarResidente ni desvincularResidente
        $controllerPath = dirname(__DIR__) . '/app/controllers/EstructuraController.php';
        $controllerContent = file_get_contents($controllerPath);

        $this->assertFalse(str_contains($controllerContent, 'function guardarResidente'),
            "EstructuraController no debe contener el método guardarResidente");
        $this->assertFalse(str_contains($controllerContent, 'function desvincularResidente'),
            "EstructuraController no debe contener el método desvincularResidente");

        // 3. En public/index.php no deben existir rutas a las acciones deprecadas
        $indexPath = BASE_PATH . '/public/index.php';
        $indexContent = file_get_contents($indexPath);

        $this->assertFalse(str_contains($indexContent, '/admin/estructura/residente/guardar'),
            "public/index.php no debe tener la ruta /admin/estructura/residente/guardar");
        $this->assertFalse(str_contains($indexContent, '/admin/estructura/residente/desvincular'),
            "public/index.php no debe tener la ruta /admin/estructura/residente/desvincular");
    }
}
