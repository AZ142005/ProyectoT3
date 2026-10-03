<?php
/**
 * Migración idempotente: Protección contra pagos duplicados en `comprobantes_pago`,
 * índices para consultas de referencia en `pagos` y almacenamiento de hash de archivo SHA-256.
 *
 * Acciones:
 *  1. Agregar `referencia_norm` y `archivo_hash` a `comprobantes_pago`.
 *  2. Backfill de `referencia_norm` y `archivo_hash` para comprobantes existentes.
 *  3. Columna generada `dup_guard` e índice único `uk_comprobante_dup_guard` en `comprobantes_pago`.
 *  4. Índices `idx_comprobantes_referencia_norm` e `idx_comprobantes_archivo_hash`.
 *  5. Agregar `archivo_hash` a `pagos`.
 *  6. Backfill de `archivo_hash` en `pagos`.
 *  7. Índices `idx_pagos_referencia_norm` e `idx_pagos_archivo_hash` en `pagos`.
 *
 * Uso: php scripts/migrate_comprobantes_duplicados.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;
use App\Models\PagoModel;

echo "=== Migración: Protección de Duplicados en Comprobantes y Optimización de Índices ===" . PHP_EOL . PHP_EOL;

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

try {
    $db = Database::getConnection();

    // -------------------------------------------------------------------------
    // 1. Columnas en comprobantes_pago
    // -------------------------------------------------------------------------
    echo "1. Verificando columnas en 'comprobantes_pago'..." . PHP_EOL;

    if (!columnaExiste($db, 'comprobantes_pago', 'referencia_norm')) {
        $db->exec("ALTER TABLE comprobantes_pago ADD COLUMN referencia_norm VARCHAR(100) NULL AFTER referencia");
        echo "   ✔ Columna 'referencia_norm' agregada a 'comprobantes_pago'." . PHP_EOL;
    } else {
        echo "   ℹ Columna 'referencia_norm' ya existe en 'comprobantes_pago'." . PHP_EOL;
    }

    if (!columnaExiste($db, 'comprobantes_pago', 'archivo_hash')) {
        $db->exec("ALTER TABLE comprobantes_pago ADD COLUMN archivo_hash CHAR(64) NULL AFTER archivo");
        echo "   ✔ Columna 'archivo_hash' agregada a 'comprobantes_pago'." . PHP_EOL;
    } else {
        echo "   ℹ Columna 'archivo_hash' ya existe en 'comprobantes_pago'." . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // 2. Backfill en comprobantes_pago
    // -------------------------------------------------------------------------
    echo PHP_EOL . "2. Backfill de datos en 'comprobantes_pago'..." . PHP_EOL;
    $stmtComprobantes = $db->query("SELECT id, referencia, archivo FROM comprobantes_pago");
    $comprobantes = $stmtComprobantes->fetchAll(PDO::FETCH_ASSOC);

    $stmtUpdComp = $db->prepare("
        UPDATE comprobantes_pago 
        SET referencia_norm = :ref_norm, archivo_hash = :hash 
        WHERE id = :id
    ");

    $uploadsDir = defined('UPLOADS_PATH') ? UPLOADS_PATH . '/comprobantes' : __DIR__ . '/../public/uploads/comprobantes';
    $backfilledComp = 0;

    foreach ($comprobantes as $c) {
        $refNorm = PagoModel::normalizarReferenciaPago($c['referencia']);
        $fileHash = null;
        if (!empty($c['archivo'])) {
            $filePath = $uploadsDir . '/' . $c['archivo'];
            if (file_exists($filePath) && is_file($filePath)) {
                $fileHash = hash_file('sha256', $filePath) ?: null;
            }
        }
        $stmtUpdComp->execute([
            'ref_norm' => $refNorm,
            'hash'     => $fileHash,
            'id'       => $c['id']
        ]);
        $backfilledComp++;
    }
    echo "   ✔ Backfill aplicado a {$backfilledComp} comprobante(s)." . PHP_EOL;

    // -------------------------------------------------------------------------
    // 3. Columna generada dup_guard e índice único en comprobantes_pago
    // -------------------------------------------------------------------------
    echo PHP_EOL . "3. Guardia única 'comprobantes_pago.dup_guard'..." . PHP_EOL;
    $exprDupComp = "IF(`estado` = 'rechazado' OR (`deleted_at` IS NOT NULL AND `estado` <> 'aprobado'), NULL, IF(`referencia_norm` IS NULL OR `referencia_norm` = '', CONCAT('S|', `factura_id`, '|', `fecha_pago`, '|', `monto`), CONCAT('R|', `factura_id`, '|', `referencia_norm`)))";

    if (!columnaExiste($db, 'comprobantes_pago', 'dup_guard')) {
        $db->exec("ALTER TABLE comprobantes_pago ADD COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS ({$exprDupComp}) STORED");
        echo "   ✔ Columna generada 'dup_guard' agregada a 'comprobantes_pago'." . PHP_EOL;
    } else {
        $db->exec("ALTER TABLE comprobantes_pago MODIFY COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS ({$exprDupComp}) STORED");
        echo "   ℹ Columna generada 'dup_guard' actualizada en 'comprobantes_pago'." . PHP_EOL;
    }

    if (!indiceExiste($db, 'comprobantes_pago', 'uk_comprobante_dup_guard')) {
        $db->exec("ALTER TABLE comprobantes_pago ADD UNIQUE KEY uk_comprobante_dup_guard (dup_guard)");
        echo "   ✔ Índice único 'uk_comprobante_dup_guard' creado." . PHP_EOL;
    } else {
        echo "   ℹ Índice único 'uk_comprobante_dup_guard' ya existe." . PHP_EOL;
    }

    if (!indiceExiste($db, 'comprobantes_pago', 'idx_comprobantes_referencia_norm')) {
        $db->exec("ALTER TABLE comprobantes_pago ADD INDEX idx_comprobantes_referencia_norm (referencia_norm)");
        echo "   ✔ Índice 'idx_comprobantes_referencia_norm' creado." . PHP_EOL;
    } else {
        echo "   ℹ Índice 'idx_comprobantes_referencia_norm' ya existe." . PHP_EOL;
    }

    if (!indiceExiste($db, 'comprobantes_pago', 'idx_comprobantes_archivo_hash')) {
        $db->exec("ALTER TABLE comprobantes_pago ADD INDEX idx_comprobantes_archivo_hash (archivo_hash)");
        echo "   ✔ Índice 'idx_comprobantes_archivo_hash' creado." . PHP_EOL;
    } else {
        echo "   ℹ Índice 'idx_comprobantes_archivo_hash' ya existe." . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // 4. Columnas e índices en pagos
    // -------------------------------------------------------------------------
    echo PHP_EOL . "4. Actualizando tabla 'pagos' con archivo_hash e índices..." . PHP_EOL;

    if (!columnaExiste($db, 'pagos', 'archivo_hash')) {
        $db->exec("ALTER TABLE pagos ADD COLUMN archivo_hash CHAR(64) NULL AFTER archivo");
        echo "   ✔ Columna 'archivo_hash' agregada a 'pagos'." . PHP_EOL;
    } else {
        echo "   ℹ Columna 'archivo_hash' ya existe en 'pagos'." . PHP_EOL;
    }

    // Backfill en pagos
    $stmtPagos = $db->query("SELECT id, archivo FROM pagos WHERE archivo_hash IS NULL AND archivo IS NOT NULL");
    $pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
    $stmtUpdPago = $db->prepare("UPDATE pagos SET archivo_hash = :hash WHERE id = :id");
    $backfilledPagos = 0;

    foreach ($pagos as $p) {
        $filePath = $uploadsDir . '/' . $p['archivo'];
        if (file_exists($filePath) && is_file($filePath)) {
            $fileHash = hash_file('sha256', $filePath) ?: null;
            if ($fileHash) {
                $stmtUpdPago->execute(['hash' => $fileHash, 'id' => $p['id']]);
                $backfilledPagos++;
            }
        }
    }
    echo "   ✔ Backfill de 'archivo_hash' aplicado a {$backfilledPagos} fila(s) de 'pagos'." . PHP_EOL;

    // Índice en pagos.referencia_norm (resuelve escaneo type=ALL)
    if (!indiceExiste($db, 'pagos', 'idx_pagos_referencia_norm')) {
        $db->exec("ALTER TABLE pagos ADD INDEX idx_pagos_referencia_norm (referencia_norm)");
        echo "   ✔ Índice 'idx_pagos_referencia_norm' creado exitosamente." . PHP_EOL;
    } else {
        echo "   ℹ Índice 'idx_pagos_referencia_norm' ya existe." . PHP_EOL;
    }

    // Índice en pagos.archivo_hash
    if (!indiceExiste($db, 'pagos', 'idx_pagos_archivo_hash')) {
        $db->exec("ALTER TABLE pagos ADD INDEX idx_pagos_archivo_hash (archivo_hash)");
        echo "   ✔ Índice 'idx_pagos_archivo_hash' creado exitosamente." . PHP_EOL;
    } else {
        echo "   ℹ Índice 'idx_pagos_archivo_hash' ya existe." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
