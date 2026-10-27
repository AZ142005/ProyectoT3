<?php
namespace Tests;

use App\Controllers\ConciliacionController;
use App\Core\Database;
use PDO;

/**
 * Pruebas T3 — integración controlador/rutas/vista para reportes de conciliación.
 *
 * Combina verificación estática del cableado (rutas, orden del flujo de
 * importación, guardias de descarga y elementos de la vista) con dos renders
 * reales del controlador contra MySQL para comprobar el lote activo, el aviso
 * de lote histórico y la exclusión de disponibles de lotes anteriores.
 */
class ConciliacionReporteIntegracionTest extends TestCase {

    /**
     * Extrae el cuerpo de un método público de un archivo fuente.
     */
    private function extraerMetodo(string $rutaArchivo, string $metodo): string {
        $contenido = (string) file_get_contents($rutaArchivo);
        preg_match(
            '/(?:public|private|protected) function ' . preg_quote($metodo, '/') . '\(.*?\)(.*?)(?=public function|private function|protected function|\Z)/s',
            $contenido,
            $m
        );
        return $m[1] ?? '';
    }

    private function conexionBd(): PDO {
        try {
            return Database::getConnection();
        } catch (\Throwable $e) {
            throw new \RuntimeException('MySQL no disponible: ' . $e->getMessage());
        }
    }

    private function loteActivoRequerido(): string {
        $lote = (new \App\Models\ConciliacionModel())->obtenerLoteActivo();
        if ($lote === null) {
            throw new \RuntimeException('No hay un lote activo para verificar la integración de reportes.');
        }
        return $lote;
    }

