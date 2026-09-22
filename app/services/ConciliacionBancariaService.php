<?php
namespace App\Services;

use PDO;
use Exception;
use InvalidArgumentException;
use App\Core\Database;
use App\Models\ConciliacionModel;
use App\Models\PagoModel;
use App\Models\MovimientosModel;

class ConciliacionBancariaService {

    /**
     * Parsear archivo CSV o TXT de extracto bancario según el banco emisor.
     *
     * @param string $rutaArchivo
     * @param string $banco 'mercantil' | 'banesco' | 'venezuela' | 'provincial' | 'generico_csv'
     * @return array Lista de movimientos normalizados
     * @throws Exception
     */
    public function parsearArchivo(string $rutaArchivo, string $banco): array {
        if (!file_exists($rutaArchivo) || !is_readable($rutaArchivo)) {
            throw new Exception("El archivo de extracto bancario no existe o no es legible.");
        }

        $contenido = file_get_contents($rutaArchivo);
        if ($contenido === false || trim($contenido) === '') {
            throw new Exception("El archivo de extracto bancario se encuentra vacío.");
        }

        // Detección de formato PDF (por extensión o firma binaria %PDF-)
        $esPdf = (strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION)) === 'pdf') || str_starts_with($contenido, '%PDF-');
        if ($esPdf) {
            $bancoLower = strtolower($banco);
            if (in_array($bancoLower, ['venezuela', 'banco_de_venezuela', 'bdv'], true)) {
                return $this->parsearPdfBancoVenezuela($rutaArchivo);
            }
            throw new Exception("El formato PDF actualmente está disponible y configurado para Banco de Venezuela.");
        }

        // Convertir codificación a UTF-8 si viene en ISO-8859-1 o Windows-1252
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1, Windows-1252, auto');
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if (empty($lineas)) {
            throw new Exception("No se pudieron extraer líneas del extracto bancario.");
        }

        // Detectar delimitador habitual (;, , o tab)
        $primeraLinea = $lineas[0];
        $delimitador = ';';
        if (substr_count($primeraLinea, ',') > substr_count($primeraLinea, ';')) {
            $delimitador = ',';
        } elseif (substr_count($primeraLinea, "\t") > substr_count($primeraLinea, ';')) {
            $delimitador = "\t";
        }

        $movimientos = [];
        $esPrimeraLinea = true;

        foreach ($lineas as $numLinea => $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') continue;

            $columnas = str_getcsv($lineaTrim, $delimitador, '"', "\\");
            if (count($columnas) < 3) continue;

            // Omitir cabeceras si la primera fila contiene palabras clave
            if ($esPrimeraLinea) {
                $esPrimeraLinea = false;
                $col0Lower = mb_strtolower(trim($columnas[0] ?? ''));
                if (str_contains($col0Lower, 'fecha') || str_contains($col0Lower, 'date') || str_contains($col0Lower, 'fec')) {
                    continue;
                }
            }

            $mov = $this->mapearColumnasPorBanco($columnas, $banco);
            if ($mov !== null) {
                $movimientos[] = $mov;
            }
        }

        if (count($movimientos) > 5000) {
            throw new Exception("El extracto contiene más de 5000 movimientos. Divida el archivo en lotes más pequeños.");
        }

        if (empty($movimientos)) {
            throw new Exception("No se detectaron movimientos válidos en el extracto para el banco seleccionado.");
        }

        return $movimientos;
    }

    /**
     * Mapea un arreglo de columnas al esquema estándar según la entidad bancaria.
     */
    private function mapearColumnasPorBanco(array $cols, string $banco): ?array {
        $fechaRaw = '';
        $referenciaRaw = '';
        $descripcion = '';
        $montoRaw = '';
        $tipoMov = 'credito';

        switch (strtolower($banco)) {
            case 'banesco':
                // Fecha (0), Descripción (1), Referencia (2), Monto (3)
                $fechaRaw = $cols[0] ?? '';
                $descripcion = $cols[1] ?? '';
                $referenciaRaw = $cols[2] ?? '';
                $montoRaw = $cols[3] ?? ($cols[4] ?? '0');
                break;

            case 'provincial':
                // Fecha (0), Descripción (1), Referencia (2), Monto (3)
                $fechaRaw = $cols[0] ?? '';
                $descripcion = $cols[1] ?? '';
                $referenciaRaw = $cols[2] ?? '';
                $montoRaw = $cols[3] ?? '0';
                break;

            case 'venezuela':
            case 'mercantil':
            default:
                // Fecha (0), Referencia (1), Descripción (2), Monto (3)
                $fechaRaw = $cols[0] ?? '';
                $referenciaRaw = $cols[1] ?? '';
                $descripcion = $cols[2] ?? '';
                $montoRaw = $cols[3] ?? ($cols[4] ?? '0');
                break;
        }

        // Normalizar Fecha a Y-m-d bajo zona horaria venezolana
        $fechaLimpia = trim($fechaRaw);
        $fechaTimestamp = strtotime(str_replace('/', '-', $fechaLimpia));
        if ($fechaTimestamp === false) {
            return null;
        }
        $fecha = date('Y-m-d', $fechaTimestamp);

        // Normalizar Monto (manejar formato 1.250,50 o 1250.50 o números negativos)
        $montoStr = trim($montoRaw);
        $esNegativo = false;
        if (str_starts_with($montoStr, '-') || str_starts_with($montoStr, '(')) {
            $esNegativo = true;
        }

        // Limpiar separadores de miles
        $montoStr = str_replace([' ', '$', 'Bs.', 'Bs', '(', ')'], '', $montoStr);
        if (str_contains($montoStr, ',') && str_contains($montoStr, '.')) {
            // Ejemplo 1.250,50 -> 1250.50
            $montoStr = str_replace('.', '', $montoStr);
            $montoStr = str_replace(',', '.', $montoStr);
        } elseif (str_contains($montoStr, ',')) {
            $montoStr = str_replace(',', '.', $montoStr);
        }

        $monto = floatval($montoStr);
        if ($esNegativo || $monto < 0) {
            $tipoMov = 'debito';
            $monto = abs($monto);
        }

        if ($monto <= 0) {
            return null;
        }

        return [
            'fecha'       => $fecha,
            'referencia'  => trim($referenciaRaw),
            'descripcion' => trim($descripcion),
            'monto'       => $monto,
            'tipo'        => $tipoMov
        ];
    }

    /**
     * Normaliza una referencia bancaria extrayendo dígitos puros y eliminando ceros a la izquierda.
     */
    public function normalizarReferencia(string $ref): string {
        $soloDigitos = preg_replace('/[^0-9]/', '', $ref);
        $limpia = ltrim($soloDigitos, '0');
        return ($limpia === '') ? '0' : $limpia;
    }

    /**
     * Calcula la métrica de similitud Jaro-Winkler entre dos cadenas (rango 0.0 a 1.0).
     */
    public function calcularSimilitudJaroWinkler(string $str1, string $str2): float {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        if ($str1 === $str2) {
            return 1.0;
        }

        $matchDistance = max(1, intdiv(max($len1, $len2), 2) - 1);

        $str1Matches = array_fill(0, $len1, false);
        $str2Matches = array_fill(0, $len2, false);

        $matches = 0;
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($str2Matches[$j]) continue;
                if ($str1[$i] !== $str2[$j]) continue;

                $str1Matches[$i] = true;
                $str2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$str1Matches[$i]) continue;
            while (!$str2Matches[$k]) {
                $k++;
            }
            if ($str1[$i] !== $str2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - ($transpositions / 2)) / $matches)) / 3.0;

        // Bonificación de prefijo común Winkler (hasta 4 caracteres)
        $prefixLen = 0;
        for ($i = 0; $i < min(4, min($len1, $len2)); $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefixLen++;
            } else {
                break;
            }
        }

        $winkler = $jaro + ($prefixLen * 0.1 * (1.0 - $jaro));
        return min(1.0, $winkler);
    }

    /**
     * Ejecuta el motor de cruce inteligente jerárquico de 3 niveles entre extracto y pagos reportados (pagos y comprobantes).
     */
    public function ejecutarCruceInteligente(array $movimientosExtracto): array {
        $db = Database::getConnection();

        $sqlPagos = "
            SELECT 'pago' AS origen_tabla,
                   p.id, p.unidad_id, p.monto, p.fecha_pago, p.referencia,
                   p.banco_pagador AS banco_origen, p.banco_receptor AS banco_destino,
                   p.banco_pagador, p.banco_receptor, p.estado,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula,
                   per.email AS residente_email, per.email, per.telefono AS residente_telefono, per.telefono,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   NULL AS factura_id
            FROM pagos p
            LEFT JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON u.propietario_id = per.id
            WHERE p.estado IN ('PENDIENTE', 'EN REVISIÓN')

            UNION ALL

            SELECT 'comprobante' AS origen_tabla,
                   c.id, f.unidad_id, c.monto, c.fecha_pago, c.referencia,
                   NULL AS banco_origen, NULL AS banco_destino,
                   NULL AS banco_pagador, NULL AS banco_receptor, c.estado,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula,
                   per.email AS residente_email, per.email, per.telefono AS residente_telefono, per.telefono,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   c.factura_id
            FROM comprobantes_pago c
            LEFT JOIN facturas f ON c.factura_id = f.id
            LEFT JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON c.residente_id = per.id OR u.propietario_id = per.id
            WHERE c.estado IN ('pendiente', 'PENDIENTE') AND c.deleted_at IS NULL
            ORDER BY fecha_pago ASC
            LIMIT 5000
        ";
        $pagosPendientes = $db->query($sqlPagos)->fetchAll(PDO::FETCH_ASSOC);
        if (count($pagosPendientes) >= 5000) {
            error_log("[CONCILIACION] WARNING: 5000+ pagos pendientes — resultados truncados.");
        }

        // Indexar pagos por referencia normalizada para búsqueda O(1)
        $indexByRef = [];
        foreach ($pagosPendientes as $pago) {
            $refNorm = $this->normalizarReferencia($pago['referencia']);
            $indexByRef[$refNorm][] = $pago;
        }

        $coincidenciasExactas = [];
        $coincidenciasSugeridas = [];
        $inconsistencias = [];
        $sinCoincidencia = [];

        $pagosEmparejadosKeys = [];

        foreach ($movimientosExtracto as $mov) {
            $refMovNormalizada = $this->normalizarReferencia($mov['referencia_bancaria']);
            $montoMov = floatval($mov['monto']);
            $fechaMov = $mov['fecha_movimiento'];

            if ($refMovNormalizada === '0') {
                $inconsistencias[] = [
                    'extracto' => $mov,
                    'pago'     => null,
                    'motivo'   => 'Referencia bancaria vacía o no estructurada',
                    'nivel'    => 3
                ];
                continue;
            }

            $encontradoExacto = false;
            $candidatoFuzzy = null;
            $mejorSimilitud = 0.0;

            // Nivel 1: Búsqueda exacta O(1) por índice de referencia completa
            $candidatosRef = $indexByRef[$refMovNormalizada] ?? [];
            foreach ($candidatosRef as $pago) {
                $pagoKey = ($pago['origen_tabla'] ?? 'pago') . '_' . $pago['id'];
                if (in_array($pagoKey, $pagosEmparejadosKeys, true)) {
                    continue;
                }

                $diferenciaMonto = abs($montoMov - floatval($pago['monto']));
                if ($diferenciaMonto < 0.01) {
                    $coincidenciasExactas[] = [
                        'extracto'      => $mov,
                        'pago'          => $pago,
                        'similitud'     => 1.0,
                        'clasificacion' => 'COINCIDENCIA_EXACTA'
                    ];
                    $pagosEmparejadosKeys[] = $pagoKey;
                    $encontradoExacto = true;
                    break;
                }
            }

            if ($encontradoExacto) {
                continue;
            }

            // Nivel 1.5: Coincidencia de sufijo de referencia bancaria (ej. últimos 6+ dígitos) con monto idéntico
            foreach ($pagosPendientes as $pago) {
                $pagoKey = ($pago['origen_tabla'] ?? 'pago') . '_' . $pago['id'];
                if (in_array($pagoKey, $pagosEmparejadosKeys, true)) {
                    continue;
                }

                $diferenciaMonto = abs($montoMov - floatval($pago['monto']));
                if ($diferenciaMonto < 0.01) {
                    $refPagoNorm = $this->normalizarReferencia($pago['referencia']);
                    $lenP = strlen($refPagoNorm);
                    $lenM = strlen($refMovNormalizada);
                    if ($lenP >= 6 && $lenM >= 6) {
                        if (str_ends_with($refMovNormalizada, $refPagoNorm) || str_ends_with($refPagoNorm, $refMovNormalizada)) {
                            $coincidenciasExactas[] = [
                                'extracto'      => $mov,
                                'pago'          => $pago,
                                'similitud'     => 1.0,
                                'clasificacion' => 'COINCIDENCIA_EXACTA'
                            ];
                            $pagosEmparejadosKeys[] = $pagoKey;
                            $encontradoExacto = true;
                            break;
                        }
                    }
                }
            }

            if ($encontradoExacto) {
                continue;
            }

            // Nivel 2: Fuzzy Match — buscar en pagos no emparejados cercanos en monto y similitud
            foreach ($pagosPendientes as $pago) {
                $pagoKey = ($pago['origen_tabla'] ?? 'pago') . '_' . $pago['id'];
                if (in_array($pagoKey, $pagosEmparejadosKeys, true)) {
                    continue;
                }

                $montoPago = floatval($pago['monto']);
                $diferenciaMonto = abs($montoMov - $montoPago);

                if ($diferenciaMonto < 0.01) {
                    $refPagoNorm = $this->normalizarReferencia($pago['referencia']);

                    // Coincidencia de sufijo parcial de 4 o 5 dígitos con monto idéntico
                    if (strlen($refPagoNorm) >= 4 && (str_ends_with($refMovNormalizada, $refPagoNorm) || str_ends_with($refPagoNorm, $refMovNormalizada))) {
                        $candidatoFuzzy = $pago;
                        $mejorSimilitud = 0.95;
                        break;
                    }

                    $diferenciaDias = abs(strtotime($fechaMov) - strtotime($pago['fecha_pago'])) / 86400;
                    if ($diferenciaDias <= 30) {
                        $similitud = $this->calcularSimilitudJaroWinkler($refMovNormalizada, $refPagoNorm);
                        if ($similitud >= 0.85 && $similitud > $mejorSimilitud) {
                            $mejorSimilitud = $similitud;
                            $candidatoFuzzy = $pago;
                        }
                    }
                }
            }

            if ($candidatoFuzzy !== null) {
                $pagoFuzzyKey = ($candidatoFuzzy['origen_tabla'] ?? 'pago') . '_' . $candidatoFuzzy['id'];
                $coincidenciasSugeridas[] = [
                    'extracto'      => $mov,
                    'pago'          => $candidatoFuzzy,
                    'similitud'     => round($mejorSimilitud * 100, 1),
                    'clasificacion' => 'COINCIDENCIA_SUGERIDA'
                ];
                $pagosEmparejadosKeys[] = $pagoFuzzyKey;
            } else {
                $sinCoincidencia[] = [
                    'extracto'      => $mov,
                    'pago'          => null,
                    'clasificacion' => 'SIN_COINCIDENCIA'
                ];
            }
        }

        return [
            'coincidencias_exactas'   => $coincidenciasExactas,
            'coincidencias_sugeridas' => $coincidenciasSugeridas,
            'inconsistencias'         => $inconsistencias,
            'sin_coincidencia'        => $sinCoincidencia,
            'total_movimientos'       => count($movimientosExtracto)
        ];
    }

    /**
     * Realiza la conciliación y aprobación de un pago o comprobante de forma atómica.
     *
     * @param int $extractoId
     * @param int $pagoId
     * @param int $adminId
     * @param string $origenTipo 'auto' | 'comprobante' | 'pago'
     * @return array
     * @throws Exception
     */
    public function conciliarYaprobar(int $extractoId, int $pagoId, int $adminId, string $origenTipo = 'auto'): array {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $stmtExt = $db->prepare("SELECT * FROM extractos_bancarios WHERE id = :id FOR UPDATE");
            $stmtExt->execute(['id' => $extractoId]);
            $extracto = $stmtExt->fetch(PDO::FETCH_ASSOC);

            if (!$extracto) {
                throw new Exception("El movimiento del extracto no existe.");
            }

            // Determinar si es de la tabla comprobantes_pago o pagos
            $esComprobante = ($origenTipo === 'comprobante');
            if ($origenTipo === 'auto') {
                $stmtCheckComp = $db->prepare("SELECT id FROM comprobantes_pago WHERE id = :id");
                $stmtCheckComp->execute(['id' => $pagoId]);
                if ($stmtCheckComp->fetch()) {
                    $esComprobante = true;
                }
            }

            if ($esComprobante) {
                return $this->procesarConciliacionComprobante($db, $extracto, $pagoId, $adminId);
            }

            return $this->procesarConciliacionPago($db, $extracto, $pagoId, $adminId);
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[CONCILIACION] Error en conciliarYaprobar: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Concilia un pago originado desde comprobantes_pago.
     */
    private function procesarConciliacionComprobante(PDO $db, array $extracto, int $comprobanteId, int $adminId): array {
        $stmtComp = $db->prepare("
            SELECT c.*, f.unidad_id,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula,
                   per.email AS residente_email, per.email, per.telefono AS residente_telefono, per.telefono,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM comprobantes_pago c
            LEFT JOIN facturas f ON c.factura_id = f.id
            LEFT JOIN unidades u ON f.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON c.residente_id = per.id OR u.propietario_id = per.id
            WHERE c.id = :id FOR UPDATE
        ");
        $stmtComp->execute(['id' => $comprobanteId]);
        $comprobante = $stmtComp->fetch(PDO::FETCH_ASSOC);

        if (!$comprobante) {
            throw new Exception("El comprobante de pago ID {$comprobanteId} no fue encontrado.");
        }

        if (strtolower($comprobante['estado'] ?? '') === 'aprobado') {
            $conciliacionModel = new ConciliacionModel();
            $conciliacionModel->marcarConciliado($extracto['id'], $comprobanteId, $adminId);
            $db->commit();
            return [
                'success' => true,
                'mensaje' => "El comprobante Ref. {$comprobante['referencia']} ya se encontraba aprobado. Extracto vinculado exitosamente."
            ];
        }

        // Si tiene factura asociada, actualizar saldo de la factura
        if (!empty($comprobante['factura_id'])) {
            $stmtFacturaLock = $db->prepare("SELECT id, saldo, monto_pagado FROM facturas WHERE id = :factura_id FOR UPDATE");
            $stmtFacturaLock->execute(['factura_id' => $comprobante['factura_id']]);
            $factura = $stmtFacturaLock->fetch(PDO::FETCH_ASSOC);

            if ($factura) {
                $montoComp = floatval($comprobante['monto']);
                $saldoFactura = floatval($factura['saldo']);
                $nuevo_saldo = max($saldoFactura - $montoComp, 0);
                $nuevo_pagado = floatval($factura['monto_pagado']) + $montoComp;
                $saldoAFavor = $montoComp - $saldoFactura;

                $stmtFactura = $db->prepare("UPDATE facturas SET saldo = :saldo, monto_pagado = :monto_pagado, estado = :estado WHERE id = :factura_id");
                $stmtFactura->execute([
                    'saldo'       => $nuevo_saldo,
                    'monto_pagado'=> $nuevo_pagado,
                    'estado'      => $nuevo_saldo <= 0 ? 'pagada' : 'pendiente',
                    'factura_id'  => $comprobante['factura_id']
                ]);

                // Aplicar saldo a favor a la siguiente factura pendiente si corresponde
                if ($saldoAFavor > 0.01 && !empty($comprobante['residente_id'])) {
                    $stmtSiguiente = $db->prepare("
                        SELECT f.id, f.saldo FROM facturas f
                        INNER JOIN unidades u ON f.unidad_id = u.id
                        WHERE u.propietario_id = :pid AND f.saldo > 0 AND f.id != :factura_id
                        AND f.deleted_at IS NULL
                        ORDER BY f.fecha_vencimiento ASC LIMIT 1
                    ");
                    $stmtSiguiente->execute(['pid' => $comprobante['residente_id'], 'factura_id' => $comprobante['factura_id']]);
                    $siguienteFactura = $stmtSiguiente->fetch(PDO::FETCH_ASSOC);

                    if ($siguienteFactura) {
                        $abono = min($saldoAFavor, floatval($siguienteFactura['saldo']));
                        $nuevoSaldoSig = floatval($siguienteFactura['saldo']) - $abono;
                        $stmtAbono = $db->prepare("
                            UPDATE facturas SET saldo = :saldo, monto_pagado = monto_pagado + :abono,
                            estado = :estado WHERE id = :fid
                        ");
                        $stmtAbono->execute([
                            'saldo'  => $nuevoSaldoSig,
                            'abono'  => $abono,
                            'estado' => $nuevoSaldoSig <= 0 ? 'pagada' : 'pendiente',
                            'fid'    => $siguienteFactura['id']
                        ]);
                    }
                }
            }
        }

        // Actualizar comprobante de pago
        $obs = trim(($comprobante['observaciones'] ?? '') . " | Conciliado con extracto {$extracto['banco']} Lote: {$extracto['lote_importacion']}");
        $stmtUpdComp = $db->prepare("UPDATE comprobantes_pago SET estado = 'aprobado', observaciones = :observaciones WHERE id = :id");
        $stmtUpdComp->execute([
            'observaciones' => $obs,
            'id'            => $comprobanteId
        ]);

        // Registrar en auditoría
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmtLog = $db->prepare("
            INSERT INTO log_auditoria (usuario_id, admin_id, accion, tabla_afectada, registro_id, estado_anterior, estado_nuevo, detalles, ip_address)
            VALUES (:usuario_id, :admin_id, 'conciliacion_bancaria', 'comprobantes_pago', :registro_id, :estado_ant, 'aprobado', :detalles, :ip)
        ");
        $stmtLog->execute([
            'usuario_id'  => $adminId,
            'admin_id'    => $adminId,
            'registro_id' => $comprobanteId,
            'estado_ant'  => $comprobante['estado'],
            'detalles'    => 'Conciliación bancaria exitosa (Lote: ' . $extracto['lote_importacion'] . ') Ref: ' . $comprobante['referencia'],
            'ip'          => $ip
        ]);

        // Vincular en extractos_bancarios
        $conciliacionModel = new ConciliacionModel();
        $conciliacionModel->marcarConciliado($extracto['id'], $comprobanteId, $adminId);

        // Registrar abono en el libro mayor de la unidad
        if (!empty($comprobante['unidad_id'])) {
            $movimientosModel = new MovimientosModel();
            $movimientosModel->registrarMovimiento(
                intval($comprobante['unidad_id']),
                'abono_pago',
                floatval($comprobante['monto']),
                "Abono por pago conciliado Ref. " . $comprobante['referencia'],
                $comprobanteId
            );
        }

        $this->enviarNotificacionConciliacion($comprobante, $extracto);

        $db->commit();

        return [
            'success' => true,
            'mensaje' => "Pago Ref. {$comprobante['referencia']} conciliado y aprobado exitosamente."
        ];
    }

    /**
     * Concilia un pago originado desde la tabla pagos.
     */
    private function procesarConciliacionPago(PDO $db, array $extracto, int $pagoId, int $adminId): array {
        $stmtPago = $db->prepare("
            SELECT p.*, per.id AS residente_id, per.email, per.telefono, CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM pagos p
            LEFT JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON u.propietario_id = per.id
            WHERE p.id = :id FOR UPDATE
        ");
        $stmtPago->execute(['id' => $pagoId]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            throw new Exception("El pago ID {$pagoId} no fue encontrado.");
        }

        if ($pago['estado'] === 'APROBADO') {
            $conciliacionModel = new ConciliacionModel();
            $conciliacionModel->marcarConciliado($extracto['id'], $pagoId, $adminId);
            $db->commit();
            return [
                'success' => true,
                'mensaje' => "El pago Ref. {$pago['referencia']} ya se encontraba aprobado. Extracto vinculado exitosamente."
            ];
        }

        // Aprobar el pago formalmente
        $stmtUpdatePago = $db->prepare("UPDATE pagos SET estado = 'APROBADO' WHERE id = :id");
        $stmtUpdatePago->execute(['id' => $pagoId]);

        // Descontar saldo de la factura asociada
        if (!empty($pago['unidad_id'])) {
            $stmtFactura = $db->prepare("
                SELECT id, saldo FROM facturas 
                WHERE unidad_id = :uid AND estado = 'PENDIENTE' AND deleted_at IS NULL
                ORDER BY anio ASC, mes ASC LIMIT 1 FOR UPDATE
            ");
            $stmtFactura->execute(['uid' => $pago['unidad_id']]);
            $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);
            if ($factura) {
                $nuevoSaldo = round(max(0, floatval($factura['saldo']) - floatval($pago['monto'])), 2);
                $stmtSaldo = $db->prepare("UPDATE facturas SET saldo = :saldo WHERE id = :fid");
                $stmtSaldo->execute(['saldo' => $nuevoSaldo, 'fid' => $factura['id']]);
            }
        }

        // Registrar en log de auditoría
        $stmtLog = $db->prepare("
            INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo)
            VALUES (:pago_id, :admin_id, :estado_ant, 'APROBADO', :motivo)
        ");
        $stmtLog->execute([
            'pago_id'    => $pagoId,
            'admin_id'   => $adminId,
            'estado_ant' => $pago['estado'],
            'motivo'     => "Conciliación bancaria exitosa (Lote: {$extracto['lote_importacion']})"
        ]);

        // Vincular en extractos_bancarios
        $conciliacionModel = new ConciliacionModel();
        $conciliacionModel->marcarConciliado($extracto['id'], $pagoId, $adminId);

        // Registrar abono en el libro mayor de la unidad
        if (!empty($pago['unidad_id'])) {
            $movimientosModel = new MovimientosModel();
            $movimientosModel->registrarMovimiento(
                intval($pago['unidad_id']),
                'abono_pago',
                floatval($pago['monto']),
                "Abono por pago conciliado Ref. " . $pago['referencia'],
                $pagoId
            );
        }

        $this->enviarNotificacionConciliacion($pago, $extracto);

        $db->commit();

        return [
            'success' => true,
            'mensaje' => "Pago Ref. {$pago['referencia']} conciliado y aprobado exitosamente."
        ];
    }

    /**
     * Envía notificaciones de conciliación por correo y portal de residente.
     */
    private function enviarNotificacionConciliacion(array $pago, array $extracto): void {
        if (empty($pago['email'])) {
            return;
        }

        $emailService = new EmailService();
        $notifService = new NotificationService();

        $cuerpoHtml = $emailService->renderTemplate('pago_aprobado', [
            'nombreResidente' => $pago['residente_nombre'] ?: 'Estimado Residente',
            'referencia'      => $pago['referencia'],
            'monto'           => $pago['monto'],
            'fechaPago'       => date('d/m/Y', strtotime($pago['fecha_pago'])),
            'bancoOrigen'     => $pago['banco_origen'] ?? $extracto['banco'] ?? 'Transferencia Bancaria'
        ]);

        $notifService->encolarNotificacion(
            $pago['email'],
            "✅ Pago Conciliado y Aprobado - Ref. " . $pago['referencia'],
            $cuerpoHtml,
            $pago['telefono'] ?? null,
            'ambos',
            'alta'
        );

        $residenteId = $pago['residente_id'] ?? ($pago['propietario_id'] ?? null);
        if (!empty($residenteId)) {
            $notifService->registrarNotificacionResidente(
                $residenteId,
                "Pago Conciliado y Aprobado",
                "Su pago Ref. " . $pago['referencia'] . " por Bs. " . number_format($pago['monto'], 2) . " ha sido verificado con el extracto bancario y aprobado.",
                "success",
                "/residente/historial"
            );
        }
    }

    /**
     * Procesa la conciliación de un lote de pagos con transacciones individuales protegidas.
     *
     * @param array $items [['extracto_id' => int, 'pago_id' => int, 'origen_tipo' => string]]
     * @param int $adminId
     * @return array ['procesados' => int, 'omitidos' => int, 'errores' => array]
     */
    public function conciliarLote(array $items, int $adminId): array {
        if (count($items) > 100) {
            throw new InvalidArgumentException("El lote de conciliación masiva no puede superar los 100 pagos por operación.");
        }

        $procesados = 0;
        $omitidos = 0;
        $errores = [];

        foreach ($items as $item) {
            $extractoId = intval($item['extracto_id'] ?? 0);
            $pagoId = intval($item['pago_id'] ?? 0);
            $origenTipo = strval($item['origen_tipo'] ?? 'auto');

            if ($extractoId <= 0 || $pagoId <= 0) {
                $omitidos++;
                continue;
            }

            try {
                $res = $this->conciliarYaprobar($extractoId, $pagoId, $adminId, $origenTipo);
                if ($res['success']) {
                    $procesados++;
                } else {
                    $omitidos++;
                }
            } catch (\Exception $e) {
                $omitidos++;
                $errores[] = "Extracto #{$extractoId} / Pago #{$pagoId}: " . $e->getMessage();
            }
        }

        return [
            'procesados' => $procesados,
            'omitidos'   => $omitidos,
            'errores'    => $errores
        ];
    }

    /**
     * Parsea un estado de cuenta en formato PDF de Banco de Venezuela (BDV).
     *
     * @param string $rutaPdf
     * @return array
     * @throws Exception
     */
    public function parsearPdfBancoVenezuela(string $rutaPdf): array {
        $texto = $this->extraerTextoDePdf($rutaPdf);
        if (empty(trim($texto))) {
            throw new Exception("No se pudo extraer texto legible del archivo PDF del Banco de Venezuela.");
        }

        // Expresión regular que reconoce la estructura tabular del estado de cuenta de Banco de Venezuela:
        // [Referencia (8-20 dígitos)] [Descripción] [Fecha DD/MM/YYYY] [Mov NC|ND|SI] [Débito] [Crédito] [Saldo]
        $patron = '/(?P<referencia>\d{8,20})\s+(?P<descripcion>.+?)\s+(?P<fecha>\d{2}[\/\-]\d{2}[\/\-]\d{4})\s+(?P<mov>NC|ND|SI)\s+(?P<debito>-?[\d\.,]+)\s+(?P<credito>-?[\d\.,]+)\s+(?P<saldo>-?[\d\.,]+)/i';

        if (!preg_match_all($patron, $texto, $matches, PREG_SET_ORDER)) {
            $lineas = preg_split('/\r\n|\r|\n/', $texto);
            $textoReconstruido = implode("\n", array_map('trim', $lineas));
            preg_match_all($patron, $textoReconstruido, $matches, PREG_SET_ORDER);
        }

        if (empty($matches)) {
            throw new Exception("No se detectaron transacciones válidas con el formato de Banco de Venezuela en el PDF.");
        }

        $movimientos = [];

        foreach ($matches as $m) {
            $movTipo = strtoupper(trim($m['mov'] ?? ''));

            // Omitir líneas de saldo inicial u otros conceptos no transaccionales
            if ($movTipo === 'SI') {
                continue;
            }

            $fechaRaw = trim($m['fecha'] ?? '');
            $fechaTimestamp = strtotime(str_replace('/', '-', $fechaRaw));
            if ($fechaTimestamp === false) {
                continue;
            }
            $fecha = date('Y-m-d', $fechaTimestamp);

            $referencia = trim($m['referencia'] ?? '');
            $descripcion = trim($m['descripcion'] ?? '');

            $debitoMonto = $this->parsearMontoVenezolano($m['debito'] ?? '0');
            $creditoMonto = $this->parsearMontoVenezolano($m['credito'] ?? '0');

            if ($movTipo === 'NC') {
                // Nota de Crédito: cobranza / ingreso
                $tipo = 'credito';
                $monto = ($creditoMonto > 0) ? $creditoMonto : $debitoMonto;
            } else {
                // Nota de Débito: comisión / egreso
                $tipo = 'debito';
                $monto = ($debitoMonto > 0) ? $debitoMonto : $creditoMonto;
            }

            if ($monto <= 0) {
                continue;
            }

            $movimientos[] = [
                'fecha'       => $fecha,
                'referencia'  => $referencia,
                'descripcion' => $descripcion,
                'monto'       => $monto,
                'tipo'        => $tipo
            ];
        }

        if (empty($movimientos)) {
            throw new Exception("No se pudieron estructurar movimientos operativos válidos a partir del PDF de Banco de Venezuela.");
        }

        return $movimientos;
    }

    /**
     * Extrae texto plano de un documento PDF de forma nativa en PHP decodificando flujos /FlateDecode y CMaps /ToUnicode.
     *
     * @param string $rutaPdf
     * @return string
     */
    public function extraerTextoDePdf(string $rutaPdf): string {
        if (!file_exists($rutaPdf) || !is_readable($rutaPdf)) {
            return '';
        }

        $contenido = file_get_contents($rutaPdf);
        if ($contenido === false || strlen($contenido) < 10) {
            return '';
        }

        // 1. Extraer todos los objetos del PDF
        preg_match_all('/(\d+)\s+0\s+obj(.*?)endobj/s', $contenido, $allObjs, PREG_SET_ORDER);
        $objsById = [];
        foreach ($allObjs as $o) {
            $objsById[(int)$o[1]] = $o[2];
        }

        // 2. Extraer y construir todos los CMaps (/ToUnicode)
        $cmapsByObjId = [];
        $allGlobalCmaps = [];
        foreach ($objsById as $id => $body) {
            if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $body, $tu)) {
                $tuId = (int)$tu[1];
                $tuStream = $this->descomprimirStreamPdf($objsById[$tuId] ?? '');
                if ($tuStream) {
                    $cmap = $this->parsearCMapPdf($tuStream);
                    $cmapsByObjId[$id] = $cmap;
                    foreach ($cmap as $k => $v) {
                        $allGlobalCmaps[$k] = $v;
                    }
                }
            }
        }

        $textoCompleto = '';

        // 3. Identificar páginas del documento
        $pageBodies = [];
        foreach ($objsById as $id => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) && !preg_match('/\/Type\s*\/Pages\b/', $body)) {
                $pageBodies[] = $body;
            }
        }

        // Si se encontraron páginas estructuradas, procesar por página con sus fuentes asignadas
        if (!empty($pageBodies)) {
            foreach ($pageBodies as $body) {
                $fonts = [];
                if (preg_match('/\/Font\s*<<([^>]+)>>/s', $body, $fDict)) {
                    if (preg_match_all('/\/([A-Za-z0-9]+)\s+(\d+)\s+0\s+R/', $fDict[1], $fMatches, PREG_SET_ORDER)) {
                        foreach ($fMatches as $fm) {
                            $fName = '/' . $fm[1];
                            $fObjId = (int)$fm[2];
                            $fonts[$fName] = $cmapsByObjId[$fObjId] ?? [];
                        }
                    }
                }

                $contentIds = [];
                if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $body, $sCont)) {
                    $contentIds[] = (int)$sCont[1];
                } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $body, $mCont)) {
                    if (preg_match_all('/(\d+)\s+0\s+R/', $mCont[1], $cMatches)) {
                        foreach ($cMatches[1] as $cid) {
                            $contentIds[] = (int)$cid;
                        }
                    }
                }

                foreach ($contentIds as $cId) {
                    $stream = $this->descomprimirStreamPdf($objsById[$cId] ?? '');
                    if ($stream) {
                        $textoCompleto .= $this->decodificarStreamContenido($stream, $fonts, $allGlobalCmaps) . "\n";
                    }
                }
            }
        } else {
            // Fallback: procesar todos los streams del archivo si no hay estructura estándar de /Page
            if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $contenido, $streamMatches)) {
                foreach ($streamMatches[1] as $rawStream) {
                    $decomp = $this->descomprimirCadena($rawStream);
                    $textoCompleto .= $this->decodificarStreamContenido($decomp, [], $allGlobalCmaps) . "\n";
                }
            }
        }

        // Limpiar secuencias de escape típicas de PDF
        $textoCompleto = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $textoCompleto);

        // Si no hubo texto estructurado, fallback a cadenas de texto plano imprimibles
        if (empty(trim($textoCompleto))) {
            preg_match_all('/[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\.,:\\/\-]{2,}/', $contenido, $plain);
            $textoCompleto = implode(' ', $plain[0] ?? []);
        }

        return $textoCompleto;
    }

    /**
     * Decodifica un stream de contenido aplicando CMaps de fuentes y operadores Tj / TJ estándar.
     */
    private function decodificarStreamContenido(string $stream, array $fonts, array $globalCmap): string {
        $texto = '';
        $curFont = '';
        $lines = preg_split('/\r\n|\r|\n/', $stream);

        foreach ($lines as $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') continue;

            if (preg_match('/(\/F[A-Za-z0-9]+)\s+[\d\.]+\s+Tf/', $lineTrim, $tf)) {
                $curFont = $tf[1];
            }

            // Operador <hex> Tj (cadenas hexadecimales de 2 bytes por caracter)
            if (preg_match_all('/<([0-9a-fA-F]+)>\s*Tj/', $lineTrim, $tjs)) {
                foreach ($tjs[1] as $hex) {
                    $cmap = $fonts[$curFont] ?? $globalCmap;
                    $len = strlen($hex);
                    for ($i = 0; $i < $len; $i += 4) {
                        $cid = hexdec(substr($hex, $i, 4));
                        $texto .= $cmap[$cid] ?? $globalCmap[$cid] ?? '';
                    }
                    $texto .= ' ';
                }
            }

            // Operador (ascii) Tj
            if (preg_match_all('/\((.*?)\)\s*Tj/', $lineTrim, $tjas)) {
                foreach ($tjas[1] as $asc) {
                    $texto .= $asc . ' ';
                }
            }

            // Operador array [...] TJ
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $lineTrim, $tjsArr)) {
                foreach ($tjsArr[1] as $tj) {
                    // Cadenas en formato hex <hex>
                    if (preg_match_all('/<([0-9a-fA-F]+)>/', $tj, $hexParts)) {
                        $cmap = $fonts[$curFont] ?? $globalCmap;
                        foreach ($hexParts[1] as $hex) {
                            $len = strlen($hex);
                            for ($i = 0; $i < $len; $i += 4) {
                                $cid = hexdec(substr($hex, $i, 4));
                                $texto .= $cmap[$cid] ?? $globalCmap[$cid] ?? '';
                            }
                        }
                        $texto .= ' ';
                    }
                    // Cadenas literales en paréntesis (ascii)
                    if (preg_match_all('/\((.*?)\)/', $tj, $ascParts)) {
                        $texto .= implode('', $ascParts[1]) . ' ';
                    }
                }
            }

            // Salto de línea al encontrar operadores posicionales o cierre de bloque
            if (preg_match('/(T\*|ET|\d+\s+-\d+\s+Td)/', $lineTrim)) {
                $texto .= "\n";
            }
        }

        return $texto;
    }

    /**
     * Descomprime un flujo binario contenido dentro de un objeto PDF.
     */
    private function descomprimirStreamPdf(string $objContent): ?string {
        if (preg_match('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $objContent, $sm)) {
            return $this->descomprimirCadena($sm[1]);
        }
        return null;
    }

    /**
     * Descomprime una cadena comprimida con zlib (FlateDecode).
     */
    private function descomprimirCadena(string $raw): string {
        $decomp = @gzuncompress($raw);
        if ($decomp === false) {
            $decomp = @gzinflate($raw);
        }
        return ($decomp !== false) ? $decomp : $raw;
    }

    /**
     * Parsea un stream CMap /ToUnicode extrayendo asignaciones de bfchar y bfrange.
     */
    private function parsearCMapPdf(string $cmapStream): array {
        $map = [];

        // bfchar: <srcHex> <destHex>
        if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $cmapStream, $chars, PREG_SET_ORDER)) {
            foreach ($chars as $ch) {
                $map[hexdec($ch[1])] = mb_chr(hexdec($ch[2]), 'UTF-8');
            }
        }

        // bfrange: <startHex> <endHex> <destStartHex>
        if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $cmapStream, $ranges, PREG_SET_ORDER)) {
            foreach ($ranges as $rng) {
                $start = hexdec($rng[1]);
                $end = hexdec($rng[2]);
                $dest = hexdec($rng[3]);
                for ($cid = $start; $cid <= $end; $cid++) {
                    $map[$cid] = mb_chr($dest + ($cid - $start), 'UTF-8');
                }
            }
        }

        // bfrange con array explícito: <startHex> <endHex> [ <dest1> <dest2> ... ]
        if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*\[(.*?)\]/s', $cmapStream, $arrRanges, PREG_SET_ORDER)) {
            foreach ($arrRanges as $ar) {
                $start = hexdec($ar[1]);
                $end = hexdec($ar[2]);
                preg_match_all('/<([0-9a-fA-F]+)>/', $ar[3], $dests);
                foreach ($dests[1] as $idx => $dhex) {
                    $map[$start + $idx] = mb_chr(hexdec($dhex), 'UTF-8');
                }
            }
        }

        return $map;
    }

    /**
     * Parsea un monto en formato numérico venezolano a float.
     *
     * @param string $montoStr
     * @return float
     */
    private function parsearMontoVenezolano(string $montoStr): float {
        $montoStr = trim($montoStr);
        $montoStr = str_replace([' ', '$', 'Bs.', 'Bs', '(', ')'], '', $montoStr);
        if (str_contains($montoStr, ',') && str_contains($montoStr, '.')) {
            $montoStr = str_replace('.', '', $montoStr);
            $montoStr = str_replace(',', '.', $montoStr);
        } elseif (str_contains($montoStr, ',')) {
            $montoStr = str_replace(',', '.', $montoStr);
        }
        return abs(floatval($montoStr));
    }
}
