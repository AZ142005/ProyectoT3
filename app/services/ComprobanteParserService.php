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
     * - OCR con separadores mezclados (ej. 6,670,50): se normaliza tomando el
     *   último separador como decimal.
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
        // Estrategia (los comprobantes suelen mencionar también al banco receptor,
        // por lo que no basta con buscar cualquier nombre de banco en el texto):
        //   a) Contexto explícito de origen ("Origen: 0151...", "Banco emisor ..."):
        //      el código bancario de 4 dígitos identifica al emisor.
        //   b) Coincidencia posicional: gana el banco cuya señal aparece primero
        //      en el texto; la marca del emisor va al inicio del comprobante y las
        //      menciones al receptor aparecen después.
        //   c) Respaldo: "pago móvil" sin banco identificable.
        $bancos = [
            'venezuela'  => ['venezuela', 'banco de venezuela', 'bdv'],
            'mercantil'  => ['mercantil', 'banco mercantil'],
            'banesco'    => ['banesco', 'banco universal banesco'],
            'provincial' => ['provincial', 'bbva'],
            'bancamiga'  => ['bancamiga'],
            'bnc'        => ['bnc', 'nacional de credito'],
            'bancaribe'  => ['bancaribe', 'caribe'],
            'tesoro'     => ['tesoro', 'banco del tesoro'],
            'exterior'   => ['exterior', 'banco exterior'],
            'plaza'      => ['plaza', 'banco plaza'],
            'activo'     => ['activo', 'banco activo'],
            'sofitasa'   => ['sofitasa'],
            '100banco'   => ['100% banco', '100banco'],
            'bfc'        => ['bfc', 'fondo comun', 'banco fondo comun']
        ];
        $codigosBanco = [
            '0102' => 'venezuela',
            '0105' => 'mercantil',
            '0134' => 'banesco',
            '0108' => 'provincial',
            '0172' => 'bancamiga',
            '0191' => 'bnc',
            '0114' => 'bancaribe',
            '0163' => 'tesoro',
            '0115' => 'exterior',
            '0138' => 'plaza',
            '0171' => 'activo',
            '0137' => 'sofitasa',
            '0156' => '100banco',
            '0151' => 'bfc'
        ];

        $textoBancos = $this->normalizarTextoBancos($texto);

        // a) Contexto explícito de origen con código bancario
        $patronOrigen = '/(?:origen|bancoemisor|emisor|desde|cuentaorigen|cta\.?origen)[^0-9]{0,12}('
            . implode('|', array_keys($codigosBanco)) . ')/';
        if (preg_match($patronOrigen, $textoBancos, $matchesOrigen)) {
            $resultado['banco'] = $codigosBanco[$matchesOrigen[1]];
        }

        // b) Coincidencia posicional: nombres de banco y códigos de cuenta
        if ($resultado['banco'] === null) {
            $mejorPosicion = PHP_INT_MAX;
            foreach ($bancos as $bancoKey => $patrones) {
                foreach ($patrones as $patron) {
                    $posicion = strpos($textoBancos, $this->normalizarTextoBancos($patron));
                    if ($posicion !== false && $posicion < $mejorPosicion) {
                        $mejorPosicion = $posicion;
                        $resultado['banco'] = $bancoKey;
                    }
                }
            }
            foreach ($codigosBanco as $codigo => $bancoKey) {
                if (preg_match('/(?<![0-9])' . $codigo . '/', $textoBancos, $mCodigo, PREG_OFFSET_CAPTURE)) {
                    if ($mCodigo[0][1] < $mejorPosicion) {
                        $mejorPosicion = $mCodigo[0][1];
                        $resultado['banco'] = $bancoKey;
                    }
                }
            }
        }

        // c) Respaldo: pago móvil sin banco identificable
        if ($resultado['banco'] === null && preg_match('/(?:pago\s*m[oó]vil|pagomovil|c2p|p2p)/iu', $texto)) {
            $resultado['banco'] = 'mercantil';
        }

        if ($resultado['banco'] !== null) {
            $resultado['banco_pagador'] = $resultado['banco'];
            $resultado['detectado'] = true;
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
        // Nota: el OCR suele mezclar separadores (p. ej. "Bs.6,670,50" por "6.670,50").
        // normalizarMonto() interpreta el último separador como decimal y descarta
        // los demás, evitando capturas parciales como "6,67" extraída de "6,670,50".
        if (preg_match('/(?:monto|total|importe|bs\.?|ves|\$|por)[:\s]*((?:[0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]+)[.,][0-9]{2})/i', $texto, $matchesMonto)) {
            $montoNormalizado = $this->normalizarMonto($matchesMonto[1]);
            if ($montoNormalizado !== null) {
                $resultado['monto'] = $montoNormalizado;
                $resultado['detectado'] = true;
            }
        } elseif (preg_match('/(?<![0-9])((?:[0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]{4,})[.,][0-9]{2})/', $texto, $matchesMontoVen)) {
            $montoNormalizado = $this->normalizarMonto($matchesMontoVen[1]);
            if ($montoNormalizado !== null) {
                $resultado['monto'] = $montoNormalizado;
                $resultado['detectado'] = true;
            }
        } elseif (preg_match('/(?<![0-9.,])([0-9]+[.,][0-9]{2})(?![0-9])/', $texto, $matchesDec)) {
            $montoNormalizado = $this->normalizarMonto($matchesDec[1]);
            if ($montoNormalizado !== null) {
                $resultado['monto'] = $montoNormalizado;
                $resultado['detectado'] = true;
            }
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

    /**
     * Normaliza texto para comparación de nombres de banco: minúsculas, sin
     * acentos y sin espacios, de modo que "BancoFondoComún" coincida con
     * "fondo comun".
     */
    private function normalizarTextoBancos(string $texto): string {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u'
        ]);
        return preg_replace('/\s+/', '', $texto);
    }

    /**
     * Normaliza un monto capturado por OCR a float.
     *
     * El OCR puede leer separadores de forma inconsistente (p. ej. "6,670,50"
     * en lugar de "6.670,50"). Se toma el último separador (punto o coma) como
     * decimal y se descartan los separadores restantes.
     *
     * @param string $token Grupo capturado por las expresiones de monto
     * @return float|null Monto normalizado, o null si no se puede interpretar
     */
    private function normalizarMonto(string $token): ?float {
        $token = preg_replace('/[^0-9.,]/', '', $token);
        if (!preg_match('/^(.*)[.,]([0-9]{2})$/', $token, $partes)) {
            return null;
        }
        $entero = preg_replace('/[^0-9]/', '', $partes[1]);
        if ($entero === '') {
            return null;
        }
        return round(floatval($entero . '.' . $partes[2]), 2);
    }
}