    /**
     * Renderiza ConciliacionController::index() con sesión admin simulada y
     * restaura todos los superglobales que toca.
     */
    private function renderizarIndex(string|array|null $lote): string {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $teniaSesion = array_key_exists('auth_user', $_SESSION);
        $sesionPrevia = $_SESSION['auth_user'] ?? null;
        $teniaLote = array_key_exists('lote', $_GET);
        $lotePrevio = $_GET['lote'] ?? null;
        $teniaPage = array_key_exists('page', $_GET);
        $pagePrevio = $_GET['page'] ?? null;

        $nivelBuffer = ob_get_level();
        ob_start();

        try {
            $_SESSION['auth_user'] = ['id' => 1, 'name' => 'Admin Test', 'role' => 'admin'];
            if ($lote === null) {
                unset($_GET['lote']);
            } else {
                $_GET['lote'] = $lote;
            }
            unset($_GET['page']);

            (new ConciliacionController())->index();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBuffer) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            if ($teniaSesion) {
                $_SESSION['auth_user'] = $sesionPrevia;
            } else {
                unset($_SESSION['auth_user']);
            }

            if ($teniaLote) {
                $_GET['lote'] = $lotePrevio;
            } else {
                unset($_GET['lote']);
            }

            if ($teniaPage) {
                $_GET['page'] = $pagePrevio;
            } else {
                unset($_GET['page']);
            }
        }
    }

    public function testRutasDeReportesRegistradasYProtegidas(): void {
        $index = (string) file_get_contents(BASE_PATH . '/public/index.php');
        $rutas = [
            '/admin/conciliacion/reporte/actual'    => 'descargarReporteActual',
            '/admin/conciliacion/reporte/historico' => 'descargarReporteHistorico',
        ];

        foreach ($rutas as $ruta => $metodo) {
            $patron = '/\$router->get\(\s*[\'"]' . preg_quote($ruta, '/') . '[\'"]\s*,\s*\[[^\]]*[\'"]'
                . preg_quote($metodo, '/') . '[\'"]\]\s*,\s*\[([^\]]+)\]\s*\)/';
            $coincide = preg_match($patron, $index, $m);

            $this->assertTrue((bool)$coincide, "La ruta GET {$ruta} debe apuntar a {$metodo}");
            if ($coincide) {
                $this->assertStringContains('UserRole::ADMIN', $m[1], "La ruta {$ruta} debe exigir UserRole::ADMIN");
                $this->assertStringContains('UserRole::AUDITOR', $m[1], "La ruta {$ruta} debe permitir UserRole::AUDITOR");
            }
        }
    }

    public function testIndexUsaLoteActivoEHistoricosYConservaFiltroMes(): void {
        $cuerpo = $this->extraerMetodo(BASE_PATH . '/app/Controllers/ConciliacionController.php', 'index');
        $this->assertTrue($cuerpo !== '', 'ConciliacionController::index() debe existir');

        foreach (['obtenerLoteActivo', 'listarHistoricos', 'loteActivo', 'loteExiste', 'loteEsActivo', 'reportesHistoricos'] as $token) {
            $this->assertStringContains($token, $cuerpo, "index() debe contener {$token}");
        }

        $posFetch  = strpos($cuerpo, '$pagosPendientes = $conciliacionModel->obtenerTodosPagosPendientes();');
        $posFiltro = strpos($cuerpo, '$pagosPendientes = array_values(array_filter(');
        $posLoop   = strpos($cuerpo, 'foreach ($pagosPendientes as $pago)');
        $posVista  = strpos($cuerpo, "'pagosPendientes'       => \$pagosPendientes,");

        $this->assertTrue($posFetch !== false && $posFiltro !== false && $posLoop !== false && $posVista !== false,
            'index() debe conservar el fetch, el filtro por mes, el loop sin_extracto y el paso a la vista');
        if ($posFetch === false || $posFiltro === false || $posLoop === false || $posVista === false) {
            return;
        }

        $this->assertTrue($posFetch < $posFiltro, 'El filtro del mes debe aplicarse después de obtener los pendientes');
        $this->assertTrue($posFiltro < $posLoop, 'El filtro debe aplicarse antes de armar las filas sin_extracto');
        $this->assertTrue($posFiltro < $posVista, 'La vista debe recibir el arreglo ya filtrado');

        $this->assertStringContains("new \\DateTimeZone('America/Caracas')", $cuerpo,
            'El corte de mes debe evaluarse en la zona horaria de la app');
        $this->assertStringContains("->format('Y-m')", $cuerpo,
            'El filtro debe comparar por prefijo de mes');
        $this->assertStringContains("!empty(\$pago['fecha_pago'])", $cuerpo,
            'Una fecha_pago vacía o nula debe quedar excluida');
    }

    public function testImportarExtractoOrquestaSnapshotReporteYCruce(): void {
        $cuerpo = $this->extraerMetodo(BASE_PATH . '/app/Controllers/ConciliacionController.php', 'importarExtracto');
        $this->assertTrue($cuerpo !== '', 'ConciliacionController::importarExtracto() debe existir');

        $pasos = [
            'obtenerLoteActivo'          => strpos($cuerpo, 'obtenerLoteActivo'),
            'construir'                  => strpos($cuerpo, 'construir('),
            'insertarExtracto'           => strpos($cuerpo, 'insertarExtracto('),
            'guardar'                    => strpos($cuerpo, 'guardar('),
            'obtenerExtractosPendientes' => strpos($cuerpo, 'obtenerExtractosPendientes('),
            'ejecutarCruceInteligente'   => strpos($cuerpo, 'ejecutarCruceInteligente('),
        ];

        $anterior = -1;
        foreach ($pasos as $nombre => $posicion) {
            $this->assertTrue($posicion !== false, "importarExtracto() debe invocar {$nombre}");
            if ($posicion === false) {
                continue;
            }
            $this->assertTrue($posicion > $anterior, "El paso {$nombre} debe ejecutarse después del paso anterior");
            $anterior = $posicion;
        }

        $this->assertStringContains('adoptados', $cuerpo,
            'El flujo de importación debe contabilizar los extractos adoptados');

        $posGuardBuildFallido = strpos($cuerpo, '$loteAnterior !== null && $nuevos > 0 && $snapshotReporte === null');
        $posGuardSaveFallido = strpos($cuerpo, '$loteAnterior !== null && $nuevos > 0 && $reporteGuardado === null');
        $posMensajeBuildFallido = strpos($cuerpo, 'No se pudo construir el reporte histórico del lote anterior.');
        $posMensajeSaveFallido = strpos($cuerpo, 'No se pudo guardar el reporte histórico del lote anterior.');

        $this->assertTrue($posGuardBuildFallido !== false, 'La falla de construcción del reporte debe estar guardada por $nuevos > 0');
        $this->assertTrue($posGuardSaveFallido !== false, 'La falla de guardado del reporte debe estar guardada por $nuevos > 0');
        $this->assertTrue($posMensajeBuildFallido !== false, 'Debe existir el mensaje de falla de construcción del reporte');
        $this->assertTrue($posMensajeSaveFallido !== false, 'Debe existir el mensaje de falla de guardado del reporte');

        if ($posGuardBuildFallido !== false && $posMensajeBuildFallido !== false) {
            $this->assertTrue($posGuardBuildFallido < $posMensajeBuildFallido,
                'El mensaje de construcción fallida debe estar dentro de su rama protegida por $nuevos > 0');
        }
        if ($posGuardSaveFallido !== false && $posMensajeSaveFallido !== false) {
            $this->assertTrue($posGuardSaveFallido < $posMensajeSaveFallido,
                'El mensaje de guardado fallido debe estar dentro de su rama protegida por $nuevos > 0');
        }

        $this->assertStringContains('Extracto procesado sin movimientos nuevos. Lote:', $cuerpo,
            'La importación sin movimientos nuevos debe informarse como procesada, no como exitosa');
        $this->assertStringContains("Flash::set(\$nuevos > 0 ? 'success' : 'info', \$msg);", $cuerpo,
            'El flash debe ser success solo cuando hubo movimientos nuevos');
    }

    public function testDescargasUsanGuardiasYEnvioSeguro(): void {
        $ruta = BASE_PATH . '/app/Controllers/ConciliacionController.php';
        $fuente = (string) file_get_contents($ruta);

        $actual = $this->extraerMetodo($ruta, 'descargarReporteActual');
        $historico = $this->extraerMetodo($ruta, 'descargarReporteHistorico');
        $enviar = $this->extraerMetodo($ruta, 'enviarArchivo');

        $this->assertMatchesRegex('/^\s*\{\s*Auth::requireRole\(\[\'admin\', \'auditor\'\]\);/', $actual,
            'descargarReporteActual() debe iniciar con la guardia admin/auditor');
        $this->assertMatchesRegex('/^\s*\{\s*Auth::requireRole\(\[\'admin\', \'auditor\'\]\);/', $historico,
            'descargarReporteHistorico() debe iniciar con la guardia admin/auditor');

        foreach (['construir', 'generarPdf', 'generarCsv'] as $token) {
            $this->assertStringContains($token, $actual, "descargarReporteActual() debe usar {$token}");
        }
        $this->assertStringContains('resolverArchivo', $historico,
            'descargarReporteHistorico() debe usar resolverArchivo');
        $this->assertStringContains('readfile', $enviar,
            'enviarArchivo() debe transmitir con readfile');

        $this->assertFalse(str_contains($fuente, 'echo '), 'El controlador no debe usar echo');
        $this->assertFalse(str_contains($fuente, 'print '), 'El controlador no debe usar print');
    }

    public function testVistaIncluyeControlesAvisosYModalDeHistoricos(): void {
        $vista = (string) file_get_contents(VIEWS_PATH . '/admin/conciliacion/index.php');

        $textos = [
            'modalReportesHistoricos',
            'reporte/actual?formato=pdf',
            'reporte/actual?formato=csv',
            'Reporte actual PDF',
            'Reporte actual CSV',
            'Históricos (',
            'urlencode',
            "e(\$r['fecha_corte']",
            "e(\$r['lote']",
            'Lote / Historial',
            'El lote consultado no existe o no tiene movimientos; la bandeja se muestra sin extractos.',
            'Lote histórico: los movimientos disponibles de este lote no se muestran en la bandeja. Descargue el reporte histórico.',
        ];

        foreach ($textos as $texto) {
            $this->assertStringContains($texto, $vista, "La vista debe contener {$texto}");
        }

        $this->assertFalse(str_contains($vista, '>Lote<'),
            'La vista no debe introducir la columna literal >Lote<');
    }

    public function testIndexRenderizaLoteActivoSeleccionadoYControlesDeReporte(): void {
        $this->conexionBd();
        $loteActivo = $this->loteActivoRequerido();

        $html = $this->renderizarIndex(null);

        $patron = '/<option value="' . preg_quote(e($loteActivo), '/') . '"[^>]*selected/';
        $this->assertMatchesRegex($patron, $html, 'El lote activo debe quedar seleccionado por defecto');
        $this->assertStringContains('id="modalReportesHistoricos"', $html,
            'El render debe incluir el modal de reportes históricos');
        $this->assertStringContains('/admin/conciliacion/reporte/actual?formato=pdf', $html,
            'El render debe incluir el enlace al reporte actual PDF');
        $this->assertStringContains('/admin/conciliacion/reporte/actual?formato=csv', $html,
            'El render debe incluir el enlace al reporte actual CSV');
        $this->assertStringContains('Históricos (', $html,
            'El render debe incluir el botón de históricos');
    }

    public function testIndexToleraLoteComoArregloYSeleccionaElActivo(): void {
        $this->conexionBd();
        $loteActivo = $this->loteActivoRequerido();

        $html = $this->renderizarIndex(['x']);

        $patron = '/<option value="' . preg_quote(e($loteActivo), '/') . '"[^>]*selected/';
        $this->assertMatchesRegex($patron, $html, 'Un lote recibido como arreglo debe caer al lote activo sin lanzar TypeError');
    }

    public function testIndexRenderizaAvisoHistoricoYExcluyeDisponiblesDeLoteViejo(): void {
        $db = $this->conexionBd();
        $loteActivo = $this->loteActivoRequerido();

        $sufijo = bin2hex(random_bytes(4));
        $loteTemporal = 'LOTE-TEST-T3-HIST-' . $sufijo;
        $referencia = 'T3HIST' . $sufijo;

        $this->assertNotEquals($loteActivo, $loteTemporal, 'El lote temporal debe ser distinto del activo');

        $stmt = $db->prepare("
            INSERT INTO extractos_bancarios
            (lote_importacion, banco, fecha_movimiento, referencia_bancaria, referencia,
             descripcion_banco, descripcion, monto, tipo_movimiento,
             estado_conciliacion, estado, fecha_carga)
            VALUES (:lote, 'Banco Test T3', '2020-01-02', :ref1, :ref2,
                    'Movimiento historico T3', 'Movimiento historico T3', 123.45, 'credito',
                    'pendiente', 'disponible', '2020-01-01 00:00:00')
        ");
        $stmt->execute([
            'lote' => $loteTemporal,
            'ref1' => $referencia,
            'ref2' => $referencia,
        ]);

        try {
            $html = $this->renderizarIndex($loteTemporal);

            $this->assertStringContains(
                'Lote histórico: los movimientos disponibles de este lote no se muestran en la bandeja. Descargue el reporte histórico.',
                $html,
                'Al consultar un lote histórico debe mostrarse el aviso informativo'
            );
            $this->assertFalse(str_contains($html, $referencia),
                'Los disponibles de un lote histórico no deben aparecer en la bandeja');
        } finally {
            $db->prepare('DELETE FROM extractos_bancarios WHERE lote_importacion = :lote')
               ->execute(['lote' => $loteTemporal]);
        }
    }
}
