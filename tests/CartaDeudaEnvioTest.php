<?php
namespace Tests;

use App\Controllers\ReporteController;
use App\Core\Database;
use App\Core\Flash;
use App\Models\CartasDeudaEnviosModel;

/**
 * Registro de envíos de carta de deuda por residente y bloqueo de reenvío
 * hasta el día siguiente.
 *
 * Cubre el modelo (registrar/obtener/enviadoHoy con precedencia por
 * propietario y fallback por unidad), la vista de la carta (estado
 * "Carta enviada" y "Último envío") y el controlador (bloqueo y éxito).
 */
class CartaDeudaEnvioTest extends TestCase {

    // =====================================================================
    // Modelo: CartasDeudaEnviosModel
    // =====================================================================

    public function testRegistrarObtenerYEnviadoHoy(): void {
        $unidadId = $this->unidadAleatoria();
        $propietarioId = $this->propietarioAleatorio();

        try {
            $model = new CartasDeudaEnviosModel();

            $this->assertNull($model->obtenerUltimoEnvio($unidadId, $propietarioId),
                "Sin registros debe retornar null");
            $this->assertFalse($model->enviadoHoy($unidadId, $propietarioId),
                "Sin registros no debe existir envío de hoy");

            $insertId = $model->registrarEnvio($unidadId, $propietarioId, 'email', 1);
            $this->assertNotEquals(false, $insertId,
                "registrarEnvio debe devolver el lastInsertId");
            $this->assertTrue(intval($insertId) > 0,
                "El lastInsertId debe ser un entero positivo");

            $ultimo = $model->obtenerUltimoEnvio($unidadId, $propietarioId);
            $this->assertNotNull($ultimo, "Debe recuperar el envío recién registrado");
            $this->assertEquals($unidadId, intval($ultimo['unidad_id']));
            $this->assertEquals($propietarioId, intval($ultimo['propietario_id']));
            $this->assertEquals('email', $ultimo['canal']);

            $this->assertTrue($model->enviadoHoy($unidadId, $propietarioId),
                "El envío recién creado cuenta como enviado hoy");
        } finally {
            $this->limpiarEnvios($unidadId, $propietarioId);
        }
    }

    public function testEnvioDeAyerNoBloqueaHoy(): void {
        $db = Database::getConnection();
        $unidadId = $this->unidadAleatoria();
        $propietarioId = $this->propietarioAleatorio();

        try {
            $db->prepare(
                "INSERT INTO cartas_deuda_envios (unidad_id, propietario_id, canal, usuario_id, created_at)
                 VALUES (:uid, :pid, 'email', 1, CURDATE() - INTERVAL 1 DAY)"
            )->execute(['uid' => $unidadId, 'pid' => $propietarioId]);

            $model = new CartasDeudaEnviosModel();
            $this->assertFalse($model->enviadoHoy($unidadId, $propietarioId),
                "Un envío de ayer no debe bloquear el reenvío de hoy");
            $this->assertNotNull($model->obtenerUltimoEnvio($unidadId, $propietarioId),
                "El último envío debe seguir disponible para la vista");
        } finally {
            $this->limpiarEnvios($unidadId, $propietarioId);
        }
    }

    public function testPrecedenciaPorPropietarioEntreUnidades(): void {
        $unidadA = $this->unidadAleatoria();
        $unidadB = $this->unidadAleatoria();
        $propietarioId = $this->propietarioAleatorio();

        try {
            $model = new CartasDeudaEnviosModel();
            $model->registrarEnvio($unidadA, $propietarioId);

            // El envío se registró en la unidad A, pero la clave del residente es el propietario.
            $this->assertTrue($model->enviadoHoy($unidadB, $propietarioId),
                "El bloqueo sigue al propietario aunque consulte otra unidad");
            $this->assertNotNull($model->obtenerUltimoEnvio($unidadB, $propietarioId),
                "El último envío por propietario ignora la unidad consultada");

            // Sin propietario, la clave es la unidad: la unidad B no tiene envíos propios.
            $this->assertFalse($model->enviadoHoy($unidadB, null),
                "Sin propietario la clave de búsqueda es la unidad");
        } finally {
            $this->limpiarEnvios($unidadA, $propietarioId);
            $this->limpiarEnvios($unidadB);
        }
    }

    public function testFallbackPorUnidadSinPropietario(): void {
        $unidadId = $this->unidadAleatoria();

        try {
            $model = new CartasDeudaEnviosModel();
            $model->registrarEnvio($unidadId, null);

            $this->assertTrue($model->enviadoHoy($unidadId, null),
                "Con propietario nulo el bloqueo usa la unidad");
            $ultimo = $model->obtenerUltimoEnvio($unidadId, null);
            $this->assertNotNull($ultimo, "Debe recuperar el envío por unidad");
            $this->assertEquals($unidadId, intval($ultimo['unidad_id']));
            $this->assertNull($ultimo['propietario_id'],
                "El envío fallback no debe inventar un propietario");
        } finally {
            $this->limpiarEnvios($unidadId);
        }
    }

