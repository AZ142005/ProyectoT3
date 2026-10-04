<?php
namespace Tests;

use App\Controllers\ConciliacionController;
use App\Core\Database;
use App\Services\ConciliacionBancariaService;
use PDO;

/**
 * RF 27 (opción A): detección de "Monto dispar" en el cruce inteligente y saneo
 * de la bandeja de conciliación: pagos conservados en las inconsistencias,
 * fechas nulas sin 01/01/1970 y dedupe del pago ya señalado.
 */
class ConciliacionMontoDisparTest extends TestCase {

    /**
     * Inserta un pago pendiente de prueba con la referencia indicada.
     * Retorna el id insertado o 0 si la BD no tiene fixtures base.
     */
    private function crearPagoPendiente(PDO $db, string $referencia, float $monto, ?string $fecha = null): int {
        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $unidad = $db->query("SELECT id FROM unidades LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$persona || !$unidad) {
            return 0;
        }

        $stmt = $db->prepare("
            INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, banco_receptor, estado)
            VALUES (:residente_id, :unidad_id, :monto, :fecha_pago, 'transferencia', :referencia, 'Banco de Venezuela', 'PENDIENTE')
        ");
        $stmt->execute([
            'residente_id' => intval($persona['id']),
            'unidad_id'    => intval($unidad['id']),
            'monto'        => $monto,
            'fecha_pago'   => $fecha ?? date('Y-m-d'),
            'referencia'   => $referencia
        ]);

        return intval($db->lastInsertId());
    }

