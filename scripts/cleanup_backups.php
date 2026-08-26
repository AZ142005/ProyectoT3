<?php
/**
 * Script independiente para purga y rotación diaria de respaldos de BD (> 7 días).
 * Ejecutable vía cron/CLI: php scripts/cleanup_backups.php
 */

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    exit(0);
}

$limiteTiempo = time() - (7 * 86400);
$archivos = glob($backupDir . '/backup_*');
$purgados = 0;

if ($archivos) {
    foreach ($archivos as $archivo) {
        if (filemtime($archivo) < $limiteTiempo) {
            @unlink($archivo);
            $purgados++;
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Limpieza de respaldos completada: {$purgados} archivo(s) eliminados.\n";
