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
                <h1 class="text-xl font-bold text-on-surface">Carta de Deuda</h1>
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
            <h4 class="text-lg font-bold text-on-surface mb-1">Carta de Deuda</h4>
            <p class="text-xs text-on-surface-variant mb-0">Listado de unidades con edificio, propietario, mora y deuda pendiente</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/admin/reportes/morosidad/exportar-csv?<?= http_build_query($filtros) ?>" class="bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-4 py-2.5 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">csv</span>
                <span>Exportar CSV</span>
            </a>
            <a href="/admin/reportes/morosidad/imprimir?<?= http_build_query($filtros) ?>" target="_blank" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-4 py-2.5 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">print</span>
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
    <div class="bg-white rounded-2xl border border-outline-variant p-4 shadow-sm mb-6">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-100">
            <span class="material-symbols-outlined text-primary text-sm">filter_alt</span>
            <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Filtros de Búsqueda</h2>
        </div>
        <form method="GET" action="/admin/reportes/morosidad" id="formFiltrosBalance" class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
            <div class="flex flex-col gap-1">
                <label for="edificio_id" class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Filtrar por Edificio / Torre</label>
                <select name="edificio_id" id="edificio_id" class="w-full px-3 py-2 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                    <option value="">-- Todos los Edificios --</option>
                    <?php foreach ($edificios as $ed): ?>
                        <option value="<?= e($ed['id']) ?>" <?= (($filtros['edificio_id'] ?? '') == $ed['id']) ? 'selected' : '' ?>>
                            <?= e($ed['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label for="dias_mora" class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Antigüedad de Deuda</label>
                <select name="dias_mora" id="dias_mora" class="w-full px-3 py-2 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                    <option value="">-- Todos los Rangos --</option>
                    <option value="30" <?= (($filtros['dias_mora'] ?? '') == '30') ? 'selected' : '' ?>>Mayor a 30 Días</option>
                    <option value="60" <?= (($filtros['dias_mora'] ?? '') == '60') ? 'selected' : '' ?>>Mayor a 60 Días</option>
                    <option value="90" <?= (($filtros['dias_mora'] ?? '') == '90') ? 'selected' : '' ?>>Crítico (Mayor a 90 Días)</option>
                </select>
            </div>
            <div class="col-span-full flex justify-end gap-2">
                <a href="/admin/reportes/morosidad" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs transition-all flex items-center justify-center gap-1" title="Limpiar Filtros">
                    <span class="material-symbols-outlined text-[16px]">clear_all</span>
                    Limpiar Filtros
                </a>
                <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2 rounded-xl shadow-sm text-xs transition-all flex items-center justify-center gap-1 active:scale-95">
                    <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                    Aplicar Filtros
                </button>
            </div>
        </form>
    </div>

    <style>
        #tablaBalanceUnidades thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #ffffff;
        }
    </style>

    <!-- Tabla del Reporte: Lista Plana de Unidades -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="text-lg font-bold text-on-surface mb-0">Carta de Deuda</h5>
                <small class="text-xs text-on-surface-variant">Unidades con su edificio y propietario, ordenadas por mayor deuda pendiente</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= count($morosos) ?> Unidades</span>
                <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= count($edificiosConsolidados) ?> Edificios</span>
            </div>
        </div>

        <!-- Búsqueda rápida por edificio, unidad o propietario -->
        <div class="p-3 border-bottom bg-light bg-opacity-50">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-[16px]">search</span>
                <input type="text" id="buscadorBalance" placeholder="Búsqueda rápida por nombre de edificio, número de unidad o propietario..."
                       onkeyup="filtrarBalance(this.value)"
                       class="w-full pl-8 pr-3 py-2 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive max-h-[calc(100vh_-_33rem)] min-h-[16rem] overflow-y-auto">
                <table class="w-full text-left text-sm border-collapse" id="tablaBalanceUnidades" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4" style="width: 22%;">Edificio</th>
                            <th class="py-3 px-4" style="width: 9%;">Unidad</th>
                            <th class="py-3 px-4" style="width: 19%;">Propietario</th>
                            <th class="py-3 px-4 text-center" style="width: 10%;">Estado</th>
                            <th class="py-3 px-4 text-center" style="width: 9%;">Facturas Vencidas</th>
                            <th class="py-3 px-4 text-center" style="width: 12%;">Días de Mora</th>
                            <th class="py-3 px-4 text-end" style="width: 10%;">Total Deuda (Bs)</th>
                            <th class="py-3 px-4 text-center" style="width: 9%; min-width: 140px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php if (empty($morosos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">search_off</span>
                                    No se encontraron unidades para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($morosos as $u): ?>
                                <?php $esSolvente = ($u['estado_financiero'] ?? 'solvente') === 'solvente'; ?>
                                <tr class="fila-unidad hover:bg-background/40 transition-colors <?= $esSolvente ? 'bg-white' : 'bg-red-50' ?>"
                                    data-busqueda="<?= e(strtolower($u['edificio_nombre'] . ' ' . $u['unidad_numero'] . ' ' . ($u['propietario_nombre'] ?? '') . ' ' . ($u['propietario_cedula'] ?? ''))) ?>">
                                    <td class="py-4 px-4">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="w-10 h-10 rounded-3 <?= $esSolvente ? 'bg-primary-subtle text-primary' : 'bg-red-50 text-red-400' ?> d-flex align-items-center justify-content-center shrink-0">
                                                <span class="material-symbols-outlined fs-5">domain</span>
                                            </div>
                                            <div>
                                                <span class="text-sm font-semibold text-on-surface d-block"><?= e($u['edificio_nombre']) ?></span>
                                                <?php if (!empty($u['edificio_descripcion'])): ?>
                                                    <span class="text-xs text-on-surface-variant font-normal"><?= e($u['edificio_descripcion']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="font-mono text-xs font-bold text-on-surface"><?= e($u['unidad_numero']) ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-on-surface small"><?= e($u['propietario_nombre'] ?: 'Sin Propietario') ?></div>
                                        <small class="text-on-surface-variant d-block" style="font-size: 0.75rem;">
                                            C.I: <?= e($u['propietario_cedula'] ?: 'N/A') ?>
                                            <?= ($u['propietario_telefono'] && $u['propietario_telefono'] !== 'N/A') ? ' | Tel: ' . e($u['propietario_telefono']) : '' ?>
                                        </small>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if ($esSolvente): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6">check_circle</span>
                                                Solvente
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-red-50 text-red-500 border border-red-200 px-2 py-1 fw-bold d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6">warning</span>
                                                Con Deuda
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if ($esSolvente): ?>
                                            <span class="badge bg-light text-muted border rounded-pill">0</span>
                                        <?php else: ?>
                                            <span class="badge bg-red-50 text-red-500 border border-red-200 rounded-pill fw-bold"><?= e($u['facturas_vencidas']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if ($esSolvente): ?>
                                            <span class="text-success small fw-semibold d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6">done_all</span>
                                                Al día
                                            </span>
                                        <?php elseif ($u['dias_mora_max'] >= 90): ?>
                                            <span class="badge bg-red-100 text-red-600 border border-red-200 rounded-pill px-2.5 py-1 fw-bold">
                                                <span class="material-symbols-outlined align-middle fs-6 me-1">warning</span>
                                                <?= e($u['dias_mora_max']) ?> días (Crítico)
                                            </span>
                                        <?php elseif ($u['dias_mora_max'] >= 60): ?>
                                            <span class="badge bg-amber-50 text-amber-700 border border-amber-200 rounded-pill px-2.5 py-1 fw-bold">
                                                <?= e($u['dias_mora_max']) ?> días
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-sky-50 text-sky-700 border border-sky-200 rounded-pill px-2.5 py-1">
                                                <?= e($u['dias_mora_max']) ?> días
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end py-4 px-4 font-mono text-xs font-bold <?= $esSolvente ? 'text-muted' : 'text-danger' ?>">
                                        <?= e(formatearMoneda($u['total_deuda'])) ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if (!$esSolvente): ?>
                                            <a href="/admin/reportes/carta-deuda/<?= e($u['unidad_id']) ?>" class="inline-flex items-center gap-1.5 bg-amber-400 hover:bg-amber-500 text-amber-950 border border-amber-500/50 shadow-sm px-3 py-1.5 rounded-lg text-xs font-bold transition-colors whitespace-nowrap" title="Ver Carta Oficial de Deuda">
                                                <span class="material-symbols-outlined text-[16px]">description</span>
                                                <span>Carta Deuda</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-on-surface-variant small d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6 text-success">verified</span> Al día
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Fila informativa para resultados de búsqueda en vivo vacíos -->
            <div id="sinResultadosBalance" class="text-center py-12 text-on-surface-variant d-none">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">search_off</span>
                No se encontraron unidades que coincidan con la búsqueda.
            </div>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>

<script>
// Búsqueda en vivo client-side sobre la lista plana de unidades
function filtrarBalance(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#tablaBalanceUnidades tbody tr.fila-unidad');
    let visibles = 0;

    rows.forEach(row => {
        const texto = (row.dataset.busqueda || '').toLowerCase();
        if (!q || texto.includes(q)) {
            row.style.display = '';
            visibles++;
        } else {
            row.style.display = 'none';
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
