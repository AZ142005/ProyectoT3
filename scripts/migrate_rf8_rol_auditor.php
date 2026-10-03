<?php
/**
 * Migración RF 8 — Gestión de roles Admin/Auditor.
 *
 * OBJETIVO
 * - Ampliar el ENUM de `usuarios.rol` para admitir el rol 'auditor'.
 *   El código ya soporta este rol (login, RBAC y middleware), pero el
 *   esquema original lo declara como `enum('admin')` y no permite
 *   persistir cuentas de auditoría.
 *
 * ROLLBACK MANUAL
 *   ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin') NOT NULL DEFAULT 'admin';
 *   (requiere que no existan filas con rol='auditor')
 *
 * IDEMPOTENTE: consulta SHOW COLUMNS antes del ALTER; puede re-ejecutarse
 * sin efectos secundarios.
 *
 * Uso: php scripts/migrate_rf8_rol_auditor.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración RF 8: Rol Auditor en tabla 'usuarios' ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    echo "1. Inspeccionando columna 'rol' de la tabla 'usuarios'..." . PHP_EOL;

    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'rol'");
    $columna = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$columna) {
        echo "   ❌ La columna 'rol' no existe en la tabla 'usuarios'." . PHP_EOL;
        exit(1);
    }

    $tipoActual = $columna['Type'] ?? '';
    echo "   ℹ Tipo actual: {$tipoActual}" . PHP_EOL;

    if (str_contains($tipoActual, 'auditor')) {
        echo "   ✔ La columna 'rol' ya admite el valor 'auditor'. No se aplicaron cambios." . PHP_EOL;
    } else {
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin','auditor') NOT NULL DEFAULT 'admin'");
        echo "   ✔ Columna 'rol' modificada a ENUM('admin','auditor') NOT NULL DEFAULT 'admin'." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración RF 8 completada con éxito." . PHP_EOL;

} catch (\Throwable $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
