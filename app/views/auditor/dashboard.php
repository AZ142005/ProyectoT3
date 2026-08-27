<div class="flex flex-1 min-h-screen w-full">
    <?php $activeRoute = 'dashboard'; require VIEWS_PATH . '/layouts/auditor_sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Dashboard de Auditoría</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="/auditor/exportar-log" class="btn btn-outline-primary btn-sm fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined fs-6">download</span>
                    <span class="hidden sm:inline">Exportar Log</span>
                </a>
                <a href="/perfil" class="text-slate-600 hover:text-primary font-bold text-xs px-3 py-2 rounded-lg border border-slate-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">account_circle</span>
                    <span class="hidden sm:inline">Perfil</span>
                </a>
                <a href="/auth/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span class="hidden sm:inline">Salir</span>
                </a>
            </div>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- Banner Informativo de Solo Lectura -->
                <div class="alert alert-info border-0 shadow-sm rounded-3 d-flex align-items-center gap-3 mb-4">
                    <span class="material-symbols-outlined fs-2 text-info">verified_user</span>
                    <div>
                        <strong>Perfil de Fiscalización Activo:</strong> Este rol cuenta con permisos de solo consulta (lectura inmutable) sobre todos los libros contables, eventos de seguridad y comprobantes del condominio.
                    </div>
                </div>

    <!-- Métricas Clave de Auditoría -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Eventos en Log</span>
                        <span class="h3 fw-bold text-dark mb-0"><?= number_format($totalLogs) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-primary opacity-50">receipt_long</span>
                </div>
                <small class="text-muted mt-2 d-block">Trazabilidad inmutable de acciones</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Libro Mayor</span>
                        <span class="h3 fw-bold text-success mb-0"><?= number_format($totalMovimientos) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-success opacity-50">account_balance_wallet</span>
                </div>
                <small class="text-muted mt-2 d-block">Movimientos contables registrados</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Conciliaciones</span>
                        <span class="h3 fw-bold text-info mb-0"><?= number_format($totalConciliados) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-info opacity-50">sync_alt</span>
                </div>
                <small class="text-muted mt-2 d-block">Extractos bancarios conciliados</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Total Gastos Auditados</span>
                        <span class="h4 fw-bold text-dark mb-0">Bs. <?= number_format($totalGastosMonto, 2) ?></span>
                    </div>
                    <span class="material-symbols-outlined fs-1 text-warning opacity-50">payments</span>
                </div>
                <small class="text-muted mt-2 d-block">Justificados con facturas fiscales</small>
            </div>
        </div>
    </div>

    <!-- Tabla de Últimos Eventos de Auditoría -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Últimos Eventos de Auditoría Registrados</h5>
            <a href="/auditor/log-transacciones" class="btn btn-primary btn-sm fw-bold">Ver Registro Completo</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-sm">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Fecha y Hora</th>
                            <th class="py-3">Usuario / Admin</th>
                            <th class="py-3">Acción Realizada</th>
                            <th class="py-3">Tabla / ID</th>
                            <th class="py-3">Detalle</th>
                            <th class="py-3 text-end pe-4">Dirección IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimosLogs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">shield</span>
                                    No hay eventos registrados en el log de auditoría.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimosLogs as $l): ?>
                                <tr>
                                    <td class="ps-4 font-monospace small text-muted"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($l['usuario_nombre']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary rounded-pill px-3 py-1 font-monospace"><?= e($l['accion']) ?></span>
                                    </td>
                                    <td class="font-monospace small text-muted">
                                        <?= e($l['tabla_afectada']) ?> #<?= e($l['registro_id'] ?: '-') ?>
                                    </td>
                                    <td class="small text-dark" style="max-width: 300px;">
                                        <?= e($l['detalles']) ?>
                                    </td>
                                    <td class="text-end pe-4 font-monospace small text-muted">
                                        <?= e($l['ip_address'] ?: '127.0.0.1') ?>
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
    </div>
</div>
