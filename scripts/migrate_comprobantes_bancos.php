<?php
/**
 * Migración idempotente: Agrega las columnas banco_pagador, banco_receptor y cuenta_bancaria_id
 * a la tabla `comprobantes_pago` para registrar fielmente el banco emisor del residente
 * y la cuenta receptora seleccionada.
 *
 * Uso: php scripts/migrate_comprobantes_bancos.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Campos Bancarios en Comprobantes de Pago ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Verificar columnas existentes en comprobantes_pago
    $stmt = $db->query("SHOW COLUMNS FROM comprobantes_pago");
    $existingColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $columnsToAdd = [];
    if (!in_array('banco_pagador', $existingColumns, true)) {
        $columnsToAdd[] = "ADD COLUMN banco_pagador VARCHAR(100) NULL AFTER metodo_pago";
    }
    if (!in_array('banco_receptor', $existingColumns, true)) {
        $columnsToAdd[] = "ADD COLUMN banco_receptor VARCHAR(100) NULL AFTER banco_pagador";
    }
    if (!in_array('cuenta_bancaria_id', $existingColumns, true)) {
        $columnsToAdd[] = "ADD COLUMN cuenta_bancaria_id INT NULL AFTER banco_receptor";
    }

    if (!empty($columnsToAdd)) {
        $sqlAlter = "ALTER TABLE comprobantes_pago " . implode(', ', $columnsToAdd);
        echo "Ejecutando: {$sqlAlter}..." . PHP_EOL;
        $db->exec($sqlAlter);
        echo "✔ Columnas bancarias agregadas exitosamente a 'comprobantes_pago'." . PHP_EOL;
    } else {
        echo "✔ Las columnas bancarias ya existen en 'comprobantes_pago'." . PHP_EOL;
    }

    // 2. Backfill opcional de registros existentes a partir de observaciones
    echo PHP_EOL . "Actualizando registros previos con datos bancarios de observaciones..." . PHP_EOL;
    $stmtBackfill = $db->query("
        SELECT id, observaciones 
        FROM comprobantes_pago 
        WHERE (banco_receptor IS NULL OR banco_receptor = '') 
          AND observaciones LIKE 'Cuenta Destino: %'
    ");
    $comprobantesObs = $stmtBackfill->fetchAll(PDO::FETCH_ASSOC);

    $actualizados = 0;
    $stmtUpdate = $db->prepare("UPDATE comprobantes_pago SET banco_receptor = :banco_receptor WHERE id = :id");

    foreach ($comprobantesObs as $row) {
        if (preg_match('/Cuenta Destino:\s*([^(\n|]+)/i', $row['observaciones'], $matches)) {
            $bancoDetectado = trim($matches[1]);
            if (!empty($bancoDetectado)) {
                $stmtUpdate->execute([
                    'banco_receptor' => $bancoDetectado,
                    'id'             => $row['id']
                ]);
                $actualizados++;
            }
        }
    }

    echo "✔ {$actualizados} comprobantes actualizados con su banco receptor histórico." . PHP_EOL;
    echo PHP_EOL . "✅ Migración completada con éxito." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
