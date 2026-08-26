<?php
/**
 * Script de Migración SQL para la Fase 1:
 * - Creación de tabla `estacionamientos` (con Soft Delete)
 * - Creación de tabla `vehiculos` (con restricción UNIQUE en placa)
 * - Creación de índices compuestos optimizados para morosidad y auditoría
 * - Verificación previa de integridad referencial (Paso 1.5)
 *
 * Uso: C:\xampp\php\php.exe scripts/migrate_fase1.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración Base de Datos: Fase 1 (Estacionamientos, Vehículos e Índices) ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

try {
    // -------------------------------------------------------------
    // PASO 1.5: Verificación de Integridad Referencial
    // -------------------------------------------------------------
    echo "1.5 Verificando integridad referencial en datos existentes..." . PHP_EOL;

    // Verificar existencia de las tablas padre requeridas
    $tablasRequeridas = ['edificios', 'unidades', 'personas', 'usuarios', 'pagos', 'facturas'];
    foreach ($tablasRequeridas as $tabla) {
        $stmt = $db->query("SHOW TABLES LIKE '{$tabla}'");
        if (!$stmt->fetch()) {
            throw new \RuntimeException("La tabla padre '{$tabla}' no existe. Ejecuta las migraciones anteriores antes de continuar.");
        }
    }
    echo "  ✔ Tablas padre verified." . PHP_EOL;

    // Saneamiento preventivo de unidades sin edificio válido (si aplicara)
    $db->exec("UPDATE unidades SET edificio_id = NULL WHERE edificio_id IS NOT NULL AND edificio_id NOT IN (SELECT id FROM edificios)");
    echo "  ✔ Saneamiento de datos referenciales completado." . PHP_EOL . PHP_EOL;

    // -------------------------------------------------------------
    // PASO 1: Creación de Tablas e Índices
    // -------------------------------------------------------------
    echo "1. Creando tabla 'estacionamientos'..." . PHP_EOL;
    $sqlEstacionamientos = "
        CREATE TABLE IF NOT EXISTS estacionamientos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero VARCHAR(20) NOT NULL,
            tipo ENUM('techado', 'descubierto', 'visitante') DEFAULT 'descubierto',
            edificio_id INT NULL,
            unidad_id INT NULL,
            estado TINYINT(1) DEFAULT 1,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estacionamiento_unidad (unidad_id),
            INDEX idx_estacionamiento_edificio (edificio_id),
            FOREIGN KEY (edificio_id) REFERENCES edificios(id) ON DELETE SET NULL,
            FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $db->exec($sqlEstacionamientos);
    echo "  ✔ Tabla 'estacionamientos' lista." . PHP_EOL . PHP_EOL;

    echo "2. Creando tabla 'vehiculos'..." . PHP_EOL;
    $sqlVehiculos = "
        CREATE TABLE IF NOT EXISTS vehiculos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            persona_id INT NOT NULL,
            estacionamiento_id INT NULL,
            placa VARCHAR(20) NOT NULL,
            marca VARCHAR(50) NOT NULL,
            modelo VARCHAR(50) NOT NULL,
            color VARCHAR(30) NOT NULL,
            observaciones VARCHAR(255) DEFAULT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_vehiculo_placa (placa),
            INDEX idx_vehiculos_unidad (unidad_id),
            INDEX idx_vehiculos_persona (persona_id),
            FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE,
            FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
            FOREIGN KEY (estacionamiento_id) REFERENCES estacionamientos(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $db->exec($sqlVehiculos);
    echo "  ✔ Tabla 'vehiculos' lista." . PHP_EOL . PHP_EOL;

    echo "3. Creando índices de optimización para morosidad y auditoría..." . PHP_EOL;

    // Helper para crear índice si no existe
    $crearIndiceSiNoExiste = function($tabla, $nombreIndice, $columnas) use ($db) {
        $stmt = $db->query("SHOW INDEX FROM {$tabla} WHERE Key_name = '{$nombreIndice}'");
        if (!$stmt->fetch()) {
            $db->exec("CREATE INDEX {$nombreIndice} ON {$tabla} ({$columnas})");
            echo "  ✔ Índice '{$nombreIndice}' creado en tabla '{$tabla}'." . PHP_EOL;
        } else {
            echo "  ℹ Índice '{$nombreIndice}' ya existía en tabla '{$tabla}'." . PHP_EOL;
        }
    };

    $crearIndiceSiNoExiste('facturas', 'idx_facturas_morosidad', 'unidad_id, estado, saldo, fecha_vencimiento');
    $crearIndiceSiNoExiste('facturas', 'idx_facturas_antiguedad', 'fecha_vencimiento, estado, saldo');
    $crearIndiceSiNoExiste('log_auditoria', 'idx_log_auditoria_pago', 'pago_id, fecha_registro');

    echo PHP_EOL . "✅ MIGRACIÓN DE LA FASE 1 COMPLETADA EXITOSAMENTE." . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ ERROR EN LA MIGRACIÓN: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
