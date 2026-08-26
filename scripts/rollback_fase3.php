<?php
/**
 * Script de Rollback para la Fase 3: Elimina tablas creadas en la Fase 3.
 * Ejecutable vía CLI: php scripts/rollback_fase3.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  ROLLBACK BASE DE DATOS - FASE 3\n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "1. Eliminando tablas creadas en Fase 3...\n";
    $db->exec("DROP TABLE IF EXISTS movimientos_cuenta;");
    $db->exec("DROP TABLE IF EXISTS extractos_bancarios;");
    $db->exec("DROP TABLE IF EXISTS gastos_comunes;");
    $db->exec("DROP TABLE IF EXISTS categorias_gastos;");
    echo "   ✔ Tablas 'movimientos_cuenta', 'extractos_bancarios', 'gastos_comunes', 'categorias_gastos' eliminadas.\n";

    echo "2. Eliminando índice de conciliación en 'pagos' si existe...\n";
    $indexes = $db->query("SHOW INDEX FROM pagos WHERE Key_name = 'idx_pagos_conciliacion'")->fetchAll();
    if (!empty($indexes)) {
        $db->exec("ALTER TABLE pagos DROP INDEX idx_pagos_conciliacion");
        echo "   ✔ Índice 'idx_pagos_conciliacion' eliminado.\n";
    }

    echo "\n========================================================\n";
    echo "✅ ROLLBACK FASE 3 COMPLETADO EXITOSAMENTE.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN ROLLBACK FASE 3: " . $e->getMessage() . "\n";
    exit(1);
}
