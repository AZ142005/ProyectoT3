<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'morosidad'; 
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
                <h1 class="text-xl font-bold text-on-surface">Reporte de Morosidad</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="/admin/reportes/morosidad/exportar-csv?<?= http_build_query($filtros) ?>" class="btn btn-outline-success btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined fs-6">csv</span>
                    <span class="hidden sm:inline">Exportar CSV</span>
                </a>
                <a href="/admin/reportes/morosidad/imprimir?<?= http_build_query($filtros) ?>" target="_blank" class="btn btn-danger btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined fs-6">print</span>
                    <span class="hidden sm:inline">Imprimir PDF</span>
                </a>
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
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-danger">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Deuda Vencida Total</div>
                    <div class="h3 mb-0 fw-bold text-danger"><?= e(formatearMoneda($kpis['total_deuda'])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-warning">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Unidades Morosas</div>
                    <div class="h3 mb-0 fw-bold text-dark"><?= e($kpis['unidades_morosas']) ?> <small class="fs-6 text-muted">/ <?= e($kpis['total_unidades']) ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-info">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Tasa de Morosidad</div>
                    <div class="h3 mb-0 fw-bold text-info"><?= e($kpis['tasa_morosidad']) ?>%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-success">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Estado Cartera</div>
                    <div class="h3 mb-0 fw-bold text-success">Actualizado</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="/admin/reportes/morosidad" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Filtrar por Edificio / Torre</label>
                    <select name="edificio_id" class="form-select">
                        <option value="">-- Todos los Edificios --</option>
                        <?php foreach ($edificios as $ed): ?>
                            <option value="<?= e($ed['id']) ?>" <?= ($filtros['edificio_id'] == $ed['id']) ? 'selected' : '' ?>>
                                <?= e($ed['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Antigüedad de Deuda</label>
                    <select name="dias_mora" class="form-select">
                        <option value="">-- Todos los Rangos --</option>
                        <option value="30" <?= ($filtros['dias_mora'] == '30') ? 'selected' : '' ?>>Mayor a 30 Días</option>
                        <option value="60" <?= ($filtros['dias_mora'] == '60') ? 'selected' : '' ?>>Mayor a 60 Días</option>
                        <option value="90" <?= ($filtros['dias_mora'] == '90') ? 'selected' : '' ?>>Crítico (Mayor a 90 Días)</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold flex-fill d-inline-flex align-items-center justify-content-center gap-1">
                        <span class="material-symbols-outlined">filter_list</span> Filtrar
                    </button>
                    <a href="/admin/reportes/morosidad" class="btn btn-outline-secondary font-weight-bold" title="Limpiar Filtros">
                        <span class="material-symbols-outlined align-middle">restart_alt</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla del Reporte -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Detalle de Unidades Morosas</h5>
            <span class="badge bg-danger rounded-pill"><?= e($paginacion['total']) ?> Registros Encontrados</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Unidad / Apto</th>
                            <th class="py-3">Edificio / Torre</th>
                            <th class="py-3">Propietario / Contacto</th>
                            <th class="py-3 text-center">Facturas Vencidas</th>
                            <th class="py-3 text-center">Días de Mora</th>
                            <th class="py-3 text-end pe-4">Monto Total ($)</th>
                            <th class="py-3 text-center pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($morosos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-success">verified</span>
                                    ¡Excelente! No existen unidades habitacionales morosas para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($morosos as $m): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-dark">
                                        Apto/Unidad <?= e($m['unidad_numero']) ?>
                                    </td>
                                    <td><?= e($m['edificio_nombre'] ?: 'Sin Torre') ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($m['propietario_nombre'] ?: 'Sin Propietario') ?></div>
                                        <small class="text-muted d-block">
                                            C.I: <?= e($m['propietario_cedula'] ?: 'N/A') ?> 
                                            <?= $m['propietario_telefono'] ? ' | Tel: ' . e($m['propietario_telefono']) : '' ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill fs-6"><?= e($m['facturas_vencidas']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($m['dias_mora_max'] >= 90): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">warning</span>
                                                <?= e($m['dias_mora_max']) ?> días (Crítico)
                                            </span>
                                        <?php elseif ($m['dias_mora_max'] >= 60): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">
                                                <?= e($m['dias_mora_max']) ?> días
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark rounded-pill px-3 py-1">
                                                <?= e($m['dias_mora_max']) ?> días
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 font-monospace fw-bold fs-6 text-danger">
                                        <?= e(formatearMoneda($m['total_deuda'])) ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="/admin/reportes/carta-deuda/<?= e($m['unidad_id']) ?>" class="btn btn-outline-warning btn-sm font-weight-bold d-inline-flex align-items-center gap-1" title="Ver Carta Oficial de Deuda">
                                            <span class="material-symbols-outlined fs-6">description</span> Carta Deuda
                                        </a>
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

            </div>
        </div>
    </div>
</div>
