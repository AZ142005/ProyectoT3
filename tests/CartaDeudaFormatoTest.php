<?php
namespace Tests;

/**
 * Verifica el formato de la notificación oficial de deuda
 * (app/views/admin/reportes/carta_deuda.php): membrete institucional, fecha,
 * destinatario, cuerpo con monto en letras, tabla mensual agrupada, párrafo
 * legal, medios de pago, firma de la administradora y caja "Recibido Por".
 *
 * La vista se renderiza por include con buffer para revisar su HTML real.
 */
class CartaDeudaFormatoTest extends TestCase {

    /**
     * Datos de fixture equivalentes a los que entrega ReporteController::generarCartaDeuda().
     */
    private function datosBase(): array {
        return [
            'unidad' => [
                'unidad_id'            => 1,
                'unidad_numero'        => '56',
                'edificio_nombre'      => 'EL CHAGUARAMO',
                'propietario_nombre'   => 'Juan Pérez',
                'propietario_cedula'   => 'V-12345678',
                'propietario_telefono' => '0414-7778899',
            ],
            'facturas' => [
                [
                    'id'                => 1,
                    'numero_factura'    => 'FAC-1',
                    'mes'               => 4,
                    'anio'              => 2026,
                    'fecha_vencimiento' => '2026-04-15',
                    'monto_total'       => '5000.00',
                    'saldo'             => '5000.00',
                    'dias_mora'         => 30,
                ],
                [
                    'id'                => 2,
                    'numero_factura'    => 'FAC-2',
                    'mes'               => 5,
                    'anio'              => 2026,
                    'fecha_vencimiento' => '2026-05-15',
                    'monto_total'       => '107196.84',
                    'saldo'             => '107196.84',
                    'dias_mora'         => 10,
                ],
            ],
            'totalDeuda'     => 112196.84,
            'enlaceWhatsapp' => 'https://api.whatsapp.com/send?phone=584147778899&text=Hola',
            'analisisTel'    => ['es_movil' => true, 'telefono' => '584147778899', 'mensaje' => null],
            'title'          => 'Carta Oficial de Deuda - Unidad 56',
        ];
    }

    /**
     * Renderiza la vista de la carta con los datos indicados y retorna su HTML.
     */
    private function renderizarCarta(array $sobrescribir = []): string {
        $datos = array_merge($this->datosBase(), $sobrescribir);

        $_SESSION = [];
        $nivelBase = ob_get_level();
        ob_start();
        try {
            extract($datos);
            include BASE_PATH . '/app/views/admin/reportes/carta_deuda.php';
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBase) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    public function testMembreteInstitucional(): void {
        $html = $this->renderizarCarta();

        $this->assertStringContains('/img/logo_condominio.png', $html,
            "El membrete debe incluir el logo del condominio");
        $this->assertStringContains('CONDOMINIO CONJUNTO LAS MESETAS', $html,
            "El membrete debe mostrar el nombre institucional");
        $this->assertStringContains('RIF. J-400577870', $html,
            "El membrete debe mostrar el RIF institucional");
        $this->assertStringContains('0271-2259170', $html,
            "El membrete debe mostrar el teléfono institucional");
    }

    public function testDestinatarioYCuerpoConMontoEnLetras(): void {
        $html = $this->renderizarCarta();

        $this->assertStringContains('Ciudadana (o):', $html,
            "La carta debe encabezar el destinatario con 'Ciudadana (o):'");
        $this->assertStringContains('JUAN PÉREZ', $html,
            "El nombre del propietario debe ir en mayúsculas");
        $this->assertStringContains('PRESENTE.-', $html,
            "La carta debe incluir el rótulo PRESENTE.-");
        $this->assertStringContains('morosidad de 2 meses vencidos', $html,
            "El cuerpo debe contar los 2 grupos mes/año como meses vencidos");
        $this->assertStringContains('CIENTO DOCE MIL CIENTO NOVENTA Y SEIS BOLÍVARES CON 84/100', $html,
            "El cuerpo debe expresar el total en letras con centavos");
        $this->assertStringContains('112.196,84', $html,
            "El cuerpo debe expresar el total en cifras con formato venezolano");
    }

    public function testTablaMensualAgrupadaPorMesYAnio(): void {
        $html = $this->renderizarCarta();

        $this->assertStringContains('Saldo de Abril-2026', $html,
            "La primera fila de la tabla debe rotularse 'Saldo de {Mes}-{año}'");
        $this->assertStringContains('Mayo-2026', $html,
            "Las filas siguientes solo llevan '{Mes}-{año}'");
        $this->assertStringContains('Total', $html,
            "La tabla debe cerrar con la fila Total");
        $this->assertStringContains('5.000,00', $html,
            "La tabla debe mostrar el saldo agrupado de abril con formato venezolano");
        $this->assertStringContains('107.196,84', $html,
            "La tabla debe mostrar el saldo agrupado de mayo con formato venezolano");
    }

    public function testTextoLegalYMediosDePago(): void {
        $html = $this->renderizarCarta();

        $this->assertStringContains('Juezas y Jueces de Paz Comunal', $html,
            "La carta debe incluir el párrafo legal con la competencia de Paz Comunal");
        $this->assertStringContains('0102-0373-2200-0010-2432', $html,
            "Los medios de pago deben incluir la cuenta del Banco de Venezuela");
        $this->assertStringContains('pagosjdclm@gmail.com', $html,
            "Los medios de pago deben incluir el correo de pagos");
    }

    public function testFirmaYCajaDeRecibo(): void {
        $html = $this->renderizarCarta();

        $this->assertStringContains('Ninoska Moncada', $html,
            "El pie debe mostrar el nombre de la administradora");
        $this->assertStringContains('Recibido Por:', $html,
            "El pie debe incluir la caja de recibo con 'Recibido Por:'");
    }

    public function testBotonWhatsAppConEnlace(): void {
        $html = $this->renderizarCarta();

        $this->assertStringContains('WhatsApp (1-Clic)', $html,
            "Con enlace disponible debe mostrarse el botón WhatsApp (1-Clic)");
    }

    public function testBotonWhatsAppIncompatible(): void {
        $html = $this->renderizarCarta(['enlaceWhatsapp' => null]);

        $this->assertStringContains('WhatsApp Incompatible', $html,
            "Sin enlace de WhatsApp debe mostrarse el botón deshabilitado");
        $this->assertStringContains('Número no móvil', $html,
            "El botón deshabilitado debe explicar el motivo en su title");
    }

    public function testSinFacturasMuestraMensajeYSinTablaNumerica(): void {
        $html = $this->renderizarCarta(['facturas' => [], 'totalDeuda' => 0]);

        $this->assertStringContains('Esta unidad no posee facturas vencidas registradas.', $html,
            "Sin facturas la carta debe indicarlo expresamente");
        $this->assertFalse(str_contains($html, '<table class="tabla-montos">'),
            "Sin facturas no debe renderizarse la tabla numérica");
        $this->assertStringContains('Ninoska Moncada', $html,
            "Sin facturas el resto del documento debe mantenerse (firma)");
    }

    public function testDatosDinamicosSeEscapan(): void {
        $html = $this->renderizarCarta([
            'unidad' => array_merge($this->datosBase()['unidad'], [
                'propietario_nombre' => 'Juan <b>Pérez</b>',
            ]),
        ]);

        $this->assertStringContains('JUAN &lt;B&gt;PÉREZ&lt;/B&gt;', $html,
            "El nombre del propietario debe escaparse con e()");
        $this->assertFalse(str_contains($html, '<B>PÉREZ</B>'),
            "El HTML crudo del propietario no debe aparecer en la carta");
    }
}
