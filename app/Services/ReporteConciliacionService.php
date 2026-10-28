<?php
namespace App\Services;

use App\Models\ConciliacionModel;

// FPDF no está en vendor/: se embebe en el repo como archivo único (sin Composer).
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

/**
 * Genera y administra los reportes históricos de conciliación bancaria.
 *
 * El reporte tiene tres secciones: movimientos conciliados de todo el historial,
 * disponibles sin reclamar del lote consultado y pagos/comprobantes pendientes
 * de conciliar. Se produce en PDF (FPDF, fuentes core Windows-1252) y CSV
 * (UTF-8 con BOM, delimitador ';'), con un sidecar JSON de metadatos que permite
 * listar los históricos sin abrir los documentos.
 *
 * Las descargas se resuelven sólo contra el directorio de reportes mediante
 * basename + extensión permitida + realpath contenido, nunca por ruta directa.
 */
class ReporteConciliacionService {

    /** Margen uniforme del reporte en milímetros. */
    private const MARGEN = 12.0;

    /** Alto de fila de las tablas en milímetros. */
    private const ALTO_FILA = 5.4;

    /** Tamaño de fuente de las tablas en puntos. */
    private const TAMANO_TABLA = 7.5;

    /** Firma inicial obligatoria de un PDF válido. */
    private const FIRMA_PDF = '%PDF';

    /** BOM UTF-8 que debe encabezar los CSV del reporte. */
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    private ConciliacionModel $model;

    public function __construct(?ConciliacionModel $model = null) {
        $this->model = $model ?? new ConciliacionModel();
    }

    /**
     * Devuelve la ruta canónica del directorio de reportes sin crearlo.
     */
    private function rutaReportes(): string {
        return BASE_PATH . '/storage/backups/reportes_conciliacion';
    }

    /**
     * Devuelve (y crea si hace falta) el directorio privado de reportes.
     * El .htaccess defensivo evita el acceso HTTP directo a los archivos.
     */
    public function directorioReportes(): string {
        $dir = $this->rutaReportes();

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_dir($dir) && !is_file($htaccess)) {
            $contenido = "# Bloqueo total de acceso HTTP directo a los reportes de conciliación\n"
                . "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Order deny,allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n"
                . "Options -Indexes\n";
            @file_put_contents($htaccess, $contenido, LOCK_EX);
        }

