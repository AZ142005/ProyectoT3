<?php
/**
 * Script de Migración SQL: Crea las tablas `pagos` y `log_auditoria`
 * para el módulo de gestión de comprobantes y auditoría.
 *
 * Uso: php scripts/migrate_pagos.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración de Pagos y Auditoría ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

try {
    // 1. Crear tabla `pagos`
    echo "1. Creando tabla 'pagos'..." . PHP_EOL;
    $sqlPagos = "
        CREATE TABLE IF NOT EXISTS pagos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            residente_id INT NOT NULL,
            unidad_id INT NOT NULL,
            monto DECIMAL(10, 2) NOT NULL,
            fecha_pago DATE NOT NULL,
            metodo_pago VARCHAR(50) NOT NULL,
            referencia VARCHAR(100) DEFAULT NULL,
            archivo VARCHAR(255) NOT NULL,
            observaciones TEXT DEFAULT NULL,
            estado VARCHAR(20) DEFAULT 'PENDIENTE',
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (residente_id) REFERENCES personas(id) ON DELETE CASCADE,
            FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $db->exec($sqlPagos);
    echo "  ✔ Tabla 'pagos' lista." . PHP_EOL . PHP_EOL;

    // 2. Crear tabla `log_auditoria`
    echo "2. Creando tabla 'log_auditoria'..." . PHP_EOL;
    $sqlAuditoria = "
        CREATE TABLE IF NOT EXISTS log_auditoria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pago_id INT NOT NULL,
            admin_id INT NOT NULL,
            estado_anterior VARCHAR(20) DEFAULT NULL,
            estado_nuevo VARCHAR(20) NOT NULL,
            motivo TEXT DEFAULT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $db->exec($sqlAuditoria);
    echo "  ✔ Tabla 'log_auditoria' lista." . PHP_EOL . PHP_EOL;

    echo "✅ Migración de pagos completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
