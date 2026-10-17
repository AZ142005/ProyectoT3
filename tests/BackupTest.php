<?php
namespace Tests;

/**
 * Tests del motor de respaldo único:
 *  - directorio y scripts base;
 *  - política de reemplazo (borrar los `backup_*` anteriores SOLO tras
 *    registrar el nuevo respaldo; sin limpieza preventiva por antigüedad);
 *  - limpieza standalone del respaldo general descargado-vencido (7 días).
 */
class BackupTest extends TestCase {

    /** Conexión a BD o null (patrón skip del repo). */
    private function conectar(): ?\PDO {
        try {
            return \App\Core\Database::getConnection();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function testStorageBackupsDirectoryExistsAndIsWritable() {
        $backupDir = dirname(__DIR__) . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $this->assertTrue(is_dir($backupDir));
        $this->assertTrue(is_writable($backupDir));
    }

    public function testBackupScriptsExist() {
        $this->assertTrue(file_exists(dirname(__DIR__) . '/scripts/backup_database.php'));
        $this->assertTrue(file_exists(dirname(__DIR__) . '/scripts/cleanup_backups.php'));
    }

    public function testBackupDatabaseReemplazaSoloTrasRegistrarElNuevo() {
        $script = (string)file_get_contents(dirname(__DIR__) . '/scripts/backup_database.php');

        $this->assertStringContains('respaldo único', $script, 'el script debe documentar la política de respaldo único');
        $this->assertStringContains("glob(\$backupDir . '/backup_*')", $script, 'el reemplazo debe barrer los archivos backup_* anteriores');
        $this->assertFalse(str_contains($script, '7 * 86400'), 'ya no debe existir la limpieza preventiva por antigüedad (rotación de 7 días)');

        $posRegistro = strpos($script, 'INSERT INTO backups_log');
        $posReemplazo = strpos($script, "glob(\$backupDir . '/backup_*')");
        $this->assertTrue($posRegistro !== false, 'el script debe registrar el respaldo en backups_log');
        $this->assertTrue($posReemplazo !== false, 'el script debe reemplazar los backup_* anteriores');
        if ($posRegistro !== false && $posReemplazo !== false) {
            $this->assertTrue(
                $posReemplazo > $posRegistro,
                'el reemplazo debe ejecutarse SOLO después de registrar el nuevo respaldo (nunca borrar el anterior antes de que el nuevo esté completo)'
            );
        }

        // Guardas de integridad del archivo nuevo ANTES del bloque de borrado:
        // escritura completada, archivo con tamaño > 0 y firma SHA-256 válida.
        $posEscritura = strpos($script, '$bytesEscritos = file_put_contents($rutaSqlGz, $gzData);');
        $posGuardaEscritura = strpos($script, 'if ($bytesEscritos === false)');
        $posGuardaTamano = strpos($script, 'filesize($rutaSqlGz) <= 0');
        $posGuardaHash = strpos($script, "if (\$hashSha256 === false || \$hashSha256 === '')");
        $this->assertTrue($posEscritura !== false, 'la escritura del .sql.gz debe capturar su resultado');
        $this->assertTrue($posGuardaEscritura !== false, 'la escritura fallida debe abortar el respaldo');
        $this->assertTrue($posGuardaTamano !== false, 'un archivo vacío o inexistente debe abortar el respaldo');
        $this->assertTrue($posGuardaHash !== false, 'una firma SHA-256 inválida debe abortar el respaldo');
        if ($posEscritura !== false && $posGuardaEscritura !== false && $posGuardaTamano !== false && $posGuardaHash !== false && $posReemplazo !== false) {
            $this->assertTrue(
                $posEscritura < $posGuardaEscritura && $posGuardaEscritura < $posReemplazo,
                'la guarda de escritura debe ejecutarse antes del reemplazo'
            );
            $this->assertTrue($posGuardaTamano < $posReemplazo, 'la guarda de tamaño debe ejecutarse antes del reemplazo');
            $this->assertTrue($posGuardaHash < $posReemplazo, 'la guarda de firma SHA-256 debe ejecutarse antes del reemplazo');
        }
        $this->assertStringContains('fallarRespaldo(', $script, 'las guardas deben abortar el respaldo sin reemplazar');
        $this->assertStringContains('[ERROR]', $script, 'el fallo de guarda debe registrarse como ERROR en el log de respaldos');
    }

    public function testCleanupBackupsEliminaDescargadoVencidoYConservaReciente() {
        $db = $this->conectar();
        if ($db === null) {
            $this->skip('BD no disponible para la limpieza standalone de respaldos');
            return;
        }
        $col = $db->query("SHOW COLUMNS FROM backups_log LIKE 'fecha_descarga'")->fetch();
        if (!$col) {
            $this->skip('Migración fecha_descarga pendiente en backups_log');
            return;
        }

        $backupDir = dirname(__DIR__) . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $marca = 'zztest_' . bin2hex(random_bytes(4));
        $archivoVencido = "backup_{$marca}.sql.gz";
        $archivoReciente = "backup_{$marca}_reciente.sql.gz";
        $rutaVencido = $backupDir . '/' . $archivoVencido;
        $rutaReciente = $backupDir . '/' . $archivoReciente;

        file_put_contents($rutaVencido, 'dummy');
        file_put_contents($rutaReciente, 'dummy');

        $db->exec("
            INSERT INTO backups_log
                (nombre_archivo, tamano_bytes, hash_sha256, tablas_respaldadas, estado, notas, fecha_descarga)
            VALUES
                (" . $db->quote($archivoVencido) . ", 5, 'test', 1, 'exitoso', NULL, NOW() - INTERVAL 8 DAY)
        ");
        $idVencido = (int)$db->lastInsertId();

        try {
            require dirname(__DIR__) . '/scripts/cleanup_backups.php';

            $this->assertFalse(file_exists($rutaVencido), 'el respaldo descargado hace 8 días debe eliminarse');
            $this->assertTrue(file_exists($rutaReciente), 'un archivo reciente sin descarga no debe tocarse');

            $nota = (string)$db->query('SELECT notas FROM backups_log WHERE id = ' . $idVencido)->fetchColumn();
            $this->assertStringContains('Archivo eliminado tras descarga', $nota, 'la fila de backups_log debe quedar anotada');
        } finally {
            if (is_file($rutaVencido)) {
                @unlink($rutaVencido);
            }
            if (is_file($rutaReciente)) {
                @unlink($rutaReciente);
            }
            $db->exec('DELETE FROM backups_log WHERE id = ' . $idVencido);
        }
    }
}
