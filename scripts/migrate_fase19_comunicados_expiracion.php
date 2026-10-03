<?php
/**
 * Migración Fase 19 — Comunicados con expiración.
 *
 * OBJETIVO
 * - `comunicados`: columna `fecha_expiracion DATETIME NULL DEFAULT NULL`.
 *   NULL = sin vencimiento. Los comunicados vencidos se ocultan de la
 *   cartelera del residente (filtro en la lectura) y se auto-eliminan
 *   lógicamente al abrir el módulo de administración (`eliminarExpirados()`).
 *
 * ROLLBACK MANUAL
 *   ALTER TABLE comunicados DROP COLUMN fecha_expiracion;
 *
 * IDEMPOTENTE: verifica con information_schema antes del ALTER; puede
 * re-ejecutarse sin efectos secundarios.
 *
 * Uso: php scripts/migrate_fase19_comunicados_expiracion.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración Fase 19: Comunicados con expiración ===" . PHP_EOL . PHP_EOL;

function columnaExiste(PDO $db, string $tabla, string $columna): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna
    ");
    $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $db = Database::getConnection();

    echo "1. 'comunicados': columna fecha_expiracion..." . PHP_EOL;

    if (columnaExiste($db, 'comunicados', 'fecha_expiracion')) {
        echo "   ℹ Columna 'fecha_expiracion' ya existe. No se aplicaron cambios." . PHP_EOL;
    } else {
        $db->exec("ALTER TABLE comunicados ADD COLUMN fecha_expiracion DATETIME NULL DEFAULT NULL");
        echo "   ✔ Columna 'fecha_expiracion' agregada." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración Fase 19 completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
