<?php
/**
 * Script de Rollback para la Fase 4: Elimina tablas y columnas de Fase 4.
 * Ejecutable vía CLI: php scripts/rollback_fase4.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  ROLLBACK BASE DE DATOS - FASE 4\n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "1. Eliminando tablas creadas en Fase 4...\n";
    $db->exec("DROP TABLE IF EXISTS backups_log;");
    $db->exec("DROP TABLE IF EXISTS refresh_tokens;");
    $db->exec("DROP TABLE IF EXISTS auth_otp_tokens;");
    echo "   ✔ Tablas 'backups_log', 'refresh_tokens', 'auth_otp_tokens' eliminadas.\n";

    echo "2. Revirtiendo columnas agregadas a 'usuarios'...\n";
    $cols = $db->query("SHOW COLUMNS FROM usuarios LIKE 'two_factor_enabled'")->fetchAll();
    if (!empty($cols)) {
        $db->exec("ALTER TABLE usuarios DROP COLUMN two_factor_enabled");
        echo "   ✔ Columna 'two_factor_enabled' eliminada de 'usuarios'.\n";
    }

    echo "\n========================================================\n";
    echo "✅ ROLLBACK FASE 4 COMPLETADO EXITOSAMENTE.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN ROLLBACK FASE 4: " . $e->getMessage() . "\n";
    exit(1);
}
