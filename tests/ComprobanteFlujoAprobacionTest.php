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
     * Verifica que la máquina de estados en PagoController permita aprobar directamente desde PENDIENTE.
     */
    public function testPagoControllerPermiteAprobarDesdePendiente(): void {
        $controllerPath = APP_PATH . '/controllers/PagoController.php';
        $this->assertTrue(file_exists($controllerPath), "PagoController.php debe existir");

        $content = file_get_contents($controllerPath);

        // Validar transiciones de PENDIENTE
        $this->assertTrue(
            preg_match("/'PENDIENTE'\s*=>\s*\[[^\]]*'APROBADO'[^\]]*\]/", $content) === 1,
            "La máquina de estados debe permitir la transición directa de PENDIENTE a APROBADO"
        );

        $this->assertTrue(
            preg_match("/'PENDIENTE'\s*=>\s*\[[^\]]*'EN REVISIÓN'[^\]]*\]/", $content) === 1,
            "La máquina de estados debe permitir la transición de PENDIENTE a EN REVISIÓN"
        );

        $this->assertTrue(
            preg_match("/'PENDIENTE'\s*=>\s*\[[^\]]*'RECHAZADO'[^\]]*\]/", $content) === 1,
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

    /**
     * Verifica que el botón de retroceso del detalle de pago respete el origen del registro:
     * los comprobantes vuelven a la Verificación de Pagos y los pagos generales a su listado.
     */
    public function testBotonRetrocesoDelDetalleVuelveAlOrigen(): void {
        $content = file_get_contents(VIEWS_PATH . '/pagos/detalle.php');

        // 1. El detalle de un comprobante regresa a /admin/comprobantes (Verificación de Pagos)
        $this->assertMatchesRegex(
            '/\$tipoOrigen\s*===\s*\'comprobante\'[\s\S]{0,700}?href="\/admin\/comprobantes"[\s\S]{0,700}?Volver a Verificaci/',
            $content,
            "El detalle de un comprobante debe volver a /admin/comprobantes (Verificación de Pagos)"
        );

        // 2. El detalle de un pago general regresa a /pagos (Listado General de Pagos)
        $this->assertMatchesRegex(
            '/\$tipoOrigen\s*===\s*\'comprobante\'[\s\S]*?else\s*:\s*\?>[\s\S]{0,700}?href="\/pagos"[\s\S]{0,700}?Volver a Pagos/',
            $content,
            "El detalle de un pago general debe volver a /pagos (Listado General de Pagos)"
        );
    }
}
