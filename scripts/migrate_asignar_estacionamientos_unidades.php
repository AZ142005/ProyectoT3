<?php
/**
 * Migración y Backfill de Datos: Asignación automática de puestos de estacionamiento a unidades existentes.
 *
 * Recorre todas las unidades registradas y les genera/asigna un puesto de estacionamiento
 * con la nomenclatura "Puesto - [Número de Apartamento]".
 *
 * Reglas de integridad:
 * 1. Idempotente: Si se reejecuta, no genera puestos duplicados.
 * 2. Prevención de Duplicados: Si una unidad ya tiene asignado un puesto (manualmente o previamente),
 *    se omite para no duplicar puestos.
 * 3. Enlace inteligente: Si existe un puesto libre con el nombre objetivo, lo vincula a la unidad.
 * 4. Ampliación de esquema: Asegura que estacionamientos.numero tenga longitud adecuada VARCHAR(50).
 *
 * Uso: php scripts/migrate_asignar_estacionamientos_unidades.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=====================================================================" . PHP_EOL;
echo "  MIGRACIÓN: ASIGNACIÓN AUTOMÁTICA DE ESTACIONAMIENTOS A UNIDADES   " . PHP_EOL;
echo "=====================================================================" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();

    // 1. Verificar existencia de tabla estacionamientos
    $stmtTabla = $db->query("SHOW TABLES LIKE 'estacionamientos'");
    if (!$stmtTabla->fetch()) {
        echo "❌ Error: La tabla 'estacionamientos' no existe. Ejecute primero migrate_fase1.php." . PHP_EOL;
        exit(1);
    }

    // 2. Asegurar que la columna numero soporte nombres descriptivos (VARCHAR 50)
    echo "1. Verificando esquema de columna 'estacionamientos.numero'..." . PHP_EOL;
    $db->exec("ALTER TABLE estacionamientos MODIFY COLUMN numero VARCHAR(50) NOT NULL;");
    echo "  ✔ Columna 'numero' ajustada a VARCHAR(50) correctamente." . PHP_EOL . PHP_EOL;

    // 3. Obtener todas las unidades registradas
    echo "2. Consultando unidades existentes..." . PHP_EOL;
    $stmtUnidades = $db->query("
        SELECT u.id, u.numero, u.edificio_id, e.nombre AS edificio_nombre
        FROM unidades u
        LEFT JOIN edificios e ON u.edificio_id = e.id
        ORDER BY u.id ASC
    ");
    $unidades = $stmtUnidades->fetchAll(PDO::FETCH_ASSOC);
    $totalUnidades = count($unidades);

    echo "  ✔ Se encontraron {$totalUnidades} unidades en el sistema." . PHP_EOL . PHP_EOL;

    if ($totalUnidades === 0) {
        echo "ℹ No hay unidades registradas para migrar." . PHP_EOL;
        exit(0);
    }

    echo "3. Procesando asignación y homologación de puestos..." . PHP_EOL;

    // Sentencias preparadas reutilizables
    $stmtCheckAsignado = $db->prepare("
        SELECT id, numero 
        FROM estacionamientos 
        WHERE unidad_id = :unidad_id AND (deleted_at IS NULL OR deleted_at = '')
        LIMIT 1
    ");

    $stmtCheckPorNombre = $db->prepare("
        SELECT id, unidad_id 
        FROM estacionamientos 
        WHERE numero = :numero AND (deleted_at IS NULL OR deleted_at = '')
        LIMIT 1
    ");

    $stmtVincular = $db->prepare("
        UPDATE estacionamientos 
        SET unidad_id = :unidad_id, edificio_id = :edificio_id 
        WHERE id = :id
    ");

    $stmtCrear = $db->prepare("
        INSERT INTO estacionamientos (numero, tipo, edificio_id, unidad_id, estado)
        VALUES (:numero, 'descubierto', :edificio_id, :unidad_id, 1)
    ");

    $omitidos = 0;
    $vinculadosExistentes = 0;
    $creadosNuevos = 0;

    $db->beginTransaction();

    foreach ($unidades as $u) {
        $unidadId = (int)$u['id'];
        $unidadNumero = trim($u['numero']);
        $edificioId = !empty($u['edificio_id']) ? (int)$u['edificio_id'] : null;

        // Validar si la unidad ya tiene un puesto asignado (prevención estricta de duplicados)
        $stmtCheckAsignado->execute(['unidad_id' => $unidadId]);
        $puestoAsignado = $stmtCheckAsignado->fetch(PDO::FETCH_ASSOC);

        if ($puestoAsignado) {
            $omitidos++;
            echo "  [OMITIDO] Unidad '{$unidadNumero}' ya tiene asignado el puesto '{$puestoAsignado['numero']}' (ID: {$puestoAsignado['id']})." . PHP_EOL;
            continue;
        }

        // Nomenclatura automática solicitada: "Puesto - [Número de Apartamento]"
        $targetNumero = "Puesto - " . $unidadNumero;

        // Validar si ya existe un puesto con ese identificador
        $stmtCheckPorNombre->execute(['numero' => $targetNumero]);
        $puestoExistente = $stmtCheckPorNombre->fetch(PDO::FETCH_ASSOC);

        if ($puestoExistente) {
            if (empty($puestoExistente['unidad_id'])) {
                // El puesto existe y está libre: vincularlo
                $stmtVincular->execute([
                    'unidad_id'   => $unidadId,
                    'edificio_id' => $edificioId,
                    'id'          => $puestoExistente['id']
                ]);
                $vinculadosExistentes++;
                echo "  [VINCULADO] Puesto existente '{$targetNumero}' (ID: {$puestoExistente['id']}) asignado a unidad '{$unidadNumero}'." . PHP_EOL;
            } else {
                // El puesto con ese nombre exacto ya está ocupado por otra unidad; generar con sufijo único
                $targetNumeroAlt = $targetNumero . '-' . $unidadId;
                $stmtCrear->execute([
                    'numero'      => $targetNumeroAlt,
                    'edificio_id' => $edificioId,
                    'unidad_id'   => $unidadId
                ]);
                $creadosNuevos++;
                echo "  [CREADO (SUFIJO)] Puesto '{$targetNumeroAlt}' creado y asignado a unidad '{$unidadNumero}'." . PHP_EOL;
            }
        } else {
            // Crear nuevo puesto de estacionamiento
            $stmtCrear->execute([
                'numero'      => $targetNumero,
                'edificio_id' => $edificioId,
                'unidad_id'   => $unidadId
            ]);
            $creadosNuevos++;
            echo "  [CREADO] Puesto '{$targetNumero}' creado y asignado a unidad '{$unidadNumero}'." . PHP_EOL;
        }
    }

    $db->commit();

    echo PHP_EOL . "=====================================================================" . PHP_EOL;
    echo "  RESUMEN DE MIGRACIÓN:                                              " . PHP_EOL;
    echo "=====================================================================" . PHP_EOL;
    echo "  • Total unidades analizadas:              {$totalUnidades}" . PHP_EOL;
    echo "  • Omitidas (ya poseían puesto):            {$omitidos}" . PHP_EOL;
    echo "  • Puestos existentes vinculados:           {$vinculadosExistentes}" . PHP_EOL;
    echo "  • Nuevos puestos creados y asignados:      {$creadosNuevos}" . PHP_EOL;
    echo "=====================================================================" . PHP_EOL;
    echo "✅ Migración completada con éxito." . PHP_EOL;
    exit(0);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo PHP_EOL . "❌ ERROR CRÍTICO EN LA MIGRACIÓN: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
