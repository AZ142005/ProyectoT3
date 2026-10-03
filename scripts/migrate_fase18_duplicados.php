<?php
/**
 * Migración Fase 18 — Endurecimiento anti-duplicados (extractos bancarios y gastos comunes).
 *
 * OBJETIVO
 * 1. `extractos_bancarios`: índice único `uk_extracto_identidad`
 *    (referencia_bancaria, fecha_movimiento, monto) para cerrar la carrera de
 *    importación y garantizar idempotencia del lote.
 * 2. `gastos_comunes`:
 *    - Columna generada `dup_guard` + `uk_gasto_dup_guard` con identidad
 *      UNIFICADA: F (con N° de factura: período + factura + proveedor) y
 *      S (sin factura: período + categoría + monto + fecha_gasto + proveedor +
 *      descripción + tipo + edificio). Las filas borradas lógicamente liberan la
 *      identidad (NULL), por lo que re-registrar un gasto tras un soft-delete
 *      no queda bloqueado.
 *    - Se ELIMINA el índice plano `uk_gasto_factura_periodo` si existe:
 *      declarado en `migrate_fase3.php`, es deliberadamente reemplazado por la
 *      guardia consciente de borrado porque el índice plano retenía la
 *      identidad de filas soft-deleted y bloqueaba el re-registro (contradiciendo
 *      los pre-chequeos de la app, que filtran `deleted_at IS NULL`). La
 *      unicidad de filas activas con factura queda garantizada por `dup_guard`.
 *    - `soporte_hash CHAR(64) NULL` + `idx_gastos_soporte_hash` para detectar la
 *      re-ingesta del mismo PDF Maestro por período.
 *
 * ANÁLISIS PREVIO OBLIGATORIO
 * - Cada bloque analiza duplicados ANTES de crear su índice y ABORTA (exit 1)
 *   con un reporte de grupos si encuentra conflictos. Sin conflictos, aplica.
 * - El análisis de gastos usa la expresión unificada sobre `deleted_at IS NULL`
 *   (cubre duplicados activos F y S); las filas borradas no participan.
 * - NOTA: ejecutar en ventana de mantenimiento si el servidor recibe escrituras;
 *   una fila insertada entre el análisis y el ALTER puede hacer fallar el índice.
 *
 * ROLLBACK MANUAL
 *   ALTER TABLE extractos_bancarios DROP INDEX uk_extracto_identidad;
 *   ALTER TABLE gastos_comunes DROP INDEX uk_gasto_dup_guard;
 *   ALTER TABLE gastos_comunes DROP COLUMN dup_guard;
 *   ALTER TABLE gastos_comunes DROP INDEX idx_gastos_soporte_hash;
 *   ALTER TABLE gastos_comunes DROP COLUMN soporte_hash;
 *   -- El índice plano `uk_gasto_factura_periodo` NO se recrea en el rollback:
 *   -- fue reemplazado a propósito por la guardia consciente de soft-delete.
 *
 * IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios (information_schema
 * antes de cada ALTER; columnas generadas se actualizan con MODIFY si ya existen).
 *
 * Uso: php scripts/migrate_fase18_duplicados.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración Fase 18: Endurecimiento anti-duplicados (extractos + gastos) ===" . PHP_EOL . PHP_EOL;

function columnaExiste(PDO $db, string $tabla, string $columna): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna
    ");
    $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

function indiceExiste(PDO $db, string $tabla, string $indice): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND INDEX_NAME = :indice
    ");
    $stmt->execute(['tabla' => $tabla, 'indice' => $indice]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Ejecuta una consulta de análisis de grupos duplicados y retorna las filas.
 */
