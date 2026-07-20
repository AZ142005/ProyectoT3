<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';
use App\Core\Database;

try {
    $db = Database::getConnection();
    // Add columns, ignoring error if they already exist
    $db->exec("ALTER TABLE pagos ADD COLUMN banco_pagador VARCHAR(100) NULL AFTER metodo_pago, ADD COLUMN banco_receptor VARCHAR(100) NULL AFTER banco_pagador");
    echo "Columnas añadidas exitosamente.\n";
} catch (Exception $e) {
    echo "Info: " . $e->getMessage() . "\n";
}