    // =====================================================================
    // Vista: estado de envío en la barra no imprimible
    // =====================================================================

    public function testVistaMuestraCartaEnviadaCuandoSeEnvioHoy(): void {
        $html = $this->renderizarVista([
            'enviadoHoy'  => true,
            'ultimoEnvio' => ['created_at' => '2026-10-11 09:30:00'],
        ]);

        $this->assertStringContains('Carta enviada', $html,
            "Con envío de hoy la vista debe mostrar 'Carta enviada'");
        $this->assertStringContains('11/10/2026 09:30', $html,
            "El estado debe incluir la fecha y hora del envío");
        $this->assertStringContains('mark_email_read', $html,
            "El botón deshabilitado debe usar el ícono mark_email_read");
        $this->assertFalse(str_contains($html, 'enviar-aviso-cobro'),
            "Con envío de hoy no debe ofrecerse el formulario de reenvío");
    }

    public function testVistaMuestraFormularioYUltimoEnvioSinEnvioDeHoy(): void {
        $html = $this->renderizarVista([
            'enviadoHoy'  => false,
            'ultimoEnvio' => ['created_at' => '2026-10-05 12:00:00'],
        ]);

        $this->assertStringContains('enviar-aviso-cobro', $html,
            "Sin envío de hoy debe mostrarse el formulario de envío");
        $this->assertStringContains('Último envío: 05/10/2026', $html,
            "Con envíos previos debe mostrarse la fecha del último envío");
        $this->assertFalse(str_contains($html, 'Carta enviada'),
            "Sin envío de hoy no debe mostrarse el estado 'Carta enviada'");
    }

    // =====================================================================
    // Controlador: enviarAvisoCobro()
    // =====================================================================

    public function testControladorBloqueaReenvioDelMismoDia(): void {
        $db = Database::getConnection();
        $fixture = $this->crearFixtureCobro();
        $unidadId = $fixture['unidad_id'];

        try {
            // Envío registrado HOY para el residente de la unidad.
            $enviosModel = new CartasDeudaEnviosModel();
            $enviosModel->registrarEnvio($unidadId, $fixture['persona_id'], 'email', 1);

            $colaAntes = $this->contar($db, "SELECT COUNT(*) AS total FROM notificaciones_cola");
            $enviosAntes = $this->contar($db, "SELECT COUNT(*) AS total FROM cartas_deuda_envios");

            $_SESSION = ['auth_user' => ['id' => 1, 'role' => 'admin', 'name' => 'Administrador Test']];
            $_POST['unidad_id'] = $unidadId;

            $redireccion = $this->ejecutarEnviarAvisoCobro();

            $this->assertNotNull($redireccion,
                "El controlador debía redirigir a la carta");
            $this->assertStringContains('/admin/reportes/carta-deuda/' . $unidadId, (string)$redireccion,
                "El redirect del bloqueo debe apuntar a la carta de la unidad");
            $this->assertNotEquals('', Flash::get('warning'),
                "El bloqueo debe informar con un flash de aviso");
            $this->assertEquals($colaAntes, $this->contar($db, "SELECT COUNT(*) AS total FROM notificaciones_cola"),
                "Al bloquear no debe encolarse ningún correo");
            $this->assertEquals($enviosAntes, $this->contar($db, "SELECT COUNT(*) AS total FROM cartas_deuda_envios"),
                "Al bloquear no debe registrarse otro envío");
        } finally {
            unset($_POST['unidad_id']);
            $_SESSION = [];
            $this->limpiarFixtureCobro($fixture['persona_id'], $unidadId);
        }
    }

