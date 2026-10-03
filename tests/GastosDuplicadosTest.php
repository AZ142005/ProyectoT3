<?php
namespace Tests;

use App\Core\Database;
use App\Models\GastosModel;
use PDO;
use PDOException;

/**
 * Pruebas Fase 18 — endurecimiento anti-duplicados en gastos_comunes:
 * - Esquema: dup_guard unificado (ramas F y S) + uk_gasto_dup_guard, soporte_hash + índice;
 *   el índice plano uk_gasto_factura_periodo queda retirado (retenía filas borradas).
 * - crearGasto: pre-chequeo F (factura) y S (sin factura), cierre de carrera 23000 -> 0.
 * - importarGastosMaestro: idempotencia por hash del PDF + período, hash por fila y
 *   omisión de renglones en carrera.
 * - Flujos legítimos: datos distintos pasan y un gasto borrado libera la identidad F y S.
 */
class GastosDuplicadosTest extends TestCase {

    private const PREFIJO = 'TEST-F18-GASTO-';

    private function columnaExiste(PDO $db, string $tabla, string $columna): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->execute(['tabla' => $tabla, 'columna' => $columna]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function indiceExiste(PDO $db, string $tabla, string $indice): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND INDEX_NAME = :indice");
        $stmt->execute(['tabla' => $tabla, 'indice' => $indice]);
        return intval($stmt->fetchColumn()) > 0;
    }

