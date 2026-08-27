<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'conciliacion'; 
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
                <h1 class="text-xl font-bold text-on-surface">Conciliación Bancaria</h1>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportarExtracto">
                    <span class="material-symbols-outlined fs-6">upload_file</span>
                    <span class="hidden sm:inline">Importar Extracto</span>
                </button>
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

    <!-- Selector de Lote y Métricas Rápidas -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Coincidencias Exactas</span>
                        <span class="h3 fw-bold text-success mb-0"><?= count($resultadoCruce['coincidencias_exactas']) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-success opacity-50">verified</span>
                </div>
                <small class="text-muted mt-2 d-block">100% Match (Referencia + Monto)</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Coincidencias Sugeridas</span>
                        <span class="h3 fw-bold text-warning mb-0"><?= count($resultadoCruce['coincidencias_sugeridas']) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-warning opacity-50">rule</span>
                </div>
                <small class="text-muted mt-2 d-block">Fuzzy Match Jaro-Winkler ≥ 85%</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Inconsistencias / Alertas</span>
                        <span class="h3 fw-bold text-danger mb-0"><?= count($resultadoCruce['inconsistencias']) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-danger opacity-50">error</span>
                </div>
                <small class="text-muted mt-2 d-block">Referencias nulas o duplicadas</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Sin Coincidencia</span>
                        <span class="h3 fw-bold text-secondary mb-0"><?= count($resultadoCruce['sin_coincidencia']) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-secondary opacity-50">help</span>
                </div>
                <small class="text-muted mt-2 d-block">Movimientos sin pago pendiente</small>
            </div>
        </div>
    </div>

    <!-- Pestañas de Resultados del Cruce -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <ul class="nav nav-pills card-header-pills" id="cruceTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold" id="exactas-tab" data-bs-toggle="tab" data-bs-target="#exactas" type="button">
                        🟢 Coincidencias Exactas (<?= count($resultadoCruce['coincidencias_exactas']) ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold" id="sugeridas-tab" data-bs-toggle="tab" data-bs-target="#sugeridas" type="button">
                        🟡 Sugerencias Difusas (<?= count($resultadoCruce['coincidencias_sugeridas']) ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold" id="inconsistencias-tab" data-bs-toggle="tab" data-bs-target="#inconsistencias" type="button">
                        🔴 Inconsistencias (<?= count($resultadoCruce['inconsistencias']) ?>)
                    </button>
                </li>
            </ul>

            <?php if (!empty($resultadoCruce['coincidencias_exactas'])): ?>
                <button type="button" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center gap-1" onclick="conciliarLoteExactas()">
                    <span class="material-symbols-outlined fs-6">done_all</span> Conciliar Todas las Exactas (1-Clic)
                </button>
            <?php endif; ?>
        </div>

        <div class="card-body p-0">
            <div class="tab-content" id="cruceTabsContent">
                <!-- TAB 1: COINCIDENCIAS EXACTAS -->
                <div class="tab-pane fade show active" id="exactas" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">Movimiento Banco (Extracto)</th>
                                    <th class="py-3">Pago Reportado (Residente)</th>
                                    <th class="py-3 text-center">Referencia</th>
                                    <th class="py-3 text-end">Monto (Bs.)</th>
                                    <th class="py-3 text-center">Similitud</th>
                                    <th class="py-3 text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resultadoCruce['coincidencias_exactas'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">task_alt</span>
                                            No hay coincidencias exactas pendientes por conciliar.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resultadoCruce['coincidencias_exactas'] as $match): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?= e($match['extracto']['banco']) ?></div>
                                                <small class="text-muted"><?= date('d/m/Y', strtotime($match['extracto']['fecha_movimiento'])) ?> | <?= e(substr($match['extracto']['descripcion_banco'], 0, 30)) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= e($match['pago']['residente_nombre']) ?></div>
                                                <small class="text-muted">Apto <?= e($match['pago']['unidad_numero']) ?> (<?= e($match['pago']['edificio_nombre']) ?>)</small>
                                            </td>
                                            <td class="text-center font-monospace fw-bold text-primary">
                                                <?= e($match['extracto']['referencia_bancaria']) ?>
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-dark">
                                                Bs. <?= number_format($match['extracto']['monto'], 2) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success rounded-pill px-3 py-1">100% Exacto</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <form method="POST" action="/admin/conciliacion/conciliar" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="extracto_id" value="<?= e($match['extracto']['id']) ?>">
                                                    <input type="hidden" name="pago_id" value="<?= e($match['pago']['id']) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm font-weight-bold d-inline-flex align-items-center gap-1">
                                                        <span class="material-symbols-outlined fs-6">check</span> Conciliar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: COINCIDENCIAS SUGERIDAS (FUZZY MATCH) -->
                <div class="tab-pane fade" id="sugeridas" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">Movimiento Banco</th>
                                    <th class="py-3">Pago Sugerido</th>
                                    <th class="py-3 text-center">Ref. Banco vs Pago</th>
                                    <th class="py-3 text-end">Monto</th>
                                    <th class="py-3 text-center">Jaro-Winkler</th>
                                    <th class="py-3 text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resultadoCruce['coincidencias_sugeridas'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">rule</span>
                                            No hay coincidencias sugeridas difusas pendientes.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resultadoCruce['coincidencias_sugeridas'] as $match): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?= e($match['extracto']['banco']) ?></div>
                                                <small class="text-muted"><?= date('d/m/Y', strtotime($match['extracto']['fecha_movimiento'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= e($match['pago']['residente_nombre']) ?></div>
                                                <small class="text-muted">Apto <?= e($match['pago']['unidad_numero']) ?> | Fecha: <?= date('d/m/Y', strtotime($match['pago']['fecha_pago'])) ?></small>
                                            </td>
                                            <td class="text-center font-monospace small">
                                                <span class="text-muted">Bco:</span> <strong><?= e($match['extracto']['referencia_bancaria']) ?></strong><br>
                                                <span class="text-muted">Res:</span> <strong><?= e($match['pago']['referencia']) ?></strong>
                                            </td>
                                            <td class="text-end font-monospace fw-bold">
                                                Bs. <?= number_format($match['extracto']['monto'], 2) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">
                                                    <?= e($match['similitud']) ?>% Similitud
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <form method="POST" action="/admin/conciliacion/conciliar" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="extracto_id" value="<?= e($match['extracto']['id']) ?>">
                                                    <input type="hidden" name="pago_id" value="<?= e($match['pago']['id']) ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold" onclick="return confirm('¿Confirmar conciliación sugerida por similitud difusa?');">
                                                        Aprobar Cruce
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: INCONSISTENCIAS -->
                <div class="tab-pane fade" id="inconsistencias" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">Fecha</th>
                                    <th class="py-3">Banco / Descripción</th>
                                    <th class="py-3">Referencia</th>
                                    <th class="py-3 text-end">Monto</th>
                                    <th class="py-3">Detalle Inconsistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resultadoCruce['inconsistencias'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <span class="material-symbols-outlined display-4 d-block mb-2 text-success">verified</span>
                                            ¡Excelente! No se detectaron inconsistencias en el extracto analizado.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resultadoCruce['inconsistencias'] as $inc): ?>
                                        <tr>
                                            <td class="ps-4 small text-muted"><?= date('d/m/Y', strtotime($inc['extracto']['fecha_movimiento'])) ?></td>
                                            <td><?= e($inc['extracto']['banco']) ?> - <?= e(substr($inc['extracto']['descripcion_banco'], 0, 40)) ?></td>
                                            <td class="font-monospace fw-bold text-danger"><?= e($inc['extracto']['referencia_bancaria'] ?: 'N/A') ?></td>
                                            <td class="text-end font-monospace">Bs. <?= number_format($inc['extracto']['monto'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-danger rounded-pill px-3 py-1"><?= e($inc['motivo']) ?></span>
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
    </div>
</div>

<!-- Modal para Importar Extracto Bancario -->
<div class="modal fade" id="modalImportarExtracto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form method="POST" action="/admin/conciliacion/importar" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">upload_file</span>
                        Importar Extracto Bancario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Entidad Bancaria <span class="text-danger">*</span></label>
                        <select name="banco" required class="form-select">
                            <option value="mercantil">Banco Mercantil (CSV / TXT)</option>
                            <option value="banesco">Banesco (CSV / TXT)</option>
                            <option value="venezuela">Banco de Venezuela (CSV / TXT)</option>
                            <option value="provincial">BBVA Provincial (CSV / TXT)</option>
                            <option value="generico_csv">Formato CSV Genérico</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Archivo de Extracto (.CSV o .TXT) <span class="text-danger">*</span></label>
                        <input type="file" name="archivo_extracto" required accept=".csv,.txt" class="form-control">
                        <div class="form-text small text-muted">Los movimientos de débito/comisiones se clasificarán automáticamente como descartados.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Procesar e Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>

<form id="formLoteExactas" method="POST" action="/admin/conciliacion/conciliar-lote" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="items_json" id="itemsJsonInput" value="">
</form>

<script>
    function conciliarLoteExactas() {
        const items = <?= json_encode(array_map(fn($m) => [
            'extracto_id' => $m['extracto']['id'],
            'pago_id'     => $m['pago']['id']
        ], $resultadoCruce['coincidencias_exactas'])) ?>;

        if (items.length === 0) return;
        if (!confirm('¿Desea conciliar y aprobar automáticamente ' + items.length + ' pagos con 100% de coincidencia exacta?')) return;

        document.getElementById('itemsJsonInput').value = JSON.stringify(items);
        document.getElementById('formLoteExactas').submit();
    }
</script>
