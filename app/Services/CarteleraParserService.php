<?php
namespace App\Services;

/**
 * Parser del "Reporte de Cartelera" emitido por Dotwood PC Condominios (Crystal Reports).
 *
 * Extrae, por unidad con deuda: saldo anterior, cuotas por mes y deuda total;
 * además de la fecha de emisión, la fila "Totales" impresa y el conteo por edificio.
 *
 * Es autocontenido (solo funciones estándar de PHP: preg_*, gzuncompress, mb_chr)
 * y tolera las particularidades del PDF de Crystal Reports:
 *  - Streams FlateDecode localizados por firma de objeto (sin depender del xref).
 *  - Fuentes TrueType simples con /Widths y /ToUnicode propios (CMaps bfchar/bfrange).
 *  - Operadores de texto Tf/Tm/Td/TD/Tj/TJ; los Td son relativos a la posición previa
 *    sin avance implícito del texto.
 *  - Valores partidos en piezas contiguas que se fusionan (misma posición o contiguas).
 *  - Columnas monetarias detectadas por clustering de bordes derechos; los meses se
 *    asignan por etiquetas de cabecera (ej. "Ago-2.026").
 *  - Validación de suma por fila y contra la fila "Totales" impresa.
 */
class CarteleraParserService
{
    /** Separación mínima entre bordes para considerar dos columnas distintas. */
    private const TOLERANCIA_GAP_COLUMNA = 500.0;
    /** Distancia máxima para asignar un valor a una columna. */
    private const TOLERANCIA_ASIGNACION = 220.0;
    /** Tolerancia vertical para agrupar ítems de una misma fila. */
    private const TOLERANCIA_FILA = 25.0;
    /** Diferencia máxima admitida al validar sumas de dinero. */
    private const TOLERANCIA_MONTO = 0.011;
    /** Ancho por defecto (unidades de fuente) si un código no está en /Widths. */
    private const ANCHO_FALTANTE = 278.0;

