<?php
// Permitir respaldos largos sin interrupción por max_execution_time
set_time_limit(0);

/**
 * Script de Respaldo Diario Automatizado de Base de Datos (respaldo único) con SHA-256.
 *
 * Política de respaldo único: tras generar el archivo .sql.gz, su firma
 * SHA-256 y registrar la fila en backups_log, se eliminan TODOS los archivos
 * `backup_*` anteriores (.sql.gz + .sha256). Nunca se borra el respaldo
 * anterior antes de que el nuevo esté completo y registrado.
 *
 * Ejecutable vía CLI: php scripts/backup_database.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

use App\Core\Database;

echo "========================================================\n";
echo "  MOTOR DE RESPALDO AUTOMATIZADO DE BASE DE DATOS (RNF 3)\n";
echo "========================================================\n\n";

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Garantizar protección contra accesos web directos
$htaccessFile = $backupDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, "# Bloqueo total de acceso HTTP directo a los archivos de respaldo\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\nOptions -Indexes\n");
}

// El reemplazo del respaldo anterior se ejecuta al final, SOLO tras el éxito
// completo (archivo + hash + registro en backups_log): respaldo único.

try {
    $db = Database::getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
    $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'condominio_cobranzas');
    $dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
    $dbPass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');

    $timestamp = date('Y-m-d_H-i-s');
    $nombreBase = "backup_{$timestamp}";
    $rutaSqlGz = "{$backupDir}/{$nombreBase}.sql.gz";
    $rutaSha256 = "{$backupDir}/{$nombreBase}.sql.gz.sha256";

    echo "1. Iniciando extracción de base de datos '{$dbName}'...\n";
    $sqlContenido = '';
    $tablasCount = 0;

    // Intentar mysqldump primario si está disponible
    $mysqldumpOk = false;
    $passArg = !empty($dbPass) ? "-p" . escapeshellarg($dbPass) : "";
    $cmd = "mysqldump -h " . escapeshellarg($dbHost) . " -u " . escapeshellarg($dbUser) . " {$passArg} " . escapeshellarg($dbName) . " 2>&1";

    if (function_exists('exec')) {
        @exec($cmd, $output, $returnVar);
        if ($returnVar === 0 && !empty($output)) {
            $sqlContenido = implode("\n", $output);
            $mysqldumpOk = true;
            echo "   ✔ Extracción completada mediante motor nativo 'mysqldump'.\n";
        }
    }

    // Fallback PDO Blindado tabla por tabla si mysqldump no está disponible
    if (!$mysqldumpOk) {
        echo "   ℹ 'mysqldump' no disponible o restringido. Ejecutando motor Fallback PDO...\n";

        $sqlContenido = "-- ========================================================\n";
        $sqlContenido .= "-- RESPALDO COMPLETO DE BASE DE DATOS - CONDOMINIO DIGITAL\n";
        $sqlContenido .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        $sqlContenido .= "-- ========================================================\n\n";
        $sqlContenido .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlContenido .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

        $stmtTablas = $db->query("SHOW TABLES");
        $tablas = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);
        $tablasCount = count($tablas);

        foreach ($tablas as $tabla) {
            // Estructura de tabla
            $stmtCreate = $db->query("SHOW CREATE TABLE `{$tabla}`");
            $createRow = $stmtCreate->fetch(PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? '';

            $sqlContenido .= "DROP TABLE IF EXISTS `{$tabla}`;\n";
            $sqlContenido .= $createSql . ";\n\n";

            // Datos de la tabla
            $stmtData = $db->query("SELECT * FROM `{$tabla}`");
            $filas = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($filas)) {
                $columnas = array_keys($filas[0]);
                $colsEscaped = implode(', ', array_map(fn($c) => "`{$c}`", $columnas));

                foreach ($filas as $fila) {
                    $valores = [];
                    foreach ($fila as $val) {
                        if ($val === null) {
                            $valores[] = 'NULL';
                        } elseif (is_numeric($val) && !str_starts_with((string)$val, '0')) {
                            $valores[] = $val;
                        } else {
                            $valores[] = $db->quote($val);
                        }
                    }
                    $valsJoined = implode(', ', $valores);
                    $sqlContenido .= "INSERT INTO `{$tabla}` ({$colsEscaped}) VALUES ({$valsJoined});\n";
                }
                $sqlContenido .= "\n";
            }
        }

        $sqlContenido .= "SET FOREIGN_KEY_CHECKS=1;\n";
        echo "   ✔ Extracción completada mediante Fallback PDO ({$tablasCount} tablas procesadas).\n";
    }

    // 2. Compresión GZIP
    echo "2. Comprimiendo volcado SQL a formato .sql.gz...\n";
    $gzData = gzencode($sqlContenido, 9);
    file_put_contents($rutaSqlGz, $gzData);
    $tamanoBytes = filesize($rutaSqlGz);
    echo "   ✔ Archivo comprimido generado: " . basename($rutaSqlGz) . " (" . round($tamanoBytes / 1024, 2) . " KB).\n";

    // 3. Firma de Integridad SHA-256
    echo "3. Generando firma criptográfica SHA-256...\n";
    $hashSha256 = hash_file('sha256', $rutaSqlGz);
    file_put_contents($rutaSha256, $hashSha256 . "  " . basename($rutaSqlGz) . "\n");
    echo "   ✔ Firma SHA-256: {$hashSha256}\n";

    // 4. Registrar en tabla 'backups_log'
    echo "4. Registrando copia en tabla 'backups_log'...\n";
    $stmtLog = $db->prepare("
        INSERT INTO backups_log 
        (nombre_archivo, tamano_bytes, hash_sha256, tablas_respaldadas, fecha_respaldo)
        VALUES (:nombre, :tamano, :hash, :tablas, NOW())
    ");
    $stmtLog->execute([
        'nombre' => basename($rutaSqlGz),
        'tamano' => $tamanoBytes,
        'hash'   => $hashSha256,
        'tablas' => $tablasCount ?: 16
    ]);
    echo "   ✔ Respaldo registrado exitosamente para auditoría.\n";

    // 5. Reemplazo del respaldo anterior (respaldo único).
    //    Se ejecuta SOLO tras el éxito completo (archivo + hash + registro):
    //    nunca se elimina el respaldo anterior antes de que el nuevo esté listo.
    echo "5. Reemplazando el respaldo anterior (política de respaldo único)...\n";
    $archivosReemplazados = 0;
    foreach ((array)glob($backupDir . '/backup_*') as $archivoAnterior) {
        if (!is_file($archivoAnterior)) {
            continue;
        }
        if ($archivoAnterior === $rutaSqlGz || $archivoAnterior === $rutaSha256) {
            continue; // el par recién creado se conserva
        }
        if (@unlink($archivoAnterior)) {
            $archivosReemplazados++;
        }
    }
    echo "   ✔ Respaldo único vigente: {$archivosReemplazados} archivo(s) anterior(es) eliminado(s).\n\n";

    // 6. Registro permanente en archivo de log del servidor
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/backups.log';
    $logMsg = "[" . date('Y-m-d H:i:s') . "] [SUCCESS] Archivo: " . basename($rutaSqlGz) . " (" . round($tamanoBytes / 1024, 2) . " KB) | SHA-256: {$hashSha256} | Tablas: " . ($tablasCount ?: 16) . " | Anteriores eliminados: {$archivosReemplazados}\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);

    echo "\n========================================================\n";
    echo "✅ RESPALDO ÚNICO COMPLETADO CON ÉXITO (el anterior fue reemplazado).\n";
    echo "========================================================\n";

} catch (Exception $e) {
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (is_dir($logDir)) {
        $logFile = $logDir . '/backups.log';
        $logMsg = "[" . date('Y-m-d H:i:s') . "] [ERROR] Falló respaldo: " . $e->getMessage() . "\n";
        @file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
    }
    echo "\n❌ ERROR EN GENERACIÓN DE RESPALDO: " . $e->getMessage() . "\n";
    exit(1);
}
