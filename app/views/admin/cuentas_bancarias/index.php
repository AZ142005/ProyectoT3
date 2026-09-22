<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'cuentas_bancarias'; 
    if (\App\Core\Auth::role() === 'auditor') {
        require VIEWS_PATH . '/layouts/auditor_sidebar.php';
    } else {
        require VIEWS_PATH . '/layouts/admin_sidebar.php';
    }
    ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Cuentas Bancarias Autorizadas</h1>
            </div>
            <a href="<?= \App\Core\Auth::role() === 'auditor' ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
    <!-- Encabezado de la Sección -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <span class="material-symbols-outlined text-primary fs-2">account_balance</span>
                Cuentas Bancarias Autorizadas
            </h2>
            <p class="text-muted small mb-0">
                Gestione las cuentas oficiales del condominio para recibir pagos por transferencia o pago móvil. Solo las cuentas activas estarán disponibles para los residentes.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCuenta" onclick="abrirModalCrear()">
                <span class="material-symbols-outlined">add_circle</span>
                <span>Nueva Cuenta Bancaria</span>
            </button>
        </div>
    </div>

    <!-- Alertas Flash -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Tarjetas de Resumen Rápido -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Total Cuentas</span>
                        <span class="h3 fw-bold text-dark mb-0"><?= count($cuentas) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-primary opacity-50">account_balance_wallet</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Cuentas Activas</span>
                        <span class="h3 fw-bold text-success mb-0">
                            <?= count(array_filter($cuentas, fn($c) => !empty($c['activa']))) ?>
                        </span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-success opacity-50">check_circle</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Canales Habilitados</span>
                        <div class="d-flex gap-2 mt-1">
                            <span class="badge bg-primary-subtle text-primary">Transferencia</span>
                            <span class="badge bg-success-subtle text-success">Pago Móvil</span>
                        </div>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-info opacity-50">point_of_sale</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Principal de Cuentas -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <span class="material-symbols-outlined text-primary">view_list</span>
                Listado de Cuentas Autorizadas
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Banco e Información</th>
                        <th class="py-3">Número de Cuenta</th>
                        <th class="py-3">Titular y RIF</th>
                        <th class="py-3 text-center">Canales</th>
                        <th class="py-3 text-center">Estado</th>
                        <th class="py-3 text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cuentas)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">account_balance</span>
                                No hay cuentas bancarias registradas aún. Haga clic en <strong>Nueva Cuenta Bancaria</strong> para agregar la primera.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuentas as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                        <span class="material-symbols-outlined text-primary fs-5">account_balance</span>
                                        <?= e($c['banco']) ?>
                                    </div>
                                    <small class="text-muted text-capitalize"><?= e($c['tipo_cuenta']) ?></small>
                                </td>
                                <td>
                                    <span class="font-monospace fw-semibold text-dark bg-light px-2 py-1 rounded border">
                                        <?= e(chunk_split($c['numero_cuenta'], 4, ' ')) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($c['titular']) ?></div>
                                    <small class="text-muted"><?= e($c['tipo_identificacion']) ?>-<?= e($c['identificacion']) ?></small>
                                    <?php if (!empty($c['telefono_pago_movil'])): ?>
                                        <div class="small text-success d-flex align-items-center gap-1 mt-0.5">
                                            <span class="material-symbols-outlined fs-6">phone_iphone</span>
                                            <?= e($c['telefono_pago_movil']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                                        <?php if (!empty($c['permite_transferencia'])): ?>
                                            <span class="badge bg-primary text-white" title="Permite Transferencias">Transf.</span>
                                        <?php endif; ?>
                                        <?php if (!empty($c['permite_pago_movil'])): ?>
                                            <span class="badge bg-success text-white" title="Permite Pago Móvil">Pago Móvil</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($c['activa'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill fw-semibold">
                                            Activa (Visible)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 rounded-pill fw-semibold">
                                            Inactiva (Oculta)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <!-- Botón Editar -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                                onclick='abrirModalEditar(<?= json_encode($c) ?>)'
                                                title="Editar cuenta">
                                            <span class="material-symbols-outlined fs-6">edit</span>
                                            <span>Editar</span>
                                        </button>

                                        <!-- Alternar Estado -->
                                        <form method="POST" action="/admin/cuentas-bancarias/toggle" class="d-inline" onsubmit="return confirm('¿Está seguro de cambiar la visibilidad de esta cuenta para los residentes?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                            <?php if (!empty($c['activa'])): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1" title="Desactivar para residentes">
                                                    <span class="material-symbols-outlined fs-6">visibility_off</span>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" title="Activar para residentes">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Eliminar / Desactivar -->
                                        <form method="POST" action="/admin/cuentas-bancarias/eliminar" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta cuenta bancaria?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center" title="Eliminar cuenta">
                                                <span class="material-symbols-outlined fs-6">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Crear / Editar Cuenta Bancaria -->
<div class="modal fade" id="modalCuenta" tabindex="-1" aria-labelledby="modalCuentaTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="/admin/cuentas-bancarias/guardar">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="cuentaId" value="0">

                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2" id="modalCuentaTitle">
                        <span class="material-symbols-outlined">account_balance</span>
                        <span id="modalHeaderTexto">Registrar Cuenta Bancaria Autorizada</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">Nombre del Banco <span class="text-danger">*</span></label>
                            <input type="text" name="banco" id="campoBanco" class="form-control" required placeholder="Ej. Banco de Venezuela, Banesco, Mercantil">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Tipo de Cuenta <span class="text-danger">*</span></label>
                            <select name="tipo_cuenta" id="campoTipoCuenta" class="form-select" required>
                                <option value="corriente">Corriente</option>
                                <option value="ahorro">Ahorro</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">Número de Cuenta (20 Dígitos) <span class="text-danger">*</span></label>
                            <input type="text" name="numero_cuenta" id="campoNumeroCuenta" maxlength="20" minlength="20" class="form-control font-monospace" required placeholder="01020000000000000000" pattern="[0-9]{20}">
                            <div class="form-text small text-muted">Ingrese exactamente los 20 dígitos numéricos continuos de la cuenta bancaria.</div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label fw-bold small text-muted">Titular de la Cuenta <span class="text-danger">*</span></label>
                            <input type="text" name="titular" id="campoTitular" class="form-control" required placeholder="Ej. Condominio Residencias El Ávila">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted">Tipo Doc. <span class="text-danger">*</span></label>
                            <select name="tipo_identificacion" id="campoTipoIdentificacion" class="form-select" required>
                                <option value="J">J (RIF Jurídico)</option>
                                <option value="G">G (Gubernamental)</option>
                                <option value="V">V (Cédula Venezolana)</option>
                                <option value="E">E (Extranjero)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">RIF / Cédula <span class="text-danger">*</span></label>
                            <input type="text" name="identificacion" id="campoIdentificacion" class="form-control" required placeholder="Ej. 12345678-0 o 12345678">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Teléfono Pago Móvil</label>
                            <input type="text" name="telefono_pago_movil" id="campoTelefono" class="form-control" placeholder="Ej. 04121234567">
                            <div class="form-text small text-muted">Requerido si habilita Pago Móvil en esta cuenta.</div>
                        </div>

                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <span class="form-label fw-bold small text-muted mb-2">Canales de Pago Habilitados</span>
                            <div class="d-flex gap-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="permite_transferencia" value="1" id="checkTransf" checked>
                                    <label class="form-check-label fw-semibold small" for="checkTransf">Transferencia</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="permite_pago_movil" value="1" id="checkPagoMovil" checked>
                                    <label class="form-check-label fw-semibold small" for="checkPagoMovil">Pago Móvil</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="activa" value="1" id="checkActiva" checked>
                                <label class="form-check-label fw-bold text-dark" for="checkActiva">
                                    Cuenta Activa y Disponible para Residentes
                                </label>
                                <div class="small text-muted">Al desmarcar esta opción, los residentes no podrán ver ni seleccionar esta cuenta al reportar pagos.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btnGuardarCuenta">Guardar Cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>

<script>
function abrirModalCrear() {
    document.getElementById('cuentaId').value = '0';
    document.getElementById('modalHeaderTexto').textContent = 'Registrar Cuenta Bancaria Autorizada';
    document.getElementById('btnGuardarCuenta').textContent = 'Guardar Cuenta';
    document.getElementById('campoBanco').value = '';
    document.getElementById('campoTipoCuenta').value = 'corriente';
    document.getElementById('campoNumeroCuenta').value = '';
    document.getElementById('campoTitular').value = '';
    document.getElementById('campoTipoIdentificacion').value = 'J';
    document.getElementById('campoIdentificacion').value = '';
    document.getElementById('campoTelefono').value = '';
    document.getElementById('checkTransf').checked = true;
    document.getElementById('checkPagoMovil').checked = true;
    document.getElementById('checkActiva').checked = true;
}

function abrirModalEditar(cuenta) {
    document.getElementById('cuentaId').value = cuenta.id;
    document.getElementById('modalHeaderTexto').textContent = 'Editar Cuenta Bancaria';
    document.getElementById('btnGuardarCuenta').textContent = 'Actualizar Cuenta';
    document.getElementById('campoBanco').value = cuenta.banco || '';
    document.getElementById('campoTipoCuenta').value = cuenta.tipo_cuenta || 'corriente';
    document.getElementById('campoNumeroCuenta').value = cuenta.numero_cuenta || '';
    document.getElementById('campoTitular').value = cuenta.titular || '';
    document.getElementById('campoTipoIdentificacion').value = cuenta.tipo_identificacion || 'J';
    document.getElementById('campoIdentificacion').value = cuenta.identificacion || '';
    document.getElementById('campoTelefono').value = cuenta.telefono_pago_movil || '';
    document.getElementById('checkTransf').checked = parseInt(cuenta.permite_transferencia) === 1;
    document.getElementById('checkPagoMovil').checked = parseInt(cuenta.permite_pago_movil) === 1;
    document.getElementById('checkActiva').checked = parseInt(cuenta.activa) === 1;

    const modal = new bootstrap.Modal(document.getElementById('modalCuenta'));
    modal.show();
}
</script>
