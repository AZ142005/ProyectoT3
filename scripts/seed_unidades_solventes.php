<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== COMPLETANDO UNIDADES RESIDENCIALES DEL CONDOMINIO (252 TOTAL) ===\n\n";

$db = Database::getConnection();
$db->beginTransaction();

try {
    $edificiosMap = [
        1 => 360,
        2 => 361,
        3 => 362,
        4 => 363,
        5 => 364,
        6 => 365,
    ];

    // Verificar que los edificios existan
    $checkEdif = $db->prepare("SELECT id, nombre FROM edificios WHERE id = :id");
    foreach ($edificiosMap as $torreNum => $edifId) {
        $checkEdif->execute([':id' => $edifId]);
        $row = $checkEdif->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("El edificio ID {$edifId} (Torre {$torreNum}) no existe en la base de datos.");
        }
    }

    $insertStmt = $db->prepare("
        INSERT INTO unidades (edificio_id, numero, cuota_mensual, estado, propietario_id)
        VALUES (:edificio_id, :numero, 0.00, 1, NULL)
    ");

    $checkStmt = $db->prepare("SELECT id FROM unidades WHERE numero = :numero");

    $insertados = 0;
    $existentes = 0;

    for ($t = 1; $t <= 6; $t++) {
        $edifId = $edificiosMap[$t];
        for ($p = 0; $p <= 6; $p++) {
            for ($a = 1; $a <= 6; $a++) {
                $numero = sprintf("%d-%d%d", $t, $p, $a);

                $checkStmt->execute([':numero' => $numero]);
                if ($checkStmt->fetch()) {
                    $existentes++;
                    continue;
                }

                $insertStmt->execute([
                    ':edificio_id' => $edifId,
                    ':numero'      => $numero,
                ]);
                $insertados++;
                echo " + Creada unidad solvente: Torre {$t} -> {$numero}\n";
            }
        }
    }

    $db->commit();
    echo "\n✔ Éxito: {$insertados} unidades creadas, {$existentes} ya existían.\n";
    echo "✔ Total unidades en las 6 torres: " . ($insertados + $existentes) . " (42 por torre x 6 = 252).\n";

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
