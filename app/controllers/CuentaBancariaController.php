<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\CuentasBancariasModel;

class CuentaBancariaController extends Controller {

    /**
     * Lista todas las cuentas bancarias configuradas para el condominio.
     */
    public function index() {
        Auth::requireRole('admin');

        $model = new CuentasBancariasModel();
        $cuentas = $model->getAll();

        $this->render('admin/cuentas_bancarias/index', [
            'cuentas'     => $cuentas,
            'activeRoute' => 'cuentas_bancarias',
            'layout'      => 'admin',
            'title'       => 'Cuentas Bancarias Autorizadas'
        ]);
    }

    /**
     * Guarda (crea o actualiza) una cuenta bancaria autorizada.
     */
    public function guardar() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $tipoCuenta = trim($_POST['tipo_cuenta'] ?? 'corriente');
        $numeroCuenta = preg_replace('/[^0-9]/', '', $_POST['numero_cuenta'] ?? '');
        $titular = trim($_POST['titular'] ?? '');
        $tipoIdentificacion = trim($_POST['tipo_identificacion'] ?? 'J');
        $identificacion = trim($_POST['identificacion'] ?? '');
        $telefono = trim($_POST['telefono_pago_movil'] ?? '');
        $permiteTransferencia = !empty($_POST['permite_transferencia']) ? 1 : 0;
        $permitePagoMovil = !empty($_POST['permite_pago_movil']) ? 1 : 0;
        $activa = !empty($_POST['activa']) ? 1 : 0;

        // Validaciones
        if (empty($banco)) {
            Flash::set('danger', 'El nombre del banco es obligatorio.');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        if (strlen($numeroCuenta) !== 20) {
            Flash::set('danger', 'El número de cuenta bancaria debe contener exactamente 20 dígitos numéricos.');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        if (empty($titular)) {
            Flash::set('danger', 'El nombre del titular de la cuenta es obligatorio.');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        if (empty($identificacion)) {
            Flash::set('danger', 'El número de RIF o Cédula es obligatorio.');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        if (!$permiteTransferencia && !$permitePagoMovil) {
            Flash::set('danger', 'Debe habilitar al menos un canal de recaudación (Transferencia o Pago Móvil).');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        $datos = [
            'banco'                 => $banco,
            'tipo_cuenta'           => in_array($tipoCuenta, ['corriente', 'ahorro'], true) ? $tipoCuenta : 'corriente',
            'numero_cuenta'         => $numeroCuenta,
            'titular'               => $titular,
            'tipo_identificacion'   => in_array($tipoIdentificacion, ['V', 'J', 'E', 'G'], true) ? $tipoIdentificacion : 'J',
            'identificacion'        => $identificacion,
            'telefono_pago_movil'   => $telefono,
            'permite_transferencia' => $permiteTransferencia,
            'permite_pago_movil'    => $permitePagoMovil,
            'activa'                => $activa
        ];

        $model = new CuentasBancariasModel();

        if ($id > 0) {
            $ok = $model->actualizar($id, $datos);
            if ($ok) {
                Flash::set('success', 'Cuenta bancaria actualizada exitosamente.');
            } else {
                Flash::set('danger', 'Error al actualizar la cuenta bancaria. Verifique que el número de cuenta no esté duplicado.');
            }
        } else {
            try {
                $newId = $model->crear($datos);
                if ($newId > 0) {
                    Flash::set('success', 'Cuenta bancaria registrada exitosamente.');
                } else {
                    Flash::set('danger', 'No se pudo registrar la cuenta bancaria.');
                }
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'uk_cuenta') || str_contains($e->getMessage(), 'Duplicate entry')) {
                    Flash::set('danger', 'Ya existe una cuenta bancaria registrada con ese número de 20 dígitos.');
                } else {
                    Flash::set('danger', 'Error al guardar la cuenta bancaria.');
                }
            }
        }

        $this->redirect('/admin/cuentas-bancarias');
    }

    /**
     * Alterna el estado activo / inactivo de una cuenta.
     */
    public function toggle() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('danger', 'Identificador de cuenta inválido.');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        $model = new CuentasBancariasModel();
        $ok = $model->toggleActiva($id);

        if ($ok) {
            Flash::set('success', 'Estado de la cuenta bancaria actualizado correctamente.');
        } else {
            Flash::set('danger', 'Error al cambiar el estado de la cuenta bancaria.');
        }

        $this->redirect('/admin/cuentas-bancarias');
    }

    /**
     * Elimina o desactiva una cuenta bancaria según su historial.
     */
    public function eliminar() {
        Auth::requireRole('admin');

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('danger', 'Identificador de cuenta inválido.');
            $this->redirect('/admin/cuentas-bancarias');
            return;
        }

        $model = new CuentasBancariasModel();
        $resultado = $model->eliminarODesactivar($id);

        if ($resultado['exito']) {
            $tipo = ($resultado['accion'] === 'desactivada') ? 'warning' : 'success';
            Flash::set($tipo, $resultado['mensaje']);
        } else {
            Flash::set('danger', 'No se pudo eliminar la cuenta bancaria.');
        }

        $this->redirect('/admin/cuentas-bancarias');
    }
}
