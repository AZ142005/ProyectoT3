<?php
/**
 * Script de Migración para la Fase 3: Conciliación Bancaria, Gastos Comunes y Trazabilidad.
 * Ejecutable vía CLI: php scripts/migrate_fase3.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  MIGRACIÓN BASE DE DATOS - FASE 3 (CONCILIACIÓN Y GASTOS)\n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Verificación Estructural de Tablas de Fases 1 y 2
    echo "1. Verificando integridad estructural de tablas base...\n";
    $tablasRequeridas = [
        'edificios', 'unidades', 'personas', 'usuarios', 
        'pagos', 'facturas', 'estacionamientos', 'vehiculos',
        'comunicados', 'notificaciones', 'notificaciones_cola', 'solicitudes_cambio_datos'
    ];

    foreach ($tablasRequeridas as $tabla) {
        $stmt = $db->query("SHOW TABLES LIKE '{$tabla}'");
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("ERROR ESTRUCTURAL: La tabla requerida '{$tabla}' no existe. Ejecute migraciones anteriores.");
        }
    }
    echo "   ✔ Tablas base y de Fase 1/2 verificadas correctamente.\n";

    // 2. Tabla de Categorías de Gastos Comunes (RF 21)
    echo "2. Creando tabla 'categorias_gastos' con seeder oficial...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS categorias_gastos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            descripcion TEXT NULL,
            icono VARCHAR(50) DEFAULT 'receipt_long',
            color VARCHAR(30) DEFAULT '#27ae60',
            activo TINYINT(1) DEFAULT 1,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    // Seeder oficial idempotente
    $categoriasDefault = [
        ['Mantenimiento e Infraestructura', 'Bombas de agua, ascensores, portones eléctricos y áreas comunes', 'build', '#27ae60'],
        ['Servicios Públicos', 'Suministro eléctrico común, agua potable y aseo urbano', 'water_drop', '#2980b9'],
        ['Seguridad y Vigilancia', 'Servicio de vigilancia privada y control de accesos', 'security', '#8e44ad'],
        ['Administración y Honorarios', 'Honorarios de administración, contabilidad y suministros de oficina', 'account_balance', '#d35400'],
        ['Fondo de Reserva e Imprevistos', 'Aporte al fondo de reserva para emergencias y proyectos comunitarios', 'savings', '#16a085']
    ];

    $stmtCheckCat = $db->prepare("SELECT id FROM categorias_gastos WHERE nombre = :nombre");
    $stmtInsCat = $db->prepare("INSERT INTO categorias_gastos (nombre, descripcion, icono, color) VALUES (:nombre, :descripcion, :icono, :color)");

    foreach ($categoriasDefault as $cat) {
        $stmtCheckCat->execute(['nombre' => $cat[0]]);
        if ($stmtCheckCat->rowCount() === 0) {
            $stmtInsCat->execute([
                'nombre'      => $cat[0],
                'descripcion' => $cat[1],
                'icono'       => $cat[2],
                'color'       => $cat[3]
            ]);
        }
    }
    echo "   ✔ Tabla 'categorias_gastos' y seeder configurados.\n";

    // 3. Tabla de Gastos Comunes del Condominio (RF 30, RF 33)
    echo "3. Creando tabla 'gastos_comunes'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS gastos_comunes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categoria_id INT NOT NULL,
            mes TINYINT NOT NULL,
            anio SMALLINT NOT NULL,
            descripcion VARCHAR(255) NOT NULL,
            monto_total DECIMAL(12,2) NOT NULL,
            fecha_gasto DATE NOT NULL,
            proveedor VARCHAR(150) NOT NULL,
            nro_factura_proveedor VARCHAR(100) DEFAULT NULL,
            soporte_digital VARCHAR(255) DEFAULT NULL,
            admin_id INT NOT NULL,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gastos_periodo (mes, anio, categoria_id),
            UNIQUE KEY uk_gasto_factura_periodo (mes, anio, nro_factura_proveedor, proveedor),
            FOREIGN KEY (categoria_id) REFERENCES categorias_gastos(id),
            FOREIGN KEY (admin_id) REFERENCES usuarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'gastos_comunes' lista con índice y clave única.\n";

    // 4. Tabla de Extractos Bancarios Importados (RF 26, RF 27)
    echo "4. Creando tabla 'extractos_bancarios'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS extractos_bancarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            banco VARCHAR(100) NOT NULL,
            fecha_movimiento DATE NOT NULL,
            referencia_bancaria VARCHAR(100) NOT NULL,
            descripcion_banco TEXT DEFAULT NULL,
            monto DECIMAL(12,2) NOT NULL,
            tipo_movimiento ENUM('credito', 'debito') DEFAULT 'credito',
            estado_conciliacion ENUM('pendiente', 'conciliado', 'descartado') DEFAULT 'pendiente',
            pago_id INT NULL,
            admin_id INT NULL,
            lote_importacion VARCHAR(50) NOT NULL,
            fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_extracto_busqueda (referencia_bancaria, monto, fecha_movimiento),
            INDEX idx_extracto_estado (estado_conciliacion),
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE SET NULL,
            FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'extractos_bancarios' creada con índices compuestos de búsqueda.\n";

    // 5. Tabla de Movimientos de Cuenta / Libro Mayor (RF 18, RF 19, RF 24)
    echo "5. Creando tabla 'movimientos_cuenta'...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS movimientos_cuenta (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unidad_id INT NOT NULL,
            tipo ENUM('cargo_factura', 'abono_pago', 'ajuste') NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            saldo_anterior DECIMAL(12,2) NOT NULL,
            saldo_posterior DECIMAL(12,2) NOT NULL,
            referencia_id INT NULL,
            descripcion VARCHAR(255) NOT NULL,
            fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_movimientos_unidad (unidad_id, fecha_movimiento),
            FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "   ✔ Tabla 'movimientos_cuenta' configurada para trazabilidad de saldos.\n";

    // 6. Índice Compuesto de Conciliación en Pagos
    echo "6. Agregando índice compuesto 'idx_pagos_conciliacion' a tabla 'pagos'...\n";
    $indexes = $db->query("SHOW INDEX FROM pagos WHERE Key_name = 'idx_pagos_conciliacion'")->fetchAll();
    if (empty($indexes)) {
        $db->exec("ALTER TABLE pagos ADD INDEX idx_pagos_conciliacion (referencia, monto, estado)");
        echo "   ✔ Índice 'idx_pagos_conciliacion' agregado con éxito.\n";
    } else {
        echo "   ✔ Índice 'idx_pagos_conciliacion' ya existía.\n";
    }

    echo "\n========================================================\n";
    echo "✅ MIGRACIÓN FASE 3 COMPLETADA CON ÉXITO.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN FASE 3: " . $e->getMessage() . "\n";
    exit(1);
}
