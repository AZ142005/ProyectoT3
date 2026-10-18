<?php
namespace Tests;

use App\Core\Database;
use App\Models\FacturasModel;

/**
 * Regla de negocio (ODD pago-minimo-deuda): al enviar un pago, si la unidad
 * tiene deuda pendiente (total_deuda > 0) el monto debe ser mayor o igual a
 * esa deuda. El sobrepago sigue permitido y una unidad sin deuda no tiene
 * restricción.
 *
 * Alcance: prueba de datos contra la base real (patrón SaldoFavorTest) y
 * verificación estática de cableado sobre el código fuente de controladores y
 * vistas (patrón PagoUploadHuerfanoTest).
 */
class PagoMontoDeudaTest extends TestCase {

    /**
     * Extrae el cuerpo de un método público de un archivo fuente.
     */
    private function extraerMetodo(string $rutaArchivo, string $metodo): string {
        $contenido = (string)file_get_contents($rutaArchivo);
        preg_match('/public function ' . preg_quote($metodo, '/') . '\(.*?\)(.*?)(?=public function|private function|protected function|\Z)/s', $contenido, $m);
        return $m[1] ?? '';
    }

    public function testTotalDeudaEsSumaSaldosPositivosSinNetearSaldoAFavor(): void {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Unidad con facturas de saldo 100, 50 y -40: la deuda solo suma los
            //    saldos positivos (150.00) y el abono se reporta como saldo a favor
            //    (40.00) sin netear la deuda.
            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 100.00, 1)");
            $stmtU->execute(['num' => 'TEST-DEUDA-' . mt_rand(1000, 9999)]);
            $unidadId = intval($db->lastInsertId());

            $stmtF = $db->prepare("
                INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado)
                VALUES (:num, :uid, 9, 2026, '2026-09-01', '2026-09-15', :total, :pagado, :saldo, :estado)
            ");
            $stmtF->execute(['num' => 'FAC-TEST-DEUDA-1-' . mt_rand(1000, 9999), 'uid' => $unidadId, 'total' => 100.00, 'pagado' => 0.00, 'saldo' => 100.00, 'estado' => 'pendiente']);
            $stmtF->execute(['num' => 'FAC-TEST-DEUDA-2-' . mt_rand(1000, 9999), 'uid' => $unidadId, 'total' => 50.00, 'pagado' => 0.00, 'saldo' => 50.00, 'estado' => 'pendiente']);
            $stmtF->execute(['num' => 'ABONO-TEST-DEUDA-' . mt_rand(1000, 9999), 'uid' => $unidadId, 'total' => 0.00, 'pagado' => 40.00, 'saldo' => -40.00, 'estado' => 'pagada']);

            $facturasModel = new FacturasModel();
            $resumen = $facturasModel->getResumenFinancieroUnidad($unidadId);

            $this->assertEquals(150.00, $resumen['total_deuda'], 'La deuda debe sumar solo los saldos positivos (100 + 50)');
            $this->assertEquals(40.00, $resumen['saldo_favor'], 'El saldo a favor (40) debe reportarse por separado, sin netear la deuda');

