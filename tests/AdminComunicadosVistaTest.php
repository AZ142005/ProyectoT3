<?php
namespace Tests;

/**
 * Verifica el contrato de vista del modal "Nuevo Comunicado":
 * - La vista previa para residentes está presente y usa los ids esperados.
 * - El switch "enviar por correo" llega desactivado por defecto (sin checked).
 * - El footer reutiliza los patrones de botón de casa (R1 primario, R2 secundario).
 */
class AdminComunicadosVistaTest extends TestCase {

    private function readView(): string {
        return (string)file_get_contents(dirname(__DIR__) . '/app/views/admin/comunicados/index.php');
    }

    public function testPreviewPresenteEnModal(): void {
        $view = $this->readView();

        $this->assertStringContains('id="previewComunicado"', $view,
            "El modal de nuevo comunicado debe incluir la tarjeta de vista previa");
        $this->assertStringContains('Vista previa', $view,
            "El modal debe rotular la vista previa para residentes");
    }

    public function testCorreoDesactivadoPorDefecto(): void {
        $view = $this->readView();

        $encontrado = preg_match('/<input[^>]*name="enviar_email"[^>]*>/s', $view, $m);
        $tag = $encontrado ? $m[0] : '';

        $this->assertTrue($encontrado === 1,
            "El modal debe incluir el switch enviar_email");
        $this->assertFalse(str_contains($tag, 'checked'),
            "El switch enviar_email no debe estar marcado por defecto");
    }

    public function testBotonesFooterConPatronesDeCasa(): void {
        $view = $this->readView();

        $inicio = strpos($view, 'modal-footer');
        $fin = $inicio !== false ? strpos($view, '</form>', $inicio) : false;
        $footer = ($inicio !== false && $fin !== false) ? substr($view, $inicio, $fin - $inicio) : '';

        $this->assertTrue($footer !== '',
            "El modal debe tener footer con formulario");
        $this->assertStringContains('hover:bg-primary-hover', $footer,
            "El botón Publicar Comunicado del footer debe usar el patrón primario (R1)");
        $this->assertStringContains('hover:bg-slate-200', $footer,
            "El botón Cancelar del footer debe usar el patrón secundario (R2)");
    }
}
