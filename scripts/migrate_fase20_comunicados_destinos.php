<?php
/**
 * Migración Fase 20 — Destinos múltiples de comunicados (unidades específicas).
 *
 * OBJETIVO
 * - `comunicados_destinos`: tabla de unión que registra a qué unidades
 *   específicas se dirige un comunicado (puede abarcar unidades de uno o
 *   varios edificios). Complementa los destinos simples existentes
 *   (`comunicados.edificio_id` / `comunicados.unidad_id`), que quedan en NULL
 *   cuando se publica en modo multi-unidad.
 *
 * ROLLBACK MANUAL
 *   DROP TABLE comunicados_destinos;
 *
 * IDEMPOTENTE: verifica con information_schema antes del CREATE; puede
 * re-ejecutarse sin efectos secundarios.
 *
 * Uso: php scripts/migrate_fase20_comunicados_destinos.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración Fase 20: Destinos múltiples de comunicados ===" . PHP_EOL . PHP_EOL;

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

    echo "1. Tabla 'comunicados_destinos'..." . PHP_EOL;

    if (tablaExiste($db, 'comunicados_destinos')) {
        echo "   ℹ Tabla 'comunicados_destinos' ya existe. No se aplicaron cambios." . PHP_EOL;
    } else {
        $db->exec("
            CREATE TABLE comunicados_destinos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                comunicado_id INT NOT NULL,
                unidad_id INT NOT NULL,
                KEY idx_comdest_comunicado (comunicado_id),
                KEY idx_comdest_unidad (unidad_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        echo "   ✔ Tabla 'comunicados_destinos' creada." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración Fase 20 completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
