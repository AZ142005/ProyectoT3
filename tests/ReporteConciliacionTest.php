<?php
namespace Tests;

use App\Models\ConciliacionModel;
use App\Services\ReporteConciliacionService;

/**
 * Pruebas T2 — servicio de reportes históricos de conciliación (PDF/CSV/JSON).
 *
 * No requieren base de datos: el modelo se simula con una subclase anónima y el
 * servicio se ejercita contra directorios temporales únicos bajo sys_get_temp_dir().
 */
class ReporteConciliacionTest extends TestCase {

    private function datosFixture(): array {
        $conciliados = [
            [
                'id'                   => 1,
                'lote_importacion'     => 'LOTE-T2-2026',
                'banco'                => 'Banco de Venezuela',
                'fecha_movimiento'     => '2026-01-10',
                'referencia_bancaria'  => 'REF-CONC-001',
                'referencia'           => 'REF-CONC-001',
                'monto'                => 1000.00,
                'actualizado_en'       => '2026-01-11 10:00:00',
                'pago_id'              => 101,
                'fecha_pago'           => '2026-01-10',
                'referencia_pago'      => 'PAGO-001',
                'unidad_numero'        => 'A-01',
                'edificio_nombre'      => 'Torre A',
                'residente_nombre'     => 'José Muñoz',
                'residente_cedula'     => 'V-11111111',
            ],
            [
                'id'                   => 2,
                'lote_importacion'     => 'LOTE-T2-2026',
                'banco'                => 'Banco de Venezuela',
                'fecha_movimiento'     => '2026-01-15',
                'referencia_bancaria'  => 'REF-CONC-002',
                'referencia'           => 'REF-CONC-002',
                'monto'                => 250.50,
                'actualizado_en'       => '2026-01-16 09:30:00',
                'pago_id'              => 102,
                'fecha_pago'           => '2026-01-15',
                'referencia_pago'      => 'PAGO-002',
                'unidad_numero'        => 'B-02',
                'edificio_nombre'      => 'Torre B',
                'residente_nombre'     => '=SUM(A1:A2)',
                'residente_cedula'     => 'V-22222222',
            ],
        ];

        $disponibles = [
            [
                'id'                  => 21,
                'lote_importacion'    => 'LOTE-T2-2026',
                'fecha_movimiento'    => '2026-01-20',
                'referencia_bancaria' => 'REF-DISP-001',
                'descripcion'         => 'Transferencia recibida',
                'descripcion_banco'   => 'Transferencia recibida',
                'monto'               => 300.25,
            ],
            [
                'id'                  => 22,
                'lote_importacion'    => 'LOTE-T2-2026',
                'fecha_movimiento'    => '2026-01-21',
                'referencia_bancaria' => 'REF-DISP-002',
                'descripcion'         => "Pago\nparcial",
                'descripcion_banco'   => "Pago\nparcial",
                'monto'               => 99.75,
            ],
        ];

        $pendientes = [
            [
                'origen_tabla'     => 'pago',
                'id'               => 301,
                'monto'            => 120.00,
                'fecha_pago'       => '2026-01-25',
                'referencia'       => 'PAGO-PEND-001',
                'unidad_numero'    => 'A-01',
                'residente_nombre' => 'José Muñoz',
            ],
            [
                'origen_tabla'     => 'comprobante',
                'id'               => 302,
                'monto'            => 80.50,
                'fecha_pago'       => '2026-01-26',
                'referencia'       => 'COMP-PEND-002',
                'unidad_numero'    => 'B-02',
                'residente_nombre' => 'María Pérez',
            ],
        ];

        return [
            'lote'               => 'LOTE-T2-2026',
            'banco'              => 'Banco de Venezuela',
            'fecha_importacion'  => '2026-01-01 08:00:00',
            'fecha_ultima_carga' => '2026-01-01 09:00:00',
            'total_movimientos'  => 6,
            'fecha_corte'        => '2026-01-31 23:59:59',
            'generado_en'        => '2026-02-01 00:00:00',
            'conteos'            => ['conciliados' => 2, 'disponibles' => 2, 'pendientes' => 2],
            'totales'            => ['conciliados' => 1250.50, 'disponibles' => 400.00, 'pendientes' => 200.50],
            'conciliados'        => $conciliados,
            'disponibles'        => $disponibles,
            'pendientes'         => $pendientes,
        ];
    }

