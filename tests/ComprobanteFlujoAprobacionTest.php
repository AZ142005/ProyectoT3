<?php
namespace Tests;

class ComprobanteFlujoAprobacionTest extends TestCase {

    /**
     * Verifica que el proxy seguro soporte descarga forzada (attachment) e inline.
     */
    public function testProxySoportaDescargaForzadaEInline(): void {
        $proxyPath = BASE_PATH . '/public/comprobante-proxy.php';
        $this->assertTrue(file_exists($proxyPath), "El archivo comprobante-proxy.php debe existir en public/");

        $content = file_get_contents($proxyPath);

        // 1. Debe verificar autenticación
        $this->assertStringContains('$_SESSION[\'auth_user\'][\'id\']', $content,
            "El proxy debe verificar que el usuario esté autenticado");

        // 2. Debe verificar BOLA/IDOR para residentes
        $this->assertStringContains('residente', $content,
            "El proxy debe contemplar control de acceso para residentes");

        // 3. Debe verificar parámetro download
        $this->assertStringContains('download', $content,
            "El proxy debe verificar el parámetro download");

        // 4. Debe configurar Content-Disposition attachment para descarga
        $this->assertStringContains('attachment', $content,
            "El proxy debe emitir header Content-Disposition: attachment para descarga");

        // 5. Debe configurar Content-Disposition inline para visualización
        $this->assertStringContains('inline', $content,
            "El proxy debe emitir header Content-Disposition: inline para previsualización");

        // 6. Debe sanitizar el nombre de archivo con basename
        $this->assertStringContains('basename', $content,
            "El proxy debe aplicar basename para prevenir Directory Traversal");
    }

    /**
     * Verifica que la máquina de estados centralizada (App\Core\EstadoPago, D1)
     * permita aprobar directamente desde PENDIENTE y que PagoController la consuma.
     */
    public function testPagoControllerPermiteAprobarDesdePendiente(): void {
        $estadoPagoPath = APP_PATH . '/core/EstadoPago.php';
        $this->assertTrue(file_exists($estadoPagoPath), "EstadoPago.php debe existir");

        $controllerContent = file_get_contents(APP_PATH . '/controllers/PagoController.php');
        $this->assertStringContains('EstadoPago::transicionesValidas()', $controllerContent,
            "PagoController debe consumir la máquina de estados centralizada");

        // Validar transiciones de PENDIENTE por comportamiento (no por literales
        // de source): la máquina de estados debe permitir las tres transiciones.
        $this->assertTrue(
            \App\Core\EstadoPago::puedeTransicionar(\App\Core\EstadoPago::PENDIENTE, \App\Core\EstadoPago::APROBADO),
            "La máquina de estados debe permitir la transición directa de PENDIENTE a APROBADO"
        );

        $this->assertTrue(
            \App\Core\EstadoPago::puedeTransicionar(\App\Core\EstadoPago::PENDIENTE, \App\Core\EstadoPago::EN_REVISION),
            "La máquina de estados debe permitir la transición de PENDIENTE a EN REVISIÓN"
        );

        $this->assertTrue(
            \App\Core\EstadoPago::puedeTransicionar(\App\Core\EstadoPago::PENDIENTE, \App\Core\EstadoPago::RECHAZADO),
            "La máquina de estados debe permitir la transición de PENDIENTE a RECHAZADO"
        );
    }

    /**
     * Verifica que la vista de detalle de pagos contenga la opción de descarga activa en todos los estados.
     */
    public function testVistaDetalleContieneDescargaYAccionesAprobacion(): void {
        $viewPath = VIEWS_PATH . '/pagos/detalle.php';
        $this->assertTrue(file_exists($viewPath), "La vista app/views/pagos/detalle.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Debe generar la URL de descarga hacia comprobante-proxy con download=1
        $this->assertStringContains('download=1', $content,
            "La vista de detalle debe enlazar la descarga hacia comprobante-proxy.php?file=...&download=1");

        // 2. Debe contener el atributo download o texto semántico de descarga
        $this->assertStringContains('Descargar Comprobante', $content,
            "La vista de detalle debe incluir el botón o enlace 'Descargar Comprobante'");

        // 3. Debe incluir las acciones de aprobación para el administrador
        $this->assertStringContains('Aprobar Pago', $content,
            "La vista de detalle debe permitir aprobar el pago directamente");

        $this->assertStringContains('Poner En Revisión', $content,
            "La vista de detalle debe permitir poner el pago en revisión");

        $this->assertStringContains('Rechazar Pago', $content,
            "La vista de detalle debe permitir rechazar el pago");

        // 4. Debe contener protección CSRF en los formularios de acción
        $this->assertStringContains('csrf_field()', $content,
            "Los formularios de aprobación y rechazo deben incluir csrf_field()");

        // 5. Debe contener soporte tanto para PDF como para imágenes
        $this->assertStringContains('isPDF', $content,
            "La vista de detalle debe discriminar entre comprobantes PDF e imágenes");
    }

    /**
     * Verifica que la vista de verificación administrativa cuente con descarga activa en fases previa y posterior.
     */
    public function testVistaVerificarComprobanteContieneDescargaActiva(): void {
        $viewPath = VIEWS_PATH . '/admin/verificar_comprobante.php';
        $this->assertTrue(file_exists($viewPath), "La vista admin/verificar_comprobante.php debe existir");

        $content = file_get_contents($viewPath);

        // 1. Enlace de descarga con download=1
        $this->assertStringContains('download=1', $content,
            "La vista verificar_comprobante.php debe soportar download=1");

        // 2. Botón de descarga
        $this->assertStringContains('Descargar Comprobante', $content,
            "La vista verificar_comprobante.php debe tener el botón 'Descargar Comprobante'");

        // 3. Soporte para PDFs
        $this->assertStringContains('isPDF', $content,
            "La vista verificar_comprobante.php debe soportar formato PDF adecuadamente");
    }

    /**
     * Verifica que las tablas de listados administrativos provean acceso directo a descarga de comprobantes.
     */
    public function testListadosProveenDescargaDirecta(): void {
        $listaPagosPath = VIEWS_PATH . '/pagos/admin/lista.php';
        $listaComprobantesPath = VIEWS_PATH . '/admin/comprobantes.php';

        $contentPagos = file_get_contents($listaPagosPath);
        $contentComprobantes = file_get_contents($listaComprobantesPath);

        $this->assertStringContains('download=1', $contentPagos,
            "El listado de pagos administrativos debe tener botón de descarga directa de comprobante");

        $this->assertStringContains('download=1', $contentComprobantes,
            "El listado de comprobantes administrativos debe tener enlace de descarga directa");
    }
}
