<?php
namespace Tests;

use App\Core\Database;
use App\Models\ConciliacionModel;
use PDO;
use PDOException;

/**
 * Pruebas Fase 18 — extractos bancarios:
 * - Índice único uk_extracto_identidad (referencia_bancaria, fecha_movimiento, monto).
 * - ConciliacionModel::insertarExtracto deduplica por SELECT y cierra la carrera
 *   capturando 23000/1062 sin lanzar.
 * - Flujos legítimos con identidades distintas pasan.
 */
class ExtractosDuplicadosTest extends TestCase {

    private const PREFIJO = 'TEST-F18-EX-';

    private function indiceExiste(PDO $db, string $tabla, string $indice): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND INDEX_NAME = :indice");
        $stmt->execute(['tabla' => $tabla, 'indice' => $indice]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function movimiento(string $referencia, float $monto, string $fecha = '2098-12-01'): array {
        return [
            'referencia'  => $referencia,
            'fecha'       => $fecha,
            'monto'       => $monto,
            'descripcion' => 'Movimiento de prueba ' . $referencia,
        ];
    }

    private function insertarDirecto(PDO $db, string $referencia, float $monto): void {
        $stmt = $db->prepare("
            INSERT INTO extractos_bancarios
            (banco, fecha_movimiento, referencia_bancaria, referencia, descripcion_banco, descripcion, monto, tipo_movimiento, estado_conciliacion, estado, lote_importacion)
            VALUES ('Banco Test', '2098-12-01', :ref, :ref2, 'desc', 'desc', :monto, 'credito', 'pendiente', 'disponible', :lote)
        ");
        $stmt->execute(['ref' => $referencia, 'ref2' => $referencia, 'monto' => $monto, 'lote' => self::PREFIJO . 'LOTE']);
    }

    private function limpiar(PDO $db): void {
        $db->exec("DELETE FROM extractos_bancarios WHERE referencia LIKE '" . self::PREFIJO . "%'");
    }

    public function testIndiceUnicoExtractoExiste(): void {
        $db = Database::getConnection();

        if (!$this->indiceExiste($db, 'extractos_bancarios', 'uk_extracto_identidad')) {
            $this->skip('migración Fase 18 no aplicada (uk_extracto_identidad)');
            return;
        }

        $this->assertTrue($this->indiceExiste($db, 'extractos_bancarios', 'uk_extracto_identidad'), 'Debe existir uk_extracto_identidad');
    }

    public function testInsercionDirectaDuplicadaBloqueada(): void {
        $db = Database::getConnection();
        if (!$this->indiceExiste($db, 'extractos_bancarios', 'uk_extracto_identidad')) {
            $this->skip('migración Fase 18 no aplicada (uk_extracto_identidad)');
            return;
        }

        $referencia = self::PREFIJO . bin2hex(random_bytes(4));

        try {
            $this->insertarDirecto($db, $referencia, 321.00);

            $codigo = null;
            try {
                $this->insertarDirecto($db, $referencia, 321.00);
            } catch (PDOException $e) {
                $codigo = (string)$e->getCode();
            }
            $this->assertEquals('23000', $codigo, 'La identidad duplicada (ref, fecha, monto) debe ser rechazada por la BD');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testInsertarExtractoDeduplicaYFlujoDistintoPasa(): void {
        $db = Database::getConnection();
        if (!$this->indiceExiste($db, 'extractos_bancarios', 'uk_extracto_identidad')) {
            $this->skip('migración Fase 18 no aplicada (uk_extracto_identidad)');
            return;
        }

        $model = new ConciliacionModel();
        $referencia = self::PREFIJO . bin2hex(random_bytes(4));
        $otraReferencia = $referencia . '-B';

        try {
            $movimiento = $this->movimiento($referencia, 150.00);
            $resultado = $model->insertarExtracto(
                [$movimiento, $movimiento, $this->movimiento($otraReferencia, 250.00)],
                'Banco Test',
                self::PREFIJO . 'LOTE'
            );

            $this->assertEquals(2, $resultado['insertados'], 'El duplicado interno no debe insertarse; el distinto sí');
            $this->assertEquals(1, $resultado['duplicados'], 'El movimiento repetido debe contarse como duplicado');

            $total = intval($db->query("SELECT COUNT(*) FROM extractos_bancarios WHERE referencia LIKE '" . self::PREFIJO . "%'")->fetchColumn());
            $this->assertEquals(2, $total, 'Solo deben persistir los movimientos con identidad distinta');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testCierreDeCarreraCaptura23000SinLanzar(): void {
        $db = Database::getConnection();
        if (!$this->indiceExiste($db, 'extractos_bancarios', 'uk_extracto_identidad')) {
            $this->skip('migración Fase 18 no aplicada (uk_extracto_identidad)');
            return;
        }

        $model = new ConciliacionModel();
        $referencia = self::PREFIJO . bin2hex(random_bytes(4));

        try {
            $primero = $model->insertarExtracto([$this->movimiento($referencia, 100.00)], 'Banco Test', self::PREFIJO . 'LOTE');
            $this->assertEquals(1, $primero['insertados'], 'El primer extracto debe insertarse');

            // Monto con escala extra: el SELECT no colisiona con el valor
            // almacenado (100.00) pero el INSERT se redondea a 100.00 y la
            // guardia única dispara 23000; el modelo debe capturarlo.
            $segundo = $model->insertarExtracto([$this->movimiento($referencia, 100.004)], 'Banco Test', self::PREFIJO . 'LOTE');
            $this->assertEquals(0, $segundo['insertados'], 'La carrera no debe insertar');
            $this->assertEquals(1, $segundo['duplicados'], 'La carrera debe contar el duplicado sin lanzar excepción');

            $total = intval($db->query("SELECT COUNT(*) FROM extractos_bancarios WHERE referencia = '{$referencia}'")->fetchColumn());
            $this->assertEquals(1, $total, 'No debe quedar una fila adicional tras la carrera');
        } finally {
            $this->limpiar($db);
        }
    }
}
