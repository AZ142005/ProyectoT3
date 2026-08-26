<?php
namespace App\Services;

class ComprobanteParserService {

    /**
     * Extrae texto plano de un archivo PDF analizando sus flujos de datos internos.
     *
     * @param string $rutaPdf Ruta al archivo PDF en disco
     * @return string Texto extraído del documento
     */
    public function extraerTextoDePdf(string $rutaPdf): string {
        if (!file_exists($rutaPdf)) {
            return '';
        }

        $contenido = file_get_contents($rutaPdf);
        if ($contenido === false) {
            return '';
        }

        $textoExtraido = '';

        // Buscar todos los bloques de flujo "stream ... endstream"
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $contenido, $matches)) {
            foreach ($matches[1] as $stream) {
                // Intentar descompresión FlateDecode
                $descomprimido = @gzuncompress($stream);
                if ($descomprimido === false) {
                    $descomprimido = @gzinflate($stream);
                }

                $data = ($descomprimido !== false) ? $descomprimido : $stream;

                // Extraer texto de operadores estándar de PDF: (texto) Tj o [(t)(e)(x)(t)(o)] TJ
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $data, $textMatches)) {
                    $textoExtraido .= ' ' . implode(' ', $textMatches[1]);
                }
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
            preg_match_all('/[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\.,:\/\-]{3,}/', $contenido, $plainMatches);
            $textoExtraido = implode(' ', $plainMatches[0] ?? []);
        }

        return $textoExtraido;
    }

    /**
     * Analiza una cadena de texto buscando patrones bancarios para sugerir campos del formulario.
     *
     * @param string $texto
     * @return array ['banco' => ?string, 'referencia' => ?string, 'monto' => ?float, 'fecha' => ?string]
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

        // 3. Detección de Monto
        // Patrón formato venezolano: 1.250,50 o internacional 1250.50
        if (preg_match('/(?:monto|total|importe|bs\.?|ves|\$)[:\s]*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i', $texto, $matchesMonto)) {
            $montoStr = str_replace('.', '', $matchesMonto[1]);
            $montoStr = str_replace(',', '.', $montoStr);
            $resultado['monto'] = floatval($montoStr);
            $resultado['detectado'] = true;
        } elseif (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})/', $texto, $matchesMontoVen)) {
            $montoStr = str_replace('.', '', $matchesMontoVen[1]);
            $montoStr = str_replace(',', '.', $montoStr);
            $resultado['monto'] = floatval($montoStr);
            $resultado['detectado'] = true;
        }

        // 4. Detección de Fecha (dd/mm/aaaa o aaaa-mm-dd)
        if (preg_match('/([0-3]?[0-9])[\/\-]([0-1]?[0-9])[\/\-](202[0-9])/', $texto, $matchesFecha)) {
            $dia = sprintf('%02d', $matchesFecha[1]);
            $mes = sprintf('%02d', $matchesFecha[2]);
            $anio = $matchesFecha[3];
            $resultado['fecha'] = "{$anio}-{$mes}-{$dia}";
            $resultado['detectado'] = true;
        }

        return $resultado;
    }

    /**
     * Procesa un archivo comprobante cargado.
     */
    public function procesarArchivo(string $tmpPath, string $extension): array {
        $ext = strtolower($extension);
        if ($ext === 'pdf') {
            $texto = $this->extraerTextoDePdf($tmpPath);
            return $this->analizarTexto($texto);
        }

        // En caso de imagen, analizamos si se envió texto o nombre
        return $this->analizarTexto(basename($tmpPath));
    }
}