    private function categoriaId(PDO $db): int {
        $id = $db->query("SELECT id FROM categorias_gastos ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $id !== false ? intval($id) : 0;
    }

    private function adminId(PDO $db): int {
        $id = $db->query("SELECT id FROM usuarios ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $id !== false ? intval($id) : 0;
    }

    /**
     * Datos base de un gasto SIN N° de factura (guarda S). El sufijo evita
     * colisiones con datos reales y entre pruebas.
     */
    private function datosGasto(int $categoriaId, int $adminId, string $sufijo, array $overrides = []): array {
        return array_merge([
            'categoria_id'          => $categoriaId,
            'mes'                   => 11,
            'anio'                  => 2098,
            'descripcion'           => 'Servicio contratado ' . $sufijo,
            'monto_total'           => 123.45,
            'fecha_gasto'           => '2098-11-10',
            'proveedor'             => self::PREFIJO . $sufijo,
            'nro_factura_proveedor' => null,
            'admin_id'              => $adminId,
            'tipo_gasto'            => 'comun',
        ], $overrides);
    }

    private function limpiarGastos(PDO $db): void {
        $db->exec("DELETE FROM log_auditoria WHERE tabla_afectada = 'gastos_comunes' AND registro_id IN (SELECT id FROM gastos_comunes WHERE proveedor LIKE '" . self::PREFIJO . "%')");
        $db->exec("DELETE FROM gastos_comunes WHERE proveedor LIKE '" . self::PREFIJO . "%'");
    }

    /**
     * Inserción directa sin pre-chequeos de la app (para probar la guardia de BD).
     */
    private function insertarGastoDirecto(PDO $db, int $categoriaId, int $adminId, string $proveedor, ?string $factura, string $descripcion, float $monto, string $fechaGasto = '2098-11-10'): void {
        $stmt = $db->prepare("
            INSERT INTO gastos_comunes
            (categoria_id, mes, anio, descripcion, monto_total, fecha, fecha_gasto, proveedor, nro_factura_proveedor, admin_id, tipo_gasto)
            VALUES (:categoria_id, 11, 2098, :descripcion, :monto_total, :fecha, :fecha_gasto, :proveedor, :factura, :admin_id, 'comun')
        ");
        $stmt->execute([
            'categoria_id' => $categoriaId,
            'descripcion'  => $descripcion,
            'monto_total'  => $monto,
            'fecha'        => $fechaGasto,
            'fecha_gasto'  => $fechaGasto,
            'proveedor'    => $proveedor,
            'factura'      => $factura,
            'admin_id'     => $adminId,
        ]);
    }

    // =================================================================
    // 1. Esquema de la migración
    // =================================================================

    public function testEsquemaMigracionFase18Gastos(): void {
        $db = Database::getConnection();

        if (!$this->columnaExiste($db, 'gastos_comunes', 'dup_guard')) {
            $this->skip('migración Fase 18 no aplicada (gastos_comunes.dup_guard)');
            return;
        }

        $this->assertTrue($this->columnaExiste($db, 'gastos_comunes', 'dup_guard'), 'gastos_comunes debe tener dup_guard');
        $this->assertTrue($this->columnaExiste($db, 'gastos_comunes', 'soporte_hash'), 'gastos_comunes debe tener soporte_hash');
        $this->assertTrue($this->indiceExiste($db, 'gastos_comunes', 'uk_gasto_dup_guard'), 'Debe existir uk_gasto_dup_guard');
        $this->assertTrue($this->indiceExiste($db, 'gastos_comunes', 'idx_gastos_soporte_hash'), 'Debe existir idx_gastos_soporte_hash');
        $this->assertFalse($this->indiceExiste($db, 'gastos_comunes', 'uk_gasto_factura_periodo'), 'uk_gasto_factura_periodo debe estar retirado (superseded por la guardia unificada)');

        $tipo = $db->query("SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos_comunes' AND COLUMN_NAME = 'dup_guard'")->fetchColumn();
        $this->assertStringContains('STORED GENERATED', (string)$tipo, 'dup_guard debe ser columna generada STORED');

        $expr = (string)$db->query("SELECT GENERATION_EXPRESSION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos_comunes' AND COLUMN_NAME = 'dup_guard'")->fetchColumn();
        $this->assertStringContains("'F|'", $expr, 'La guardia unificada debe cubrir filas con factura (rama F)');
        $this->assertStringContains("'S|'", $expr, 'La guardia unificada debe cubrir filas sin factura (rama S)');
        $this->assertStringContains('deleted_at', $expr, 'La guardia debe liberar filas borradas (deleted_at)');
    }

    // =================================================================
    // 2. crearGasto: duplicados F y S
    // =================================================================

    public function testCrearGastoDuplicadoConFacturaEsBloqueado(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->columnaExiste($db, 'gastos_comunes', 'dup_guard')) {
            $this->skip('migración Fase 18 no aplicada');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));
        $factura = 'F18-' . $sufijo;
        $proveedor = self::PREFIJO . $sufijo;

        try {
            $datos = $this->datosGasto($categoriaId, $adminId, $sufijo, [
                'nro_factura_proveedor' => $factura,
                'descripcion'           => 'Compra con factura ' . $sufijo,
            ]);
            $id = $model->crearGasto($datos);
            $this->assertTrue($id > 0, 'El primer gasto con factura debe registrarse');

            // La guardia única DEBE cubrir filas activas con factura: un INSERT
            // directo con la misma F (mes, anio, factura, proveedor) falla 23000.
            $codigoActivo = null;
            try {
                $this->insertarGastoDirecto($db, $categoriaId, $adminId, $proveedor, $factura, 'Otra descripción ' . $sufijo, 456.78);
            } catch (PDOException $e) {
                $codigoActivo = (string)$e->getCode();
            }
            $this->assertEquals('23000', $codigoActivo, 'La guardia unificada debe bloquear facturas activas duplicadas a nivel BD');

            // Pre-chequeo de la app: duplicado exacto => excepción "Ya existe un gasto"
            $this->expectExceptionMessage('Ya existe un gasto', function () use ($model, $datos) {
                $model->crearGasto($datos);
            });

            // Flujo legítimo: factura/proveedor distintos pasan
            $idOtro = $model->crearGasto($this->datosGasto($categoriaId, $adminId, $sufijo . '-B', [
                'nro_factura_proveedor' => $factura . '-B',
                'descripcion'           => 'Compra distinta ' . $sufijo,
            ]));
            $this->assertTrue($idOtro > 0, 'Una factura distinta debe poder registrarse');
        } finally {
            $this->limpiarGastos($db);
        }
    }

    /**
     * REGRESIÓN: el índice plano uk_gasto_factura_periodo retenía la identidad
     * de filas soft-deleted y bloqueaba el re-registro. Con la guardia unificada,
     * borrar un gasto con factura libera su identidad F.
     */
    public function testGastoFacturaSoftDeletedLiberaIdentidadF(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->columnaExiste($db, 'gastos_comunes', 'dup_guard') || !$this->indiceExiste($db, 'gastos_comunes', 'uk_gasto_dup_guard')) {
            $this->skip('migración Fase 18 no aplicada (dup_guard/uk_gasto_dup_guard)');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));
        $proveedor = self::PREFIJO . $sufijo;
        $factura = 'F18-FREE-' . $sufijo;

