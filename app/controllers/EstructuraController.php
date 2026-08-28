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
                $unidades = $cached['unidades'];
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
            file_put_contents($cacheFile, json_encode(['edificios' => $edificios, 'unidades' => $unidades, 'timestamp' => time()]));
        }

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
                    $this->invalidarCacheEstructura();
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
                    $this->invalidarCacheEstructura();
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
                $this->invalidarCacheEstructura();
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
                $this->invalidarCacheEstructura();
            } else {
                Flash::error('Unidad no encontrada.');
            }
        }

        $this->redirect('/admin/estructura');
    }

    /**
     * Registra, actualiza o reactiva un residente (propietario/inquilino) asociado a una unidad.
     */
    public function guardarResidente() {
        Auth::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id           = intval($_POST['id'] ?? 0);
            $unidadId     = intval($_POST['unidad_id'] ?? 0);
            $cedulaTipo   = strtoupper(trim($_POST['cedula_tipo'] ?? 'V'));
            $cedulaNumero = preg_replace('/[^0-9]/', '', trim($_POST['cedula_numero'] ?? ''));

            // Fallback si se envía el campo 'cedula' directo
            if (empty($cedulaNumero) && !empty($_POST['cedula'])) {
                $raw = normalizarCedula($_POST['cedula']);
                if (in_array(substr($raw, 0, 1), ['V', 'E'], true)) {
                    $cedulaTipo = substr($raw, 0, 1);
                    $cedulaNumero = substr($raw, 1);
                } else {
                    $cedulaNumero = $raw;
                }
            }

            $nombre    = trim($_POST['nombre'] ?? '');
            $apellido  = trim($_POST['apellido'] ?? '');
            $tipo      = trim($_POST['tipo'] ?? 'propietario');
            $telCodigo = trim($_POST['telefono_codigo'] ?? '');
            $telNumero = trim($_POST['telefono_numero'] ?? '');
            $telefono  = !empty($telNumero) ? ($telCodigo . $telNumero) : trim($_POST['telefono'] ?? '');
            $email     = trim($_POST['email'] ?? '');

            // 1. Validaciones previas de formato y obligatoriedad
            if (empty($cedulaNumero) || empty($nombre) || empty($apellido) || $unidadId <= 0) {
                Flash::error('La cédula, el nombre, el apellido y la unidad son obligatorios.');
                $this->redirect('/admin/estructura');
                return;
            }

            if (!in_array($cedulaTipo, ['V', 'E'], true)) {
                Flash::error('Tipo de documento no válido (debe seleccionar V o E).');
                $this->redirect('/admin/estructura');
                return;
            }

            if (strlen($cedulaNumero) < 5 || strlen($cedulaNumero) > 8 || !ctype_digit($cedulaNumero)) {
                Flash::error('El número de cédula debe contener entre 5 y 8 dígitos numéricos.');
                $this->redirect('/admin/estructura');
                return;
            }

            $cedula = $cedulaTipo . $cedulaNumero;

            if (!validarCedula($cedula)) {
                Flash::error('El formato de la cédula no es válido (use inicial V o E seguida de 5 a 8 dígitos).');
                $this->redirect('/admin/estructura');
                return;
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Flash::error('El formato del correo electrónico no es válido.');
                $this->redirect('/admin/estructura');
                return;
            }

            if (!empty($telNumero) && (strlen($telNumero) !== 7 || !ctype_digit($telNumero))) {
                Flash::error('El número de teléfono debe contener exactamente 7 dígitos numéricos tras el código de operadora.');
                $this->redirect('/admin/estructura');
                return;
            }

            if (!empty($telefono) && !validarTelefono($telefono)) {
                Flash::error('El formato del teléfono no es válido (use una operadora válida como 0412, 0422, 0414, 0424, 0416 o 0426).');
                $this->redirect('/admin/estructura');
                return;
            }

            if (!in_array($tipo, ['propietario', 'inquilino', 'ambos'], true)) {
                Flash::error('El tipo de residente seleccionado no es válido.');
                $this->redirect('/admin/estructura');
                return;
            }

            $personasModel = new PersonasModel();
            $unidadesModel = new UnidadesModel();

            // 2. Validar Unidad Activa
            $unidad = $unidadesModel->getById($unidadId);
            if (!$unidad || $unidad['estado'] != 1) {
                Flash::error('La unidad seleccionada no existe o se encuentra inactiva.');
                $this->redirect('/admin/estructura');
                return;
            }

            // 3. Validar Unicidad de Email en activos
            if (!empty($email) && $personasModel->emailExistsActive($email, $id > 0 ? $id : null)) {
                Flash::error('El correo electrónico ya se encuentra registrado por otro residente activo.');
                $this->redirect('/admin/estructura');
                return;
            }

            $personaExistente = $personasModel->getByCedula($cedula);
            $datosPersona = [
                'cedula'    => $cedula,
                'nombre'    => $nombre,
                'apellido'  => $apellido,
                'tipo'      => $tipo,
                'telefono'  => $telefono,
                'email'     => $email,
                'unidad_id' => $unidadId
            ];

            // 4. Ejecución Transaccional Atómica
            $db = Database::getConnection();
            $db->beginTransaction();

            try {
                $targetPersonaId = 0;
                $msgExito = '';

                if ($id > 0) {
                    // Edición explícita
                    if ($personaExistente && $personaExistente['id'] != $id && $personaExistente['estado'] == 1) {
                        $db->rollBack();
                        Flash::error('La cédula indicada ya pertenece a otro residente registrado.');
                        $this->redirect('/admin/estructura');
                        return;
                    }
                    $personasModel->updateResidente($id, $datosPersona);
                    $targetPersonaId = $id;
                    $msgExito = 'Datos del residente actualizados exitosamente.';
                } else {
                    // Creación o Reactivación
                    if ($personaExistente) {
                        if ($personaExistente['estado'] == 1) {
                            $db->rollBack();
                            Flash::error('Esta cédula ya se encuentra activa en el apartamento ' . ($personaExistente['unidad_id'] ?? 'N/A') . '.');
                            $this->redirect('/admin/estructura');
                            return;
                        }
                        // Reactivación de cédula inactiva
                        $personasModel->updateResidente((int)$personaExistente['id'], $datosPersona);
                        $targetPersonaId = (int)$personaExistente['id'];
                        $msgExito = 'Residente reactivado y asignado a la unidad exitosamente.';
                    } else {
                        // Inserción limpia
                        $targetPersonaId = $personasModel->createResidente($datosPersona);
                        $msgExito = 'Residente registrado y asignado exitosamente.';
                    }
                }

                // 5. Asignación de titular si corresponde
                if ($targetPersonaId > 0 && in_array($tipo, ['propietario', 'ambos'])) {
                    if (empty($unidad['propietario_id']) || $unidad['propietario_id'] == $targetPersonaId) {
                        $unidadesModel->setPropietario($unidadId, $targetPersonaId);
                    }
                }

                $db->commit();
                Flash::success($msgExito);

                // 6. Invalidación de Caché Resiliente
                try {
                    $this->invalidarCacheEstructura();
                } catch (\Throwable $eCache) {
                    error_log("[ESTRUCTURA] Advertencia al invalidar caché: " . $eCache->getMessage());
                }

            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("[ESTRUCTURA] Error al guardar residente: " . $e->getMessage());
                Flash::error('Error interno al guardar los datos del residente.');
            }
        }

        $this->redirect('/admin/estructura');
    }

    /**
     * Desvincula lógicamente a un residente de una unidad y promueve/limpia propietario.
     */
    public function desvincularResidente() {
        Auth::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $personaId = intval($_POST['persona_id'] ?? 0);
            $unidadId  = intval($_POST['unidad_id'] ?? 0);

            if ($personaId > 0 && $unidadId > 0) {
                $db = Database::getConnection();
                $db->beginTransaction();

                try {
                    $personasModel = new PersonasModel();
                    $unidadesModel = new UnidadesModel();

                    $personasModel->desvincularResidente($personaId);
                    $unidadesModel->gestionarBajaPropietario($unidadId, $personaId);

                    $db->commit();
                    Flash::success('Residente desvinculado de la unidad exitosamente.');

                    // Invalidación de Caché Resiliente
                    try {
                        $this->invalidarCacheEstructura();
                    } catch (\Throwable $eCache) {
                        error_log("[ESTRUCTURA] Advertencia al invalidar caché: " . $eCache->getMessage());
                    }

                } catch (\Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log("[ESTRUCTURA] Error al desvincular residente: " . $e->getMessage());
                    Flash::error('Error interno al desvincular al residente.');
                }
            } else {
                Flash::error('Datos inválidos para la desvinculación.');
            }
        }

        $this->redirect('/admin/estructura');
    }
}