    /** Abreviaturas (y nombres completos) de meses en español. */
    private const MESES = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    /**
     * Parsea el PDF del Reporte de Cartelera.
     *
     * @param string $rutaPdf Ruta absoluta al archivo PDF.
     * @return array{
     *     ok: bool,
     *     errores: string[],
     *     fecha_emision: string|null,
     *     anio: int|null,
     *     meses: int[],
     *     filas: array<int, array{unidad: string, saldo_anterior: float, meses: array<int, float>, total: float}>,
     *     totales: array{saldo_anterior: float, meses: array<int, float>, total: float},
     *     conteo_por_edificio: array<int, int>,
     *     paginas: int
     * }
     */
    public function parsear(string $rutaPdf): array
    {
        if (!is_file($rutaPdf) || !is_readable($rutaPdf)) {
            return $this->respuestaError(['No se pudo leer el archivo PDF (no existe o no es legible).']);
        }

        $raw = @file_get_contents($rutaPdf);
        if ($raw === false || strlen($raw) < 100) {
            return $this->respuestaError(['El archivo está vacío o no se pudo leer.']);
        }
        if (strpos(substr($raw, 0, 1024), '%PDF') === false) {
            return $this->respuestaError(['El archivo no parece un PDF válido (sin cabecera %PDF).']);
        }

        $objetos = $this->indexarObjetos($raw);
        $paginas = $this->obtenerPaginas($objetos);
        if (empty($paginas)) {
            return $this->respuestaError(['No se encontraron páginas en el PDF.']);
        }

        // Extracción de ítems de texto de todas las páginas
        $items = [];
        $cacheFuentes = [];
        foreach ($paginas as $indice => $pagina) {
            $contenido = $this->contenidoDePagina($objetos, $pagina);
            if ($contenido === null || $contenido === '') {
                continue;
            }
            $fuentes = [];
            foreach ($this->obtenerFuentes($objetos, $pagina) as $nombre => $numFuente) {
                $datos = $this->datosFuente($objetos, $numFuente, $cacheFuentes);
                if ($datos !== null) {
                    $fuentes[$nombre] = $datos;
                }
            }
            $items = array_merge($items, $this->extraerItems($contenido, $fuentes, $indice + 1));
        }

        if (empty($items)) {
            return $this->respuestaError(['No se pudo extraer texto del PDF (posible formato no soportado).']);
        }

        $items = $this->fusionarPiezas($items);
        $emision = $this->detectarFechaEmision($items);

        // Valores monetarios con formato venezolano: 1.234,56 (opcional signo negativo)
        $numRe = '/^\s*-?\d{1,3}(?:\.\d{3})*,\d{2}\s*$/';
        $monetarios = [];
        foreach ($items as $it) {
            if (preg_match($numRe, $it['t'])) {
                $monetarios[] = $it;
            }
        }
        if (count($monetarios) < 10) {
            return $this->respuestaError(['No se detectaron valores monetarios en el reporte.']);
        }

        $columnas = $this->agruparColumnas($monetarios);
        if (count($columnas) < 3) {
            return $this->respuestaError([sprintf(
                'Se detectaron %d columnas monetarias; se esperaban al menos 3 (saldo, meses y total).',
                count($columnas)
            )]);
        }

        $etiquetasMes = $this->detectarEtiquetasMes($items);
        $colSaldo = 0;                                   // primera columna: S. Anterior
        $colTotal = count($columnas) - 1;                // última columna: Deuda Total
        $colsMes = array_slice($columnas, 1, $colTotal - 1);

        if (empty($etiquetasMes) || count($colsMes) !== count($etiquetasMes)) {
            return $this->respuestaError([sprintf(
                'No coinciden las columnas de meses detectadas (%d) con las etiquetas de cabecera (%d).',
                count($colsMes),
                count($etiquetasMes)
            )]);
        }

        // Mapa: índice de columna monetaria => número de mes (según orden horizontal)
        $mesPorColumna = [];
        foreach ($colsMes as $i => $col) {
            $mesPorColumna[$i + 1] = $etiquetasMes[$i]['mes'];
        }

        // Construcción de filas: cada valor de la columna Total es un ancla de fila
        $errores = [];
        $filas = [];
        $raras = [];
        $huerfanos = 0;
        $totalesImpresos = null;

        foreach ($items as $indiceAncla => $ancla) {
            if (!preg_match($numRe, $ancla['t']) || $this->columnaDe($ancla, $columnas) !== $colTotal) {
                continue;
            }

            $y = $ancla['y'];
            $pagina = $ancla['p'];
            $celdas = [];
            $etiquetaPartes = [];
            $esTotales = false;

            foreach ($items as $i => $it) {
                if ($i === $indiceAncla || $it['p'] !== $pagina || abs($it['y'] - $y) > self::TOLERANCIA_FILA) {
                    continue;
                }
                $t = trim($it['t']);
                if ($t === '') {
                    continue;
                }

                if (preg_match($numRe, $t)) {
                    $col = $this->columnaDe($it, $columnas);
                    if ($col === null) {
                        $huerfanos++;
                        continue;
                    }
                    if ($col !== $colTotal) {
                        $celdas[$col] = ($celdas[$col] ?? 0.0) + $this->montoDesdeTexto($t);
                    }
                    continue;
                }

                if ($it['x'] < $this->corteEtiqueta($columnas)) {
                    $etiquetaPartes[] = $it;
                }
                if (stripos($t, 'Totales') !== false) {
                    $esTotales = true;
                }
            }

            $total = $this->montoDesdeTexto($ancla['t']);

            if ($esTotales) {
                $totalesImpresos = ['celdas' => $celdas, 'total' => $total];
                continue;
            }

            usort($etiquetaPartes, fn($a, $b) => $a['x'] <=> $b['x']);
            $etiqueta = '';
            foreach ($etiquetaPartes as $parte) {
                $etiqueta .= trim($parte['t']);
            }
            $etiqueta = trim(preg_replace('/(Inmueble|Sector:?)/iu', '', $etiqueta));
            // La cabecera de columna "S. Anterior Bs" puede caer dentro de la banda
            // de la última fila de cada página; se descarta como residuo.
            $etiqueta = trim(preg_replace('/S\.\s*Anterior\s*(?:Bs\.?)?$/iu', '', $etiqueta));

            if (!preg_match('/^\d{1,2}-\d{1,2}$/', $etiqueta)) {
                $raras[] = $etiqueta !== '' ? $etiqueta : '(sin etiqueta)';
                continue;
            }

            $sumaCeldas = 0.0;
            foreach ($celdas as $monto) {
                $sumaCeldas += $monto;
            }
            if (abs($sumaCeldas - $total) >= self::TOLERANCIA_MONTO) {
                if (count($errores) < 30) {
                    $errores[] = sprintf(
                        'Fila %s: la suma de cuotas (%s) no coincide con la deuda total (%s).',
                        $etiqueta,
                        number_format($sumaCeldas, 2, ',', '.'),
                        number_format($total, 2, ',', '.')
                    );
                }
            }

            $meses = [];
            foreach ($mesPorColumna as $idxCol => $mes) {
                if (!empty($celdas[$idxCol])) {
                    $meses[$mes] = round($celdas[$idxCol], 2);
                }
            }

            $filas[] = [
                'unidad' => $etiqueta,
                'saldo_anterior' => round($celdas[$colSaldo] ?? 0.0, 2),
                'meses' => $meses,
                'total' => round($total, 2),
            ];
        }

        if (empty($filas)) {
            $errores[] = 'No se detectaron filas de unidades con deuda.';
        }
        if ($huerfanos > 0) {
            $errores[] = sprintf(
                'Se detectaron %d valores monetarios fuera de las columnas del reporte.',
                $huerfanos
            );
        }
        if (!empty($raras)) {
            $errores[] = sprintf(
                'Se detectaron %d filas con etiqueta no reconocida: %s',
                count($raras),
                implode(', ', array_slice($raras, 0, 10))
            );
        }

        // Totales de salida + validación contra la fila "Totales" impresa
        $totales = ['saldo_anterior' => 0.0, 'meses' => [], 'total' => 0.0];
        $sumaSaldo = 0.0;
        $sumaTotal = 0.0;
        $sumaPorMes = [];
        foreach ($filas as $f) {
            $sumaSaldo += $f['saldo_anterior'];
            $sumaTotal += $f['total'];
            foreach ($f['meses'] as $mes => $valor) {
                $sumaPorMes[$mes] = ($sumaPorMes[$mes] ?? 0.0) + $valor;
            }
        }

        if ($totalesImpresos === null) {
            $errores[] = 'No se encontró la fila "Totales" impresa en el reporte.';
            $totales['saldo_anterior'] = round($sumaSaldo, 2);
            $totales['total'] = round($sumaTotal, 2);
            foreach ($mesPorColumna as $mes) {
                $totales['meses'][$mes] = round($sumaPorMes[$mes] ?? 0.0, 2);
            }
        } else {
            $saldoImpreso = $totalesImpresos['celdas'][$colSaldo] ?? 0.0;
            if (abs($sumaSaldo - $saldoImpreso) >= self::TOLERANCIA_MONTO) {
                $errores[] = sprintf(
                    'Saldo anterior: la suma calculada (%s) no coincide con el total impreso (%s).',
                    number_format($sumaSaldo, 2, ',', '.'),
                    number_format($saldoImpreso, 2, ',', '.')
                );
            }
            foreach ($mesPorColumna as $idxCol => $mes) {
                $impreso = $totalesImpresos['celdas'][$idxCol] ?? 0.0;
                $calculado = $sumaPorMes[$mes] ?? 0.0;
                if (abs($calculado - $impreso) >= self::TOLERANCIA_MONTO) {
                    $errores[] = sprintf(
                        'Mes %02d: la suma calculada (%s) no coincide con el total impreso (%s).',
                        $mes,
                        number_format($calculado, 2, ',', '.'),
                        number_format($impreso, 2, ',', '.')
                    );
                }
            }
            if (abs($sumaTotal - $totalesImpresos['total']) >= self::TOLERANCIA_MONTO) {
                $errores[] = sprintf(
                    'Deuda total: la suma calculada (%s) no coincide con el total impreso (%s).',
                    number_format($sumaTotal, 2, ',', '.'),
                    number_format($totalesImpresos['total'], 2, ',', '.')
                );
            }

            $totales['saldo_anterior'] = round($saldoImpreso, 2);
            $totales['total'] = round($totalesImpresos['total'], 2);
            foreach ($mesPorColumna as $idxCol => $mes) {
                $totales['meses'][$mes] = round($totalesImpresos['celdas'][$idxCol] ?? 0.0, 2);
            }
        }

        if ($emision === null) {
            $errores[] = 'No se detectó la fecha de emisión del reporte.';
        }

        $conteo = [];
        foreach ($filas as $f) {
            $edificio = (int)explode('-', $f['unidad'])[0];
            $conteo[$edificio] = ($conteo[$edificio] ?? 0) + 1;
        }
        ksort($conteo);

        return [
            'ok' => empty($errores),
            'errores' => $errores,
            'fecha_emision' => $emision['fecha'] ?? null,
            'anio' => $emision['anio'] ?? null,
            'meses' => array_map(fn($e) => $e['mes'], $etiquetasMes),
            'filas' => $filas,
            'totales' => $totales,
            'conteo_por_edificio' => $conteo,
            'paginas' => count($paginas),
        ];
    }

