<?php
namespace App\Services;

// FPDF no está en vendor/: se embebe en el repo como archivo único (sin Composer).
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

/**
 * Genera el PDF oficial de la Carta de Deuda replicando el formato institucional
 * (app/views/admin/reportes/carta_deuda.php): membrete con logo, fecha a la derecha,
 * destinatario, cuerpo con monto en letras, tabla mensual agrupada, párrafo legal,
 * medios de pago, firma de la administradora y caja "Recibido Por".
 *
 * FPDF con fuentes core (Helvetica) usa Windows-1252 y NO convierte UTF-8: todo
 * texto pasa por txt() antes de dibujarse. La '✓' no existe en cp1252; la lista de
 * pagos usa '•' (0x95) en su lugar.
 */
class CartaDeudaPdfService {

    /** Ancho de la hoja Letter en milímetros. */
    private const ANCHO_HOJA = 215.9;

    /** Margen uniforme de la carta en milímetros. */
    private const MARGEN = 20.0;

    /** Alto del logo del membrete en milímetros (el ancho conserva la proporción). */
    private const ALTO_LOGO = 16.0;

    /** Ancho de la tabla mensual de deuda en milímetros (centrada en la página). */
    private const ANCHO_TABLA = 110.0;

    /**
     * Genera la carta de deuda en PDF y retorna el documento como string binario.
     *
     * @param array $detalle Estructura que entrega ReportesModel::obtenerDetalleDeudaUnidad():
     *                       ['unidad' => [...], 'facturas' => [...], 'total_deuda' => float]
     * @return string Documento PDF completo.
     */
    public function generar(array $detalle): string {
        $unidad     = $detalle['unidad'] ?? [];
        $facturas   = $detalle['facturas'] ?? [];
        $totalDeuda = floatval($detalle['total_deuda'] ?? 0);

        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
        $pdf->SetAutoPageBreak(true, self::MARGEN);
        $pdf->AddPage();

        $this->dibujarMembrete($pdf);
        $this->dibujarFecha($pdf);
        $this->dibujarDestinatario($pdf, $unidad);

        $gruposFacturas = agruparFacturasPorMes($facturas);

        if (empty($gruposFacturas)) {
            $pdf->SetFont('Helvetica', '', 10.5);
            $pdf->MultiCell(0, 5, $this->txt('Esta unidad no posee facturas vencidas registradas.'), 0, 'J');
        } else {
            $this->dibujarCuerpo($pdf, count($gruposFacturas), $totalDeuda);
            $this->dibujarTablaMensual($pdf, $gruposFacturas, $totalDeuda);
        }

        $this->dibujarParrafoLegal($pdf);
        $this->dibujarMediosDePago($pdf);
        $this->dibujarPie($pdf);

        return $pdf->Output('S');
    }

