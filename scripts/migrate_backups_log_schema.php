<?php
/**
 * Migración: Alinear el esquema de la tabla 'backups_log' con el código.
 *
 * Contexto: la tabla en bases de datos existentes quedó con el esquema viejo
 * (archivo, tamano, checksum, admin_id, created_at), mientras que el script CLI
 * scripts/backup_database.php, RespaldoController y la vista admin/respaldos/index.php
 * esperan el esquema definido en scripts/migrate_fase4.php.
 *
 * Comportamiento:
 *  - Si 'backups_log' ya tiene la columna 'nombre_archivo' → no hace nada.
 *  - Si no existe la tabla → la crea con el esquema objetivo.
 *  - Si existe con esquema viejo: con 0 filas la recrea; con filas > 0 aborta
 *    (no se migran datos a ciegas).
 *
 * Ejecutable vía CLI: php scripts/migrate_backups_log_schema.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Alinear esquema de 'backups_log' con el código ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // Esquema objetivo (copiado de scripts/migrate_fase4.php, líneas 127-145)
    $esquemaObjetivo = "
        CREATE TABLE IF NOT EXISTS backups_log (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            nombre_archivo     VARCHAR(255) NOT NULL,
            tamano_bytes       BIGINT UNSIGNED NOT NULL,
            hash_sha256        VARCHAR(64) NOT NULL COMMENT 'SHA-256 del archivo .sql.gz',
            checksum_sha256    VARCHAR(64) NULL COMMENT 'Alias de hash_sha256 para compatibilidad Fase 6',
            tablas_respaldadas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            motor              ENUM('mysqldump','pdo_fallback') NOT NULL DEFAULT 'mysqldump'
                               COMMENT 'Motor utilizado para generar el respaldo',
            estado             ENUM('exitoso','fallido','parcial') NOT NULL DEFAULT 'exitoso',
            notas              TEXT NULL,
            admin_id           INT NULL,
            fecha_respaldo     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_backups_fecha (fecha_respaldo),
            INDEX idx_backups_estado (estado, fecha_respaldo),
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    $existe = $db->query("SHOW TABLES LIKE 'backups_log'")->fetch();

    if (!$existe) {
        echo "  ℹ La tabla 'backups_log' no existe." . PHP_EOL;
        $db->exec($esquemaObjetivo);
        echo "  ✔ Tabla 'backups_log' creada con el esquema objetivo." . PHP_EOL;
        echo PHP_EOL . "✅ Migración finalizada con éxito." . PHP_EOL;
        exit(0);
    }

    $colAlineada = $db->query("SHOW COLUMNS FROM backups_log LIKE 'nombre_archivo'")->fetch();
    if ($colAlineada) {
        echo "  ℹ La tabla 'backups_log' ya está alineada con el esquema objetivo." . PHP_EOL;
        echo "  ✔ Nada que hacer (migración idempotente)." . PHP_EOL;
        echo PHP_EOL . "✅ Migración finalizada con éxito." . PHP_EOL;
        exit(0);
    }

    // Esquema viejo: decidir según filas existentes
    $totalFilas = (int)$db->query("SELECT COUNT(*) FROM backups_log")->fetchColumn();

    if ($totalFilas > 0) {
        echo "  ❌ ABORTADO: 'backups_log' conserva el esquema viejo (columna 'archivo') y contiene {$totalFilas} fila(s)." . PHP_EOL;
        echo "     No se migran datos a ciegas. Revise/exporte esas filas manualmente y vuelva a ejecutar esta migración." . PHP_EOL;
        exit(1);
    }

    echo "  ℹ Esquema viejo detectado (archivo, tamano, checksum, admin_id, created_at) con 0 filas." . PHP_EOL;
    $db->exec("DROP TABLE backups_log");
    echo "  ✔ Tabla vieja 'backups_log' eliminada (sin datos que preservar)." . PHP_EOL;

    $db->exec($esquemaObjetivo);
    echo "  ✔ Tabla 'backups_log' recreada con el esquema objetivo." . PHP_EOL;

    echo PHP_EOL . "✅ Migración finalizada con éxito." . PHP_EOL;
    exit(0);
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