            // 2. Unidad sin saldos positivos: la deuda debe ser 0.00.
            $stmtU2 = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:num, 100.00, 1)");
            $stmtU2->execute(['num' => 'TEST-DEUDA-CERO-' . mt_rand(1000, 9999)]);
            $unidadId2 = intval($db->lastInsertId());

            $stmtF->execute(['num' => 'ABONO-TEST-CERO-' . mt_rand(1000, 9999), 'uid' => $unidadId2, 'total' => 0.00, 'pagado' => 25.00, 'saldo' => -25.00, 'estado' => 'pagada']);

            $resumenSinDeuda = $facturasModel->getResumenFinancieroUnidad($unidadId2);

            $this->assertEquals(0.00, $resumenSinDeuda['total_deuda'], 'Una unidad sin saldos positivos no tiene deuda');
            $this->assertEquals(25.00, $resumenSinDeuda['saldo_favor'], 'El saldo a favor debe conservarse aunque no exista deuda');
        } finally {
            $db->rollBack();
        }
    }

    public function testSubirBloqueaMontoMenorADeudaAntesDeSubirArchivo(): void {
        $cuerpo = $this->extraerMetodo(dirname(__DIR__) . '/app/Controllers/PagoController.php', 'subir');

        $this->assertTrue($cuerpo !== '', 'PagoController::subir() debe existir');
        $this->assertStringContains('no puede ser menor a la deuda pendiente de la unidad', $cuerpo,
            'subir() debe rechazar montos menores a la deuda pendiente de la unidad');

        // El mensaje de rechazo se busca con paréntesis para no confundirlo con el
        // comentario de la regla de negocio, que repite la misma frase.
        $posResumen = strpos($cuerpo, 'getResumenFinancieroUnidad');
        $posMensaje = strpos($cuerpo, 'no puede ser menor a la deuda pendiente de la unidad (');
        $posUpload  = strpos($cuerpo, 'FileUploader');

        $this->assertTrue($posResumen !== false && $posMensaje !== false && $posUpload !== false,
            'subir() debe consultar la deuda, rechazar montos menores y conservar la subida del comprobante');

        if ($posResumen === false || $posMensaje === false || $posUpload === false) {
            return;
        }

        $this->assertTrue($posResumen < $posMensaje, 'La deuda debe consultarse antes de evaluar el rechazo');
        $this->assertTrue($posMensaje < $posUpload, 'El rechazo por deuda debe ocurrir antes de subir el archivo (sin comprobante huérfano)');
    }

    public function testReportarBloqueaMontoMenorADeudaAntesDeCrearPago(): void {
        $cuerpo = $this->extraerMetodo(dirname(__DIR__) . '/app/Controllers/PagoDirectoController.php', 'reportar');

        $this->assertTrue($cuerpo !== '', 'PagoDirectoController::reportar() debe existir');
        $this->assertStringContains('no puede ser menor a la deuda pendiente de la unidad', $cuerpo,
            'reportar() debe rechazar montos menores a la deuda pendiente de la unidad');

        // El mensaje de rechazo se busca con paréntesis para no confundirlo con el
        // comentario de la regla de negocio, que repite la misma frase.
        $posResumen = strpos($cuerpo, 'getResumenFinancieroUnidad');
        $posMensaje = strpos($cuerpo, 'no puede ser menor a la deuda pendiente de la unidad (');
        $posUpload  = strpos($cuerpo, 'new FileUploader()');
        $posCrear   = strpos($cuerpo, 'crearPago(');

        $this->assertTrue($posResumen !== false && $posMensaje !== false && $posUpload !== false && $posCrear !== false,
            'reportar() debe consultar la deuda, rechazar montos menores, subir el comprobante y registrar el pago');

        if ($posResumen === false || $posMensaje === false || $posUpload === false || $posCrear === false) {
            return;
        }

        $this->assertTrue($posResumen < $posMensaje, 'La deuda debe consultarse antes de evaluar el rechazo');
        $this->assertTrue($posMensaje < $posUpload, 'El rechazo por deuda debe ocurrir antes de subir el archivo');
        $this->assertTrue($posMensaje < $posCrear, 'El rechazo por deuda debe ocurrir antes de crear el pago');
    }

    public function testVistasIncluyenGuardaCliente(): void {
        $vistaSubir = (string)file_get_contents(dirname(__DIR__) . '/app/views/pagos/residente/subir.php');
        $vistaDirecto = (string)file_get_contents(dirname(__DIR__) . '/app/views/pago_directo/index.php');

        $this->assertStringContains('errorMontoDeuda', $vistaSubir,
            'La vista de residente debe incluir el mensaje de monto menor a la deuda');
        $this->assertStringContains('data-deuda', $vistaSubir,
            'El formulario de residente debe exponer la deuda vigente en data-deuda');
        $this->assertStringContains("formPago.addEventListener('submit'", $vistaSubir,
            'La vista de residente debe bloquear en cliente el envío con monto menor a la deuda');

        $this->assertStringContains('errorMontoDeuda', $vistaDirecto,
            'La vista pública debe incluir el mensaje de monto menor a la deuda');
        $this->assertStringContains('dataset.deuda', $vistaDirecto,
            'La vista pública debe leer la deuda vigente desde el formulario');
        $this->assertStringContains("formPagoDirecto.addEventListener('submit'", $vistaDirecto,
            'La vista pública debe bloquear en cliente el envío con monto menor a la deuda');
    }
}
