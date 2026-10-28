<?php
namespace Tests;

use App\Core\Database;
use App\Models\ConciliacionModel;
use App\Services\ConciliacionBancariaService;
use PDO;

/**
 * Pruebas T1 — lote único activo, adopción de duplicados y métricas de lote.
 *
 * Requieren MySQL activo (base condominio_cobranzas). Cada prueba usa un
 * prefijo único y limpia sus filas en finally. Si la base no está disponible
 * se lanza una excepción para que el runner reporte el bloqueo de entorno.
 */
class ConciliacionLoteUnicoTest extends TestCase {

    private const PREFIJO = 'TEST-LOTEUNICO-';

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

    private function insertarFila(
        PDO $db,
        string $lote,
        string $referencia,
        ?string $estado = 'disponible',
        ?string $estadoConciliacion = 'pendiente',
        ?int $pagoId = null,
        string $fechaCarga = '2020-01-01 00:00:00',
        string $fechaMovimiento = '2099-01-15',
        float $monto = 100.00,
        string $tipo = 'credito',
        string $descripcion = 'Movimiento de prueba T1'
    ): int {
        $stmt = $db->prepare("
            INSERT INTO extractos_bancarios
            (lote_importacion, banco, fecha_movimiento, referencia_bancaria, referencia,
             descripcion_banco, descripcion, monto, tipo_movimiento,
             estado_conciliacion, estado, pago_id, fecha_carga)
            VALUES
            (:lote, 'Banco Test Lote Unico', :fecha_mov, :ref, :ref2,
             :desc_banco, :descripcion, :monto, :tipo,
             :estado_conciliacion, :estado, :pago_id, :fecha_carga)
        ");
        $stmt->execute([
            'lote'                => $lote,
            'fecha_mov'           => $fechaMovimiento,
            'ref'                 => $referencia,
            'ref2'                => $referencia,
            'desc_banco'          => $descripcion,
            'descripcion'         => $descripcion,
            'monto'               => $monto,
            'tipo'                => $tipo,
            'estado_conciliacion' => $estadoConciliacion,
            'estado'              => $estado,
            'pago_id'             => $pagoId,
            'fecha_carga'         => $fechaCarga,
        ]);

        return (int)$db->lastInsertId();
    }

    private function obtenerFila(PDO $db, string $referencia): array|false {
        $stmt = $db->prepare("
            SELECT id, lote_importacion, banco, descripcion, estado, estado_conciliacion,
                   pago_id, fecha_carga, monto, tipo_movimiento
            FROM extractos_bancarios
            WHERE referencia_bancaria = :ref
            LIMIT 1
        ");
        $stmt->execute(['ref' => $referencia]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function pagoLibre(PDO $db): ?int {
        $id = $db->query("
            SELECT p.id
            FROM pagos p
            LEFT JOIN conciliacion_abono_pago c ON c.pago_id = p.id
            WHERE c.id IS NULL
            ORDER BY p.id
            LIMIT 1
        ")->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    private function vincular(PDO $db, int $movimientoId, int $pagoId): void {
        $db->prepare("
            INSERT INTO conciliacion_abono_pago (movimiento_id, pago_id, conciliado_por)
            VALUES (:mov, :pago, 1)
        ")->execute(['mov' => $movimientoId, 'pago' => $pagoId]);
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

    private function movimiento(string $referencia, float $monto, string $descripcion = 'Importacion T1'): array {
        return [
            'fecha'       => '2099-01-15',
            'referencia'  => $referencia,
            'descripcion' => $descripcion,
            'monto'       => $monto,
        ];
    }

    public function testLoteActivoEsElMasRecienteConDesempatePorId(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $loteA = $p . '-A';
        $loteB = $p . '-B';
        $loteC = $p . '-C';
        $loteD = $p . '-D';
        $futuroAnterior = date('Y-m-d H:i:s', time() + 3600);
        $futuro = date('Y-m-d H:i:s', time() + 7200);
        $futuroEmpate = date('Y-m-d H:i:s', time() + 10800);

        try {
            $this->insertarFila($db, $loteA, $p . '-A1', fechaCarga: $futuro);
            $this->insertarFila($db, $loteB, $p . '-B1', fechaCarga: $futuroEmpate);
            $this->insertarFila($db, $loteC, $p . '-C1', fechaCarga: $futuroEmpate);
            // D tiene el id más alto pero una fecha anterior: no debe ganar.
            $this->insertarFila($db, $loteD, $p . '-D1', fechaCarga: $futuroAnterior);

            $model = new ConciliacionModel();
            $activo = $model->obtenerLoteActivo();

            $this->assertEquals($loteC, $activo, 'El lote activo debe ser el de mayor fecha_carga y, en empate, el de mayor id');
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testPendientesSoloDeLaVentanaYHistoricoDevuelveVacio(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $loteHistorico = $p . '-HIST';
        $loteRelleno1 = $p . '-FILL1';
        $loteRelleno2 = $p . '-FILL2';
        $loteActivo = $p . '-ACT';
        $pasado = '2020-01-01 00:00:00';
        $futuro = date('Y-m-d H:i:s', time() + 7200);

        try {
            // H1 se inserta primero (id menor) pero tiene la fecha de movimiento más reciente.
            $this->insertarFila($db, $loteHistorico, $p . '-H1', fechaCarga: $pasado, fechaMovimiento: '2099-01-11');
            $this->insertarFila($db, $loteHistorico, $p . '-H2', estado: 'pendiente', fechaCarga: $pasado, fechaMovimiento: '2099-01-10');

            // Dos lotes de relleno más recientes dejan al histórico fuera de la ventana.
            // Sólo tienen filas conciliadas, así que no aportan disponibles.
            $this->insertarFila($db, $loteRelleno1, $p . '-F1', estado: 'conciliado', estadoConciliacion: 'conciliado', fechaCarga: '2021-01-01 00:00:00', fechaMovimiento: '2099-03-01');
            $this->insertarFila($db, $loteRelleno2, $p . '-F2', estado: 'conciliado', estadoConciliacion: 'conciliado', fechaCarga: '2022-01-01 00:00:00', fechaMovimiento: '2099-03-02');

            $this->insertarFila($db, $loteActivo, $p . '-A1', fechaCarga: $futuro, fechaMovimiento: '2099-02-01');
            $this->insertarFila(
                $db,
                $loteActivo,
                $p . '-A2',
                estado: 'conciliado',
                estadoConciliacion: 'conciliado',
                pagoId: 3,
                fechaCarga: $futuro,
                fechaMovimiento: '2099-02-02'
            );
            // A3: fila legacy sin estado pero con estado_conciliacion pendiente.
            $this->insertarFila(
                $db,
                $loteActivo,
                $p . '-A3',
                estado: null,
                fechaCarga: $futuro,
                fechaMovimiento: '2099-02-03',
                monto: 103.00
            );
            $this->insertarFila(
                $db,
                $loteActivo,
                $p . '-A4',
                estado: 'anulado',
                estadoConciliacion: 'anulado',
                fechaCarga: $futuro,
                fechaMovimiento: '2099-02-04',
                monto: 104.00
            );
            // A5: disponible pero ya vinculado por pago_id.
            $this->insertarFila(
                $db,
                $loteActivo,
                $p . '-A5',
                pagoId: 3,
                fechaCarga: $futuro,
                fechaMovimiento: '2099-02-05',
                monto: 105.00
            );

            $model = new ConciliacionModel();

            $pendientesGlobales = $model->obtenerExtractosPendientes(null);
            $referenciasGlobales = array_column($pendientesGlobales, 'referencia_bancaria');
            $referenciasPropias = array_values(array_filter(
                $referenciasGlobales,
                static fn(string $referencia): bool => str_starts_with($referencia, $p . '-')
            ));
            sort($referenciasPropias);
            $esperadas = [$p . '-A1', $p . '-A3'];
            sort($esperadas);

            $this->assertEquals($esperadas, $referenciasPropias, 'Sin lote explícito solo deben salir los disponibles reales de los lotes en ventana');
            $this->assertFalse(in_array($p . '-H1', $referenciasGlobales, true), 'El disponible del lote fuera de la ventana no debe aparecer');
            $this->assertFalse(in_array($p . '-H2', $referenciasGlobales, true), 'El disponible del lote fuera de la ventana no debe aparecer');
            $this->assertFalse(in_array($p . '-A2', $referenciasGlobales, true), 'Una fila conciliada no debe aparecer');
            $this->assertFalse(in_array($p . '-A4', $referenciasGlobales, true), 'Una fila anulada no debe aparecer');
            $this->assertFalse(in_array($p . '-A5', $referenciasGlobales, true), 'Una fila con pago_id vinculado no debe aparecer');

            $pendientesVentana = $model->obtenerExtractosPendientesVentana(3);
            $referenciasVentana = array_column($pendientesVentana, 'referencia_bancaria');
            $this->assertTrue(in_array($p . '-A1', $referenciasVentana, true), 'El disponible del lote activo debe aparecer en la ventana');
            $this->assertTrue(in_array($p . '-A3', $referenciasVentana, true), 'La fila legacy del lote activo debe aparecer en la ventana');
            $this->assertFalse(in_array($p . '-H1', $referenciasVentana, true), 'El lote histórico no debe aportar disponibles a la ventana');
            $this->assertFalse(in_array($p . '-H2', $referenciasVentana, true), 'El lote histórico no debe aportar disponibles a la ventana');

            $this->assertEquals(0, count($model->obtenerExtractosPendientes($loteHistorico)), 'Un lote fuera de la ventana no debe mostrar pendientes en la bandeja');

            $pendientesActivos = $model->obtenerExtractosPendientes($loteActivo);
            $this->assertEquals(2, count($pendientesActivos), 'El lote activo explícito debe devolver sus disponibles reales');

            $disponiblesActivos = $model->obtenerDisponiblesLote($loteActivo);
            $referenciasActivas = array_column($disponiblesActivos, 'referencia_bancaria');
            $this->assertEquals(2, count($disponiblesActivos), 'La consulta cruda del lote activo debe devolver los mismos disponibles');
            $this->assertTrue(in_array($p . '-A3', $referenciasActivas, true), 'La consulta cruda debe incluir la fila legacy con estado NULL');
            $this->assertFalse(in_array($p . '-A4', $referenciasActivas, true), 'La consulta cruda debe excluir la fila anulada');

            $disponiblesHistoricos = $model->obtenerDisponiblesLote($loteHistorico);
            $this->assertEquals(2, count($disponiblesHistoricos), 'La consulta cruda sí debe devolver los disponibles del lote histórico para reportes');
            $this->assertEquals($p . '-H1', $disponiblesHistoricos[0]['referencia_bancaria'], 'El orden crudo debe ser fecha_movimiento DESC aunque H1 tenga id menor');
            $this->assertEquals($p . '-H2', $disponiblesHistoricos[1]['referencia_bancaria'], 'El orden crudo no debe caer en id DESC');
            $this->assertTrue(isset($disponiblesHistoricos[0]['lote_importacion'], $disponiblesHistoricos[0]['estado']), 'La consulta cruda debe conservar las columnas del extracto');
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testReimportacionAdoptaDuplicadoNoConciliado(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $loteViejo = $p . '-OLD';
        $loteNuevo = $p . '-NEW';
        $referencia = $p . '-MOV1';
        $monto = 555.55;

        try {
            $model = new ConciliacionModel();

            $primero = $model->insertarExtracto(
                [$this->movimiento($referencia, $monto, 'Importacion original')],
                'Banco Viejo',
                $loteViejo
            );
            $this->assertEquals(1, $primero['insertados'], 'La primera importación debe insertar la fila');
            $this->assertEquals(0, $primero['duplicados'], 'La primera importación no tiene duplicados');
            $this->assertEquals(0, $primero['adoptados'] ?? -1, 'La primera importación no debe adoptar filas');

            $fila = $this->obtenerFila($db, $referencia);
            $this->assertTrue($fila !== false, 'La fila importada debe existir');
            if ($fila === false) {
                return;
            }

            $db->prepare("UPDATE extractos_bancarios SET fecha_carga = '2020-01-01 00:00:00' WHERE id = :id")
               ->execute(['id' => $fila['id']]);

            $segundo = $model->insertarExtracto(
                [$this->movimiento($referencia, $monto, 'Importacion adoptada')],
                'Banco Nuevo',
                $loteNuevo
            );

            $this->assertEquals(0, $segundo['insertados'], 'El duplicado no debe insertar una fila nueva');
            $this->assertEquals(1, $segundo['duplicados'], 'El duplicado debe contarse como duplicado');
            $this->assertEquals(1, $segundo['adoptados'] ?? -1, 'El duplicado no conciliado debe adoptarse al lote nuevo');

            $stmtTotal = $db->prepare("SELECT COUNT(*) FROM extractos_bancarios WHERE referencia_bancaria = :ref");
            $stmtTotal->execute(['ref' => $referencia]);
            $this->assertEquals(1, (int)$stmtTotal->fetchColumn(), 'No debe existir una fila adicional tras la adopción');

            $adoptada = $this->obtenerFila($db, $referencia);
            $this->assertTrue($adoptada !== false, 'La fila adoptada debe seguir existiendo');
            if ($adoptada === false) {
                return;
            }

            $this->assertEquals($loteNuevo, $adoptada['lote_importacion'], 'La fila adoptada debe pertenecer al lote nuevo');
            $this->assertEquals('Banco Nuevo', $adoptada['banco'], 'La adopción debe actualizar el banco');
            $this->assertEquals('Importacion adoptada', $adoptada['descripcion'], 'La adopción debe actualizar la descripción');
            $this->assertEquals('disponible', $adoptada['estado'], 'La adopción no debe modificar el estado');
            $this->assertEquals('pendiente', $adoptada['estado_conciliacion'], 'La adopción no debe modificar el estado de conciliación');
            $this->assertNull($adoptada['pago_id'], 'La adopción no debe vincular un pago');
            $this->assertTrue(
                strtotime($adoptada['fecha_carga']) > strtotime('2020-01-02 00:00:00'),
                'La adopción debe refrescar fecha_carga'
            );
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testReimportacionNoTocaDuplicadoConciliado(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $loteViejo = $p . '-OLD';
        $loteNuevo = $p . '-NEW';
        $pasado = '2020-01-01 00:00:00';

        $casos = [
            ['ref' => $p . '-CONC1', 'estado' => 'conciliado', 'estado_conciliacion' => 'conciliado', 'pago_id' => 3],
            ['ref' => $p . '-CONC2', 'estado' => 'conciliado', 'estado_conciliacion' => 'pendiente', 'pago_id' => null],
            ['ref' => $p . '-CONC3', 'estado' => 'disponible', 'estado_conciliacion' => 'pendiente', 'pago_id' => 3],
        ];

        try {
            foreach ($casos as $caso) {
                $this->insertarFila(
                    $db,
                    $loteViejo,
                    $caso['ref'],
                    estado: $caso['estado'],
                    estadoConciliacion: $caso['estado_conciliacion'],
                    pagoId: $caso['pago_id'],
                    fechaCarga: $pasado,
                    monto: 700.00
                );
            }

            $model = new ConciliacionModel();
            $movimientos = [];
            foreach ($casos as $caso) {
                $movimientos[] = $this->movimiento($caso['ref'], 700.00, 'Reimportacion conciliada');
            }

            $resultado = $model->insertarExtracto($movimientos, 'Banco Nuevo', $loteNuevo);

            $this->assertEquals(0, $resultado['insertados'], 'Un duplicado conciliado nunca debe insertar');
            $this->assertEquals(3, $resultado['duplicados'], 'Los tres duplicados deben contarse');
            $this->assertEquals(0, $resultado['adoptados'] ?? -1, 'Un duplicado conciliado nunca debe adoptarse');

            foreach ($casos as $caso) {
                $fila = $this->obtenerFila($db, $caso['ref']);
                $this->assertTrue($fila !== false, 'La fila conciliada debe seguir existiendo');
                if ($fila === false) {
                    continue;
                }

                $this->assertEquals($loteViejo, $fila['lote_importacion'], 'El lote de una fila conciliada no debe cambiar');
                $this->assertEquals($caso['estado'], $fila['estado'], 'El estado de una fila conciliada no debe cambiar');
                $this->assertEquals($caso['estado_conciliacion'], $fila['estado_conciliacion'], 'El estado_conciliacion de una fila conciliada no debe cambiar');
                $this->assertEquals($caso['pago_id'], $fila['pago_id'] === null ? null : (int)$fila['pago_id'], 'El pago_id de una fila conciliada no debe cambiar');
                $this->assertEquals($pasado, $fila['fecha_carga'], 'La fecha_carga de una fila conciliada no debe refrescarse');
            }
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testAdopcionSoloAplicaAFilasDisponiblesSinVinculo(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $loteViejo = $p . '-OLD';
        $loteNuevo = $p . '-NEW';
        $pasado = '2020-01-01 00:00:00';

        $pagoLibre = $this->pagoLibre($db);
        if ($pagoLibre === null) {
            throw new \RuntimeException('No hay pagos libres para probar el vínculo 1:1');
        }

        try {
            $this->insertarFila($db, $loteViejo, $p . '-PAGO0', pagoId: 0, fechaCarga: $pasado, monto: 801.00);
            $this->insertarFila($db, $loteViejo, $p . '-PAGOPOS', pagoId: 3, fechaCarga: $pasado, monto: 802.00);
            $idVinculado = $this->insertarFila($db, $loteViejo, $p . '-SOLOVINCULO', fechaCarga: $pasado, monto: 803.00);

            $this->vincular($db, $idVinculado, $pagoLibre);

            $model = new ConciliacionModel();
            $resultado = $model->insertarExtracto(
                [
                    $this->movimiento($p . '-PAGO0', 801.00, 'Reimportacion pago cero'),
                    $this->movimiento($p . '-PAGOPOS', 802.00, 'Reimportacion pago positivo'),
                    $this->movimiento($p . '-SOLOVINCULO', 803.00, 'Reimportacion solo vinculo'),
                ],
                'Banco Nuevo',
                $loteNuevo
            );

            $this->assertEquals(0, $resultado['insertados'], 'Ningún duplicado debe insertar');
            $this->assertEquals(3, $resultado['duplicados'], 'Los tres duplicados deben contarse');
            $this->assertEquals(1, $resultado['adoptados'] ?? -1, 'Solo el duplicado con pago_id 0 debe adoptarse');

            $filaPagoCero = $this->obtenerFila($db, $p . '-PAGO0');
            $this->assertTrue($filaPagoCero !== false, 'La fila con pago_id 0 debe existir');
            if ($filaPagoCero !== false) {
                $this->assertEquals($loteNuevo, $filaPagoCero['lote_importacion'], 'Un pago_id 0 equivale a sin vínculo y permite adoptar');
            }

            $filaPagoPositivo = $this->obtenerFila($db, $p . '-PAGOPOS');
            $this->assertTrue($filaPagoPositivo !== false, 'La fila con pago_id positivo debe existir');
            if ($filaPagoPositivo !== false) {
                $this->assertEquals($loteViejo, $filaPagoPositivo['lote_importacion'], 'Un pago_id positivo no debe adoptarse');
                $this->assertEquals(3, (int)$filaPagoPositivo['pago_id'], 'El pago_id no debe modificarse');
            }

            $filaVinculo = $this->obtenerFila($db, $p . '-SOLOVINCULO');
            $this->assertTrue($filaVinculo !== false, 'La fila vinculada solo por tabla debe existir');
            if ($filaVinculo !== false) {
                $this->assertEquals($loteViejo, $filaVinculo['lote_importacion'], 'Una fila con vínculo 1:1 no debe adoptarse');
                $this->assertNull($filaVinculo['pago_id'], 'La adopción no debe crear un pago_id');
            }

            $stmtVinculo = $db->prepare("SELECT COUNT(*) FROM conciliacion_abono_pago WHERE movimiento_id = :mov");
            $stmtVinculo->execute(['mov' => $idVinculado]);
            $this->assertEquals(1, (int)$stmtVinculo->fetchColumn(), 'El vínculo 1:1 debe permanecer intacto');
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testObtenerLotesMarcaActivoYCuentaPendientesReales(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $loteViejo = $p . '-A-HIST';
        $loteRelleno1 = $p . '-M-RELL1';
        $loteRelleno2 = $p . '-N-RELL2';
        $loteActivo = $p . '-Z-ACT';
        $pasado = '2020-01-01 00:00:00';
        $futuro = date('Y-m-d H:i:s', time() + 7200);

        $pagoLibre = $this->pagoLibre($db);
        if ($pagoLibre === null) {
            throw new \RuntimeException('No hay pagos libres para probar la exclusión de vínculos');
        }

        try {
            $this->insertarFila($db, $loteViejo, $p . '-L1', fechaCarga: $pasado, monto: 101.00);
            $this->insertarFila($db, $loteViejo, $p . '-L2', estado: 'pendiente', fechaCarga: $pasado, monto: 102.00);
            $this->insertarFila($db, $loteViejo, $p . '-L3', pagoId: 3, fechaCarga: $pasado, monto: 103.00);
            $this->insertarFila(
                $db,
                $loteViejo,
                $p . '-L4',
                estado: 'conciliado',
                estadoConciliacion: 'conciliado',
                pagoId: 3,
                fechaCarga: $pasado,
                monto: 104.00
            );
            $this->insertarFila(
                $db,
                $loteViejo,
                $p . '-L5',
                estado: 'descartado',
                estadoConciliacion: 'descartado',
                fechaCarga: $pasado,
                monto: 105.00,
                tipo: 'debito'
            );
            $this->insertarFila(
                $db,
                $loteViejo,
                $p . '-L7',
                estado: null,
                fechaCarga: $pasado,
                monto: 107.00
            );
            // L8: conciliado solo por estado, con estado_conciliacion pendiente.
            $this->insertarFila(
                $db,
                $loteViejo,
                $p . '-L8',
                estado: 'conciliado',
                estadoConciliacion: 'pendiente',
                fechaCarga: $pasado,
                monto: 108.00
            );
            // L9: disponible pero con vínculo 1:1 vigente: no debe contar como pendiente.
            $idVinculado = $this->insertarFila($db, $loteViejo, $p . '-L9', fechaCarga: $pasado, monto: 109.00);
            $this->vincular($db, $idVinculado, $pagoLibre);

            // Dos lotes de relleno más recientes dejan al lote viejo fuera de la ventana.
            $this->insertarFila($db, $loteRelleno1, $p . '-R1', estado: 'conciliado', estadoConciliacion: 'conciliado', fechaCarga: '2021-01-01 00:00:00', monto: 110.00);
            $this->insertarFila($db, $loteRelleno2, $p . '-R2', estado: 'conciliado', estadoConciliacion: 'conciliado', fechaCarga: '2022-01-01 00:00:00', monto: 111.00);

            $this->insertarFila($db, $loteActivo, $p . '-L6', fechaCarga: $futuro, monto: 106.00);

            $model = new ConciliacionModel();
            $lotes = $model->obtenerLotes();

            $porLote = [];
            foreach ($lotes as $indice => $lote) {
                $porLote[$lote['lote_importacion']] = [$lote, $indice];
            }

            $this->assertTrue(isset($porLote[$loteViejo]), 'obtenerLotes debe incluir el lote histórico de prueba');
            $this->assertTrue(isset($porLote[$loteActivo]), 'obtenerLotes debe incluir el lote activo de prueba');
            if (!isset($porLote[$loteViejo], $porLote[$loteActivo])) {
                return;
            }

            [$filaVieja, $indiceViejo] = $porLote[$loteViejo];
            [$filaActiva, $indiceActivo] = $porLote[$loteActivo];

            foreach (['lote_importacion', 'banco', 'fecha_importacion', 'total_movimientos', 'conciliados', 'pendientes', 'descartados', 'es_activo'] as $clave) {
                $this->assertTrue(array_key_exists($clave, $filaVieja), "La métrica de lote debe incluir la clave {$clave}");
            }

            $this->assertEquals(1, $filaActiva['es_activo'], 'El lote más reciente debe marcarse como activo');
            $this->assertEquals(0, $filaVieja['es_activo'], 'Un lote fuera de la ventana no debe marcarse como activo');
            $this->assertTrue($indiceActivo < $indiceViejo, 'El lote más reciente debe listarse primero');

            foreach ([$loteRelleno1, $loteRelleno2] as $loteRelleno) {
                $this->assertTrue(isset($porLote[$loteRelleno]), 'obtenerLotes debe incluir los lotes de relleno');
                if (isset($porLote[$loteRelleno])) {
                    $this->assertEquals(1, $porLote[$loteRelleno][0]['es_activo'], 'Todo lote dentro de la ventana debe marcarse activo');
                }
            }

            $this->assertTrue(isset($lotes[0]), 'obtenerLotes debe devolver al menos un lote');
            if (isset($lotes[0])) {
                $this->assertEquals($loteActivo, $lotes[0]['lote_importacion'], 'El primer lote del listado debe ser el activo');
                $this->assertEquals(1, $lotes[0]['es_activo'], 'El primer lote del listado debe estar marcado como activo');
            }

            $this->assertEquals(8, (int)$filaVieja['total_movimientos'], 'El lote histórico debe reportar sus 8 movimientos');
            $this->assertEquals(2, (int)$filaVieja['conciliados'], 'Debe contar conciliados por cualquiera de los dos estados');
            $this->assertEquals(3, (int)$filaVieja['pendientes'], 'Solo los créditos disponibles reales y sin vínculo cuentan como pendientes');
            $this->assertEquals(1, (int)$filaVieja['descartados'], 'El lote histórico debe reportar 1 descartado');
            $this->assertEquals(1, (int)$filaActiva['pendientes'], 'El lote activo debe reportar su disponible real');
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testConciliarAbonoAceptaEstadoPendienteLegacy(): void {
        $db = $this->conexion();
        $p = $this->prefijo();
        $referencia = $p . '-LEGACY';

        $pagoLibre = $this->pagoLibre($db);
        if ($pagoLibre === null) {
            throw new \RuntimeException('No hay pagos libres para probar conciliarAbono legacy');
        }

        try {
            $extractoId = $this->insertarFila(
                $db,
                $p . '-LEGACY',
                $referencia,
                estado: 'pendiente',
                estadoConciliacion: 'pendiente',
                monto: 901.00
            );

            $model = new ConciliacionModel();
            $resultado = $model->conciliarAbono($extractoId, $pagoLibre, 1);

            $this->assertTrue(!empty($resultado['ok']), 'conciliarAbono debe aceptar una fila legacy con estado pendiente');
            $this->assertEquals('CONCILIACION_EXITOSA', $resultado['codigo'], 'La conciliación legacy debe completarse');

            $fila = $this->obtenerFila($db, $referencia);
            $this->assertTrue($fila !== false, 'La fila legacy debe existir');
            if ($fila !== false) {
                $this->assertEquals('conciliado', $fila['estado'], 'El estado debe quedar conciliado');
                $this->assertEquals('conciliado', $fila['estado_conciliacion'], 'El estado_conciliacion debe quedar conciliado');
                $this->assertEquals($pagoLibre, (int)$fila['pago_id'], 'El pago debe quedar vinculado');
            }

            $stmtVinculo = $db->prepare("SELECT COUNT(*) FROM conciliacion_abono_pago WHERE movimiento_id = :mov");
            $stmtVinculo->execute(['mov' => $extractoId]);
            $this->assertEquals(1, (int)$stmtVinculo->fetchColumn(), 'El vínculo 1:1 debe registrarse');
        } finally {
            $this->limpiar($db, $p);
        }
    }

    public function testCruceInteligenteAceptaEstadoPendiente(): void {
        $p = $this->prefijo();
        $referencia = $p . '-CRUCE-99112233';
        $referenciaConciliada = $p . '-CRUCE-99112234';

        $movimientoPendiente = [
            'id'                  => 990001,
            'banco'               => 'Banco Inexistente',
            'fecha_movimiento'    => '2099-12-31',
            'referencia_bancaria' => $referencia,
            'descripcion'         => 'Prueba estado pendiente',
            'monto'               => 987654321.99,
            'tipo_movimiento'     => 'credito',
            'estado'              => 'pendiente',
        ];
        $movimientoConciliado = [
            'id'                  => 990002,
            'banco'               => 'Banco Inexistente',
            'fecha_movimiento'    => '2099-12-31',
            'referencia_bancaria' => $referenciaConciliada,
            'descripcion'         => 'Prueba estado conciliado',
            'monto'               => 987654321.99,
            'tipo_movimiento'     => 'credito',
            'estado'              => 'conciliado',
        ];

        $service = new ConciliacionBancariaService();
        $resultado = $service->ejecutarCruceInteligente([$movimientoPendiente, $movimientoConciliado]);

        $this->assertEquals(1, count($resultado['sin_coincidencia']), 'El movimiento pendiente debe evaluarse y no coincidir');
        $this->assertEquals($referencia, $resultado['sin_coincidencia'][0]['extracto']['referencia_bancaria'], 'El movimiento pendiente debe aparecer en sin_coincidencia');

        $referenciasEvaluadas = [];
        foreach (['coincidencias_exactas', 'coincidencias_sugeridas', 'sin_coincidencia'] as $grupo) {
            foreach ($resultado[$grupo] as $item) {
                if (!empty($item['extracto']['referencia_bancaria'])) {
                    $referenciasEvaluadas[] = $item['extracto']['referencia_bancaria'];
                }
            }
        }
        $this->assertFalse(in_array($referenciaConciliada, $referenciasEvaluadas, true), 'Un movimiento conciliado debe seguir omitido del cruce');
    }

    public function testCruceInteligenteRespetaEstadoConciliacionContrario(): void {
        $p = $this->prefijo();

        $base = [
            'banco'               => 'Banco Inexistente',
            'fecha_movimiento'    => '2099-12-31',
            'monto'               => 987654321.99,
            'tipo_movimiento'     => 'credito',
        ];

        $movimientoContrario = array_merge($base, [
            'id'                  => 990011,
            'referencia_bancaria' => $p . '-CONTRA1',
            'descripcion'         => 'Estado conciliado en el segundo campo',
            'estado'              => 'disponible',
            'estado_conciliacion' => 'conciliado',
        ]);
        $movimientoAnulado = array_merge($base, [
            'id'                  => 990012,
            'referencia_bancaria' => $p . '-CONTRA2',
            'descripcion'         => 'Movimiento anulado',
            'estado'              => 'anulado',
            'estado_conciliacion' => 'pendiente',
        ]);
        $movimientoValido = array_merge($base, [
            'id'                  => 990013,
            'referencia_bancaria' => $p . '-CONTRA3',
            'descripcion'         => 'Disponible con conciliación pendiente',
            'estado'              => 'disponible',
            'estado_conciliacion' => 'pendiente',
        ]);

        $service = new ConciliacionBancariaService();
        $resultado = $service->ejecutarCruceInteligente([$movimientoContrario, $movimientoAnulado, $movimientoValido]);

        $this->assertEquals(1, count($resultado['sin_coincidencia']), 'Solo el movimiento válido debe evaluarse');
        $this->assertEquals($p . '-CONTRA3', $resultado['sin_coincidencia'][0]['extracto']['referencia_bancaria'], 'El movimiento disponible/pendiente debe evaluarse');

        $referenciasEvaluadas = [];
        foreach (['coincidencias_exactas', 'coincidencias_sugeridas', 'sin_coincidencia'] as $grupo) {
            foreach ($resultado[$grupo] as $item) {
                if (!empty($item['extracto']['referencia_bancaria'])) {
                    $referenciasEvaluadas[] = $item['extracto']['referencia_bancaria'];
                }
            }
        }
        $this->assertFalse(in_array($p . '-CONTRA1', $referenciasEvaluadas, true), 'Un estado_conciliacion conciliado debe omitir el movimiento');
        $this->assertFalse(in_array($p . '-CONTRA2', $referenciasEvaluadas, true), 'Un estado anulado debe omitir el movimiento');
    }
}
