<?php
namespace App\Services;

class GastoParserService {

    /**
     * Mapeo de reglas heurísticas de clasificación por palabras clave.
     */
    private array $reglasCategorias = [
        'servicios_basicos' => [
            'keywords' => ['corpoelec', 'electricidad', 'luz', 'hidrocentro', 'agua', 'gas', 'cantv', 'internet', 'fibra', 'energia', 'aseo'],
            'preferencia' => ['servicios básicos', 'servicios', 'servicios publicos', 'electricidad']
        ],
        'vigilancia' => [
            'keywords' => ['vigilancia', 'seguridad', 'guardia', 'garita', 'alarma', 'camara', 'custodia', 'sereno', 'circuito cerrado', 'cctv'],
            'preferencia' => ['vigilancia', 'seguridad']
        ],
        'mantenimiento' => [
            'keywords' => ['bomba', 'bombas', 'hidroneumatico', 'ascensor', 'ascensores', 'elevador', 'pintura', 'impermeabiliz', 'porton', 'jardin', 'piscina', 'bombillo', 'tuberia', 'reparacion', 'plomeria', 'herreria', 'albanileria', 'filtro', 'cloro'],
            'preferencia' => ['mantenimiento', 'reparaciones']
        ],
        'administrativos' => [
            'keywords' => ['papeleria', 'abogado', 'contador', 'auditoria', 'banco', 'comision', 'honorarios', 'administracion', 'fotocopia', 'impresion', 'software', 'resma'],
            'preferencia' => ['administrativos', 'gastos administrativos', 'administracion']
        ],
        'reserva' => [
            'keywords' => ['reserva', 'imprevisto', 'fondo', 'contingencia', 'ahorro'],
            'preferencia' => ['fondo de reserva', 'imprevistos', 'reserva']
        ]
    ];

