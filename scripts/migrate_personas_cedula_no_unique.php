<?php
/**
 * Permite que una misma cédula exista en más de una unidad (mismo propietario
 * con varios apartamentos).
 *
 * - Elimina el índice UNIQUE de personas.cedula.
 * - Deja un índice no único para búsquedas por cédula.
 * - Completa la cédula en las segundas unidades que habían quedado con SIN-:
 *   4-12, 5-63 y 6-35.
 *
 * Idempotente. Uso: php scripts/migrate_personas_cedula_no_unique.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== MIGRACIÓN: CÉDULA SIN UNICIDAD GLOBAL ===" . PHP_EOL;
$db = Database::getConnection();

$indices = $db->query("SHOW INDEX FROM personas WHERE Column_name = 'cedula'")->fetchAll(PDO::FETCH_ASSOC);

$uniques = [];
$tieneNoUnico = false;
foreach ($indices as $indice) {
    if ((int)$indice['Non_unique'] === 0) {
        $uniques[$indice['Key_name']] = true;
    } else {
        $tieneNoUnico = true;
    }
}

foreach (array_keys($uniques) as $nombreIndice) {
    $nombreSeguro = str_replace('`', '``', $nombreIndice);
    $db->exec("ALTER TABLE personas DROP INDEX `{$nombreSeguro}`");
    echo "OK: índice UNIQUE eliminado: {$nombreIndice}" . PHP_EOL;
}

if (!$tieneNoUnico) {
    $db->exec("ALTER TABLE personas ADD INDEX idx_personas_cedula (cedula)");
    echo "OK: índice no único idx_personas_cedula agregado." . PHP_EOL;
} else {
    echo "OK: ya existe un índice no único para cedula." . PHP_EOL;
}

$correcciones = [
    '4-12' => 'V-24566338',
    '5-63' => 'V-3453682',
    '6-35' => 'V-16266526',
];

$stmtUpdate = $db->prepare("
    UPDATE personas p
    INNER JOIN unidades u ON u.id = p.unidad_id
    SET p.cedula = :cedula
    WHERE u.numero = :numero
      AND p.id = u.propietario_id
");

foreach ($correcciones as $numero => $cedula) {
    $stmtUpdate->execute(['cedula' => $cedula, 'numero' => $numero]);
    echo "OK: unidad {$numero} -> {$cedula} ({$stmtUpdate->rowCount()} fila(s))." . PHP_EOL;
}

echo "=== CÉDULAS DUPLICADAS RESULTANTES ===" . PHP_EOL;
$stmtDup = $db->query("
    SELECT cedula, COUNT(*) AS n
    FROM personas
    WHERE cedula IS NOT NULL AND cedula <> ''
    GROUP BY cedula
    HAVING n > 1
    ORDER BY cedula
");
foreach ($stmtDup as $dup) {
    echo " - {$dup['cedula']}: {$dup['n']} registros" . PHP_EOL;
}

echo "=== Fin ===" . PHP_EOL;