        return $dir;
    }

    /**
     * Construye en memoria el reporte de un lote con una fecha de corte dada.
     *
     * @throws \InvalidArgumentException si el lote no existe en extractos_bancarios
     */
    public function construir(string $lote, ?string $fechaCorte = null): array {
        $fechaCorte = $fechaCorte ?: date('Y-m-d H:i:s');

        $datosLote = $this->model->obtenerDatosLote($lote);
        if ($datosLote === null) {
            throw new \InvalidArgumentException('No existe el lote de importación indicado: ' . $lote);
        }

        // El snapshot se construye de forma sincrónica en el corte: se consultan
        // todos los conciliados del instante sin filtrar por fecha_carga, porque
        // date() de PHP y CURRENT_TIMESTAMP de MySQL pueden diferir por zona horaria.
        $conciliados = $this->model->obtenerMovimientosConciliados();
        $disponibles = $this->model->obtenerDisponiblesLote($lote);
        $pendientes  = $this->model->obtenerTodosPagosPendientes();

        return [
            'lote'               => $datosLote['lote_importacion'] ?? $lote,
            'banco'              => $datosLote['banco'] ?? '',
            'fecha_importacion'  => $datosLote['fecha_importacion'] ?? '',
            'fecha_ultima_carga' => $datosLote['fecha_ultima_carga'] ?? '',
            'total_movimientos'  => (int)($datosLote['total_movimientos'] ?? 0),
            'fecha_corte'        => $fechaCorte,
            'generado_en'        => date('Y-m-d H:i:s'),
            'conteos'            => [
                'conciliados' => count($conciliados),
                'disponibles' => count($disponibles),
                'pendientes'  => count($pendientes),
            ],
            'totales'            => [
                'conciliados' => $this->sumarMontos($conciliados),
                'disponibles' => $this->sumarMontos($disponibles),
                'pendientes'  => $this->sumarMontos($pendientes),
            ],
            'conciliados'       => $conciliados,
            'disponibles'       => $disponibles,
            'pendientes'        => $pendientes,
        ];
    }

    /**
     * Construye en memoria el reporte de la ventana activa (varios lotes).
     *
     * Los disponibles se fusionan lote a lote; los conciliados y pendientes son
     * globales, igual que en construir(). La etiqueta del reporte es el nombre
     * del lote único o "Ventana de N lotes".
     *
     * @param array<int, array<string, mixed>|string> $lotes Filas de obtenerLotesVentana() o nombres de lote
     * @throws \InvalidArgumentException si la lista de lotes está vacía
     */
    public function construirVentana(array $lotes, ?string $fechaCorte = null): array {
        $fechaCorte = $fechaCorte ?: date('Y-m-d H:i:s');

        $nombres = [];
        $bancos = [];
        $fechasImportacion = [];
        $fechasUltimaCarga = [];
        $totalMovimientos = 0;
        $disponibles = [];

        foreach ($lotes as $lote) {
            if (is_array($lote)) {
                $nombre = (string)($lote['lote_importacion'] ?? '');
                $banco = trim((string)($lote['banco'] ?? ''));
                $fechaImportacion = (string)($lote['fecha_importacion'] ?? '');
                $fechaUltimaCarga = (string)($lote['fecha_ultima_carga'] ?? '');
                $totalLote = (int)($lote['total_movimientos'] ?? 0);
            } else {
                $nombre = (string)$lote;
                $banco = '';
                $fechaImportacion = '';
                $fechaUltimaCarga = '';
                $totalLote = 0;
            }

            if ($nombre === '' || isset($nombres[$nombre])) {
                continue;
            }
            $nombres[$nombre] = true;

            if ($banco === '' || $fechaImportacion === '' || $totalLote === 0) {
                $datosLote = $this->model->obtenerDatosLote($nombre);
                if ($datosLote !== null) {
                    $banco = $banco !== '' ? $banco : (string)($datosLote['banco'] ?? '');
                    $fechaImportacion = $fechaImportacion !== ''
                        ? $fechaImportacion
                        : (string)($datosLote['fecha_importacion'] ?? '');
                    $fechaUltimaCarga = $fechaUltimaCarga !== ''
                        ? $fechaUltimaCarga
                        : (string)($datosLote['fecha_ultima_carga'] ?? '');
                    $totalLote = $totalLote > 0 ? $totalLote : (int)($datosLote['total_movimientos'] ?? 0);
                }
            }

            foreach (explode(',', $banco) as $bancoLote) {
                $bancoLote = trim($bancoLote);
                if ($bancoLote !== '') {
                    $bancos[$bancoLote] = true;
                }
            }

            if ($fechaImportacion !== '') {
                $fechasImportacion[] = $fechaImportacion;
            }
            if ($fechaUltimaCarga !== '') {
                $fechasUltimaCarga[] = $fechaUltimaCarga;
            }
            $totalMovimientos += $totalLote;

            foreach ($this->model->obtenerDisponiblesLote($nombre) as $fila) {
                if (!isset($fila['lote_importacion']) || $fila['lote_importacion'] === '') {
                    $fila['lote_importacion'] = $nombre;
                }
                $disponibles[] = $fila;
            }
        }

        if (empty($nombres)) {
            throw new \InvalidArgumentException('La ventana de conciliación no contiene lotes.');
        }

        $cantidadLotes = count($nombres);
        $etiqueta = $cantidadLotes === 1
            ? (string)array_key_first($nombres)
            : 'Ventana de ' . $cantidadLotes . ' lotes';

        $conciliados = $this->model->obtenerMovimientosConciliados();
        $pendientes  = $this->model->obtenerTodosPagosPendientes();

        return [
            'lote'               => $etiqueta,
            'banco'              => implode(', ', array_keys($bancos)),
            'fecha_importacion'  => empty($fechasImportacion) ? '' : min($fechasImportacion),
            'fecha_ultima_carga' => empty($fechasUltimaCarga) ? '' : max($fechasUltimaCarga),
            'total_movimientos'  => $totalMovimientos,
            'fecha_corte'        => $fechaCorte,
            'generado_en'        => date('Y-m-d H:i:s'),
            'conteos'            => [
                'conciliados' => count($conciliados),
                'disponibles' => count($disponibles),
                'pendientes'  => count($pendientes),
            ],
            'totales'            => [
                'conciliados' => $this->sumarMontos($conciliados),
                'disponibles' => $this->sumarMontos($disponibles),
                'pendientes'  => $this->sumarMontos($pendientes),
            ],
            'conciliados'       => $conciliados,
            'disponibles'       => $disponibles,
            'pendientes'        => $pendientes,
        ];
    }

    /**
     * Genera el reporte completo en CSV (UTF-8 con BOM y delimitador ';').
     */
    public function generarCsv(array $datos): string {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo preparar el flujo para generar el CSV.');
        }

        $bytesBom = fwrite($handle, self::BOM_UTF8);
        if ($bytesBom === false || $bytesBom !== 3) {
            fclose($handle);
            throw new \RuntimeException('No se pudo escribir el BOM del CSV.');
        }

        $this->filaCsv($handle, ['Reporte de conciliación bancaria']);
        $this->filaCsv($handle, ['Lote', $datos['lote'] ?? '']);
        $this->filaCsv($handle, ['Banco', $datos['banco'] ?? '']);
        $this->filaCsv($handle, ['Fecha de importación', $datos['fecha_importacion'] ?? '']);
        $this->filaCsv($handle, ['Fecha de corte', $datos['fecha_corte'] ?? '']);
        $this->filaCsv($handle, ['Generado en', $datos['generado_en'] ?? '']);
        $this->filaCsv($handle, []);
        $this->filaCsv($handle, ['Resumen']);
        $this->filaCsv($handle, [
            'Movimientos conciliados',
            (string)($datos['conteos']['conciliados'] ?? 0),
            'Total',
            $this->formatearMonto($datos['totales']['conciliados'] ?? 0),
        ]);
        $this->filaCsv($handle, [
            'Movimientos disponibles',
            (string)($datos['conteos']['disponibles'] ?? 0),
            'Total',
            $this->formatearMonto($datos['totales']['disponibles'] ?? 0),
        ]);
        $this->filaCsv($handle, [
            'Pagos pendientes de conciliar',
            (string)($datos['conteos']['pendientes'] ?? 0),
            'Total',
            $this->formatearMonto($datos['totales']['pendientes'] ?? 0),
        ]);
        $this->filaCsv($handle, []);

        $this->filaCsv($handle, ['MOVIMIENTOS CONCILIADOS']);
        $this->filaCsv($handle, ['Fecha', 'Unidad', 'Residente', 'Referencia', 'Lote', 'Monto']);
        foreach (($datos['conciliados'] ?? []) as $fila) {
            $this->filaCsv($handle, [
                $fila['fecha_movimiento'] ?? '',
                $fila['unidad_numero'] ?? '',
                $fila['residente_nombre'] ?? '',
                $fila['referencia_bancaria'] ?? $fila['referencia'] ?? '',
                $fila['lote_importacion'] ?? '',
                $this->formatearMonto($fila['monto'] ?? 0),
            ]);
        }

        $this->filaCsv($handle, []);
        $this->filaCsv($handle, ['MOVIMIENTOS DISPONIBLES SIN RECLAMAR']);
        $this->filaCsv($handle, ['Fecha', 'Lote', 'Referencia', 'Descripción', 'Monto']);
        foreach (($datos['disponibles'] ?? []) as $fila) {
            $this->filaCsv($handle, [
                $fila['fecha_movimiento'] ?? '',
                $fila['lote_importacion'] ?? '',
                $fila['referencia_bancaria'] ?? $fila['referencia'] ?? '',
                $fila['descripcion'] ?? $fila['descripcion_banco'] ?? '',
                $this->formatearMonto($fila['monto'] ?? 0),
            ]);
        }

        $this->filaCsv($handle, []);
        $this->filaCsv($handle, ['PAGOS REPORTADOS PENDIENTES DE CONCILIAR']);
        $this->filaCsv($handle, ['Fecha', 'Unidad', 'Residente', 'Referencia', 'Origen', 'Monto']);
        foreach (($datos['pendientes'] ?? []) as $fila) {
            $this->filaCsv($handle, [
                $fila['fecha_pago'] ?? '',
                $fila['unidad_numero'] ?? '',
                $fila['residente_nombre'] ?? '',
                $fila['referencia'] ?? '',
                ucfirst((string)($fila['origen_tabla'] ?? '')),
                $this->formatearMonto($fila['monto'] ?? 0),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Genera el reporte completo en PDF y lo devuelve como string binario.
     */
    public function generarPdf(array $datos): string {
        $pdf = new class('P', 'mm', 'Letter') extends \FPDF {
            public function Footer(): void {
                $this->SetY(-10);
                $this->SetFont('Helvetica', '', 7);
                $this->Cell(
                    0,
                    5,
                    mb_convert_encoding('Página ' . $this->PageNo(), 'Windows-1252', 'UTF-8'),
                    0,
                    0,
                    'C'
                );
            }
        };

        $pdf->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
        $pdf->SetAutoPageBreak(true, self::MARGEN);
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(0, 8, $this->txt('REPORTE DE CONCILIACIÓN BANCARIA'), 0, 1, 'C');
        $pdf->Ln(2);

        $this->lineaMeta($pdf, 'Lote', (string)($datos['lote'] ?? ''));
        $this->lineaMeta($pdf, 'Banco', (string)($datos['banco'] ?? ''));
        $this->lineaMeta($pdf, 'Fecha de importación', (string)($datos['fecha_importacion'] ?? ''));
        $this->lineaMeta($pdf, 'Fecha de corte', (string)($datos['fecha_corte'] ?? ''));
        $this->lineaMeta($pdf, 'Generado en', (string)($datos['generado_en'] ?? ''));

        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(0, 5, $this->txt('RESUMEN'), 0, 1);
        $pdf->SetFont('Helvetica', '', 8.5);
        $this->lineaResumen(
            $pdf,
            'Conciliados',
            (int)($datos['conteos']['conciliados'] ?? 0),
            $datos['totales']['conciliados'] ?? 0
        );
        $this->lineaResumen(
            $pdf,
            'Disponibles sin reclamar',
            (int)($datos['conteos']['disponibles'] ?? 0),
            $datos['totales']['disponibles'] ?? 0
        );
        $this->lineaResumen(
            $pdf,
            'Pagos pendientes',
            (int)($datos['conteos']['pendientes'] ?? 0),
            $datos['totales']['pendientes'] ?? 0
        );

        $this->dibujarSeccion(
            $pdf,
            'MOVIMIENTOS CONCILIADOS',
            ['Fecha', 'Unidad', 'Residente', 'Referencia', 'Lote', 'Monto'],
            $this->filasConciliados($datos['conciliados'] ?? []),
            [22.0, 16.0, 52.0, 38.0, 38.0, 25.9],
            ['L', 'L', 'L', 'L', 'L', 'R']
        );

        $this->dibujarSeccion(
            $pdf,
            'MOVIMIENTOS DISPONIBLES SIN RECLAMAR',
            ['Fecha', 'Lote', 'Referencia', 'Descripción', 'Monto'],
            $this->filasDisponibles($datos['disponibles'] ?? []),
            [20.0, 36.0, 40.0, 70.9, 25.0],
            ['L', 'L', 'L', 'L', 'R']
        );

        $this->dibujarSeccion(
            $pdf,
            'PAGOS REPORTADOS PENDIENTES DE CONCILIAR',
            ['Fecha', 'Unidad', 'Residente', 'Referencia', 'Origen', 'Monto'],
            $this->filasPendientes($datos['pendientes'] ?? []),
            [22.0, 16.0, 45.0, 38.0, 38.0, 32.9],
            ['L', 'L', 'L', 'L', 'L', 'R']
        );

        return $pdf->Output('S');
    }

    /**
     * Persiste PDF, CSV y sidecar JSON de metadatos en el directorio indicado.
     *
     * @return array{base:string,ruta_pdf:string,ruta_csv:string,ruta_json:string,archivo_pdf:string,archivo_csv:string}
     * @throws \RuntimeException si no se puede crear el directorio o escribir algún archivo
     */
    public function guardar(array $datos, ?string $directorio = null): array {
        $dir = $directorio ?: $this->directorioReportes();

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de reportes: ' . $dir);
        }

        $loteSanitizado = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($datos['lote'] ?? ''));
        $loteSanitizado = is_string($loteSanitizado) ? trim($loteSanitizado, '._-') : '';
        if ($loteSanitizado === '') {
            $loteSanitizado = 'lote';
        }

        $marcaTiempo = strtotime((string)($datos['fecha_corte'] ?? ''));
        if ($marcaTiempo === false) {
            $marcaTiempo = time();
        }

        $base = sprintf(
            'conciliacion_%s_%s_%s',
            $loteSanitizado,
            date('Ymd-His', $marcaTiempo),
            bin2hex(random_bytes(3))
        );

        $nombrePdf  = $base . '.pdf';
        $nombreCsv  = $base . '.csv';
        $nombreJson = $base . '.json';
        $rutaPdf    = $dir . DIRECTORY_SEPARATOR . $nombrePdf;
        $rutaCsv    = $dir . DIRECTORY_SEPARATOR . $nombreCsv;
        $rutaJson   = $dir . DIRECTORY_SEPARATOR . $nombreJson;

        $metadatos = [
            'lote'               => $datos['lote'] ?? null,
            'banco'              => $datos['banco'] ?? null,
            'fecha_importacion'  => $datos['fecha_importacion'] ?? null,
            'fecha_ultima_carga' => $datos['fecha_ultima_carga'] ?? null,
            'total_movimientos'  => $datos['total_movimientos'] ?? null,
            'fecha_corte'        => $datos['fecha_corte'] ?? null,
            'generado_en'        => $datos['generado_en'] ?? null,
            'conteos'            => $datos['conteos'] ?? [],
            'totales'            => $datos['totales'] ?? [],
        ];

        $contenidoPdf = $this->generarPdf($datos);
        $contenidoCsv = $this->generarCsv($datos);

        if ($contenidoPdf === '') {
            throw new \RuntimeException('El PDF generado del reporte está vacío.');
        }
        if ($contenidoCsv === '') {
            throw new \RuntimeException('El CSV generado del reporte está vacío.');
        }

        try {
            $this->escribirArchivo($rutaPdf, $contenidoPdf);

            $this->escribirArchivo($rutaCsv, $contenidoCsv);

            $json = json_encode($metadatos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new \RuntimeException('No se pudieron serializar los metadatos del reporte.');
            }

            $this->escribirArchivo($rutaJson, $json);
        } catch (\Throwable $e) {
            // Se eliminan las tres rutas candidatas para no dejar PDF/CSV/JSON
            // huérfanos ni parciales si una escritura posterior falla.
            foreach ([$rutaPdf, $rutaCsv, $rutaJson] as $ruta) {
                @unlink($ruta);
            }
            throw $e;
        }

        return [
            'base'        => $base,
            'ruta_pdf'    => $rutaPdf,
            'ruta_csv'    => $rutaCsv,
            'ruta_json'   => $rutaJson,
            'archivo_pdf' => $nombrePdf,
            'archivo_csv' => $nombreCsv,
        ];
    }

    /**
     * Lista los reportes históricos cuyos PDF y CSV existen y son legibles.
     * Ordena por fecha_corte descendente y, en empate, por nombre base descendente.
     */
    public function listarHistoricos(?string $directorio = null): array {
        $dir = $directorio ?: $this->rutaReportes();

        if (!is_dir($dir)) {
            return [];
        }

        $archivosJson = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $items = [];

        foreach ($archivosJson as $rutaJson) {
            $base = basename($rutaJson, '.json');
            $nombrePdf = $base . '.pdf';
            $nombreCsv = $base . '.csv';

            if (!preg_match('/^[A-Za-z0-9._-]+\.(pdf|csv)$/', $nombrePdf)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9._-]+\.(pdf|csv)$/', $nombreCsv)) {
                continue;
            }

            $rutaPdf = $dir . DIRECTORY_SEPARATOR . $nombrePdf;
            $rutaCsv = $dir . DIRECTORY_SEPARATOR . $nombreCsv;

            if (!is_file($rutaPdf) || !is_readable($rutaPdf)) {
                continue;
            }
            if (!is_file($rutaCsv) || !is_readable($rutaCsv)) {
                continue;
            }

            $tamanoPdf = @filesize($rutaPdf);
            $tamanoCsv = @filesize($rutaCsv);
            if ($tamanoPdf === false || $tamanoCsv === false || $tamanoPdf <= 0 || $tamanoCsv <= 0) {
                continue;
            }

            $firmaPdf = @file_get_contents($rutaPdf, false, null, 0, 4);
            if ($firmaPdf === false || $firmaPdf !== self::FIRMA_PDF) {
                continue;
            }

            $firmaCsv = @file_get_contents($rutaCsv, false, null, 0, 3);
            if ($firmaCsv === false || $firmaCsv !== self::BOM_UTF8) {
                continue;
            }

            $contenidoJson = @file_get_contents($rutaJson);
            if ($contenidoJson === false) {
                continue;
            }
            $metadatos = json_decode($contenidoJson, true);
            if (!is_array($metadatos)) {
                continue;
            }

            $lote = $metadatos['lote'] ?? null;
            if (!is_string($lote) || trim($lote) === '') {
                continue;
            }

            $fechaCorte = $metadatos['fecha_corte'] ?? null;
            if (!is_string($fechaCorte) || trim($fechaCorte) === '') {
                continue;
            }

            $banco = $metadatos['banco'] ?? null;
            if ($banco !== null && !is_string($banco)) {
                continue;
            }

            $conteos = isset($metadatos['conteos']) && is_array($metadatos['conteos'])
                ? $metadatos['conteos']
                : [];
            $totales = isset($metadatos['totales']) && is_array($metadatos['totales'])
                ? $metadatos['totales']
                : [];

            $items[] = [
                'base'        => $base,
                'lote'        => $lote,
                'banco'       => $banco,
                'fecha_corte' => $fechaCorte,
                'conteos'     => $conteos,
                'totales'     => $totales,
                'archivo_pdf' => $nombrePdf,
                'archivo_csv' => $nombreCsv,
                'tamano_pdf'  => $tamanoPdf,
                'tamano_csv'  => $tamanoCsv,
            ];
        }

        usort($items, function (array $a, array $b): int {
            $comparacion = strcmp((string)$b['fecha_corte'], (string)$a['fecha_corte']);
            if ($comparacion !== 0) {
                return $comparacion;
            }
            return strcmp((string)$b['base'], (string)$a['base']);
        });

        return $items;
    }

    /**
     * Resuelve de forma segura un archivo de reporte dentro del directorio.
     *
     * Rechaza nombres vacíos, rutas con separadores, extensiones distintas de
     * pdf/csv y cualquier archivo cuyo realpath quede fuera del directorio.
     */
    public function resolverArchivo(string $archivo, ?string $directorio = null): ?string {
        if ($archivo === '' || basename($archivo) !== $archivo) {
            return null;
        }

        if (str_contains($archivo, '/') || str_contains($archivo, '\\')) {
            return null;
        }

        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'csv'], true)) {
            return null;
        }

        $dir = $directorio ?: $this->rutaReportes();
        $dirReal = realpath($dir);
        if ($dirReal === false) {
            return null;
        }

        $rutaReal = realpath($dir . DIRECTORY_SEPARATOR . $archivo);
        if ($rutaReal === false || !is_file($rutaReal)) {
            return null;
        }

        $prefijo = rtrim($dirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($rutaReal, $prefijo)) {
            return null;
        }

        return $rutaReal;
    }

    /**
     * Sanitiza una celda CSV: neutraliza saltos de línea e inyección de fórmulas.
     */
    private function sanearCeldaCsv(mixed $valor): string {
        $texto = (string)($valor ?? '');
        $texto = str_replace(["\r\n", "\r", "\n"], ' ', $texto);

        // El tabulador y el punto y coma sólo son peligrosos como primer byte;
        // los caracteres de fórmula se evalúan tras descartar espacios iniciales.
        $primerCaracter = $texto !== '' ? $texto[0] : '';
        $recortado = ltrim($texto);
        $primerRecortado = $recortado !== '' ? $recortado[0] : '';

        $peligroso = in_array($primerCaracter, ["\t", ';'], true)
            || in_array($primerRecortado, ['=', '+', '-', '@'], true);

        if ($peligroso) {
            return "\t" . $texto;
        }

        return $texto;
    }

    /**
     * Escribe una fila CSV aplicando sanitización a todas las celdas.
     */
    private function filaCsv($handle, array $celdas): void {
        $limpias = [];
        foreach ($celdas as $celda) {
            $limpias[] = $this->sanearCeldaCsv($celda);
        }

        $resultado = fputcsv($handle, $limpias, ';', '"', '');
        if ($resultado === false) {
            throw new \RuntimeException('No se pudo escribir una fila del CSV.');
        }
    }

    /**
     * Convierte UTF-8 a Windows-1252 (codificación de las fuentes core de FPDF)
     * y elimina caracteres de control antes de dibujar.
     */
    private function txt(string $texto): string {
        $limpio = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $texto);
        if ($limpio === null) {
            $limpio = str_replace(["\r", "\n", "\t"], ' ', $texto);
        }

        return mb_convert_encoding($limpio, 'Windows-1252', 'UTF-8');
    }

    /**
     * Formatea un monto con separador de miles y coma decimal.
     */
    private function formatearMonto(mixed $monto): string {
        return number_format((float)($monto ?? 0), 2, ',', '.');
    }

    /**
     * Suma los montos de una sección redondeando a dos decimales.
     */
    private function sumarMontos(array $filas): float {
        $total = 0.0;
        foreach ($filas as $fila) {
            $total += (float)($fila['monto'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Recorta un texto al ancho disponible, con puntos suspensivos si excede.
     */
    private function recortar(\FPDF $pdf, string $texto, float $ancho): string {
        $texto = $this->txt($texto);
        if ($ancho <= 0) {
            return '';
        }
        if ($pdf->GetStringWidth($texto) <= $ancho) {
            return $texto;
        }

        $sufijo = $this->txt('...');
        while ($texto !== '' && $pdf->GetStringWidth($texto . $sufijo) > $ancho) {
            $texto = substr($texto, 0, -1);
        }

        return $texto . $sufijo;
    }

    /**
     * Dibuja una línea de metadatos con etiqueta en negrita.
     */
    private function lineaMeta(\FPDF $pdf, string $etiqueta, string $valor): void {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(38, 5, $this->txt($etiqueta . ':'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell(0, 5, $this->recortar($pdf, $valor, 140), 0, 1);
    }

    /**
     * Dibuja una línea del resumen con conteo y total.
     */
    private function lineaResumen(\FPDF $pdf, string $etiqueta, int $conteo, mixed $total): void {
        $texto = $etiqueta . ': ' . $conteo . ' movimiento(s) — Total: ' . $this->formatearMonto($total);
        $pdf->Cell(0, 5, $this->txt($texto), 0, 1);
    }

    /**
     * Dibuja una sección completa: título, cabecera y filas con corte de página.
     */
    private function dibujarSeccion(
        \FPDF $pdf,
        string $titulo,
        array $encabezados,
        array $filas,
        array $anchos,
        array $alineaciones
    ): void {
        $this->asegurarEspacio($pdf, 22);

        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(0, 5.5, $this->txt($titulo), 0, 1, 'L');

        if (empty($filas)) {
            $pdf->SetFont('Helvetica', '', self::TAMANO_TABLA);
            $pdf->Cell(0, 5, $this->txt('Sin registros.'), 0, 1);
            return;
        }

        $this->dibujarEncabezadoTabla($pdf, $encabezados, $anchos, $alineaciones);

        foreach ($filas as $fila) {
            if ($pdf->GetY() + self::ALTO_FILA > $pdf->GetPageHeight() - self::MARGEN) {
                $pdf->AddPage();
                $this->dibujarEncabezadoTabla($pdf, $encabezados, $anchos, $alineaciones);
            }

            foreach ($fila as $indice => $celda) {
                $ancho = $anchos[$indice] ?? 20.0;
                $pdf->Cell(
                    $ancho,
                    self::ALTO_FILA,
                    $this->recortar($pdf, (string)$celda, $ancho - 2),
                    1,
                    0,
                    $alineaciones[$indice] ?? 'L'
                );
            }
            $pdf->Ln(self::ALTO_FILA);
        }
    }

    /**
     * Dibuja la cabecera de una tabla con el mismo formato en cada página.
     */
    private function dibujarEncabezadoTabla(\FPDF $pdf, array $encabezados, array $anchos, array $alineaciones): void {
        $pdf->SetFont('Helvetica', 'B', self::TAMANO_TABLA);
        foreach ($encabezados as $indice => $encabezado) {
            $ancho = $anchos[$indice] ?? 20.0;
            $pdf->Cell(
                $ancho,
                self::ALTO_FILA,
                $this->recortar($pdf, $encabezado, $ancho - 2),
                1,
                0,
                $alineaciones[$indice] ?? 'L'
            );
        }
        $pdf->Ln(self::ALTO_FILA);
        $pdf->SetFont('Helvetica', '', self::TAMANO_TABLA);
    }

    /**
     * Agrega una página si el espacio restante no alcanza para el bloque pedido.
     */
    private function asegurarEspacio(\FPDF $pdf, float $alto): void {
        if ($pdf->GetY() + $alto > $pdf->GetPageHeight() - self::MARGEN) {
            $pdf->AddPage();
        }
    }

    /**
     * Prepara las filas de la sección de conciliados para la tabla PDF.
     */
    private function filasConciliados(array $conciliados): array {
        $filas = [];
        foreach ($conciliados as $fila) {
            $filas[] = [
                (string)($fila['fecha_movimiento'] ?? ''),
                (string)($fila['unidad_numero'] ?? ''),
                (string)($fila['residente_nombre'] ?? ''),
                (string)($fila['referencia_bancaria'] ?? $fila['referencia'] ?? ''),
                (string)($fila['lote_importacion'] ?? ''),
                $this->formatearMonto($fila['monto'] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Prepara las filas de la sección de disponibles para la tabla PDF.
     */
    private function filasDisponibles(array $disponibles): array {
        $filas = [];
        foreach ($disponibles as $fila) {
            $descripcion = $fila['descripcion'] ?? $fila['descripcion_banco'] ?? '';
            $filas[] = [
                (string)($fila['fecha_movimiento'] ?? ''),
                (string)($fila['lote_importacion'] ?? ''),
                (string)($fila['referencia_bancaria'] ?? $fila['referencia'] ?? ''),
                (string)$descripcion,
                $this->formatearMonto($fila['monto'] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Prepara las filas de la sección de pendientes para la tabla PDF.
     */
    private function filasPendientes(array $pendientes): array {
        $filas = [];
        foreach ($pendientes as $fila) {
            $filas[] = [
                (string)($fila['fecha_pago'] ?? ''),
                (string)($fila['unidad_numero'] ?? ''),
                (string)($fila['residente_nombre'] ?? ''),
                (string)($fila['referencia'] ?? ''),
                ucfirst((string)($fila['origen_tabla'] ?? '')),
                $this->formatearMonto($fila['monto'] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Escribe un archivo de forma atómica-lógica y falla si no escribe todo.
     */
    private function escribirArchivo(string $ruta, string $contenido): void {
        $bytes = @file_put_contents($ruta, $contenido, LOCK_EX);
        if ($bytes === false || $bytes !== strlen($contenido)) {
            throw new \RuntimeException('No se pudo escribir el archivo de reporte: ' . $ruta);
        }
    }
}
