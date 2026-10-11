<?php
namespace Tests;

use App\Services\CartaDeudaPdfService;

/**
 * Generación del PDF oficial de la Carta de Deuda con FPDF embebido.
 *
 * Verifica la estructura mínima del documento, el caso sin facturas y la
 * codificación Windows-1252: los acentos deben quedar como byte cp1252
 * (é = 0xE9) y nunca como el par UTF-8 (0xC3 0xA9).
 */
class CartaDeudaPdfTest extends TestCase {

    /**
     * Detalle de deuda equivalente al que entrega ReportesModel::obtenerDetalleDeudaUnidad().
     */
    private function detalleBase(array $sobrescribir = []): array {
        $base = [
            'unidad' => [
                'unidad_id'          => 1,
                'unidad_numero'      => '56',
                'edificio_nombre'    => 'EL CHAGUARAMO',
                'propietario_nombre' => 'José Pérez',
                'propietario_cedula' => 'V-12345678',
            ],
            'facturas' => [
                ['mes' => 4, 'anio' => 2026, 'saldo' => '5000.00'],
                ['mes' => 5, 'anio' => 2026, 'saldo' => '107196.84'],
            ],
            'total_deuda' => 112196.84,
        ];

        return array_merge($base, $sobrescribir);
    }

    /**
     * Infla los streams deflate del PDF y concatena solo los de contenido de página.
     *
     * Se resuelven por la referencia /Contents N 0 R de cada página: los streams
     * de imagen (logo) también son deflate y sus bytes de píxeles crudos podrían
     * contener secuencias espurias si se inflaran junto con el texto.
     */
    private function inflarStreamsDeTexto(string $pdf): string {
        $contenido = '';

        if (!preg_match_all('#/Contents (\d+) 0 R#', $pdf, $referencias)) {
            return $contenido;
        }

        foreach (array_unique($referencias[1]) as $objeto) {
            $patron = '#(?<!\d)' . preg_quote($objeto, '#') . ' 0 obj.*?\bstream\r?\n(.*?)\r?\nendstream#s';
            if (!preg_match($patron, $pdf, $coincidencia)) {
                continue;
            }

            try {
                $inflado = gzuncompress($coincidencia[1]);
            } catch (\Throwable $e) {
                continue; // Stream sin deflate: se ignora.
            }

            if (is_string($inflado)) {
                $contenido .= $inflado;
            }
        }

        return $contenido;
    }

    public function testGeneraPdfValidoDeUnaPagina(): void {
        $pdf = (new CartaDeudaPdfService())->generar($this->detalleBase());

        $this->assertTrue(str_starts_with($pdf, '%PDF-'),
            'El documento debe iniciar con la firma %PDF-');
        $this->assertTrue(str_ends_with(rtrim($pdf), '%%EOF'),
            'El documento debe cerrar con el marcador %%EOF');
        $this->assertStringContains('/Type /Page', $pdf,
            'El PDF debe declarar al menos una página');
        $this->assertGreaterThan(3000, strlen($pdf),
            'El PDF generado debe superar los 3 KB de contenido');

        // Una página Letter: un solo objeto de página (excluye /Type /Pages).
        $paginas = preg_match_all('#/Type\s*/Page(?![s])#', $pdf);
        $this->assertEquals(1, $paginas,
            'El fixture de dos meses debe caber en una sola página Letter');
    }

    public function testSinFacturasNoLanzaYDescribeLaSituacion(): void {
        $pdf = (new CartaDeudaPdfService())->generar($this->detalleBase([
            'facturas'    => [],
            'total_deuda' => 0,
        ]));

        $this->assertTrue(str_starts_with($pdf, '%PDF-'),
            'Sin facturas debe generarse igualmente un PDF válido');

        $texto = $this->inflarStreamsDeTexto($pdf);
        $this->assertStringContains('no posee facturas vencidas', $texto,
            'Sin facturas el cuerpo debe indicarlo expresamente');
    }

    public function testAcentosComoByteCp1252YNoParUtf8(): void {
        $pdf = (new CartaDeudaPdfService())->generar($this->detalleBase());
        $texto = $this->inflarStreamsDeTexto($pdf);

        // 'José Pérez' en mayúsculas → 'JOSÉ PÉREZ': ambas É = 0xC9 en cp1252.
        $this->assertTrue(str_contains($texto, 'JOS' . "\xC9" . ' P' . "\xC9" . 'REZ'),
            'El nombre con acentos debe aparecer codificado en Windows-1252');
        $this->assertFalse(str_contains($texto, "\xC3\xA9"),
            'No debe aparecer el par UTF-8 de é (doble codificación)');

        $eCp1252 = mb_convert_encoding('é', 'Windows-1252', 'UTF-8');
        $this->assertStringContains('C' . $eCp1252 . 'dula de Identidad', $texto,
            'La caja de recibo debe codificar Cédula con el byte cp1252');
    }
}
