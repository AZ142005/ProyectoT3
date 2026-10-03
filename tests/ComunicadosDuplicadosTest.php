<?php
namespace Tests;

use App\Core\Database;
use App\Models\ComunicadosModel;
use PDO;

/**
 * Pruebas Fase 18 — anti-duplicados de comunicados:
 * - existeDuplicadoReciente: duplicado inmediato, contenido distinto, fuera de
 *   ventana, borrado lógico y otro administrador.
 * - El controlador integra rate limit (10/hora/admin) y la guarda de duplicado
 *   antes de insertar/encolar correos.
 */
class ComunicadosDuplicadosTest extends TestCase {

    private const PREFIJO = 'TEST-F18-COM-';

    private function adminIds(PDO $db): array {
        return array_map('intval', $db->query("SELECT id FROM usuarios ORDER BY id ASC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN));
    }

    private function crear(ComunicadosModel $model, int $adminId, string $sufijo, array $overrides = []): int {
        $datos = array_merge([
            'titulo'            => self::PREFIJO . $sufijo,
            'contenido'         => '<p>Contenido de prueba ' . $sufijo . '</p>',
            'nivel_urgencia'    => 'normal',
            'edificio_id'       => null,
            'unidad_id'         => null,
            'admin_id'          => $adminId,
        ], $overrides);

        return $model->crearComunicado($datos);
    }

    private function limpiarComunicados(PDO $db): void {
        $db->exec("DELETE FROM comunicados WHERE titulo LIKE '" . self::PREFIJO . "%'");
    }

    public function testDuplicadoRecienteDetectado(): void {
        $db = Database::getConnection();
        $adminIds = $this->adminIds($db);
        if (empty($adminIds)) {
            $this->skip('No hay usuarios administradores en la base de datos');
            return;
        }

        $model = new ComunicadosModel();
        $sufijo = bin2hex(random_bytes(4));
        $adminId = $adminIds[0];

        try {
            $id = $this->crear($model, $adminId, $sufijo);
            $this->assertTrue($id > 0, 'El comunicado debe crearse');

            $esDuplicado = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Contenido de prueba ' . $sufijo . '</p>',
                null,
                null,
                $adminId
            );
            $this->assertTrue($esDuplicado, 'La republicación inmediata idéntica debe detectarse como duplicado');
        } finally {
            $this->limpiarComunicados($db);
        }
    }

    public function testContenidoDistintoNoEsDuplicado(): void {
        $db = Database::getConnection();
        $adminIds = $this->adminIds($db);
        if (empty($adminIds)) {
            $this->skip('No hay usuarios administradores en la base de datos');
            return;
        }

        $model = new ComunicadosModel();
        $sufijo = bin2hex(random_bytes(4));
        $adminId = $adminIds[0];

        try {
            $this->crear($model, $adminId, $sufijo);

            $esDuplicado = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Otro contenido completamente distinto ' . $sufijo . '</p>',
                null,
                null,
                $adminId
            );
            $this->assertFalse($esDuplicado, 'Un contenido distinto no debe considerarse duplicado');
        } finally {
            $this->limpiarComunicados($db);
        }
    }

