<?php
namespace App\Services;

class ComprobanteParserService {

    /**
     * Extrae texto plano de un archivo PDF analizando sus flujos de datos internos.
     * Soporta flujos con codificación /FlateDecode (gzip/deflate).
     *
     * Limitaciones conocidas:
     * - No soporta PDFs cifrados o con contraseña.
     * - No soporta flujos con filtros distintos a /FlateDecode (LZW, JBIG2, etc.).
     * - Si la extracción falla, se registra en error_log para monitoreo y mejora continua.
     *
     * @param string $rutaPdf Ruta al archivo PDF en disco
     * @return string Texto extraído del documento (vacío si no se pudo extraer)
     */
    public function extraerTextoDePdf(string $rutaPdf): string {
        if (!file_exists($rutaPdf)) {
            error_log("[ComprobanteParserService] Archivo no encontrado: {$rutaPdf}");
            return '';
        }

        $contenido = file_get_contents($rutaPdf);
        if ($contenido === false) {
            error_log("[ComprobanteParserService] No se pudo leer el archivo: {$rutaPdf}");
            return '';
        }

        // Verificar tamaño mínimo para evitar procesamiento de archivos corruptos
        if (strlen($contenido) < 10) {
            error_log("[ComprobanteParserService] Archivo demasiado pequeño o corrupto: {$rutaPdf}");
            return '';
        }

        $textoExtraido = '';
        $streamsEncontrados = 0;
        $streamsDecodificados = 0;

        // Buscar todos los bloques de flujo "stream ... endstream"
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $contenido, $matches)) {
            $streamsEncontrados = count($matches[1]);

            foreach ($matches[1] as $stream) {
                // Intentar descompresión FlateDecode (gzip / deflate)
                $descomprimido = @gzuncompress($stream);
                if ($descomprimido === false) {
                    $descomprimido = @gzinflate($stream);
                }

                $data = ($descomprimido !== false) ? $descomprimido : $stream;
                if ($descomprimido !== false) {
                    $streamsDecodificados++;
                }

                // Extraer texto de operadores estándar de PDF: (texto) Tj
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $data, $textMatches)) {
                    $textoExtraido .= ' ' . implode(' ', $textMatches[1]);
                }
                // Extraer texto de arrays PDF: [(t)(e)(x)(t)(o)] TJ
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $data, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\((.*?)\)/s', $tj, $subMatches)) {
                            $textoExtraido .= ' ' . implode('', $subMatches[1]);
                        }
                    }
                }
            }
        }

        // Si no se extrajeron operadores estructurados, buscar cadenas legibles de texto plano
        if (empty(trim($textoExtraido))) {
            preg_match_all('/[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\.,:\\/\-]{3,}/', $contenido, $plainMatches);
            $textoExtraido = implode(' ', $plainMatches[0] ?? []);
        }

        // Registrar en error_log si el resultado fue insatisfactorio para monitoreo y mejora continua
        $textoFinal = trim($textoExtraido);
        if (empty($textoFinal) && $streamsEncontrados > 0) {
            error_log(sprintf(
                "[ComprobanteParserService] Extracción incompleta en '%s': %d stream(s) encontrado(s), %d decodificado(s). " .
                "El PDF puede usar filtros no soportados (cifrado, LZW, JBIG2). " .
                "El usuario deberá ingresar los datos manualmente.",
                basename($rutaPdf),
                $streamsEncontrados,
                $streamsDecodificados
            ));
        } elseif (empty($textoFinal)) {
            error_log(sprintf(
                "[ComprobanteParserService] No se encontraron streams en '%s'. " .
                "El archivo puede no ser un PDF válido o ser una imagen escaneada.",
                basename($rutaPdf)
            ));
        }

        return $textoFinal;
    }

    /**
     * Analiza una cadena de texto buscando patrones bancarios venezolanos
     * para sugerir campos del formulario de pago.
     *
     * Nota sobre detección de montos:
     * - Formato venezolano: 1.250,50 (punto como separador de miles, coma decimal)
     * - Formato internacional: 1250.50 (punto decimal)
     * El parser detecta ambos formatos correctamente antes de hacer la conversión.
     *
     * @param string $texto
     * @return array ['banco' => ?string, 'referencia' => ?string, 'monto' => ?float, 'fecha' => ?string, 'detectado' => bool]
     */
    public function analizarTexto(string $texto): array {
        $resultado = [
            'banco'                  => null,
            'banco_pagador'          => null,
            'banco_receptor'         => null,
            'cuenta_destino_prefijo' => null,
            'metodo_pago'            => null,
            'referencia'             => null,
            'monto'                  => null,
            'fecha'                  => null,
            'detectado'              => false
        ];

        if (empty(trim($texto))) {
            return $resultado;
        }

        // 1. Detección de Método de Pago
        if (preg_match('/(?:pago\s*m[oó]vil|pagomovil|p2p|c2p)/iu', $texto)) {
            $resultado['metodo_pago'] = 'pago_movil';
            $resultado['detectado'] = true;
        } elseif (preg_match('/(?:transferencia|traspaso|d[eé]bito\s+en\s+cuenta|cr[eé]dito\s+en\s+cuenta)/iu', $texto)) {
            $resultado['metodo_pago'] = 'transferencia';
            $resultado['detectado'] = true;
        }

        // 2. Detección de Banco Pagador / Emisor
        $bancos = [
            'venezuela'  => ['venezuela', 'banco de venezuela', 'bdv', '0102'],
            'mercantil'  => ['mercantil', 'banco mercantil', '0105'],
            'banesco'    => ['banesco', 'banco universal banesco', '0134'],
            'provincial' => ['provincial', 'bbva', 'bbva provincial', '0108'],
            'bancamiga'  => ['bancamiga', '0172'],
            'bnc'        => ['bnc', 'nacional de credito', '0191'],
            'bancaribe'  => ['bancaribe', 'caribe', '0114'],
            'tesoro'     => ['tesoro', 'banco del tesoro', '0163'],
            'exterior'   => ['exterior', 'banco exterior', '0115'],
            'plaza'      => ['plaza', 'banco plaza', '0138'],
            'activo'     => ['activo', 'banco activo', '0171'],
            'sofitasa'   => ['sofitasa', '0137'],
            '100banco'   => ['100% banco', '100banco', '0156'],
            'bfc'        => ['fondo comun', 'bfc', '0151'],
            'pago_movil' => ['pago movil', 'pagomovil', 'c2p', 'p2p']
        ];

        foreach ($bancos as $bancoKey => $patrones) {
            foreach ($patrones as $patron) {
                if (stripos($texto, $patron) !== false) {
                    $resultado['banco'] = $bancoKey === 'pago_movil' ? 'mercantil' : $bancoKey;
                    $resultado['banco_pagador'] = $resultado['banco'];
                    $resultado['detectado'] = true;
                    break 2;
                }
            }
        }

        // 3. Detección de Cuenta Receptora / Destino (código bancario de 4 dígitos o número de 20 dígitos)
        if (preg_match('/(?:cuenta\s+(?:destino|beneficiario|receptora)|destino|acreditad[oa]\s+a)[:\s#]*([0-9]{4})/iu', $texto, $matchesCta)) {
            $resultado['cuenta_destino_prefijo'] = $matchesCta[1];
            $resultado['detectado'] = true;
        } elseif (preg_match('/\b(0102|0105|0134|0108|0172|0191|0114|0163|0115|0138|0171|0137|0156|0151)[0-9]{16}\b/', $texto, $matchesCtaCompleta)) {
            $resultado['cuenta_destino_prefijo'] = $matchesCtaCompleta[1];
            $resultado['detectado'] = true;
        }

        // Detección de mención explícita de banco destino
        if (preg_match('/(?:banco\s+(?:destino|receptor|beneficiario)|destino)[:\s]*([a-zA-Z\s]{4,30})/iu', $texto, $matchesBcoDestino)) {
            $posibleBco = trim($matchesBcoDestino[1]);
            foreach ($bancos as $bKey => $pats) {
                if ($bKey === 'pago_movil') continue;
                foreach ($pats as $p) {
                    if (stripos($posibleBco, $p) !== false) {
                        $resultado['banco_receptor'] = $bKey;
                        $resultado['detectado'] = true;
                        break 2;
                    }
                }
            }
        }

        // 4. Detección de Referencia (6 a 16 dígitos)
        if (preg_match('/(?:ref|referencia|comprobante|nro|operaci[oó]n|transacci[oó]n|secuencia|aprobaci[oó]n)[:\s#]*([0-9]{6,16})/iu', $texto, $matchesRef)) {
            $resultado['referencia'] = $matchesRef[1];
            $resultado['detectado'] = true;
        } elseif (preg_match('/\b([0-9]{7,14})\b/', $texto, $matchesRefIsolated)) {
            $resultado['referencia'] = $matchesRefIsolated[1];
            $resultado['detectado'] = true;
        }

        // 5. Detección de Monto con distinción correcta de formato
        if (preg_match('/(?:monto|total|importe|bs\.?|ves|\$|por)[:\s]*([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})/i', $texto, $matchesMonto)) {
            $montoStr = str_replace('.', '', $matchesMonto[1]);
            $montoStr = str_replace(',', '.', $montoStr);
            $resultado['monto'] = round(floatval($montoStr), 2);
            $resultado['detectado'] = true;
        } elseif (preg_match('/(?:monto|total|importe|bs\.?|ves|\$|por)[:\s]*([0-9]+\.[0-9]{2})\b/i', $texto, $matchesMonto)) {
            $resultado['monto'] = round(floatval($matchesMonto[1]), 2);
            $resultado['detectado'] = true;
        } elseif (preg_match('/(?<![0-9])((?:[0-9]{1,3}(?:\.[0-9]{3})*|[0-9]{4,}),[0-9]{2})/', $texto, $matchesMontoVen)) {
            $montoStr = str_replace('.', '', $matchesMontoVen[1]);
            $montoStr = str_replace(',', '.', $montoStr);
            $resultado['monto'] = round(floatval($montoStr), 2);
            $resultado['detectado'] = true;
        } elseif (preg_match('/\b([0-9]+\.[0-9]{2})\b/', $texto, $matchesDec)) {
            $resultado['monto'] = round(floatval($matchesDec[1]), 2);
            $resultado['detectado'] = true;
        }

        // 6. Detección de Fecha (dd/mm/aaaa, dd-mm-aaaa, o texto en español)
        if (preg_match('/([0-3]?[0-9])[\/\-]([0-1]?[0-9])[\/\-](202[0-9])/', $texto, $matchesFecha)) {
            $dia  = sprintf('%02d', $matchesFecha[1]);
            $mes  = sprintf('%02d', $matchesFecha[2]);
            $anio = $matchesFecha[3];
            $resultado['fecha'] = "{$anio}-{$mes}-{$dia}";
            $resultado['detectado'] = true;
        } elseif (preg_match('/(202[0-9])[\/\-]([0-1]?[0-9])[\/\-]([0-3]?[0-9])/', $texto, $matchesFechaIso)) {
            $anio = $matchesFechaIso[1];
            $mes  = sprintf('%02d', $matchesFechaIso[2]);
            $dia  = sprintf('%02d', $matchesFechaIso[3]);
            $resultado['fecha'] = "{$anio}-{$mes}-{$dia}";
            $resultado['detectado'] = true;
        } else {
            $mesesEsp = [
                'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
                'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
                'septiembre' => '09', 'setiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
                'ene' => '01', 'feb' => '02', 'mar' => '03', 'abr' => '04',
                'may' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
                'sep' => '09', 'oct' => '10', 'nov' => '11', 'dic' => '12'
            ];
            $mesesRegex = implode('|', array_keys($mesesEsp));
            if (preg_match('/([0-3]?[0-9])\s+(?:de\s+)?(' . $mesesRegex . ')(?:\s+(?:de\s+|del\s+)?)?(202[0-9])/i', $texto, $mFechaTxt)) {
                $dia = sprintf('%02d', $mFechaTxt[1]);
                $mesKey = strtolower(trim($mFechaTxt[2]));
                $mes = $mesesEsp[$mesKey] ?? '01';
                $anio = $mFechaTxt[3];
                $resultado['fecha'] = "{$anio}-{$mes}-{$dia}";
                $resultado['detectado'] = true;
            }
        }

        return $resultado;
    }

    /**
     * Procesa texto plano reconocido (ej. mediante motor OCR en cliente o servicio externo).
     *
     * @param string $texto
     * @return array Resultado del análisis heurístico
     */
    public function procesarTextoOcr(string $texto): array {
        return $this->analizarTexto($texto);
    }

    /**
     * Procesa un archivo comprobante cargado.
     * Soporta PDF (con extracción nativa de streams) e integración con texto OCR.
     *
     * @param string $tmpPath   Ruta temporal del archivo cargado
     * @param string $extension Extensión del archivo (pdf, txt, jpg, etc.)
     * @param string|null $textoOcr Texto pre-extraído vía OCR si aplica
     * @return array Resultado del análisis heurístico
     */
    public function procesarArchivo(string $tmpPath, string $extension, ?string $textoOcr = null): array {
        if (!empty($textoOcr)) {
            return $this->analizarTexto($textoOcr);
        }

        $ext = strtolower($extension);

        if ($ext === 'pdf') {
            $texto = $this->extraerTextoDePdf($tmpPath);

            if (empty($texto)) {
                error_log("[ComprobanteParserService] procesarArchivo: extracción vacía para archivo '{$tmpPath}'. El usuario deberá ingresar los datos manualmente.");
                return [
                    'banco'      => null,
                    'referencia' => null,
                    'monto'      => null,
                    'fecha'      => null,
                    'detectado'  => false
                ];
            }

            return $this->analizarTexto($texto);
        }

        // Para imágenes sin texto OCR proporcionado
        error_log("[ComprobanteParserService] procesarArchivo: formato '{$ext}' requiere extracción OCR para análisis automático. El usuario deberá ingresar los datos manualmente.");
        return [
            'banco'      => null,
            'referencia' => null,
            'monto'      => null,
            'fecha'      => null,
            'detectado'  => false
        ];
    }
}
