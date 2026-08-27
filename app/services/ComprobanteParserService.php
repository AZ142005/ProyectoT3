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
            'banco'      => null,
            'referencia' => null,
            'monto'      => null,
            'fecha'      => null,
            'detectado'  => false
        ];

        if (empty(trim($texto))) {
            return $resultado;
        }

        // 1. Detección de Banco
        $bancos = [
            'mercantil'  => ['mercantil', 'banco mercantil'],
            'banesco'    => ['banesco', 'banco universal banesco'],
            'venezuela'  => ['venezuela', 'banco de venezuela', 'bdv', 'bancaribe'],
            'provincial' => ['provincial', 'bbva', 'bbva provincial'],
            'bancamiga'  => ['bancamiga'],
            'pago_movil' => ['pago movil', 'pagomovil', 'c2p', 'p2p']
        ];

        foreach ($bancos as $bancoKey => $patrones) {
            foreach ($patrones as $patron) {
                if (stripos($texto, $patron) !== false) {
                    $resultado['banco'] = $bancoKey === 'pago_movil' ? 'mercantil' : $bancoKey;
                    $resultado['detectado'] = true;
                    break 2;
                }
            }
        }

        // 2. Detección de Referencia (6 a 12 dígitos)
        if (preg_match('/(?:ref|referencia|comprobante|nro|operaci[oó]n|transacci[oó]n|secuencia)[:\s#]*([0-9]{6,12})/i', $texto, $matchesRef)) {
            $resultado['referencia'] = $matchesRef[1];
            $resultado['detectado'] = true;
        } elseif (preg_match('/\b([0-9]{7,10})\b/', $texto, $matchesRefIsolated)) {
            $resultado['referencia'] = $matchesRefIsolated[1];
            $resultado['detectado'] = true;
        }

        // 3. Detección de Monto con distinción correcta de formato
        // Prioridad al formato venezolano (punto=miles, coma=decimal): 1.250,50
        // El formato internacional (punto=decimal): 150.00
        // Se distinguen ANTES de aplicar str_replace para evitar inflación 100x.
        if (preg_match('/(?:monto|total|importe|bs\.?|ves|\$|por)[:\s]*([0-9]{1,3}(?:\.[0-9]{3})+,[0-9]{2})/i', $texto, $matchesMonto)) {
            // Formato venezolano: 1.250,50 → remover puntos de miles, cambiar coma por punto
            $montoStr = str_replace('.', '', $matchesMonto[1]);
            $montoStr = str_replace(',', '.', $montoStr);
            $resultado['monto'] = round(floatval($montoStr), 2);
            $resultado['detectado'] = true;
        } elseif (preg_match('/(?:monto|total|importe|bs\.?|ves|\$|por)[:\s]*([0-9]+\.[0-9]{2})\b/i', $texto, $matchesMonto)) {
            // Formato internacional: 1500.00 → usar directamente (NO strip de puntos)
            $resultado['monto'] = round(floatval($matchesMonto[1]), 2);
            $resultado['detectado'] = true;
        } elseif (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})+,[0-9]{2})/', $texto, $matchesMontoVen)) {
            // Monto venezolano sin prefijo de etiqueta
            $montoStr = str_replace('.', '', $matchesMontoVen[1]);
            $montoStr = str_replace(',', '.', $montoStr);
            $resultado['monto'] = round(floatval($montoStr), 2);
            $resultado['detectado'] = true;
        } elseif (preg_match('/\b([0-9]+\.[0-9]{2})\b/', $texto, $matchesDec)) {
            // Monto decimal estándar
            $resultado['monto'] = round(floatval($matchesDec[1]), 2);
            $resultado['detectado'] = true;
        }

        // 4. Detección de Fecha (dd/mm/aaaa o dd-mm-aaaa)
        if (preg_match('/([0-3]?[0-9])[\/\-]([0-1]?[0-9])[\/\-](202[0-9])/', $texto, $matchesFecha)) {
            $dia  = sprintf('%02d', $matchesFecha[1]);
            $mes  = sprintf('%02d', $matchesFecha[2]);
            $anio = $matchesFecha[3];
            $resultado['fecha'] = "{$anio}-{$mes}-{$dia}";
            $resultado['detectado'] = true;
        }

        return $resultado;
    }

    /**
     * Procesa un archivo comprobante cargado.
     * Soporta PDF (con extracción nativa de streams) y formatos de texto.
     *
     * Mensaje de fallback para la UI:
     * "La extracción automática funciona mejor con comprobantes de texto plano.
     *  Si algún dato no se completa automáticamente, ingréselo manualmente."
     *
     * @param string $tmpPath  Ruta temporal del archivo cargado
     * @param string $extension Extensión del archivo (pdf, txt, jpg, etc.)
     * @return array Resultado del análisis heurístico
     */
    public function procesarArchivo(string $tmpPath, string $extension): array {
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

        // Para imágenes y otros formatos no soportados, retornar sin datos
        error_log("[ComprobanteParserService] procesarArchivo: formato '{$ext}' no soportado para extracción automática. El usuario deberá ingresar los datos manualmente.");
        return [
            'banco'      => null,
            'referencia' => null,
            'monto'      => null,
            'fecha'      => null,
            'detectado'  => false
        ];
    }
}
