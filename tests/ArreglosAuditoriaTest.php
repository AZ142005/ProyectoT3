<?php
namespace Tests;

/**
 * Regresión de los arreglos de auditoría admin/auditor (T1–T6).
 * Verificaciones estables sobre el código fuente: sin dependencias de líneas exactas.
 */
class ArreglosAuditoriaTest extends TestCase {

    /**
     * Extrae el cuerpo de un método público de un archivo fuente.
     */
    private function extraerMetodo(string $rutaArchivo, string $metodo): string {
        $contenido = file_get_contents($rutaArchivo);
        preg_match('/public function ' . preg_quote($metodo, '/') . '\(.*?\)(.*?)(?=public function|private function|protected function|\Z)/s', $contenido, $m);
        return $m[1] ?? '';
    }

    // T1 — Sin formularios anidados en la vista de pagos
    public function testListaPagosTieneFormulariosBalanceadosYSinAnidar(): void {
        $vista = file_get_contents(BASE_PATH . '/app/views/pagos/admin/lista.php');

        $abiertos = preg_match_all('/<form\b/i', $vista);
        $cerrados = preg_match_all('/<\/form>/i', $vista);
        $this->assertEquals($abiertos, $cerrados, 'La vista de pagos debe tener etiquetas <form> balanceadas');

        // Escaneo secuencial: la profundidad nunca debe superar 1 (sin formularios anidados)
        $profundidad = 0;
        $maxProfundidad = 0;
        preg_match_all('/<form\b|<\/form>/i', $vista, $tags);
        foreach ($tags[0] as $tag) {
            if (stripos($tag, '</form') === 0) {
                $profundidad--;
            } else {
                $profundidad++;
                if ($profundidad > $maxProfundidad) {
                    $maxProfundidad = $profundidad;
                }
            }
        }
        $this->assertTrue($maxProfundidad <= 1, 'No debe existir ningún <form> anidado en la vista de pagos');
    }

    public function testListaPagosMantieneAccionesPorFilaYAsociacionMasiva(): void {
        $vista = file_get_contents(BASE_PATH . '/app/views/pagos/admin/lista.php');

        $this->assertStringContains('action="/pagos/cambiar-estado"', $vista,
            'Las acciones por fila deben postear a /pagos/cambiar-estado');
        $this->assertStringContains('action="/admin/pagos/aprobar-masivo"', $vista,
            'La acción masiva debe postear a /admin/pagos/aprobar-masivo');
        $this->assertStringContains('name="pago_ids[]"', $vista,
            'La vista debe conservar los checkboxes pago_ids[]');
        $this->assertStringContains('form="formAprobacionMasiva"', $vista,
            'Los checkboxes deben asociarse al formulario masivo con el atributo form');
    }

    // T2 — Saneamiento de archivo_maestro / soportes
    public function testGastoControllerSaneaArchivoMaestroConBasename(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/GastoController.php', 'importarMaestro');

        $this->assertStringContains('basename', $metodo, 'importarMaestro debe aplicar basename al archivo maestro');
        $posBasename = strpos($metodo, 'basename');
        $posImportacion = strpos($metodo, 'importarGastosMaestro');
        $this->assertTrue($posBasename !== false && $posImportacion !== false && $posBasename < $posImportacion,
            'El saneamiento debe ocurrir antes de llamar a importarGastosMaestro');
    }

    public function testGastosModelEliminarGastoAplicaBasenameAntesDeUnlink(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/models/GastosModel.php', 'eliminarGasto');

        $posBasename = strpos($metodo, 'basename');
        $posUnlink = strpos($metodo, 'unlink(');
        $this->assertTrue($posBasename !== false, 'eliminarGasto debe aplicar basename al soporte');
        $this->assertTrue($posUnlink !== false, 'eliminarGasto debe conservar el unlink del soporte');
        $this->assertTrue($posBasename < $posUnlink,
            'El basename debe aplicarse antes de construir la ruta y llamar a unlink');
    }

    public function testGastosModelImportarGastosMaestroSaneaAntesDeGuardarSoporte(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/models/GastosModel.php', 'importarGastosMaestro');

        $posBasename = strpos($metodo, 'basename');
        $posSoporte = strpos($metodo, "'soporte'");
        $this->assertTrue($posBasename !== false, 'importarGastosMaestro debe sanear el nombre del archivo');
        $this->assertTrue($posSoporte !== false, 'importarGastosMaestro debe seguir insertando la columna soporte');
        $this->assertTrue($posBasename < $posSoporte,
            'El saneamiento debe ocurrir antes de usar el nombre en la columna soporte');
    }

    // T3/T4 — Estacionamiento
    public function testGuardarVehiculoResuelvePersonaDesdeLaUnidad(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/EstacionamientoController.php', 'guardarVehiculo');

        $this->assertFalse(str_contains($metodo, "\$_POST['persona_id']"),
            'guardarVehiculo no debe leer persona_id desde el cliente');
        $this->assertStringContains('obtenerPrimerResidenteActivo', $metodo,
            'Debe preferir el residente activo de la unidad');
        $this->assertStringContains('propietario_id', $metodo,
            'Debe caer al propietario de la unidad si no hay residente activo');
    }

