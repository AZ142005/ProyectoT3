<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'gastos'; 
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
                <h1 class="text-xl font-bold text-on-surface">Gastos Comunes</h1>
            </div>
            <div class="flex items-center gap-3">
                <?php if (\App\Core\Auth::role() !== 'auditor'): ?>
                    <button type="button" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
                        <span class="material-symbols-outlined fs-6">add</span>
                        <span class="hidden sm:inline">Nuevo Gasto</span>
                    </button>
                <?php endif; ?>
                <a href="/perfil" class="text-slate-600 hover:text-primary font-bold text-xs px-3 py-2 rounded-lg border border-slate-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">account_circle</span>
                    <span class="hidden sm:inline">Perfil</span>
                </a>
                <a href="/admin/logout" onclick="return confirm('¿Está seguro de que desea cerrar sesión?');" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                </a>
            </div>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Resumen de Totales por Categoría -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Total Gastos del Mes</span>
                        <span class="h3 fw-bold text-dark mb-0">Bs. <?= number_format($totalMes, 2) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-primary opacity-50">payments</span>
                </div>
                <small class="text-muted mt-2 d-block">Período <?= e($filtros['mes']) ?>/<?= e($filtros['anio']) ?></small>
            </div>
        </div>

        <?php foreach (array_slice($totalesPorCategoria, 0, 3) as $tc): ?>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="border-left: 4px solid <?= e($tc['color']) ?> !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="max-width: 170px;">
                                <?= e($tc['categoria_nombre']) ?>
                            </span>
                            <span class="h4 fw-bold text-dark mb-0">Bs. <?= number_format($tc['total_monto'], 2) ?></span>
                        </div>
                        <span class="material-symbols-outlined fs-1 opacity-50" style="color: <?= e($tc['color']) ?>;">
                            <?= e($tc['icono']) ?>
                        </span>
                    </div>
                    <small class="text-muted mt-2 d-block"><?= e($tc['cantidad_gastos']) ?> facturas registradas</small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabla de Gastos Comunes -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Historial de Gastos del Período</h5>
            <span class="badge bg-primary rounded-pill"><?= e($paginacion['total']) ?> Gastos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Categoría</th>
                            <th class="py-3">Proveedor / Nro. Factura</th>
                            <th class="py-3">Descripción</th>
                            <th class="py-3 text-center">Fecha Gasto</th>
                            <th class="py-3 text-end">Monto Total</th>
                            <th class="py-3 text-center">Soporte Digital</th>
                            <th class="py-3 text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gastos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">receipt_long</span>
                                    No hay gastos comunes registrados para este período.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gastos as $g): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="badge rounded-pill px-3 py-1 text-white" style="background-color: <?= e($g['categoria_color']) ?>;">
                                            <span class="material-symbols-outlined align-middle fs-6 me-1"><?= e($g['categoria_icono']) ?></span>
                                            <?= e($g['categoria_nombre']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($g['proveedor']) ?></div>
                                        <small class="text-muted">Fac: <?= e($g['nro_factura_proveedor'] ?: 'S/N') ?></small>
                                    </td>
                                    <td class="small text-muted" style="max-width: 250px;">
                                        <?= e($g['descripcion']) ?>
                                    </td>
                                    <td class="text-center small"><?= e(date('d/m/Y', strtotime($g['fecha_gasto']))) ?></td>
                                    <td class="text-end font-monospace fw-bold text-dark fs-6">
                                        Bs. <?= number_format($g['monto_total'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($g['soporte_digital'])): ?>
                                            <a href="/uploads/soportes/<?= e($g['soporte_digital']) ?>" target="_blank" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6">visibility</span> Ver Doc
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white">Sin Soporte</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form method="POST" action="/admin/gastos/eliminar" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este gasto y su archivo físico?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e($g['id']) ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm border-0" title="Eliminar Gasto">
                                                <span class="material-symbols-outlined fs-6">delete</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="p-3 border-top bg-light">
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Registrar Nuevo Gasto -->
<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form method="POST" action="/admin/gastos/guardar" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">receipt</span>
                        Registrar Gasto Común del Condominio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Categoría de Gasto <span class="text-danger">*</span></label>
                            <select name="categoria_id" required class="form-select">
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= e($cat['id']) ?>"><?= e($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Mes <span class="text-danger">*</span></label>
                            <select name="mes" required class="form-select">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= e($m) ?>" <?= $m === intval(date('n')) ? 'selected' : '' ?>><?= e($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Año <span class="text-danger">*</span></label>
                            <input type="number" name="anio" value="<?= date('Y') ?>" required class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Proveedor / Empresa <span class="text-danger">*</span></label>
                            <input type="text" name="proveedor" required placeholder="Ej: Hidroeléctrica / Bombas del Centro C.A." class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Nro. Factura Fiscal</label>
                            <input type="text" name="nro_factura_proveedor" placeholder="Ej: FAC-009842" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Monto Total (Bs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="monto_total" required placeholder="0.00" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Fecha del Gasto <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_gasto" value="<?= date('Y-m-d') ?>" required class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Descripción del Gasto <span class="text-danger">*</span></label>
                        <textarea name="descripcion" rows="3" required placeholder="Describa el trabajo realizado o concepto..." class="form-control"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Soporte Digital Adjunto (PDF, JPG, PNG)</label>
                        <input type="file" name="soporte_digital" accept=".pdf,.jpg,.jpeg,.png" class="form-control">
                        <div class="form-text small text-muted">Factura o recibo digital que podrán consultar los residentes como justificación.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Guardar Gasto Común</button>
                </div>
            </form>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>