    public function testControladorRegistraEnvioYEncolaAlExito(): void {
        $db = Database::getConnection();
        $fixture = $this->crearFixtureCobro();
        $unidadId = $fixture['unidad_id'];
        $maxColaId = $this->contar($db, "SELECT COALESCE(MAX(id), 0) AS total FROM notificaciones_cola");
        $colaId = null;

        try {
            $colaAntes = $this->contar($db, "SELECT COUNT(*) AS total FROM notificaciones_cola");
            $enviosAntes = $this->contarEnviosUnidad($db, $unidadId);

            $_SESSION = ['auth_user' => ['id' => 1, 'role' => 'admin', 'name' => 'Administrador Test']];
            $_POST['unidad_id'] = $unidadId;

            $redireccion = $this->ejecutarEnviarAvisoCobro();

            // Identifica la fila encolada por el controlador para su limpieza y verificación.
            $stmtCola = $db->prepare("SELECT id, cuerpo_html, adjuntos FROM notificaciones_cola WHERE id > :max ORDER BY id DESC LIMIT 1");
            $stmtCola->execute(['max' => $maxColaId]);
            $filaCola = $stmtCola->fetch(\PDO::FETCH_ASSOC);
            $colaId = $filaCola ? intval($filaCola['id']) : null;

            $this->assertNotNull($redireccion,
                "El controlador debía redirigir a la carta");
            $this->assertStringContains('/admin/reportes/carta-deuda/' . $unidadId, (string)$redireccion,
                "El redirect del éxito debe apuntar a la carta de la unidad");
            $this->assertNotEquals('', Flash::get('success'),
                "El envío exitoso debe informar con un flash de éxito");
            $this->assertEquals($colaAntes + 1, $this->contar($db, "SELECT COUNT(*) AS total FROM notificaciones_cola"),
                "Debe encolarse exactamente 1 correo");
            $this->assertEquals($enviosAntes + 1, $this->contarEnviosUnidad($db, $unidadId),
                "Debe registrarse exactamente 1 envío");

            // El correo encolado debe llevar el texto breve y la Carta de Deuda como adjunto PDF.
            $this->assertNotNull($filaCola, "El controlador debe haber encolado la notificación");
            $this->assertNotNull($filaCola['adjuntos'] ?? null,
                "La carta debe encolarse con el PDF adjunto cifrado");
            if (!empty($filaCola['adjuntos'])) {
                $cuerpoDescifrado = \App\Core\Encryption::decrypt($filaCola['cuerpo_html']);
                $this->assertStringContains('PDF', $cuerpoDescifrado,
                    "El cuerpo breve debe mencionar la Carta de Deuda en PDF");

                $adjuntos = json_decode(\App\Core\Encryption::decrypt($filaCola['adjuntos']), true);
                $this->assertIsArray($adjuntos,
                    "Los adjuntos descifrados deben ser un JSON decodificable");
                $nombreAdjunto = is_array($adjuntos) ? ($adjuntos[0]['nombre'] ?? '') : '';
                $this->assertStringContains('Carta-Deuda-Unidad-', $nombreAdjunto,
                    "El adjunto debe nombrarse Carta-Deuda-Unidad-...pdf");
                $this->assertMatchesRegex('/^Carta-Deuda-Unidad-.+\.pdf$/', $nombreAdjunto,
                    "El nombre del adjunto debe terminar en .pdf");

                $contenidoPdf = is_array($adjuntos) ? base64_decode($adjuntos[0]['contenido'] ?? '', true) : false;
                $this->assertTrue(is_string($contenidoPdf) && str_starts_with($contenidoPdf, '%PDF-'),
                    "El adjunto debe decodificar a un PDF real");
            }

            $stmtCanal = $db->prepare("SELECT canal FROM cartas_deuda_envios WHERE unidad_id = :uid ORDER BY id DESC LIMIT 1");
            $stmtCanal->execute(['uid' => $unidadId]);
            $this->assertEquals('email', $stmtCanal->fetch()['canal'],
                "El envío registrado debe usar el canal email");
        } finally {
            unset($_POST['unidad_id']);
            $_SESSION = [];
            $db->prepare("DELETE FROM notificaciones WHERE residente_id = :pid")->execute(['pid' => $fixture['persona_id']]);
            if ($colaId !== null) {
                $db->prepare("DELETE FROM notificaciones_cola WHERE id = :id")->execute(['id' => $colaId]);
            }
            $this->limpiarFixtureCobro($fixture['persona_id'], $unidadId);
        }
    }

    // =====================================================================
    // Utilidades
    // =====================================================================

    /** Genera un id de unidad exclusivo del test (la tabla no tiene FK). */
    private function unidadAleatoria(): int {
        return random_int(900000000, 990000000);
    }

    /** Genera un id de propietario exclusivo del test (la tabla no tiene FK). */
    private function propietarioAleatorio(): int {
        return random_int(991000000, 999999999);
    }

    /** Elimina los envíos asociados a la unidad y/o al propietario del test. */
    private function limpiarEnvios(int $unidadId, ?int $propietarioId = null): void {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM cartas_deuda_envios WHERE unidad_id = :uid")->execute(['uid' => $unidadId]);
        if ($propietarioId !== null) {
            $db->prepare("DELETE FROM cartas_deuda_envios WHERE propietario_id = :pid")->execute(['pid' => $propietarioId]);
        }
    }

    /** Cuenta filas para una consulta simple con alias total. */
    private function contar(\PDO $db, string $sql): int {
        return intval($db->query($sql)->fetch()['total']);
    }