    public function testEliminarVehiculoUsaRolesString(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/EstacionamientoController.php', 'eliminarVehiculo');

        $this->assertStringContains("Auth::requireRole(['admin', 'auditor'])", $metodo,
            'eliminarVehiculo debe usar roles string como el resto del archivo');
        $this->assertFalse(str_contains($metodo, 'UserRole::'),
            'eliminarVehiculo no debe usar la constante UserRole sin importar');
    }

    // T5 — Carta de deuda tolera ids no numéricos
    public function testReporteCartaDeudaParametroSinTipoCon404(): void {
        $controlador = file_get_contents(BASE_PATH . '/app/controllers/ReporteController.php');

        $this->assertStringContains('public function generarCartaDeuda($unidadId)', $controlador,
            'El parámetro debe ser sin tipo para evitar TypeError con entradas no numéricas');
        $this->assertFalse(str_contains($controlador, 'public function generarCartaDeuda(int $unidadId)'),
            'No debe conservarse el parámetro tipado int');

        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/ReporteController.php', 'generarCartaDeuda');
        $this->assertStringContains('intval($unidadId)', $metodo, 'Debe convertir el id con intval');
        $this->assertStringContains('errors/404', $metodo, 'Debe renderizar 404 para ids inválidos');
    }

    // T6b — Rango de período en la generación de facturas
    public function testAdminGenerarFacturasValidaRangoDePeriodo(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/AdminController.php', 'generarFacturas');

        $this->assertStringContains('$mes < 1 || $mes > 12', $metodo,
            'generarFacturas debe validar el mes en 1..12');
        $this->assertStringContains('$anio < 2000 || $anio > 2100', $metodo,
            'generarFacturas debe validar el año en 2000..2100');
        $this->assertStringContains('Período inválido.', $metodo,
            'Debe informar el período inválido con flash');
        $this->assertStringContains('is_scalar', $metodo,
            'Debe validar escalares antes de intval (un array no debe coaccionar a 1)');
    }

    // T6c — Sin http_response_code(400) previo al redirect
    public function testEstacionamientoGuardarSinHttpResponseCode400(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/EstacionamientoController.php', 'guardar');

        $this->assertFalse(str_contains($metodo, 'http_response_code(400)'),
            'guardar no debe fijar 400 antes del redirect de flash');
    }

    // T6d — Coerción intval(array)
    public function testAprobarLoteFiltraIdsNoEscalares(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/models/PagoModel.php', 'aprobarLote');

        $this->assertStringContains('is_scalar', $metodo, 'aprobarLote debe exigir ids escalares');
        $this->assertStringContains('is_numeric', $metodo, 'aprobarLote debe exigir ids numéricos');
    }

    public function testImportarMaestroProtegeCamposAntesDeTrimYFloatval(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/GastoController.php', 'importarMaestro');

        $this->assertStringContains('is_string', $metodo, 'Los campos de texto deben validarse como string');
        $this->assertStringContains('is_scalar', $metodo, 'Los campos numéricos deben validarse como escalares');
    }

    // T6a — Cuerpo JSON en conciliarPago
    public function testConciliarPagoAceptaJsonYSoloIdsEscalares(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/ConciliacionController.php', 'conciliarPago');

        $this->assertStringContains('application/json', $metodo, 'conciliarPago debe detectar el cuerpo JSON');
        $this->assertStringContains('php://input', $metodo, 'conciliarPago debe leer php://input para el JSON');
        $this->assertStringContains('is_scalar', $metodo, 'conciliarPago debe exigir ids escalares');
        $this->assertStringContains('is_numeric', $metodo, 'conciliarPago debe exigir ids numéricos');
    }

    // T6f — Perfil del auditor usa los datos de usuarioAdmin
    public function testPerfilAuditorUsaUsuarioAdminEnTarjeta(): void {
        $vista = file_get_contents(BASE_PATH . '/app/views/perfil/index.php');

        preg_match('/Cédula de Identidad(.*?)Teléfono Móvil/s', $vista, $m);
        $this->assertTrue(count($m) > 1, 'La tarjeta de datos del perfil debe existir');
        $this->assertStringContains('$isAdmin || $isAuditor', $m[1],
            'La tarjeta debe usar usuarioAdmin también para el rol auditor');
    }

    // Coerción — período escalar en el módulo de gastos
    public function testGastoControllerNormalizaPeriodoScalar(): void {
        foreach (['guardar', 'parsearMaestro', 'importarMaestro'] as $metodoNombre) {
            $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/GastoController.php', $metodoNombre);
            $this->assertStringContains('periodoDesdePost', $metodo,
                "{$metodoNombre} debe normalizar mes/año con periodoDesdePost (solo escalares numéricos)");
        }
    }

    public function testImportarMaestroValidaRangoDePeriodo(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/GastoController.php', 'importarMaestro');

        $this->assertStringContains('$mes < 1 || $mes > 12', $metodo,
            'importarMaestro debe validar el mes en 1..12');
        $this->assertStringContains('$anio < 2000 || $anio > 2100', $metodo,
            'importarMaestro debe validar el año en 2000..2100');
    }

    public function testConciliarPagoDevuelveCsrfTokenEnRespuestasJson(): void {
        $metodo = $this->extraerMetodo(BASE_PATH . '/app/controllers/ConciliacionController.php', 'conciliarPago');

        $this->assertTrue(substr_count($metodo, "'csrf_token'") >= 4,
            'Las 4 respuestas JSON deben exponer el token CSRF rotado para el siguiente llamado');
    }
}
