<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Database;
use App\Core\UserRole;
use App\Core\RateLimiter;
use App\Services\DataRetentionService;
use PDO;

class RespaldoController extends Controller {

    /**
     * Muestra el panel de administración de respaldos de base de datos.
     * Política de respaldo único: solo se presenta el respaldo vigente
     * (la fila más reciente de backups_log).
     */
    public function index() {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT b.*, COALESCE(u.nombre_completo, 'Automático (CLI)') AS admin_nombre
            FROM backups_log b
            LEFT JOIN usuarios u ON b.admin_id = u.id
            ORDER BY b.id DESC
            LIMIT 1
        ");
        $respaldo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Retención de datos acumulados: estadísticas por tabla e historial de
        // exportes/purgas (listarPurgas devuelve [] si la migración no se aplicó).
        $servicioRetencion = new DataRetentionService($db);
        $retencion = $servicioRetencion->estadisticas();
        $purgas = $servicioRetencion->listarPurgas(25);

        $this->render('admin/respaldos/index', [
            'respaldo'  => $respaldo,
            'retencion' => $retencion,
            'purgas'    => $purgas,
            'layout'    => 'admin',
            'title'     => 'Respaldos Automatizados del Servidor (RNF 3)'
        ]);
    }

    /**
     * Genera un nuevo respaldo de la base de datos de forma manual.
     */
    public function generarManual() {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        // La generación manual desde web fue reemplazada por automatización de servidor
        Flash::set('info', 'La generación manual ha sido deshabilitada. Los respaldos se ejecutan automáticamente a nivel de servidor (CLI / Cron).');
        $this->redirect('/admin/respaldos');
    }

    /**
     * Descarga de forma segura un archivo de respaldo .sql.gz.
     */
    public function descargar(string $id) {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

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

        if (!$backupDir || !$rutaArchivo) {
            Flash::set('danger', 'Ruta de respaldo no válida.');
            $this->redirect('/admin/respaldos');
            return;
        }

        // Archivo ya eliminado del servidor (reemplazo diario o ciclo único):
        // mensaje amable antes que cualquier otra validación de ruta.
        if (!file_exists($rutaArchivo)) {
            Flash::set('danger', 'El archivo físico de respaldo ya no existe en el servidor (purgado por política de retención).');
            $this->redirect('/admin/respaldos');
            return;
        }

        // Contención de ruta (defensa en profundidad; basename ya la asegura).
        $resolvedPath = realpath($rutaArchivo);
        if (!$resolvedPath || strpos($resolvedPath, $backupDir) !== 0) {
            Flash::set('danger', 'Ruta de respaldo no válida.');
            $this->redirect('/admin/respaldos');
            return;
        }

        $rutaArchivo = $resolvedPath;

        // 6E.3: Verificar integridad SHA-256. Las filas reales del respaldo
        // general registran el hash en hash_sha256; checksum_sha256 es un
        // alias legado (NULL en esas filas) y solo manda si viniera poblado.
        $hashEsperado = !empty($respaldo['checksum_sha256'])
            ? (string)$respaldo['checksum_sha256']
            : (string)($respaldo['hash_sha256'] ?? '');
        if ($hashEsperado !== '') {
            $checksumActual = hash_file('sha256', $rutaArchivo);
            if ($checksumActual !== $hashEsperado) {
                Flash::set('danger', '¡ALERTA DE INTEGRIDAD! El archivo de respaldo ha sido modificado. El checksum SHA-256 no coincide.');
                $this->redirect('/admin/respaldos');
                return;
            }
        }

        // Ciclo único: marcar la descarga con el reloj de la base de datos
        // (NOW() de MySQL) ANTES de transmitir; el archivo se eliminará del
        // servidor 7 días después de esta descarga.
        $marcar = $db->prepare("UPDATE backups_log SET fecha_descarga = NOW() WHERE id = :id");
        $marcar->execute(['id' => (int)$respaldo['id']]);

        header('Content-Description: File Transfer');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($rutaArchivo) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('X-Checksum-SHA256: ' . ($hashEsperado !== '' ? $hashEsperado : hash_file('sha256', $rutaArchivo)));
        header('Content-Length: ' . filesize($rutaArchivo));
        readfile($rutaArchivo);
        exit;
    }

