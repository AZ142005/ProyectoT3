<?php
/**
 * Migración: Integridad de Datos — FASE 4
 * - Extiende log_auditoria para soportar auditoría genérica (no solo pagos)
 * - Añade columnas que AuditorController espera pero que faltan en migrate_pagos.php
 *
 * Ejecutable vía CLI: php scripts/migrate_integridad_datos.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  MIGRACIÓN - FASE 4: INTEGRIDAD DE DATOS\n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Extender log_auditoria para auditoría genérica
    echo "1. Verificando/esquema de tabla 'log_auditoria'...\n";

    // Hacer pago_id nullable (ya que comprobantes/gastos no tienen pago_id)
    $pagoIdNullable = $db->query("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'log_auditoria' AND COLUMN_NAME = 'pago_id'")->fetch();
    if ($pagoIdNullable && $pagoIdNullable['IS_NULLABLE'] === 'NO') {
        $db->exec("ALTER TABLE log_auditoria MODIFY COLUMN pago_id INT NULL");
        echo "   ✔ Columna 'pago_id' ahora permite NULL.\n";
    } else {
        echo "   ✔ Columna 'pago_id' ya es nullable.\n";
    }

    // Columnas que AuditorController espera pero que la migración original no define
    $columnasRequeridas = [
        'usuario_id'   => "ALTER TABLE log_auditoria ADD COLUMN usuario_id INT NULL AFTER admin_id",
        'accion'       => "ALTER TABLE log_auditoria ADD COLUMN accion VARCHAR(50) NOT NULL DEFAULT 'cambio_estado' AFTER motivo",
        'tabla_afectada' => "ALTER TABLE log_auditoria ADD COLUMN tabla_afectada VARCHAR(50) NULL AFTER accion",
        'registro_id'  => "ALTER TABLE log_auditoria ADD COLUMN registro_id INT NULL AFTER tabla_afectada",
        'detalles'     => "ALTER TABLE log_auditoria ADD COLUMN detalles TEXT NULL AFTER registro_id",
        'ip_address'   => "ALTER TABLE log_auditoria ADD COLUMN ip_address VARCHAR(45) NULL AFTER detalles",
    ];

    $existentes = $db->query("SHOW COLUMNS FROM log_auditoria")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columnasRequeridas as $col => $sql) {
        if (!in_array($col, $existentes)) {
            $db->exec($sql);
            echo "   ✔ Columna '{$col}' agregada a 'log_auditoria'.\n";
        }
    }

    // Renombrar admin_id a admin_id (mantener compatibilidad) y hacerlo nullable
    $adminIdInfo = $db->query("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'log_auditoria' AND COLUMN_NAME = 'admin_id'")->fetch();
    if ($adminIdInfo && $adminIdInfo['IS_NULLABLE'] === 'NO') {
        $db->exec("ALTER TABLE log_auditoria MODIFY COLUMN admin_id INT NULL");
        echo "   ✔ Columna 'admin_id' ahora permite NULL.\n";
    }

    // Añadir created_at si no existe (migración original usa fecha_registro)
    if (!in_array('created_at', $existentes)) {
        $db->exec("ALTER TABLE log_auditoria ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER fecha_registro");
        echo "   ✔ Columna 'created_at' agregada a 'log_auditoria'.\n";
    }

    echo "   ✔ Esquema de 'log_auditoria' actualizado para auditoría genérica.\n";

    // 2. Añadir índices para rendimiento en consultas del AuditorController
    echo "2. Verificando índices de rendimiento...\n";
    $indices = $db->query("SHOW INDEX FROM log_auditoria WHERE Key_name != 'PRIMARY'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('idx_usuario_id', $indices ?? [])) {
        $db->exec("CREATE INDEX idx_usuario_id ON log_auditoria (usuario_id)");
        echo "   ✔ Índice 'idx_usuario_id' creado.\n";
    }
    if (!in_array('idx_tabla_registro', $indices ?? [])) {
        $db->exec("CREATE INDEX idx_tabla_registro ON log_auditoria (tabla_afectada, registro_id)");
        echo "   ✔ Índice 'idx_tabla_registro' creado.\n";
    }
    if (!in_array('idx_created_at', $indices ?? [])) {
        $db->exec("CREATE INDEX idx_created_at ON log_auditoria (created_at)");
        echo "   ✔ Índice 'idx_created_at' creado.\n";
    }

    echo "\n========================================================\n";
    echo "✅ MIGRACIÓN FASE 4 COMPLETADA CON ÉXITO.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN: " . $e->getMessage() . "\n";
    exit(1);
}
