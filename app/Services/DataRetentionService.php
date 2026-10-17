<?php
namespace App\Services;

use PDO;
use App\Core\Database;

/**
 * Servicio de retención de datos acumulados.
 *
 * Centraliza la política de retención de las tres tablas que crecen sin
 * límite (log_auditoria, historial_estado_abono, rate_limits) y es la única
 * fuente de verdad usada por el panel admin (RespaldoController) y por el
 * CLI scripts/cleanup_retencion.php.
 *
 * Regla de oro: la purga SIEMPRE va precedida de un export verificado
 * (.sql.gz + SHA-256) en storage/backups/purgas/. Si el export no se puede
 * escribir o releer, no se borra nada y se registra estado 'fallido'
 * (best-effort) en purgas_log. El corte temporal se calcula una sola vez
 * por corrida y se usa tanto para el SELECT como para el DELETE.
 *
 * Sin registros elegibles no se genera archivo ni fila de bitácora: la
 * corrida programada con 0 elegibles es un no-op limpio.
 *
 * Ciclo único de archivos (anti-acumulación): un archivo descargado se
 * elimina del servidor LIMPIEZA_DIAS_TRAS_DESCARGA (7) días después de la
 * descarga; un exporte de purga que nunca se descargó se elimina a los
 * LIMPIEZA_DIAS_SIN_DESCARGAR (30) días. Las filas de backups_log y
 * purgas_log NUNCA se borran: quedan como historial con nota.
 */
class DataRetentionService {

    /**
     * Política de retención: tabla => [columna de fecha, días de retención].
     *
     * - log_auditoria usa created_at (indexado con idx_created_at); no usar
     *   fecha_registro porque no tiene índice usable por rango.
     * - historial_estado_abono usa creado_en (requiere idx_hist_creado).
     * - rate_limits usa window_start: la ventana máxima real del rate limiter
     *   es 3600 s, así que 30 días es un margen seguro.
     */
    public const RETENCION = [
        'log_auditoria'          => ['col' => 'created_at',   'dias' => 365],
        'historial_estado_abono' => ['col' => 'creado_en',    'dias' => 365],
        'rate_limits'            => ['col' => 'window_start', 'dias' => 30],
    ];

    /**
     * Ciclo único de limpieza de archivos (anti-acumulación).
     *
     * - Un archivo descargado (respaldo general o exporte de purga) se
     *   elimina del servidor 7 días después de la descarga.
     * - Un exporte de purga que nunca se descargó se elimina a los 30 días
     *   desde su ejecución.
     *
     * Constantes configurables: las usan el panel, el CLI de retención y los
     * scripts standalone (cleanup_backups.php) para aplicar las mismas reglas.
     */
    public const LIMPIEZA_DIAS_TRAS_DESCARGA = 7;
    public const LIMPIEZA_DIAS_SIN_DESCARGAR = 30;

    /** @var PDO Conexión activa. */
    private PDO $db;

    /** @var string Directorio destino de los exportes de purga. */
    private string $dirExportes;

