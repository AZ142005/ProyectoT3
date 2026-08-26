<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Database;
use App\Core\UserRole;
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

        $backupScript = dirname(__DIR__, 2) . '/scripts/backup_database.php';

        try {
            // Ejecutar script CLI de respaldo
            $phpBin = PHP_BINARY ?: 'php';
            $output = [];
            $returnVar = 0;
            exec("{$phpBin} " . escapeshellarg($backupScript) . " 2>&1", $output, $returnVar);

            if ($returnVar === 0) {
                Flash::set('success', 'Respaldo de base de datos generado exitosamente con compresión y firma SHA-256.');
            } else {
                Flash::set('danger', 'Error al generar respaldo: ' . implode("\n", array_slice($output, -3)));
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

        $rutaArchivo = dirname(__DIR__, 2) . '/storage/backups/' . basename($respaldo['nombre_archivo']);
        if (!file_exists($rutaArchivo)) {
            Flash::set('danger', 'El archivo físico de respaldo ya no existe en el servidor (purgado por política de retención).');
            $this->redirect('/admin/respaldos');
            return;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($rutaArchivo) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($rutaArchivo));
        readfile($rutaArchivo);
        exit;
    }
}
