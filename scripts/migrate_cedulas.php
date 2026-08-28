<?php
/**
 * Script de migración y normalización de Cédulas en la base de datos
 *
 * Normaliza todas las cédulas en la tabla personas a formato sin guiones,
 * sin espacios y en mayúsculas (ej: V12345678).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    echo "=== Iniciando normalización de cédulas en tabla personas ===" . PHP_EOL;

    // 1. Contar registros con guiones o espacios
    $stmtCount = $db->query("SELECT COUNT(*) FROM personas WHERE cedula LIKE '%-%' OR cedula LIKE '% %'");
    $totalModificar = (int)$stmtCount->fetchColumn();

    echo "Cédulas con guiones o espacios detectadas: {$totalModificar}" . PHP_EOL;

    if ($totalModificar > 0) {
        $stmtUpdate = $db->prepare("UPDATE personas SET cedula = REPLACE(REPLACE(UPPER(cedula), '-', ''), ' ', '') WHERE cedula LIKE '%-%' OR cedula LIKE '% %'");
        $stmtUpdate->execute();
        echo "✔ Cédulas normalizadas correctamente." . PHP_EOL;
    } else {
        echo "✔ Todas las cédulas ya se encuentran normalizadas." . PHP_EOL;
    }

    // 2. Verificar duplicados residuales
    $stmtDup = $db->query("SELECT cedula, COUNT(*) as cnt FROM personas WHERE estado = 1 GROUP BY cedula HAVING cnt > 1");
    $duplicados = $stmtDup->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($duplicados)) {
        echo "⚠ ATENCIÓN: Se detectaron cédulas duplicadas activas tras la normalización:" . PHP_EOL;
        foreach ($duplicados as $d) {
            echo "  - Cédula: {$d['cedula']} ({$d['cnt']} registros)" . PHP_EOL;
        }
    } else {
        echo "✔ Verificación completada: 0 duplicados en cédulas activas." . PHP_EOL;
    }

    echo "=== Migración finalizada con éxito ===" . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Error en migración de cédulas: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
