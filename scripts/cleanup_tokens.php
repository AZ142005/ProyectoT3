<?php
/**
 * Cron job diario: limpia tokens expirados de JWT blacklist, OTP, refresh tokens y notificaciones.
 *
 * Configurar en crontab:
 *   crontab -e
 *   0 2 * * * /usr/bin/php /ruta/proyecto/scripts/cleanup_tokens.php >> /ruta/proyecto/logs/cleanup.log 2>&1
 *
 * Verifica existencia de cada tabla antes de operar. Si falta, registra warning y continúa.
 */
set_time_limit(300);
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando limpieza de tokens expirados...\n";
$cleaned = 0;
$warnings = 0;

function tableExists($db, string $table): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $db = Database::getConnection();

    // 1. JWT blacklist: tokens expirados
    if (tableExists($db, 'jwt_blacklist')) {
        $db->exec("DELETE FROM jwt_blacklist WHERE expires_at < NOW()");
        $count = $db->rowCount();
        echo "  ✔ JWT blacklist: {$count} registro(s) eliminado(s).\n";
        $cleaned++;
    } else {
        echo "  ⚠ jwt_blacklist no existe — omitida.\n";
        $warnings++;
    }

    // 2. OTP tokens: TODOS los expirados (usados y no usados).
    //    No hay razón conservar tokens expirados — el OTP solo es útil dentro de su ventana de 5 min.
    if (tableExists($db, 'auth_otp_tokens')) {
        $db->exec("DELETE FROM auth_otp_tokens WHERE expires_at < NOW()");
        $count = $db->rowCount();
        echo "  ✔ OTP tokens: {$count} registro(s) eliminado(s).\n";
        $cleaned++;
    } else {
        echo "  ⚠ auth_otp_tokens no existe — omitida.\n";
        $warnings++;
    }

    // 3. Refresh tokens: expirados
    if (tableExists($db, 'refresh_tokens')) {
        $db->exec("DELETE FROM refresh_tokens WHERE expires_at < NOW()");
        $count = $db->rowCount();
        echo "  ✔ Refresh tokens: {$count} registro(s) eliminado(s).\n";
        $cleaned++;
    } else {
        echo "  ⚠ refresh_tokens no existe — omitida.\n";
        $warnings++;
    }

    // 4. Notificaciones leídas > 90 días
    //    Política de retención: mensajes leídos se conservan 90 días para historial,
    //    luego se purgan para evitar crecimiento indefinido de la tabla.
    if (tableExists($db, 'notificaciones')) {
        $db->exec("DELETE FROM notificaciones WHERE leido = 1 AND fecha_registro < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $count = $db->rowCount();
        echo "  ✔ Notificaciones antiguas (>90d): {$count} registro(s) eliminado(s).\n";
        $cleaned++;
    } else {
        echo "  ⚠ notificaciones no existe — omitida.\n";
        $warnings++;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Limpieza completada. {$cleaned} tablas procesadas, {$warnings} advertencias.\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
