<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    echo "Iniciando rollback seguro de la Fase 2...\n";

    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("DROP TABLE IF EXISTS solicitudes_cambio_datos;");
    $db->exec("DROP TABLE IF EXISTS notificaciones_cola;");
    $db->exec("DROP TABLE IF EXISTS notificaciones;");
    $db->exec("DROP TABLE IF EXISTS comunicados;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "✨ Rollback de la Fase 2 completado exitosamente.\n";
} catch (Exception $e) {
    echo "❌ Error durante el rollback: " . $e->getMessage() . "\n";
    exit(1);
}
