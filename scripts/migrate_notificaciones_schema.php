<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Esquema Notificaciones y Compatibilidad ===" . PHP_EOL . PHP_EOL;
$db = Database::getConnection();

try {
    // 1. Verificar si existe la tabla notificaciones
    $stmt = $db->query("SHOW TABLES LIKE 'notificaciones'");
    if (!$stmt->fetch()) {
        $db->exec("
            CREATE TABLE notificaciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                residente_id INT NOT NULL,
                persona_id INT DEFAULT NULL,
                comunicado_id INT DEFAULT NULL,
                titulo VARCHAR(255) NOT NULL,
                mensaje TEXT NOT NULL,
                tipo VARCHAR(50) DEFAULT 'info',
                leido TINYINT(1) DEFAULT 0,
                leida TINYINT(1) DEFAULT 0,
                enlace VARCHAR(255) DEFAULT NULL,
                fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notif_residente (residente_id, leido),
                INDEX idx_notificaciones_persona (persona_id),
                CONSTRAINT fk_notif_residente FOREIGN KEY (residente_id) REFERENCES personas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "  ✔ Tabla 'notificaciones' creada con esquema completo." . PHP_EOL;
    } else {
        // 2. Columna residente_id
        $col = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'residente_id'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE notificaciones ADD COLUMN residente_id INT NULL AFTER id");
            // Si existe persona_id, transferir datos
            $colPersona = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'persona_id'")->fetch();
            if ($colPersona) {
                $db->exec("UPDATE notificaciones SET residente_id = persona_id WHERE residente_id IS NULL");
            }
            $db->exec("ALTER TABLE notificaciones MODIFY COLUMN residente_id INT NOT NULL");
            try {
                $db->exec("ALTER TABLE notificaciones ADD CONSTRAINT fk_notif_residente FOREIGN KEY (residente_id) REFERENCES personas(id) ON DELETE CASCADE");
            } catch (\Exception $e) {
                echo "  ℹ Restricción FK residente_id ya presente o no requerida." . PHP_EOL;
            }
            echo "  ✔ Columna 'residente_id' añadida y migrada." . PHP_EOL;
        }

        // 3. Columna persona_id: asegurar que sea NULL por defecto para no bloquear inserciones
        $colPersona = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'persona_id'")->fetch();
        if ($colPersona && $colPersona['Null'] === 'NO') {
            $db->exec("ALTER TABLE notificaciones MODIFY COLUMN persona_id INT DEFAULT NULL");
            echo "  ✔ Columna 'persona_id' modificada para permitir NULL por defecto." . PHP_EOL;
        }

        // 4. Columna tipo
        $col = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'tipo'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE notificaciones ADD COLUMN tipo VARCHAR(50) DEFAULT 'info' AFTER mensaje");
            echo "  ✔ Columna 'tipo' añadida." . PHP_EOL;
        }

        // 5. Columna leido
        $col = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'leido'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE notificaciones ADD COLUMN leido TINYINT(1) DEFAULT 0 AFTER tipo");
            $colLeida = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'leida'")->fetch();
            if ($colLeida) {
                $db->exec("UPDATE notificaciones SET leido = leida WHERE leido IS NULL OR leido = 0");
            }
            echo "  ✔ Columna 'leido' añadida y sincronizada." . PHP_EOL;
        }

        // 6. Columna leida: asegurar que permita DEFAULT 0
        $colLeida = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'leida'")->fetch();
        if ($colLeida && $colLeida['Default'] === null) {
            $db->exec("ALTER TABLE notificaciones MODIFY COLUMN leida TINYINT(1) DEFAULT 0");
            echo "  ✔ Columna 'leida' ajustada a DEFAULT 0." . PHP_EOL;
        }

        // 7. Columna enlace
        $col = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'enlace'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE notificaciones ADD COLUMN enlace VARCHAR(255) DEFAULT NULL AFTER leido");
            echo "  ✔ Columna 'enlace' añadida." . PHP_EOL;
        }

        // 8. Columna fecha_registro
        $col = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'fecha_registro'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE notificaciones ADD COLUMN fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER enlace");
            $colCreated = $db->query("SHOW COLUMNS FROM notificaciones LIKE 'created_at'")->fetch();
            if ($colCreated) {
                $db->exec("UPDATE notificaciones SET fecha_registro = created_at WHERE fecha_registro IS NULL");
            }
            echo "  ✔ Columna 'fecha_registro' añadida y sincronizada." . PHP_EOL;
        }

        // 9. Índice para optimizar consultas de bandeja (residente_id, leido)
        $indices = $db->query("SHOW INDEX FROM notificaciones WHERE Key_name = 'idx_notif_residente'")->fetchAll();
        if (empty($indices)) {
            $db->exec("ALTER TABLE notificaciones ADD INDEX idx_notif_residente (residente_id, leido)");
            echo "  ✔ Índice 'idx_notif_residente' añadido." . PHP_EOL;
        }
    }

    // 10. Columna personas.ultimo_acceso (previene errores en Auth::requireLogin/Rol)
    $colUltimo = $db->query("SHOW COLUMNS FROM personas LIKE 'ultimo_acceso'")->fetch();
    if (!$colUltimo) {
        $db->exec("ALTER TABLE personas ADD COLUMN ultimo_acceso DATETIME NULL AFTER estado");
        echo "  ✔ Columna 'ultimo_acceso' añadida a 'personas'." . PHP_EOL;
    }

    echo PHP_EOL . "✅ Migración de notificaciones y compatibilidad finalizada con éxito." . PHP_EOL;
} catch (\Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
