<?php
namespace Tests;

use Tests\TestCase;

/**
 * Tests de Modelos — Estructura, patrones y seguridad en models
 *
 * Verifica que los modelos sigan patrones correctos de consultas
 * preparadas, transacciones y manejo de errores.
 */
class ModelTest extends TestCase {

    private string $modelsPath;

    public function __construct() {
        $this->modelsPath = dirname(__DIR__) . '/app/models/';
    }

    private function getModelFiles(): array {
        return glob($this->modelsPath . '*.php');
    }

    // =====================================================================
    // ESTRUCTURA GENERAL DE MODELOS
    // =====================================================================

    /**
     * Verifica que todos los archivos de modelo existan.
     */
    public function testAllModelFilesExist(): void {
        $expected = [
            'UsuariosModel.php',
            'PersonasModel.php',
            'PagoModel.php',
            'ComprobantesModel.php',
            'FacturasModel.php',
            'EdificiosModel.php',
            'UnidadesModel.php',
            'EstacionamientosModel.php',
            'VehiculosModel.php',
        ];

        foreach ($expected as $file) {
            $this->assertFileExists($this->modelsPath . $file,
                "Modelo {$file} debe existir");
        }
    }

    /**
     * Verifica que todos los modelos usen namespace App\Models.
     */
    public function testModelsUseCorrectNamespace(): void {
        foreach ($this->getModelFiles() as $file) {
            $content = file_get_contents($file);
            $modelName = basename($file);

            $this->assertStringContains('namespace App\\Models', $content,
                "{$modelName} debe usar namespace App\\Models");
        }
    }

    /**
     * Verifica que todos los modelos importen Database o extiendan BaseModel.
     */
    public function testModelsImportDatabase(): void {
        foreach ($this->getModelFiles() as $file) {
            $content = file_get_contents($file);
            $modelName = basename($file);

            $this->assertTrue(
                str_contains($content, 'use App\\Core\\Database') || str_contains($content, 'extends BaseModel'),
                "{$modelName} debe importar Database o extender BaseModel"
            );
        }
    }

    /**
     * Verifica que todos los modelos usen getConnection() o $this->db().
     */
    public function testModelsUseGetConnection(): void {
        foreach ($this->getModelFiles() as $file) {
            $content = file_get_contents($file);
            $modelName = basename($file);

            $this->assertTrue(
                str_contains($content, 'Database::getConnection()') || str_contains($content, '$this->db()'),
                "{$modelName} debe usar Database::getConnection() o \$this->db()"
            );
        }
    }

    // =====================================================================
    // CONSULTAS PREPARADAS
    // =====================================================================

    /**
     * Verifica que todos los modelos usen prepare() antes de execute().
     */
    public function testModelsUsePrepareStatements(): void {
        foreach ($this->getModelFiles() as $file) {
            $content = file_get_contents($file);
            $modelName = basename($file);

            $prepareCount = substr_count($content, '->prepare(');
            $executeCount = substr_count($content, '->execute(');
            $queryCount = substr_count($content, '->query(');

            if ($executeCount > 0 && $modelName !== 'BaseModel.php') {
                $this->assertGreaterThan(0, $prepareCount,
                    "{$modelName} tiene execute() sin prepare() correspondiente");
            }
        }
    }

