<?php
/**
 * Migración: esquema de retención de datos acumulados.
 *
 * Crea la tabla 'purgas_log' (bitácora de exportes y purgas con respaldo
 * previo verificado SHA-256) y agrega el índice 'idx_hist_creado' a
 * 'historial_estado_abono' (hoy un rango por 'creado_en' haría full scan).
 *
 * Idempotente: puede ejecutarse varias veces sin efectos secundarios.
 * No toca datos existentes.
 *
 * Ejecutable vía CLI: php scripts/migrate_retencion_datos_schema.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: esquema de retención de datos (purgas_log + idx_hist_creado) ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Tabla 'purgas_log': exportaciones y purgas de las tablas con retención.
    $existePurgas = $db->query("SHOW TABLES LIKE 'purgas_log'")->fetch();
    if (!$existePurgas) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS purgas_log (
                id                   INT AUTO_INCREMENT PRIMARY KEY,
                tipo                 ENUM('exportacion','purga') NOT NULL DEFAULT 'purga',
                tabla                VARCHAR(64) NOT NULL,
                fecha_corte          DATETIME NULL COMMENT 'Corte usado en el SELECT y el DELETE (NOW - días de retención)',
                registros_exportados INT UNSIGNED NOT NULL DEFAULT 0,
                registros_eliminados INT UNSIGNED NOT NULL DEFAULT 0,
                archivo              VARCHAR(255) NULL COMMENT 'Nombre del .sql.gz generado en storage/backups/purgas/',
                tamano_bytes         BIGINT UNSIGNED NOT NULL DEFAULT 0,
                hash_sha256          VARCHAR(64) NULL COMMENT 'SHA-256 del archivo .sql.gz',
                modo                 ENUM('manual','programado') NOT NULL DEFAULT 'manual',
                admin_id             INT NULL,
                estado               ENUM('exitoso','parcial','fallido') NOT NULL DEFAULT 'exitoso',
                notas                TEXT NULL,
                fecha_ejecucion      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_purgas_fecha (fecha_ejecucion),
                INDEX idx_purgas_tabla (tabla, fecha_ejecucion),
                FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "  ✔ Tabla 'purgas_log' creada." . PHP_EOL;
    } else {
        echo "  ℹ La tabla 'purgas_log' ya existe." . PHP_EOL;
    }

    // 2. Índice de rango para la retención de 'historial_estado_abono'.
    $existeHistorial = $db->query("SHOW TABLES LIKE 'historial_estado_abono'")->fetch();
    if (!$existeHistorial) {
        echo "  ⚠ La tabla 'historial_estado_abono' no existe — índice omitido." . PHP_EOL;
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'historial_estado_abono'
              AND INDEX_NAME = 'idx_hist_creado'
        ");
        $stmt->execute();

        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("ALTER TABLE historial_estado_abono ADD INDEX idx_hist_creado (creado_en)");
            echo "  ✔ Índice 'idx_hist_creado' agregado a 'historial_estado_abono'." . PHP_EOL;
        } else {
            echo "  ℹ El índice 'idx_hist_creado' ya existe." . PHP_EOL;
        }
    }

    echo PHP_EOL . "✅ Migración finalizada con éxito." . PHP_EOL;
    exit(0);
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
