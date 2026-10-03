<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Models\EdificiosModel;
use App\Models\UnidadesModel;
use App\Models\PersonasModel;

class EstructuraController extends Controller {

    /**
     * Invalida la caché de estructura (llamar después de cualquier mutación).
     */
    private function invalidarCacheEstructura(): void {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/estructura';
        if (is_dir($cacheDir)) {
            $cacheFiles = glob($cacheDir . '/data_*.json');
            if ($cacheFiles) {
                foreach ($cacheFiles as $f) {
                    if (file_exists($f)) { unlink($f); }
                }
            }
        }
    }

    /**
     * Muestra la vista principal de gestión de estructura (Edificios y Unidades).
     */
    public function index() {
        Auth::requireRole('admin');

        $edificiosModel = new EdificiosModel();
        $unidadesModel  = new UnidadesModel();
        $personasModel  = new PersonasModel();

        $filtroEdificio = intval($_GET['edificio_id'] ?? 0);

        // Cache structure data for 60s to avoid expensive JOINs
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/estructura';
        if (!is_dir($cacheDir)) { mkdir($cacheDir, 0755, true); }
        $cacheFile = $cacheDir . '/data_' . $filtroEdificio . '.json';
        $cacheTtl = 60;

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['timestamp']) && (time() - $cached['timestamp']) < $cacheTtl) {
                $edificios = $cached['edificios'];
                $unidades  = $cached['unidades'];
            } else {
                $edificios = $edificiosModel->getAll();
                $unidades  = $unidadesModel->getAllWithEdificio($filtroEdificio);
                foreach ($unidades as &$u) {
                    $residentes = $personasModel->getByUnidadId((int)$u['id'], true);
                    foreach ($residentes as &$r) {
                        $r['es_titular'] = ((int)($u['propietario_id'] ?? 0) === (int)$r['id']);
                    }
                    unset($r);
                    $u['residentes'] = $residentes;
                }
                unset($u);

                $unidadesPorEdificio = [];
                foreach ($unidades as $u) {
                    $edId = (int)($u['edificio_id'] ?? 0);
                    $unidadesPorEdificio[$edId][] = $u;
                }
                foreach ($edificios as &$ed) {
                    $edId = (int)$ed['id'];
                    $ed['unidades_list'] = $unidadesPorEdificio[$edId] ?? [];
                    $totalRes = 0;
                    foreach ($ed['unidades_list'] as $u) {
                        $totalRes += count($u['residentes'] ?? []);
                    }
                    $ed['total_residentes'] = $totalRes;
                }
                unset($ed);

                file_put_contents($cacheFile, json_encode(['edificios' => $edificios, 'unidades' => $unidades, 'timestamp' => time()]));
            }
        } else {
            $edificios = $edificiosModel->getAll();
            $unidades  = $unidadesModel->getAllWithEdificio($filtroEdificio);
            foreach ($unidades as &$u) {
                $residentes = $personasModel->getByUnidadId((int)$u['id'], true);
                foreach ($residentes as &$r) {
                    $r['es_titular'] = ((int)($u['propietario_id'] ?? 0) === (int)$r['id']);
                }
                unset($r);
                $u['residentes'] = $residentes;
            }
            unset($u);

            $unidadesPorEdificio = [];
            foreach ($unidades as $u) {
                $edId = (int)($u['edificio_id'] ?? 0);
                $unidadesPorEdificio[$edId][] = $u;
            }
            foreach ($edificios as &$ed) {
                $edId = (int)$ed['id'];
                $ed['unidades_list'] = $unidadesPorEdificio[$edId] ?? [];
                $totalRes = 0;
                foreach ($ed['unidades_list'] as $u) {
                    $totalRes += count($u['residentes'] ?? []);
                }
                $ed['total_residentes'] = $totalRes;
            }
            unset($ed);

            file_put_contents($cacheFile, json_encode(['edificios' => $edificios, 'unidades' => $unidades, 'timestamp' => time()]));
        }

        $tabActual = trim($_GET['tab'] ?? 'visualizacion');
        if (!in_array($tabActual, ['visualizacion', 'configuracion'], true)) {
            $tabActual = 'visualizacion';
        }

        // Paginación estandarizada a 25 registros por página para los edificios del conjunto
        $pagina = max(1, intval($_GET['page'] ?? 1));
        $porPagina = 25;
        $totalEdificios = count($edificios);
        $totalPaginas = (int) ceil($totalEdificios / $porPagina);
        $offset = ($pagina - 1) * $porPagina;
        $edificiosPaginados = array_slice($edificios, $offset, $porPagina);

        $paginacion = [
            'total'        => $totalEdificios,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'totalPaginas' => $totalPaginas,
        ];

        $filtros = [
            'tab'         => $tabActual,
            'edificio_id' => $filtroEdificio ?: '',
        ];

        $this->render('admin/estructura', [
            'edificios'       => $edificiosPaginados,
            'todosEdificios'  => $edificios,
            'unidades'        => $unidades,
            'filtroEdificio'  => $filtroEdificio,
            'tabActual'       => $tabActual,
            'paginacion'      => $paginacion,
            'filtros'         => $filtros,
            'showNav'         => true,
            'title'           => 'Estructura del Conjunto - Administrador'
        ]);
    }

    /**
     * Procesa la creación o edición de un edificio.
     */
    public function guardarEdificio() {
        Auth::requireRole('admin');

        $tab = ($_POST['tab'] ?? 'configuracion') === 'visualizacion' ? 'visualizacion' : 'configuracion';

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
                    $this->invalidarCacheEstructura();
                }
            }
        }

        $this->redirect('/admin/estructura?tab=' . $tab);
    }

    /**
     * Procesa la creación o edición de una unidad (apartamento).
     */
    public function guardarUnidad() {
        Auth::requireRole('admin');

        $tab = ($_POST['tab'] ?? 'configuracion') === 'visualizacion' ? 'visualizacion' : 'configuracion';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id                      = intval($_POST['id'] ?? 0);
            $numero                  = trim($_POST['numero'] ?? '');
            $edificio_id             = intval($_POST['edificio_id'] ?? 0);
            $asignarEstacionamiento = !empty($_POST['asignar_estacionamiento']);

            if (empty($numero)) {
                Flash::error('El código/número de la unidad es obligatorio.');
            } elseif (mb_strlen($numero) > 50) {
                Flash::error('El código/número de la unidad no puede exceder 50 caracteres.');
            } elseif ($edificio_id <= 0) {
                Flash::error('Debe seleccionar un edificio para la unidad.');
            } else {
                $edificiosModel = new EdificiosModel();
                $unidadesModel = new UnidadesModel();

                if (!$edificiosModel->getById($edificio_id)) {
                    Flash::error('El edificio seleccionado no existe.');
                } elseif ($unidadesModel->numeroExists($numero, $id > 0 ? $id : null)) {
                    Flash::error('Ya existe una unidad con ese código/número registrado.');
                } else {
                    $data = [
                        'numero'      => $numero,
                        'edificio_id' => $edificio_id,
                    ];

                    if ($id > 0) {
                        $res = $unidadesModel->update($id, $data);
                        Flash::success($res ? 'Unidad actualizada exitosamente.' : 'Error al actualizar la unidad.');
                    } else {
                        $res = $unidadesModel->createWithEstacionamiento($data, $asignarEstacionamiento);
                        if ($res) {
                            $msg = $asignarEstacionamiento 
                                ? 'Unidad registrada y puesto de estacionamiento asignado exitosamente.' 
                                : 'Unidad registrada exitosamente.';
                            Flash::success($msg);
                        } else {
                            Flash::error('Error al registrar la unidad o asignar el puesto de estacionamiento.');
                        }
                    }
                    $this->invalidarCacheEstructura();
                }
            }
        }

        $this->redirect('/admin/estructura?tab=' . $tab);
    }

    /**
     * Cambia el estado de un edificio (Activar / Desactivar).
     */
    public function toggleEdificio() {
        Auth::requireRole('admin');

        $tab = ($_POST['tab'] ?? 'configuracion') === 'visualizacion' ? 'visualizacion' : 'configuracion';
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $edificiosModel = new EdificiosModel();
            if ($edificiosModel->toggleEstado($id)) {
                Flash::success('Estado del edificio modificado.');
                $this->invalidarCacheEstructura();
            } else {
                Flash::error('Edificio no encontrado.');
            }
        }

        $this->redirect('/admin/estructura?tab=' . $tab);
    }

    /**
     * Cambia el estado de una unidad (Activar / Desactivar).
     */
    public function toggleUnidad() {
        Auth::requireRole('admin');

        $tab = ($_POST['tab'] ?? 'visualizacion') === 'configuracion' ? 'configuracion' : 'visualizacion';
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $unidadesModel = new UnidadesModel();
            if ($unidadesModel->toggleEstado($id)) {
                Flash::success('Estado de la unidad modificado.');
                $this->invalidarCacheEstructura();
            } else {
                Flash::error('Unidad no encontrada.');
            }
        }

        $this->redirect('/admin/estructura?tab=' . $tab);
    }
}
