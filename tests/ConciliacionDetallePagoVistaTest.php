<?php
namespace Tests;

/**
 * Verifica la coherencia estética del modal emergente de Detalle del Pago
 * (#modalDetallePago) de admin/conciliacion/index.php: header y badge con
 * tokens de casa, tabla comparativa al patrón del Historial de Pagos y
 * mapeo dinámico del color del badge por estado en verDetalleConciliacion.
 */
class ConciliacionDetallePagoVistaTest extends TestCase {

    /**
     * Extrae la región del modal de Detalle del Pago, delimitada por el
     * comentario de apertura y el comentario del modal de Rechazar Pago.
     */
    private function regionModalDetallePago(string $content): string {
        $inicio = strpos($content, 'Modal Ventana de Detalles de Pago');
        $fin = strpos($content, 'Modal para Rechazar Pago', $inicio === false ? 0 : $inicio);
        if ($inicio === false || $fin === false || $fin <= $inicio) {
            return '';
        }
        return substr($content, $inicio, $fin - $inicio);
    }

    public function testRegionDelModalDetallePagoEstaDelimitada(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');
        $region = $this->regionModalDetallePago($content);

        $this->assertTrue($region !== '',
            "La región del modal de Detalle del Pago debe poder extraerse");
        $this->assertStringContains('id="modalDetallePago"', $region,
            "La región extraída debe contener el modal de Detalle del Pago");
    }

    public function testRegionUsaTokensDeCasaSinClasesBootstrapDeTabla(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');
        $region = $this->regionModalDetallePago($content);

        foreach (['bg-dark', 'text-dark', 'table-bordered', 'table-light', 'font-monospace', 'btn btn-'] as $claseProhibida) {
            $this->assertFalse(str_contains($region, $claseProhibida),
                "La región del modal no debe contener '{$claseProhibida}'");
        }

        foreach (['mdlEstadoBadge', 'rounded-pill', 'text-on-surface-variant'] as $tokenEsperado) {
            $this->assertStringContains($tokenEsperado, $region,
                "La región del modal debe contener '{$tokenEsperado}'");
        }
    }

    public function testJsMapeaColoresDelBadgeDeEstado(): void {
        $content = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertStringContains('bg-success', $content,
            "El archivo debe contener el color de aprobado del badge");
        $this->assertStringContains('bg-danger', $content,
            "El archivo debe contener el color de rechazado del badge");

        // El color de rechazo ya existe en el header del modal de Rechazar Pago,
        // por eso el mapeo se verifica también dentro del JS de verDetalleConciliacion.
        $inicioJs = strpos($content, 'function verDetalleConciliacion');
        $this->assertTrue($inicioJs !== false,
            "Debe existir la función verDetalleConciliacion");
        $jsDetalle = $inicioJs === false ? '' : substr($content, $inicioJs);

        $this->assertStringContains('bg-success', $jsDetalle,
            "El JS del detalle debe mapear APROBADO/VERIFICADO a bg-success");
        $this->assertStringContains('bg-danger', $jsDetalle,
            "El JS del detalle debe mapear RECHAZADO a bg-danger");
        $this->assertStringContains('bg-warning text-on-surface', $jsDetalle,
            "El JS debe mapear PENDIENTE a bg-warning con el token de casa (text-on-surface)");
        $this->assertFalse(str_contains($jsDetalle, 'bg-warning text-dark'),
            "El JS no debe usar text-dark (patrón de casa: text-on-surface)");
        $this->assertStringContains('bg-secondary text-white', $jsDetalle,
            "El JS debe usar el fallback secundario para otros estados");
    }
}
