<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Database;
use App\Core\UserRole;
use App\Core\RateLimiter;
use PDO;

class RespaldoController extends Controller {

    /**
     * Muestra el panel de administración de respaldos de base de datos.
     */
    public function index() {
        Auth::requireRole(UserRole::ADMIN);

        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT b.*, COALESCE(u.nombre_completo, 'Automático (CLI)') AS admin_nombre
            FROM backups_log b
            LEFT JOIN usuarios u ON b.admin_id = u.id
            ORDER BY b.id DESC
        ");
        $respaldos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('admin/respaldos/index', [
            'respaldos' => $respaldos,
            'layout'    => 'admin',
            'title'     => 'Respaldos de Base de Datos (RNF 3)'
        ]);
    }

    /**
     * Genera un nuevo respaldo de la base de datos de forma manual.
     */
    public function generarManual() {
        Auth::requireRole(UserRole::ADMIN);

        // Rate limiting: máximo 3 respaldos por hora
        if (!RateLimiter::attempt('backup', 3, 3600)) {
            $segundos = RateLimiter::secondsUntilAvailable('backup', 3600);
            $minutos = ceil($segundos / 60);
            Flash::set('danger', "Debe esperar {$minutos} minuto(s) antes de generar otro respaldo.");
            $this->redirect('/admin/respaldos');
            return;
        }

        $backupScript = dirname(__DIR__, 2) . '/scripts/backup_database.php';

        if (!file_exists($backupScript)) {
            Flash::set('danger', 'Script de respaldo no encontrado. Contacte al administrador del sistema.');
            $this->redirect('/admin/respaldos');
            return;
        }

        try {
            // Ejecutar script CLI de respaldo con sanitización completa
            $phpBin = escapeshellarg(PHP_BINARY ?: 'php');
            $scriptPath = escapeshellarg($backupScript);
            $output = [];
            $returnVar = 0;
            exec("{$phpBin} {$scriptPath} 2>&1", $output, $returnVar);

            if ($returnVar === 0) {
                Flash::set('success', 'Respaldo de base de datos generado exitosamente con compresión y firma SHA-256.');
            } else {
                error_log("[RESPALDO] Output: " . implode("\n", $output));
                Flash::set('danger', 'Error al generar respaldo. Revise los logs del servidor para más detalles.');
            }
        } catch (\Exception $e) {
            error_log("[RESPALDO] Error ejecutar respaldo: " . $e->getMessage());
            Flash::set('danger', 'Error al generar el respaldo de base de datos.');
        }

        $this->redirect('/admin/respaldos');
    }

    /**
     * Descarga de forma segura un archivo de respaldo .sql.gz.
     */
    public function descargar(string $id) {
        Auth::requireRole(UserRole::ADMIN);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM backups_log WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        $respaldo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$respaldo) {
            Flash::set('danger', 'Respaldo no encontrado.');
            $this->redirect('/admin/respaldos');
            return;
        }

        $backupDir = realpath(dirname(__DIR__, 2) . '/storage/backups');
        $rutaArchivo = $backupDir ? $backupDir . '/' . basename($respaldo['nombre_archivo']) : null;
        $resolvedPath = $rutaArchivo ? realpath($rutaArchivo) : null;

        if (!$backupDir || !$resolvedPath || strpos($resolvedPath, $backupDir) !== 0) {
            Flash::set('danger', 'Ruta de respaldo no válida.');
            $this->redirect('/admin/respaldos');
            return;
        }

        if (!file_exists($resolvedPath)) {
            Flash::set('danger', 'El archivo físico de respaldo ya no existe en el servidor (purgado por política de retención).');
            $this->redirect('/admin/respaldos');
            return;
        }

        $rutaArchivo = $resolvedPath;

        // 6E.3: Verificar integridad SHA-256 si el checksum está registrado
        if (!empty($respaldo['checksum_sha256'])) {
            $checksumActual = hash_file('sha256', $rutaArchivo);
            if ($checksumActual !== $respaldo['checksum_sha256']) {
                Flash::set('danger', '¡ALERTA DE INTEGRIDAD! El archivo de respaldo ha sido modificado. El checksum SHA-256 no coincide.');
                $this->redirect('/admin/respaldos');
                return;
            }
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($rutaArchivo) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('X-Checksum-SHA256: ' . ($respaldo['checksum_sha256'] ?? hash_file('sha256', $rutaArchivo)));
        header('Content-Length: ' . filesize($rutaArchivo));
        readfile($rutaArchivo);
        exit;
    }
}