    /** @var bool|null Cache del chequeo INFORMATION_SCHEMA de purgas_log. */
    private ?bool $purgasLogExiste = null;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->dirExportes = dirname(__DIR__, 2) . '/storage/backups/purgas';
    }

    // =====================================================================
    // WHITELIST Y CORTES (puros / estáticos)
    // =====================================================================

    /** @return string[] Tablas con política de retención. */
    public static function tablasPermitidas(): array {
        return array_keys(self::RETENCION);
    }

    /** Indica si la tabla está en la whitelist de retención. */
    public static function esTablaPermitida(string $tabla): bool {
        return isset(self::RETENCION[$tabla]);
    }

    /**
     * Configuración de retención de una tabla; rechaza cualquier tabla fuera
     * de la whitelist (identificadores dinámicos jamás provienen del input).
     *
     * @return array{col:string,dias:int}
     * @throws \InvalidArgumentException
     */
    public static function configuracion(string $tabla): array {
        if (!self::esTablaPermitida($tabla)) {
            throw new \InvalidArgumentException(
                "Tabla no permitida para retención: '{$tabla}'. Permitidas: " . implode(', ', self::tablasPermitidas())
            );
        }
        return self::RETENCION[$tabla];
    }

    /**
     * Corte puro: fecha/hora (Y-m-d H:i:s) anterior a la cual un registro se
     * considera elegible para exportar/purgar. Método estático y testeable
     * sin BD; la instancia lo alimenta con el NOW() de la base de datos.
     *
     * La resta es de días calendario (no de segundos fijos) para que un
     * cambio de horario de verano no desplace la hora del corte.
     */
    public static function calcularCorte(string $ahora, int $dias): string {
        $fecha = new \DateTime($ahora);
        $fecha->sub(new \DateInterval('P' . $dias . 'D'));
        return $fecha->format('Y-m-d H:i:s');
    }

    /**
     * Regla pura del ciclo único: ¿la descarga está vencida? Un archivo con
     * fecha de descarga anterior al corte ($ahora - $dias) debe eliminarse.
     * Compara cadenas 'Y-m-d H:i:s' (orden lexicográfico = cronológico).
     */
    public static function vencidoTrasDescarga(
        ?string $fechaDescarga,
        string $ahora,
        int $dias = self::LIMPIEZA_DIAS_TRAS_DESCARGA
    ): bool {
        if ($fechaDescarga === null || $fechaDescarga === '') {
            return false;
        }
        return $fechaDescarga < self::calcularCorte($ahora, $dias);
    }

    /**
     * Regla pura del ciclo único: ¿un exporte NUNCA descargado está vencido?
     * Si existe fecha de descarga, esta regla no aplica (manda la de 7 días);
     * sin descarga, vence a los $dias (30) desde su ejecución.
     */
    public static function vencidoSinDescargar(
        ?string $fechaDescarga,
        ?string $fechaEjecucion,
        string $ahora,
        int $dias = self::LIMPIEZA_DIAS_SIN_DESCARGAR
    ): bool {
        if ($fechaDescarga !== null && $fechaDescarga !== '') {
            return false;
        }
        if ($fechaEjecucion === null || $fechaEjecucion === '') {
            return false;
        }
        return $fechaEjecucion < self::calcularCorte($ahora, $dias);
    }

    // =====================================================================
    // SERIALIZACIÓN SQL (pública y pura: testeable sin BD)
    // =====================================================================

    /**
     * Convierte un valor escalar o null en un literal SQL seguro. Mismo
     * espíritu que el fallback PDO de scripts/backup_database.php: NULL tal
     * cual, números sin comillas y texto entre comillas simples con comilla
     * y backslash escapados (MySQL interpreta \ como escape).
     *
     * @param mixed $valor
     */
    public static function literalSql($valor): string {
        if ($valor === null) {
            return 'NULL';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if (is_int($valor)) {
            return (string)$valor;
        }
        if (is_float($valor)) {
            // PHP 8 formatea floats con punto decimal, independiente del locale.
            return (string)$valor;
        }

        $texto = (string)$valor;
        $texto = str_replace('\\', '\\\\', $texto);
        $texto = str_replace("'", "''", $texto);
        return "'" . $texto . "'";
    }

    /**
     * Serializa filas en sentencias INSERT ... VALUES. Público y puro:
     * testeable sin conexión a la base de datos.
     *
     * @param string $tabla Nombre de tabla (se valida como identificador simple).
     * @param array<int,array<string,mixed>> $filas
     * @return string Una sentencia INSERT por fila, terminada en salto de línea.
     * @throws \InvalidArgumentException Si el identificador de tabla o columna es inválido.
     */
    public static function serializarFilas(string $tabla, array $filas): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) {
            throw new \InvalidArgumentException("Identificador de tabla inválido: '{$tabla}'.");
        }
        if (empty($filas)) {
            return '';
        }

        $sql = '';
        foreach ($filas as $fila) {
            $columnas = array_keys($fila);
            $cols = [];
            foreach ($columnas as $col) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$col)) {
                    throw new \InvalidArgumentException("Identificador de columna inválido: '{$col}'.");
                }
                $cols[] = '`' . $col . '`';
            }
            $valores = array_map([self::class, 'literalSql'], array_values($fila));
            $sql .= "INSERT INTO `{$tabla}` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $valores) . ");\n";
        }
        return $sql;
    }

    // =====================================================================
    // ESTADÍSTICAS Y BITÁCORA
    // =====================================================================

    /**
     * Estadísticas de acumulación por tabla. Sin argumento: las 3 tablas de
     * la política. Con argumento: solo esa tabla (validada por whitelist).
     *
     * @return array<string,array{tabla:string,columna:string,dias:int,existe:bool,filas:int,tamano_mb:float,mas_antiguo:?string,elegibles:int,fecha_corte:?string}>
     */
    public function estadisticas(?string $tabla = null): array {
        $tablas = $tabla === null ? self::tablasPermitidas() : [self::validarNombre($tabla)];

        $resultado = [];
        foreach ($tablas as $t) {
            $cfg = self::RETENCION[$t];
            $col = $cfg['col'];

            $fila = [
                'tabla'       => $t,
                'columna'     => $col,
                'dias'        => (int)$cfg['dias'],
                'existe'      => false,
                'filas'       => 0,
                'tamano_mb'   => 0.0,
                'mas_antiguo' => null,
                'elegibles'   => 0,
                'fecha_corte' => null,
            ];

            if (!$this->tablaExiste($t)) {
                $resultado[$t] = $fila;
                continue;
            }
            $fila['existe'] = true;

            // Tamaño (datos + índices) vía information_schema.
            $stmt = $this->db->prepare("
                SELECT ROUND((COALESCE(data_length, 0) + COALESCE(index_length, 0)) / 1048576, 3)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
            ");
            $stmt->execute(['t' => $t]);
            $fila['tamano_mb'] = (float)$stmt->fetchColumn();

            $fila['filas'] = (int)$this->db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            $fila['mas_antiguo'] = $this->db->query("SELECT MIN(`{$col}`) FROM `{$t}`")->fetchColumn() ?: null;
            $fila['fecha_corte'] = $this->fechaCorte($t);

            $stmtCorte = $this->db->prepare("SELECT COUNT(*) FROM `{$t}` WHERE `{$col}` < :corte");
            $stmtCorte->execute(['corte' => $fila['fecha_corte']]);
            $fila['elegibles'] = (int)$stmtCorte->fetchColumn();

            $resultado[$t] = $fila;
        }

        return $resultado;
    }

    /**
     * Historial de exportes y purgas registradas en purgas_log, más recientes
     * primero. Devuelve [] si la migración aún no se aplicó.
     */
    public function listarPurgas(int $limit = 50): array {
        if (!$this->verificarPurgasLog()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->query("
            SELECT p.*, COALESCE(u.nombre_completo, 'Automático (CLI)') AS admin_nombre
            FROM purgas_log p
            LEFT JOIN usuarios u ON p.admin_id = u.id
            ORDER BY p.id DESC
            LIMIT {$limit}
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ciclo único de exportes de purga: elimina el archivo .sql.gz (y su
     * .sha256 hermano) cuando la fila de purgas_log está vencida:
     *  - descargado: fecha_descarga + LIMPIEZA_DIAS_TRAS_DESCARGA (7) < ahora, o
     *  - nunca descargado: fecha_ejecucion + LIMPIEZA_DIAS_SIN_DESCARGAR (30) < ahora.
     *
     * Reemplaza la antigua rotación por antigüedad de archivo (366 días).
     * Las filas de purgas_log NUNCA se borran: quedan como historial con nota
     * («archivo eliminado tras descarga…» / «archivo eliminado sin descarga…»).
     *
     * @param bool $dryRun true = reporta sin borrar ni anotar.
     * @return array{eliminados:int,detalles:array<int,array{id:int,archivo:string,regla:string}>}
     */
    public function limpiarExportesPurgas(bool $dryRun = false): array {
        if (!$this->verificarPurgasLog() || !is_dir($this->dirExportes)) {
            return ['eliminados' => 0, 'detalles' => []];
        }

        $ahora = (string)$this->db->query('SELECT NOW()')->fetchColumn();
        $stmt = $this->db->query("
            SELECT id, archivo, fecha_descarga, fecha_ejecucion, notas
            FROM purgas_log
            WHERE archivo IS NOT NULL AND archivo <> ''
            ORDER BY id ASC
        ");

        $eliminados = 0;
        $detalles = [];
        foreach ($stmt as $fila) {
            $fechaDescarga = $fila['fecha_descarga'] ?? null;
            $fechaEjecucion = $fila['fecha_ejecucion'] ?? null;

            $trasDescarga = self::vencidoTrasDescarga($fechaDescarga, $ahora);
            $sinDescargar = !$trasDescarga
                && self::vencidoSinDescargar($fechaDescarga, $fechaEjecucion, $ahora);
            if (!$trasDescarga && !$sinDescargar) {
                continue;
            }

            $archivo = basename((string)$fila['archivo']);
            $ruta = $this->dirExportes . '/' . $archivo;
            if (!is_file($ruta)) {
                continue; // ya eliminado en una corrida anterior (o ausente)
            }

            $regla = $trasDescarga ? 'descargado + 7 días' : 'sin descargar + 30 días';
            if (!$dryRun) {
                if (!@unlink($ruta)) {
                    continue; // se reintenta en la próxima corrida
                }
                // El .sha256 hermano acompaña al exporte eliminado (si existe).
                $rutaSha = $ruta . '.sha256';
                if (is_file($rutaSha)) {
                    @unlink($rutaSha);
                }
                $nota = $trasDescarga
                    ? 'Archivo eliminado tras descarga (ciclo único: 7 días).'
                    : 'Archivo eliminado sin descarga (ciclo único: 30 días).';
                $this->anotarPurgasLog((int)$fila['id'], (string)($fila['notas'] ?? ''), $nota);
            }

            $eliminados++;
            $detalles[] = ['id' => (int)$fila['id'], 'archivo' => $archivo, 'regla' => $regla];
        }

        return ['eliminados' => $eliminados, 'detalles' => $detalles];
    }

    /**
     * Backstop del respaldo general: si una fila de backups_log fue descargada
     * y pasaron LIMPIEZA_DIAS_TRAS_DESCARGA (7) días, elimina el archivo físico
     * (.sql.gz + .sha256) y anota la fila. El reemplazo diario de
     * scripts/backup_database.php cubre el caso normal (respaldo único); este
     * barrido protege cuando el respaldo dejó de generarse.
     *
     * Las filas de backups_log NUNCA se borran: quedan como historial con nota.
     *
     * @param bool $dryRun true = reporta sin borrar ni anotar.
     * @return array{eliminados:int,detalles:array<int,array{id:int,archivo:string,regla:string}>}
     */
    public function limpiarRespaldoGeneralVencido(bool $dryRun = false): array {
        if (!$this->tablaExiste('backups_log')) {
            return ['eliminados' => 0, 'detalles' => []];
        }

        $ahora = (string)$this->db->query('SELECT NOW()')->fetchColumn();
        $stmt = $this->db->query("
            SELECT id, nombre_archivo, fecha_descarga, notas
            FROM backups_log
            WHERE fecha_descarga IS NOT NULL
            ORDER BY id ASC
        ");

        $dirBackups = dirname(__DIR__, 2) . '/storage/backups';
        $eliminados = 0;
        $detalles = [];
        foreach ($stmt as $fila) {
            if (!self::vencidoTrasDescarga($fila['fecha_descarga'] ?? null, $ahora)) {
                continue;
            }

            $archivo = basename((string)$fila['nombre_archivo']);
            $ruta = $dirBackups . '/' . $archivo;
            if (!is_file($ruta)) {
                continue; // ya eliminado (reemplazo diario u otra corrida)
            }

            if (!$dryRun) {
                if (!@unlink($ruta)) {
                    continue; // se reintenta en la próxima corrida
                }
                $rutaSha = $ruta . '.sha256';
                if (is_file($rutaSha)) {
                    @unlink($rutaSha);
                }
                $this->anotarBackupsLog(
                    (int)$fila['id'],
                    (string)($fila['notas'] ?? ''),
                    'Archivo eliminado tras descarga (ciclo único: 7 días).'
                );
            }

            $eliminados++;
            $detalles[] = ['id' => (int)$fila['id'], 'archivo' => $archivo, 'regla' => 'descargado + 7 días'];
        }

        return ['eliminados' => $eliminados, 'detalles' => $detalles];
    }

    // =====================================================================
    // EXPORTAR / PURGAR
    // =====================================================================

    /**
     * Exporta los registros elegibles de una tabla (sin borrar nada).
     *
     * @param string $tabla   Tabla de la whitelist.
     * @param string $modo    'manual' | 'programado'.
     * @param int|null $adminId Usuario que dispara la operación (null = CLI).
     * @return array Resultado normalizado (ver ejecutarExporte()).
     */
    public function exportar(string $tabla, string $modo = 'manual', ?int $adminId = null): array {
        return $this->ejecutarExporte($tabla, $modo, $adminId, false);
    }

    /**
     * Purga los registros elegibles de una tabla: export verificado primero,
     * DELETE con el mismo corte después, y contraste de conteos al final.
     *
     * @param string $tabla   Tabla de la whitelist.
     * @param string $modo    'manual' | 'programado'.
     * @param int|null $adminId Usuario que dispara la operación (null = CLI).
     * @return array Resultado normalizado (ver ejecutarExporte()).
     */
    public function purgar(string $tabla, string $modo = 'manual', ?int $adminId = null): array {
        return $this->ejecutarExporte($tabla, $modo, $adminId, true);
    }

    /**
     * Flujo común de exportación/purga:
     *  1. Validación de whitelist y existencia de tablas.
     *  2. Corte único (NOW - días) para SELECT y DELETE.
     *  3. SELECT de elegibles + escritura .sql.gz + verificación (existe,
     *     tamaño > 0, gzdecode re-lee el contenido exacto).
     *  4. Registro en purgas_log (tipo exportacion|purga).
     *  5. Si $eliminar: DELETE con el mismo corte y contraste de conteos
     *     (estado 'parcial' + nota si difieren).
     *
     * Cualquier fallo previo al DELETE deja estado 'fallido' en purgas_log
     * (si la tabla existe) y no borra nada.
     */
    private function ejecutarExporte(string $tabla, string $modo, ?int $adminId, bool $eliminar): array {
        if (!in_array($modo, ['manual', 'programado'], true)) {
            throw new \InvalidArgumentException("Modo inválido: '{$modo}'. Use 'manual' o 'programado'.");
        }
        $cfg = self::configuracion($tabla);
        $col = $cfg['col'];
        $tipo = $eliminar ? 'purga' : 'exportacion';
        $base = [
            'tabla'                 => $tabla,
            'tipo'                  => $tipo,
            'fecha_corte'           => null,
            'registros_exportados'  => 0,
            'registros_eliminados'  => 0,
            'archivo'               => null,
            'hash_sha256'           => null,
            'tamano_bytes'          => 0,
        ];

        if (!$this->tablaExiste($tabla)) {
            return array_merge($base, [
                'exito'   => false,
                'estado'  => 'fallido',
                'mensaje' => "La tabla '{$tabla}' no existe en la base de datos.",
            ]);
        }
        if (!$this->verificarPurgasLog()) {
            return array_merge($base, [
                'exito'   => false,
                'estado'  => 'fallido',
                'mensaje' => "La tabla 'purgas_log' no existe. Ejecute scripts/migrate_retencion_datos_schema.php.",
            ]);
        }

        // 1) Corte único para toda la corrida.
        $corte = $this->fechaCorte($tabla);
        $base['fecha_corte'] = $corte;

        // 2-3) Export con verificación previa al DELETE.
        try {
            $exporte = $this->generarExporte($tabla, $col, $corte, $tipo);
        } catch (\Throwable $e) {
            $this->registrarFallo($tabla, $tipo, $modo, $adminId, $corte, 'Respaldo previo falló: ' . $e->getMessage());
            return array_merge($base, [
                'exito'   => false,
                'estado'  => 'fallido',
                'mensaje' => 'Fallo el respaldo previo; no se eliminó nada: ' . $e->getMessage(),
            ]);
        }

        if ($exporte['exportados'] === 0) {
            // No hay nada que respaldar ni eliminar: no-op sin archivo ni bitácora.
            return array_merge($base, [
                'exito'   => true,
                'estado'  => 'sin_elegibles',
                'mensaje' => "La tabla '{$tabla}' no tiene registros anteriores al corte {$corte}; nada que exportar ni purgar.",
            ]);
        }

        // 4) Registro inicial de la operación (antes del DELETE).
        try {
            $purgasId = $this->registrarPurgasLog([
                'tipo'                 => $tipo,
                'tabla'                => $tabla,
                'fecha_corte'          => $corte,
                'registros_exportados' => $exporte['exportados'],
                'registros_eliminados' => 0,
                'archivo'              => $exporte['archivo'],
                'tamano_bytes'         => $exporte['tamano'],
                'hash_sha256'          => $exporte['hash'],
                'modo'                 => $modo,
                'admin_id'             => $adminId,
                'estado'               => 'exitoso',
                'notas'                => 'Exporte verificado (relectura gzip + SHA-256) previo a la operación.',
            ]);
        } catch (\Throwable $e) {
            return array_merge($base, [
                'exito'   => false,
                'estado'  => 'fallido',
                'mensaje' => 'No se pudo registrar la operación en purgas_log; no se eliminó nada: ' . $e->getMessage(),
            ]);
        }

        $base['registros_exportados'] = $exporte['exportados'];
        $base['archivo'] = $exporte['archivo'];
        $base['hash_sha256'] = $exporte['hash'];
        $base['tamano_bytes'] = $exporte['tamano'];

        if (!$eliminar) {
            return array_merge($base, [
                'exito'   => true,
                'estado'  => 'exitoso',
                'mensaje' => "Exporte generado: {$exporte['exportados']} registro(s) de '{$tabla}' en {$exporte['archivo']}.",
            ]);
        }

        // 5) DELETE con el mismo corte.
        try {
            $stmt = $this->db->prepare("DELETE FROM `{$tabla}` WHERE `{$col}` < :corte");
            $stmt->execute(['corte' => $corte]);
            $eliminados = $stmt->rowCount();
        } catch (\Throwable $e) {
            $this->actualizarPurgasLog($purgasId, [
                'estado' => 'fallido',
                'notas'  => 'Fallo el DELETE: ' . $e->getMessage() . ". Exporte disponible: {$exporte['archivo']}.",
            ]);
            return array_merge($base, [
                'exito'   => false,
                'estado'  => 'fallido',
                'mensaje' => 'El respaldo existe pero el DELETE falló: ' . $e->getMessage(),
            ]);
        }

        // 6) Contraste de conteos: exportado vs eliminado.
        $estado = ($exporte['exportados'] === $eliminados) ? 'exitoso' : 'parcial';
        $notas = ($estado === 'exitoso')
            ? "Purga completa: {$eliminados} registro(s) eliminado(s) tras exporte verificado."
            : "Contraste con diferencia: exportados {$exporte['exportados']}, eliminados {$eliminados}.";

        $this->actualizarPurgasLog($purgasId, [
            'estado'               => $estado,
            'registros_eliminados' => $eliminados,
            'notas'                => $notas,
        ]);

        $base['registros_eliminados'] = $eliminados;

        return array_merge($base, [
            'exito'   => true,
            'estado'  => $estado,
            'mensaje' => "{$eliminados} registro(s) de '{$tabla}' eliminado(s) tras respaldar {$exporte['exportados']} en {$exporte['archivo']}.",
        ]);
    }

    /**
     * Genera el export .sql.gz de los registros anteriores al corte y lo
     * verifica (existencia, tamaño y relectura gzip idéntica al SQL fuente).
     * Ante fallo limpia archivos parciales y lanza excepción.
     *
     * @return array{archivo:string,ruta:string,hash:string,tamano:int,exportados:int}
     */
    private function generarExporte(string $tabla, string $col, string $corte, string $tipo): array {
        $this->prepararDirectorio();

        // Lectura por lotes ordenada por id: memoria acotada y conteo exacto.
        $sqlFilas = '';
        $exportados = 0;
        $ultimoId = 0;
        $lote = 500;
        do {
            $stmt = $this->db->prepare("
                SELECT * FROM `{$tabla}`
                WHERE `{$col}` < :corte AND `id` > :ultimo
                ORDER BY `id` ASC
                LIMIT {$lote}
            ");
            $stmt->execute(['corte' => $corte, 'ultimo' => $ultimoId]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($filas)) {
                $sqlFilas .= self::serializarFilas($tabla, $filas);
                $exportados += count($filas);
                $ultimoId = (int)$filas[count($filas) - 1]['id'];
            }
        } while (count($filas) === $lote);

        $sql = $this->cabeceraExporte($tabla, $corte, $exportados, $tipo)
            . $sqlFilas
            . "\nSET FOREIGN_KEY_CHECKS=1;\n";

        $nombre = sprintf('%s_%s_%s.sql.gz', $tabla, $tipo, date('Y-m-d_H-i-s'));
        $ruta = $this->dirExportes . '/' . $nombre;
        $rutaSha = $ruta . '.sha256';

        try {
            $bytes = file_put_contents($ruta, gzencode($sql, 9));
            if ($bytes === false) {
                throw new \RuntimeException('No se pudo escribir el archivo de exporte.');
            }

            clearstatcache(true, $ruta);
            if (!is_file($ruta) || filesize($ruta) <= 0) {
                throw new \RuntimeException('El archivo de exporte quedó vacío o no existe.');
            }

            // Verificación fuerte: el contenido descomprimido debe ser idéntico al SQL generado.
            $decodificado = gzdecode((string)file_get_contents($ruta));
            if ($decodificado === false || hash('sha256', $decodificado) !== hash('sha256', $sql)) {
                throw new \RuntimeException('La verificación de relectura gzip del exporte falló.');
            }

            $hash = (string)hash_file('sha256', $ruta);
            if (file_put_contents($rutaSha, $hash . '  ' . $nombre . "\n") === false) {
                throw new \RuntimeException('No se pudo escribir la firma SHA-256 del exporte.');
            }

            return [
                'archivo'    => $nombre,
                'ruta'       => $ruta,
                'hash'       => $hash,
                'tamano'     => (int)filesize($ruta),
                'exportados' => $exportados,
            ];
        } catch (\Throwable $e) {
            @unlink($ruta);
            @unlink($rutaSha);
            throw $e;
        }
    }

    /** Cabecera informativa del exporte (mismo espíritu que backup_database.php). */
    private function cabeceraExporte(string $tabla, string $corte, int $total, string $tipo): string {
        return "-- ========================================================\n"
            . "-- EXPORTE DE RETENCIÓN ({$tipo}) - CONDOMINIO DIGITAL\n"
            . "-- Tabla: {$tabla}\n"
            . "-- Corte de retención: {$corte} (solo registros anteriores al corte)\n"
            . "-- Registros exportados: {$total}\n"
            . "-- Fecha: " . date('Y-m-d H:i:s') . "\n"
            . "-- ========================================================\n\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    /** Crea el directorio de exportes y su .htaccess de bloqueo web (patrón de storage/backups/). */
    private function prepararDirectorio(): void {
        if (!is_dir($this->dirExportes)) {
            if (!@mkdir($this->dirExportes, 0755, true) && !is_dir($this->dirExportes)) {
                throw new \RuntimeException('No se pudo crear el directorio de exportes: ' . $this->dirExportes);
            }
        }
        $htaccess = $this->dirExportes . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents(
                $htaccess,
                "# Bloqueo total de acceso HTTP directo a los exportes de retención\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\nOptions -Indexes\n"
            );
        }
    }

    // =====================================================================
    // HELPERS INTERNOS
    // =====================================================================

    /** Corte real de la tabla usando el reloj de la base de datos (una sola fuente de tiempo). */
    private function fechaCorte(string $tabla): string {
        $cfg = self::configuracion($tabla);
        $ahora = (string)$this->db->query('SELECT NOW()')->fetchColumn();
        return self::calcularCorte($ahora, (int)$cfg['dias']);
    }

    /** Valida (whitelist) y retorna el nombre de tabla. */
    private function validarNombre(string $tabla): string {
        self::configuracion($tabla);
        return $tabla;
    }

    /** Chequeo tolerante de existencia de tabla vía INFORMATION_SCHEMA. */
    public function tablaExiste(string $tabla): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
        ");
        $stmt->execute(['t' => $tabla]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Chequeo con cache de la tabla de bitácora. */
    private function verificarPurgasLog(): bool {
        if ($this->purgasLogExiste === null) {
            $this->purgasLogExiste = $this->tablaExiste('purgas_log');
        }
        return $this->purgasLogExiste;
    }

    /** Inserta una fila en purgas_log; retorna el id insertado. */
    private function registrarPurgasLog(array $datos): int {
        $stmt = $this->db->prepare("
            INSERT INTO purgas_log
                (tipo, tabla, fecha_corte, registros_exportados, registros_eliminados,
                 archivo, tamano_bytes, hash_sha256, modo, admin_id, estado, notas, fecha_ejecucion)
            VALUES
                (:tipo, :tabla, :fecha_corte, :registros_exportados, :registros_eliminados,
                 :archivo, :tamano_bytes, :hash_sha256, :modo, :admin_id, :estado, :notas, NOW())
        ");
        $stmt->execute([
            'tipo'                 => $datos['tipo'],
            'tabla'                => $datos['tabla'],
            'fecha_corte'          => $datos['fecha_corte'],
            'registros_exportados' => $datos['registros_exportados'],
            'registros_eliminados' => $datos['registros_eliminados'],
            'archivo'              => $datos['archivo'],
            'tamano_bytes'         => $datos['tamano_bytes'],
            'hash_sha256'          => $datos['hash_sha256'],
            'modo'                 => $datos['modo'],
            'admin_id'             => $datos['admin_id'],
            'estado'               => $datos['estado'],
            'notas'                => $datos['notas'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Actualiza una fila existente de purgas_log (columnas acotadas). */
    private function actualizarPurgasLog(int $id, array $campos): void {
        $permitidas = ['estado', 'registros_eliminados', 'notas'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($campos as $campo => $valor) {
            if (in_array($campo, $permitidas, true)) {
                $sets[] = "`{$campo}` = :{$campo}";
                $params[$campo] = $valor;
            }
        }
        if (empty($sets)) {
            return;
        }
        $stmt = $this->db->prepare("UPDATE purgas_log SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    /** Anota (append) en purgas_log la eliminación del archivo del ciclo único. */
    private function anotarPurgasLog(int $id, string $notasActuales, string $nota): void {
        $this->actualizarPurgasLog($id, ['notas' => trim($notasActuales . ' ' . $nota)]);
    }

    /** Anota (append) en backups_log la eliminación del archivo del ciclo único. */
    private function anotarBackupsLog(int $id, string $notasActuales, string $nota): void {
        $stmt = $this->db->prepare("UPDATE backups_log SET notas = :notas WHERE id = :id");
        $stmt->execute(['notas' => trim($notasActuales . ' ' . $nota), 'id' => $id]);
    }

    /**
     * Registro best-effort de un fallo previo al DELETE. Si la bitácora no
     * existe o falla el insert, el error original sigue siendo el que manda.
     */
    private function registrarFallo(string $tabla, string $tipo, string $modo, ?int $adminId, ?string $corte, string $mensaje): void {
        if (!$this->verificarPurgasLog()) {
            return;
        }
        try {
            $this->registrarPurgasLog([
                'tipo'                 => $tipo,
                'tabla'                => $tabla,
                'fecha_corte'          => $corte,
                'registros_exportados' => 0,
                'registros_eliminados' => 0,
                'archivo'              => null,
                'tamano_bytes'         => 0,
                'hash_sha256'          => null,
                'modo'                 => $modo,
                'admin_id'             => $adminId,
                'estado'               => 'fallido',
                'notas'                => mb_substr($mensaje, 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Silencioso a propósito: no puede tapar el error original.
        }
    }
}