    /**
     * Respuesta uniforme para errores tempranos de parseo.
     */
    private function respuestaError(array $errores): array
    {
        return [
            'ok' => false,
            'errores' => $errores,
            'fecha_emision' => null,
            'anio' => null,
            'meses' => [],
            'filas' => [],
            'totales' => ['saldo_anterior' => 0.0, 'meses' => [], 'total' => 0.0],
            'conteo_por_edificio' => [],
            'paginas' => 0,
        ];
    }

    /**
     * Indexa los objetos indirectos del PDF por firma "N 0 obj ... endobj".
     * Para objetos con stream, descomprime su contenido (FlateDecode).
     */
    private function indexarObjetos(string $raw): array
    {
        $objetos = [];
        if (!preg_match_all('/(\d+)\s+0\s+obj(.*?)endobj/s', $raw, $coincidencias, PREG_SET_ORDER)) {
            return $objetos;
        }

        foreach ($coincidencias as $m) {
            $num = (int)$m[1];
            $cuerpo = $m[2];
            $entrada = ['dict' => $cuerpo, 'stream' => null];

            $posStream = strpos($cuerpo, 'stream');
            if ($posStream !== false) {
                $entrada['dict'] = substr($cuerpo, 0, $posStream);
                $datos = substr($cuerpo, $posStream + 6);
                if (substr($datos, 0, 2) === "\r\n") {
                    $datos = substr($datos, 2);
                } elseif ($datos !== '' && ($datos[0] === "\n" || $datos[0] === "\r")) {
                    $datos = substr($datos, 1);
                }
                $fin = strrpos($datos, 'endstream');
                if ($fin !== false) {
                    $datos = substr($datos, 0, $fin);
                }
                $entrada['stream'] = $this->descomprimirStream($datos, $entrada['dict']);
            }

            $objetos[$num] = $entrada;
        }

        return $objetos;
    }

