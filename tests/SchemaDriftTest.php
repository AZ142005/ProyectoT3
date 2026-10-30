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

    public function testCedulaDePersonasNoEsUnica(): void {
        $db = $this->db();
        $stmt = $db->query("SHOW INDEX FROM personas WHERE Column_name = 'cedula'");
        $tieneUnico = false;
        $tieneNoUnico = false;

        foreach ($stmt as $indice) {
            if ((int)$indice['Non_unique'] === 0) {
                $tieneUnico = true;
            } else {
                $tieneNoUnico = true;
            }
        }

        $this->assertFalse($tieneUnico, 'personas.cedula no debe tener un índice UNIQUE');
        $this->assertTrue($tieneNoUnico, 'personas.cedula debe tener un índice no único para búsquedas');
    }

    public function testCedulaPuedeRepetirseEnUnidadesDistintas(): void {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(4));
            $cedula = 'V-TEST' . $sufijo;

            $stmtU = $db->prepare("INSERT INTO unidades (numero, cuota_mensual, estado) VALUES (:numero, 0.00, 1)");
            $stmtU->execute(['numero' => 'TEST-CED-A-' . $sufijo]);
            $unidadA = (int)$db->lastInsertId();
            $stmtU->execute(['numero' => 'TEST-CED-B-' . $sufijo]);
            $unidadB = (int)$db->lastInsertId();

            $stmtP = $db->prepare("
                INSERT INTO personas (cedula, nombre, apellido, email, unidad_id, tipo, estado)
                VALUES (:cedula, 'Test', 'Cedula', :email, :unidad_id, 'propietario', 1)
            ");
            $stmtP->execute([
                'cedula'    => $cedula,
                'email'     => 'ced-a-' . $sufijo . '@example.com',
                'unidad_id' => $unidadA,
            ]);
            $stmtP->execute([
                'cedula'    => $cedula,
                'email'     => 'ced-b-' . $sufijo . '@example.com',
                'unidad_id' => $unidadB,
            ]);

            $stmt = $db->prepare("SELECT COUNT(*) FROM personas WHERE cedula = :cedula");
            $stmt->execute(['cedula' => $cedula]);
            $this->assertEquals(2, (int)$stmt->fetchColumn(),
                'La misma cédula debe poder existir en dos unidades distintas');
        } finally {
            $db->rollBack();
        }
    }
}
