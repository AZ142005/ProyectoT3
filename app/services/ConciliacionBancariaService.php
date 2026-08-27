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

            $columnas = str_getcsv($lineaTrim, $delimitador);
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
     * Ejecuta el motor de cruce inteligente jerárquico de 3 niveles entre extracto y pagos reportados.
     */
    public function ejecutarCruceInteligente(array $movimientosExtracto): array {
        $db = Database::getConnection();

        // Obtener todos los pagos pendientes o en revisión con datos del residente
        $sqlPagos = "
            SELECT p.id, p.unidad_id, p.monto, p.fecha_pago, p.referencia, p.banco_origen, p.banco_destino, p.estado,
                   CONCAT(per.nombre, ' ', per.apellido) AS residente_nombre, per.cedula AS residente_cedula,
                   per.email AS residente_email, per.telefono AS residente_telefono,
                   u.numero AS unidad_numero, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM pagos p
            LEFT JOIN unidades u ON p.unidad_id = u.id
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas per ON u.propietario_id = per.id
            WHERE p.estado IN ('PENDIENTE', 'EN REVISIÓN')
            ORDER BY p.fecha_pago ASC
        ";
        $pagosPendientes = $db->query($sqlPagos)->fetchAll(PDO::FETCH_ASSOC);

        // Fase B.3: Indexar pagos por referencia normalizada para búsqueda O(1)
        $indexByRef = [];
        foreach ($pagosPendientes as $pago) {
            $refNorm = $this->normalizarReferencia($pago['referencia']);
            $indexByRef[$refNorm][] = $pago;
        }

        $coincidenciasExactas = [];
        $coincidenciasSugeridas = [];
        $inconsistencias = [];
        $sinCoincidencia = [];

        $pagosEmparejadosIds = [];

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

            // Nivel 1: Búsqueda exacta O(1) por índice de referencia
            $candidatosRef = $indexByRef[$refMovNormalizada] ?? [];
            foreach ($candidatosRef as $pago) {
                if (in_array($pago['id'], $pagosEmparejadosIds)) {
                    continue;
                }

                $diferenciaMonto = abs($montoMov - floatval($pago['monto']));
                if ($diferenciaMonto < 0.01) {
                    $coincidenciasExactas[] = [
                        'extracto'   => $mov,
                        'pago'       => $pago,
                        'similitud'  => 1.0,
                        'clasificacion' => 'COINCIDENCIA_EXACTA'
                    ];
                    $pagosEmparejadosIds[] = $pago['id'];
                    $encontradoExacto = true;
                    break;
                }
            }

            if ($encontradoExacto) {
                continue;
            }

            // Nivel 2: Fuzzy Match — buscar en pagos no emparejados cercanos en monto y fecha
            foreach ($pagosPendientes as $pago) {
                if (in_array($pago['id'], $pagosEmparejadosIds)) {
                    continue;
                }

                $montoPago = floatval($pago['monto']);
                $diferenciaMonto = abs($montoMov - $montoPago);

                if ($diferenciaMonto < 0.01) {
                    $diferenciaDias = abs(strtotime($fechaMov) - strtotime($pago['fecha_pago'])) / 86400;
                    if ($diferenciaDias <= 3) {
                        $refPagoNorm = $this->normalizarReferencia($pago['referencia']);
                        $similitud = $this->calcularSimilitudJaroWinkler($refMovNormalizada, $refPagoNorm);
                        if ($similitud >= 0.85 && $similitud > $mejorSimilitud) {
                            $mejorSimilitud = $similitud;
                            $candidatoFuzzy = $pago;
                        }
                    }
                }
            }

            if ($candidatoFuzzy !== null) {
                $coincidenciasSugeridas[] = [
                    'extracto'      => $mov,
                    'pago'          => $candidatoFuzzy,
                    'similitud'     => round($mejorSimilitud * 100, 1),
                    'clasificacion' => 'COINCIDENCIA_SUGERIDA'
                ];
                $pagosEmparejadosIds[] = $candidatoFuzzy['id'];
            } else {
                $sinCoincidencia[] = [
                    'extracto' => $mov,
                    'pago'     => null,
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
     * Realiza la conciliación y aprobación de un pago de forma atómica en una transacción individual.
     */
    public function conciliarYaprobar(int $extractoId, int $pagoId, int $adminId): array {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $stmtExt = $db->prepare("SELECT * FROM extractos_bancarios WHERE id = :id FOR UPDATE");
            $stmtExt->execute(['id' => $extractoId]);
            $extracto = $stmtExt->fetch(PDO::FETCH_ASSOC);

            if (!$extracto) {
                throw new Exception("El movimiento del extracto no existe.");
            }

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

            // Si el pago ya fue aprobado manualmente previo a la conciliación
            if ($pago['estado'] === 'APROBADO') {
                $conciliacionModel = new ConciliacionModel();
                $conciliacionModel->marcarConciliado($extractoId, $pagoId, $adminId);
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
            $conciliacionModel->marcarConciliado($extractoId, $pagoId, $adminId);

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

            // Disparar evento de notificación al residente (Fase 2)
            if (!empty($pago['email'])) {
                $emailService = new EmailService();
                $notifService = new NotificationService();

                $cuerpoHtml = $emailService->renderTemplate('pago_aprobado', [
                    'nombreResidente' => $pago['residente_nombre'] ?: 'Estimado Residente',
                    'referencia'      => $pago['referencia'],
                    'monto'           => $pago['monto'],
                    'fechaPago'       => date('d/m/Y', strtotime($pago['fecha_pago'])),
                    'bancoOrigen'     => $pago['banco_origen'] ?? 'Transferencia Bancaria'
                ]);

                $notifService->encolarNotificacion(
                    $pago['email'],
                    "✅ Pago Conciliado y Aprobado - Ref. " . $pago['referencia'],
                    $cuerpoHtml,
                    $pago['telefono'],
                    'ambos',
                    'alta'
                );

                if (!empty($pago['residente_id'])) {
                    $notifService->registrarNotificacionResidente(
                        $pago['residente_id'],
                        "Pago Conciliado y Aprobado",
                        "Su pago Ref. " . $pago['referencia'] . " por Bs. " . number_format($pago['monto'], 2) . " ha sido verificado con el extracto bancario y aprobado.",
                        "success",
                        "/residente/historial"
                    );
                }
            }

            $db->commit();

            return [
                'success' => true,
                'mensaje' => "Pago Ref. {$pago['referencia']} conciliado y aprobado exitosamente."
            ];
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en conciliarYaprobar: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesa la conciliación de un lote de pagos con transacciones individuales protegidas.
     *
     * @param array $items [['extracto_id' => int, 'pago_id' => int]]
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

            if ($extractoId <= 0 || $pagoId <= 0) {
                $omitidos++;
                continue;
            }

            try {
                $res = $this->conciliarYaprobar($extractoId, $pagoId, $adminId);
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
}
