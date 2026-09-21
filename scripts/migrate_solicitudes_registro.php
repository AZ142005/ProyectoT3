<?php
/**
 * FASE 12 — Tabla solicitudes_registro y campo numero_residentes en personas
 * Ejecutar: php scripts/migrate_solicitudes_registro.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    // 1. Crear tabla solicitudes_registro
    $db->exec("
        CREATE TABLE IF NOT EXISTS solicitudes_registro (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cedula VARCHAR(20) NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            telefono VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            unidad_id INT NOT NULL,
            numero_residentes INT DEFAULT 1 NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
            admin_id INT NULL,
            motivo_rechazo VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            INDEX idx_solicitud_estado (estado),
            INDEX idx_solicitud_unidad (unidad_id),
            INDEX idx_solicitud_cedula (cedula),
            INDEX idx_solicitud_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "[OK] Tabla 'solicitudes_registro' creada o ya existente.\n";

    // 2. Verificar y agregar columna 'numero_residentes' en tabla personas
    $checkRes = $db->query("SHOW COLUMNS FROM personas LIKE 'numero_residentes'");
    if (!$checkRes->fetch()) {
        $db->exec("ALTER TABLE personas ADD COLUMN numero_residentes INT DEFAULT 1 NOT NULL AFTER tipo");
        echo "[OK] Columna 'numero_residentes' agregada a personas.\n";
    } else {
        echo "[INFO] La columna 'numero_residentes' ya existe en personas.\n";
    }

    echo "[COMPLETADO] Migración de solicitudes_registro ejecutada con éxito.\n";
    exit(0);
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