    public function testFueraDeVentanaNoEsDuplicado(): void {
        $db = Database::getConnection();
        $adminIds = $this->adminIds($db);
        if (empty($adminIds)) {
            $this->skip('No hay usuarios administradores en la base de datos');
            return;
        }

        $model = new ComunicadosModel();
        $sufijo = bin2hex(random_bytes(4));
        $adminId = $adminIds[0];

        try {
            // Publicado hace 30 minutos
            $this->crear($model, $adminId, $sufijo, [
                'fecha_publicacion' => date('Y-m-d H:i:s', time() - 1800),
            ]);

            $enVentanaCorta = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Contenido de prueba ' . $sufijo . '</p>',
                null,
                null,
                $adminId,
                10
            );
            $this->assertFalse($enVentanaCorta, 'Un comunicado de hace 30 minutos no debe bloquear con ventana de 10');

            $enVentanaLarga = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Contenido de prueba ' . $sufijo . '</p>',
                null,
                null,
                $adminId,
                60
            );
            $this->assertTrue($enVentanaLarga, 'Con ventana de 60 minutos el comunicado reciente sí debe detectarse');
        } finally {
            $this->limpiarComunicados($db);
        }
    }

    public function testComunicadoBorradoNoCuenta(): void {
        $db = Database::getConnection();
        $adminIds = $this->adminIds($db);
        if (empty($adminIds)) {
            $this->skip('No hay usuarios administradores en la base de datos');
            return;
        }

        $model = new ComunicadosModel();
        $sufijo = bin2hex(random_bytes(4));
        $adminId = $adminIds[0];

        try {
            $id = $this->crear($model, $adminId, $sufijo);
            $this->assertTrue($model->softDelete($id), 'El borrado lógico debe ejecutarse');

            $esDuplicado = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Contenido de prueba ' . $sufijo . '</p>',
                null,
                null,
                $adminId
            );
            $this->assertFalse($esDuplicado, 'Un comunicado borrado no debe contar como duplicado');
        } finally {
            $this->limpiarComunicados($db);
        }
    }

    public function testOtroAdminNoBloquea(): void {
        $db = Database::getConnection();
        $adminIds = $this->adminIds($db);
        if (empty($adminIds)) {
            $this->skip('No hay usuarios administradores en la base de datos');
            return;
        }

        $model = new ComunicadosModel();
        $sufijo = bin2hex(random_bytes(4));
        // Si existe un segundo admin real se usa; si no, un ID distinto sintético
        // (la consulta solo compara admin_id, sin FK) para cubrir el criterio.
        $otroAdminId = $adminIds[1] ?? ($adminIds[0] + 100000);

        try {
            $this->crear($model, $adminIds[0], $sufijo);

            $esDuplicado = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Contenido de prueba ' . $sufijo . '</p>',
                null,
                null,
                $otroAdminId
            );
            $this->assertFalse($esDuplicado, 'Otro administrador no debe quedar bloqueado por el comunicado de un colega');
        } finally {
            $this->limpiarComunicados($db);
        }
    }

    public function testDestinoDistintoNoEsDuplicado(): void {
        $db = Database::getConnection();
        $adminIds = $this->adminIds($db);
        if (empty($adminIds)) {
            $this->skip('No hay usuarios administradores en la base de datos');
            return;
        }

        $model = new ComunicadosModel();
        $sufijo = bin2hex(random_bytes(4));
        $adminId = $adminIds[0];

        try {
            // Global (sin destinos)
            $this->crear($model, $adminId, $sufijo);

            // Mismos título/contenido pero dirigido a una unidad: no es duplicado
            $esDuplicado = $model->existeDuplicadoReciente(
                self::PREFIJO . $sufijo,
                '<p>Contenido de prueba ' . $sufijo . '</p>',
                null,
                999999,
                $adminId
            );
            $this->assertFalse($esDuplicado, 'Un destino distinto (null <=> unidad) no debe considerarse duplicado');
        } finally {
            $this->limpiarComunicados($db);
        }
    }

    public function testControladorIntegraRateLimitYGuardaDuplicado(): void {
        $contenido = (string)file_get_contents(dirname(__DIR__) . '/app/controllers/ComunicadoController.php');

        $this->assertStringContains("RateLimiter::attempt('comunicado_' . \$adminId, 10, 3600)", $contenido,
            'ComunicadoController::guardar debe aplicar rate limit 10/3600 por admin');
        $this->assertStringContains('existeDuplicadoReciente', $contenido,
            'ComunicadoController::guardar debe consultar la guarda de duplicado');
        $this->assertStringContains('Este comunicado ya fue publicado hace instantes; se evitó un duplicado.', $contenido,
            'Debe mostrarse el Flash informativo de duplicado');

        $posRate = strpos($contenido, "RateLimiter::attempt('comunicado_'");
        $posDup = strpos($contenido, 'existeDuplicadoReciente');
        $posCrear = strpos($contenido, 'crearComunicado(');
        $this->assertTrue($posRate !== false && $posDup !== false && $posCrear !== false && $posRate < $posCrear && $posDup < $posCrear,
            'El rate limit y la guarda deben ejecutarse antes de crearComunicado');
    }
}
