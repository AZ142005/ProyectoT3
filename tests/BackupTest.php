<?php
namespace Tests;

class BackupTest extends TestCase {

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

    public function testBackupRetentionCleansOldFiles() {
        $backupDir = dirname(__DIR__) . '/storage/backups';
        $testOldFile = $backupDir . '/backup_test_old.sql.gz';
        $testRecentFile = $backupDir . '/backup_test_recent.sql.gz';

        file_put_contents($testOldFile, 'dummy data');
        file_put_contents($testRecentFile, 'dummy data');

        // Simular archivo de 10 días de antigüedad
        touch($testOldFile, time() - (10 * 86400));
        // Archivo reciente (1 hora atrás)
        touch($testRecentFile, time() - 3600);

        // Ejecutar script de rotación
        require dirname(__DIR__) . '/scripts/cleanup_backups.php';

        $this->assertFalse(file_exists($testOldFile));
        $this->assertTrue(file_exists($testRecentFile));

        // Limpieza del archivo de test
        @unlink($testRecentFile);
    }
}
