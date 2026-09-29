<?php
/**
 * Migración: Prevención de pagos duplicados y doble crédito.
 *
 * OBJETIVO
 * - Resolver duplicados legacy en `pagos` por identidad económica (unidad +
 *   referencia normalizada; si no hay referencia: unidad + fecha + monto).
 * - Agregar `pagos.referencia_norm` (normalización en PHP, fuente única) y la
 *   columna generada `pagos.dup_guard` con índice único `uk_pago_dup_guard`.
 * - Agregar `movimientos_cuenta.referencia_tipo` y `movimientos_cuenta.huella`
 *   con índice único `uk_mov_huella` (idempotencia del libro mayor).
 *
 * POLÍTICA DE LA GUARDIA `dup_guard` (identidad económica)
 * - Libera la identidad (NULL) para filas en estado RECHAZADO (permite volver a
 *   subir el comprobante tras un rechazo) y para filas soft-deleted que NO están
 *   APROBADAS (nunca acreditaron dinero).
 * - Un pago APROBADO conserva su identidad aunque esté soft-deleted: el crédito
 *   original persiste en `movimientos_cuenta` y aprobar un reemplazo duplicaría
 *   el abono. La misma política aplican `PagoModel::crearPago` (pre-check) y las
 *   guardias de aprobación (`PagoModel::cambiarEstado` / `aprobarLote`).
 * - Si no hay referencia, la identidad cae a unidad + fecha_pago + monto
 *   (trade-off documentado: dos pagos legítimos iguales sin referencia en la
 *   misma fecha serían bloqueados; se prefirió el resguardo económico).
 * - No se backfillea `huella` en movimientos históricos: pueden contener
 *   duplicados legítimos de daño previo; la aplicación aplica solo a inserts
 *   nuevos y este script reporta los duplicados de `pagos`.
 * - El índice antiguo no-único `idx_pago_duplicado` se deja intacto (inofensivo).
 *
 * ORDEN DE DESPLIEGUE (IMPORTANTE)
 *   1) Congelar escrituras sobre `pagos` (ventana de mantenimiento).
 *   2) Ejecutar esta migración (--apply) ANTES de desplegar el código que
 *      escribe `pagos.referencia_norm` y `movimientos_cuenta.huella`.
 *      El código nuevo falla si la migración no se aplicó primero.
 *   3) Desplegar el código nuevo y recién entonces descongelar escrituras.
 *   - Si NO es posible congelar escrituras: las filas insertadas por el código
 *     anterior durante la ventana quedan con `referencia_norm` NULL (ese código
 *     no conoce la columna) y su identidad `R|...` no queda cubierta. En ese
 *     caso re-ejecute esta migración (idempotente) tras desplegar el código
 *     nuevo para completar el backfill; la sección 5 del reporte lista las
 *     filas pendientes.
 *
 * APLICACIÓN IRREVERSIBLE (--apply)
 * - Los auto-rechazos (PENDIENTE/EN REVISIÓN → RECHAZADO) son DEFINITIVOS:
 *   RECHAZADO es terminal y el rollback SQL documentado NO los revierte.
 * - Por eso `--apply` pide confirmación interactiva POR GRUPO antes de aplicar
 *   cualquier cambio. Si un grupo se declina o queda pendiente de revisión
 *   manual, la migración NO aplica ningún cambio y sale con código 1.
 * - `--yes` omite las confirmaciones (usar solo en entornos controlados).
 *   Sin TTY y sin `--yes`, el script aborta si hay rechazos pendientes.
 *
 * ROLLBACK MANUAL
 *   ALTER TABLE pagos DROP INDEX uk_pago_dup_guard;
 *   ALTER TABLE pagos DROP COLUMN dup_guard;
 *   ALTER TABLE pagos DROP COLUMN referencia_norm;
 *   ALTER TABLE movimientos_cuenta DROP INDEX uk_mov_huella;
 *   ALTER TABLE movimientos_cuenta DROP COLUMN huella;
 *   ALTER TABLE movimientos_cuenta DROP COLUMN referencia_tipo;
 *   NOTA: los duplicados legacy auto-rechazados por esta migración NO se
 *   revierten con el rollback y deben revisarse manualmente.
 *
 * REQUISITOS
 * - MySQL >= 5.7 o MariaDB >= 10.2 (columnas generadas STORED).
 * - Si la versión no cumple, el script aborta con mensaje claro.
 *
 * USO (CLI, desde la raíz del repo)
 *   php scripts/migrate_pago_duplicados.php                # dry-run (solo reporte)
 *   php scripts/migrate_pago_duplicados.php --dry-run
 *   php scripts/migrate_pago_duplicados.php --apply        # aplica con confirmación por grupo
 *   php scripts/migrate_pago_duplicados.php --apply --yes  # sin confirmaciones (controlado)
 *
 * IDEMPOTENTE: puede re-ejecutarse sin efectos secundarios.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Database;
use App\Models\PagoModel;

$apply = in_array('--apply', $argv, true);
$assumeYes = in_array('--yes', $argv, true);
$dryRun = !$apply;

/**
 * Indica si el motor soporta columnas generadas STORED (MySQL >= 5.7 / MariaDB >= 10.2).
 */
