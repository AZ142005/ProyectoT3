<?php
namespace Tests;

use App\Core\Database;
use App\Models\ConciliacionModel;
use PDO;

/**
 * Pruebas de la ventana de conciliación: los 3 lotes más recientes por banco.
 *
 * Requieren MySQL activo (base condominio_cobranzas). Cada prueba usa un
 * prefijo único y limpia sus filas en finally. Si la base no está disponible
 * se lanza una excepción para que el runner reporte el bloqueo de entorno.
 */
class ConciliacionVentanaTest extends TestCase {

    private const PREFIJO = 'TEST-VENTANA-';

    private function conexion(): PDO {
        try {
            return Database::getConnection();
        } catch (\Throwable $e) {
            throw new \RuntimeException('MySQL no disponible: ' . $e->getMessage());
        }
    }

    private function prefijo(): string {
        return self::PREFIJO . bin2hex(random_bytes(4));
    }

    private function banco(string $prefijo): string {
        return 'Banco Test Ventana ' . substr($prefijo, -6);
    }

    private function insertarFila(
        PDO $db,
        string $banco,
        string $lote,
        string $referencia,
        string $fechaCarga,
        ?string $estado = 'disponible',
        ?string $estadoConciliacion = 'pendiente',
        float $monto = 100.00
    ): int {
        $stmt = $db->prepare("
            INSERT INTO extractos_bancarios
            (lote_importacion, banco, fecha_movimiento, referencia_bancaria, referencia,
             descripcion_banco, descripcion, monto, tipo_movimiento,
             estado_conciliacion, estado, fecha_carga)
            VALUES
            (:lote, :banco, '2099-01-15', :ref, :ref2,
             'Movimiento de prueba ventana', 'Movimiento de prueba ventana', :monto, 'credito',
             :estado_conciliacion, :estado, :fecha_carga)
        ");
        $stmt->execute([
            'lote'                => $lote,
            'banco'               => $banco,
            'ref'                 => $referencia,
            'ref2'                => $referencia,
            'monto'               => $monto,
            'estado_conciliacion' => $estadoConciliacion,
            'estado'              => $estado,
            'fecha_carga'         => $fechaCarga,
        ]);

        return (int)$db->lastInsertId();
    }

    private function limpiar(PDO $db, string $prefijo): void {
        $stmtIds = $db->prepare("
            SELECT id FROM extractos_bancarios
            WHERE lote_importacion LIKE :lote OR referencia_bancaria LIKE :ref
        ");
        $stmtIds->execute([
            'lote' => $prefijo . '%',
            'ref'  => $prefijo . '%',
        ]);
        $ids = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($ids)) {
            $marcadores = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM historial_estado_abono WHERE movimiento_id IN ({$marcadores})")->execute($ids);
            $db->prepare("DELETE FROM conciliacion_abono_pago WHERE movimiento_id IN ({$marcadores})")->execute($ids);
        }

        $stmt = $db->prepare("
            DELETE FROM extractos_bancarios
            WHERE lote_importacion LIKE :lote OR referencia_bancaria LIKE :ref
        ");
        $stmt->execute([
            'lote' => $prefijo . '%',
            'ref'  => $prefijo . '%',
        ]);
    }

    public function testVentanaConservaLosTresLotesMasRecientesPorBanco(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $banco = $this->banco($p);
        $lotes = [$p . '-L1', $p . '-L2', $p . '-L3', $p . '-L4'];
        $fechas = [
            '2020-01-01 00:00:00',
            '2020-01-02 00:00:00',
            '2020-01-03 00:00:00',
            '2020-01-04 00:00:00',
        ];

        try {
            foreach ($lotes as $indice => $lote) {
                $this->insertarFila(
                    $db,
                    $banco,
                    $lote,
                    $p . '-REF' . ($indice + 1),
                    $fechas[$indice],
                    monto: 100.00 + $indice
                );
            }

            // Un conciliado del lote más antiguo debe conservarse aunque quede fuera de la ventana.
            $this->insertarFila(
                $db,
                $banco,
                $lotes[0],
                $p . '-CONC',
                $fechas[0],
                estado: 'conciliado',
                estadoConciliacion: 'conciliado',
                monto: 999.00
            );

            $model = new ConciliacionModel();

            $ventanaBanco = $model->obtenerLotesVentanaPorBanco($banco, 3);
            $nombresVentana = array_column($ventanaBanco, 'lote_importacion');

            $this->assertEquals(3, count($ventanaBanco), 'La ventana por banco debe contener exactamente 3 lotes');
            $this->assertEquals(
                [$lotes[3], $lotes[2], $lotes[1]],
                $nombresVentana,
                'La ventana debe contener los 3 lotes más recientes, del más nuevo al más antiguo'
            );
            $this->assertFalse(
                in_array($lotes[0], $nombresVentana, true),
                'El lote más antiguo debe quedar fuera de la ventana'
            );

            $ventanaGlobal = array_values(array_filter(
                $model->obtenerLotesVentana(3),
                static fn(array $lote): bool => ($lote['banco'] ?? '') === $banco
            ));
            $this->assertEquals(
                $nombresVentana,
                array_column($ventanaGlobal, 'lote_importacion'),
                'obtenerLotesVentana debe devolver la misma ventana del banco consultado'
            );

            $pendientesVentana = $model->obtenerExtractosPendientesVentana(3);
            $referenciasVentana = array_column($pendientesVentana, 'referencia_bancaria');
            foreach ([2, 3, 4] as $numero) {
                $this->assertTrue(
                    in_array($p . '-REF' . $numero, $referenciasVentana, true),
                    "El disponible REF{$numero} de un lote en ventana debe aparecer en la bandeja"
                );
            }
            $this->assertFalse(
                in_array($p . '-REF1', $referenciasVentana, true),
                'El disponible del lote fuera de la ventana no debe aparecer en la bandeja'
            );
            $this->assertFalse(
                in_array($p . '-CONC', $referenciasVentana, true),
                'Un movimiento conciliado no debe aparecer como disponible'
            );

            $this->assertEquals(
                [],
                $model->obtenerExtractosPendientes($lotes[0]),
                'obtenerExtractosPendientes debe devolver [] para un lote fuera de la ventana'
            );

            $pendientesLote3 = $model->obtenerExtractosPendientes($lotes[2]);
            $this->assertEquals(1, count($pendientesLote3), 'Un lote dentro de la ventana debe devolver sus disponibles');
            $this->assertEquals(
                $p . '-REF3',
                $pendientesLote3[0]['referencia_bancaria'],
                'El disponible devuelto debe pertenecer al lote en ventana consultado'
            );

            $pendientesGlobales = $model->obtenerExtractosPendientes(null);
            $referenciasGlobales = array_column($pendientesGlobales, 'referencia_bancaria');
            foreach ([2, 3, 4] as $numero) {
                $this->assertTrue(
                    in_array($p . '-REF' . $numero, $referenciasGlobales, true),
                    "Sin lote explícito debe incluirse el disponible REF{$numero} de la ventana"
                );
            }
            $this->assertFalse(
                in_array($p . '-REF1', $referenciasGlobales, true),
                'Sin lote explícito no debe incluirse el disponible fuera de la ventana'
            );

            $metricas = [];
            foreach ($model->obtenerLotes() as $fila) {
                if (($fila['banco'] ?? '') === $banco) {
                    $metricas[$fila['lote_importacion']] = $fila;
                }
            }

            $this->assertEquals(4, count($metricas), 'obtenerLotes debe listar los 4 lotes del banco de prueba');
            $this->assertEquals(0, (int)($metricas[$lotes[0]]['es_activo'] ?? -1), 'El lote fuera de la ventana no debe marcarse activo');
            foreach ([1, 2, 3] as $indice) {
                $this->assertEquals(
                    1,
                    (int)($metricas[$lotes[$indice]]['es_activo'] ?? -1),
                    "El lote {$lotes[$indice]} de la ventana debe marcarse activo"
                );
            }

            $stmtConciliado = $db->prepare("SELECT estado, estado_conciliacion FROM extractos_bancarios WHERE referencia_bancaria = :ref");
            $stmtConciliado->execute(['ref' => $p . '-CONC']);
            $filaConciliada = $stmtConciliado->fetch(PDO::FETCH_ASSOC);
            $this->assertTrue($filaConciliada !== false, 'El conciliado del lote fuera de la ventana debe seguir existiendo');
            if ($filaConciliada !== false) {
                $this->assertEquals('conciliado', $filaConciliada['estado'], 'El estado conciliado fuera de la ventana no debe alterarse');
                $this->assertEquals('conciliado', $filaConciliada['estado_conciliacion'], 'El estado_conciliacion fuera de la ventana no debe alterarse');
            }
        } finally {
            $this->limpiar($db, $p);
        }
    }
}
