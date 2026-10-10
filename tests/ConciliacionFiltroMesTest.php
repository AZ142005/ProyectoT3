<?php
namespace Tests;

use App\Controllers\ConciliacionController;
use App\Core\Database;
use PDO;

/**
 * T3 (ODD compresion-retencion-comprobantes): la bandeja de conciliación solo
 * muestra los pagos pendientes del mes actual. El filtro se aplica sobre
 * $pagosPendientes (afecta a las filas «sin_extracto» y a la variable de vista)
 * sin tocar los cruces, extractos ni lotes.
 *
 * La prueba funcional renderiza el controlador real contra BD con el patrón de
 * ConciliacionMontoDisparTest; si no hay fixtures base (persona + unidad) se
 * marca skip. La parte estática cubre el cableado: el filtro vive entre el
 * fetch y el loop de sin_extracto.
 */
class ConciliacionFiltroMesTest extends TestCase {

    private const ZONA_APP = 'America/Caracas';

    /**
     * Extrae el cuerpo de un método público de un archivo fuente.
     */
    private function extraerMetodo(string $rutaArchivo, string $metodo): string {
        $contenido = (string) file_get_contents($rutaArchivo);
        preg_match('/public function ' . preg_quote($metodo, '/') . '\(.*?\)(.*?)(?=public function|private function|protected function|\Z)/s', $contenido, $m);
        return $m[1] ?? '';
    }