function versionSoportaColumnasGeneradas(string $version): bool {
    $numerica = preg_replace('/[^0-9.].*$/', '', trim($version));
    if ($numerica === null || $numerica === '') {
        return false;
    }
    if (stripos($version, 'mariadb') !== false) {
        return version_compare($numerica, '10.2', '>=');
    }
    return version_compare($numerica, '5.7', '>=');
}

function columnaExiste(PDO $db, string $tabla, string $columna): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
    $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
    return intval($stmt->fetchColumn()) > 0;
}

function indiceExiste(PDO $db, string $tabla, string $indice): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND INDEX_NAME = :indice");
    $stmt->execute(['tabla' => $tabla, 'indice' => $indice]);
    return intval($stmt->fetchColumn()) > 0;
}

function expresionGeneradaColumna(PDO $db, string $tabla, string $columna): ?string {
    $stmt = $db->prepare("SELECT GENERATION_EXPRESSION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
    $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
    $expr = $stmt->fetchColumn();
    return ($expr === false || $expr === null) ? null : (string)$expr;
}

/**
 * Indica si la expresión almacenada de dup_guard retiene la identidad de los
 * APROBADOS soft-deleted (política vigente). Compara sobre la expresión
 * normalizada (sin backticks, paréntesis ni espacios) para ser robusto ante
 * el reformateo canónico del motor: un falso negativo solo provoca un
 * MODIFY COLUMN idempotente.
 */
function expresionDupGuardVigente(?string $expresion): bool {
    if ($expresion === null || $expresion === '') {
        return false;
    }
    $normalizada = strtolower(preg_replace('/[`()\s]+/', '', $expresion));
    return str_contains($normalizada, 'deleted_atisnotnulland');
}

/**
 * Retorna los ids de pagos con referencia normalizable pero `referencia_norm`
 * NULL: filas escritas por el código anterior durante la ventana de despliegue
 * que quedaron fuera del backfill.
 *
 * @return int[]
 */
function filasPendientesBackfill(PDO $db): array {
    $stmt = $db->query("SELECT id, referencia FROM pagos WHERE referencia_norm IS NULL");
    $pendientes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        if (PagoModel::normalizarReferenciaPago($fila['referencia']) !== null) {
            $pendientes[] = intval($fila['id']);
        }
    }
    return $pendientes;
}

/**
 * Agrupa los pagos no rechazados por identidad económica.
 *
 * Incluye APROBADOS soft-deleted: conservan su identidad económica porque el
 * crédito original persiste, por lo que también deben bloquear el índice único.
 * Los soft-deleted no aprobados sí se excluyen (liberan la identidad).
 *
 * @return array{total_filas:int, grupos:array<int,array>}
 */
