<?php
/**
 * Migración Idempotente: Adjuntos de la cola de notificaciones
 * Agrega `notificaciones_cola.adjuntos` (MEDIUMTEXT, JSON cifrado en base64).
 * Ejecución: php scripts/migrate_notificaciones_adjuntos.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Adjuntos en notificaciones_cola ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Agregar la columna `adjuntos` solo si no existe (idempotente).
    $stmt = $db->query("SHOW COLUMNS FROM `notificaciones_cola` LIKE 'adjuntos'");
    if ($stmt->fetch()) {
        echo "  ✔ La columna 'adjuntos' ya existe. Nada que hacer." . PHP_EOL;
    } else {
        $db->exec("ALTER TABLE `notificaciones_cola` ADD COLUMN `adjuntos` MEDIUMTEXT NULL AFTER `cuerpo_html`");
        echo "  ✔ Columna 'adjuntos' agregada a 'notificaciones_cola'." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración de adjuntos completada con éxito." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