    /**
     * Inserta un pago pendiente de prueba con la fecha indicada.
     * Retorna el id insertado o 0 si la BD no tiene fixtures base.
     */
    private function crearPagoPendiente(PDO $db, string $referencia, string $fechaPago): int {
        $persona = $db->query("SELECT id FROM personas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $unidad = $db->query("SELECT id FROM unidades LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$persona || !$unidad) {
            return 0;
        }

        $stmt = $db->prepare("
            INSERT INTO pagos (residente_id, unidad_id, monto, fecha_pago, metodo_pago, referencia, banco_receptor, estado)
            VALUES (:residente_id, :unidad_id, 10.00, :fecha_pago, 'transferencia', :referencia, 'Banco de Venezuela', 'PENDIENTE')
        ");
        $stmt->execute([
            'residente_id' => intval($persona['id']),
            'unidad_id'    => intval($unidad['id']),
            'fecha_pago'   => $fechaPago,
            'referencia'   => $referencia,
        ]);

        return intval($db->lastInsertId());
    }

    /**
     * Cableado estático: el filtro del mes se aplica a $pagosPendientes después
     * del fetch y antes de armar las filas «sin_extracto» y de pasar el arreglo
     * a la vista.
     */
    public function testIndexAplicaFiltroDeMesAntesDeSinExtractoYVista(): void {
        $cuerpo = $this->extraerMetodo(BASE_PATH . '/app/controllers/ConciliacionController.php', 'index');
        $this->assertTrue($cuerpo !== '', 'ConciliacionController::index() debe existir');

        $posFetch  = strpos($cuerpo, '$pagosPendientes = $conciliacionModel->obtenerTodosPagosPendientes();');
        $posFiltro = strpos($cuerpo, '$pagosPendientes = array_values(array_filter(');
        $posLoop   = strpos($cuerpo, 'foreach ($pagosPendientes as $pago)');
        $posVista  = strpos($cuerpo, "'pagosPendientes'       => \$pagosPendientes,");

        $this->assertTrue($posFetch !== false && $posFiltro !== false && $posLoop !== false && $posVista !== false,
            'index() debe conservar el fetch, el filtro por mes, el loop sin_extracto y el paso a la vista');

        if ($posFetch === false || $posFiltro === false || $posLoop === false || $posVista === false) {
            return;
        }

        $this->assertTrue($posFetch < $posFiltro,
            'El filtro del mes debe aplicarse después de obtener los pendientes');
        $this->assertTrue($posFiltro < $posLoop,
            'El filtro debe aplicarse antes de armar las filas sin_extracto');
        $this->assertTrue($posFiltro < $posVista,
            'La vista debe recibir el arreglo ya filtrado');

        // Lógica de prefijo YYYY-MM en la zona horaria de la app y exclusión de fecha_pago vacía.
        $this->assertStringContains("new \\DateTimeZone('America/Caracas')", $cuerpo,
            'El corte de mes debe evaluarse en la zona horaria de la app (America/Caracas)');
        $this->assertStringContains("->format('Y-m')", $cuerpo,
            'El filtro debe comparar por prefijo de mes (YYYY-MM)');
        $this->assertStringContains("!empty(\$pago['fecha_pago'])", $cuerpo,
            'Una fecha_pago vacía o nula debe quedar excluida');
        $this->assertStringContains("str_starts_with((string) \$pago['fecha_pago'], \$prefijoMesActual)", $cuerpo,
            'La comparación debe ser por prefijo del mes actual sobre fecha_pago');
    }

    /**
     * La vista avisa de forma discreta que la lista de pagos pendientes está
     * acotada al mes actual, mostrando el mes/año provisto por el controlador.
     */
    public function testVistaMuestraAvisoDeMesActual(): void {
        $vista = (string) file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $this->assertStringContains('Solo se muestran los pagos del mes actual', $vista,
            'La vista debe avisar que la lista de pagos pendientes está acotada al mes actual');
        $this->assertStringContains('$mesPagosActual', $vista,
            'El aviso debe mostrar el mes/año provisto por el controlador');
    }

    /**
     * Render real del controlador (sin HTTP, con sesión admin simulada) contra
     * un lote inexistente: sin extractos no hay cruces, así que todo pendiente
     * visible cae en «sin_extracto». El pago del mes actual debe listarse y el
     * de un mes anterior no debe aparecer en el HTML.
     */
    public function testIndexSoloListaPagosDelMesActualComoSinExtracto(): void {
        $db = Database::getConnection();
        $zona = new \DateTimeZone(self::ZONA_APP);
        $hoy = new \DateTimeImmutable('now', $zona);

        // Último día del mes actual: garantiza la primera posición del orden desc por fecha.
        $fechaMesActual   = $hoy->modify('last day of this month')->format('Y-m-d');
        $fechaMesAnterior = $hoy->modify('first day of last month')->format('Y-m-d');

        $referenciaActual   = 'FMT' . mt_rand(100000, 999999);
        $referenciaAnterior = 'FMT' . mt_rand(100000, 999999);

        $idActual   = $this->crearPagoPendiente($db, $referenciaActual, $fechaMesActual);
        $idAnterior = $this->crearPagoPendiente($db, $referenciaAnterior, $fechaMesAnterior);

        if ($idActual === 0 || $idAnterior === 0) {
            foreach ([$idActual, $idAnterior] as $id) {
                if ($id > 0) {
                    $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $id]);
                }
            }
            $this->skip('Datos insuficientes: se requiere al menos una persona y una unidad.');
            return;
        }

        $sesionPrevia = $_SESSION['auth_user'] ?? null;
        $lotePrevio = $_GET['lote'] ?? null;

        try {
            $_SESSION['auth_user'] = ['id' => 1, 'name' => 'Admin Test', 'role' => 'admin'];
            $_GET['lote'] = 'LOTE-FILTRO-MES-' . uniqid();

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

            $atributoActual   = 'name="pago_id" value="' . $idActual . '"';
            $atributoAnterior = 'name="pago_id" value="' . $idAnterior . '"';

            $enSinExtracto = 0;
            $apareceAnterior = false;
            foreach ($filas as $filaHtml) {
                if ($filaHtml[1] === 'sin_extracto' && str_contains($filaHtml[2], $atributoActual)) {
                    $enSinExtracto++;
                }
                if (str_contains($filaHtml[2], $atributoAnterior)) {
                    $apareceAnterior = true;
                }
            }

            $this->assertEquals(1, $enSinExtracto,
                'El pago del mes actual debe listarse exactamente una vez como sin_extracto');
            $this->assertFalse($apareceAnterior,
                'El pago de un mes anterior no debe aparecer en la bandeja filtrada');
        } finally {
            $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $idActual]);
            $db->prepare("DELETE FROM pagos WHERE id = :id")->execute(['id' => $idAnterior]);
        }
    }
}