function analizarDuplicadosPagos(PDO $db): array {
    $stmt = $db->query("SELECT id, unidad_id, referencia, fecha_pago, monto, estado, deleted_at
                        FROM pagos
                        WHERE estado != 'RECHAZADO' AND (deleted_at IS NULL OR estado = 'APROBADO')
                        ORDER BY id ASC");
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grupos = [];
    foreach ($filas as $fila) {
        $refNorm = PagoModel::normalizarReferenciaPago($fila['referencia']);
        if ($refNorm !== null) {
            $clave = 'R|' . intval($fila['unidad_id']) . '|' . $refNorm;
        } else {
            $clave = 'S|' . intval($fila['unidad_id']) . '|' . $fila['fecha_pago'] . '|' . number_format(floatval($fila['monto']), 2, '.', '');
        }
        $grupos[$clave][] = $fila;
    }

    $reporte = ['total_filas' => count($filas), 'grupos' => []];
    foreach ($grupos as $clave => $miembros) {
        if (count($miembros) < 2) {
            continue;
        }

        usort($miembros, fn($a, $b) => intval($a['id']) <=> intval($b['id']));

        $tieneSoftDeleted = false;
        foreach ($miembros as $miembro) {
            if ($miembro['deleted_at'] !== null) {
                $tieneSoftDeleted = true;
                break;
            }
        }

        $rechazar = [];
        $restantes = [];
        if ($tieneSoftDeleted) {
            // Los grupos con filas soft-deleted requieren revisión manual:
            // nunca se resuelven con rechazos automáticos.
            $restantes = $miembros;
        } else {
            foreach ($miembros as $indice => $miembro) {
                $estado = strtoupper(trim((string)$miembro['estado']));
                if ($indice > 0 && ($estado === 'PENDIENTE' || $estado === 'EN REVISIÓN')) {
                    $rechazar[] = $miembro;
                } else {
                    $restantes[] = $miembro;
                }
            }
        }

        $reporte['grupos'][] = [
            'clave'              => $clave,
            'miembros'           => $miembros,
            'conservar'          => $miembros[0],
            'rechazar'           => $rechazar,
            'restantes'          => $restantes,
            'tiene_soft_deleted' => $tieneSoftDeleted,
            'no_resuelto'        => $tieneSoftDeleted || count($restantes) >= 2,
        ];
    }

    return $reporte;
}

echo "========================================================\n";
echo "  MIGRACIÓN: PREVENCIÓN DE PAGOS DUPLICADOS\n";
echo "========================================================\n";
echo "  Modo: " . ($dryRun ? 'DRY-RUN (solo reporte)' : 'APPLY (ejecuta cambios)') . "\n\n";

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ----------------------------------------------------------------
    // 0. Verificación de versión del motor
    // ----------------------------------------------------------------
    $version = (string)$db->query("SELECT VERSION()")->fetchColumn();
    echo "0. Motor de base de datos: {$version}\n";
    if (!versionSoportaColumnasGeneradas($version)) {
        echo "❌ ERROR: se requiere MySQL >= 5.7 o MariaDB >= 10.2 para columnas generadas STORED.\n";
        exit(1);
    }
    echo "   ✔ Versión compatible con columnas generadas STORED.\n\n";

    // ----------------------------------------------------------------
    // 1. Duplicados legacy en pagos
    // ----------------------------------------------------------------
    echo "1. Analizando duplicados legacy en 'pagos' (excluye RECHAZADO; incluye APROBADO soft-deleted)...\n";
    $reporte = analizarDuplicadosPagos($db);
    echo "   ℹ Filas analizadas: {$reporte['total_filas']}. Grupos duplicados: " . count($reporte['grupos']) . ".\n";

    $totalARechazar = 0;
    $gruposManuales = [];
    $gruposConfirmados = [];
    $gruposDeclinados = [];

    // Confirmación interactiva por grupo antes de rechazos irreversibles.
    $requiereConfirmacion = false;
    foreach ($reporte['grupos'] as $grupo) {
        if (!$grupo['no_resuelto'] && !empty($grupo['rechazar'])) {
            $requiereConfirmacion = true;
            break;
        }
    }
    if ($apply && !$assumeYes && $requiereConfirmacion && !stream_isatty(STDIN)) {
        echo "❌ ABORTADO: --apply requiere confirmación interactiva por grupo cuando hay auto-rechazos.\n";
        echo "   Sin TTY no es posible confirmar. Ejecute en una terminal interactiva\n";
        echo "   o use --yes explícitamente en un entorno controlado. No se aplicó ningún cambio.\n";
        exit(1);
    }

    foreach ($reporte['grupos'] as $grupo) {
        $ids = array_map(
            fn($m) => '#' . intval($m['id']) . '(' . strtoupper(trim((string)$m['estado'])) . ($m['deleted_at'] !== null ? ', soft-deleted' : '') . ')',
            $grupo['miembros']
        );
        echo "   • Identidad {$grupo['clave']}: " . implode(', ', $ids)
            . " → conservar #" . intval($grupo['conservar']['id']) . "\n";

        if ($grupo['no_resuelto']) {
            $gruposManuales[] = $grupo;
            echo "     ↳ Requiere revisión manual: la migración no lo toca automáticamente.\n";
            continue;
        }

        if (empty($grupo['rechazar'])) {
            continue;
        }

        $idsRechazar = array_map(fn($m) => '#' . intval($m['id']), $grupo['rechazar']);
        echo "     ↳ Candidatos a auto-rechazo (PENDIENTE/EN REVISIÓN): " . implode(', ', $idsRechazar) . "\n";

        if ($dryRun) {
            $totalARechazar += count($grupo['rechazar']);
            continue;
        }

        if ($assumeYes) {
            $gruposConfirmados[] = $grupo;
            $totalARechazar += count($grupo['rechazar']);
            continue;
        }

        echo "     ¿Confirmar el rechazo definitivo de " . implode(', ', $idsRechazar) . "? [s/N]: ";
        $respuesta = strtolower(trim((string)fgets(STDIN)));
        if (in_array($respuesta, ['s', 'si', 'sí', 'y', 'yes'], true)) {
            $gruposConfirmados[] = $grupo;
            $totalARechazar += count($grupo['rechazar']);
        } else {
            $gruposDeclinados[] = $grupo;
            echo "     ↳ NO confirmado: el grupo queda sin resolver.\n";
        }
    }

    if ($dryRun) {
        if ($totalARechazar > 0) {
            echo "   ℹ Se rechazarían {$totalARechazar} fila(s) PENDIENTE/EN REVISIÓN duplicadas (dry-run, sin cambios).\n";
        } else {
            echo "   ✔ No hay filas PENDIENTE/EN REVISIÓN duplicadas por auto-rechazar.\n";
        }
    }

    // Compuerta previa a cualquier mutación: sin cambios si hay grupos declinados
    // o pendientes de revisión manual (los rechazos son irreversibles).
    if (!empty($gruposDeclinados)) {
        echo "\n❌ ABORTADO: " . count($gruposDeclinados) . " grupo(s) sin confirmar para auto-rechazo.\n";
        echo "   No se aplicó ningún cambio. Re-ejecute y confirme todos los grupos cuando esté preparado.\n";
        exit(1);
    }

    if (!empty($gruposManuales)) {
        echo "\n❌ ABORTADO: existen " . count($gruposManuales) . " grupo(s) que requieren revisión manual.\n";
        echo "   Estos grupos NO se tocan automáticamente (p. ej. APROBADOS soft-deleted o >= 2 filas no rechazables):\n";
        foreach ($gruposManuales as $grupo) {
            $detalle = array_map(
                fn($m) => '#' . intval($m['id']) . '(estado=' . strtoupper(trim((string)$m['estado'])) . ($m['deleted_at'] !== null ? ', soft-deleted=' . $m['deleted_at'] : '') . ', monto=' . $m['monto'] . ', fecha=' . $m['fecha_pago'] . ')',
                $grupo['restantes']
            );
            echo "   • {$grupo['clave']}: " . implode(', ', $detalle) . "\n";
        }
        echo "   No se aplicó ningún cambio ni se creó el índice único.\n";
        echo "   Resuelva manualmente estos grupos y re-ejecute la migración.\n";
        exit(1);
    }

    // Aplicar auto-rechazos confirmados (irreversibles, auditados uno a uno).
    $autoRechazados = 0;
    foreach ($gruposConfirmados as $grupo) {
        foreach ($grupo['rechazar'] as $miembro) {
            $estadoAnterior = strtoupper(trim((string)$miembro['estado']));
            $upd = $db->prepare("UPDATE pagos SET estado = 'RECHAZADO' WHERE id = :id AND estado = :esperado");
            $upd->execute(['id' => intval($miembro['id']), 'esperado' => $miembro['estado']]);
            if ($upd->rowCount() !== 1) {
                echo "     ⚠ No se pudo rechazar el pago #" . intval($miembro['id']) . " (estado cambió).\n";
                continue;
            }

            $insLog = $db->prepare("INSERT INTO log_auditoria (pago_id, admin_id, estado_anterior, estado_nuevo, motivo, accion)
                                    VALUES (:pago_id, NULL, :estado_anterior, 'RECHAZADO', :motivo, 'migracion_duplicados')");
            $insLog->execute([
                'pago_id'         => intval($miembro['id']),
                'estado_anterior' => $estadoAnterior,
                'motivo'          => "Rechazo automático por migración de duplicados: identidad {$grupo['clave']} compartida con el pago #" . intval($grupo['conservar']['id']) . ".",
            ]);
            $autoRechazados++;
        }
    }

    if (!$dryRun) {
        if ($totalARechazar > 0) {
            echo "   ✔ Rechazados automáticamente: {$autoRechazados} de {$totalARechazar} planificados (cada grupo fue confirmado).\n";
        } else {
            echo "   ✔ No hay filas PENDIENTE/EN REVISIÓN duplicadas por auto-rechazar.\n";
        }
    }

    // ----------------------------------------------------------------
    // 2. Columna pagos.referencia_norm + backfill
    // ----------------------------------------------------------------
    echo "\n2. Columna 'pagos.referencia_norm'...\n";
    if ($dryRun) {
        echo columnaExiste($db, 'pagos', 'referencia_norm')
            ? "   ℹ Ya existe. El backfill se ejecutaría en modo --apply.\n"
            : "   ℹ No existe: se crearía y se backfillearía en modo --apply.\n";
    } else {
        if (!columnaExiste($db, 'pagos', 'referencia_norm')) {
            $db->exec("ALTER TABLE pagos ADD COLUMN referencia_norm VARCHAR(100) NULL AFTER referencia");
            echo "   ✔ Columna 'pagos.referencia_norm' agregada.\n";
        } else {
            echo "   ℹ Columna 'pagos.referencia_norm' ya existe.\n";
        }

        $stmtPagos = $db->query("SELECT id, referencia FROM pagos");
        $stmtBackfill = $db->prepare("UPDATE pagos SET referencia_norm = :referencia_norm WHERE id = :id");
        $backfilled = 0;
        foreach ($stmtPagos->fetchAll(PDO::FETCH_ASSOC) as $pago) {
            $stmtBackfill->execute([
                'referencia_norm' => PagoModel::normalizarReferenciaPago($pago['referencia']),
                'id'              => intval($pago['id']),
            ]);
            $backfilled++;
        }
        echo "   ✔ Backfill de 'referencia_norm' aplicado a {$backfilled} fila(s).\n";
    }

    // ----------------------------------------------------------------
    // 3. Columna generada pagos.dup_guard + índice único
    // ----------------------------------------------------------------
    echo "\n3. Guardia única 'pagos.dup_guard'...\n";
    // Nota de compatibilidad: MariaDB (<= 10.4 comprobado) rechaza DATE_FORMAT()
    // en columnas generadas (error 1901). La concatenación directa de la columna
    // DATE produce exactamente 'YYYY-MM-DD', por lo que la identidad es idéntica
    // a la expresión especificada con DATE_FORMAT('%Y-%m-%d').
    $exprDupGuard = "IF(`estado` = 'RECHAZADO' OR (`deleted_at` IS NOT NULL AND `estado` <> 'APROBADO'), NULL, IF(`referencia_norm` IS NULL OR `referencia_norm` = '', CONCAT('S|', `unidad_id`, '|', `fecha_pago`, '|', `monto`), CONCAT('R|', `unidad_id`, '|', `referencia_norm`)))";

    if ($dryRun) {
        if (!columnaExiste($db, 'pagos', 'dup_guard')) {
            echo "   ℹ No existe: se crearía la columna generada STORED.\n";
        } elseif (!expresionDupGuardVigente(expresionGeneradaColumna($db, 'pagos', 'dup_guard'))) {
            echo "   ℹ Existe con una expresión anterior: se actualizaría a la política vigente en --apply.\n";
        } else {
            echo "   ℹ Columna generada ya existe con la política vigente.\n";
        }
        echo indiceExiste($db, 'pagos', 'uk_pago_dup_guard')
            ? "   ℹ Índice único 'uk_pago_dup_guard' ya existe.\n"
            : "   ℹ No existe: se crearía el índice único 'uk_pago_dup_guard'.\n";
    } else {
        if (!columnaExiste($db, 'pagos', 'dup_guard')) {
            $db->exec("ALTER TABLE pagos ADD COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS ({$exprDupGuard}) STORED");
            echo "   ✔ Columna generada 'pagos.dup_guard' agregada.\n";
        } elseif (!expresionDupGuardVigente(expresionGeneradaColumna($db, 'pagos', 'dup_guard'))) {
            $db->exec("ALTER TABLE pagos MODIFY COLUMN dup_guard VARCHAR(191) GENERATED ALWAYS AS ({$exprDupGuard}) STORED");
            echo "   ✔ Expresión de 'pagos.dup_guard' actualizada a la política vigente.\n";
        } else {
            echo "   ℹ Columna generada 'pagos.dup_guard' ya existe con la política vigente.\n";
        }

        if (!indiceExiste($db, 'pagos', 'uk_pago_dup_guard')) {
            $db->exec("ALTER TABLE pagos ADD UNIQUE KEY uk_pago_dup_guard (dup_guard)");
            echo "   ✔ Índice único 'uk_pago_dup_guard' creado.\n";
        } else {
            echo "   ℹ Índice único 'uk_pago_dup_guard' ya existe.\n";
        }
    }

    // ----------------------------------------------------------------
    // 4. Idempotencia en movimientos_cuenta
    // ----------------------------------------------------------------
    echo "\n4. Idempotencia en 'movimientos_cuenta' (referencia_tipo + huella)...\n";
    if ($dryRun) {
        echo columnaExiste($db, 'movimientos_cuenta', 'referencia_tipo')
            ? "   ℹ Columna 'referencia_tipo' ya existe.\n"
            : "   ℹ No existe: se crearía 'referencia_tipo'.\n";
        echo columnaExiste($db, 'movimientos_cuenta', 'huella')
            ? "   ℹ Columna 'huella' ya existe.\n"
            : "   ℹ No existe: se crearía 'huella'.\n";
        echo indiceExiste($db, 'movimientos_cuenta', 'uk_mov_huella')
            ? "   ℹ Índice único 'uk_mov_huella' ya existe.\n"
            : "   ℹ No existe: se crearía el índice único 'uk_mov_huella'.\n";
        echo "   ℹ Sin backfill histórico de 'huella' (decisión de diseño D5).\n";
    } else {
        if (!columnaExiste($db, 'movimientos_cuenta', 'referencia_tipo')) {
            $db->exec("ALTER TABLE movimientos_cuenta ADD COLUMN referencia_tipo VARCHAR(20) NULL AFTER referencia_id");
            echo "   ✔ Columna 'movimientos_cuenta.referencia_tipo' agregada.\n";
        } else {
            echo "   ℹ Columna 'movimientos_cuenta.referencia_tipo' ya existe.\n";
        }

        if (!columnaExiste($db, 'movimientos_cuenta', 'huella')) {
            $db->exec("ALTER TABLE movimientos_cuenta ADD COLUMN huella CHAR(64) NULL AFTER referencia_tipo");
            echo "   ✔ Columna 'movimientos_cuenta.huella' agregada.\n";
        } else {
            echo "   ℹ Columna 'movimientos_cuenta.huella' ya existe.\n";
        }

        if (!indiceExiste($db, 'movimientos_cuenta', 'uk_mov_huella')) {
            $db->exec("ALTER TABLE movimientos_cuenta ADD UNIQUE KEY uk_mov_huella (huella)");
            echo "   ✔ Índice único 'uk_mov_huella' creado.\n";
        } else {
            echo "   ℹ Índice único 'uk_mov_huella' ya existe.\n";
        }
        echo "   ℹ Sin backfill histórico de 'huella' (decisión de diseño D5).\n";
    }

    // ----------------------------------------------------------------
    // 5. Control de filas pendientes de backfill (ventana de despliegue)
    // ----------------------------------------------------------------
    echo "\n5. Control de filas pendientes de backfill (ventana de despliegue)...\n";
    if (!columnaExiste($db, 'pagos', 'referencia_norm')) {
        echo "   ℹ La columna 'referencia_norm' no existe todavía: el backfill completo se ejecutará en --apply.\n";
    } else {
        $pendientes = filasPendientesBackfill($db);
        if (empty($pendientes)) {
            echo "   ✔ Sin filas pendientes de backfill: toda referencia normalizable está cubierta.\n";
        } else {
            $muestra = array_slice($pendientes, 0, 10);
            echo "   ⚠ ADVERTENCIA: " . count($pendientes) . " fila(s) con referencia válida y referencia_norm NULL (ids: #" . implode(', #', $muestra) . (count($pendientes) > 10 ? ', …' : '') . ").\n";
            echo "     Indica escrituras del código anterior durante la ventana de migración.\n";
            echo "     Re-ejecute esta migración (idempotente) después de desplegar el código nuevo\n";
            echo "     o con escrituras congeladas para completar el backfill y la protección.\n";
        }
    }

    echo "\n========================================================\n";
    if ($dryRun) {
        echo "ℹ DRY-RUN COMPLETADO: no se aplicaron cambios.\n";
        echo "  Ejecute con --apply para crear columnas, índice único y resolver duplicados.\n";
    } else {
        echo "✅ MIGRACIÓN COMPLETADA CON ÉXITO.\n";
    }
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN: " . $e->getMessage() . "\n";
    exit(1);
}
