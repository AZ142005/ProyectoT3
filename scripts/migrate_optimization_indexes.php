<?php
/**
 * Script de Migración SQL: Índices de Rendimiento y Optimización de Consultas
 *
 * Crea índices compuestos en MySQL/MariaDB de forma no bloqueante (ALGORITHM=INPLACE, LOCK=NONE)
 * y con verificación de existencia previa en INFORMATION_SCHEMA para máxima idempotencia.
 *
 * Uso: php scripts/migrate_optimization_indexes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "========================================================" . PHP_EOL;
echo "  MIGRACIÓN DE ÍNDICES DE OPTIMIZACIÓN (MySQL/MariaDB)  " . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

// Definición de índices objetivo: [tabla, nombre_indice, columnas_sql]
$indexesToCreate = [
    // Tabla pagos
    ['pagos', 'idx_pagos_estado_fecha', 'estado, fecha_pago'],
    ['pagos', 'idx_pagos_residente_fecha', 'residente_id, fecha_pago'],
    ['pagos', 'idx_pagos_unidad', 'unidad_id'],

    // Tabla facturas
    ['facturas', 'idx_facturas_unidad_saldo', 'unidad_id, saldo'],
    ['facturas', 'idx_facturas_vencimiento', 'fecha_vencimiento'],

    // Tabla unidades
    ['unidades', 'idx_unidades_edificio_estado', 'edificio_id, estado'],

    // Tabla personas
    ['personas', 'idx_personas_unidad_estado', 'unidad_id, estado'],
    ['personas', 'idx_personas_cedula', 'cedula'],
];

// Si existe la tabla legacy comprobantes_pago, indexarla también
try {
    $stmt = $db->query("SHOW TABLES LIKE 'comprobantes_pago'");
    if ($stmt->fetch()) {
        $indexesToCreate[] = ['comprobantes_pago', 'idx_comprobantes_residente_estado', 'residente_id, estado'];
        $indexesToCreate[] = ['comprobantes_pago', 'idx_comprobantes_factura', 'factura_id'];
    }
} catch (\PDOException $e) {
    // Ignorar si no aplica
}

$creados = 0;
$existentes = 0;
$errores = 0;

foreach ($indexesToCreate as [$tabla, $nombreIndice, $columnas]) {
    echo "• Verificando índice '$nombreIndice' en tabla '$tabla'..." . PHP_EOL;

    try {
        // 1. Verificar si la tabla existe
        $tableCheck = $db->query("SHOW TABLES LIKE '$tabla'");
        if (!$tableCheck->fetch()) {
            echo "   ℹ Tabla '$tabla' no existe en esta BD. Saltando." . PHP_EOL;
            continue;
        }

        // 2. Verificar si el índice ya existe en INFORMATION_SCHEMA
        $checkStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = DATABASE() 
              AND table_name = :tabla 
              AND index_name = :indice
        ");
        $checkStmt->execute([
            'tabla'  => $tabla,
            'indice' => $nombreIndice
        ]);

        if ($checkStmt->fetchColumn() > 0) {
            echo "   ✔ Índice '$nombreIndice' ya existe. (Idempotente)" . PHP_EOL;
            $existentes++;
            continue;
        }

        // 3. Intentar creación no bloqueante con ALGORITHM=INPLACE, LOCK=NONE
        $sqlInplace = "ALTER TABLE `{$tabla}` ADD INDEX `{$nombreIndice}` ({$columnas}) ALGORITHM=INPLACE, LOCK=NONE";
        try {
            $db->exec($sqlInplace);
            echo "   ✅ Creado con éxito (INPLACE, LOCK=NONE)." . PHP_EOL;
            $creados++;
        } catch (\PDOException $eInplace) {
            // Fallback a ALTER TABLE estándar si la versión de MySQL/MariaDB o tipo de índice no soporta INPLACE
            $sqlStandard = "ALTER TABLE `{$tabla}` ADD INDEX `{$nombreIndice}` ({$columnas})";
            $db->exec($sqlStandard);
            echo "   ✅ Creado con éxito (Modo estándar)." . PHP_EOL;
            $creados++;
        }

    } catch (\Exception $e) {
        echo "   ❌ Error al procesar índice '$nombreIndice': " . $e->getMessage() . PHP_EOL;
        $errores++;
    }
}

echo PHP_EOL . "========================================================" . PHP_EOL;
echo "Resumen de Migración:" . PHP_EOL;
echo "  - Índices nuevos creados: $creados" . PHP_EOL;
echo "  - Índices ya existentes:  $existentes" . PHP_EOL;
echo "  - Errores encontrados:    $errores" . PHP_EOL;
echo "========================================================" . PHP_EOL;

if ($errores === 0) {
    echo "✅ MIGRACIÓN DE ÍNDICES FINALIZADA SATISFACTORIAMENTE." . PHP_EOL;
    exit(0);
} else {
    echo "⚠ La migración finalizó con advertencias." . PHP_EOL;
    exit(1);
}
