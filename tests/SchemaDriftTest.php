<?php
namespace Tests;

use App\Core\Database;
use PDO;

/**
 * Guardia contra el drift de esquema local que rompía la suite.
 *
 * Estas tablas/columnas/índices se crean con migraciones idempotentes ya
 * versionadas en scripts/. El test falla si una base queda a medias.
 */
class SchemaDriftTest extends TestCase {

    private function db(): PDO {
        try {
            return Database::getConnection();
        } catch (\Throwable $e) {
            throw new \RuntimeException('MySQL no disponible para SchemaDriftTest: ' . $e->getMessage());
        }
    }

    public function testTablasDeMigracionesExisten(): void {
        $db = $this->db();
        $tablas = ['cartas_deuda_envios', 'reportes_cartelera', 'deudas_manuales', 'purgas_log'];
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla
        ");

        foreach ($tablas as $tabla) {
            $stmt->execute(['tabla' => $tabla]);
            $this->assertEquals(1, (int)$stmt->fetchColumn(),
                "La tabla {$tabla} debe existir (migración idempotente aplicada)");
        }
    }

    public function testColumnasDeMigracionesExisten(): void {
        $db = $this->db();
        $columnas = [
            ['notificaciones_cola', 'adjuntos'],
            ['backups_log', 'fecha_descarga'],
            ['purgas_log', 'fecha_descarga'],
        ];
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna
        ");

        foreach ($columnas as [$tabla, $columna]) {
            $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
            $this->assertEquals(1, (int)$stmt->fetchColumn(),
                "La columna {$tabla}.{$columna} debe existir");
        }
    }

    public function testIndiceRetencionExiste(): void {
        $db = $this->db();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'historial_estado_abono'
              AND INDEX_NAME = 'idx_hist_creado'
        ");
        $stmt->execute();
        $this->assertGreaterThan(0, (int)$stmt->fetchColumn(),
            'El índice idx_hist_creado debe existir (migración de retención aplicada)');
    }
}