    private function crearDirectorioTemporal(): string {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reporte_conciliacion_' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal de prueba: ' . $dir);
        }
        return $dir;
    }

    private function eliminarDirectorio(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $ruta = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($ruta)) {
                $this->eliminarDirectorio($ruta);
            } else {
                @unlink($ruta);
            }
        }

        @rmdir($dir);
    }

    private function escribirReporteFalso(
        string $dir,
        string $base,
        array|string|null $metadatos,
        ?string $pdf,
        ?string $csv
    ): void {
        if ($pdf !== null) {
            file_put_contents($dir . DIRECTORY_SEPARATOR . $base . '.pdf', $pdf);
        }
        if ($csv !== null) {
            file_put_contents($dir . DIRECTORY_SEPARATOR . $base . '.csv', $csv);
        }
        if ($metadatos !== null) {
            $contenido = is_string($metadatos) ? $metadatos : (string)json_encode($metadatos);
            file_put_contents($dir . DIRECTORY_SEPARATOR . $base . '.json', $contenido);
        }
    }

    /**
     * Infla los streams deflate de contenido de página para inspeccionar el texto.
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
                continue;
            }

            if (is_string($inflado)) {
                $contenido .= $inflado;
            }
        }

        return $contenido;
    }

    private function servicioConFixture(array $datos, ?\ArrayObject $llamadas = null): ReporteConciliacionService {
        $modelo = new class($datos, $llamadas) extends ConciliacionModel {
            private array $datos;
            private ?\ArrayObject $llamadas;

            public function __construct(array $datos, ?\ArrayObject $llamadas) {
                $this->datos = $datos;
                $this->llamadas = $llamadas;
            }

            public function obtenerDatosLote(string $lote): ?array {
                if ($this->llamadas !== null) {
                    $this->llamadas['lote'] = $lote;
                }

                return [
                    'lote_importacion'    => $lote,
                    'banco'               => $this->datos['banco'],
                    'fecha_importacion'   => $this->datos['fecha_importacion'],
                    'fecha_ultima_carga'  => $this->datos['fecha_ultima_carga'],
                    'total_movimientos'   => $this->datos['total_movimientos'],
                ];
            }

            public function obtenerMovimientosConciliados(?string $fechaLimite = null): array {
                if ($this->llamadas !== null) {
                    $this->llamadas['limite'] = $fechaLimite;
                }

                return $this->datos['conciliados'];
            }

            public function obtenerDisponiblesLote(string $lote): array {
                if ($this->llamadas !== null) {
                    $this->llamadas['lote_disponibles'] = $lote;
                }

                return $this->datos['disponibles'];
            }

            public function obtenerTodosPagosPendientes(): array {
                return $this->datos['pendientes'];
            }
        };

        return new ReporteConciliacionService($modelo);
    }

    private function ventanaFixture(): array {
        $datos = $this->datosFixture();

        $datos['lotes'] = [
            [
                'lote_importacion'   => 'LOTE-VENT-A',
                'banco'              => 'Banco Único',
                'fecha_importacion'  => '2026-01-01 08:00:00',
                'fecha_ultima_carga' => '2026-01-01 09:00:00',
                'total_movimientos'  => 3,
            ],
            [
                'lote_importacion'   => 'LOTE-VENT-B',
                'banco'              => 'Banco Único',
                'fecha_importacion'  => '2026-02-01 08:00:00',
                'fecha_ultima_carga' => '2026-02-01 09:00:00',
                'total_movimientos'  => 2,
            ],
        ];

        $datos['disponibles_por_lote'] = [
            'LOTE-VENT-A' => [
                [
                    'id'                  => 501,
                    'lote_importacion'    => 'LOTE-VENT-A',
                    'fecha_movimiento'    => '2026-02-10',
                    'referencia_bancaria' => 'REF-VENT-A1',
                    'descripcion'         => 'Disponible ventana A1',
                    'monto'               => 120.00,
                ],
                [
                    'id'                  => 502,
                    'lote_importacion'    => 'LOTE-VENT-A',
                    'fecha_movimiento'    => '2026-02-11',
                    'referencia_bancaria' => 'REF-VENT-A2',
                    'descripcion'         => 'Disponible ventana A2',
                    'monto'               => 30.25,
                ],
            ],
            'LOTE-VENT-B' => [
                [
                    'id'                  => 503,
                    'lote_importacion'    => 'LOTE-VENT-B',
                    'fecha_movimiento'    => '2026-02-12',
                    'referencia_bancaria' => 'REF-VENT-B1',
                    'descripcion'         => 'Disponible ventana B1',
                    'monto'               => 49.75,
                ],
            ],
        ];

        return $datos;
    }

    private function servicioVentanaConFixture(array $datos, ?\ArrayObject $llamadas = null): ReporteConciliacionService {
        $modelo = new class($datos, $llamadas) extends ConciliacionModel {
            private array $datos;
            private ?\ArrayObject $llamadas;

            public function __construct(array $datos, ?\ArrayObject $llamadas) {
                $this->datos = $datos;
                $this->llamadas = $llamadas;
            }

            public function obtenerDatosLote(string $lote): ?array {
                if ($this->llamadas !== null) {
                    $lotesDatos = $this->llamadas['lotes_datos'] ?? [];
                    $lotesDatos[] = $lote;
                    $this->llamadas['lotes_datos'] = $lotesDatos;
                }

                foreach (($this->datos['lotes'] ?? []) as $fila) {
                    if (($fila['lote_importacion'] ?? '') === $lote) {
                        return $fila;
                    }
                }

                return null;
            }

            public function obtenerDisponiblesLote(string $lote): array {
                if ($this->llamadas !== null) {
                    $lotesDisponibles = $this->llamadas['lotes_disponibles'] ?? [];
                    $lotesDisponibles[] = $lote;
                    $this->llamadas['lotes_disponibles'] = $lotesDisponibles;
                }

                return $this->datos['disponibles_por_lote'][$lote] ?? [];
            }

            public function obtenerMovimientosConciliados(?string $fechaLimite = null): array {
                return $this->datos['conciliados'];
            }

            public function obtenerTodosPagosPendientes(): array {
                return $this->datos['pendientes'];
            }
        };

        return new ReporteConciliacionService($modelo);
    }

    public function testCsvIncluyeBomSeccionesDelimitadorYTotales(): void {
        $servicio = new ReporteConciliacionService();
        $csv = $servicio->generarCsv($this->datosFixture());

        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"), 'El CSV debe iniciar con BOM UTF-8');
        $this->assertStringContains('MOVIMIENTOS CONCILIADOS', $csv, 'El CSV debe incluir la sección de conciliados');
        $this->assertStringContains('MOVIMIENTOS DISPONIBLES SIN RECLAMAR', $csv, 'El CSV debe incluir la sección de disponibles');
        $this->assertStringContains('PAGOS REPORTADOS PENDIENTES DE CONCILIAR', $csv, 'El CSV debe incluir la sección de pendientes');
        $this->assertStringContains('Fecha;Unidad;Residente;Referencia;Lote;Monto', $csv, 'La cabecera de conciliados debe usar punto y coma');
        $this->assertStringContains('Fecha;Lote;Referencia;Descripción;Monto', $csv, 'La cabecera de disponibles debe incluir el lote y usar punto y coma');
        $this->assertStringContains('Fecha;Unidad;Residente;Referencia;Origen;Monto', $csv, 'La cabecera de pendientes debe usar punto y coma');
        $this->assertStringContains('2026-01-20;LOTE-T2-2026;REF-DISP-001;', $csv, 'La fila de disponibles debe identificar su lote');
        $this->assertStringContains("\t=SUM(A1:A2)", $csv, 'Una celda que inicia con = debe neutralizarse con tabulador');
        $this->assertStringContains('1.250,50', $csv, 'El total de conciliados debe formatearse con coma decimal');
        $this->assertStringContains('400,00', $csv, 'El total de disponibles debe formatearse con coma decimal');
        $this->assertStringContains('200,50', $csv, 'El total de pendientes debe formatearse con coma decimal');
        $this->assertStringContains('Pago parcial', $csv, 'Los saltos de línea deben reemplazarse por espacios');
        $this->assertFalse(str_contains($csv, "Pago\nparcial"), 'No debe quedar un salto de línea crudo dentro de una celda');
    }

    public function testCsvNeutralizaTodasLasVariantesDeInyeccion(): void {
        $servicio = new ReporteConciliacionService();
        $datos = $this->datosFixture();
        $datos['banco'] = '+SUMA(A1:A2)';
        $datos['lote'] = '-1+1';
        $datos['conciliados'][0]['residente_nombre'] = '@import';
        $datos['conciliados'][1]['residente_nombre'] = '=SUM(A1:A2)';
        $datos['disponibles'][0]['descripcion'] = "\tEncabezado";
        $datos['disponibles'][1]['descripcion'] = ';DROP TABLE pagos';
        $datos['pendientes'][0]['residente_nombre'] = "Linea\r\nCortada";

        $csv = $servicio->generarCsv($datos);

        $this->assertStringContains("\t+SUMA(A1:A2)", $csv, 'El prefijo + debe neutralizarse');
        $this->assertStringContains("\t-1+1", $csv, 'El prefijo - debe neutralizarse');
        $this->assertStringContains("\t@import", $csv, 'El prefijo @ debe neutralizarse');
        $this->assertStringContains("\t=SUM(A1:A2)", $csv, 'El prefijo = debe neutralizarse');
        $this->assertStringContains("\t\tEncabezado", $csv, 'Un tabulador inicial debe neutralizarse con otro tabulador');
        $this->assertStringContains("\t;DROP TABLE pagos", $csv, 'Un punto y coma inicial debe neutralizarse');
        $this->assertStringContains('Linea Cortada', $csv, 'CR y LF deben convertirse en espacios');
        $this->assertFalse(str_contains($csv, "Linea\r\nCortada"), 'No debe quedar un CRLF crudo en la celda');
    }

    public function testPdfEsValidoYNoFallaConAcentos(): void {
        $servicio = new ReporteConciliacionService();
        $pdf = $servicio->generarPdf($this->datosFixture());

        $this->assertTrue(str_starts_with($pdf, '%PDF'), 'El PDF debe iniciar con la firma %PDF');
        $this->assertTrue(str_ends_with(trim($pdf), '%%EOF'), 'El PDF debe cerrar con %%EOF');
        $this->assertGreaterThan(1000, strlen($pdf), 'El PDF debe tener contenido real');

        $texto = $this->inflarStreamsDeTexto($pdf);
        $munozCp1252 = mb_convert_encoding('Muñoz', 'Windows-1252', 'UTF-8');
        $this->assertStringContains($munozCp1252, $texto, 'El PDF debe contener el nombre acentuado en Windows-1252');
    }

    public function testConstruirUsaElModeloYCalculaConteosYtotales(): void {
        $datos = $this->datosFixture();
        $llamadas = new \ArrayObject();
        $servicio = $this->servicioConFixture($datos, $llamadas);

        $resultado = $servicio->construir('LOTE-T2-2026', '2026-01-31 23:59:59');

        $this->assertEquals('LOTE-T2-2026', $resultado['lote'], 'El lote debe provenir de obtenerDatosLote');
        $this->assertEquals('Banco de Venezuela', $resultado['banco'], 'El banco debe provenir de obtenerDatosLote');
        $this->assertEquals('2026-01-01 08:00:00', $resultado['fecha_importacion'], 'Debe conservar la fecha de importación');
        $this->assertEquals('2026-01-01 09:00:00', $resultado['fecha_ultima_carga'], 'Debe conservar la fecha de última carga');
        $this->assertEquals(6, $resultado['total_movimientos'], 'Debe conservar el total de movimientos del lote');
        $this->assertEquals('2026-01-31 23:59:59', $resultado['fecha_corte'], 'Debe respetar la fecha de corte recibida');
        $this->assertEquals(['conciliados' => 2, 'disponibles' => 2, 'pendientes' => 2], $resultado['conteos'], 'Los conteos deben corresponder a cada sección');
        $this->assertEquals(['conciliados' => 1250.50, 'disponibles' => 400.00, 'pendientes' => 200.50], $resultado['totales'], 'Los totales deben sumar los montos de cada sección');
        $this->assertEquals($datos['conciliados'], $resultado['conciliados'], 'Debe exponer los conciliados del modelo');
        $this->assertEquals($datos['disponibles'], $resultado['disponibles'], 'Debe exponer los disponibles del modelo');
        $this->assertEquals($datos['pendientes'], $resultado['pendientes'], 'Debe exponer los pendientes del modelo');
        $this->assertEquals('LOTE-T2-2026', $llamadas['lote'] ?? null, 'obtenerDatosLote debe recibir el lote solicitado');
        $this->assertNull($llamadas['limite'] ?? null, 'construir no debe filtrar conciliados por fecha_carga (desfase de zona horaria PHP/MySQL)');
        $this->assertEquals('LOTE-T2-2026', $llamadas['lote_disponibles'] ?? null, 'Los disponibles deben consultarse por el lote solicitado');
    }

    public function testConstruirLanzaExcepcionSiElLoteNoExiste(): void {
        $modelo = new class extends ConciliacionModel {
            public function obtenerDatosLote(string $lote): ?array {
                return null;
            }
        };

        $servicio = new ReporteConciliacionService($modelo);

        $this->expectException(\InvalidArgumentException::class, function () use ($servicio) {
            $servicio->construir('LOTE-INEXISTENTE');
        }, 'Un lote inexistente debe lanzar InvalidArgumentException');
    }

    public function testConstruirVentanaFusionaDisponiblesYCalculaMetadatos(): void {
        $datos = $this->ventanaFixture();
        $llamadas = new \ArrayObject();
        $servicio = $this->servicioVentanaConFixture($datos, $llamadas);

        $resultado = $servicio->construirVentana($datos['lotes'], '2026-03-31 23:59:59');

        $this->assertEquals('Ventana de 2 lotes', $resultado['lote'], 'La etiqueta debe indicar la cantidad de lotes de la ventana');
        $this->assertEquals('Banco Único', $resultado['banco'], 'El banco debe deduplicarse entre los lotes');
        $this->assertEquals('2026-01-01 08:00:00', $resultado['fecha_importacion'], 'La fecha de importación debe ser la mínima de la ventana');
        $this->assertEquals('2026-02-01 09:00:00', $resultado['fecha_ultima_carga'], 'La última carga debe ser la máxima de la ventana');
        $this->assertEquals(5, $resultado['total_movimientos'], 'El total de movimientos debe sumar los lotes de la ventana');
        $this->assertEquals('2026-03-31 23:59:59', $resultado['fecha_corte'], 'Debe respetar la fecha de corte recibida');
        $this->assertEquals(['conciliados' => 2, 'disponibles' => 3, 'pendientes' => 2], $resultado['conteos'], 'Los conteos deben fusionar disponibles y usar conciliados/pendientes globales');
        $this->assertEquals(['conciliados' => 1250.50, 'disponibles' => 200.00, 'pendientes' => 200.50], $resultado['totales'], 'Los totales deben sumar cada sección');
        $this->assertEquals(
            ['LOTE-VENT-A', 'LOTE-VENT-A', 'LOTE-VENT-B'],
            array_column($resultado['disponibles'], 'lote_importacion'),
            'Los disponibles deben fusionarse lote a lote conservando su lote de origen'
        );
        $this->assertEquals(['LOTE-VENT-A', 'LOTE-VENT-B'], $llamadas['lotes_disponibles'] ?? [], 'Debe consultar los disponibles de cada lote de la ventana');
        $this->assertEquals($datos['conciliados'], $resultado['conciliados'], 'Los conciliados son globales');
        $this->assertEquals($datos['pendientes'], $resultado['pendientes'], 'Los pendientes son globales');
    }

    public function testConstruirVentanaConUnSoloLoteUsaSuNombreYMetadatos(): void {
        $datos = $this->ventanaFixture();
        $llamadas = new \ArrayObject();
        $servicio = $this->servicioVentanaConFixture($datos, $llamadas);

        $resultado = $servicio->construirVentana(['LOTE-VENT-A'], '2026-03-31 23:59:59');

        $this->assertEquals('LOTE-VENT-A', $resultado['lote'], 'Con un solo lote la etiqueta debe ser su nombre');
        $this->assertEquals('Banco Único', $resultado['banco'], 'El banco debe resolverse desde los metadatos del lote');
        $this->assertEquals('2026-01-01 08:00:00', $resultado['fecha_importacion'], 'La fecha debe provenir de los metadatos del lote');
        $this->assertEquals(3, $resultado['total_movimientos'], 'El total debe provenir de los metadatos del lote');
        $this->assertEquals(2, $resultado['conteos']['disponibles'], 'Debe incluir los disponibles del único lote');
        $this->assertEquals(150.25, $resultado['totales']['disponibles'], 'Debe sumar los disponibles del único lote');
        $this->assertEquals(['LOTE-VENT-A'], $llamadas['lotes_datos'] ?? [], 'Un nombre de lote debe resolverse con obtenerDatosLote');
    }

    public function testConstruirVentanaLanzaExcepcionSiNoHayLotes(): void {
        $servicio = new ReporteConciliacionService();

        $this->expectException(\InvalidArgumentException::class, function () use ($servicio) {
            $servicio->construirVentana([]);
        }, 'Una ventana sin lotes debe lanzar InvalidArgumentException');
    }

    public function testCsvDisponiblesIncluyeColumnaLote(): void {
        $servicio = new ReporteConciliacionService();
        $datos = $this->datosFixture();
        $datos['disponibles'][0]['lote_importacion'] = 'LOTE-DISP-A';

        $csv = $servicio->generarCsv($datos);

        $this->assertStringContains('Fecha;Lote;Referencia;Descripción;Monto', $csv, 'La cabecera de disponibles debe incluir la columna Lote');
        $this->assertStringContains('2026-01-20;LOTE-DISP-A;REF-DISP-001;', $csv, 'La fila de disponibles debe incluir el lote de origen');
    }

    public function testPdfDisponiblesIncluyeColumnaLote(): void {
        $servicio = new ReporteConciliacionService();
        $datos = $this->datosFixture();
        $datos['disponibles'][0]['lote_importacion'] = 'LOTE-DISP-PDF-A';
        $datos['disponibles'][1]['lote_importacion'] = 'LOTE-DISP-PDF-B';

        $pdf = $servicio->generarPdf($datos);
        $texto = $this->inflarStreamsDeTexto($pdf);

        $this->assertStringContains('LOTE-DISP-PDF-A', $texto, 'El PDF de disponibles debe incluir el lote de su primera fila');
        $this->assertStringContains('LOTE-DISP-PDF-B', $texto, 'El PDF de disponibles debe incluir el lote de su segunda fila');
        $this->assertStringContains('REF-DISP-001', $texto, 'El PDF debe conservar la referencia de los disponibles');
    }

    public function testGuardarVentanaUsaEtiquetaYMetadatosDeVentana(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $datos = $this->ventanaFixture();
            $servicio = $this->servicioVentanaConFixture($datos);
            $reporte = $servicio->construirVentana($datos['lotes'], '2026-03-31 23:59:59');

            $guardado = $servicio->guardar($reporte, $dir);

            $this->assertStringContains('Ventana_de_2_lotes', $guardado['base'], 'El nombre base debe derivarse de la etiqueta de la ventana');
            $this->assertFileExists($guardado['ruta_pdf'] ?? '', 'La ventana debe generar su PDF');
            $this->assertFileExists($guardado['ruta_csv'] ?? '', 'La ventana debe generar su CSV');
            $this->assertFileExists($guardado['ruta_json'] ?? '', 'La ventana debe generar su sidecar JSON');

            $json = json_decode((string)@file_get_contents($guardado['ruta_json'] ?? ''), true);
            $this->assertIsArray($json, 'El sidecar JSON de la ventana debe decodificar como arreglo');
            if (is_array($json)) {
                $this->assertEquals('Ventana de 2 lotes', $json['lote'] ?? null, 'El JSON debe guardar la etiqueta de la ventana');
                $this->assertEquals('Banco Único', $json['banco'] ?? null, 'El JSON debe guardar el banco único');
                $this->assertEquals(3, $json['conteos']['disponibles'] ?? null, 'El JSON debe guardar los disponibles fusionados');
            }
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testGuardarYListarHistoricosOrdenaPorFechaDeCorte(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new ReporteConciliacionService();
            $datos = $this->datosFixture();

            $primero = $servicio->guardar($datos, $dir);

            $this->assertFileExists($primero['ruta_pdf'] ?? '', 'guardar() debe escribir el PDF');
            $this->assertFileExists($primero['ruta_csv'] ?? '', 'guardar() debe escribir el CSV');
            $this->assertFileExists($primero['ruta_json'] ?? '', 'guardar() debe escribir el sidecar JSON');

            $this->assertTrue(str_ends_with($primero['archivo_pdf'] ?? '', '.pdf'), 'archivo_pdf debe ser un nombre .pdf');
            $this->assertTrue(str_ends_with($primero['archivo_csv'] ?? '', '.csv'), 'archivo_csv debe ser un nombre .csv');

            $json = json_decode((string)@file_get_contents($primero['ruta_json'] ?? ''), true);
            $this->assertIsArray($json, 'El sidecar JSON debe decodificar como arreglo');
            $this->assertTrue(is_array($json), 'El sidecar JSON debe ser válido');
            if (is_array($json)) {
                $this->assertEquals('LOTE-T2-2026', $json['lote'], 'El JSON debe guardar el lote');
                $this->assertEquals('2026-01-01 09:00:00', $json['fecha_ultima_carga'] ?? null, 'El JSON debe guardar la fecha de última carga');
                $this->assertEquals(6, $json['total_movimientos'] ?? null, 'El JSON debe guardar el total de movimientos');
                $this->assertEquals(2, $json['conteos']['conciliados'] ?? null, 'El JSON debe guardar los conteos');
                $this->assertEquals(1250.50, $json['totales']['conciliados'] ?? null, 'El JSON debe guardar los totales');
            }

            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(1, count($items), 'listarHistoricos debe encontrar el reporte guardado');
            if (isset($items[0])) {
                $this->assertEquals($primero['base'], $items[0]['base'], 'El reporte listado debe coincidir con el guardado');
                $this->assertTrue(($items[0]['tamano_pdf'] ?? 0) > 0, 'El tamaño del PDF debe ser positivo');
                $this->assertTrue(($items[0]['tamano_csv'] ?? 0) > 0, 'El tamaño del CSV debe ser positivo');
            }

            $segundosDatos = $datos;
            $segundosDatos['fecha_corte'] = '2026-12-31 23:59:59';
            $segundo = $servicio->guardar($segundosDatos, $dir);

            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(2, count($items), 'listarHistoricos debe encontrar ambos reportes');
            if (isset($items[0])) {
                $this->assertEquals('2026-12-31 23:59:59', $items[0]['fecha_corte'], 'El reporte con fecha de corte posterior debe listarse primero');
                $this->assertEquals($segundo['base'], $items[0]['base'], 'El primer elemento debe ser el segundo reporte guardado');
            }
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testListarHistoricosIgnoraSidecarsYArchivosInvalidos(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new ReporteConciliacionService();

            $csvValido = "\xEF\xBB\xBFa;b\n";

            file_put_contents($dir . DIRECTORY_SEPARATOR . 'malformado.pdf', '%PDF-1.4');
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'malformado.csv', $csvValido);
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'malformado.json', '{json invalido');

            file_put_contents($dir . DIRECTORY_SEPARATOR . 'huerfano.pdf', '%PDF-1.4');
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'huerfano.csv', $csvValido);

            $sidecar = json_encode([
                'lote'        => 'LOTE-INVALIDO',
                'banco'       => 'Banco',
                'fecha_corte' => '2026-01-01 00:00:00',
                'conteos'     => [],
                'totales'     => [],
            ]);
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'vacio.pdf', '');
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'vacio.csv', $csvValido);
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'vacio.json', $sidecar);

            file_put_contents($dir . DIRECTORY_SEPARATOR . 'cerocsv.pdf', '%PDF-1.4');
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'cerocsv.csv', '');
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'cerocsv.json', $sidecar);

            file_put_contents($dir . DIRECTORY_SEPARATOR . 'tipos.pdf', '%PDF-1.4');
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'tipos.csv', $csvValido);
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'tipos.json', json_encode(['lote' => ['no', 'es', 'string']]));

            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(0, count($items), 'Los sidecars malformados, archivos vacíos y pares sin JSON no deben listarse');

            $guardado = $servicio->guardar($this->datosFixture(), $dir);
            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(1, count($items), 'Los archivos inválidos no deben ocultar un reporte válido');
            if (isset($items[0])) {
                $this->assertEquals($guardado['base'], $items[0]['base'], 'El único reporte listado debe ser el válido');
            }
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testListarHistoricosRechazaMetadatosIncompletos(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new ReporteConciliacionService();
            $pdf = '%PDF-1.4';
            $csv = "\xEF\xBB\xBFa;b\n";

            $this->escribirReporteFalso($dir, 'arreglo', '[]', $pdf, $csv);
            $this->escribirReporteFalso($dir, 'sinlote', [
                'banco'       => 'Banco',
                'fecha_corte' => '2026-01-01 00:00:00',
            ], $pdf, $csv);
            $this->escribirReporteFalso($dir, 'sinfecha', [
                'lote'  => 'LOTE-X',
                'banco' => 'Banco',
            ], $pdf, $csv);
            $this->escribirReporteFalso($dir, 'vacios', [
                'lote'        => ' ',
                'banco'       => 'Banco',
                'fecha_corte' => '',
            ], $pdf, $csv);

            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(0, count($items), 'Un sidecar sin lote o sin fecha de corte no debe listarse');

            $guardado = $servicio->guardar($this->datosFixture(), $dir);
            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(1, count($items), 'El reporte válido debe seguir listándose');
            if (isset($items[0])) {
                $this->assertEquals($guardado['base'], $items[0]['base'], 'El único reporte listado debe ser el válido');
            }
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testListarHistoricosRechazaFirmasDeArchivoInvalidas(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new ReporteConciliacionService();
            $metadatos = [
                'lote'        => 'LOTE-X',
                'banco'       => 'Banco',
                'fecha_corte' => '2026-01-01 00:00:00',
                'conteos'     => [],
                'totales'     => [],
            ];
            $bom = "\xEF\xBB\xBF";

            $this->escribirReporteFalso($dir, 'nopdf', $metadatos, 'NO-PDF', $bom . "a;b\n");
            $this->escribirReporteFalso($dir, 'sinbom', $metadatos, "%PDF-1.4\n", "a;b\n");
            $this->escribirReporteFalso($dir, 'faltapdf', $metadatos, null, $bom . "a;b\n");
            $this->escribirReporteFalso($dir, 'faltacsv', $metadatos, "%PDF-1.4\n", null);

            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(0, count($items), 'Un PDF sin firma, un CSV sin BOM o un archivo faltante no debe listarse');

            $guardado = $servicio->guardar($this->datosFixture(), $dir);
            $items = $servicio->listarHistoricos($dir);
            $this->assertEquals(1, count($items), 'El reporte con firmas válidas debe listarse');
            if (isset($items[0])) {
                $this->assertEquals($guardado['base'], $items[0]['base'], 'El único reporte listado debe ser el válido');
            }
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testGuardarRechazaGeneracionVaciaAntesDeEscribir(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new class extends ReporteConciliacionService {
                public function generarCsv(array $datos): string {
                    return '';
                }
            };

            $this->expectException(\RuntimeException::class, function () use ($servicio, $dir) {
                $servicio->guardar($this->datosFixture(), $dir);
            }, 'guardar debe fallar si el CSV generado está vacío');

            $archivos = array_diff(scandir($dir) ?: [], ['.', '..']);
            $this->assertEquals(0, count($archivos), 'No debe escribir archivos cuando la generación está vacía');
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testGuardarSanitizaLoteConRutaRelativa(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new ReporteConciliacionService();
            $datos = $this->datosFixture();
            $datos['lote'] = '../EVIL';

            $guardado = $servicio->guardar($datos, $dir);
            $base = $guardado['base'];

            $this->assertFalse(str_contains($base, '..'), 'El nombre base no debe contener dos puntos');
            $this->assertFalse(str_contains($base, '/'), 'El nombre base no debe contener barras');
            $this->assertFalse(str_contains($base, '\\'), 'El nombre base no debe contener contrabarras');
            $this->assertStringContains('EVIL', $base, 'El nombre base debe conservar la parte segura del lote');
            $this->assertEquals(realpath($dir), realpath(dirname($guardado['ruta_pdf'])), 'Los archivos deben quedar dentro del directorio indicado');
            $this->assertFileExists($guardado['ruta_pdf'], 'El PDF debe existir en el directorio temporal');
            $this->assertFileExists($guardado['ruta_csv'], 'El CSV debe existir en el directorio temporal');
            $this->assertFileExists($guardado['ruta_json'], 'El JSON debe existir en el directorio temporal');
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }

    public function testListarYResolverNoCreanElDirectorioDeReportes(): void {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reporte_conciliacion_inexistente_' . bin2hex(random_bytes(6));

        try {
            $this->assertFalse(is_dir($dir), 'El directorio único de prueba no debe existir antes de la comprobación');

            $servicio = new ReporteConciliacionService();
            $this->assertEquals([], $servicio->listarHistoricos($dir), 'listarHistoricos debe devolver [] si el directorio no existe');
            $this->assertNull($servicio->resolverArchivo('x.pdf', $dir), 'resolverArchivo debe devolver null si el directorio no existe');
            $this->assertFalse(is_dir($dir), 'Listar o resolver no deben crear el directorio de reportes');
        } finally {
            if (is_dir($dir)) {
                $this->eliminarDirectorio($dir);
            }
        }
    }

    public function testResolverArchivoAceptaValidosYRechazaRutasPeligrosas(): void {
        $dir = $this->crearDirectorioTemporal();

        try {
            $servicio = new ReporteConciliacionService();
            $guardado = $servicio->guardar($this->datosFixture(), $dir);

            $pdf = $servicio->resolverArchivo($guardado['archivo_pdf'], $dir);
            $csv = $servicio->resolverArchivo($guardado['archivo_csv'], $dir);
            $this->assertNotNull($pdf, 'resolverArchivo debe aceptar un PDF existente del directorio');
            $this->assertNotNull($csv, 'resolverArchivo debe aceptar un CSV existente del directorio');
            $this->assertTrue($pdf !== null && is_file($pdf), 'La ruta resuelta del PDF debe ser un archivo real');
            $this->assertTrue($csv !== null && is_file($csv), 'La ruta resuelta del CSV debe ser un archivo real');

            $this->assertNull($servicio->resolverArchivo('../x.pdf', $dir), 'Debe rechazar el escape con ../');
            $this->assertNull($servicio->resolverArchivo('sub/x.pdf', $dir), 'Debe rechazar subrutas');
            $this->assertNull($servicio->resolverArchivo($guardado['ruta_pdf'], $dir), 'Debe rechazar rutas absolutas');
            $this->assertNull($servicio->resolverArchivo('x.txt', $dir), 'Debe rechazar extensiones no permitidas');
            $this->assertNull($servicio->resolverArchivo('x.pdf.exe', $dir), 'Debe rechazar extensiones dobles');
            $this->assertNull($servicio->resolverArchivo('', $dir), 'Debe rechazar un nombre vacío');

            $dirFaltante = $dir . DIRECTORY_SEPARATOR . 'inexistente';
            $this->assertNull($servicio->resolverArchivo('x.pdf', $dirFaltante), 'Un directorio inexistente no debe resolver archivos');
            $this->assertEquals([], $servicio->listarHistoricos($dirFaltante), 'listarHistoricos debe devolver [] si el directorio no existe');
        } finally {
            $this->eliminarDirectorio($dir);
        }
    }
}
