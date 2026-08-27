<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\EdificiosModel;
use App\Models\UnidadesModel;

class EstructuraController extends Controller {
    /**
     * Muestra la vista principal de gestión de estructura (Edificios y Unidades).
     */
    public function index() {
        Auth::requireRole('admin');

        $edificiosModel = new EdificiosModel();
        $unidadesModel  = new UnidadesModel();

        $filtroEdificio = intval($_GET['edificio_id'] ?? 0);

        $edificios = $edificiosModel->getAll();
        $unidades  = $unidadesModel->getAllWithEdificio($filtroEdificio);

        $this->render('admin/estructura', [
            'edificios'      => $edificios,
            'unidades'       => $unidades,
            'filtroEdificio' => $filtroEdificio,
            'showNav'        => true,
            'title'          => 'Estructura del Conjunto - Administrador'
        ]);
    }

    /**
     * Procesa la creación o edición de un edificio.
     */
    public function guardarEdificio() {
        Auth::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id          = intval($_POST['id'] ?? 0);
            $nombre      = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre)) {
                Flash::error('El nombre del edificio no puede estar vacío.');
            } elseif (mb_strlen($nombre) > 255) {
                Flash::error('El nombre del edificio no puede exceder 255 caracteres.');
            } elseif (mb_strlen($descripcion) > 1000) {
                Flash::error('La descripción no puede exceder 1000 caracteres.');
            } else {
                $edificiosModel = new EdificiosModel();

                if ($edificiosModel->nombreExists($nombre, $id > 0 ? $id : null)) {
                    Flash::error('Ya existe un edificio registrado con ese nombre.');
                } else {
                    if ($id > 0) {
                        $res = $edificiosModel->update($id, ['nombre' => $nombre, 'descripcion' => $descripcion]);
                        Flash::success($res ? 'Edificio actualizado exitosamente.' : 'Error al actualizar el edificio.');
                    } else {
                        $res = $edificiosModel->create(['nombre' => $nombre, 'descripcion' => $descripcion]);
                        Flash::success($res ? 'Edificio creado exitosamente.' : 'Error al crear el edificio.');
                    }
                }
            }
        }

        $this->redirect('/admin/estructura');
    }

    /**
     * Procesa la creación o edición de una unidad (apartamento).
     */
    public function guardarUnidad() {
        Auth::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id            = intval($_POST['id'] ?? 0);
            $numero        = trim($_POST['numero'] ?? '');
            $edificio_id   = intval($_POST['edificio_id'] ?? 0);
            $cuota_mensual = floatval($_POST['cuota_mensual'] ?? 0);

            if (empty($numero)) {
                Flash::error('El código/número de la unidad es obligatorio.');
            } elseif (mb_strlen($numero) > 50) {
                Flash::error('El código/número de la unidad no puede exceder 50 caracteres.');
            } elseif ($edificio_id <= 0) {
                Flash::error('Debe seleccionar un edificio para la unidad.');
            } elseif ($cuota_mensual < 0) {
                Flash::error('La cuota mensual debe ser un valor mayor o igual a 0.');
            } else {
                $edificiosModel = new EdificiosModel();
                $unidadesModel = new UnidadesModel();

                // FK validation: edificio debe existir
                if (!$edificiosModel->getById($edificio_id)) {
                    Flash::error('El edificio seleccionado no existe.');
                } elseif ($unidadesModel->numeroExists($numero, $id > 0 ? $id : null)) {
                    Flash::error('Ya existe una unidad con ese código/número registrado.');
                } else {
                    $data = [
                        'numero'        => $numero,
                        'edificio_id'   => $edificio_id,
                        'cuota_mensual' => $cuota_mensual
                    ];

                    if ($id > 0) {
                        $res = $unidadesModel->update($id, $data);
                        Flash::success($res ? 'Unidad actualizada exitosamente.' : 'Error al actualizar la unidad.');
                    } else {
                        $res = $unidadesModel->create($data);
                        Flash::success($res ? 'Unidad registrada exitosamente.' : 'Error al registrar la unidad.');
                    }
                }
            }
        }

        $this->redirect('/admin/estructura');
    }

    /**
     * Cambia el estado de un edificio (Activar / Desactivar).
     */
    public function toggleEdificio() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $edificiosModel = new EdificiosModel();
            if ($edificiosModel->toggleEstado($id)) {
                Flash::success('Estado del edificio modificado.');
            } else {
                Flash::error('Edificio no encontrado.');
            }
        }

        $this->redirect('/admin/estructura');
    }

    /**
     * Cambia el estado de una unidad (Activar / Desactivar).
     */
    public function toggleUnidad() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $unidadesModel = new UnidadesModel();
            if ($unidadesModel->toggleEstado($id)) {
                Flash::success('Estado de la unidad modificado.');
            } else {
                Flash::error('Unidad no encontrada.');
            }
        }

        $this->redirect('/admin/estructura');
    }
}
