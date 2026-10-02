<?php
/**
 * Migración Idempotente y Backfill de Datos:
 * Reemplazo de Cédula por Correo Electrónico como credencial principal de autenticación.
 * 
 * - Verifica y asigna correos temporales a usuarios/personas sin email.
 * - Detecta y resuelve colisiones de correos electrónicos duplicados.
 * - Normaliza todos los correos a minúsculas.
 * - Elimina la restricción UNIQUE del campo 'cedula' en 'usuarios' (pasa a ser dato demográfico).
 * - Agrega la restricción UNIQUE al campo 'email' en 'usuarios' y 'personas'.
 * 
 * Uso: php scripts/migrate_auth_email.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;

echo "=== Migración de Autenticación: Correo Electrónico como Credencial Principal ===" . PHP_EOL . PHP_EOL;

try {
    $db = Database::getConnection();
    $dbName = DB_NAME;

    // =========================================================================
    // 1. INSPECCIÓN Y BACKFILL EN TABLA 'usuarios'
    // =========================================================================
    echo "[1/4] Procesando tabla 'usuarios'..." . PHP_EOL;

    // 1.1 Asignar correos temporales a usuarios con email vacío o nulo
    $stmtNullUser = $db->query("SELECT id, usuario, cedula FROM usuarios WHERE email IS NULL OR TRIM(email) = ''");
    $usuariosSinEmail = $stmtNullUser->fetchAll(PDO::FETCH_ASSOC);
    $backfilledUsers = 0;

    foreach ($usuariosSinEmail as $u) {
        $tempEmail = !empty($u['cedula']) 
            ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $u['cedula'])) . '@condominio.local'
            : 'usuario_' . $u['id'] . '@condominio.local';

        $upd = $db->prepare("UPDATE usuarios SET email = :email WHERE id = :id");
        $upd->execute(['email' => $tempEmail, 'id' => $u['id']]);
        echo "  ℹ Asignado correo temporal '{$tempEmail}' a usuario ID {$u['id']} ({$u['usuario']})." . PHP_EOL;
        $backfilledUsers++;
    }

    if ($backfilledUsers === 0) {
        echo "  ✔ Todos los usuarios poseen correo electrónico registrado." . PHP_EOL;
    }

    // 1.2 Normalizar a minúsculas
    $db->exec("UPDATE usuarios SET email = LOWER(TRIM(email)) WHERE email IS NOT NULL");

    // 1.3 Detectar y resolver duplicados en 'usuarios'
    $stmtDupUser = $db->query("
        SELECT LOWER(TRIM(email)) as norm_email, COUNT(*) as cnt 
        FROM usuarios 
        GROUP BY LOWER(TRIM(email)) 
        HAVING cnt > 1
    ");
    $duplicadosUser = $stmtDupUser->fetchAll(PDO::FETCH_ASSOC);
    $resolvedUserDups = 0;

    foreach ($duplicadosUser as $dup) {
        $normEmail = $dup['norm_email'];
        $stmtRows = $db->prepare("SELECT id FROM usuarios WHERE LOWER(TRIM(email)) = :email ORDER BY id ASC");
        $stmtRows->execute(['email' => $normEmail]);
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

        // Dejar el primer ID intacto y modificar los demás
        for ($i = 1; $i < count($rows); $i++) {
            $conflictId = $rows[$i]['id'];
            $newEmail = 'dup_' . $conflictId . '_' . $normEmail;
            $upd = $db->prepare("UPDATE usuarios SET email = :email WHERE id = :id");
            $upd->execute(['email' => $newEmail, 'id' => $conflictId]);
            echo "  ⚠️ Resuelta colisión en 'usuarios': ID {$conflictId} actualizado a '{$newEmail}'." . PHP_EOL;
            $resolvedUserDups++;
        }
    }

    if ($resolvedUserDups === 0) {
        echo "  ✔ Sin colisiones de correo electrónico en 'usuarios'." . PHP_EOL;
    }

    // =========================================================================
    // 2. MODIFICACIÓN DE ÍNDICES EN TABLA 'usuarios'
    // =========================================================================
    echo PHP_EOL . "[2/4] Actualizando índices y restricciones en tabla 'usuarios'..." . PHP_EOL;

    // 2.1 Desvincular restricción UNIQUE de la cédula en 'usuarios'
    $stmtCedIdx = $db->query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'usuarios' 
          AND COLUMN_NAME = 'cedula' 
          AND NON_UNIQUE = 0
    ");
    $cedulaIndexes = $stmtCedIdx->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cedulaIndexes as $idx) {
        $idxName = $idx['INDEX_NAME'];
        if ($idxName !== 'PRIMARY') {
            $db->exec("ALTER TABLE usuarios DROP INDEX `{$idxName}`");
            echo "  ✔ Eliminada restricción UNIQUE '{$idxName}' sobre columna 'cedula' en 'usuarios'." . PHP_EOL;
        }
    }
    if (empty($cedulaIndexes)) {
        echo "  ℹ Columna 'cedula' en 'usuarios' ya no posee restricción UNIQUE." . PHP_EOL;
    }

    // Asegurar que la columna 'cedula' sea NULL y sin UNIQUE
    $db->exec("ALTER TABLE usuarios MODIFY COLUMN cedula VARCHAR(20) NULL");

    // 2.2 Agregar restricción UNIQUE al campo 'email' en 'usuarios'
    $stmtEmailIdx = $db->query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'usuarios' 
          AND COLUMN_NAME = 'email' 
          AND NON_UNIQUE = 0
    ");
    $hasEmailUnique = (bool)$stmtEmailIdx->fetchColumn();

    if (!$hasEmailUnique) {
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN email VARCHAR(150) NOT NULL");
        $db->exec("ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_email (email)");
        echo "  ✔ Restricción UNIQUE KEY 'uq_usuarios_email' agregada a tabla 'usuarios'." . PHP_EOL;
    } else {
        echo "  ℹ La columna 'email' en 'usuarios' ya posee restricción de unicidad." . PHP_EOL;
    }

    // =========================================================================
    // 3. INSPECCIÓN Y BACKFILL EN TABLA 'personas'
    // =========================================================================
    echo PHP_EOL . "[3/4] Procesando tabla 'personas'..." . PHP_EOL;

    // 3.1 Asignar correos temporales a personas con email vacío o nulo
    $stmtNullPersona = $db->query("SELECT id, cedula, nombre, apellido FROM personas WHERE email IS NULL OR TRIM(email) = ''");
    $personasSinEmail = $stmtNullPersona->fetchAll(PDO::FETCH_ASSOC);
    $backfilledPersonas = 0;

    foreach ($personasSinEmail as $p) {
        $tempEmail = !empty($p['cedula']) 
            ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $p['cedula'])) . '@condominio.local'
            : 'residente_' . $p['id'] . '@condominio.local';

        $upd = $db->prepare("UPDATE personas SET email = :email WHERE id = :id");
        $upd->execute(['email' => $tempEmail, 'id' => $p['id']]);
        echo "  ℹ Asignado correo temporal '{$tempEmail}' a residente ID {$p['id']} ({$p['nombre']} {$p['apellido']})." . PHP_EOL;
        $backfilledPersonas++;
    }

    if ($backfilledPersonas === 0) {
        echo "  ✔ Todos los residentes poseen correo electrónico registrado." . PHP_EOL;
    }

    // 3.2 Normalizar a minúsculas
    $db->exec("UPDATE personas SET email = LOWER(TRIM(email)) WHERE email IS NOT NULL");

    // 3.3 Detectar y resolver duplicados en 'personas'
    $stmtDupPersona = $db->query("
        SELECT LOWER(TRIM(email)) as norm_email, COUNT(*) as cnt 
        FROM personas 
        GROUP BY LOWER(TRIM(email)) 
        HAVING cnt > 1
    ");
    $duplicadosPersona = $stmtDupPersona->fetchAll(PDO::FETCH_ASSOC);
    $resolvedPersonaDups = 0;

    foreach ($duplicadosPersona as $dup) {
        $normEmail = $dup['norm_email'];
        $stmtRows = $db->prepare("SELECT id FROM personas WHERE LOWER(TRIM(email)) = :email ORDER BY id ASC");
        $stmtRows->execute(['email' => $normEmail]);
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

        // Dejar el primer ID intacto y modificar los demás
        for ($i = 1; $i < count($rows); $i++) {
            $conflictId = $rows[$i]['id'];
            $newEmail = 'dup_' . $conflictId . '_' . $normEmail;
            $upd = $db->prepare("UPDATE personas SET email = :email WHERE id = :id");
            $upd->execute(['email' => $newEmail, 'id' => $conflictId]);
            echo "  ⚠️ Resuelta colisión en 'personas': ID {$conflictId} actualizado a '{$newEmail}'." . PHP_EOL;
            $resolvedPersonaDups++;
        }
    }

    if ($resolvedPersonaDups === 0) {
        echo "  ✔ Sin colisiones de correo electrónico en 'personas'." . PHP_EOL;
    }

    // =========================================================================
    // 4. MODIFICACIÓN DE ÍNDICES EN TABLA 'personas'
    // =========================================================================
    echo PHP_EOL . "[4/4] Actualizando índices y restricciones en tabla 'personas'..." . PHP_EOL;

    $stmtPersonaEmailIdx = $db->query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'personas' 
          AND COLUMN_NAME = 'email' 
          AND NON_UNIQUE = 0
    ");
    $hasPersonaEmailUnique = (bool)$stmtPersonaEmailIdx->fetchColumn();

    if (!$hasPersonaEmailUnique) {
        $db->exec("ALTER TABLE personas MODIFY COLUMN email VARCHAR(150) NOT NULL");
        $db->exec("ALTER TABLE personas ADD UNIQUE KEY uq_personas_email (email)");
        echo "  ✔ Restricción UNIQUE KEY 'uq_personas_email' agregada a tabla 'personas'." . PHP_EOL;
    } else {
        echo "  ℹ La columna 'email' en 'personas' ya posee restricción de unicidad." . PHP_EOL;
    }

    echo PHP_EOL . "============================================================" . PHP_EOL;
    echo "✅ Migración y backfill completados exitosamente." . PHP_EOL;
    echo "   - Usuarios backfilled: {$backfilledUsers}" . PHP_EOL;
    echo "   - Duplicados resueltos en usuarios: {$resolvedUserDups}" . PHP_EOL;
    echo "   - Residentes backfilled: {$backfilledPersonas}" . PHP_EOL;
    echo "   - Duplicados resueltos en residentes: {$resolvedPersonaDups}" . PHP_EOL;
    echo "============================================================" . PHP_EOL;

} catch (\Throwable $e) {
    echo PHP_EOL . "❌ Error en migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
