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
            'cuenta_destino_numero'  => null,
            'telefono_destino'       => null,
            'identificacion_destino' => null,
            'metodo_pago'            => null,
            'referencia'             => null,
            'monto'                  => null,
            'fecha'                  => null,
            'confianza'              => [],
            'inconsistencias'        => [],
            'detectado'              => false
        ];

        if (empty(trim($texto))) {
            return $resultado;
        }

        // Las capturas de teléfono suelen incluir notificaciones superpuestas
        // ("PagomovilBDV recibido", "Recibiste un PagomovilBDV por Bs...");
        // se descartan para que no contaminen la detección (en particular la
        // del banco emisor, que veía un "BDV" de la notificación).
        $lineas = preg_split('/\r?\n/', $texto);
        $lineas = array_filter($lineas, function (string $linea): bool {
            $avisoRecibido  = preg_match('/recibi(?:do|ste)/iu', $linea) === 1;
            $mencionPagoMovil = preg_match('/pagomovil|pago\s*m[oó]vil/iu', $linea) === 1;
            return !($avisoRecibido && $mencionPagoMovil);
        });
        $texto = implode("\n", $lineas);

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
        // Si ninguna señal identifica al emisor, el banco queda vacío: es mejor
        // no adivinar que marcar un banco equivocado.
        $bancos = [
            'venezuela'  => ['venezuela', 'banco de venezuela', 'bdv'],
            'mercantil'  => ['mercantil', 'banco mercantil', 'tpago'],
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

        // b) Coincidencia posicional: nombres de banco y códigos de cuenta.
        //    Las menciones al banco receptor (tras etiquetas como "destino",
        //    "receptor" o "beneficiario", con el valor incluso en la línea
        //    siguiente) no identifican al emisor y quedan excluidas.
        if ($resultado['banco'] === null) {
            $zonasReceptor = [];
            if (preg_match_all('/(?:destino|receptor|beneficiario|acreditad[oa]|recibe|banco[:\-]|banco(?=[0-9]{4}))/iu', $textoBancos, $mZonas, PREG_OFFSET_CAPTURE)) {
                foreach ($mZonas[0] as $mZona) {
                    $inicio = $mZona[1];
                    $fin = $inicio;
                    $buscado = $inicio;
                    for ($n = 0; $n < 2; $n++) {
                        $salto = strpos($textoBancos, "\n", $buscado);
                        if ($salto === false) { $fin = strlen($textoBancos); break; }
                        $fin = $salto;
                        $buscado = $salto + 1;
                    }
                    $zonasReceptor[] = [$inicio, min($fin, $inicio + 160)];
                }
            }
            $enZonaReceptor = function (int $posicion) use ($zonasReceptor): bool {
                foreach ($zonasReceptor as $zona) {
                    if ($posicion >= $zona[0] && $posicion <= $zona[1]) { return true; }
                }
                return false;
            };

            $mejorPosicion = PHP_INT_MAX;
            foreach ($bancos as $bancoKey => $patrones) {
                foreach ($patrones as $patron) {
                    $buscar = $this->normalizarTextoBancos($patron);
                    $offset = 0;
                    while (($posicion = strpos($textoBancos, $buscar, $offset)) !== false) {
                        if (!$enZonaReceptor($posicion)) {
                            if ($posicion < $mejorPosicion) {
                                $mejorPosicion = $posicion;
                                $resultado['banco'] = $bancoKey;
                            }
                            break;
                        }
                        $offset = $posicion + 1;
                    }
                }
            }
            foreach ($codigosBanco as $codigo => $bancoKey) {
                $offset = 0;
                while (preg_match('/(?<![0-9])' . $codigo . '/', $textoBancos, $mCodigo, PREG_OFFSET_CAPTURE, $offset)) {
                    $posicionCodigo = $mCodigo[0][1];
                    if (!$enZonaReceptor($posicionCodigo)) {
                        if ($posicionCodigo < $mejorPosicion) {
                            $mejorPosicion = $posicionCodigo;
                            $resultado['banco'] = $bancoKey;
                        }
                        break;
                    }
                    $offset = $posicionCodigo + 1;
                }
            }
        }

        if ($resultado['banco'] !== null) {
            $resultado['banco_pagador'] = $resultado['banco'];
            $resultado['detectado'] = true;
        }

        // 3. Detección de Cuenta Receptora / Destino (código bancario de 4 dígitos, número de 20 dígitos, teléfono y RIF)
        // 3.1 Detección de cuentas bancarias de 20 dígitos (con o sin espacios/guiones)
        if (preg_match_all('/(?<!\d)(01[0-9]{2})[\s\-]?([0-9]{4})[\s\-]?([0-9]{2})[\s\-]?([0-9]{10})(?!\d)/', $texto, $matchesCuentas20, PREG_OFFSET_CAPTURE)) {
            $mejorCuenta = null;
            foreach ($matchesCuentas20[0] as $i => $mCta) {
                $ctaLimpia = $matchesCuentas20[1][$i][0] . $matchesCuentas20[2][$i][0] . $matchesCuentas20[3][$i][0] . $matchesCuentas20[4][$i][0];
                $pos = $mCta[1];
                $entorno = substr($texto, max(0, $pos - 80), 160);
                if (preg_match('/(?:destino|beneficiario|receptora|acreditad|hacia|abonar|para)/iu', $entorno)) {
                    $mejorCuenta = $ctaLimpia;
                    break;
                }
            }
            if (!$mejorCuenta && !empty($matchesCuentas20[0])) {
                $ultimoIdx = count($matchesCuentas20[0]) - 1;
                $mejorCuenta = $matchesCuentas20[1][$ultimoIdx][0] . $matchesCuentas20[2][$ultimoIdx][0] . $matchesCuentas20[3][$ultimoIdx][0] . $matchesCuentas20[4][$ultimoIdx][0];
            }
            if ($mejorCuenta) {
                $resultado['cuenta_destino_numero'] = $mejorCuenta;
                $resultado['cuenta_destino_prefijo'] = substr($mejorCuenta, 0, 4);
                $resultado['detectado'] = true;
            }
        }

        // 3.2 Suffix o prefijo explícito de cuenta si no se extrajo la completa de 20 dígitos
        if (!$resultado['cuenta_destino_numero']) {
            if (preg_match('/(?:cuenta(?:\s+(?:destino|beneficiario|receptora))?|destino|acreditad[oa]\s+a)[:\s#]*([0-9]{4})[\s\-]*(?:[0-9xX\*]{4,20})/iu', $texto, $mCtaMask)) {
                $resultado['cuenta_destino_prefijo'] = $mCtaMask[1];
                $resultado['detectado'] = true;
            } elseif (preg_match('/(?:cuenta\s+(?:destino|beneficiario|receptora)|destino|acreditad[oa]\s+a)[:\s#]*([0-9\s\-]{4,24})/iu', $texto, $matchesCta)) {
                $digits = preg_replace('/[^0-9]/', '', $matchesCta[1]);
                if (strlen($digits) >= 20) {
                    $resultado['cuenta_destino_numero'] = substr($digits, 0, 20);
                    $resultado['cuenta_destino_prefijo'] = substr($digits, 0, 4);
                } elseif (strlen($digits) >= 4) {
                    $resultado['cuenta_destino_prefijo'] = substr($digits, 0, 4);
                    $resultado['cuenta_destino_numero'] = $digits;
                }
                $resultado['detectado'] = true;
            } elseif (preg_match('/\b(0102|0105|0134|0108|0172|0191|0114|0163|0115|0138|0171|0137|0156|0151)[0-9]{16}\b/', $texto, $matchesCtaCompleta)) {
                $resultado['cuenta_destino_prefijo'] = $matchesCtaCompleta[1];
                $resultado['cuenta_destino_numero'] = $matchesCtaCompleta[0];
                $resultado['detectado'] = true;
            }
        }

        // 3.3 Detección de mención explícita de banco destino
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

        // 3.4 Detección de Teléfono Destino (Pago Móvil)
        if (preg_match('/(?:tel[eé]fono|celular|t[eé]l|pago\s*m[oó]vil|destino)[^0-9\n\r]{0,15}(04[12][246][\s\-]?[0-9]{7})\b/iu', $texto, $mPhone)) {
            $resultado['telefono_destino'] = preg_replace('/[^0-9]/', '', $mPhone[1]);
            $resultado['detectado'] = true;
        }

        // 3.5 Detección de Identificación Destino (RIF o Cédula del Beneficiario)
        if (preg_match('/(?:beneficiario|destino|rif|c[eé]dula|identificaci[oó]n|ci)[^0-9a-zA-Z\n\r]{0,15}([JjVvGgEe][\- ]?[0-9]{7,9}[\- ]?[0-9]?)\b/iu', $texto, $mRif)) {
            $resultado['identificacion_destino'] = strtoupper(preg_replace('/[\- ]/', '', $mRif[1]));
            $resultado['detectado'] = true;
        }

        // 4. Detección de Referencia (2 a 16 dígitos)
        if (preg_match('/(?:ref|referencia|comprobante|nro|operaci[oó]n|transacci[oó]n|secuencia|aprobaci[oó]n)[:\s#]*([0-9]{2,16})/iu', $texto, $matchesRef)) {
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

        // 6. Detección de Fecha (dd/mm/aaaa, dd/mm/aa, dd-mm-aaaa, o texto en español)
        if (preg_match('/([0-3]?[0-9])[\/\-]([0-1]?[0-9])[\/\-](20[0-9]{2}|[0-9]{2})(?![0-9])/', $texto, $matchesFecha)) {
            $dia  = sprintf('%02d', $matchesFecha[1]);
            $mes  = sprintf('%02d', $matchesFecha[2]);
            $anio = intval($matchesFecha[3]);
            if ($anio < 100) { $anio += 2000; } // Año de 2 dígitos (ej. 26 -> 2026)
            $resultado['fecha'] = sprintf('%04d-%s-%s', $anio, $mes, $dia);
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

        // 7. Cálculo de niveles de confianza (0.0 a 1.0) e inconsistencias heurísticas
        $confianza = [
            'monto'              => 0.0,
            'fecha_pago'         => 0.0,
            'referencia'         => 0.0,
            'banco_pagador'      => 0.0,
            'cuenta_bancaria_id' => 0.0
        ];
        $inconsistencias = [
            'monto'              => null,
            'fecha_pago'         => null,
            'referencia'         => null,
            'banco_pagador'      => null,
            'cuenta_bancaria_id' => null
        ];

        // Validación y confianza de Monto
        if ($resultado['monto'] !== null && $resultado['monto'] > 0) {
            $confianza['monto'] = 1.0;
        } else {
            $confianza['monto'] = 0.0;
            $inconsistencias['monto'] = 'No se pudo leer el monto con certeza en el comprobante. Por favor ingréselo manualmente.';
        }

        // Validación y confianza de Fecha de Pago
        if ($resultado['fecha'] !== null) {
            $ts = strtotime($resultado['fecha']);
            $ahora = time();
            if ($ts > $ahora + 86400) {
                $confianza['fecha_pago'] = 0.4;
                $inconsistencias['fecha_pago'] = 'Atención: La fecha detectada (' . date('d/m/Y', $ts) . ') es futura o posterior a la fecha de hoy. Verifique que sea correcta.';
            } elseif ($ts < $ahora - (180 * 86400)) {
                $confianza['fecha_pago'] = 0.5;
                $inconsistencias['fecha_pago'] = 'Atención: La fecha detectada (' . date('d/m/Y', $ts) . ') tiene más de 6 meses de antigüedad. Verifique si es el comprobante correcto.';
            } else {
                $confianza['fecha_pago'] = 1.0;
            }
        } else {
            $confianza['fecha_pago'] = 0.0;
            $inconsistencias['fecha_pago'] = 'No se detectó la fecha de pago en el comprobante. Por favor indíquela manualmente.';
        }

        // Validación y confianza de Referencia
        if (!empty($resultado['referencia'])) {
            if (strlen($resultado['referencia']) < 6) {
                $confianza['referencia'] = 0.5;
                $inconsistencias['referencia'] = 'Atención: La referencia detectada (' . $resultado['referencia'] . ') es corta o parece incompleta. Verifique el número de confirmación completo.';
            } else {
                $confianza['referencia'] = 1.0;
            }
        } else {
            $confianza['referencia'] = 0.0;
            $inconsistencias['referencia'] = 'No se detectó el número de referencia. Por favor ingréselo manualmente.';
        }

        // Validación y confianza de Banco Emisor (Pagador)
        if (!empty($resultado['banco_pagador'])) {
            $confianza['banco_pagador'] = 1.0;
        } else {
            $confianza['banco_pagador'] = 0.0;
            $inconsistencias['banco_pagador'] = 'No se reconoció el banco emisor. Por favor seleccione su banco en la lista.';
        }

        $resultado['confianza'] = $confianza;
        $resultado['inconsistencias'] = $inconsistencias;

        return $resultado;
    }

    /**
     * Valida y asocia la cuenta de destino del comprobante contra las cuentas oficiales del condominio.
     * Genera alertas informativas (no bloqueantes) ante inconsistencias o cuentas no reconocidas.
     *
     * @param array $datosExtraidos Resultado de analizarTexto()
     * @param array $cuentasBancarias Lista de cuentas (todas: activas e inactivas)
     * @return array [
     *     'cuenta_bancaria_id'    => ?int,
     *     'banco_receptor'        => string,
     *     'cuenta_destino_valida' => bool,
     *     'confianza'             => float,
     *     'inconsistencia'        => ?string,
     *     'coincidencia_por'      => ?string
     * ]
     */
    public function validarCuentaDestino(array $datosExtraidos, array $cuentasBancarias): array {
        $resultado = [
            'cuenta_bancaria_id'    => null,
            'banco_receptor'        => '',
            'cuenta_destino_valida' => true,
            'confianza'             => 0.0,
            'inconsistencia'        => null,
            'coincidencia_por'      => null
        ];

        $ctaNumExtraida = $datosExtraidos['cuenta_destino_numero'] ?? null;
        $ctaPrefijo = $datosExtraidos['cuenta_destino_prefijo'] ?? null;
        $bcoReceptor = $datosExtraidos['banco_receptor'] ?? null;
        $tlfDestino = !empty($datosExtraidos['telefono_destino']) ? preg_replace('/[^0-9]/', '', $datosExtraidos['telefono_destino']) : null;
        $idDestino = !empty($datosExtraidos['identificacion_destino']) ? preg_replace('/[^0-9]/', '', $datosExtraidos['identificacion_destino']) : null;

        $cuentasActivas = array_values(array_filter($cuentasBancarias, fn($c) => !empty($c['activa'])));
        $cuentasInactivas = array_values(array_filter($cuentasBancarias, fn($c) => empty($c['activa'])));

        $buscarCoincidencia = function(array $listaCuentas) use ($ctaNumExtraida, $ctaPrefijo, $bcoReceptor, $tlfDestino, $idDestino): ?array {
            // 1. Coincidencia por número de cuenta completo (20 dígitos) o parcial significativo
            if ($ctaNumExtraida) {
                $numLimpio = preg_replace('/[^0-9]/', '', $ctaNumExtraida);
                foreach ($listaCuentas as $c) {
                    $cuentaOficial = preg_replace('/[^0-9]/', '', $c['numero_cuenta'] ?? '');
                    if ($cuentaOficial !== '' && ($numLimpio === $cuentaOficial || (strlen($numLimpio) >= 8 && str_contains($cuentaOficial, $numLimpio)))) {
                        return ['cuenta' => $c, 'criterio' => 'numero_cuenta', 'confianza' => 1.0];
                    }
                }
            }

            // 2. Coincidencia por teléfono de pago móvil
            if ($tlfDestino) {
                foreach ($listaCuentas as $c) {
                    $tlfOficial = preg_replace('/[^0-9]/', '', $c['telefono_pago_movil'] ?? $c['telefono'] ?? '');
                    if ($tlfOficial && $tlfDestino === $tlfOficial) {
                        return ['cuenta' => $c, 'criterio' => 'telefono_pago_movil', 'confianza' => 0.95];
                    }
                }
            }

            // 3. Coincidencia por RIF / Identificación + Banco
            if ($idDestino && $bcoReceptor) {
                $bancoDetectado = $this->normalizarTextoBancos($bcoReceptor);
                foreach ($listaCuentas as $c) {
                    $idOficial = preg_replace('/[^0-9]/', '', $c['identificacion'] ?? '');
                    $bancoOficial = $this->normalizarTextoBancos($c['banco'] ?? '');
                    if ($idOficial && $idDestino === $idOficial && str_contains($bancoOficial, $bancoDetectado)) {
                        return ['cuenta' => $c, 'criterio' => 'rif_banco', 'confianza' => 0.9];
                    }
                }
            }

            // 4. Coincidencia por prefijo bancario de 4 dígitos (código de banco)
            if ($ctaPrefijo) {
                $coincidenciasPrefijo = [];
                foreach ($listaCuentas as $c) {
                    $cuentaOficial = preg_replace('/[^0-9]/', '', $c['numero_cuenta'] ?? '');
                    if (str_starts_with($cuentaOficial, $ctaPrefijo)) {
                        $coincidenciasPrefijo[] = $c;
                    }
                }
                if (count($coincidenciasPrefijo) === 1) {
                    return ['cuenta' => $coincidenciasPrefijo[0], 'criterio' => 'prefijo_banco', 'confianza' => 0.85];
                } elseif (count($coincidenciasPrefijo) > 1 && $bcoReceptor) {
                    $bancoDetectado = $this->normalizarTextoBancos($bcoReceptor);
                    foreach ($coincidenciasPrefijo as $c) {
                        if (str_contains($this->normalizarTextoBancos($c['banco']), $bancoDetectado)) {
                            return ['cuenta' => $c, 'criterio' => 'prefijo_y_nombre_banco', 'confianza' => 0.85];
                        }
                    }
                }
            }

            // 5. Coincidencia por nombre de banco receptor detectado
            if ($bcoReceptor) {
                $bancoDetectado = $this->normalizarTextoBancos($bcoReceptor);
                $coincidentesBanco = [];
                foreach ($listaCuentas as $c) {
                    $bancoOficial = $this->normalizarTextoBancos($c['banco'] ?? '');
                    if ($bancoOficial !== '' && (str_contains($bancoOficial, $bancoDetectado) || str_contains($bancoDetectado, $bancoOficial))) {
                        $coincidentesBanco[] = $c;
                    }
                }
                if (count($coincidentesBanco) === 1) {
                    return ['cuenta' => $coincidentesBanco[0], 'criterio' => 'nombre_banco', 'confianza' => 0.75];
                }
            }

            return null;
        };

        // 1. Evaluar contra cuentas activas autorizadas
        $matchActiva = $buscarCoincidencia($cuentasActivas);
        if ($matchActiva !== null) {
            $c = $matchActiva['cuenta'];
            $resultado['cuenta_bancaria_id'] = (int)$c['id'];
            $resultado['banco_receptor'] = $c['banco'];
            $resultado['cuenta_destino_valida'] = true;
            $resultado['confianza'] = $matchActiva['confianza'];
            $resultado['inconsistencia'] = null;
            $resultado['coincidencia_por'] = $matchActiva['criterio'];
            return $resultado;
        }

        // 2. Evaluar si coincide con una cuenta INACTIVA del condominio
        $matchInactiva = $buscarCoincidencia($cuentasInactivas);
        if ($matchInactiva !== null) {
            $c = $matchInactiva['cuenta'];
            $resultado['cuenta_bancaria_id'] = null;
            $resultado['banco_receptor'] = $c['banco'];
            $resultado['cuenta_destino_valida'] = false;
            $resultado['confianza'] = 0.3;
            $resultado['inconsistencia'] = "Atención: El comprobante refleja un pago hacia la cuenta de " . ($c['banco'] ?? 'banco oficial') . " que actualmente se encuentra inactiva. Por favor verifique si realizó la transferencia a la cuenta oficial vigente.";
            $resultado['coincidencia_por'] = 'inactiva_' . $matchInactiva['criterio'];
            return $resultado;
        }

        // 3. Evaluar si se detectó destino en el comprobante pero NO coincide con ninguna cuenta del condominio
        $huboDatosDestinoDetectados = ($ctaNumExtraida || $ctaPrefijo || $bcoReceptor || $tlfDestino);
        if ($huboDatosDestinoDetectados) {
            $detalleDestino = $bcoReceptor ? ucfirst($bcoReceptor) : ($ctaPrefijo ? "Banco {$ctaPrefijo}" : 'desconocido');
            if ($ctaNumExtraida) {
                $digitos = preg_replace('/[^0-9]/', '', $ctaNumExtraida);
                if (strlen($digitos) >= 4) {
                    $detalleDestino .= " (cuenta *" . substr($digitos, -4) . ")";
                }
            }
            $resultado['cuenta_bancaria_id'] = null;
            $resultado['banco_receptor'] = $bcoReceptor ? ucfirst($bcoReceptor) : '';
            $resultado['cuenta_destino_valida'] = false;
            $resultado['confianza'] = 0.1;
            $resultado['inconsistencia'] = "Atención: El número de cuenta o banco receptor detectado en el comprobante ({$detalleDestino}) no coincide con nuestras cuentas registradas. Verifique los datos o seleccione la cuenta oficial correspondiente.";
            return $resultado;
        }

        // 4. No se detectó cuenta de destino en el comprobante
        if (count($cuentasActivas) === 1) {
            $c = $cuentasActivas[0];
            $resultado['cuenta_bancaria_id'] = (int)$c['id'];
            $resultado['banco_receptor'] = $c['banco'];
            $resultado['cuenta_destino_valida'] = true;
            $resultado['confianza'] = 0.5;
            $resultado['inconsistencia'] = "No se detectó la cuenta receptora en el comprobante. Se seleccionó la cuenta oficial por defecto ({$c['banco']}); confirme que corresponda a su pago.";
            $resultado['coincidencia_por'] = 'cuenta_unica_default';
            return $resultado;
        }

        $resultado['cuenta_bancaria_id'] = null;
        $resultado['banco_receptor'] = '';
        $resultado['cuenta_destino_valida'] = true;
        $resultado['confianza'] = 0.0;
        $resultado['inconsistencia'] = "No se detectó la cuenta receptora en el comprobante. Por favor seleccione la cuenta oficial a la que realizó la operación.";
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
     * acentos, sin espacios ni tabulaciones (conserva los saltos de línea,
     * que delimitan las zonas de contexto receptor). Así "BancoFondoComún"
     * coincide con "fondo comun".
     */
    private function normalizarTextoBancos(string $texto): string {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u'
        ]);
        return preg_replace('/[ \t]+/', '', $texto);
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