        try {
            $id1 = $model->crearGasto($this->datosGasto($categoriaId, $adminId, $sufijo, [
                'nro_factura_proveedor' => $factura,
                'descripcion'           => 'Compra original ' . $sufijo,
            ]));
            $this->assertTrue($id1 > 0, 'El gasto original con factura debe registrarse');

            $db->prepare("UPDATE gastos_comunes SET deleted_at = NOW() WHERE id = :id")->execute(['id' => $id1]);

            // Re-registro del mismo (mes, anio, factura, proveedor) con otros datos
            $id2 = $model->crearGasto($this->datosGasto($categoriaId, $adminId, $sufijo, [
                'nro_factura_proveedor' => $factura,
                'descripcion'           => 'Recompra tras borrado ' . $sufijo,
                'monto_total'           => 999.99,
                'fecha_gasto'           => '2098-11-20',
            ]));
            $this->assertTrue($id2 > 0, 'Re-registrar la misma factura tras soft-delete debe funcionar (identidad F liberada)');

            // Prueba SQL directa: mientras la fila re-registrada está activa, el duplicado F se rechaza
            $codigoActivo = null;
            try {
                $this->insertarGastoDirecto($db, $categoriaId, $adminId, $proveedor, $factura, 'Duplicado activo ' . $sufijo, 111.11, '2098-11-21');
            } catch (PDOException $e) {
                $codigoActivo = (string)$e->getCode();
            }
            $this->assertEquals('23000', $codigoActivo, 'Mientras la identidad F esté activa, el duplicado directo se rechaza');

            // Se libera de nuevo (soft-delete) y la inserción directa pasa
            $db->prepare("UPDATE gastos_comunes SET deleted_at = NOW() WHERE proveedor = :p AND deleted_at IS NULL")->execute(['p' => $proveedor]);
            $this->insertarGastoDirecto($db, $categoriaId, $adminId, $proveedor, $factura, 'Tercera compra ' . $sufijo, 222.22, '2098-11-22');
            $this->assertTrue(true, 'Tras el soft-delete la identidad F queda libre (inserción directa exitosa)');
        } finally {
            $this->limpiarGastos($db);
        }
    }

    public function testCrearGastoDuplicadoSinFacturaEsBloqueadoYDistintoPasa(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->columnaExiste($db, 'gastos_comunes', 'dup_guard')) {
            $this->skip('migración Fase 18 no aplicada');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));

        try {
            $datos = $this->datosGasto($categoriaId, $adminId, $sufijo);
            $id1 = $model->crearGasto($datos);
            $this->assertTrue($id1 > 0, 'El primer gasto sin factura debe registrarse');

            // Flujo legítimo: descripción distinta => identidad S distinta => pasa
            $id2 = $model->crearGasto($this->datosGasto($categoriaId, $adminId, $sufijo, [
                'descripcion' => 'Servicio contratado ' . $sufijo . ' (segunda quincena)',
            ]));
            $this->assertTrue($id2 > 0, 'Un gasto sin factura con descripción distinta debe permitirse');

            // Duplicado exacto => pre-chequeo S lo bloquea
            $this->expectExceptionMessage('Ya existe un gasto', function () use ($model, $datos) {
                $model->crearGasto($datos);
            });
        } finally {
            $this->limpiarGastos($db);
        }
    }

    public function testCarreraDeGuardiaUnicaDevuelveCeroSinLanzar(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->indiceExiste($db, 'gastos_comunes', 'uk_gasto_dup_guard')) {
            $this->skip('migración Fase 18 no aplicada (uk_gasto_dup_guard)');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));
        $prefijoDescripcion = str_repeat('Z', 60); // LEFT(descripcion, 60) colisiona en la guarda

        try {
            $id1 = $model->crearGasto($this->datosGasto($categoriaId, $adminId, $sufijo, [
                'descripcion' => $prefijoDescripcion . 'ALFA',
            ]));
            $this->assertTrue($id1 > 0, 'El primer gasto debe registrarse');

            // El pre-chequeo compara la descripción completa (no colisiona), pero la
            // guarda única usa LEFT(descripcion,60): el INSERT captura 23000 y retorna 0.
            $resultado = $model->crearGasto($this->datosGasto($categoriaId, $adminId, $sufijo, [
                'descripcion' => $prefijoDescripcion . 'BETA',
            ]));
            $this->assertEquals(0, $resultado, 'La carrera de la guarda única debe retornar 0 sin lanzar excepción');
        } finally {
            $this->limpiarGastos($db);
        }
    }

    public function testGastoSoftDeletedLiberaIdentidadSinFactura(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->indiceExiste($db, 'gastos_comunes', 'uk_gasto_dup_guard')) {
            $this->skip('migración Fase 18 no aplicada (uk_gasto_dup_guard)');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));

        try {
            $datos = $this->datosGasto($categoriaId, $adminId, $sufijo);
            $id1 = $model->crearGasto($datos);
            $this->assertTrue($id1 > 0, 'El primer gasto sin factura debe registrarse');

            $db->prepare("UPDATE gastos_comunes SET deleted_at = NOW() WHERE id = :id")->execute(['id' => $id1]);

            // El gasto borrado ya no cuenta para el pre-chequeo ni para la guarda
            $id2 = $model->crearGasto($datos);
            $this->assertTrue($id2 > 0, 'Un gasto borrado debe liberar la identidad S');
        } finally {
            $this->limpiarGastos($db);
        }
    }

    // =================================================================
    // 3. importarGastosMaestro
    // =================================================================

    public function testImportarMaestroIdempotenciaPorHash(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->columnaExiste($db, 'gastos_comunes', 'soporte_hash')) {
            $this->skip('migración Fase 18 no aplicada (soporte_hash)');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));
        $hashPdf = hash('sha256', 'pdf-maestro-' . $sufijo);

        try {
            $items = [
                [
                    'categoria_id' => $categoriaId,
                    'descripcion'  => 'Renglón maestro ' . $sufijo,
                    'monto_total'  => 55.10,
                    'fecha_gasto'  => '2098-11-15',
                    'proveedor'    => self::PREFIJO . $sufijo,
                ],
            ];

            $primera = $model->importarGastosMaestro($items, $adminId, 'maestro_' . $sufijo . '.pdf', 11, 2098, $hashPdf);
            $this->assertEquals(1, $primera['procesados'], 'La primera importación debe procesar el renglón');
            $this->assertFalse((bool)$primera['archivo_ya_importado'], 'La primera importación no es duplicada');

            $hashGuardado = $db->query("SELECT soporte_hash FROM gastos_comunes WHERE proveedor = '" . self::PREFIJO . $sufijo . "' LIMIT 1")->fetchColumn();
            $this->assertEquals($hashPdf, $hashGuardado, 'Cada fila debe almacenar el hash del PDF Maestro');

            // Re-ingesta del mismo PDF para el mismo período => sin inserts
            $segunda = $model->importarGastosMaestro($items, $adminId, 'maestro_' . $sufijo . '.pdf', 11, 2098, $hashPdf);
            $this->assertTrue((bool)$segunda['archivo_ya_importado'], 'La re-ingesta debe reportar archivo_ya_importado');
            $this->assertEquals(0, $segunda['procesados'], 'La re-ingesta no debe insertar');
            $this->assertEquals(count($items), $segunda['omitidos'], 'La re-ingesta debe omitir todos los renglones');
            $this->assertEquals(count($items), $segunda['duplicados'], 'La re-ingesta debe contar los renglones duplicados');

            $total = intval($db->query("SELECT COUNT(*) FROM gastos_comunes WHERE proveedor = '" . self::PREFIJO . $sufijo . "'")->fetchColumn());
            $this->assertEquals(1, $total, 'La re-ingesta no debe crear filas adicionales');
        } finally {
            $this->limpiarGastos($db);
        }
    }

    public function testImportarMaestroOmiteFilasDuplicadasEnCarrera(): void {
        $db = Database::getConnection();
        $categoriaId = $this->categoriaId($db);
        $adminId = $this->adminId($db);
        if ($categoriaId <= 0 || $adminId <= 0) {
            $this->skip('Datos insuficientes (categorías/usuarios) para la prueba.');
            return;
        }
        if (!$this->indiceExiste($db, 'gastos_comunes', 'uk_gasto_dup_guard')) {
            $this->skip('migración Fase 18 no aplicada (uk_gasto_dup_guard)');
            return;
        }

        $model = new GastosModel();
        $sufijo = bin2hex(random_bytes(4));

        try {
            $itemRepetido = [
                'categoria_id' => $categoriaId,
                'descripcion'  => 'Renglón repetido ' . $sufijo,
                'monto_total'  => 77.70,
                'fecha_gasto'  => '2098-11-16',
                'proveedor'    => self::PREFIJO . $sufijo,
            ];
            $itemDistinto = [
                'categoria_id' => $categoriaId,
                'descripcion'  => 'Renglón distinto ' . $sufijo,
                'monto_total'  => 88.80,
                'fecha_gasto'  => '2098-11-17',
                'proveedor'    => self::PREFIJO . $sufijo,
            ];

            // Dos renglones idénticos sin factura: el segundo choca con la guarda
            // S dentro de la misma transacción y se reporta como omitido.
            $resultado = $model->importarGastosMaestro(
                [$itemRepetido, $itemRepetido, $itemDistinto],
                $adminId,
                'maestro_carrera_' . $sufijo . '.pdf',
                11,
                2098,
                null
            );

            $this->assertEquals(2, $resultado['procesados'], 'Los renglones distintos deben procesarse');
            $this->assertEquals(1, $resultado['omitidos'], 'El renglón repetido debe omitirse en carrera');
            $this->assertEquals(1, $resultado['duplicados'], 'El conteo de duplicados debe reflejar la omisión');
            $this->assertFalse((bool)$resultado['archivo_ya_importado'], 'Sin hash previo no es re-ingesta');
        } finally {
            $this->limpiarGastos($db);
        }
    }

    public function testGastoControllerMuestraMensajeDuplicado(): void {
        $contenido = (string)file_get_contents(dirname(__DIR__) . '/app/controllers/GastoController.php');
        $this->assertStringContains('Ya existe un gasto con los mismos datos en el período', $contenido,
            'GastoController::guardar debe mostrar el Flash específico de duplicado');
    }
}
