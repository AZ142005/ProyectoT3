<?php
/**
 * Script de Migración para la Fase 4: Seguridad Avanzada (2FA/JWT), Auditoría y Respaldos.
 * Ejecutable vía CLI: php scripts/migrate_fase4.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  MIGRACIÓN BASE DE DATOS - FASE 4 (SEGURIDAD Y AUDITORÍA)\n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Verificación Estructural de Tablas de Fases Anteriores
    echo "1. Verificando integridad estructural de tablas base...\n";
    $tablasRequeridas = [
        'edificios', 'unidades', 'personas', 'usuarios', 
        'pagos', 'facturas', 'estacionamientos', 'vehiculos',
        'comunicados', 'notificaciones', 'notificaciones_cola', 'solicitudes_cambio_datos',
        'categorias_gastos', 'gastos_comunes', 'extractos_bancarios', 'movimientos_cuenta'
    ];

    foreach ($tablasRequeridas as $tabla) {
        $stmt = $db->query("SHOW TABLES LIKE '{$tabla}'");
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("ERROR ESTRUCTURAL: La tabla requerida '{$tabla}' no existe. Ejecute migraciones anteriores.");
        }
    }
    echo "   ✔ Todas las tablas de Fases 1, 2 y 3 verificadas correctamente.\n";

    // 2. Modificación de tablas 'usuarios' y 'personas' para soporte 2FA
    echo "2. Agregando soporte 2FA a tablas 'usuarios' y 'personas'...\n";
    $colsU = $db->query("SHOW COLUMNS FROM usuarios LIKE 'two_factor_enabled'")->fetchAll();
    if (empty($colsU)) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        echo "   ✔ Columna 'two_factor_enabled' agregada a 'usuarios'.\n";
    }

    $colsP = $db->query("SHOW COLUMNS FROM personas LIKE 'two_factor_enabled'")->fetchAll();
    if (empty($colsP)) {
        $db->exec("ALTER TABLE personas ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        echo "   ✔ Columna 'two_factor_enabled' agregada a 'personas'.\n";
    }

    // 3. Tabla de Tokens OTP Temporales para 2FA (RF 3)
    echo "3. Creando tabla 'auth_otp_tokens'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS auth_otp_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            codigo_hash VARCHAR(255) NOT NULL,
            intentos TINYINT DEFAULT 0,
            usado TINYINT(1) DEFAULT 0,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_usuario (usuario_id, expires_at),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'auth_otp_tokens' lista con índices de expiración.\n";

    // 4. Tabla de Refresh Tokens para JWT (RF 6)
    echo "4. Creando tabla 'refresh_tokens'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS refresh_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            revocado TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_refresh_token (usuario_id, expires_at),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'refresh_tokens' configurada para persistencia y revocación JWT.\n";

    // 5. Tabla de Registro de Respaldos de Base de Datos (RNF 3)
    echo "5. Creando tabla 'backups_log'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS backups_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre_archivo VARCHAR(255) NOT NULL,
            tamano_bytes BIGINT NOT NULL,
            hash_sha256 VARCHAR(64) NOT NULL,
            tablas_respaldadas INT NOT NULL,
            admin_id INT NULL,
            fecha_respaldo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_backups_fecha (fecha_respaldo),
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'backups_log' lista para control de integridad SHA-256.\n";

    // 6. Asegurar directorio storage/backups/
    $backupDir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "   ✔ Directorio seguro 'storage/backups/' creado.\n";
    }

    echo "\n========================================================\n";
    echo "✅ MIGRACIÓN FASE 4 COMPLETADA CON ÉXITO.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN FASE 4: " . $e->getMessage() . "\n";
    exit(1);
}