    /**
     * Exporta (sin borrar) los registros elegibles de una tabla con retención.
     * Solo admin: genera un respaldo dirigido .sql.gz + SHA-256 en
     * storage/backups/purgas/ y lo registra en purgas_log.
     */
    public function exportarAcumulados() {
        Auth::requireRole(UserRole::ADMIN);

        $tabla = trim((string)($_POST['tabla'] ?? ''));

        try {
            $servicio = new DataRetentionService();
            $resultado = $servicio->exportar($tabla, 'manual', Auth::id());
        } catch (\InvalidArgumentException $e) {
            Flash::set('danger', $e->getMessage());
            $this->redirect('/admin/respaldos');
            return;
        }

        if (!$resultado['exito']) {
            Flash::set('danger', 'No se pudo exportar: ' . $resultado['mensaje']);
        } elseif ($resultado['estado'] === 'sin_elegibles') {
            Flash::set('info', $resultado['mensaje']);
        } else {
            Flash::set('success', sprintf(
                'Exporte generado para %s: %s registro(s) en %s (SHA-256 %s…).',
                $resultado['tabla'],
                number_format($resultado['registros_exportados'], 0, ',', '.'),
                $resultado['archivo'],
                substr((string)$resultado['hash_sha256'], 0, 12)
            ));
        }

        $this->redirect('/admin/respaldos');
    }

    /**
     * Purga los registros elegibles de una tabla con retención, siempre
     * precedida de un export verificado (respaldo previo obligatorio).
     * Solo admin y confirmación explícita en la vista (operación destructiva).
     */
    public function purgarAcumulados() {
        Auth::requireRole(UserRole::ADMIN);

        $tabla = trim((string)($_POST['tabla'] ?? ''));

        try {
            $servicio = new DataRetentionService();
            $resultado = $servicio->purgar($tabla, 'manual', Auth::id());
        } catch (\InvalidArgumentException $e) {
            Flash::set('danger', $e->getMessage());
            $this->redirect('/admin/respaldos');
            return;
        }

        if (!$resultado['exito']) {
            Flash::set('danger', 'No se pudo purgar: ' . $resultado['mensaje']);
        } elseif ($resultado['estado'] === 'sin_elegibles') {
            Flash::set('info', $resultado['mensaje']);
        } elseif ($resultado['estado'] === 'parcial') {
            Flash::set('warning', $resultado['mensaje'] . ' La operación quedó registrada como parcial en la bitácora.');
        } else {
            Flash::set('success', $resultado['mensaje'] . ' Respaldo previo verificado con SHA-256.');
        }

        $this->redirect('/admin/respaldos');
    }

    /**
     * Descarga un exporte de purga/exportación con verificación de integridad
     * SHA-256 contra el hash registrado en purgas_log.
     */
    public function descargarPurga(string $id) {
        Auth::requireRole([UserRole::ADMIN, UserRole::AUDITOR]);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM purgas_log WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        $purga = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purga || empty($purga['archivo'])) {
            Flash::set('danger', 'Exporte de purga no encontrado.');
            $this->redirect('/admin/respaldos');
            return;
        }

        $dirExportes = realpath(dirname(__DIR__, 2) . '/storage/backups/purgas');
        $rutaArchivo = $dirExportes ? $dirExportes . '/' . basename($purga['archivo']) : null;

        if (!$dirExportes || !$rutaArchivo) {
            Flash::set('danger', 'Ruta de exporte no válida.');
            $this->redirect('/admin/respaldos');
            return;
        }

        // Archivo ya eliminado del servidor (ciclo único): mensaje amable
        // antes que cualquier otra validación de ruta.
        if (!file_exists($rutaArchivo)) {
            Flash::set('danger', 'El archivo físico del exporte ya no existe (eliminado por política de retención).');
            $this->redirect('/admin/respaldos');
            return;
        }

        // Contención de ruta (defensa en profundidad; basename ya la asegura).
        $resolvedPath = realpath($rutaArchivo);
        if (!$resolvedPath || strpos($resolvedPath, $dirExportes) !== 0) {
            Flash::set('danger', 'Ruta de exporte no válida.');
            $this->redirect('/admin/respaldos');
            return;
        }

        // Verificación de integridad SHA-256 registrada al exportar/purgar.
        if (!empty($purga['hash_sha256'])) {
            $checksumActual = hash_file('sha256', $resolvedPath);
            if ($checksumActual !== $purga['hash_sha256']) {
                Flash::set('danger', '¡ALERTA DE INTEGRIDAD! El exporte fue modificado. El checksum SHA-256 no coincide.');
                $this->redirect('/admin/respaldos');
                return;
            }
        }

        // Ciclo único: marcar la descarga con NOW() de MySQL ANTES de
        // transmitir; el exporte se eliminará del servidor 7 días después.
        $marcar = $db->prepare("UPDATE purgas_log SET fecha_descarga = NOW() WHERE id = :id");
        $marcar->execute(['id' => (int)$purga['id']]);

        header('Content-Description: File Transfer');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($resolvedPath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('X-Checksum-SHA256: ' . ($purga['hash_sha256'] ?? hash_file('sha256', $resolvedPath)));
        header('Content-Length: ' . filesize($resolvedPath));
        readfile($resolvedPath);
        exit;
    }
}
