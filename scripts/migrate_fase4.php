<?php
/**
 * Script de Migración para la Fase 4: Seguridad Avanzada (2FA/JWT), Auditoría y Respaldos.
 * Ejecutable vía CLI: php scripts/migrate_fase4.php
 *
 * Compatibilidad: MySQL 5.7+ y MySQL 8.0+
 * Usa SHOW COLUMNS / INFORMATION_SCHEMA en lugar de IF NOT EXISTS en ALTER TABLE
 * para garantizar compatibilidad con MySQL 5.7.
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  MIGRACIÓN BASE DE DATOS - FASE 4 (SEGURIDAD Y AUDITORÍA)\n";
echo "========================================================\n\n";

// Función auxiliar de compatibilidad MySQL 5.7+/8.0+
// Verifica si una columna existe en una tabla usando INFORMATION_SCHEMA
function columnaExiste(PDO $db, string $tabla, string $columna): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = :tabla 
          AND COLUMN_NAME = :columna
    ");
    $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

// Función auxiliar: verifica si una tabla existe
function tablaExiste(PDO $db, string $tabla): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = :tabla
    ");
    $stmt->execute(['tabla' => $tabla]);
    return (int)$stmt->fetchColumn() > 0;
}

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
        if (!tablaExiste($db, $tabla)) {
            throw new RuntimeException(
                "ERROR ESTRUCTURAL: La tabla requerida '{$tabla}' no existe. " .
                "Ejecute las migraciones de Fases 1, 2 y 3 antes de continuar."
            );
        }
    }
    echo "   ✔ Todas las tablas de Fases 1, 2 y 3 verificadas correctamente.\n";

    // 2. Modificación de tablas 'usuarios' y 'personas' para soporte 2FA
    // Usa INFORMATION_SCHEMA para compatibilidad MySQL 5.7
    echo "2. Agregando soporte 2FA a tablas 'usuarios' y 'personas'...\n";

    if (!columnaExiste($db, 'usuarios', 'two_factor_enabled')) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
        echo "   ✔ Columna 'two_factor_enabled' agregada a 'usuarios'.\n";
    } else {
        echo "   ℹ 'usuarios.two_factor_enabled' ya existe (idempotente).\n";
    }

    if (!columnaExiste($db, 'personas', 'two_factor_enabled')) {
        $db->exec("ALTER TABLE personas ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
        echo "   ✔ Columna 'two_factor_enabled' agregada a 'personas'.\n";
    } else {
        echo "   ℹ 'personas.two_factor_enabled' ya existe (idempotente).\n";
    }

    // 3. Tabla de Tokens OTP Temporales para 2FA (RF 3)
    // usuario_tipo distingue entre usuarios (admin/auditor) y personas (residentes)
    // Resuelve colisión de IDs entre tablas (G1-SEC-02)
    echo "3. Creando tabla 'auth_otp_tokens' (con discriminador usuario_tipo)...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS auth_otp_tokens (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id     INT NOT NULL,
            usuario_tipo   ENUM('usuario','persona') NOT NULL DEFAULT 'persona',
            codigo_hash    VARCHAR(255) NOT NULL,
            intentos       TINYINT DEFAULT 0,
            usado          TINYINT(1) DEFAULT 0,
            expires_at     TIMESTAMP NOT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_usuario_tipo (usuario_id, usuario_tipo, expires_at),
            INDEX idx_otp_activo (usuario_id, usuario_tipo, usado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'auth_otp_tokens' lista con discriminador de tipo y índices compuestos.\n";

    // 4. Tabla de Refresh Tokens para JWT (RF 6)
    // usuario_tipo previene colisión de IDs entre usuarios y personas (G1-SEC-01)
    echo "4. Creando tabla 'refresh_tokens' (con discriminador usuario_tipo)...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS refresh_tokens (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id     INT NOT NULL,
            usuario_tipo   ENUM('usuario','persona') NOT NULL DEFAULT 'persona',
            token_hash     VARCHAR(255) NOT NULL UNIQUE,
            expires_at     TIMESTAMP NOT NULL,
            revocado       TINYINT(1) DEFAULT 0,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_refresh_usuario (usuario_id, usuario_tipo, revocado),
            INDEX idx_refresh_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'refresh_tokens' configurada con discriminador de tipo para JWT seguro.\n";

    // 5. Tabla de Registro de Respaldos de Base de Datos (RNF 3)
    // Incluye checksum_sha256 desde la Fase 4 (no se requiere migración adicional en Fase 6)
    // hash_sha256 mantiene compatibilidad hacia adelante con el campo checksum_sha256 de fases posteriores
    echo "5. Creando tabla 'backups_log' (con hash_sha256 y checksum_sha256)...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS backups_log (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            nombre_archivo     VARCHAR(255) NOT NULL,
            tamano_bytes       BIGINT UNSIGNED NOT NULL,
            hash_sha256        VARCHAR(64) NOT NULL COMMENT 'SHA-256 del archivo .sql.gz',
            checksum_sha256    VARCHAR(64) NULL COMMENT 'Alias de hash_sha256 para compatibilidad Fase 6',
            tablas_respaldadas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            motor              ENUM('mysqldump','pdo_fallback') NOT NULL DEFAULT 'mysqldump'
                               COMMENT 'Motor utilizado para generar el respaldo',
            estado             ENUM('exitoso','fallido','parcial') NOT NULL DEFAULT 'exitoso',
            notas              TEXT NULL,
            admin_id           INT NULL,
            fecha_respaldo     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_backups_fecha (fecha_respaldo),
            INDEX idx_backups_estado (estado, fecha_respaldo),
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'backups_log' lista con hash_sha256, checksum_sha256, motor y estado.\n";
    echo "   ℹ 'checksum_sha256' incluido desde Fase 4 — no requiere migración adicional en Fase 6.\n";

    // 6. Directorio de respaldos fuera de la raíz pública
    echo "6. Verificando directorio de almacenamiento seguro 'storage/backups/'...\n";
    $backupDir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "   ✔ Directorio 'storage/backups/' creado con permisos 0755.\n";
    } else {
        echo "   ℹ Directorio 'storage/backups/' ya existe.\n";
    }

    // Verificar que el directorio NO está dentro de public/
    $publicDir = realpath(dirname(__DIR__) . '/public');
    $resolvedBackup = realpath($backupDir);
    if ($resolvedBackup && $publicDir && str_starts_with($resolvedBackup, $publicDir)) {
        echo "   ⚠ ADVERTENCIA: 'storage/backups/' está dentro de public/. Muévalo a una ruta segura.\n";
    } else {
        echo "   ✔ Directorio de respaldos está fuera de la raíz pública. Correcto.\n";
    }

    echo "\n========================================================\n";
    echo "✅ MIGRACIÓN FASE 4 COMPLETADA CON ÉXITO.\n";
    echo "   Recuerde:\n";
    echo "   • Generar JWT_SECRET: openssl rand -base64 32\n";
    echo "   • Generar NOTIFICATION_ENCRYPT_KEY: openssl rand -hex 32\n";
    echo "   • Generar APP_KEY: openssl rand -hex 32\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN FASE 4: " . $e->getMessage() . "\n";
    exit(1);
}
