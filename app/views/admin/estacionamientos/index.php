<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'estacionamientos'; 
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
                <h1 class="text-xl font-bold text-on-surface">Estacionamientos y Vehículos</h1>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash de Notificación -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Barra de Acciones del Contenido -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-lg font-bold text-on-surface mb-1">Control de Estacionamientos</h4>
            <p class="text-xs text-on-surface-variant mb-0">Gestión de puestos asignados, visitantes y parque vehicular</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalNuevoPuesto">
                <span class="material-symbols-outlined text-[16px]">local_parking</span>
                <span>Nuevo Puesto</span>
            </button>
            <button type="button" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalNuevoVehiculo">
                <span class="material-symbols-outlined text-[16px]">directions_car</span>
                <span>Registrar Vehículo</span>
            </button>
        </div>
    </div>
    <?php if ($success = \App\Core\Flash::get('success')): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <span class="material-symbols-outlined">check_circle</span>
            <div><?= e($success) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error = \App\Core\Flash::get('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <span class="material-symbols-outlined">error</span>
            <div><?= e($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tarjetas de Resumen KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-primary">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Puestos Totales</div>
                    <div class="h3 mb-0 fw-bold text-dark"><?= e($kpis['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-success">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Asignados</div>
                    <div class="h3 mb-0 fw-bold text-success"><?= e($kpis['asignados']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-warning">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Disponibles / Libres</div>
                    <div class="h3 mb-0 fw-bold text-warning"><?= e($kpis['libres']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-info">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Techados / Visitantes</div>
                    <div class="h3 mb-0 fw-bold text-info"><?= e($kpis['techados']) ?> / <?= e($kpis['visitantes']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Principal de Puestos de Estacionamiento -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="text-lg font-bold text-on-surface mb-0">Listado de Puestos de Estacionamiento</h5>
            <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacion['total'] ?? count($puestos)) ?> Puestos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Nº Puesto</th>
                            <th class="py-3 px-4">Tipo</th>
                            <th class="py-3 px-4">Propietario / Residente</th>
                            <th class="py-3 px-4">Vehículo Registrado</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php if (empty($puestos)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">local_parking</span>
                                    No hay puestos de estacionamiento registrados en el sistema.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $unidadEdificioMap = [];
                            foreach ($unidades ?? [] as $u) {
                                $unidadEdificioMap[(int)$u['id']] = (int)($u['edificio_id'] ?? 0);
                            }
                            ?>
                            <?php foreach ($puestos as $p): ?>
                                <?php $edificioActual = !empty($p['unidad_id']) ? ($unidadEdificioMap[(int)$p['unidad_id']] ?? 0) : 0; ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-mono text-xs font-bold text-on-surface">
                                        <?= e($p['numero']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if ($p['tipo'] === 'techado'): ?>
                                            <span class="badge rounded-pill bg-primary">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">roofing</span>Techado
                                            </span>
                                        <?php elseif ($p['tipo'] === 'visitante'): ?>
                                            <span class="badge rounded-pill bg-info text-on-surface">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">badge</span>Visitante
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-secondary">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">directions_car</span>Descubierto
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (!empty($p['persona_nombre'])): ?>
                                            <div class="fw-semibold text-on-surface"><?= e($p['persona_nombre']) ?></div>
                                            <?php if (!empty($p['persona_telefono'])): ?>
                                                <small class="text-xs text-on-surface-variant d-block"><span class="material-symbols-outlined align-middle fs-6 me-0.5">call</span><?= e($p['persona_telefono']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-on-surface-variant fst-italic">Sin registrar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (!empty($p['vehiculo_id'])): ?>
                                            <div class="d-flex align-items-center gap-1 mb-1 flex-wrap">
                                                <span class="badge bg-light text-on-surface border"><?= e(etiquetaTipoVehiculo($p['vehiculo_tipo'] ?? '')) ?></span>
                                                <?php if (!empty($p['vehiculo_marca'])): ?>
                                                    <span class="text-xs text-on-surface fw-medium"><?= e($p['vehiculo_marca']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($p['vehiculo_color'])): ?>
                                                <small class="text-xs text-on-surface-variant d-block">Color: <?= e($p['vehiculo_color']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-on-surface-variant fst-italic">Sin vehículo registrado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (!empty($p['unidad_id'])): ?>
                                            <span class="badge bg-success">Asignado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-on-surface">Disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalAsignar_<?= e($p['id']) ?>" title="Asignar a Unidad">
                                                <span class="material-symbols-outlined fs-6">link</span>
                                            </button>
                                            <form action="/admin/estacionamientos/eliminar" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de dar de baja este puesto de estacionamiento?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                                <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center" title="Dar de baja">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Modal de Asignación individual -->
                                        <div class="modal fade text-start" id="modalAsignar_<?= e($p['id']) ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content rounded-3 border-0 shadow-lg">
                                                    <form action="/admin/estacionamientos/asignar" method="POST" onsubmit="return validarAsignacionEstacionamiento(this)">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="puesto_id" value="<?= e($p['id']) ?>">
                                                        <div class="modal-header bg-primary text-white py-3">
                                                            <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                                                                <span class="material-symbols-outlined">link</span>
                                                                Asignar <?= e($p['numero']) ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="row g-2">
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-bold small text-on-surface-variant">Edificio / Torre</label>
                                                                    <select class="js-edificio-asignar w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary transition-colors cursor-pointer" onchange="cargarUnidadesAsignacion(this)">
                                                                        <option value="">Seleccione un edificio...</option>
                                                                        <?php foreach ($edificios as $ed): ?>
                                                                            <option value="<?= e($ed['id']) ?>" <?= ($edificioActual === (int)$ed['id']) ? 'selected' : '' ?>><?= e($ed['nombre']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-bold small text-on-surface-variant">Unidad / Apartamento</label>
                                                                    <select name="unidad_id" required disabled data-unidad-actual="<?= e($p['unidad_id'] ?? '') ?>"
                                                                            class="js-unidad-asignar w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                                                                        <option value="" disabled selected>Seleccione un edificio primero...</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light">
                                                            <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                                                                <span class="material-symbols-outlined text-[16px]">save</span>
                                                                Guardar Asignación
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php include VIEWS_PATH . '/components/pagination.php'; ?>
        </div>
    </div>
</div>

<!-- Modal Registrar Nuevo Puesto -->
<div class="modal fade" id="modalNuevoPuesto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form action="/admin/estacionamientos/guardar" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">local_parking</span>
                        Nuevo Puesto de Estacionamiento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Número / Identificador de Puesto <span class="text-danger">*</span></label>
                        <input type="text" name="numero" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors" placeholder="Ej: E-101, P-05" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Tipo de Estacionamiento <span class="text-danger">*</span></label>
                        <select name="tipo" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer" required>
                            <option value="descubierto">Descubierto</option>
                            <option value="techado">Techado</option>
                            <option value="visitante">Visitante</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Edificio / Torre (Opcional)</label>
                        <select name="edificio_id" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                            <option value="">-- General / Sin Especificar --</option>
                            <?php foreach ($edificios as $ed): ?>
                                <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Asignar a Unidad (Opcional)</label>
                        <select name="unidad_id" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                            <option value="">-- Dejar Libre / Disponible --</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= e($u['id']) ?>">Unidad/Apto <?= e($u['numero']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        Registrar Puesto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Registrar Vehículo -->
<div class="modal fade" id="modalNuevoVehiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form action="/admin/vehiculos/guardar" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">directions_car</span>
                        Registrar Vehículo de Residente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Unidad Habitacional <span class="text-danger">*</span></label>
                        <select name="unidad_id" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer" required>
                            <option value="">-- Seleccionar Unidad --</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= e($u['id']) ?>">Unidad/Apto <?= e($u['numero']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-xs text-on-surface-variant d-block mt-1">La unidad debe tener una plaza de estacionamiento asignada.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Tipo de Vehículo <span class="text-danger">*</span></label>
                        <select name="tipo_vehiculo" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary transition-colors cursor-pointer" required>
                            <option value="">-- Seleccionar Tipo --</option>
                            <?php foreach (\App\Models\VehiculosModel::TIPOS_VEHICULO as $valorTipo => $etiquetaTipo): ?>
                                <option value="<?= e($valorTipo) ?>"><?= e($etiquetaTipo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Color <span class="text-danger">*</span></label>
                            <input type="text" name="color" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors" placeholder="Ej: Blanco" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Marca (opcional)</label>
                            <input type="text" name="marca" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors" placeholder="Ej: Toyota">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Guardar Vehículo
                    </button>
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
function validarAsignacionEstacionamiento(formulario) {
    const unidadSelect = formulario.querySelector('.js-unidad-asignar');
    if (!unidadSelect || unidadSelect.disabled || unidadSelect.value === '') {
        window.alert('Seleccione un edificio y un apartamento (o "Sin Asignar") antes de guardar.');
        return false;
    }
    return true;
}

async function cargarUnidadesAsignacion(edificioSelect) {
    const formulario = edificioSelect.closest('form');
    const unidadSelect = formulario ? formulario.querySelector('.js-unidad-asignar') : null;
    if (!unidadSelect) {
        return;
    }

    const edificioId = edificioSelect.value;
    const unidadActual = unidadSelect.dataset.unidadActual || '';

    unidadSelect.innerHTML = '';
    const marcador = document.createElement('option');
    marcador.value = '';
    marcador.disabled = true;
    marcador.selected = true;
    marcador.textContent = edificioId ? 'Seleccione un apartamento...' : 'Seleccione un edificio primero...';
    unidadSelect.appendChild(marcador);

    if (!edificioId) {
        unidadSelect.disabled = true;
        return;
    }

    try {
        const respuesta = await fetch('/admin/estacionamientos/unidades?edificio_id=' + encodeURIComponent(edificioId), {
            headers: { 'Accept': 'application/json' }
        });
        const datos = await respuesta.json();

        if (unidadActual !== '' && unidadActual !== '0') {
            const liberar = document.createElement('option');
            liberar.value = '0';
            liberar.textContent = 'Sin Asignar (Liberar Puesto)';
            unidadSelect.appendChild(liberar);
        }

        (datos.unidades || []).forEach(function (unidad) {
            const opcion = document.createElement('option');
            opcion.value = unidad.id;
            opcion.textContent = 'Apto. ' + unidad.numero;
            unidadSelect.appendChild(opcion);
        });

        unidadSelect.disabled = false;

        if (unidadActual !== '' && unidadSelect.querySelector('option[value="' + unidadActual + '"]')) {
            unidadSelect.value = unidadActual;
        }
    } catch (error) {
        unidadSelect.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            const edificioSelect = modal.querySelector('.js-edificio-asignar');
            const unidadSelect = modal.querySelector('.js-unidad-asignar');
            if (!edificioSelect || !unidadSelect) {
                return;
            }
            if (edificioSelect.value && unidadSelect.options.length <= 1) {
                cargarUnidadesAsignacion(edificioSelect);
            }
        });
    });
});
</script>
