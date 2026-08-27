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
    $checkIndex = function(string $table, string $index) use ($db): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index");
        $stmt->execute(['table' => $table, 'index' => $index]);
        return (int)$stmt->fetchColumn() > 0;
    };

    if (!$checkIndex('log_auditoria', 'idx_usuario_id')) {
        $db->exec("CREATE INDEX idx_usuario_id ON log_auditoria (usuario_id)");
        echo "   ✔ Índice 'idx_usuario_id' creado.\n";
    }
    if (!$checkIndex('log_auditoria', 'idx_tabla_registro')) {
        $db->exec("CREATE INDEX idx_tabla_registro ON log_auditoria (tabla_afectada, registro_id)");
        echo "   ✔ Índice 'idx_tabla_registro' creado.\n";
    }
    if (!$checkIndex('log_auditoria', 'idx_created_at')) {
        $db->exec("CREATE INDEX idx_created_at ON log_auditoria (created_at)");
        echo "   ✔ Índice 'idx_created_at' creado.\n";
    }

    // 3. Añadir columnas deleted_at para soporte de soft-delete en todas las tablas
    echo "3. Verificando columnas 'deleted_at' para soft-delete...\n";
    $tablasSoftDelete = ['facturas', 'comunicados', 'estacionamientos', 'vehiculos', 'gastos_comunes', 'pagos', 'comprobantes_pago'];

    foreach ($tablasSoftDelete as $tabla) {
        $cols = $db->query("SHOW COLUMNS FROM {$tabla}")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('deleted_at', $cols)) {
            $db->exec("ALTER TABLE {$tabla} ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            echo "   ✔ Columna 'deleted_at' agregada a tabla '{$tabla}'.\n";
        } else {
            echo "   ℹ '{$tabla}.deleted_at' ya existe.\n";
        }
    }

    // 4. Asegurar columnas requeridas en estacionamientos, vehiculos y comunicados
    echo "4. Verificando columnas estructurales en módulos auxiliares...\n";

    // estacionamientos: edificio_id, fecha_registro
    $colsEst = $db->query("SHOW COLUMNS FROM estacionamientos")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('edificio_id', $colsEst)) {
        $db->exec("ALTER TABLE estacionamientos ADD COLUMN edificio_id INT NULL AFTER tipo");
        echo "   ✔ Columna 'edificio_id' agregada a 'estacionamientos'.\n";
    }
    if (!$checkIndex('estacionamientos', 'idx_estacionamiento_edificio')) {
        $db->exec("CREATE INDEX idx_estacionamiento_edificio ON estacionamientos (edificio_id)");
        echo "   ✔ Índice 'idx_estacionamiento_edificio' creado.\n";
    }
    if (!in_array('fecha_registro', $colsEst)) {
        $db->exec("ALTER TABLE estacionamientos ADD COLUMN fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "   ✔ Columna 'fecha_registro' agregada a 'estacionamientos'.\n";
    }

    // vehiculos: unidad_id, estacionamiento_id, observaciones, fecha_registro
    $colsVeh = $db->query("SHOW COLUMNS FROM vehiculos")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('unidad_id', $colsVeh)) {
        $db->exec("ALTER TABLE vehiculos ADD COLUMN unidad_id INT NULL AFTER id");
        echo "   ✔ Columna 'unidad_id' agregada a 'vehiculos'.\n";
    }
    if (!$checkIndex('vehiculos', 'idx_vehiculos_unidad')) {
        $db->exec("CREATE INDEX idx_vehiculos_unidad ON vehiculos (unidad_id)");
        echo "   ✔ Índice 'idx_vehiculos_unidad' creado.\n";
    }
    if (!in_array('estacionamiento_id', $colsVeh)) {
        $db->exec("ALTER TABLE vehiculos ADD COLUMN estacionamiento_id INT NULL AFTER persona_id");
        echo "   ✔ Columna 'estacionamiento_id' agregada a 'vehiculos'.\n";
    }
    if (!$checkIndex('vehiculos', 'idx_vehiculos_estacionamiento')) {
        $db->exec("CREATE INDEX idx_vehiculos_estacionamiento ON vehiculos (estacionamiento_id)");
        echo "   ✔ Índice 'idx_vehiculos_estacionamiento' creado.\n";
    }
    if (!in_array('observaciones', $colsVeh)) {
        $db->exec("ALTER TABLE vehiculos ADD COLUMN observaciones VARCHAR(255) NULL AFTER color");
        echo "   ✔ Columna 'observaciones' agregada a 'vehiculos'.\n";
    }
    if (!in_array('fecha_registro', $colsVeh)) {
        $db->exec("ALTER TABLE vehiculos ADD COLUMN fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "   ✔ Columna 'fecha_registro' agregada a 'vehiculos'.\n";
    }

    // comunicados: nivel_urgencia, unidad_id, fecha_publicacion
    $colsCom = $db->query("SHOW COLUMNS FROM comunicados")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('nivel_urgencia', $colsCom)) {
        $db->exec("ALTER TABLE comunicados ADD COLUMN nivel_urgencia ENUM('normal', 'importante', 'urgente') DEFAULT 'normal' AFTER contenido");
        echo "   ✔ Columna 'nivel_urgencia' agregada a 'comunicados'.\n";
    }
    if (!in_array('unidad_id', $colsCom)) {
        $db->exec("ALTER TABLE comunicados ADD COLUMN unidad_id INT NULL AFTER edificio_id");
        echo "   ✔ Columna 'unidad_id' agregada a 'comunicados'.\n";
    }
    if (!$checkIndex('comunicados', 'idx_comunicados_unidad')) {
        $db->exec("CREATE INDEX idx_comunicados_unidad ON comunicados (unidad_id)");
        echo "   ✔ Índice 'idx_comunicados_unidad' creado.\n";
    }
    if (!in_array('fecha_publicacion', $colsCom)) {
        $db->exec("ALTER TABLE comunicados ADD COLUMN fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "   ✔ Columna 'fecha_publicacion' agregada a 'comunicados'.\n";
    }

    echo "\n========================================================\n";
    echo "✅ MIGRACIÓN INTEGRIDAD DE DATOS COMPLETADA CON ÉXITO.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN: " . $e->getMessage() . "\n";
    exit(1);
}