function analizarGrupos(PDO $db, string $sql): array {
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Aborta la migración con reporte de grupos duplicados.
 */
function abortarPorDuplicados(string $contexto, array $grupos, string $detalle): void {
    echo "   ❌ CONFLICTO: se encontraron " . count($grupos) . " grupo(s) duplicado(s) para {$contexto}." . PHP_EOL;
    echo "      Indexación imposible sin resolverlos manualmente:" . PHP_EOL;
    foreach ($grupos as $g) {
        echo "      - " . $detalle . " => " . json_encode($g, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    echo PHP_EOL . "❌ MIGRACIÓN ABORTADA. No se creó el índice conflictivo. Resuelva los duplicados y re-ejecute." . PHP_EOL;
    exit(1);
}

$exprDupGasto = "IF(`deleted_at` IS NOT NULL, NULL, IF(`nro_factura_proveedor` IS NULL OR `nro_factura_proveedor` = '', CONCAT('S|', IFNULL(`mes`,0), '|', IFNULL(`anio`,0), '|', IFNULL(`categoria_id`,0), '|', IFNULL(`monto_total`,0), '|', IFNULL(`fecha_gasto`,'0000-00-00'), '|', LEFT(IFNULL(`proveedor`,''),45), '|', LEFT(`descripcion`,60), '|', `tipo_gasto`, '|', IFNULL(`edificio_id`,0)), CONCAT('F|', IFNULL(`mes`,0), '|', IFNULL(`anio`,0), '|', LEFT(`nro_factura_proveedor`,50), '|', LEFT(IFNULL(`proveedor`,''),45))))";

try {
    $db = Database::getConnection();

    // -------------------------------------------------------------------------
    // 1. Extractos bancarios: identidad única (ref, fecha, monto)
    // -------------------------------------------------------------------------
    echo "1. 'extractos_bancarios': índice único uk_extracto_identidad..." . PHP_EOL;

    if (indiceExiste($db, 'extractos_bancarios', 'uk_extracto_identidad')) {
        echo "   ℹ Índice único 'uk_extracto_identidad' ya existe." . PHP_EOL;
    } else {
        $grupos = analizarGrupos($db, "
            SELECT referencia_bancaria, fecha_movimiento, monto, COUNT(*) AS total
            FROM extractos_bancarios
            GROUP BY referencia_bancaria, fecha_movimiento, monto
            HAVING COUNT(*) > 1
            ORDER BY total DESC
            LIMIT 10
        ");
        if (!empty($grupos)) {
            abortarPorDuplicados('extractos_bancarios (referencia_bancaria, fecha_movimiento, monto)', $grupos, 'referencia/fecha/monto');
        }
        $db->exec("ALTER TABLE extractos_bancarios ADD UNIQUE KEY uk_extracto_identidad (referencia_bancaria, fecha_movimiento, monto)");
        echo "   ✔ Índice único 'uk_extracto_identidad' creado." . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // 2. Gastos: guardia única unificada dup_guard (identidades F y S)
    // -------------------------------------------------------------------------
    echo PHP_EOL . "2. 'gastos_comunes': guardia única unificada 'dup_guard' (F/S)..." . PHP_EOL;

    // Análisis previo con la NUEVA expresión unificada, solo filas activas
    $grupos = analizarGrupos($db, "
        SELECT {$exprDupGasto} AS dup_guard, COUNT(*) AS total
        FROM gastos_comunes
        WHERE deleted_at IS NULL
        GROUP BY dup_guard
        HAVING COUNT(*) > 1
        ORDER BY total DESC
        LIMIT 10
    ");
    if (!empty($grupos)) {
        abortarPorDuplicados('gastos_comunes (guarda unificada F/S)', $grupos, 'dup_guard');
    }

    if (!columnaExiste($db, 'gastos_comunes', 'dup_guard')) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS ({$exprDupGasto}) STORED");
        echo "   ✔ Columna generada 'dup_guard' agregada." . PHP_EOL;
    } else {
        $db->exec("ALTER TABLE gastos_comunes MODIFY COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS ({$exprDupGasto}) STORED");
        echo "   ℹ Columna generada 'dup_guard' actualizada." . PHP_EOL;
    }

    if (!indiceExiste($db, 'gastos_comunes', 'uk_gasto_dup_guard')) {
        $db->exec("ALTER TABLE gastos_comunes ADD UNIQUE KEY uk_gasto_dup_guard (dup_guard)");
        echo "   ✔ Índice único 'uk_gasto_dup_guard' creado." . PHP_EOL;
    } else {
        echo "   ℹ Índice único 'uk_gasto_dup_guard' ya existe." . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // 3. Retirar el índice plano uk_gasto_factura_periodo (bloqueaba el
    //    re-registro tras soft-delete; la guardia unificada ya cubre las
    //    filas activas con factura)
    // -------------------------------------------------------------------------
    echo PHP_EOL . "3. 'gastos_comunes': retirando índice plano uk_gasto_factura_periodo..." . PHP_EOL;

    if (indiceExiste($db, 'gastos_comunes', 'uk_gasto_factura_periodo')) {
        $db->exec("ALTER TABLE gastos_comunes DROP INDEX uk_gasto_factura_periodo");
        echo "   ✔ Índice 'uk_gasto_factura_periodo' eliminado (superseded por uk_gasto_dup_guard)." . PHP_EOL;
    } else {
        echo "   ℹ Índice 'uk_gasto_factura_periodo' no existe (correcto)." . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // 4. Gastos: hash del soporte digital (idempotencia de importación PDF Maestro)
    // -------------------------------------------------------------------------
    echo PHP_EOL . "4. 'gastos_comunes': columna soporte_hash + índice..." . PHP_EOL;

    if (!columnaExiste($db, 'gastos_comunes', 'soporte_hash')) {
        $db->exec("ALTER TABLE gastos_comunes ADD COLUMN soporte_hash CHAR(64) NULL AFTER soporte_digital");
        echo "   ✔ Columna 'soporte_hash' agregada." . PHP_EOL;
    } else {
        echo "   ℹ Columna 'soporte_hash' ya existe." . PHP_EOL;
    }

    if (!indiceExiste($db, 'gastos_comunes', 'idx_gastos_soporte_hash')) {
        $db->exec("ALTER TABLE gastos_comunes ADD INDEX idx_gastos_soporte_hash (soporte_hash)");
        echo "   ✔ Índice 'idx_gastos_soporte_hash' creado." . PHP_EOL;
    } else {
        echo "   ℹ Índice 'idx_gastos_soporte_hash' ya existe." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración Fase 18 completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
