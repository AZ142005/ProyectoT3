<?php
/**
 * Migración Fase 22 — Deudas manuales por unidad y período.
 *
 * OBJETIVO
 * - `deudas_manuales`: monto de deuda elegido manualmente para unidades
 *   específicas (alcance libre: unidades y/o edificios, o todo el
 *   condominio). Al emitir la deuda del período, el monto manual sustituye
 *   la cuota prorrateada de la unidad (se aplica completo, sin repartir) y
 *   se materializa en la factura FAC-… del período vía upsert.
 * - Upsert por (unidad_id, mes, anio) con baja lógica (`deleted_at`): guardar
 *   un nuevo monto para la misma unidad/período reemplaza el anterior.
 *
 * ROLLBACK MANUAL
 *   DROP TABLE deudas_manuales;
 *
 * IDEMPOTENTE: verifica con information_schema antes del CREATE y puede
 * re-ejecutarse sin efectos secundarios.
 *
 * Uso: php scripts/migrate_fase22_deudas_manuales.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración Fase 22: Deudas manuales por unidad y período ===" . PHP_EOL . PHP_EOL;

function tablaExiste(PDO $db, string $tabla): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla
    ");
    $stmt->execute(['tabla' => $tabla]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $db = Database::getConnection();

    echo "1. Tabla 'deudas_manuales'..." . PHP_EOL;
    if (tablaExiste($db, 'deudas_manuales')) {
        echo "   ℹ Tabla 'deudas_manuales' ya existe. No se aplicaron cambios." . PHP_EOL;
    } else {
        $db->exec("
            CREATE TABLE deudas_manuales (
                id int(11) NOT NULL AUTO_INCREMENT,
                unidad_id int(11) NOT NULL,
                mes tinyint NOT NULL,
                anio smallint NOT NULL,
                monto decimal(10,2) NOT NULL,
                admin_id int(11) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT current_timestamp(),
                updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                deleted_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_deuda_manual_unidad_periodo (unidad_id, mes, anio),
                KEY idx_deuda_manual_periodo (mes, anio),
                CONSTRAINT fk_deuda_manual_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        echo "   ✔ Tabla creada." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración completada." . PHP_EOL;
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
