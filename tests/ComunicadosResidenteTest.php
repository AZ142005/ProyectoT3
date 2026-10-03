<?php
namespace Tests;

use App\Core\Database;
use App\Models\ComunicadosModel;
use PDO;

/**
 * Pruebas del panel del residente con comunicados reales (solo visualización):
 * - obtenerPorResidente: visibilidad global, por edificio y por unidad; excluye
 *   otros destinos, borrados lógicos y publicaciones futuras; orden descendente
 *   y paginación.
 * - Contrato de vista y controladores: dashboard con comunicados y sin listas
 *   de facturas/comprobantes, y resolución unificada residente -> unidad/edificio.
 */
class ComunicadosResidenteTest extends TestCase {

    private const PREFIJO = 'TEST-RES-COM-';

    // Los destinos de comunicados no declaran FK en el esquema; estos IDs
    // sintéticos aíslan la visibilidad sin depender de datos sembrados.
    private const EDIFICIO_A = 900001;
    private const EDIFICIO_B = 900002;
    private const UNIDAD_A   = 700001;
    private const UNIDAD_B   = 700002;

    private function adminId(PDO $db): int {
        $id = $db->query("SELECT id FROM usuarios ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $id !== false ? intval($id) : 1000001;
    }

    /**
     * Fecha de publicación relativa al reloj de la base de datos. El entorno
     * tiene desfase PHP/MySQL; anclar los datos a NOW() de la BD mantiene los
     * casos estables ante el filtro de visibilidad, que usa el reloj de PHP.
     */
    private function dbTime(PDO $db, int $deltaSeconds = 0): string {
        $now = (string)$db->query('SELECT NOW()')->fetchColumn();
        return date('Y-m-d H:i:s', strtotime($now) + $deltaSeconds);
    }

    private function crear(PDO $db, ComunicadosModel $model, array $overrides = []): int {
        $sufijo = bin2hex(random_bytes(4));
        $datos = array_merge([
            'titulo'            => self::PREFIJO . $sufijo,
            'contenido'         => '<p>Contenido de prueba ' . $sufijo . '</p>',
            'nivel_urgencia'    => 'normal',
            'edificio_id'       => null,
            'unidad_id'         => null,
            'admin_id'          => $this->adminId($db),
            'fecha_publicacion' => $this->dbTime($db, -60),
        ], $overrides);

        return $model->crearComunicado($datos);
    }

    private function idsVisibles(ComunicadosModel $model, ?int $edificioId, ?int $unidadId): array {
        $resultado = $model->obtenerPorResidente($edificioId, $unidadId, 1, 100);
        return array_map('intval', array_column($resultado['datos'], 'id'));
    }

    private function limpiar(PDO $db): void {
        $db->exec("DELETE FROM comunicados WHERE titulo LIKE '" . self::PREFIJO . "%'");
    }

    public function testGlobalEsVisibleParaCualquierResidente(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $id = $this->crear($db, $model);

            $visibles = $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A);
            $this->assertTrue(in_array($id, $visibles, true),
                'Un comunicado global debe ser visible para cualquier residente');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testComunicadoDeEdificioVisibleSoloParaEseEdificio(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $id = $this->crear($db, $model, ['edificio_id' => self::EDIFICIO_A]);

            $this->assertTrue(in_array($id, $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A), true),
                'Un comunicado de edificio debe ser visible para un residente de ese edificio');

            $this->assertFalse(in_array($id, $this->idsVisibles($model, self::EDIFICIO_B, self::UNIDAD_A), true),
                'Un comunicado de edificio no debe ser visible para residentes de otro edificio');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testComunicadoDeUnidadVisibleSoloParaEsaUnidad(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $id = $this->crear($db, $model, [
                'edificio_id' => self::EDIFICIO_A,
                'unidad_id'   => self::UNIDAD_A,
            ]);

            $this->assertTrue(in_array($id, $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A), true),
                'Un comunicado de unidad debe ser visible para esa unidad');

            $this->assertFalse(in_array($id, $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_B), true),
                'Un comunicado de unidad no debe ser visible para otra unidad');

