<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

use App\Core\Database;

date_default_timezone_set('America/Caracas');

try {
    $db = Database::getConnection();
    $db->exec("SET time_zone = '-04:00'");
    echo "Iniciando migración de la Fase 2 (Notificaciones, Comunicados, Cartas de Cobro y Perfil)...\n";

    // --- Paso 1.5: Verificación Estructural de Tablas y Columnas de la Fase 1 ---
    $tablasRequeridas = ['edificios', 'unidades', 'personas', 'usuarios', 'pagos', 'facturas', 'estacionamientos', 'vehiculos'];
    foreach ($tablasRequeridas as $tabla) {
        $stmt = $db->query("SHOW TABLES LIKE '{$tabla}'");
        if ($stmt->rowCount() === 0) {
            throw new Exception("Error crítico: La tabla requerida '{$tabla}' no existe. Debe ejecutar scripts/migrate_fase1.php antes de continuar.");
        }
    }

    // Verificación de columnas críticas
    $columnasVerificar = [
        'personas' => ['telefono', 'email', 'cedula'],
        'facturas' => ['saldo', 'estado', 'fecha_vencimiento'],
        'unidades' => ['edificio_id', 'propietario_id'],
        'pagos'    => ['estado', 'monto']
    ];

    foreach ($columnasVerificar as $tabla => $cols) {
        foreach ($cols as $col) {
            $stmt = $db->query("SHOW COLUMNS FROM `{$tabla}` LIKE '{$col}'");
            if ($stmt->rowCount() === 0) {
                throw new Exception("Error estructural: La columna '{$col}' no existe en la tabla '{$tabla}'. Verifique la base de datos.");
            }
        }
    }
    echo "  ✅ Estructura previa de la Fase 1 verificada exitosamente.\n";

    // --- Paso 1: Creación de Tablas de la Fase 2 ---

    // 1. Tabla de Comunicados del Condominio con Soft Delete (RF 37)
    $db->exec("
        CREATE TABLE IF NOT EXISTS comunicados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(150) NOT NULL,
            contenido TEXT NOT NULL,
            nivel_urgencia ENUM('normal', 'importante', 'urgente') DEFAULT 'normal',
            edificio_id INT NULL,
            unidad_id INT NULL,
            admin_id INT NOT NULL,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_comunicados_publicacion (fecha_publicacion, edificio_id, unidad_id),
            FOREIGN KEY (edificio_id) REFERENCES edificios(id) ON DELETE CASCADE,
            FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✅ Tabla 'comunicados' creada/verificada.\n";

    // 2. Tabla de Notificaciones de Residentes / Bandeja (RF 35, RF 37)
    $db->exec("
        CREATE TABLE IF NOT EXISTS notificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            residente_id INT NOT NULL,
            titulo VARCHAR(150) NOT NULL,
            mensaje TEXT NOT NULL,
            tipo VARCHAR(50) DEFAULT 'info',
            leido TINYINT(1) DEFAULT 0,
            enlace VARCHAR(255) DEFAULT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_residente (residente_id, leido),
            FOREIGN KEY (residente_id) REFERENCES personas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✅ Tabla 'notificaciones' creada/verificada.\n";

    // 3. Tabla Cola de Notificaciones Asíncronas con Cifrado Integral (RF 36)
    $db->exec("
        CREATE TABLE IF NOT EXISTS notificaciones_cola (
            id INT AUTO_INCREMENT PRIMARY KEY,
            destinatario_email VARCHAR(255) NOT NULL,
            destinatario_telefono VARCHAR(50) DEFAULT NULL,
            asunto VARCHAR(200) NOT NULL,
            cuerpo_html MEDIUMTEXT NOT NULL,
            canal ENUM('email', 'whatsapp', 'ambos') DEFAULT 'email',
            prioridad ENUM('alta', 'normal', 'baja') DEFAULT 'normal',
            estado ENUM('pendiente', 'enviado', 'fallido') DEFAULT 'pendiente',
            intentos TINYINT DEFAULT 0,
            proximo_intento TIMESTAMP NULL DEFAULT NULL,
            error_mensaje TEXT DEFAULT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cola_procesamiento (estado, proximo_intento, prioridad, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✅ Tabla 'notificaciones_cola' creada/verificada.\n";

    // 4. Tabla de Solicitudes de Cambio de Datos (RF 9)
    $db->exec("
        CREATE TABLE IF NOT EXISTS solicitudes_cambio_datos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            persona_id INT NOT NULL,
            datos_nuevos_json VARCHAR(2048) NOT NULL,
            estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
            motivo_admin TEXT DEFAULT NULL,
            admin_id INT NULL,
            fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_respuesta TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_solicitudes_persona (persona_id, estado),
            FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✅ Tabla 'solicitudes_cambio_datos' creada/verificada.\n";

    echo "✨ ¡Migración de la Fase 2 ejecutada exitosamente con todas las tablas e índices!\n";

} catch (Exception $e) {
    echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
