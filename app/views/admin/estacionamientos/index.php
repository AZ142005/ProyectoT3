<div class="flex flex-1 min-h-screen w-full">
    <?php $activeRoute = 'estacionamientos'; require VIEWS_PATH . '/layouts/admin_sidebar.php'; ?>

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
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-success btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoPuesto">
                    <span class="material-symbols-outlined fs-6">add_circle</span>
                    <span class="hidden sm:inline">Nuevo Puesto</span>
                </button>
                <button type="button" class="btn btn-outline-success btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoVehiculo">
                    <span class="material-symbols-outlined fs-6">directions_car</span>
                    <span class="hidden sm:inline">Registrar Vehículo</span>
                </button>
                <a href="/perfil" class="text-slate-600 hover:text-primary font-bold text-xs px-3 py-2 rounded-lg border border-slate-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">account_circle</span>
                    <span class="hidden sm:inline">Perfil</span>
                </a>
                <a href="/admin/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span class="hidden sm:inline">Salir</span>
                </a>
            </div>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash de Notificación -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>
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
            <h5 class="card-title mb-0 fw-bold text-dark">Listado de Puestos de Estacionamiento</h5>
            <span class="badge bg-secondary rounded-pill"><?= count($puestos) ?> Puestos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Nº Puesto</th>
                            <th class="py-3">Tipo</th>
                            <th class="py-3">Edificio / Torre</th>
                            <th class="py-3">Unidad Habitacional</th>
                            <th class="py-3">Vehículo Asignado</th>
                            <th class="py-3">Estado</th>
                            <th class="text-end pe-4 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($puestos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-secondary">local_parking</span>
                                    No hay puestos de estacionamiento registrados en el sistema.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($puestos as $p): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-dark">
                                        Puesto #<?= e($p['numero']) ?>
                                    </td>
                                    <td>
                                        <?php if ($p['tipo'] === 'techado'): ?>
                                            <span class="badge rounded-pill bg-primary">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">roofing</span>Techado
                                            </span>
                                        <?php elseif ($p['tipo'] === 'visitante'): ?>
                                            <span class="badge rounded-pill bg-info text-dark">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">badge</span>Visitante
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-secondary">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">directions_car</span>Descubierto
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($p['edificio_nombre'] ?: 'General / Sin Torre') ?></td>
                                    <td>
                                        <?php if (!empty($p['unidad_numero'])): ?>
                                            <span class="fw-semibold text-dark">Apto/Unidad <?= e($p['unidad_numero']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Sin Asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['vehiculo_placa'])): ?>
                                            <span class="badge bg-light text-dark border font-monospace me-1"><?= e($p['vehiculo_placa']) ?></span>
                                            <small class="text-muted"><?= e($p['vehiculo_marca']) ?> <?= e($p['vehiculo_modelo']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted small">Ninguno</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['unidad_id'])): ?>
                                            <span class="badge bg-success">Asignado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
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
                                                <div class="modal-content">
                                                    <form action="/admin/estacionamientos/asignar" method="POST">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="puesto_id" value="<?= e($p['id']) ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fw-bold">Asignar Puesto #<?= e($p['numero']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <label class="form-label fw-bold small">Seleccionar Unidad Habitacional</label>
                                                            <select name="unidad_id" class="form-select">
                                                                <option value="">-- Sin Asignar (Liberar Puesto) --</option>
                                                                <?php foreach ($unidades as $u): ?>
                                                                    <option value="<?= e($u['id']) ?>" <?= ($p['unidad_id'] == $u['id']) ? 'selected' : '' ?>>
                                                                        Unidad/Apto <?= e($u['numero']) ?> (<?= e($u['torre'] ?? 'Piso ' . $u['piso']) ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-success fw-bold">Guardar Asignación</button>
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
        </div>
    </div>
</div>

<!-- Modal Registrar Nuevo Puesto -->
<div class="modal fade" id="modalNuevoPuesto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/estacionamientos/guardar" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">Nuevo Puesto de Estacionamiento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Número / Identificador de Puesto <span class="text-danger">*</span></label>
                        <input type="text" name="numero" class="form-control" placeholder="Ej: E-101, P-05" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Tipo de Estacionamiento <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <option value="descubierto">Descubierto</option>
                            <option value="techado">Techado</option>
                            <option value="visitante">Visitante</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Edificio / Torre (Opcional)</label>
                        <select name="edificio_id" class="form-select">
                            <option value="">-- General / Sin Especificar --</option>
                            <?php foreach ($edificios as $ed): ?>
                                <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Asignar a Unidad (Opcional)</label>
                        <select name="unidad_id" class="form-select">
                            <option value="">-- Dejar Libre / Disponible --</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= e($u['id']) ?>">Unidad/Apto <?= e($u['numero']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold">Registrar Puesto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Registrar Vehículo -->
<div class="modal fade" id="modalNuevoVehiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/admin/vehiculos/guardar" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">Registrar Vehículo de Residente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Unidad Habitacional <span class="text-danger">*</span></label>
                        <select name="unidad_id" class="form-select" required>
                            <option value="">-- Seleccionar Unidad --</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= e($u['id']) ?>">Unidad/Apto <?= e($u['numero']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Placa de Vehículo <span class="text-danger">*</span></label>
                            <input type="text" name="placa" class="form-control text-uppercase" placeholder="Ej: ABC123" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Marca <span class="text-danger">*</span></label>
                            <input type="text" name="marca" class="form-control" placeholder="Ej: Toyota" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Modelo <span class="text-danger">*</span></label>
                            <input type="text" name="modelo" class="form-control" placeholder="Ej: Corolla" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Color <span class="text-danger">*</span></label>
                            <input type="text" name="color" class="form-control" placeholder="Ej: Blanco" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Asignar Puesto de Estacionamiento (Opcional)</label>
                        <select name="estacionamiento_id" class="form-select">
                            <option value="">-- Sin Asignación Directa --</option>
                            <?php foreach ($puestos as $p): ?>
                                <option value="<?= e($p['id']) ?>">Puesto #<?= e($p['numero']) ?> (<?= e($p['tipo']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold">Guardar Vehículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>
