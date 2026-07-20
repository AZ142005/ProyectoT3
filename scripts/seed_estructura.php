<?php
/**
 * Script de Seeder: Puebla datos iniciales para Edificios (Torres) y Unidades (Apartamentos).
 *
 * Uso: C:\xampp\php\php.exe scripts/seed_estructura.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Seeder de Estructura (Edificios y Unidades) ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

$edificiosData = [
    ['nombre' => 'Torre A', 'descripcion' => 'Edificio Residencial Principal'],
    ['nombre' => 'Torre B', 'descripcion' => 'Edificio Residencial Secundario'],
    ['nombre' => 'Torre C', 'descripcion' => 'Edificio de Suites / Anexo']
];

$edificiosIds = [];

// 1. Insertar / Obtener Edificios
foreach ($edificiosData as $ed) {
    $stmt = $db->prepare("SELECT id FROM edificios WHERE nombre = :nombre");
    $stmt->execute(['nombre' => $ed['nombre']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $edificiosIds[$ed['nombre']] = $existing['id'];
        echo "  ℹ Edificio '{$ed['nombre']}' ya existente (ID: {$existing['id']})" . PHP_EOL;
    } else {
        $ins = $db->prepare("INSERT INTO edificios (nombre, descripcion, estado) VALUES (:nombre, :descripcion, 1)");
        $ins->execute(['nombre' => $ed['nombre'], 'descripcion' => $ed['descripcion']]);
        $newId = $db->lastInsertId();
        $edificiosIds[$ed['nombre']] = $newId;
        echo "  ✔ Edificio '{$ed['nombre']}' creado (ID: {$newId})" . PHP_EOL;
    }
}

// 2. Insertar Unidades de ejemplo
$unidadesData = [
    ['numero' => 'A-101', 'edificio' => 'Torre A', 'cuota' => 150.00],
    ['numero' => 'A-102', 'edificio' => 'Torre A', 'cuota' => 180.00],
    ['numero' => 'B-201', 'edificio' => 'Torre B', 'cuota' => 200.00],
    ['numero' => 'C-301', 'edificio' => 'Torre C', 'cuota' => 220.00],
];

echo PHP_EOL . "Procesando unidades de ejemplo..." . PHP_EOL;

foreach ($unidadesData as $u) {
    $edificioId = $edificiosIds[$u['edificio']] ?? null;
    if (!$edificioId) continue;

    $stmt = $db->prepare("SELECT id FROM unidades WHERE numero = :numero");
    $stmt->execute(['numero' => $u['numero']]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Actualizar edificio_id y cuota si es necesario
        $upd = $db->prepare("UPDATE unidades SET edificio_id = :edificio_id, cuota_mensual = :cuota WHERE id = :id");
        $upd->execute(['edificio_id' => $edificioId, 'cuota' => $u['cuota'], 'id' => $existing['id']]);
        echo "  ℹ Unidad '{$u['numero']}' actualizada." . PHP_EOL;
    } else {
        $ins = $db->prepare("INSERT INTO unidades (numero, edificio_id, cuota_mensual, estado) VALUES (:numero, :edificio_id, :cuota, 1)");
        $ins->execute(['numero' => $u['numero'], 'edificio_id' => $edificioId, 'cuota' => $u['cuota']]);
        echo "  ✔ Unidad '{$u['numero']}' registrada." . PHP_EOL;
    }
}

echo PHP_EOL . "✅ Seeder de estructura completado." . PHP_EOL;