    /**
     * Descomprime un stream FlateDecode (zlib o deflate crudo).
     */
    private function descomprimirStream(string $datos, string $dict): ?string
    {
        if (stripos($dict, 'FlateDecode') === false) {
            return $datos;
        }
        $salida = @gzuncompress($datos);
        if ($salida === false) {
            $salida = @gzinflate($datos);
        }
        return $salida === false ? null : $salida;
    }

    /**
     * Obtiene las páginas del documento (/Type /Page), en orden de objeto.
     */
    private function obtenerPaginas(array $objetos): array
    {
        $paginas = [];
        foreach ($objetos as $num => $obj) {
            if (preg_match('/\/Type\s*\/Page(?![a-zA-Z])/', $obj['dict'])) {
                $paginas[] = ['num' => $num, 'dict' => $obj['dict']];
            }
        }
        usort($paginas, fn($a, $b) => $a['num'] <=> $b['num']);
        return $paginas;
    }

    /**
     * Concatena los streams de contenido declarados en /Contents de una página.
     */
    private function contenidoDePagina(array $objetos, array $pagina): ?string
    {
        $refs = [];
        if (preg_match('/\/Contents\s*\[([^\]]*)\]/', $pagina['dict'], $m)) {
            if (preg_match_all('/(\d+)\s+0\s+R/', $m[1], $rs)) {
                foreach ($rs[1] as $r) {
                    $refs[] = (int)$r;
                }
            }
        } elseif (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $pagina['dict'], $m)) {
            $refs[] = (int)$m[1];
        }

