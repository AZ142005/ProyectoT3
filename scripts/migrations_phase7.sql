-- Fase 7: Verificar/crear tablas y columnas necesarias
-- Ejecutar: php scripts/migrations_phase7.sql (o via MySQL CLI)
-- Idempotente: crea tablas/columnas solo si no existen

SET @db = DATABASE();

-- 1. jwt_blacklist (JWT blacklist — JWT.php)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jwt_blacklist');
SET @sql = IF(@x=0,
    'CREATE TABLE jwt_blacklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jti VARCHAR(64) NOT NULL UNIQUE,
        user_id INT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_jti (jti),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "jwt_blacklist ya existe" AS status');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2. auth_otp_tokens (OTP — OtpModel.php)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='auth_otp_tokens');
SET @sql = IF(@x=0,
    'CREATE TABLE auth_otp_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        codigo_hash VARCHAR(255) NOT NULL,
        intentos INT DEFAULT 0,
        usado TINYINT(1) DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "auth_otp_tokens ya existe" AS status');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

-- 3. refresh_tokens (API refresh — ApiController.php)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='refresh_tokens');
SET @sql = IF(@x=0,
    'CREATE TABLE refresh_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        revocado TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token_hash),
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "refresh_tokens ya existe" AS status');
PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

-- 4. rate_limits (RateLimiter.php)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='rate_limits');
SET @sql = IF(@x=0,
    'CREATE TABLE rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(100) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        attempts INT DEFAULT 1,
        window_start DATETIME NOT NULL,
        INDEX idx_key_ip (`key`, ip),
        INDEX idx_window (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "rate_limits ya existe" AS status');
PREPARE s4 FROM @sql; EXECUTE s4; DEALLOCATE PREPARE s4;

-- 5. notificaciones (NotificacionesModel.php)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notificaciones');
SET @sql = IF(@x=0,
    'CREATE TABLE notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        residente_id INT NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        mensaje TEXT,
        tipo VARCHAR(20) DEFAULT "info",
        leido TINYINT(1) DEFAULT 0,
        enlace VARCHAR(255) NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_residente (residente_id),
        INDEX idx_leido (leido)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "notificaciones ya existe" AS status');
PREPARE s5 FROM @sql; EXECUTE s5; DEALLOCATE PREPARE s5;

-- 6. backups_log (RespaldoController.php)
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='backups_log');
SET @sql = IF(@x=0,
    'CREATE TABLE backups_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre_archivo VARCHAR(255) NOT NULL,
        tamano_bytes BIGINT DEFAULT 0,
        hash_sha256 VARCHAR(64) NULL,
        checksum_sha256 VARCHAR(64) NULL,
        tablas_respaldadas INT DEFAULT 0,
        admin_id INT NULL,
        fecha_respaldo DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "backups_log ya existe" AS status');
PREPARE s6 FROM @sql; EXECUTE s6; DEALLOCATE PREPARE s6;

-- 7. Columna ultimo_acceso en usuarios
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='usuarios' AND COLUMN_NAME='ultimo_acceso');
SET @sql = IF(@x=0,
    'ALTER TABLE usuarios ADD COLUMN ultimo_acceso DATETIME NULL AFTER estado',
    'SELECT "usuarios.ultimo_acceso ya existe" AS status');
PREPARE s7 FROM @sql; EXECUTE s7; DEALLOCATE PREPARE s7;

-- 8. Columna ultimo_acceso en personas
SET @x = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='personas' AND COLUMN_NAME='ultimo_acceso');
SET @sql = IF(@x=0,
    'ALTER TABLE personas ADD COLUMN ultimo_acceso DATETIME NULL AFTER estado',
    'SELECT "personas.ultimo_acceso ya existe" AS status');
PREPARE s8 FROM @sql; EXECUTE s8; DEALLOCATE PREPARE s8;

SELECT '✅ Migración Fase 7 completada' AS resultado;
