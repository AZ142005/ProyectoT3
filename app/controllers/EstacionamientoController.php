<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\EstacionamientosModel;
use App\Models\VehiculosModel;
use App\Models\EdificiosModel;
use App\Models\UnidadesModel;
use Exception;

class EstacionamientoController extends Controller {

    /**
     * Muestra el panel principal de gestión de estacionamientos y vehículos.
     */
    public function index() {
        Auth::requireRole('admin');

        $estacionamientosModel = new EstacionamientosModel();
        $edificiosModel = new EdificiosModel();
        $unidadesModel = new UnidadesModel();

        $puestos = $estacionamientosModel->listarConDetalles();
        $edificios = $edificiosModel->getActivos();
        $unidades = $unidadesModel->getActivas();

        // Cálculo de métricas KPI
        $kpis = [
            'total'       => count($puestos),
            'asignados'   => 0,
            'libres'      => 0,
            'techados'    => 0,
            'descubiertos' => 0,
            'visitantes'  => 0,
        ];

        foreach ($puestos as $p) {
            if (!empty($p['unidad_id'])) {
                $kpis['asignados']++;
            } else {
                $kpis['libres']++;
            }
            if ($p['tipo'] === 'techado') $kpis['techados']++;
            if ($p['tipo'] === 'descubierto') $kpis['descubiertos']++;
            if ($p['tipo'] === 'visitante') $kpis['visitantes']++;
        }

        $this->render('admin/estacionamientos/index', [
            'puestos'   => $puestos,
            'edificios' => $edificios,
            'unidades'  => $unidades,
            'kpis'      => $kpis,
            'layout'    => 'admin',
            'title'     => 'Gestión de Estacionamientos y Vehículos'
        ]);
    }

    /**
     * Guarda o actualiza un puesto de estacionamiento.
     */
    public function guardar() {
        Auth::requireRole('admin');

        $numero = trim($_POST['numero'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'descubierto');
        $edificioId = !empty($_POST['edificio_id']) ? intval($_POST['edificio_id']) : null;
        $unidadId = !empty($_POST['unidad_id']) ? intval($_POST['unidad_id']) : null;

        if (empty($numero)) {
            Flash::error("El número o identificador del puesto es obligatorio.");
            $this->redirect('/admin/estacionamientos');
        }

        // Validación estricta del tipo ENUM
        if (!in_array($tipo, EstacionamientosModel::TIPOS_VALIDOS, true)) {
            http_response_code(400);
            Flash::error("El tipo de estacionamiento seleccionado no es válido.");
            $this->redirect('/admin/estacionamientos');
        }

        $estacionamientosModel = new EstacionamientosModel();

        try {
            $id = !empty($_POST['id']) ? intval($_POST['id']) : null;

            if ($id) {
                $estacionamientosModel->update($id, [
                    'numero'      => $numero,
                    'tipo'        => $tipo,
                    'edificio_id' => $edificioId,
                    'unidad_id'   => $unidadId
                ]);
                Flash::success("Puesto de estacionamiento '{$numero}' actualizado correctamente.");
            } else {
                $estacionamientosModel->create([
                    'numero'      => $numero,
                    'tipo'        => $tipo,
                    'edificio_id' => $edificioId,
                    'unidad_id'   => $unidadId,
                    'estado'      => 1
                ]);
                Flash::success("Puesto de estacionamiento '{$numero}' registrado correctamente.");
            }
        } catch (Exception $e) {
            error_log("[ESTACIONAMIENTO] Error guardar puesto: " . $e->getMessage());
            Flash::error('Error al guardar el puesto de estacionamiento.');
        }

        $this->redirect('/admin/estacionamientos');
    }

    /**
     * Procesa la asignación o desasignación de un puesto a una unidad.
     */
    public function asignar() {
        Auth::requireRole('admin');

        $puestoId = intval($_POST['puesto_id'] ?? 0);
        $unidadId = !empty($_POST['unidad_id']) ? intval($_POST['unidad_id']) : null;

        if ($puestoId <= 0) {
            Flash::error("Identificador de puesto no válido.");
            $this->redirect('/admin/estacionamientos');
        }

        $estacionamientosModel = new EstacionamientosModel();
        try {
            $estacionamientosModel->asignarAUnidad($puestoId, $unidadId);
            Flash::success("Asignación de puesto de estacionamiento actualizada correctamente.");
        } catch (Exception $e) {
            error_log("[ESTACIONAMIENTO] Error asignar puesto: " . $e->getMessage());
            Flash::error('Error al eliminar el puesto de estacionamiento.');
        }

        $this->redirect('/admin/estacionamientos');
    }

    /**
     * Da de baja (Soft Delete) a un puesto de estacionamiento.
     */
    public function eliminar() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::error("ID de puesto no válido.");
            $this->redirect('/admin/estacionamientos');
        }

        $estacionamientosModel = new EstacionamientosModel();
        if ($estacionamientosModel->softDelete($id)) {
            Flash::success("El puesto de estacionamiento fue dado de baja correctamente.");
        } else {
            Flash::error("No se pudo dar de baja el puesto.");
        }

        $this->redirect('/admin/estacionamientos');
    }

    /**
     * Guarda un vehículo asociado a una unidad.
     */
    public function guardarVehiculo() {
        Auth::requireLogin();

        $vehiculosModel = new VehiculosModel();

        try {
            $unidadId = intval($_POST['unidad_id'] ?? 0);
            $personaId = Auth::id(); // O persona seleccionada por admin

            if (Auth::role() === 'admin' && !empty($_POST['persona_id'])) {
                $personaId = intval($_POST['persona_id']);
            }

            $datos = [
                'unidad_id'          => $unidadId,
                'persona_id'         => $personaId,
                'estacionamiento_id' => !empty($_POST['estacionamiento_id']) ? intval($_POST['estacionamiento_id']) : null,
                'placa'              => $_POST['placa'] ?? '',
                'marca'              => $_POST['marca'] ?? '',
                'modelo'             => $_POST['modelo'] ?? '',
                'color'              => $_POST['color'] ?? '',
                'observaciones'      => $_POST['observaciones'] ?? ''
            ];

            $vehiculosModel->crearVehiculo($datos);
            Flash::success("Vehículo registrado exitosamente.");
        } catch (Exception $e) {
            error_log("[ESTACIONAMIENTO] Error guardar vehiculo: " . $e->getMessage());
            Flash::error('Error al guardar la información del vehículo.');
        }

        $redirectUrl = (Auth::role() === 'admin') ? '/admin/estacionamientos' : '/residente/dashboard';
        $this->redirect($redirectUrl);
    }

    /**
     * Elimina un vehículo registrado.
     */
    public function eliminarVehiculo() {
        Auth::requireLogin();

        $id = intval($_POST['id'] ?? 0);
        $vehiculosModel = new VehiculosModel();

        $unidadId = (Auth::role() === 'residente') ? ($this->getAuthenticatedResidente()['unidad_id'] ?? null) : null;

        if ($vehiculosModel->eliminarVehiculo($id, $unidadId)) {
            Flash::success("El vehículo ha sido eliminado correctamente.");
        } else {
            Flash::error("No se pudo eliminar el vehículo especificado.");
        }

        $redirectUrl = (Auth::role() === 'admin') ? '/admin/estacionamientos' : '/residente/dashboard';
        $this->redirect($redirectUrl);
    }
}