        $contenido = '';
        foreach ($refs as $r) {
            if (isset($objetos[$r]) && $objetos[$r]['stream'] !== null) {
                $contenido .= $objetos[$r]['stream'] . "\n";
            }
        }
        return $contenido === '' ? null : $contenido;
    }

    /**
     * Devuelve el mapa nombre de fuente => número de objeto desde /Resources /Font.
     */
    private function obtenerFuentes(array $objetos, array $pagina): array
    {
        $dict = $pagina['dict'];
        if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $dict, $m) && isset($objetos[(int)$m[1]])) {
            $dict = $objetos[(int)$m[1]]['dict'];
        }

        $fuentes = [];
        if (preg_match('/\/Font\s*<<(.*?)>>/s', $dict, $m)) {
            if (preg_match_all('#/([^\s/<>\[\]]+)\s+(\d+)\s+0\s+R#', $m[1], $rs, PREG_SET_ORDER)) {
                foreach ($rs as $r) {
                    $fuentes[$r[1]] = (int)$r[2];
                }
            }
        } elseif (preg_match('/\/Font\s+(\d+)\s+0\s+R/', $dict, $m) && isset($objetos[(int)$m[1]])) {
            $dictFuente = $objetos[(int)$m[1]]['dict'];
            if (preg_match_all('#/([^\s/<>\[\]]+)\s+(\d+)\s+0\s+R#', $dictFuente, $rs, PREG_SET_ORDER)) {
                foreach ($rs as $r) {
                    $fuentes[$r[1]] = (int)$r[2];
                }
            }
        }
        return $fuentes;
    }

    /**
     * Extrae y cachea los datos de una fuente: CMap /ToUnicode, /Widths y /FirstChar.
     */
    private function datosFuente(array $objetos, int $numFuente, array &$cache): ?array
    {
        if (array_key_exists($numFuente, $cache)) {
            return $cache[$numFuente];
        }

        $datos = null;
        if (isset($objetos[$numFuente])) {
            $dict = $objetos[$numFuente]['dict'];

            $cmap = [];
            if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $dict, $m)) {
                $numCmap = (int)$m[1];
                if (isset($objetos[$numCmap]) && $objetos[$numCmap]['stream'] !== null) {
                    $cmap = $this->parsearCmap($objetos[$numCmap]['stream']);
                }
            }

            $widths = [];
            if (preg_match('/\/Widths\s+(\d+)\s+0\s+R/', $dict, $m) && isset($objetos[(int)$m[1]]['dict'])) {
                if (preg_match('/\[(.*)\]/s', $objetos[(int)$m[1]]['dict'], $wm)) {
                    $widths = $this->numerosDeArray($wm[1]);
                }
            } elseif (preg_match('/\/Widths\s*\[(.*?)\]/s', $dict, $wm)) {
                $widths = $this->numerosDeArray($wm[1]);
            }

            $firstChar = 33;
            if (preg_match('/\/FirstChar\s+(\d+)/', $dict, $m)) {
                $firstChar = (int)$m[1];
            }

            $datos = ['cmap' => $cmap, 'widths' => $widths, 'firstChar' => $firstChar];
        }

        $cache[$numFuente] = $datos;
        return $datos;
    }

    /**
     * Extrae la lista de números de un array PDF ("665 556 556 ...").
     */
    private function numerosDeArray(string $texto): array
    {
        if (!preg_match_all('/-?[\d.]+/', $texto, $nums)) {
            return [];
        }
        return array_map('floatval', $nums[0]);
    }

    /**
     * Parsea un CMap /ToUnicode: bloques bfrange (con destino simple o array) y bfchar.
     */
    private function parsearCmap(string $s): array
    {
        $map = [];

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $s, $bloques)) {
            foreach ($bloques[1] as $bloque) {
                if (!preg_match_all(
                    '/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*(?:<([0-9a-fA-F]+)>|\[((?:\s*<[0-9a-fA-F]+>\s*)+)\])/',
                    $bloque,
                    $entradas,
                    PREG_SET_ORDER
                )) {
                    continue;
                }
                foreach ($entradas as $e) {
                    $lo = hexdec($e[1]);
                    $hi = hexdec($e[2]);
                    if (!empty($e[4])) {
                        // Destinos explícitos por índice dentro del array
                        preg_match_all('/<([0-9a-fA-F]+)>/', $e[4], $destinos);
                        $i = 0;
                        foreach ($destinos[1] as $destino) {
                            if ($lo + $i > $hi) {
                                break;
                            }
                            $map[$lo + $i] = mb_chr(hexdec($destino), 'UTF-8');
                            $i++;
                        }
                    } else {
                        $destinoBase = hexdec($e[3]);
                        for ($c = $lo; $c <= $hi; $c++) {
                            $map[$c] = mb_chr($destinoBase + ($c - $lo), 'UTF-8');
                        }
                    }
                }
            }
        }

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $s, $bloques)) {
            foreach ($bloques[1] as $bloque) {
                if (!preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $bloque, $entradas, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($entradas as $e) {
                    $map[hexdec($e[1])] = mb_chr(hexdec($e[2]), 'UTF-8');
                }
            }
        }

        return $map;
    }

    /**
     * Decodifica el contenido de una página en ítems de texto posicionados.
     */
    private function extraerItems(string $contenido, array $fuentes, int $pagina): array
    {
        $items = [];
        $font = null;
        $tamano = 0.0;
        $cx = 0.0;
        $cy = 0.0;

        $lineas = preg_split('/\r\n|\r|\n/', $contenido);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }

            if (preg_match('#^/([^\s/]+)\s+([\d.]+)\s+Tf$#', $linea, $tf)) {
                $font = $tf[1];
                $tamano = (float)$tf[2];
                continue;
            }
            if (preg_match('#^(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+Tm$#', $linea, $tm)) {
                $cx = (float)$tm[5];
                $cy = (float)$tm[6];
                continue;
            }
            if (preg_match('#^(-?[\d.]+)\s+(-?[\d.]+)\s+T[dD]$#', $linea, $td)) {
                // Td/TD son relativos a la posición previa; sin avance implícito del texto
                $cx += (float)$td[1];
                $cy += (float)$td[2];
                continue;
            }

            $fuente = ($font !== null) ? ($fuentes[$font] ?? null) : null;
            if ($fuente === null) {
                continue;
            }

            if (strpos($linea, 'TJ') !== false && preg_match('/\[(.*)\]\s*TJ/u', $linea, $arr)) {
                [$texto, $ancho] = $this->interpretarArregloTJ($arr[1], $fuente, $tamano);
                if ($texto !== '') {
                    $items[] = ['p' => $pagina, 'x' => $cx, 'y' => $cy, 'end' => $cx + $ancho, 't' => $texto];
                }
                continue;
            }
            if (strpos($linea, 'Tj') !== false && preg_match('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/u', $linea, $ss)) {
                $bytes = $this->unescapePdfString($ss[1]);
                $texto = $this->decodificarBytes($bytes, $fuente['cmap']);
                if ($texto !== '') {
                    $items[] = [
                        'p' => $pagina,
                        'x' => $cx,
                        'y' => $cy,
                        'end' => $cx + $this->anchoCodigos($bytes, $fuente, $tamano),
                        't' => $texto,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Interpreta el contenido de un arreglo TJ: strings decodificados y ajustes de kerning.
     *
     * @return array{0: string, 1: float} Texto decodificado y ancho acumulado.
     */
    private function interpretarArregloTJ(string $interno, array $fuente, float $tamano): array
    {
        $texto = '';
        $ancho = 0.0;
        $off = 0;
        $largo = strlen($interno);

        while ($off < $largo) {
            if ($interno[$off] === '(') {
                $j = $off + 1;
                $crudo = '';
                while ($j < $largo) {
                    $c = $interno[$j];
                    if ($c === '\\') {
                        $crudo .= $c . ($interno[$j + 1] ?? '');
                        $j += 2;
                        continue;
                    }
                    if ($c === ')') {
                        break;
                    }
                    $crudo .= $c;
                    $j++;
                }
                $bytes = $this->unescapePdfString($crudo);
                $texto .= $this->decodificarBytes($bytes, $fuente['cmap']);
                $ancho += $this->anchoCodigos($bytes, $fuente, $tamano);
                $off = $j + 1;
            } elseif (preg_match('/^\s*(-?[\d.]+)/', substr($interno, $off), $num)) {
                $ancho += -((float)$num[1]) * $tamano / 1000.0;
                $off += strlen($num[0]);
            } else {
                $off++;
            }
        }

        return [$texto, $ancho];
    }

    /**
     * Traduce bytes de una fuente simple a texto usando su CMap /ToUnicode.
     */
    private function decodificarBytes(string $bytes, array $cmap): string
    {
        $texto = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $texto .= $cmap[ord($bytes[$i])] ?? '.';
        }
        return $texto;
    }

    /**
     * Ancho de una cadena de bytes según /Widths y /FirstChar de la fuente.
     */
    private function anchoCodigos(string $bytes, array $fuente, float $tamano): float
    {
        $ancho = 0.0;
        $widths = $fuente['widths'];
        $firstChar = $fuente['firstChar'];
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $indice = ord($bytes[$i]) - $firstChar;
            $ancho += $widths[$indice] ?? self::ANCHO_FALTANTE;
        }
        return $ancho * $tamano / 1000.0;
    }

    /**
     * Aplica las secuencias de escape de una cadena literal PDF.
     */
    private function unescapePdfString(string $s): string
    {
        $salida = '';
        $i = 0;
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if ($c === '\\' && $i + 1 < $n) {
                $sig = $s[$i + 1];
                if ($sig >= '0' && $sig <= '7') {
                    $oct = '';
                    $j = $i + 1;
                    while ($j < $n && $j < $i + 4 && $s[$j] >= '0' && $s[$j] <= '7') {
                        $oct .= $s[$j];
                        $j++;
                    }
                    $salida .= chr(octdec($oct) & 0xFF);
                    $i = $j;
                    continue;
                }
                $salida .= $sig;
                $i += 2;
                continue;
            }
            $salida .= $c;
            $i++;
        }
        return $salida;
    }

    /**
     * Fusiona piezas de texto que Crystal Report emite por separado:
     *  - En la misma posición exacta (el visor la dibuja a continuación del avance).
     *  - Contiguas (el borde derecho de una precede al inicio de la otra).
     */
    private function fusionarPiezas(array $items): array
    {
        $fusionados = [];
        foreach ($items as $it) {
            $n = count($fusionados);
            if ($n > 0) {
                $ultimo = $fusionados[$n - 1];
                $mismoLugar = ($ultimo['p'] === $it['p']
                    && abs($it['y'] - $ultimo['y']) <= 3
                    && abs($it['x'] - $ultimo['x']) <= 2);
                $continuacion = ($ultimo['p'] === $it['p']
                    && abs($it['y'] - $ultimo['y']) <= 3
                    && abs($it['x'] - $ultimo['end']) <= 60);

                if ($mismoLugar) {
                    $ultimo['t'] .= $it['t'];
                    $ultimo['end'] = $ultimo['end'] + ($it['end'] - $it['x']);
                    $fusionados[$n - 1] = $ultimo;
                    continue;
                }
                if ($continuacion) {
                    $ultimo['t'] .= $it['t'];
                    $ultimo['end'] = $it['end'];
                    $fusionados[$n - 1] = $ultimo;
                    continue;
                }
            }
            $fusionados[] = $it;
        }
        return $fusionados;
    }

    /**
     * Detecta la fecha de emisión del reporte (dd/mm/aaaa, junto a "Fecha emisión:").
     *
     * @return array{fecha: string, anio: int}|null
     */
    private function detectarFechaEmision(array $items): ?array
    {
        $etiqueta = null;
        foreach ($items as $it) {
            if (preg_match('/fecha/iu', $it['t']) && preg_match('/emisi/iu', $it['t'])) {
                $etiqueta = $it;
                break;
            }
        }

        $candidatas = [];
        foreach ($items as $it) {
            if (preg_match('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', $it['t'], $d)) {
                $candidatas[] = ['item' => $it, 'd' => $d];
            }
        }
        if (empty($candidatas)) {
            return null;
        }

        $elegida = $candidatas[0];
        if ($etiqueta !== null) {
            foreach ($candidatas as $c) {
                if ($c['item']['p'] === $etiqueta['p']
                    && abs($c['item']['y'] - $etiqueta['y']) <= 12
                    && $c['item']['x'] > $etiqueta['x']) {
                    $elegida = $c;
                    break;
                }
            }
        }

        $dia = (int)$elegida['d'][1];
        $mes = (int)$elegida['d'][2];
        $anio = (int)$elegida['d'][3];
        if (!checkdate($mes, $dia, $anio)) {
            return null;
        }

        return [
            'fecha' => sprintf('%04d-%02d-%02d', $anio, $mes, $dia),
            'anio' => $anio,
        ];
    }

    /**
     * Agrupa los bordes derechos de los valores monetarios en columnas por clustering.
     *
     * @param array $monetarios Ítems con texto en formato monetario.
     * @return array<int, array{desde: float, hasta: float, conteo: int, centro: float}>
     */
    private function agruparColumnas(array $monetarios): array
    {
        $bordes = array_map(fn($it) => $it['end'], $monetarios);
        sort($bordes, SORT_NUMERIC);

        $grupos = [];
        foreach ($bordes as $borde) {
            $n = count($grupos);
            if ($n > 0 && ($borde - $grupos[$n - 1]['hasta']) <= self::TOLERANCIA_GAP_COLUMNA) {
                $grupos[$n - 1]['hasta'] = $borde;
                $grupos[$n - 1]['conteo']++;
                continue;
            }
            $grupos[] = ['desde' => $borde, 'hasta' => $borde, 'conteo' => 1];
        }

        foreach ($grupos as &$g) {
            $g['centro'] = ($g['desde'] + $g['hasta']) / 2.0;
        }
        unset($g);

        // No se descartan agrupaciones: si aparece una columna inesperada, la
        // validación de meses/columnas falla y la importación se bloquea (fail closed).
        usort($grupos, fn($a, $b) => $a['centro'] <=> $b['centro']);

        return array_values($grupos);
    }

    /**
     * Índice de la columna a la que pertenece un ítem monetario (o null si queda fuera).
     */
    private function columnaDe(array $item, array $columnas): ?int
    {
        $mejor = null;
        $distancia = PHP_FLOAT_MAX;
        foreach ($columnas as $i => $col) {
            if ($item['end'] < $col['desde']) {
                $d = $col['desde'] - $item['end'];
            } elseif ($item['end'] > $col['hasta']) {
                $d = $item['end'] - $col['hasta'];
            } else {
                $d = 0.0;
            }
            if ($d < $distancia) {
                $distancia = $d;
                $mejor = $i;
            }
        }
        return ($distancia <= self::TOLERANCIA_ASIGNACION) ? $mejor : null;
    }

    /**
     * Etiquetas de mes de cabecera ("Ago-2.026"), deduplicadas y ordenadas por x.
     *
     * @return array<int, array{mes: int, etiqueta: string, x: float}>
     */
    private function detectarEtiquetasMes(array $items): array
    {
        $patron = '/^(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)-(\d{1,3}(?:\.\d{3})*)$/iu';
        $vistos = [];
        $etiquetas = [];
        foreach ($items as $it) {
            $t = trim($it['t']);
            if (!preg_match($patron, $t, $m)) {
                continue;
            }
            $abreviatura = mb_substr(mb_strtolower($m[1], 'UTF-8'), 0, 3);
            $mes = self::MESES[$abreviatura] ?? null;
            if ($mes === null || isset($vistos[$mes])) {
                continue;
            }
            $vistos[$mes] = true;
            $etiquetas[] = ['mes' => $mes, 'etiqueta' => $t, 'x' => $it['x']];
        }
        usort($etiquetas, fn($a, $b) => $a['x'] <=> $b['x']);
        return $etiquetas;
    }

    /**
     * Borde derecho máximo para considerar un ítem como parte de la columna de etiquetas.
     * Las etiquetas del reporte se alinean a la izquierda, muy lejos de la primera
     * columna monetaria; la cabecera de la tabla cae en medio de ese margen.
     */
    private function corteEtiqueta(array $columnas): float
    {
        return $columnas[0]['desde'] - 1500.0;
    }

    /**
     * Convierte un texto monetario venezolano ("1.234,56") a float.
     */
    private function montoDesdeTexto(string $texto): float
    {
        $texto = trim($texto);
        $negativo = str_starts_with($texto, '-');
        $limpio = str_replace(['-', '.'], '', $texto);
        $limpio = str_replace(',', '.', $limpio);
        $valor = (float)$limpio;
        return $negativo ? -$valor : $valor;
    }
}