            $this->assertFalse(in_array($id, $this->idsVisibles($model, null, null), true),
                'Un comunicado de unidad no debe ser visible sin contexto de unidad');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testComunicadoBorradoExcluido(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $id = $this->crear($db, $model);
            $this->assertTrue($model->softDelete($id), 'El borrado lógico debe ejecutarse');

            $this->assertFalse(in_array($id, $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A), true),
                'Un comunicado borrado lógicamente no debe ser visible');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testComunicadoFuturoExcluido(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $id = $this->crear($db, $model, [
                'fecha_publicacion' => $this->dbTime($db, 86400),
            ]);

            $this->assertFalse(in_array($id, $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A), true),
                'Un comunicado con fecha de publicación futura no debe ser visible');
        } finally {
            $this->limpiar($db);
        }
    }

    /**
     * Regresión: la lectura debe comparar contra el reloj de PHP (el mismo con
     * el que se escribe). Con desfase PHP/MySQL, comparar contra NOW() de MySQL
     * dejaba invisible un comunicado recién publicado durante horas.
     */
    public function testComunicadoRecienPublicadoEsVisibleDeInmediato(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            // Sin fecha explícita: usa el reloj de PHP, igual que el flujo real de publicación.
            $id = $model->crearComunicado([
                'titulo'         => self::PREFIJO . bin2hex(random_bytes(4)),
                'contenido'      => '<p>Recién publicado</p>',
                'nivel_urgencia' => 'normal',
                'edificio_id'    => null,
                'unidad_id'      => null,
                'admin_id'       => $this->adminId($db),
            ]);

            $this->assertTrue(in_array($id, $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A), true),
                'Un comunicado recién publicado (reloj PHP) debe ser visible de inmediato');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testOrdenDescendentePorFechaDePublicacion(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $viejo = $this->crear($db, $model, [
                'fecha_publicacion' => $this->dbTime($db, -7200),
            ]);
            $nuevo = $this->crear($db, $model, [
                'fecha_publicacion' => $this->dbTime($db, -3600),
            ]);

            $visibles = $this->idsVisibles($model, self::EDIFICIO_A, self::UNIDAD_A);
            $posNuevo = array_search($nuevo, $visibles, true);
            $posViejo = array_search($viejo, $visibles, true);

            $this->assertTrue($posNuevo !== false && $posViejo !== false,
                'Ambos comunicados de prueba deben ser visibles');
            $this->assertTrue($posNuevo < $posViejo,
                'El comunicado más reciente debe aparecer primero');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testPaginacionYTotal(): void {
        $db = Database::getConnection();
        $model = new ComunicadosModel();

        try {
            $this->crear($db, $model);
            $this->crear($db, $model);
            $this->crear($db, $model);

            $resultado = $model->obtenerPorResidente(null, null, 1, 2);

            $this->assertTrue($resultado['total'] >= 3,
                'El total debe incluir todos los comunicados visibles');
            $this->assertTrue(count($resultado['datos']) <= 2,
                'La página no debe exceder el tamaño solicitado');
            $this->assertTrue($resultado['totalPaginas'] >= 2,
                'Con más registros que el tamaño de página debe haber al menos 2 páginas');
        } finally {
            $this->limpiar($db);
        }
    }

    public function testDashboardMuestraComunicadosYSinListasDePago(): void {
        $view = (string)file_get_contents(dirname(__DIR__) . '/app/views/residente/dashboard.php');

        $this->assertStringContains('Comunicados', $view,
            'El dashboard del residente debe mostrar el apartado de Comunicados');
        $this->assertStringContains('/residente/cartelera', $view,
            'El dashboard debe enlazar a la cartelera completa');
        $this->assertFalse(str_contains($view, 'Facturas Pendientes'),
            'El dashboard no debe contener la sección Facturas Pendientes');
        $this->assertFalse(str_contains($view, 'Comprobantes Recientes'),
            'El dashboard no debe contener la sección Comprobantes Recientes');
        $this->assertFalse(str_contains($view, '#modalComprobante'),
            'El modal de comprobantes debe haberse eliminado del dashboard');
    }

    public function testControladoresUsanResolucionUnificadaYComunicados(): void {
        $residenteController = (string)file_get_contents(dirname(__DIR__) . '/app/controllers/ResidenteController.php');
        $comunicadoController = (string)file_get_contents(dirname(__DIR__) . '/app/controllers/ComunicadoController.php');
        $personasModel = (string)file_get_contents(dirname(__DIR__) . '/app/models/PersonasModel.php');

        $this->assertStringContains('obtenerPorResidente', $residenteController,
            'ResidenteController::dashboard debe cargar comunicados con obtenerPorResidente');
        $this->assertStringContains('getAuthenticatedResidente', $comunicadoController,
            'ComunicadoController::carteleraResidente debe usar la resolución del portal');
        $this->assertStringContains('u.edificio_id AS edificio_id', $personasModel,
            'PersonasModel::getResidenteDetails debe exponer el edificio de la unidad');

        preg_match('/public function carteleraResidente\(\)(.*?)(?=public function|private function|\z)/s', $comunicadoController, $m);
        $this->assertTrue(count($m) > 1, 'carteleraResidente() debe existir');
        if (count($m) > 1) {
            $this->assertFalse(str_contains($m[1], 'propietario_id'),
                'La cartelera no debe resolver la unidad vía propietario_id');
        }
    }
}
