<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\MovimientosModel;

class EstadoCuentaController extends Controller {

    /**
     * Muestra el libro mayor personal y estado de cuenta del residente.
     */
    public function index() {
        Auth::requireRole('residente');

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? 0;
        $pagina = max(1, intval($_GET['page'] ?? 1));

        $db = \App\Core\Database::getConnection();
        $stmtU = $db->prepare("
            SELECT u.*, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            WHERE u.propietario_id = :pid
            LIMIT 1
        ");
        $stmtU->execute(['pid' => $personaId]);
        $unidad = $stmtU->fetch(\PDO::FETCH_ASSOC);

        $movimientos = [];
        $paginacion = ['total' => 0, 'pagina' => 1, 'porPagina' => 20, 'totalPaginas' => 1];
        $saldoActual = 0.00;

        if ($unidad) {
            $movimientosModel = new MovimientosModel();
            $resultado = $movimientosModel->obtenerHistorialUnidad($unidad['id'], $pagina, 20);
            $saldoActual = $movimientosModel->obtenerSaldoActualUnidad($unidad['id']);

            $movimientos = $resultado['datos'];
            $paginacion = [
                'total'        => $resultado['total'],
                'pagina'       => $resultado['pagina'],
                'porPagina'    => $resultado['porPagina'],
                'totalPaginas' => $resultado['totalPaginas'],
            ];
        }

        $this->render('residente/estado_cuenta', [
            'unidad'      => $unidad,
            'movimientos' => $movimientos,
            'saldoActual' => $saldoActual,
            'paginacion'  => $paginacion,
            'title'       => 'Mi Estado de Cuenta y Libro Mayor'
        ]);
    }

    /**
     * Vista de impresión formal del estado de cuenta de la unidad.
     */
    public function imprimir() {
        Auth::requireRole('residente');

        $user = Auth::user();
        $personaId = $user['persona_id'] ?? 0;

        // 6D.1: Protección contra enumeración — verificar que el usuario tiene unidad
        $db = \App\Core\Database::getConnection();
        $stmtU = $db->prepare("
            SELECT u.*, COALESCE(e.nombre, 'Sin Torre') AS edificio_nombre,
                   CONCAT(p.nombre, ' ', p.apellido) AS propietario_nombre, p.cedula AS propietario_cedula
            FROM unidades u
            LEFT JOIN edificios e ON u.edificio_id = e.id
            LEFT JOIN personas p ON u.propietario_id = p.id
            WHERE u.propietario_id = :pid
            LIMIT 1
        ");
        $stmtU->execute(['pid' => $personaId]);
        $unidad = $stmtU->fetch(\PDO::FETCH_ASSOC);

        if (!$unidad) {
            $this->render('errors/404', ['title' => 'Unidad no encontrada']);
            return;
        }

        $movimientosModel = new MovimientosModel();
        // 6D.2: LIMIT 500 para impresión
        $resultado = $movimientosModel->obtenerHistorialUnidad($unidad['id'], 1, 500);
        $saldoActual = $movimientosModel->obtenerSaldoActualUnidad($unidad['id']);

        $this->render('residente/estado_cuenta_imprimir', [
            'unidad'      => $unidad,
            'movimientos' => $resultado['datos'],
            'saldoActual' => $saldoActual,
            'title'       => 'Estado de Cuenta Oficial - Apto ' . $unidad['numero']
        ]);
    }
}