    /**
     * Extracto disponible con la referencia del pago pero monto distinto:
     * debe reportarse como inconsistencia "Monto dispar" con pago adjunto.
     */
    public function testDetectaMontoDisparComoInconsistencia(): void {
        $db = Database::getConnection();
        $service = new ConciliacionBancariaService();

        $referencia = '88664422' . mt_rand(10000, 99999);
        $pagoId = $this->crearPagoPendiente($db, $referencia, 950.00);

        if ($pagoId === 0) {
            $this->skip('Datos insuficientes: se requiere al menos una persona y una unidad.');
            return;
        }

        try {
            $movimiento = [
                'id'                  => 999998,
                'banco'               => 'Banco de Venezuela',
                'fecha_movimiento'    => date('Y-m-d'),
                'referencia_bancaria' => $referencia,
                'descripcion'         => 'PAGO TEST MONTO DISPAR',
                'monto'               => 1200.37,
                'tipo_movimiento'     => 'credito',
                'estado'              => 'disponible'
            ];

            $resultado = $service->ejecutarCruceInteligente([$movimiento]);

            $this->assertEquals(0, count($resultado['coincidencias_exactas']),
                'Un monto distinto no debe clasificarse como coincidencia exacta');
            $this->assertEquals(0, count($resultado['coincidencias_sugeridas']),
                'Un monto distinto no debe clasificarse como coincidencia sugerida');
            $this->assertEquals(1, count($resultado['inconsistencias']),
                'Debe reportarse exactamente 1 inconsistencia por monto dispar');
            $this->assertEquals(0, count($resultado['sin_coincidencia']),
                'El movimiento con candidato por referencia no debe caer en sin_coincidencia');

            $inconsistencia = $resultado['inconsistencias'][0];
            $this->assertEquals('Monto dispar', $inconsistencia['motivo'] ?? null,
                'El motivo debe ser "Monto dispar"');
            $this->assertTrue(($inconsistencia['extracto'] ?? null) !== null,
                'La inconsistencia debe conservar el extracto');
            $this->assertTrue(($inconsistencia['pago'] ?? null) !== null,
                'La inconsistencia debe conservar el pago candidato');
            $this->assertEquals($pagoId, intval($inconsistencia['pago']['id'] ?? 0),
                'El pago señalado debe ser el fixture insertado');

            $alerta = $inconsistencia['alerta'] ?? '';
            $this->assertTrue(str_contains($alerta, '1.200,37'),
                'La alerta debe incluir el monto del extracto formateado');
            $this->assertTrue(str_contains($alerta, '950,00'),
                'La alerta debe incluir el monto del pago formateado');
        } finally {
            $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $pagoId]);
        }
    }

    /**
     * Un movimiento sin pagos con esa referencia debe seguir en sin_coincidencia
     * (no regresión del camino sin candidatos).
     */
    public function testSinCandidatosSigueEnSinCoincidencia(): void {
        $service = new ConciliacionBancariaService();

        $movimiento = [
            'id'                  => 999997,
            'banco'               => 'Banco de Venezuela',
            'fecha_movimiento'    => date('Y-m-d'),
            'referencia_bancaria' => '7711223344' . mt_rand(10000, 99999),
            'descripcion'         => 'PAGO TEST SIN CANDIDATOS',
            'monto'               => 4321.09,
            'tipo_movimiento'     => 'credito',
            'estado'              => 'disponible'
        ];

        $resultado = $service->ejecutarCruceInteligente([$movimiento]);

        $this->assertEquals(0, count($resultado['coincidencias_exactas']),
            'No debe haber coincidencias exactas sin pagos con esa referencia');
        $this->assertEquals(0, count($resultado['coincidencias_sugeridas']),
            'No debe haber coincidencias sugeridas sin pagos con esa referencia');
        $this->assertEquals(0, count($resultado['inconsistencias']),
            'No debe inventarse una inconsistencia de monto dispar sin candidatos');
        $this->assertEquals(1, count($resultado['sin_coincidencia']),
            'Sin pagos con esa referencia el movimiento debe seguir en sin_coincidencia');
        $this->assertEquals('SIN_COINCIDENCIA', $resultado['sin_coincidencia'][0]['clasificacion'] ?? null,
            'La clasificación debe conservarse como SIN_COINCIDENCIA');
    }

    /**
     * Inconsistencias con extracto null (referencias históricas) deben conservar
     * pago/alerta y jamás producir fechas 01/01/1970 en la bandeja.
     */
    public function testInconsistenciaConExtractoNullNoRompeFechas(): void {
        $controlador = file_get_contents(BASE_PATH . '/app/controllers/ConciliacionController.php');
        $vista = file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        // Controlador: conserva pago/alerta y protege la fecha con fallback null-safe.
        $this->assertStringContains("\$match['pago'] ?? null", $controlador,
            'El controlador debe conservar el pago de la inconsistencia');
        $this->assertStringContains("\$match['alerta'] ?? null", $controlador,
            'El controlador debe propagar la alerta de la inconsistencia');
        $this->assertStringContains("\$match['extracto']['fecha_movimiento'] ?? \$match['pago']['fecha_pago'] ?? null", $controlador,
            'La fecha de la inconsistencia debe usar fallback extracto → pago → null');
        $this->assertMatchesRegex("/\['coincidencias_exactas',\s*'coincidencias_sugeridas',\s*'inconsistencias'\]/", $controlador,
            'El dedupe de la bandeja debe incluir las inconsistencias como origen');
        $this->assertStringContains("!empty(\$match['pago'])", $controlador,
            'El dedupe debe ser null-safe para inconsistencias sin pago');

        // Vista: renderiza motivo/alerta de la inconsistencia y blinda entidades nulas.
        $this->assertStringContains("\$fila['motivo']", $vista,
            'La vista debe renderizar el motivo de la inconsistencia');
        $this->assertStringContains("\$fila['alerta']", $vista,
            'La vista debe renderizar la alerta de la inconsistencia');
        $this->assertStringContains("=== 'inconsistencia' && !empty(\$fila['motivo'])", $vista,
            'El bloque de motivo/alerta debe limitarse a filas de inconsistencia con motivo');
        $this->assertMatchesRegex(
            '#!empty\(\$fila\[.fecha.\]\)\s*\?\s*e\(date\(.d/m/Y.,\s*strtotime\(\$fila\[.fecha.\]\)\)\)\s*:\s*.{1,6}#',
            $vista,
            'La fecha solo debe formatearse cuando existe; vacía debe mostrarse con guion largo'
        );
        $this->assertStringContains("\$pago = \$fila['pago'] ?? null;", $vista,
            'La vista debe blindar el acceso a pago nulo');
        $this->assertStringContains("\$extracto = \$fila['extracto'] ?? null;", $vista,
            'La vista debe blindar el acceso a extracto nulo');
    }

    /**
     * Render real del controlador (sin HTTP, con sesión admin simulada) contra un
     * extracto en BD que solo difiere en monto con un pago del mismo lote:
     * el pago debe aparecer en la fila de inconsistencia y NO duplicarse como sin_extracto.
     */
    public function testPagoDeInconsistenciaNoSeDuplicaComoSinExtracto(): void {
        $db = Database::getConnection();

        $referencia = '55998877' . mt_rand(10000, 99999);
        $lote = 'LOTE-TEST-MONTODISPAR-' . uniqid();
        $fecha = '9999-12-31';

        $pagoId = $this->crearPagoPendiente($db, $referencia, 777.76, $fecha);
        if ($pagoId === 0) {
            $this->skip('Datos insuficientes: se requiere al menos una persona y una unidad.');
            return;
        }

        $stmtExt = $db->prepare("
            INSERT INTO extractos_bancarios (banco, fecha_movimiento, referencia_bancaria, referencia, monto, tipo_movimiento, estado, estado_conciliacion, lote_importacion)
            VALUES ('Banco de Venezuela', :fecha, :ref1, :ref2, 888.87, 'credito', 'disponible', 'pendiente', :lote)
        ");
        $stmtExt->execute([
            'fecha' => $fecha,
            'ref1'  => $referencia,
            'ref2'  => $referencia,
            'lote'  => $lote
        ]);
        $extractoId = intval($db->lastInsertId());

        $sesionPrevia = $_SESSION['auth_user'] ?? null;
        $lotePrevio = $_GET['lote'] ?? null;

        try {
            $_SESSION['auth_user'] = ['id' => 1, 'name' => 'Admin Test', 'role' => 'admin'];
            $_GET['lote'] = $lote;

            ob_start();
            try {
                (new ConciliacionController())->index();
            } finally {
                $html = ob_get_clean();
                if ($sesionPrevia === null) {
                    unset($_SESSION['auth_user']);
                } else {
                    $_SESSION['auth_user'] = $sesionPrevia;
                }
                if ($lotePrevio === null) {
                    unset($_GET['lote']);
                } else {
                    $_GET['lote'] = $lotePrevio;
                }
            }

            preg_match_all('/<tr[^>]*data-categoria="([^"]+)"[^>]*>(.*?)<\/tr>/s', $html, $filas, PREG_SET_ORDER);

            $atributoPago = 'name="pago_id" value="' . $pagoId . '"';
            $enInconsistencia = 0;
            $enSinExtracto = 0;
            foreach ($filas as $filaHtml) {
                if (!str_contains($filaHtml[2], $atributoPago)) {
                    continue;
                }
                if ($filaHtml[1] === 'inconsistencia') {
                    $enInconsistencia++;
                }
                if ($filaHtml[1] === 'sin_extracto') {
                    $enSinExtracto++;
                }
            }

            $this->assertEquals(1, $enInconsistencia,
                'La bandeja debe listar el pago dentro de la inconsistencia de monto dispar');
            $this->assertEquals(0, $enSinExtracto,
                'El pago ya señalado en una inconsistencia no debe duplicarse como sin_extracto');
            $this->assertStringContains('Monto dispar', $html,
                'La bandeja debe renderizar el motivo "Monto dispar"');
        } finally {
            $db->prepare("DELETE FROM extractos_bancarios WHERE id = :id")->execute(['id' => $extractoId]);
            $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $pagoId]);
        }
    }
}
