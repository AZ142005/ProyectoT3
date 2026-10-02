<?php
/**
 * Migración Idempotente: Concurrencia Estricta y Cruce Inteligente 1:1
 * 
 * - Crea la tabla 'movimientos_bancarios' con estados (disponible, conciliado, anulado).
 * - Crea la tabla 'conciliacion_abono_pago' con restricciones UNIQUE 1:1.
 * - Crea la tabla de auditoría append-only 'historial_estado_abono'.
 * - Normaliza y migra estados preexistentes desde 'extractos_bancarios'.
 * - Crea índices compuestos para acelerar filtros de concurrencia.
 * 
 * Uso: php scripts/migrate_concurrencia_conciliacion.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración: Concurrencia Estricta y Cruce Inteligente 1:1 ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Crear tabla 'movimientos_bancarios'
    echo "[1/5] Verificando tabla 'movimientos_bancarios'..." . PHP_EOL;
    $db->exec("
        CREATE TABLE IF NOT EXISTS movimientos_bancarios (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            banco VARCHAR(100) NOT NULL,
            fecha DATE NOT NULL,
            fecha_movimiento DATE NULL,
            referencia VARCHAR(100) NOT NULL,
            referencia_bancaria VARCHAR(100) NULL,
            descripcion TEXT NULL,
            descripcion_banco TEXT NULL,
            importe DECIMAL(12,2) NOT NULL,
            monto DECIMAL(12,2) NULL,
            tipo ENUM('credito', 'debito') NOT NULL DEFAULT 'credito',
            tipo_movimiento ENUM('credito', 'debito') NOT NULL DEFAULT 'credito',
            estado ENUM('disponible', 'conciliado', 'anulado') NOT NULL DEFAULT 'disponible',
            lote_importacion VARCHAR(50) NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mov_estado (estado),
            INDEX idx_mov_estado_fecha (estado, fecha, importe),
            INDEX idx_mov_referencia (referencia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✔ Tabla 'movimientos_bancarios' lista." . PHP_EOL;

    // 2. Normalizar tabla preexistente 'extractos_bancarios'
    echo PHP_EOL . "[2/5] Actualizando columnas en tabla 'extractos_bancarios'..." . PHP_EOL;
    $stmtCol = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'estado'");
    if (!$stmtCol->fetch()) {
        $db->exec("ALTER TABLE extractos_bancarios ADD COLUMN estado ENUM('disponible', 'conciliado', 'anulado') NOT NULL DEFAULT 'disponible' AFTER estado_conciliacion");
        echo "  ✔ Columna 'estado' agregada a 'extractos_bancarios'." . PHP_EOL;
    } else {
        echo "  ℹ Columna 'estado' ya existe en 'extractos_bancarios'." . PHP_EOL;
    }

    $stmtAct = $db->query("SHOW COLUMNS FROM extractos_bancarios LIKE 'actualizado_en'");
    if (!$stmtAct->fetch()) {
        $db->exec("ALTER TABLE extractos_bancarios ADD COLUMN actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "  ✔ Columna 'actualizado_en' agregada a 'extractos_bancarios'." . PHP_EOL;
    }

    // 3. Crear tabla de vínculo 'conciliacion_abono_pago'
    echo PHP_EOL . "[3/5] Verificando tabla de vínculo 1:1 'conciliacion_abono_pago'..." . PHP_EOL;
    $db->exec("
        CREATE TABLE IF NOT EXISTS conciliacion_abono_pago (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            movimiento_id BIGINT NOT NULL,
            pago_id BIGINT NOT NULL,
            conciliado_por BIGINT NOT NULL,
            conciliado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            origen_tipo VARCHAR(20) NOT NULL DEFAULT 'pago',
            idempotency_key VARCHAR(64) NULL,
            CONSTRAINT uq_movimiento UNIQUE (movimiento_id),
            CONSTRAINT uq_pago UNIQUE (pago_id),
            INDEX idx_cap_idempotency (idempotency_key),
            INDEX idx_cap_movimiento (movimiento_id),
            INDEX idx_cap_pago (pago_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✔ Tabla 'conciliacion_abono_pago' lista con constraints UNIQUE (movimiento_id) y (pago_id)." . PHP_EOL;

    // 4. Crear tabla append-only de auditoría 'historial_estado_abono'
    echo PHP_EOL . "[4/5] Verificando tabla append-only 'historial_estado_abono'..." . PHP_EOL;
    $db->exec("
        CREATE TABLE IF NOT EXISTS historial_estado_abono (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            movimiento_id BIGINT NOT NULL,
            pago_id BIGINT NULL,
            estado_anterior VARCHAR(30) NULL,
            estado_nuevo VARCHAR(30) NOT NULL,
            usuario_id BIGINT NOT NULL,
            resultado ENUM('exito', 'rechazado', 'error') NOT NULL DEFAULT 'exito',
            codigo_negocio VARCHAR(50) NULL,
            detalles TEXT NULL,
            ip_address VARCHAR(45) NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hist_mov (movimiento_id),
            INDEX idx_hist_res (resultado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "  ✔ Tabla 'historial_estado_abono' lista." . PHP_EOL;

    // 5. Migrar estados preexistentes y poblar vínculos
    echo PHP_EOL . "[5/5] Sincronizando estados y migrando abonos previos..." . PHP_EOL;

    // 5.1 Migrar estados en extractos_bancarios
    $db->exec("
        UPDATE extractos_bancarios 
        SET estado = 'conciliado' 
        WHERE (pago_id IS NOT NULL AND pago_id > 0) OR estado_conciliacion = 'conciliado'
    ");
    $db->exec("
        UPDATE extractos_bancarios 
        SET estado = 'anulado' 
        WHERE estado_conciliacion = 'descartado' AND estado != 'conciliado'
    ");
    $db->exec("
        UPDATE extractos_bancarios 
        SET estado = 'disponible' 
        WHERE estado IS NULL OR (estado != 'conciliado' AND estado != 'anulado')
    ");

    // 5.2 Poblar conciliacion_abono_pago con registros existentes que tengan pago_id
    $stmtPrev = $db->query("
        SELECT id, pago_id, admin_id, fecha_carga 
        FROM extractos_bancarios 
        WHERE pago_id IS NOT NULL AND pago_id > 0
    ");
    $previos = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);
    $vinculadosPrevios = 0;

    $stmtInsertVinculo = $db->prepare("
        INSERT IGNORE INTO conciliacion_abono_pago 
        (movimiento_id, pago_id, conciliado_por, conciliado_en, origen_tipo)
        VALUES (:mov_id, :pago_id, :admin_id, :fecha, 'pago')
    ");

    foreach ($previos as $p) {
        $adminId = !empty($p['admin_id']) ? (int)$p['admin_id'] : 1;
        $fecha = !empty($p['fecha_carga']) ? $p['fecha_carga'] : date('Y-m-d H:i:s');
        $stmtInsertVinculo->execute([
            'mov_id'   => $p['id'],
            'pago_id'  => $p['pago_id'],
            'admin_id' => $adminId,
            'fecha'    => $fecha
        ]);
        if ($stmtInsertVinculo->rowCount() > 0) {
            $vinculadosPrevios++;
        }
    }
    echo "  ✔ Vínculos históricos sincronizados en 'conciliacion_abono_pago': {$vinculadosPrevios}." . PHP_EOL;

    // 5.3 Copiar extractos_bancarios a movimientos_bancarios si está vacía
    $countMov = (int)$db->query("SELECT COUNT(*) FROM movimientos_bancarios")->fetchColumn();
    if ($countMov === 0) {
        $db->exec("
            INSERT INTO movimientos_bancarios (
                id, banco, fecha, fecha_movimiento, referencia, referencia_bancaria,
                descripcion, descripcion_banco, importe, monto, tipo, tipo_movimiento,
                estado, lote_importacion, creado_en, actualizado_en
            )
            SELECT 
                id, banco, fecha_movimiento, fecha_movimiento,
                COALESCE(referencia_bancaria, referencia, ''), COALESCE(referencia_bancaria, referencia, ''),
                COALESCE(descripcion_banco, descripcion, ''), COALESCE(descripcion_banco, descripcion, ''),
                monto, monto, tipo_movimiento, tipo_movimiento,
                estado, lote_importacion, fecha_carga, NOW()
            FROM extractos_bancarios
        ");
        echo "  ✔ Movimientos sincronizados en tabla canonical 'movimientos_bancarios'." . PHP_EOL;
    }

    echo PHP_EOL . "============================================================" . PHP_EOL;
    echo "✅ Migración de concurrencia y cruce 1:1 finalizada exitosamente." . PHP_EOL;
    echo "============================================================" . PHP_EOL;

} catch (\Throwable $e) {
    echo PHP_EOL . "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
