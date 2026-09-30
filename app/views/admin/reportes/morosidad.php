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
                <h1 class="text-xl font-bold text-on-surface">Balance</h1>
            </div>
            <a href="<?= \App\Core\Auth::role() === 'auditor' ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Barra de Acciones del Contenido -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Balance General</h4>
            <p class="text-muted small mb-0">Consolidado financiero por edificios y desglose de solvencia de unidades</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/admin/reportes/morosidad/exportar-csv?<?= http_build_query($filtros) ?>" class="btn btn-outline-success btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined fs-6">csv</span>
                <span>Exportar CSV</span>
            </a>
            <a href="/admin/reportes/morosidad/imprimir?<?= http_build_query($filtros) ?>" target="_blank" class="btn btn-danger btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined fs-6">print</span>
                <span>Imprimir PDF</span>
            </a>
        </div>
    </div>

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
                    <div class="text-muted small fw-semibold text-uppercase">Unidades con Deuda</div>
                    <div class="h3 mb-0 fw-bold text-dark"><?= e($kpis['unidades_morosas']) ?> <small class="fs-6 text-muted">/ <?= e($kpis['total_unidades']) ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-success">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Unidades Solventes</div>
                    <div class="h3 mb-0 fw-bold text-success"><?= e($kpis['unidades_solventes'] ?? ($kpis['total_unidades'] - $kpis['unidades_morosas'])) ?> <small class="fs-6 text-muted">/ <?= e($kpis['total_unidades']) ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white border-start border-4 border-info">
                <div class="card-body py-3">
                    <div class="text-muted small fw-semibold text-uppercase">Tasa de Solvencia</div>
                    <div class="h3 mb-0 fw-bold text-info"><?= e($kpis['tasa_solvencia'] ?? round((100 - $kpis['tasa_morosidad']), 1)) ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="/admin/reportes/morosidad" class="row g-3 align-items-end" id="formFiltrosBalance">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Estado Financiero</label>
                    <select name="estado" class="form-select">
                        <option value="">-- Todos los Estados --</option>
                        <option value="solvente" <?= (($filtros['estado'] ?? '') === 'solvente') ? 'selected' : '' ?>>Solventes (Al Día)</option>
                        <option value="deudor" <?= (($filtros['estado'] ?? '') === 'deudor') ? 'selected' : '' ?>>Con Deuda (Morosos)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Filtrar por Edificio / Torre</label>
                    <select name="edificio_id" class="form-select">
                        <option value="">-- Todos los Edificios --</option>
                        <?php foreach ($edificios as $ed): ?>
                            <option value="<?= e($ed['id']) ?>" <?= (($filtros['edificio_id'] ?? '') == $ed['id']) ? 'selected' : '' ?>>
                                <?= e($ed['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Antigüedad de Deuda</label>
                    <select name="dias_mora" class="form-select">
                        <option value="">-- Todos los Rangos --</option>
                        <option value="30" <?= (($filtros['dias_mora'] ?? '') == '30') ? 'selected' : '' ?>>Mayor a 30 Días</option>
                        <option value="60" <?= (($filtros['dias_mora'] ?? '') == '60') ? 'selected' : '' ?>>Mayor a 60 Días</option>
                        <option value="90" <?= (($filtros['dias_mora'] ?? '') == '90') ? 'selected' : '' ?>>Crítico (Mayor a 90 Días)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold flex-fill d-inline-flex align-items-center justify-content-center gap-1 shadow-sm">
                        <span class="material-symbols-outlined fs-6">filter_list</span> Filtrar
                    </button>
                    <a href="/admin/reportes/morosidad" class="btn btn-outline-secondary font-weight-bold" title="Limpiar Filtros">
                        <span class="material-symbols-outlined align-middle fs-6">restart_alt</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla del Reporte: Agrupación por Edificios -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark">Balance General</h5>
                <small class="text-muted">Vista consolidada por edificio con detalle de unidades desplegable</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-1.5"><?= count($edificiosConsolidados) ?> Edificios Registrados</span>
                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill px-3 py-1.5"><?= count($morosos) ?> Unidades</span>
            </div>
        </div>

        <!-- Búsqueda rápida sobre edificios y unidades -->
        <div class="p-3 border-bottom bg-light bg-opacity-50">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <span class="material-symbols-outlined fs-6">search</span>
                </span>
                <input type="text" id="buscadorBalance" class="form-control border-start-0 ps-0" 
                       placeholder="Búsqueda rápida por nombre de edificio, número de unidad o propietario..." 
                       onkeyup="filtrarBalance(this.value)">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaBalanceEdificios">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Edificio</th>
                            <th class="py-3">Unidades</th>
                            <th class="py-3">Estado de Solvencia</th>
                            <th class="py-3 text-end">Balance Total (Bs)</th>
                            <th class="py-3 text-center pe-4" style="width: 170px;">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background" id="accordionBalanceEdificios">
                        <?php if (empty($edificiosConsolidados)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-primary">search_off</span>
                                    No se encontraron edificios ni unidades para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($edificiosConsolidados as $ed): ?>
                                <?php 
                                $unidadesEdificio = $ed['unidades'] ?? [];
                                $totalUnidades = count($unidadesEdificio);
                                $filtroEdificioId = intval($filtros['edificio_id'] ?? 0);
                                $estaAbierto = ($filtroEdificioId > 0 && $filtroEdificioId === (int)$ed['edificio_id']);
                                $tieneDeuda = ($ed['balance_total'] > 0);
                                $searchTerms = strtolower($ed['edificio_nombre'] . ' ' . ($ed['edificio_descripcion'] ?? '') . ' ' . implode(' ', array_column($unidadesEdificio, 'unidad_numero')) . ' ' . implode(' ', array_column($unidadesEdificio, 'propietario_nombre')));
                                ?>
                                <tr class="fila-edificio cursor-pointer transition-colors" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#collapse-edificio-<?= e($ed['edificio_id']) ?>"
                                    aria-expanded="<?= $estaAbierto ? 'true' : 'false' ?>"
                                    data-busqueda="<?= e($searchTerms) ?>">
                                    <td class="ps-4 py-3 font-bold text-on-surface">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="w-10 h-10 rounded-3 <?= $tieneDeuda ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?> d-flex align-items-center justify-center shrink-0">
                                                <span class="material-symbols-outlined fs-5">domain</span>
                                            </div>
                                            <div>
                                                <span class="text-sm font-bold text-dark d-block"><?= e($ed['edificio_nombre']) ?></span>
                                                <?php if (!empty($ed['edificio_descripcion'])): ?>
                                                    <span class="text-xs text-muted font-normal"><?= e($ed['edificio_descripcion']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-slate-100 text-slate-800 border border-slate-200 px-2.5 py-1.5 rounded-pill fw-bold d-inline-flex align-items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">apartment</span>
                                            <span><?= e($totalUnidades) ?> unidades</span>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6">check_circle</span>
                                                <?= e($ed['unidades_solventes']) ?> Solventes
                                            </span>
                                            <?php if ($ed['unidades_deudoras'] > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fw-bold d-inline-flex align-items-center gap-1">
                                                    <span class="material-symbols-outlined fs-6">warning</span>
                                                    <?= e($ed['unidades_deudoras']) ?> Con Deuda
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2 py-1 fw-semibold">
                                                    0 Con Deuda
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 text-end font-monospace fw-bold fs-6 <?= $tieneDeuda ? 'text-danger' : 'text-success' ?>">
                                        <?= e(formatearMoneda($ed['balance_total'])) ?>
                                    </td>
                                    <td class="py-3 text-center pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 text-xs fw-bold rounded-pill btn-toggle-detalle"
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse-edificio-<?= e($ed['edificio_id']) ?>"
                                                title="Ver unidades de <?= e($ed['edificio_nombre']) ?>">
                                            <span class="btn-text"><?= $estaAbierto ? 'Ocultar' : 'Ver Unidades' ?></span>
                                            <span class="material-symbols-outlined fs-6 chevron-icon"><?= $estaAbierto ? 'expand_less' : 'expand_more' ?></span>
                                        </button>
                                    </td>
                                </tr>

                                <!-- DESPLIEGUE DRILL-DOWN: DETALLE DE UNIDADES DEL EDIFICIO SELECCIONADO (ACORDEÓN EXCLUSIVO) -->
                                <tr class="collapse <?= $estaAbierto ? 'show' : '' ?> fila-unidades-collapse bg-light bg-opacity-75" 
                                    id="collapse-edificio-<?= e($ed['edificio_id']) ?>" 
                                    data-bs-parent="#accordionBalanceEdificios">
                                    <td colspan="5" class="p-3 border-bottom">
                                        <div class="bg-white rounded-3 border shadow-sm p-3">
                                            <div class="d-flex align-items-center justify-content-between border-bottom pb-2.5 mb-3 flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="material-symbols-outlined text-primary fs-5">roofing</span>
                                                    <h6 class="fw-bold uppercase tracking-wider text-dark mb-0">
                                                        Unidades pertenecientes a <?= e($ed['edificio_nombre']) ?>
                                                    </h6>
                                                    <span class="badge bg-slate-100 text-slate-700 border text-xs"><?= e($totalUnidades) ?> unidades</span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="text-xs text-muted">Balance del Edificio:</span>
                                                    <span class="fw-bold fs-6 font-monospace <?= $tieneDeuda ? 'text-danger' : 'text-success' ?>">
                                                        <?= e(formatearMoneda($ed['balance_total'])) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <?php if (empty($unidadesEdificio)): ?>
                                                <div class="text-center py-4 text-muted">
                                                    <span class="material-symbols-outlined fs-2 text-muted mb-1 d-block">home</span>
                                                    <p class="small mb-0">No hay unidades registradas o que coincidan con los filtros en este edificio.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr class="text-muted small fw-bold text-uppercase">
                                                                <th class="ps-3 py-2">Unidad / Apto</th>
                                                                <th class="py-2">Propietario / Contacto</th>
                                                                <th class="py-2 text-center">Estado</th>
                                                                <th class="py-2 text-center">Facturas Vencidas</th>
                                                                <th class="py-2 text-center">Días de Mora</th>
                                                                <th class="py-2 text-end">Total Deuda (Bs)</th>
                                                                <th class="py-2 text-center pe-3">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($unidadesEdificio as $u): ?>
                                                                <?php $esSolvente = ($u['estado_financiero'] ?? 'solvente') === 'solvente'; ?>
                                                                <tr class="<?= $esSolvente ? 'bg-white' : 'table-danger bg-opacity-10' ?>">
                                                                    <td class="ps-3 font-monospace fw-bold text-dark">
                                                                        Apto/Unidad <?= e($u['unidad_numero']) ?>
                                                                    </td>
                                                                    <td>
                                                                        <div class="fw-bold text-dark small"><?= e($u['propietario_nombre'] ?: 'Sin Propietario') ?></div>
                                                                        <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                                            C.I: <?= e($u['propietario_cedula'] ?: 'N/A') ?> 
                                                                            <?= ($u['propietario_telefono'] && $u['propietario_telefono'] !== 'N/A') ? ' | Tel: ' . e($u['propietario_telefono']) : '' ?>
                                                                        </small>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($esSolvente): ?>
                                                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold d-inline-flex align-items-center gap-1">
                                                                                <span class="material-symbols-outlined fs-6">check_circle</span>
                                                                                Solvente
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fw-bold d-inline-flex align-items-center gap-1">
                                                                                <span class="material-symbols-outlined fs-6">warning</span>
                                                                                Con Deuda
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($esSolvente): ?>
                                                                            <span class="badge bg-light text-muted border rounded-pill">0</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-danger rounded-pill"><?= e($u['facturas_vencidas']) ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($esSolvente): ?>
                                                                            <span class="text-success small fw-semibold d-inline-flex align-items-center gap-1">
                                                                                <span class="material-symbols-outlined fs-6">done_all</span>
                                                                                Al día
                                                                            </span>
                                                                        <?php elseif ($u['dias_mora_max'] >= 90): ?>
                                                                            <span class="badge bg-danger rounded-pill px-2.5 py-1 fw-bold">
                                                                                <span class="material-symbols-outlined align-middle fs-6 me-1">warning</span>
                                                                                <?= e($u['dias_mora_max']) ?> días (Crítico)
                                                                            </span>
                                                                        <?php elseif ($u['dias_mora_max'] >= 60): ?>
                                                                            <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold">
                                                                                <?= e($u['dias_mora_max']) ?> días
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">
                                                                                <?= e($u['dias_mora_max']) ?> días
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-end font-monospace fw-bold small <?= $esSolvente ? 'text-muted' : 'text-danger' ?>">
                                                                        <?= e(formatearMoneda($u['total_deuda'])) ?>
                                                                    </td>
                                                                    <td class="text-center pe-3">
                                                                        <?php if (!$esSolvente): ?>
                                                                            <a href="/admin/reportes/carta-deuda/<?= e($u['unidad_id']) ?>" class="btn btn-outline-warning btn-sm fw-bold d-inline-flex align-items-center gap-1 py-1 px-2" title="Ver Carta Oficial de Deuda">
                                                                                <span class="material-symbols-outlined fs-6">description</span> Carta Deuda
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <span class="text-muted small d-inline-flex align-items-center gap-1">
                                                                                <span class="material-symbols-outlined fs-6 text-success">verified</span> Al día
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Fila informativa para resultados de búsqueda en vivo vacíos -->
            <div id="sinResultadosBalance" class="text-center py-5 text-muted d-none">
                <span class="material-symbols-outlined display-4 d-block mb-2 text-primary">search_off</span>
                No se encontraron edificios o unidades que coincidan con la búsqueda.
            </div>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>

<style>
.fila-edificio {
    transition: background-color 0.2s ease-in-out;
}
.fila-edificio:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.04);
}
.fila-unidades-collapse {
    transition: all 0.25s ease-in-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const collapseElements = document.querySelectorAll('.fila-unidades-collapse');

    collapseElements.forEach(collapseEl => {
        // Evento show: Acordeón exclusivo cerrando cualquier otro edificio expandido
        collapseEl.addEventListener('show.bs.collapse', function () {
            collapseElements.forEach(otherEl => {
                if (otherEl !== collapseEl && otherEl.classList.contains('show')) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                        const bsCollapse = bootstrap.Collapse.getInstance(otherEl) || new bootstrap.Collapse(otherEl, { toggle: false });
                        bsCollapse.hide();
                    } else {
                        otherEl.classList.remove('show');
                    }
                }
            });

            // Actualizar texto e icono del botón activador a "Ocultar"
            const targetId = '#' + collapseEl.id;
            const triggerBtns = document.querySelectorAll(`[data-bs-target="${targetId}"]`);
            triggerBtns.forEach(btn => {
                const icon = btn.querySelector('.chevron-icon');
                if (icon) icon.textContent = 'expand_less';
                const textSpan = btn.querySelector('.btn-text');
                if (textSpan) textSpan.textContent = 'Ocultar';
                btn.setAttribute('aria-expanded', 'true');
            });
        });

        // Evento hide: Restaurar texto e icono del botón a "Ver Unidades"
        collapseEl.addEventListener('hide.bs.collapse', function () {
            const targetId = '#' + collapseEl.id;
            const triggerBtns = document.querySelectorAll(`[data-bs-target="${targetId}"]`);
            triggerBtns.forEach(btn => {
                const icon = btn.querySelector('.chevron-icon');
                if (icon) icon.textContent = 'expand_more';
                const textSpan = btn.querySelector('.btn-text');
                if (textSpan) textSpan.textContent = 'Ver Unidades';
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    });
});

// Búsqueda en vivo client-side sobre edificios y unidades
function filtrarBalance(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#tablaBalanceEdificios tbody tr.fila-edificio');
    let visibles = 0;

    rows.forEach(row => {
        const targetId = row.getAttribute('data-bs-target');
        const collapseRow = targetId ? document.querySelector(targetId) : null;
        const textoEdificio = (row.dataset.busqueda || row.innerText || '').toLowerCase();
        const textoUnidades = collapseRow ? (collapseRow.innerText || '').toLowerCase() : '';

        if (!q || textoEdificio.includes(q) || textoUnidades.includes(q)) {
            row.style.display = '';
            visibles++;
            if (q && textoUnidades.includes(q) && collapseRow) {
                // Autoexpandir si la búsqueda coincide directamente con unidades dentro del edificio
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(collapseRow, { toggle: false }).show();
                } else {
                    collapseRow.classList.add('show');
                }
            }
        } else {
            row.style.display = 'none';
            if (collapseRow) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(collapseRow, { toggle: false }).hide();
                } else {
                    collapseRow.classList.remove('show');
                }
            }
        }
    });

    const sinResultados = document.getElementById('sinResultadosBalance');
    if (sinResultados) {
        if (visibles === 0 && rows.length > 0) {
            sinResultados.classList.remove('d-none');
        } else {
            sinResultados.classList.add('d-none');
        }
    }
}
</script>
