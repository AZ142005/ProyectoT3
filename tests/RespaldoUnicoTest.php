<?php
namespace Tests;

use App\Services\DataRetentionService;

/**
 * Tests del ciclo único de respaldos y exportes:
 *  - migración fecha_descarga (backups_log / purgas_log);
 *  - reglas puras 7 días post-descarga / 30 días sin descarga;
 *  - limpieza real de archivos vencidos con filas anotadas (skip sin BD);
 *  - cableado del barrido diario en scripts/cleanup_retencion.php.
 */
class RespaldoUnicoTest extends TestCase {

    /** Conexión a BD o null (patrón skip del repo). */
    private function conectar(): ?\PDO {
        try {
            return \App\Core\Database::getConnection();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** ¿Existe la columna en la tabla? */
    private function columnaExiste(\PDO $db, string $tabla, string $columna): bool {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
        ");
        $stmt->execute(['t' => $tabla, 'c' => $columna]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // =====================================================================
    // MIGRACIÓN
    // =====================================================================

    public function testMigracionAgregaFechaDescargaEnAmbasBitacoras(): void {
        $migracion = (string)file_get_contents(dirname(__DIR__) . '/scripts/migrate_descarga_respaldo_schema.php');
        $this->assertStringContains('SHOW COLUMNS', $migracion, 'la migración debe usar el guard SHOW COLUMNS (idempotente)');
        $this->assertStringContains('backups_log', $migracion, 'la migración debe cubrir backups_log');
        $this->assertStringContains('purgas_log', $migracion, 'la migración debe cubrir purgas_log');
        $this->assertStringContains('fecha_descarga', $migracion, 'la migración debe agregar fecha_descarga');

        $db = $this->conectar();
        if ($db === null) {
            $this->skip('BD no disponible para verificar la columna fecha_descarga');
            return;
        }
        $this->assertTrue($this->columnaExiste($db, 'backups_log', 'fecha_descarga'), 'backups_log.fecha_descarga debe existir (ejecutar scripts/migrate_descarga_respaldo_schema.php)');
        $this->assertTrue($this->columnaExiste($db, 'purgas_log', 'fecha_descarga'), 'purgas_log.fecha_descarga debe existir (ejecutar scripts/migrate_descarga_respaldo_schema.php)');
    }

    // =====================================================================
    // REGLAS PURAS DEL CICLO ÚNICO
    // =====================================================================

    public function testCicloUnicoUsaVentanas7y30(): void {
        $this->assertEquals(7, DataRetentionService::LIMPIEZA_DIAS_TRAS_DESCARGA, 'la ventana post-descarga debe ser 7 días');
        $this->assertEquals(30, DataRetentionService::LIMPIEZA_DIAS_SIN_DESCARGAR, 'la ventana sin descarga debe ser 30 días');
    }

    public function testVencidoTrasDescarga(): void {
        $ahora = '2026-10-17 12:00:00';
        $this->assertTrue(DataRetentionService::vencidoTrasDescarga('2026-10-09 11:59:59', $ahora), 'descargado hace más de 7 días vence');
        $this->assertTrue(DataRetentionService::vencidoTrasDescarga('2026-10-10 11:59:59', $ahora), 'justo pasado el corte de 7 días vence');
        $this->assertFalse(DataRetentionService::vencidoTrasDescarga('2026-10-10 12:00:01', $ahora), 'descargado hace menos de 7 días no vence');
        $this->assertFalse(DataRetentionService::vencidoTrasDescarga(null, $ahora), 'sin fecha de descarga la regla de 7 días no aplica');
        $this->assertFalse(DataRetentionService::vencidoTrasDescarga('', $ahora), 'fecha vacía no vence');
    }

    public function testVencidoSinDescargar(): void {
        $ahora = '2026-10-17 12:00:00';
        $this->assertTrue(DataRetentionService::vencidoSinDescargar(null, '2026-09-16 11:59:59', $ahora), '31 días sin descarga vence');
        $this->assertFalse(DataRetentionService::vencidoSinDescargar(null, '2026-09-17 12:00:01', $ahora), 'menos de 30 días sin descarga no vence');
        $this->assertFalse(DataRetentionService::vencidoSinDescargar('2026-09-01 00:00:00', '2026-08-01 00:00:00', $ahora), 'si fue descargado manda la regla de 7 días');
        $this->assertFalse(DataRetentionService::vencidoSinDescargar(null, null, $ahora), 'sin fechas no vence');
    }

    // =====================================================================
    // BARRIDO DIARIO (estático)
    // =====================================================================

    public function testCleanupRetencionCableaLasReglasNuevasConDryRun(): void {
        $script = (string)file_get_contents(dirname(__DIR__) . '/scripts/cleanup_retencion.php');
        $this->assertStringContains('limpiarExportesPurgas($dryRun)', $script, 'el barrido debe limpiar exportes con las reglas nuevas respetando --dry-run');
        $this->assertStringContains('limpiarRespaldoGeneralVencido($dryRun)', $script, 'el barrido debe incluir el backstop del respaldo general respetando --dry-run');
        $this->assertFalse(str_contains($script, 'limpiarExportesAntiguos'), 'la rotación antigua (366 días) ya no debe usarse');
        $this->assertFalse(str_contains($script, '366'), 'no debe quedar ninguna mención a 366 días en el barrido');
    }

    // =====================================================================
    // LIMPIEZA REAL DE ARCHIVOS (BD; skip si no hay conexión o migración)
    // =====================================================================

    public function testLimpiezaExportesPurgasAplicaCicloUnico(): void {
        $db = $this->conectar();
        if ($db === null) {
            $this->skip('BD no disponible para la limpieza de exportes');
            return;
        }
        if (!$this->columnaExiste($db, 'purgas_log', 'fecha_descarga')) {
            $this->skip('Migración fecha_descarga pendiente en purgas_log');
            return;
        }

        $dir = dirname(__DIR__) . '/storage/backups/purgas';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $marca = 'zztest_' . bin2hex(random_bytes(4));
        $casos = [
            'descargado'    => ['archivo' => "rate_limits_exportacion_{$marca}_descargado.sql.gz", 'fecha_descarga' => 'NOW() - INTERVAL 8 DAY',  'fecha_ejecucion' => 'NOW() - INTERVAL 40 DAY'],
            'sin_descargar' => ['archivo' => "rate_limits_exportacion_{$marca}_sin.sql.gz",        'fecha_descarga' => 'NULL',                    'fecha_ejecucion' => 'NOW() - INTERVAL 31 DAY'],
            'reciente'      => ['archivo' => "rate_limits_exportacion_{$marca}_reciente.sql.gz",   'fecha_descarga' => 'NOW() - INTERVAL 1 DAY',  'fecha_ejecucion' => 'NOW() - INTERVAL 2 DAY'],
        ];

        $ids = [];
        foreach ($casos as $nombre => $caso) {
            file_put_contents($dir . '/' . $caso['archivo'], 'dummy');
            $db->exec("
                INSERT INTO purgas_log
                    (tipo, tabla, fecha_corte, registros_exportados, registros_eliminados, archivo, tamano_bytes, hash_sha256, modo, estado, notas, fecha_ejecucion, fecha_descarga)
                VALUES
                    ('exportacion', 'rate_limits', NULL, 1, 0, " . $db->quote($caso['archivo']) . ", 5, 'test', 'manual', 'exitoso', NULL, {$caso['fecha_ejecucion']}, {$caso['fecha_descarga']})
            ");
            $ids[$nombre] = (int)$db->lastInsertId();
        }

        try {
            $resultado = (new DataRetentionService($db))->limpiarExportesPurgas(false);

            $this->assertTrue($resultado['eliminados'] >= 2, 'debe eliminar al menos los dos sintéticos vencidos');
            $this->assertFalse(file_exists($dir . '/' . $casos['descargado']['archivo']), 'el exporte descargado hace 8 días debe eliminarse');
            $this->assertFalse(file_exists($dir . '/' . $casos['sin_descargar']['archivo']), 'el exporte nunca descargado de hace 31 días debe eliminarse');
            $this->assertTrue(file_exists($dir . '/' . $casos['reciente']['archivo']), 'el exporte descargado hace 1 día no debe tocarse');

            $notaDescargado = (string)$db->query('SELECT notas FROM purgas_log WHERE id = ' . $ids['descargado'])->fetchColumn();
            $this->assertStringContains('Archivo eliminado tras descarga', $notaDescargado, 'la fila descargada debe quedar anotada');
            $notaSin = (string)$db->query('SELECT notas FROM purgas_log WHERE id = ' . $ids['sin_descargar'])->fetchColumn();
            $this->assertStringContains('Archivo eliminado sin descarga', $notaSin, 'la fila nunca descargada debe quedar anotada');

            // Dry-run: no borra ni anota.
            $dry = (new DataRetentionService($db))->limpiarExportesPurgas(true);
            $this->assertEquals(0, $dry['eliminados'], 'en dry-run no debe reportar nada pendiente (los vencidos ya se limpiaron)');
        } finally {
            foreach ($casos as $caso) {
                $rutaCaso = $dir . '/' . $caso['archivo'];
                if (is_file($rutaCaso)) {
                    @unlink($rutaCaso);
                }
                if (is_file($rutaCaso . '.sha256')) {
                    @unlink($rutaCaso . '.sha256');
                }
            }
            if (!empty($ids)) {
                $db->exec('DELETE FROM purgas_log WHERE id IN (' . implode(',', $ids) . ')');
            }
        }
    }

    public function testLimpiezaRespaldoGeneralVencidoEliminaArchivoYAnota(): void {
        $db = $this->conectar();
        if ($db === null) {
            $this->skip('BD no disponible para el backstop del respaldo general');
            return;
        }
        if (!$this->columnaExiste($db, 'backups_log', 'fecha_descarga')) {
            $this->skip('Migración fecha_descarga pendiente en backups_log');
            return;
        }

        $dir = dirname(__DIR__) . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $marca = 'zztest_' . bin2hex(random_bytes(4));
        $archivo = "backup_{$marca}.sql.gz";
        file_put_contents($dir . '/' . $archivo, 'dummy');

        $db->exec("
            INSERT INTO backups_log
                (nombre_archivo, tamano_bytes, hash_sha256, tablas_respaldadas, estado, notas, fecha_descarga)
            VALUES
                (" . $db->quote($archivo) . ", 5, 'test', 1, 'exitoso', NULL, NOW() - INTERVAL 8 DAY)
        ");
        $id = (int)$db->lastInsertId();

        try {
            $resultado = (new DataRetentionService($db))->limpiarRespaldoGeneralVencido(false);

            $this->assertTrue($resultado['eliminados'] >= 1, 'el backstop debe eliminar el respaldo sintético descargado-vencido');
            $this->assertFalse(file_exists($dir . '/' . $archivo), 'el respaldo descargado hace 8 días debe eliminarse por el backstop');
            $nota = (string)$db->query('SELECT notas FROM backups_log WHERE id = ' . $id)->fetchColumn();
            $this->assertStringContains('Archivo eliminado tras descarga', $nota, 'la fila de backups_log debe quedar anotada');
        } finally {
            $rutaSintetica = $dir . '/' . $archivo;
            if (is_file($rutaSintetica)) {
                @unlink($rutaSintetica);
            }
            if (is_file($rutaSintetica . '.sha256')) {
                @unlink($rutaSintetica . '.sha256');
            }
            $db->exec('DELETE FROM backups_log WHERE id = ' . $id);
        }
    }
}