    /**
     * Verifica que no haya interpolación de variables PHP en strings SQL.
     */
    public function testNoDirectVariableInterpolationInSql(): void {
        $dangerousPatterns = [
            '/\$_GET\s*\[/',     // $_GET directo en SQL
            '/\$_POST\s*\[/',    // $_POST directo en SQL
            '/\$_REQUEST\s*\[/', // $_REQUEST directo en SQL
        ];

        foreach ($this->getModelFiles() as $file) {
            $content = file_get_contents($file);
            $modelName = basename($file);

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $this->failures[] = "SQL INJECTION: {$modelName} usa variable de superglobal directamente en query";
                    $this->failed++;
                    return;
                }
            }
        }

        $this->passed++;
    }

    // =====================================================================
    // PAGOMODEL — TRANSACCIONES
    // =====================================================================

    /**
     * Verifica que PagoModel::cambiarEstado() use transacciones.
     */
    public function testPagoModelCambiarEstadoUsesTransaction(): void {
        $content = file_get_contents($this->modelsPath . 'PagoModel.php');

        $this->assertStringContains('beginTransaction', $content,
            "PagoModel::cambiarEstado() debe usar beginTransaction()");
        $this->assertStringContains('commit', $content,
            "PagoModel::cambiarEstado() debe usar commit()");
        $this->assertStringContains('rollBack', $content,
            "PagoModel::cambiarEstado() debe usar rollBack()");
    }

    /**
     * Verifica que PagoModel::cambiarEstado() use FOR UPDATE para bloqueo de fila.
     */
    public function testPagoModelUsesForUpdate(): void {
        $content = file_get_contents($this->modelsPath . 'PagoModel.php');

        $this->assertStringContains('FOR UPDATE', $content,
            "PagoModel::cambiarEstado() debe usar FOR UPDATE para evitar carreras de datos");
    }

    /**
     * Verifica que cambiarEstado valide el estado anterior antes de actualizar.
     */
    public function testCambiarEstadoChecksPreviousState(): void {
        $content = file_get_contents($this->modelsPath . 'PagoModel.php');

        $this->assertStringContains('SELECT estado FROM pagos', $content,
            "cambiarEstado() debe obtener el estado anterior");
    }

    // =====================================================================
    // COMPROBANTESMODEL — TRANSACCIONES
    // =====================================================================

    /**
     * Verifica que ComprobantesModel::aprobar() use transacciones.
     */
    public function testComprobantesModelAprobarUsesTransaction(): void {
        $content = file_get_contents($this->modelsPath . 'ComprobantesModel.php');

        $this->assertStringContains('beginTransaction', $content,
            "ComprobantesModel::aprobar() debe usar beginTransaction()");
        $this->assertStringContains('commit', $content,
            "ComprobantesModel::aprobar() debe usar commit()");
        $this->assertStringContains('rollBack', $content,
            "ComprobantesModel::aprobar() debe usar rollBack()");
    }

    /**
     * Verifica que aprobar() valide el estado del comprobante.
     */
    public function testAprobarValidatesState(): void {
        $content = file_get_contents($this->modelsPath . 'ComprobantesModel.php');

        $this->assertStringContains("'pendiente'", $content,
            "aprobar() debe verificar que el comprobante esté en estado 'pendiente'");
    }

    // =====================================================================
    // FACTURASMODEL — LÓGICA DE NEGOCIO
    // =====================================================================

    /**
     * Verifica que crearFacturasMasivas() use transacciones.
     */
    public function testFacturasMasivasUsesTransaction(): void {
        $content = file_get_contents($this->modelsPath . 'FacturasModel.php');

        $this->assertStringContains('beginTransaction', $content,
            "crearFacturasMasivas() debe usar beginTransaction()");
        $this->assertStringContains('commit', $content,
            "crearFacturasMasivas() debe usar commit()");
        $this->assertStringContains('rollBack', $content,
            "crearFacturasMasivas() debe usar rollBack()");
    }

    /**
     * Verifica que crearFacturasMasivas() maneje saldo a favor.
     */
    public function testFacturasMasivasHandlesSaldoFavor(): void {
        $content = file_get_contents($this->modelsPath . 'FacturasModel.php');

        $this->assertStringContains('saldo_favor', $content,
            "crearFacturasMasivas() debe manejar saldo a favor");

        $this->assertStringContains('UPDATE facturas SET saldo = 0', $content,
            "crearFacturasMasivas() debe consumir el saldo a favor");
    }

    // =====================================================================
    // EDIFICIOSMODEL — CRUD COMPLETO
    // =====================================================================

    /**
     * Verifica que EdificiosModel tenga operaciones CRUD básicas.
     */
    public function testEdificiosModelHasCrud(): void {
        $content = file_get_contents($this->modelsPath . 'EdificiosModel.php');

        $this->assertTrue(
            str_contains($content, 'INSERT INTO edificios') || str_contains($content, "parent::create('edificios'"),
            "EdificiosModel debe tener CREATE"
        );
        $this->assertTrue(
            str_contains($content, 'UPDATE edificios') || str_contains($content, "parent::update('edificios'"),
            "EdificiosModel debe tener UPDATE"
        );
        $this->assertStringContains('SELECT', $content,
            "EdificiosModel debe tener READ");
    }

    /**
     * Verifica que toggleEstado() alterne entre 0 y 1.
     */
    public function testEdificiosToggleState(): void {
        $content = file_get_contents($this->modelsPath . 'EdificiosModel.php');

        $this->assertTrue(
            str_contains($content, 'IF(estado = 1, 0, 1)') || str_contains($content, "parent::toggleEstado('edificios'"),
            "toggleEstado() debe alternar usando IF(estado = 1, 0, 1) o delegar a BaseModel"
        );
    }

    // =====================================================================
    // UNIDADESMODEL — CRUD COMPLETO
    // =====================================================================

    /**
     * Verifica que UnidadesModel tenga operaciones CRUD básicas.
     */
    public function testUnidadesModelHasCrud(): void {
        $content = file_get_contents($this->modelsPath . 'UnidadesModel.php');

        $this->assertTrue(
            str_contains($content, 'INSERT INTO unidades') || str_contains($content, "parent::create('unidades'"),
            "UnidadesModel debe tener CREATE"
        );
        $this->assertTrue(
            str_contains($content, 'UPDATE unidades') || str_contains($content, "parent::update('unidades'"),
            "UnidadesModel debe tener UPDATE"
        );
        $this->assertStringContains('SELECT', $content,
            "UnidadesModel debe tener READ");
    }

    /**
     * Verifica que UnidadesModel tenga validación de duplicados.
     */
    public function testUnidadesModelHasDuplicateCheck(): void {
        $content = file_get_contents($this->modelsPath . 'UnidadesModel.php');

        $this->assertStringContains('numeroExists', $content,
            "UnidadesModel debe tener metodo numeroExists() para validar duplicados");
    }

    // =====================================================================
    // PERSONASMODEL — REGISTRO
    // =====================================================================

    /**
     * Verifica que PersonasModel.register() actualice email y password.
     */
    public function testPersonasModelRegister(): void {
        $content = file_get_contents($this->modelsPath . 'PersonasModel.php');

        $this->assertStringContains('UPDATE personas SET email = :email, password = :password', $content,
            "register() debe actualizar email y password en la tabla personas");
    }

    /**
     * Verifica que PersonasModel.emailExists() cuente registros.
     */
    public function testPersonasModelEmailExists(): void {
        $content = file_get_contents($this->modelsPath . 'PersonasModel.php');

        $this->assertTrue(
            str_contains($content, 'COUNT(*)') || str_contains($content, "exists('personas'"),
            "emailExists() debe usar COUNT(*) o delegar a BaseModel::exists()"
        );
    }

    /**
     * Verifica que PersonasModel tenga métodos para gestión completa de residentes.
     */
    public function testPersonasModelResidentMethodsExist(): void {
        $content = file_get_contents($this->modelsPath . 'PersonasModel.php');

        $this->assertStringContains('function getByUnidadId', $content, "Debe existir getByUnidadId()");
        $this->assertStringContains('function createResidente', $content, "Debe existir createResidente()");
        $this->assertStringContains('function updateResidente', $content, "Debe existir updateResidente()");
        $this->assertStringContains('function desvincularResidente', $content, "Debe existir desvincularResidente()");
        $this->assertStringContains('function getByCedula', $content, "Debe existir getByCedula()");
        $this->assertStringContains('function emailExistsActive', $content, "Debe existir emailExistsActive()");
    }

    /**
     * Verifica que UnidadesModel tenga métodos para asignación y gestión de propietario.
     */
    public function testUnidadesModelOwnerMethodsExist(): void {
        $content = file_get_contents($this->modelsPath . 'UnidadesModel.php');

        $this->assertStringContains('function setPropietario', $content, "Debe existir setPropietario()");
        $this->assertStringContains('function gestionarBajaPropietario', $content, "Debe existir gestionarBajaPropietario()");
    }

    // =====================================================================
    // MANEJO DE ERRORES
    // =====================================================================

    /**
     * Verifica que los modelos con transacciones tengan try/catch.
     */
    public function testTransactionalModelsHaveTryCatch(): void {
        $transactionalMethods = [
            ['PagoModel.php', 'beginTransaction'],
            ['ComprobantesModel.php', 'beginTransaction'],
            ['FacturasModel.php', 'beginTransaction'],
        ];

        foreach ($transactionalMethods as [$file, $keyword]) {
            $content = file_get_contents($this->modelsPath . $file);
            $hasTryCatch = str_contains($content, 'try') && str_contains($content, 'catch');

            $this->assertTrue($hasTryCatch,
                "{$file} usa transactions pero no tiene try/catch");
        }
    }

    /**
     * Verifica que los errores se registren en log.
     */
    public function testTransactionErrorsAreLogged(): void {
        $transactionalFiles = ['PagoModel.php', 'ComprobantesModel.php', 'FacturasModel.php'];

        foreach ($transactionalFiles as $file) {
            $content = file_get_contents($this->modelsPath . $file);

            if (str_contains($content, 'beginTransaction')) {
                $this->assertStringContains('error_log', $content,
                    "{$file} debe registrar errores en log");
            }
        }
    }
}