    /** Cuenta los envíos registrados para una unidad del test. */
    private function contarEnviosUnidad(\PDO $db, int $unidadId): int {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM cartas_deuda_envios WHERE unidad_id = :uid");
        $stmt->execute(['uid' => $unidadId]);
        return intval($stmt->fetch()['total']);
    }

    /**
     * Renderiza la vista de la carta por include con buffer.
     */
    private function renderizarVista(array $sobrescribir = []): string {
        $datos = array_merge([
            'unidad' => [
                'unidad_id'            => 1,
                'unidad_numero'        => '56',
                'edificio_nombre'      => 'EL CHAGUARAMO',
                'propietario_nombre'   => 'Juan Pérez',
                'propietario_cedula'   => 'V-12345678',
                'propietario_telefono' => '0414-7778899',
            ],
            'facturas' => [
                [
                    'id'                => 1,
                    'numero_factura'    => 'FAC-1',
                    'mes'               => 4,
                    'anio'              => 2026,
                    'fecha_vencimiento' => '2026-04-15',
                    'monto_total'       => '5000.00',
                    'saldo'             => '5000.00',
                    'dias_mora'         => 30,
                ],
            ],
            'totalDeuda'     => 5000.00,
            'enlaceWhatsapp' => 'https://api.whatsapp.com/send?phone=584147778899&text=Hola',
            'analisisTel'    => ['es_movil' => true, 'telefono' => '584147778899', 'mensaje' => null],
            'title'          => 'Carta Oficial de Deuda - Unidad 56',
        ], $sobrescribir);

        $_SESSION = [];
        $nivelBase = ob_get_level();
        ob_start();
        try {
            extract($datos);
            include BASE_PATH . '/app/views/admin/reportes/carta_deuda.php';
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBase) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Ejecuta enviarAvisoCobro() interceptando el redirect con una subclase anónima.
     *
     * @return string|null Mensaje del redirect interceptado, o null si no hubo redirect
     */
    private function ejecutarEnviarAvisoCobro(): ?string {
        $ctrl = new class extends ReporteController {
            protected function redirect($url): void {
                throw new \RuntimeException('redirect:' . $url);
            }
        };

        try {
            $ctrl->enviarAvisoCobro();
            return null;
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Crea persona propietaria (con email y teléfono), unidad y una factura vencida.
     *
     * @return array{persona_id: int, unidad_id: int}
     */
    private function crearFixtureCobro(): array {
        $db = Database::getConnection();
        $sufijo = strtolower(substr(uniqid('', true), -8));

        $db->prepare(
            "INSERT INTO personas (cedula, nombre, apellido, telefono, email, tipo, numero_residentes)
             VALUES (:cedula, :nombre, :apellido, :telefono, :email, 'propietario', 1)"
        )->execute([
            'cedula'   => 'V-' . random_int(10000000, 99999999),
            'nombre'   => 'Residente',
            'apellido' => 'Test CM',
            'telefono' => '04147778899',
            'email'    => 'test.envio.cm.' . $sufijo . '@example.com',
        ]);
        $personaId = intval($db->lastInsertId());

        $db->prepare("INSERT INTO unidades (propietario_id, numero, cuota_mensual) VALUES (:propietario_id, :numero, 0.00)")
            ->execute(['propietario_id' => $personaId, 'numero' => 'TEST-ENV-' . $sufijo]);
        $unidadId = intval($db->lastInsertId());

        $venc = date('Y-m-d', strtotime('-30 days'));
        $db->prepare(
            "INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, saldo, estado)
             VALUES (:numero, :unidad, :mes, :anio, :emision, :venc, 1000.00, 1000.00, 'vencida')"
        )->execute([
            'numero'  => 'TEST-ENV-' . $sufijo . '-1',
            'unidad'  => $unidadId,
            'mes'     => intval(date('n', strtotime($venc))),
            'anio'    => intval(date('Y', strtotime($venc))),
            'emision' => date('Y-m-d', strtotime('-45 days')),
            'venc'    => $venc,
        ]);

        return ['persona_id' => $personaId, 'unidad_id' => $unidadId];
    }

    /** Elimina el fixture completo del controlador (envíos, facturas, unidad y persona). */
    private function limpiarFixtureCobro(int $personaId, int $unidadId): void {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM cartas_deuda_envios WHERE unidad_id = :uid")->execute(['uid' => $unidadId]);
        $db->prepare("DELETE FROM facturas WHERE unidad_id = :uid")->execute(['uid' => $unidadId]);
        $db->prepare("DELETE FROM unidades WHERE id = :id")->execute(['id' => $unidadId]);
        $db->prepare("DELETE FROM personas WHERE id = :id")->execute(['id' => $personaId]);
    }
}
