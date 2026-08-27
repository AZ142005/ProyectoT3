<?php
/**
 * Script de Rollback para la Fase 4: Seguridad Avanzada (2FA/JWT), Auditoría y Respaldos.
 * Elimina tablas y columnas creadas por migrate_fase4.php.
 * Ejecutable vía CLI: php scripts/rollback_fase4.php
 *
 * Compatibilidad: MySQL 5.7+ y MySQL 8.0+
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  ROLLBACK BASE DE DATOS - FASE 4 (SEGURIDAD Y AUDITORÍA)\n";
echo "========================================================\n\n";
echo "⚠ ADVERTENCIA: Esta operación eliminará definitivamente los datos\n";
echo "   de OTP activos, refresh tokens y el registro de respaldos.\n\n";

// Función auxiliar de compatibilidad MySQL 5.7+ (mismo enfoque que la migración)
function columnaExisteRollback(PDO $db, string $tabla, string $columna): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :tabla
          AND COLUMN_NAME = :columna
    ");
    $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Eliminar tablas creadas en Fase 4 (en orden inverso de dependencias FK)
    echo "1. Eliminando tablas creadas en Fase 4...\n";
    $db->exec("DROP TABLE IF EXISTS backups_log;");
    echo "   ✔ Tabla 'backups_log' eliminada.\n";

    $db->exec("DROP TABLE IF EXISTS refresh_tokens;");
    echo "   ✔ Tabla 'refresh_tokens' eliminada.\n";

    $db->exec("DROP TABLE IF EXISTS auth_otp_tokens;");
    echo "   ✔ Tabla 'auth_otp_tokens' eliminada.\n";

    // 2. Revertir columnas en 'usuarios' (con compatibilidad MySQL 5.7 via INFORMATION_SCHEMA)
    echo "2. Revirtiendo columnas agregadas a 'usuarios'...\n";
    if (columnaExisteRollback($db, 'usuarios', 'two_factor_enabled')) {
        $db->exec("ALTER TABLE usuarios DROP COLUMN two_factor_enabled");
        echo "   ✔ Columna 'two_factor_enabled' eliminada de 'usuarios'.\n";
    } else {
        echo "   ℹ Columna 'two_factor_enabled' no existe en 'usuarios' (ya revertida).\n";
    }

    // 3. Revertir columnas en 'personas'
    echo "3. Revirtiendo columnas agregadas a 'personas'...\n";
    if (columnaExisteRollback($db, 'personas', 'two_factor_enabled')) {
        $db->exec("ALTER TABLE personas DROP COLUMN two_factor_enabled");
        echo "   ✔ Columna 'two_factor_enabled' eliminada de 'personas'.\n";
    } else {
        echo "   ℹ Columna 'two_factor_enabled' no existe en 'personas' (ya revertida).\n";
    }

    echo "\n========================================================\n";
    echo "✅ ROLLBACK FASE 4 COMPLETADO EXITOSAMENTE.\n";
    echo "   Los archivos físicos en storage/backups/ NO fueron eliminados.\n";
    echo "   Elimínelos manualmente si es necesario.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN ROLLBACK FASE 4: " . $e->getMessage() . "\n";
    exit(1);
}
