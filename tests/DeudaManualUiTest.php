<?php
namespace Tests;

/**
 * Verifica que el modal de deuda manual use el estilo de checkboxes por edificio,
 * con cada edificio colapsable (despliegue/contraccion).
 */
class DeudaManualUiTest extends TestCase {

    public function testModalDeudaManualUsaCheckboxesConEdificiosColapsables(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/gastos/index.php');

        $this->assertStringContains('id="contenedorUnidadesDeudaManual"', $content,
            "Debe existir el contenedor de unidades por edificio");
        $this->assertStringContains('deuda-check-unidad', $content,
            "Debe existir el checkbox de unidad");
        $this->assertStringContains('deuda-check-edificio', $content,
            "Debe existir el checkbox de edificio completo");
        $this->assertStringContains('deuda-grupo-unidades', $content,
            "Cada edificio debe tener su panel de unidades colapsable");
        $this->assertStringContains('toggleGrupoDeudaManual', $content,
            "Debe existir la funcion de despliegue/contraccion por edificio");
        $this->assertStringContains('aria-expanded="false"', $content,
            "Los edificios deben iniciar colapsados");
        $this->assertStringContains('style="display: none;"', $content,
            "El panel de unidades debe iniciar oculto");
        $this->assertFalse(str_contains($content, 'id="edificioDeudaManual"'),
            "Ya no debe usar el selector en cascada");
        $this->assertFalse(str_contains($content, 'DEUDA_MANUAL_DATA'),
            "Ya no debe depender del mapa JSON de la cascada");
    }
}
