<?php
namespace Tests;

/**
 * Regresión T4 (ODD limpieza-acumulacion-datos): los controladores de pago
 * deben eliminar el comprobante ya persistido por FileUploader cuando el
 * registro falla definitivamente, para no dejar huérfanos en
 * uploads/comprobantes.
 *
 * Alcance: verificación estática de cableado sobre el código fuente (patrón
 * estable del repo, ver ArreglosAuditoriaTest). No es una prueba E2E del flujo
 * de subida: FileUploader usa move_uploaded_file(), que en CLI rechaza
 * cualquier archivo que no provenga de un POST HTTP real.
 */
class PagoUploadHuerfanoTest extends TestCase {

    /**
     * Extrae el cuerpo de un método público de un archivo fuente.
     */
    private function extraerMetodo(string $rutaArchivo, string $metodo): string {
        $contenido = (string)file_get_contents($rutaArchivo);
        preg_match('/public function ' . preg_quote($metodo, '/') . '\(.*?\)(.*?)(?=public function|private function|protected function|\Z)/s', $contenido, $m);
        return $m[1] ?? '';
    }

    public function testPagoControllerEliminaComprobanteHuerfanoEnRamaDeFallo(): void {
        $cuerpo = $this->extraerMetodo(dirname(__DIR__) . '/app/controllers/PagoController.php', 'subir');

        $this->assertTrue($cuerpo !== '', 'PagoController::subir() debe existir');

        $posCrear  = strpos($cuerpo, '$result = $pagoModel->crearPago(');
        $posExito  = strpos($cuerpo, "\$this->redirect('/pagos')");
        $posRuta   = strpos($cuerpo, "UPLOADS_PATH . '/comprobantes/' . \$uniqueName");
        $posUnlink = strpos($cuerpo, 'unlink(');
        $posError  = strpos($cuerpo, 'Flash::error("No se pudo registrar el pago');

        $this->assertTrue($posCrear !== false && $posExito !== false && $posRuta !== false && $posUnlink !== false && $posError !== false,
            'La rama de fallo de subir() debe conservar: crearPago, redirect de éxito, ruta del comprobante, unlink y Flash::error');

        if ($posCrear === false || $posExito === false || $posRuta === false || $posUnlink === false || $posError === false) {
            return;
        }

        // La eliminación vive únicamente después de crearPago y de la ruta de
        // éxito, y antes de renderizar el error: solo aplica cuando no se creó fila.
        $this->assertTrue($posCrear < $posExito, 'El redirect de éxito debe seguir a crearPago()');
        $this->assertTrue($posExito < $posRuta, 'La ruta del comprobante debe resolverse después de la ruta de éxito (solo en el else)');
        $this->assertTrue($posRuta < $posUnlink, 'La ruta debe construirse antes de eliminar el archivo');
        $this->assertTrue($posUnlink < $posError, 'El archivo debe eliminarse antes de mostrar el mensaje de error');

        // Guarda defensiva: nunca eliminar cuando no hay archivo persistido.
        $this->assertTrue(strpos($cuerpo, 'file_exists($rutaComprobante)') !== false,
            'Debe verificar file_exists($rutaComprobante) antes de eliminar');
        $this->assertTrue(strpos($cuerpo, 'if ($uniqueName && file_exists($rutaComprobante))') !== false,
            'La eliminación no debe ejecutarse sin nombre de archivo persistido (rama de fallo previa al upload)');
    }

    public function testPagoDirectoControllerEliminaComprobanteHuerfanoEnRamaDeFallo(): void {
        $cuerpo = $this->extraerMetodo(dirname(__DIR__) . '/app/controllers/PagoDirectoController.php', 'reportar');

        $this->assertTrue($cuerpo !== '', 'PagoDirectoController::reportar() debe existir');

        $posCrear  = strpos($cuerpo, '->crearPago($residenteId');
        $posExito  = strpos($cuerpo, "redirect('/pago-directo/exito')");
        $posRuta   = strpos($cuerpo, "UPLOADS_PATH . '/comprobantes/' . \$nombreArchivo");
        $posUnlink = strpos($cuerpo, 'unlink(');
        $posError  = strpos($cuerpo, "renderFormulario('Ya existe un pago registrado");

        $this->assertTrue($posCrear !== false && $posExito !== false && $posRuta !== false && $posUnlink !== false && $posError !== false,
            'La rama de fallo de reportar() debe conservar: crearPago, redirect de éxito, ruta del comprobante, unlink y renderFormulario de duplicado');

        if ($posCrear === false || $posExito === false || $posRuta === false || $posUnlink === false || $posError === false) {
            return;
        }

        $this->assertTrue($posCrear < $posExito, 'El redirect de éxito debe seguir a crearPago()');
        $this->assertTrue($posExito < $posRuta, 'La ruta del comprobante debe resolverse después de la ruta de éxito (solo cuando el registro falla)');
        $this->assertTrue($posRuta < $posUnlink, 'La ruta debe construirse antes de eliminar el archivo');
        $this->assertTrue($posUnlink < $posError, 'El archivo debe eliminarse antes de re-renderizar el formulario con el error');

        $this->assertTrue(strpos($cuerpo, 'file_exists($rutaComprobante)') !== false,
            'Debe verificar file_exists($rutaComprobante) antes de eliminar');
        $this->assertTrue(strpos($cuerpo, 'if ($nombreArchivo && file_exists($rutaComprobante))') !== false,
            'La eliminación no debe ejecutarse sin nombre de archivo persistido (errores previos al upload)');
    }

    public function testRutaDeEliminacionCoincideConLaRutaDeAlmacenamiento(): void {
        // Paridad de directorio: FileUploader persiste en UPLOADS_PATH/comprobantes
        // y los controladores deben eliminar exactamente de esa ruta.
        $uploader = (string)file_get_contents(dirname(__DIR__) . '/app/services/FileUploader.php');
        $this->assertStringContains("UPLOADS_PATH . '/comprobantes'", $uploader,
            'FileUploader debe seguir almacenando los comprobantes en UPLOADS_PATH/comprobantes');
    }
}
