<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
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
            'mensaje'        => $_SESSION['flash_mensaje'] ?? '',
            'error'          => $_SESSION['flash_error'] ?? '',
            'showNav'        => true,
            'title'          => 'Estructura del Conjunto - Administrador'
        ]);

        unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);
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
                $_SESSION['flash_error'] = 'El nombre del edificio no puede estar vacío.';
            } else {
                $edificiosModel = new EdificiosModel();

                if ($edificiosModel->nombreExists($nombre, $id > 0 ? $id : null)) {
                    $_SESSION['flash_error'] = 'Ya existe un edificio registrado con ese nombre.';
                } else {
                    if ($id > 0) {
                        $res = $edificiosModel->update($id, ['nombre' => $nombre, 'descripcion' => $descripcion]);
                        $_SESSION['flash_mensaje'] = $res ? 'Edificio actualizado exitosamente.' : 'Error al actualizar el edificio.';
                    } else {
                        $res = $edificiosModel->create(['nombre' => $nombre, 'descripcion' => $descripcion]);
                        $_SESSION['flash_mensaje'] = $res ? 'Edificio creado exitosamente.' : 'Error al crear el edificio.';
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
                $_SESSION['flash_error'] = 'El código/número de la unidad es obligatorio.';
            } elseif ($edificio_id <= 0) {
                $_SESSION['flash_error'] = 'Debe seleccionar un edificio para la unidad.';
            } elseif ($cuota_mensual < 0) {
                $_SESSION['flash_error'] = 'La cuota mensual debe ser un valor mayor o igual a 0.';
            } else {
                $unidadesModel = new UnidadesModel();

                if ($unidadesModel->numeroExists($numero, $id > 0 ? $id : null)) {
                    $_SESSION['flash_error'] = 'Ya existe una unidad con ese código/número registrado.';
                } else {
                    $data = [
                        'numero'        => $numero,
                        'edificio_id'   => $edificio_id,
                        'cuota_mensual' => $cuota_mensual
                    ];

                    if ($id > 0) {
                        $res = $unidadesModel->update($id, $data);
                        $_SESSION['flash_mensaje'] = $res ? 'Unidad actualizada exitosamente.' : 'Error al actualizar la unidad.';
                    } else {
                        $res = $unidadesModel->create($data);
                        $_SESSION['flash_mensaje'] = $res ? 'Unidad registrada exitosamente.' : 'Error al registrar la unidad.';
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

        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $edificiosModel = new EdificiosModel();
            $edificiosModel->toggleEstado($id);
            $_SESSION['flash_mensaje'] = 'Estado del edificio modificado.';
        }

        $this->redirect('/admin/estructura');
    }

    /**
     * Cambia el estado de una unidad (Activar / Desactivar).
     */
    public function toggleUnidad() {
        Auth::requireRole('admin');

        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $unidadesModel = new UnidadesModel();
            $unidadesModel->toggleEstado($id);
            $_SESSION['flash_mensaje'] = 'Estado de la unidad modificado.';
        }

        $this->redirect('/admin/estructura');
    }
}