    /**
     * Extrae el texto plano de un PDF agrupándolo por número de página.
     * Opera 100% en PHP nativo decodificando flujos /FlateDecode (RF 32).
     *
     * @param string $rutaPdf
     * @return array [numero_pagina => string texto_pagina]
     */
    public function extraerPaginasTextoPdf(string $rutaPdf): array {
        if (!file_exists($rutaPdf) || !is_readable($rutaPdf)) {
            error_log("[GastoParserService] Archivo PDF no encontrado o ilegible: {$rutaPdf}");
            return [];
        }

        $contenido = file_get_contents($rutaPdf);
        if ($contenido === false || strlen($contenido) < 10) {
            return [];
        }

        $paginas = [];

        // 1. Extraer objetos del PDF: id gen obj ... endobj
        $objetos = [];
        if (preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)endobj/s', $contenido, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $objId = intval($m[1]);
                $objetos[$objId] = $m[3];
            }
        }

        // 2. Localizar objetos de tipo Page (/Type /Page)
        $paginaObjIds = [];
        foreach ($objetos as $objId => $cuerpo) {
            if (preg_match('/\/Type\s*\/Page\b/', $cuerpo) && !preg_match('/\/Type\s*\/Pages\b/', $cuerpo)) {
                $paginaObjIds[] = $objId;
            }
        }

        // Si se detectaron páginas estructuradas
        if (!empty($paginaObjIds)) {
            $numPagina = 1;
            foreach ($paginaObjIds as $pageObjId) {
                $cuerpoPagina = $objetos[$pageObjId] ?? '';
                $streamIds = [];

                // Buscar /Contents: puede ser referencia simple '12 0 R' o array '[12 0 R 13 0 R]'
                if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $cuerpoPagina, $contMatch)) {
                    $streamIds[] = intval($contMatch[1]);
                } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $cuerpoPagina, $contArrayMatch)) {
                    if (preg_match_all('/(\d+)\s+\d+\s+R/', $contArrayMatch[1], $streamRefs)) {
                        foreach ($streamRefs[1] as $refId) {
                            $streamIds[] = intval($refId);
                        }
                    }
                }

                $textoPagina = '';
                foreach ($streamIds as $sId) {
                    if (isset($objetos[$sId])) {
                        $textoPagina .= ' ' . $this->decodificarFlujoTexto($objetos[$sId]);
                    }
                }

                $paginas[$numPagina] = trim($textoPagina);
                $numPagina++;
            }
        }

        // Fallback: si no se pudo segmentar por objetos /Page, procesar todos los streams secuenciales
        if (empty($paginas) || empty(trim(implode('', $paginas)))) {
            $textoGlobal = '';
            if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $contenido, $streamMatches)) {
                foreach ($streamMatches[1] as $rawStream) {
                    $textoGlobal .= ' ' . $this->extraerTextoDeStream($rawStream);
                }
            }
            if (empty(trim($textoGlobal))) {
                preg_match_all('/[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\.,:\\/\-]{3,}/', $contenido, $plainMatches);
                $textoGlobal = implode(' ', $plainMatches[0] ?? []);
            }
            $paginas[1] = trim($textoGlobal);
        }

        return $paginas;
    }

    /**
     * Decodifica un objeto PDF que contiene un stream y extrae los operadores de texto.
     */
    private function decodificarFlujoTexto(string $cuerpoObjeto): string {
        if (!preg_match('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $cuerpoObjeto, $m)) {
            return '';
        }
        return $this->extraerTextoDeStream($m[1]);
    }

    /**
     * Descomprime un stream binario de PDF y extrae texto de operadores Tj / TJ.
     */
    private function extraerTextoDeStream(string $stream): string {
        $descomprimido = @gzuncompress($stream);
        if ($descomprimido === false) {
            $descomprimido = @gzinflate($stream);
        }
        $data = ($descomprimido !== false) ? $descomprimido : $stream;
        $texto = '';

        // Operador estándar (texto) Tj
        if (preg_match_all('/\((.*?)\)\s*Tj/s', $data, $textMatches)) {
            $texto .= ' ' . implode(' ', $textMatches[1]);
        }

        // Operador array [(t)(e)(x)(t)(o)] TJ
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $data, $tjMatches)) {
            foreach ($tjMatches[1] as $tj) {
                if (preg_match_all('/\((.*?)\)/s', $tj, $subMatches)) {
                    $texto .= ' ' . implode('', $subMatches[1]);
                }
            }
        }

        // Si no hubo operadores Tj/TJ directos, extraer texto plano legible
        if (empty(trim($texto))) {
            preg_match_all('/[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\.,:\\/\-]{3,}/', $data, $plain);
            $texto = implode(' ', $plain[0] ?? []);
        }

        // Limpiar secuencias de escape de PDF \( \) \\
        $texto = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $texto);

        return $texto;
    }

    /**
     * Analiza texto plano o fragmentado identificando renglones de gastos.
     *
     * @param string $texto Texto extraído de la página o documento
     * @param int $pagina Número de página asociado
     * @param array $categorias Categorías activas en el sistema [{'id', 'nombre'}]
     * @param int|null $mes Mes del período para inferencia de fechas
     * @param int|null $anio Año del período para inferencia de fechas
     * @return array Renglones estructurados detectados
     */
    public function analizarLineasGastos(
        string $texto,
        int $pagina = 1,
        array $categorias = [],
        ?int $mes = null,
        ?int $anio = null
    ): array {
        $mes = $mes ?: intval(date('n'));
        $anio = $anio ?: intval(date('Y'));

        // Normalizar separadores de línea
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        // Si el texto viene en un solo bloque continuo sin saltos de línea (típico de PDFs con streams lineales),
        // segmentar únicamente si hay múltiples fechas explícitas o números de renglón
        if (substr_count($texto, "\n") < 2 && strlen($texto) > 40) {
            $texto = preg_replace('/(?<=\S)\s+(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', "\n$1", $texto);
            $texto = preg_replace('/(?<=\S)\s+(\b(?:rengl[oó]n|item)\s*#?\s*\d+\b)/i', "\n$1", $texto);
        }

        $lineas = explode("\n", $texto);
        $renglones = [];

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if (strlen($lineaTrim) < 8) {
                continue;
            }

            $datos = $this->extraerGastoDeLinea($lineaTrim, $pagina, $categorias, $mes, $anio);
            if ($datos !== null) {
                $renglones[] = $datos;
            }
        }

        return $renglones;
    }

    /**
     * Extrae y estructura un gasto individual a partir de una línea de texto.
     */
    public function extraerGastoDeLinea(
        string $linea,
        int $pagina,
        array $categorias,
        int $mes,
        int $anio
    ): ?array {
        // 1. Extraer Monto: soporte formato venezolano (1.250,50 o 450,20) o decimal estándar (1250.50 o 450.20)
        $monto = null;
        $montoPattern = '/(?:Bs\.?|USD|\$)?\s*(?:(\d{1,3}(?:\.\d{3})+|\d+),(\d{2})|(\d+)\.(\d{2}))(?!\d)/i';

        if (preg_match_all($montoPattern, $linea, $montoMatches, PREG_SET_ORDER)) {
            // Tomar el último monto de la línea (generalmente la columna total)
            $ultimoMonto = end($montoMatches);
            if (!empty($ultimoMonto[2])) {
                // Formato venezolano con coma decimal
                $entero = str_replace('.', '', $ultimoMonto[1]);
                $monto = floatval($entero . '.' . $ultimoMonto[2]);
            } elseif (!empty($ultimoMonto[4])) {
                // Formato decimal con punto
                $monto = floatval($ultimoMonto[3] . '.' . $ultimoMonto[4]);
            }
        }

        // Si no hay monto financiero positivo, descartar (cabeceras, notas, etc.)
        if ($monto === null || $monto <= 0) {
            return null;
        }

        // 2. Extraer Fecha
        $fechaGasto = sprintf('%04d-%02d-01', $anio, $mes);
        if (preg_match('/(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $linea, $fechaMatchIso)) {
            $anioDetectado = intval($fechaMatchIso[1]);
            $mesDetectado = intval($fechaMatchIso[2]);
            $dia = intval($fechaMatchIso[3]);

            if (checkdate($mesDetectado, $dia, $anioDetectado)) {
                $fechaGasto = sprintf('%04d-%02d-%02d', $anioDetectado, $mesDetectado, $dia);
            }
        } elseif (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/', $linea, $fechaMatch)) {
            $dia = intval($fechaMatch[1]);
            $mesDetectado = intval($fechaMatch[2]);
            $anioDetectado = intval($fechaMatch[3]);

            if ($anioDetectado < 100) {
                $anioDetectado += 2000;
            }

            if (checkdate($mesDetectado, $dia, $anioDetectado)) {
                $fechaGasto = sprintf('%04d-%02d-%02d', $anioDetectado, $mesDetectado, $dia);
            }
        }

        // 3. Extraer Nro. de Factura / Control
        $nroFactura = null;
        if (preg_match('/(?:fac(?:tura)?|control|nro\.?|ref\.?|doc\.?|fact\.)[:\s#]*([a-zA-Z0-9\-_]+)/i', $linea, $facMatch)) {
            $nroFactura = trim($facMatch[1]);
        }

        // 4. Cadena limpia de montos y fechas para aislar proveedor y descripción
        $lineaLimpia = preg_replace('/(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/', '', $linea);
        $lineaLimpia = preg_replace('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', '', $lineaLimpia);
        if ($nroFactura) {
            $lineaLimpia = preg_replace('/(?:fac(?:tura)?|control|nro\.?|ref\.?|doc\.?|fact\.)[:\s#]*' . preg_quote($nroFactura, '/') . '/i', '', $lineaLimpia);
        }
        $lineaLimpia = preg_replace($montoPattern, '', $lineaLimpia);
        $lineaLimpia = trim(preg_replace('/\s+/', ' ', $lineaLimpia));

        // 5. Extraer Proveedor
        $proveedor = 'Gasto General';
        if (preg_match('/([A-ZÁÉÍÓÚÑa-záéíóúñ0-9\s\.\-&]+(?:C\.A\.|S\.A\.|S\.R\.L\.|C\.A|S\.A|CORPOELEC|HIDROCENTRO|CANTV|Ferreter[ií]a|Servicios|Inversiones|Comercializadora))/i', $lineaLimpia, $provMatch)) {
            $proveedor = trim($provMatch[1]);
        } elseif (preg_match('/^[A-ZÁÉÍÓÚÑa-záéíóúñ\s]{3,35}(?=\s*[\-|–|:]|\s+\d)/i', $lineaLimpia, $provMatch2)) {
            $proveedor = trim($provMatch2[0]);
        } elseif (preg_match('/^[A-ZÁÉÍÓÚÑa-záéíóúñ0-9\.\-]{3,30}/i', $lineaLimpia, $provMatch3)) {
            $proveedor = trim($provMatch3[0]);
        }

        // 6. Extraer Descripción
        $descripcion = trim(str_ireplace($proveedor, '', $lineaLimpia));
        $descripcion = trim(preg_replace('/^[\-|–|:\s]+/', '', $descripcion));
        if (mb_strlen($descripcion) < 4) {
            $descripcion = "Gasto correspondiente a {$proveedor}";
        }
        if (mb_strlen($descripcion) > 250) {
            $descripcion = mb_substr($descripcion, 0, 247) . '...';
        }

        // 7. Inferir Categoría Semántica
        $categoriaId = $this->inferirCategoriaId($linea, $categorias);

        return [
            'fecha_gasto'           => $fechaGasto,
            'proveedor'             => mb_substr($proveedor, 0, 150),
            'nro_factura_proveedor' => $nroFactura ? mb_substr($nroFactura, 0, 100) : null,
            'descripcion'           => $descripcion,
            'monto_total'           => round($monto, 2),
            'categoria_id'          => $categoriaId,
            'pagina_soporte'        => max(1, $pagina),
            'extracto_texto'        => mb_substr(trim($linea), 0, 500)
        ];
    }

    /**
     * Infiere la categoría más adecuada buscando coincidencias de palabras clave.
     */
    private function inferirCategoriaId(string $linea, array $categorias): int {
        if (empty($categorias)) {
            return 1;
        }

        $lineaLower = mb_strtolower($linea);

        foreach ($this->reglasCategorias as $claveRegla => $regla) {
            foreach ($regla['keywords'] as $kw) {
                if (mb_strpos($lineaLower, $kw) !== false) {
                    // Buscar coincidencia en la lista real de categorías
                    foreach ($categorias as $cat) {
                        $catNomLower = mb_strtolower($cat['nombre']);
                        foreach ($regla['preferencia'] as $pref) {
                            if (mb_strpos($catNomLower, $pref) !== false) {
                                return intval($cat['id']);
                            }
                        }
                    }
                }
            }
        }

        // Si no hubo coincidencia semántica, asignar la primera categoría activa
        return intval($categorias[0]['id'] ?? 1);
    }

    /**
     * Procesa un PDF Maestro completo de gastos y retorna los renglones extraídos.
     *
     * @param string $rutaPdf Ruta al archivo PDF en el servidor
     * @param int $mes Mes del período
     * @param int $anio Año del período
     * @param array $categorias Lista de categorías activas
     * @return array ['exito' => bool, 'total' => int, 'suma' => float, 'renglones' => array, 'paginas' => int]
     */
    public function procesarPdfMaestro(string $rutaPdf, int $mes, int $anio, array $categorias = []): array {
        $paginas = $this->extraerPaginasTextoPdf($rutaPdf);
        $todosLosRenglones = [];
        $sumaTotal = 0.0;

        foreach ($paginas as $numPagina => $textoPagina) {
            $renglonesPagina = $this->analizarLineasGastos($textoPagina, $numPagina, $categorias, $mes, $anio);
            foreach ($renglonesPagina as $r) {
                $todosLosRenglones[] = $r;
                $sumaTotal += $r['monto_total'];
            }
        }

        return [
            'exito'     => count($todosLosRenglones) > 0,
            'total'     => count($todosLosRenglones),
            'suma'      => round($sumaTotal, 2),
            'renglones' => $todosLosRenglones,
            'paginas'   => count($paginas)
        ];
    }
}
