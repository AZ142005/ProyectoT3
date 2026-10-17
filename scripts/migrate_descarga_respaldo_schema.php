<?php
/**
 * Migración: columna 'fecha_descarga' para el ciclo único de respaldos.
 *
 * Agrega 'fecha_descarga TIMESTAMP NULL' a 'backups_log' y 'purgas_log':
 * se marca con NOW() al descargar un archivo y es la base de la limpieza
 * (los archivos descargados se eliminan del servidor 7 días después).
 *
 * Idempotente: guard SHOW COLUMNS; puede ejecutarse varias veces sin efectos
 * secundarios. No toca datos existentes.
 *
 * Ejecutable vía CLI: php scripts/migrate_descarga_respaldo_schema.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: fecha_descarga en backups_log y purgas_log (ciclo único) ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    foreach (['backups_log', 'purgas_log'] as $tabla) {
        $existe = $db->query("SHOW TABLES LIKE '{$tabla}'")->fetch();
        if (!$existe) {
            echo "  ⚠ La tabla '{$tabla}' no existe — columna omitida." . PHP_EOL;
            continue;
        }

        $columna = $db->query("SHOW COLUMNS FROM {$tabla} LIKE 'fecha_descarga'")->fetch();
        if ($columna) {
            echo "  ℹ '{$tabla}.fecha_descarga' ya existe." . PHP_EOL;
            continue;
        }

        $db->exec("
            ALTER TABLE {$tabla}
            ADD COLUMN fecha_descarga TIMESTAMP NULL DEFAULT NULL
            COMMENT 'Fecha de descarga del archivo; NULL si nunca se descargó (ciclo único 7/30 días)'
        ");
        echo "  ✔ Columna 'fecha_descarga' agregada a '{$tabla}'." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración finalizada con éxito." . PHP_EOL;
    exit(0);
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
