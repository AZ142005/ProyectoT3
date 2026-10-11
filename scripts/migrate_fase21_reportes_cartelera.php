<?php
/**
 * Migración Fase 21 — Historial de reportes de cartelera.
 *
 * OBJETIVO
 * - `reportes_cartelera`: registra cada carga (importación) del Reporte de
 *   Cartelera: archivo almacenado, nombre original, hash, fecha de emisión,
 *   fecha de carga y resultado de la importación. Alimenta el "Historial de
 *   Reportes" de /admin/gastos (solo se listan las cargas del último mes).
 * - Backfill: reconstruye UNA fila por importación histórica registrada en
 *   `log_auditoria` (accion='importar_cartelera'), agrupada por archivo y día.
 *
 * ROLLBACK MANUAL
 *   DROP TABLE reportes_cartelera;
 *
 * IDEMPOTENTE: verifica con information_schema antes del CREATE y solo ejecuta
 * el backfill si la tabla está vacía; puede re-ejecutarse sin efectos
 * secundarios.
 *
 * Uso: php scripts/migrate_fase21_reportes_cartelera.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración Fase 21: Historial de reportes de cartelera ===" . PHP_EOL . PHP_EOL;

function tablaExiste(PDO $db, string $tabla): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla
    ");
    $stmt->execute(['tabla' => $tabla]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $db = Database::getConnection();

    echo "1. Tabla 'reportes_cartelera'..." . PHP_EOL;
    if (tablaExiste($db, 'reportes_cartelera')) {
        echo "   ℹ Tabla 'reportes_cartelera' ya existe. No se aplicaron cambios." . PHP_EOL;
    } else {
        $db->exec("
            CREATE TABLE reportes_cartelera (
                id int(11) NOT NULL AUTO_INCREMENT,
                archivo varchar(255) NOT NULL,
                nombre_original varchar(255) DEFAULT NULL,
                hash varchar(64) DEFAULT NULL,
                fecha_emision date DEFAULT NULL,
                fecha_carga datetime NOT NULL DEFAULT current_timestamp(),
                facturas_creadas int(11) NOT NULL DEFAULT 0,
                facturas_actualizadas int(11) NOT NULL DEFAULT 0,
                facturas_omitidas int(11) NOT NULL DEFAULT 0,
                edificios_creados int(11) NOT NULL DEFAULT 0,
                unidades_creadas int(11) NOT NULL DEFAULT 0,
                usuario_id int(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_reportes_cartelera_hash (hash),
                KEY idx_reportes_cartelera_fecha_carga (fecha_carga)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        echo "   ✔ Tabla creada." . PHP_EOL;
    }

    echo "2. Backfill de importaciones históricas (log_auditoria)..." . PHP_EOL;
    $total = (int)$db->query("SELECT COUNT(*) FROM reportes_cartelera")->fetchColumn();
    if ($total > 0) {
        echo "   ℹ Ya hay {$total} fila(s) en el historial. Backfill omitido." . PHP_EOL;
    } else {
        $stmt = $db->prepare("
            INSERT INTO reportes_cartelera
                (archivo, nombre_original, hash, fecha_emision, fecha_carga, facturas_creadas, facturas_actualizadas, facturas_omitidas, edificios_creados, unidades_creadas, usuario_id)
            SELECT
                TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(detalles, ' | ', 1), ': ', -1)) AS archivo,
                NULL, NULL, NULL, MIN(created_at), COUNT(*), 0, 0, 0, 0, MAX(admin_id)
            FROM log_auditoria
            WHERE accion = 'importar_cartelera'
            GROUP BY TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(detalles, ' | ', 1), ': ', -1)), DATE(created_at)
        ");
        $stmt->execute();
        echo "   ✔ {$stmt->rowCount()} fila(s) reconstruida(s)." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración completada." . PHP_EOL;
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