    /**
     * Membrete: logo a la izquierda y bloque institucional centrado, con doble filete.
     */
    private function dibujarMembrete(\FPDF $pdf): void {
        $y = self::MARGEN + 2;

        $logo = BASE_PATH . '/public/img/logo_condominio.png';
        if (is_file($logo)) {
            $pdf->Image($logo, self::MARGEN, $y, 0, self::ALTO_LOGO);
        }

        $pdf->SetXY(self::MARGEN, $y);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 6.5, $this->txt('CONDOMINIO CONJUNTO LAS MESETAS'), 0, 1, 'C');
        $pdf->SetX(self::MARGEN);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5.5, $this->txt('RIF. J-400577870'), 0, 1, 'C');
        $pdf->SetX(self::MARGEN);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, $this->txt('Av. Ppal. de Morón. Oficina Administrativa'), 0, 1, 'C');
        $pdf->SetX(self::MARGEN);
        $pdf->Cell(0, 5, $this->txt('Urb. Las Mesetas. Teléf. 0271-2259170 - Valera, Edo. Trujillo'), 0, 1, 'C');

        // Doble filete: línea fina superior + línea gruesa inferior.
        $yRegla = max($pdf->GetY(), $y + self::ALTO_LOGO) + 2;
        $pdf->SetLineWidth(0.25);
        $pdf->Line(self::MARGEN, $yRegla, self::ANCHO_HOJA - self::MARGEN, $yRegla);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(self::MARGEN, $yRegla + 1.2, self::ANCHO_HOJA - self::MARGEN, $yRegla + 1.2);
        $pdf->SetLineWidth(0.2);
        $pdf->SetY($yRegla + 4);
    }

    /**
     * Fecha de emisión alineada a la derecha.
     */
    private function dibujarFecha(\FPDF $pdf): void {
        $pdf->SetFont('Helvetica', '', 11);
        $fecha = 'Valera, ' . date('j') . ' de ' . nombreMes(date('n')) . ' de ' . date('Y');
        $pdf->Cell(0, 6, $this->txt($fecha), 0, 1, 'R');
    }

    /**
     * Destinatario: rótulo, nombre en mayúsculas/negrita, unidad y PRESENTE.-
     */
    private function dibujarDestinatario(\FPDF $pdf, array $unidad): void {
        $nombre = trim((string)($unidad['propietario_nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Propietario / Residente';
        }
        $numero   = (string)($unidad['unidad_numero'] ?? '');
        $edificio = (string)($unidad['edificio_nombre'] ?? '');

        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(0, 5.2, $this->txt('Ciudadana (o):'), 0, 1);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5.2, $this->txt(mb_strtoupper($nombre, 'UTF-8')), 0, 1);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(0, 5.2, $this->txt('Propietario(a) del Apto. ' . $numero . '. Del Edificio "' . $edificio . '".'), 0, 1);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5.2, $this->txt('PRESENTE.-'), 0, 1);
    }

    /**
     * Cuerpo justificado con la mora en meses y el monto en letras (texto de la vista web).
     */
    private function dibujarCuerpo(\FPDF $pdf, int $cantidadMeses, float $totalDeuda): void {
        $textoMeses = ($cantidadMeses === 1) ? '1 mes vencido' : $cantidadMeses . ' meses vencidos';

        $texto = 'Nos es grato dirigirnos a usted, para saludarle y a la vez notificarle que a la fecha, '
            . 'tiene una morosidad de ' . $textoMeses . ' de condominio, que corresponden a gastos comunes '
            . '(pago de servicios básicos, vigilancia, mantenimiento de equipos y áreas comunes de nuestra '
            . 'urbanización y salario de trabajadores, pues han sido consumidos y disfrutados en su totalidad), '
            . 'por un monto de ' . montoEnLetras($totalDeuda) . ' (' . formatearMoneda($totalDeuda) . '). '
            . 'Monto total se detalla a continuación:';

        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5, $this->txt($texto), 0, 'J');
    }

    /**
     * Tabla mensual centrada: Mes | Monto Bs., con la fila Total en negrita.
     *
     * @param array $gruposFacturas Grupos de agruparFacturasPorMes().
     */
    private function dibujarTablaMensual(\FPDF $pdf, array $gruposFacturas, float $totalDeuda): void {
        $x          = (self::ANCHO_HOJA - self::ANCHO_TABLA) / 2;
        $anchoMes   = 70.0;
        $anchoMonto = self::ANCHO_TABLA - $anchoMes;

        $pdf->Ln(3);
        $pdf->SetX($x);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell($anchoMes, 6, $this->txt('Mes'), 1, 0, 'C');
        $pdf->Cell($anchoMonto, 6, $this->txt('Monto Bs.'), 1, 1, 'C');

        $pdf->SetFont('Helvetica', '', 10.5);
        $esPrimeraFila = true;
        foreach ($gruposFacturas as $grupo) {
            $etiqueta = ($esPrimeraFila ? 'Saldo de ' : '') . nombreMes($grupo['mes']) . '-' . $grupo['anio'];
            $pdf->SetX($x);
            $pdf->Cell($anchoMes, 6, $this->txt($etiqueta), 1, 0, 'L');
            $pdf->Cell($anchoMonto, 6, $this->txt(formatearMonto($grupo['total'])), 1, 1, 'R');
            $esPrimeraFila = false;
        }

        $pdf->SetX($x);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell($anchoMes, 6, $this->txt('Total'), 1, 0, 'L');
        $pdf->Cell($anchoMonto, 6, $this->txt(formatearMonto($totalDeuda)), 1, 1, 'R');
    }

    /**
     * Párrafo legal justificado (mismo texto de la vista web).
     */
    private function dibujarParrafoLegal(\FPDF $pdf): void {
        $texto = 'Nos gustaría que usted gentilmente cancele lo más pronto posible la totalidad de la deuda '
            . 'indicada en el párrafo anterior; de lo contrario, nos veremos en la necesidad de pasar a '
            . 'consultoría jurídica su deuda y/o a competencia de las Juezas y Jueces de Paz Comunal. '
            . 'Artículo 8, Literal 2 y el gasto de honorarios profesionales correrá por su cuenta.';

        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5, $this->txt($texto), 0, 'J');
    }

    /**
     * Medios de pago con viñeta '•' (cp1252) y la nota de recibo.
     */
    private function dibujarMediosDePago(\FPDF $pdf): void {
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5, $this->txt('Los medios establecidos para su pago son los siguientes:'), 0, 'L');
        $pdf->Cell(0, 5, $this->txt('• Cuenta Corriente Banco de Venezuela Nº. 0102-0373-2200-0010-2432'), 0, 1, 'L');
        $pdf->Cell(0, 5, $this->txt('• Divisa (Tasa del BCV del día de cancelación)'), 0, 1, 'L');
        $pdf->Cell(0, 5, $this->txt('• Enviar su pago al correo electrónico: pagosjdclm@gmail.com'), 0, 1, 'L');
        $pdf->Ln(1);
        $pdf->MultiCell(0, 5, $this->txt('Sírvase dar recibo al encargado de practicar esta Notificación.'), 0, 'L');
    }

    /**
     * Pie: firma de la administradora (izquierda) y caja "Recibido Por:" (derecha).
     */
    private function dibujarPie(\FPDF $pdf): void {
        $yPie = $pdf->GetY() + 8;

        $pdf->SetXY(self::MARGEN, $yPie);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(60, 5.2, $this->txt('Ninoska Moncada'), 0, 1);
        $pdf->SetX(self::MARGEN);
        $pdf->Cell(60, 5.2, $this->txt('Administradora'), 0, 1);

        // Caja de recibo como tabla con bordes (mismas filas que la vista web).
        $anchoCaja = 85.0;
        $x = self::ANCHO_HOJA - self::MARGEN - $anchoCaja;
        $pdf->SetXY($x, $yPie - 2);
        $pdf->Cell($anchoCaja, 8, $this->txt('Recibido Por:'), 1, 1);
        $pdf->SetX($x);
        $pdf->Cell($anchoCaja, 8, $this->txt('Nombres y Apellidos:'), 1, 1);
        $pdf->SetX($x);
        $pdf->Cell($anchoCaja, 8, $this->txt('Cédula de Identidad Nº'), 1, 1);
        $pdf->SetX($x);
        $pdf->Cell($anchoCaja / 2, 8, $this->txt('Fecha:'), 1, 0);
        $pdf->Cell($anchoCaja / 2, 8, $this->txt('Hora:'), 1, 1);
    }

    /**
     * Convierte UTF-8 a Windows-1252 (codificación de las fuentes core de FPDF).
     */
    private function txt(string $texto): string {
        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }
}
