<?php
$resumen = $resumen ?? [];
$kpis = $kpis ?? ($resumen['kpis_morosidad'] ?? []);
$gastos_por_categoria = $gastos_por_categoria ?? [];
$cuentas_bancarias = $cuentas_bancarias ?? [];
$ultimos_comprobantes = $ultimos_comprobantes ?? [];
$mes = intval($mes ?? date('n'));
$anio = intval($anio ?? date('Y'));
?>
<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'dashboard'; 
    if (\App\Core\Auth::role() === 'auditor') {
        require VIEWS_PATH . '/layouts/auditor_sidebar.php';
    } else {
        require VIEWS_PATH . '/layouts/admin_sidebar.php';
    }
    ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0 bg-slate-50/50">
        <!-- Barra superior -->
        <header class="bg-white border-b-2 border-institutional-brown/40 h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-on-surface mb-0">Panel de Control Financiero</h1>
                    <span class="text-xs text-on-surface-variant font-medium">Resumen general y estado económico del conjunto</span>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Selector de Período Rápido Permanente -->
                <form method="GET" action="/admin/dashboard" class="d-flex align-items-center gap-1.5 mb-0">
                    <select name="mes" class="form-select form-select-sm text-xs font-semibold rounded-lg border-outline-variant bg-white" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= e($m) ?>" <?= $m === $mes ? 'selected' : '' ?>><?= e(nombreMes($m)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="anio" class="form-select form-select-sm text-xs font-semibold rounded-lg border-outline-variant bg-white" onchange="this.form.submit()">
                        <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= e($y) ?>" <?= $y === $anio ? 'selected' : '' ?>><?= e($y) ?></option>
                        <?php endfor; ?>
                    </select>
                </form>

                <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                </a>
            </div>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <!-- Banner Informativo: Alcance de Solo Lectura y Centralización en Conciliación -->
            <div class="card border-0 shadow-xs rounded-3 mb-4 bg-white border-start border-4 border-primary">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2.5 bg-blue-50 text-primary rounded-circle d-flex align-items-center justify-center">
                            <span class="material-symbols-outlined fs-4">analytics</span>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark fs-6">Resumen Ejecutivo Financiero (Solo Lectura)</h5>
                            <p class="text-muted small mb-0">
                                Monitoreo centralizado de balances y salud de cobranzas. La verificación y aprobación operativa de pagos se gestiona exclusivamente en <strong>Conciliación</strong>.
                            </p>
                        </div>
                    </div>
                    <?php if (($resumen['total_pendientes'] ?? 0) > 0): ?>
                        <div class="d-flex align-items-center gap-2 shrink-0">
                            <span class="badge bg-amber-100 text-amber-900 border border-amber-300 px-3 py-2 rounded-pill font-bold text-xs d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined text-sm">pending</span>
                                <?= e($resumen['total_pendientes']) ?> por conciliar
                            </span>
                            <a href="/admin/conciliacion" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                                <span>Ir a Conciliación</span>
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-2 rounded-pill font-bold text-xs d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined text-sm">verified</span>
                            Pagos al día
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Métricas Financieras Principales (KPI Cards) -->
            <div class="row g-4 mb-4">
                <!-- Tarjeta 1: Total por Cobrar (Deuda Total) -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Total por Cobrar</span>
                                <div class="bg-red-50 text-danger p-2.5 rounded-circle d-flex align-items-center justify-center">
                                    <span class="material-symbols-outlined fs-5">account_balance_wallet</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="fw-bold text-danger mb-1"><?= e(formatearMoneda($resumen['total_por_cobrar'] ?? 0)) ?></h3>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted text-xs"><?= e($kpis['unidades_morosas'] ?? 0) ?> unidades deudoras</span>
                                    <a href="/admin/reportes/morosidad" class="text-xs text-primary font-bold text-decoration-none hover:underline d-inline-flex align-items-center gap-0.5">
                                        <span>Carta de Deuda</span>
                                        <span class="material-symbols-outlined text-xs">chevron_right</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta 2: Ingresos Recaudados (Mes Actual) -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Ingresos (<?= e(nombreMes($mes)) ?>)</span>
                                <div class="bg-emerald-50 text-emerald-700 p-2.5 rounded-circle d-flex align-items-center justify-center">
                                    <span class="material-symbols-outlined fs-5">trending_up</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="fw-bold text-emerald-700 mb-1"><?= e(formatearMoneda($resumen['total_ingresos_mes'] ?? 0)) ?></h3>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted text-xs">Histórico: <?= e(formatearMoneda($resumen['total_recaudado_historico'] ?? 0)) ?></span>
                                    <a href="/admin/comprobantes" class="text-xs text-primary font-bold text-decoration-none hover:underline d-inline-flex align-items-center gap-0.5">
                                        <span>Historial</span>
                                        <span class="material-symbols-outlined text-xs">chevron_right</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta 3: Egresos / Gastos Comunes (Mes Actual) -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Egresos (<?= e(nombreMes($mes)) ?>)</span>
                                <div class="bg-blue-50 text-primary p-2.5 rounded-circle d-flex align-items-center justify-center">
                                    <span class="material-symbols-outlined fs-5">receipt_long</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="fw-bold text-primary mb-1"><?= e(formatearMoneda($resumen['total_gastos_mes'] ?? 0)) ?></h3>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted text-xs">Histórico: <?= e(formatearMoneda($resumen['total_gastos_historico'] ?? 0)) ?></span>
                                    <a href="/admin/gastos" class="text-xs text-primary font-bold text-decoration-none hover:underline d-inline-flex align-items-center gap-0.5">
                                        <span>Gastos</span>
                                        <span class="material-symbols-outlined text-xs">chevron_right</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta 4: Balance Operativo Neto -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Balance Operativo</span>
                                <div class="<?= ($resumen['balance_neto_mes'] ?? 0) >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-danger' ?> p-2.5 rounded-circle d-flex align-items-center justify-center">
                                    <span class="material-symbols-outlined fs-5">balance</span>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h3 class="fw-bold <?= ($resumen['balance_neto_mes'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-danger' ?> mb-0">
                                        <?= e(formatearMoneda($resumen['balance_neto_mes'] ?? 0)) ?>
                                    </h3>
                                    <span class="badge <?= ($resumen['balance_neto_mes'] ?? 0) >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-danger' ?> text-[10px] font-bold px-2 py-0.5 rounded-pill">
                                        <?= ($resumen['balance_neto_mes'] ?? 0) >= 0 ? 'Superávit' : 'Déficit' ?>
                                    </span>
                                </div>
                                <span class="text-muted text-xs d-block">Neto acumulado: <?= e(formatearMoneda($resumen['balance_historico'] ?? 0)) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila 2: Salud de Cartera & Distribución de Egresos -->
            <div class="row g-4 mb-4">
                <!-- Columna Izquierda: Salud de Cartera (Morosidad vs. Solvencia) -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-header bg-white py-3.5 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-primary fs-5">pie_chart</span>
                                <h5 class="fw-bold mb-0 text-dark fs-6">Salud de Cartera (Morosidad vs Solvencia)</h5>
                            </div>
                            <span class="badge bg-slate-100 text-slate-700 border text-xs">Total: <?= e($kpis['total_unidades'] ?? 0) ?> Unidades</span>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <!-- Barra visual de proporción de cartera -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between text-xs font-bold text-muted mb-1.5">
                                    <span class="text-emerald-700">Solvencia: <?= e($kpis['tasa_solvencia'] ?? 0) ?>%</span>
                                    <span class="text-danger">Morosidad: <?= e($kpis['tasa_morosidad'] ?? 0) ?>%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-3 flex overflow-hidden border border-slate-200">
                                    <div class="bg-emerald-500 h-full transition-all duration-500" style="width: <?= e($kpis['tasa_solvencia'] ?? 0) ?>%;" title="Solventes: <?= e($kpis['tasa_solvencia'] ?? 0) ?>%"></div>
                                    <div class="bg-red-500 h-full transition-all duration-500" style="width: <?= e($kpis['tasa_morosidad'] ?? 0) ?>%;" title="Morosos: <?= e($kpis['tasa_morosidad'] ?? 0) ?>%"></div>
                                </div>
                            </div>

                            <!-- Desglose de unidades -->
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="p-3 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                                            <span class="text-xs font-bold text-emerald-900">Unidades Solventes</span>
                                        </div>
                                        <h4 class="fw-bold text-emerald-800 mb-0"><?= e($kpis['unidades_solventes'] ?? 0) ?></h4>
                                        <small class="text-emerald-600 text-xs font-semibold">Al día con sus cuotas</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-2xl bg-red-50/60 border border-red-100">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="material-symbols-outlined text-red-600 text-lg">warning</span>
                                            <span class="text-xs font-bold text-red-900">Unidades Deudoras</span>
                                        </div>
                                        <h4 class="fw-bold text-danger mb-0"><?= e($kpis['unidades_morosas'] ?? 0) ?></h4>
                                        <small class="text-red-600 text-xs font-semibold">Con saldo pendiente</small>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-top mt-4 d-flex justify-content-between align-items-center">
                                <span class="text-muted text-xs">Deuda en mora: <strong class="text-danger"><?= e(formatearMoneda($kpis['total_deuda'] ?? 0)) ?></strong></span>
                                <a href="/admin/reportes/morosidad" class="btn btn-sm btn-outline-secondary rounded-xl text-xs font-bold px-3 py-1.5 d-inline-flex align-items-center gap-1">
                                    <span>Ver Cartas de Deuda</span>
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Distribución de Egresos por Categoría -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-header bg-white py-3.5 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-primary fs-5">category</span>
                                <h5 class="fw-bold mb-0 text-dark fs-6">Egresos por Categoría (<?= e(nombreMes($mes)) ?>)</h5>
                            </div>
                            <span class="text-xs text-muted font-medium"><?= e($anio) ?></span>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <?php if (empty($gastos_por_categoria) || ($resumen['total_gastos_mes'] ?? 0) <= 0): ?>
                                <div class="text-center py-6 text-muted">
                                    <span class="material-symbols-outlined fs-1 text-slate-300 mb-2">receipt_long</span>
                                    <p class="small fw-semibold mb-0">No hay egresos registrados en el período <?= e(nombreMes($mes)) ?> <?= e($anio) ?>.</p>
                                    <span class="text-xs text-muted">Los gastos cargados en el módulo se reflejarán automáticamente aquí.</span>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($gastos_por_categoria as $cat): ?>
                                        <?php if (floatval($cat['total_monto']) > 0): ?>
                                            <?php 
                                                $pct = ($resumen['total_gastos_mes'] ?? 0) > 0 
                                                    ? round((floatval($cat['total_monto']) / $resumen['total_gastos_mes']) * 100, 1) 
                                                    : 0;
                                            ?>
                                            <div>
                                                <div class="d-flex justify-content-between align-items-center text-xs mb-1">
                                                    <span class="fw-bold text-dark d-flex align-items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-sm text-primary"><?= e($cat['icono'] ?: 'label') ?></span>
                                                        <?= e($cat['categoria_nombre']) ?>
                                                    </span>
                                                    <span class="font-mono font-bold text-slate-700">
                                                        <?= e(formatearMoneda($cat['total_monto'])) ?> 
                                                        <span class="text-muted font-normal">(<?= e($pct) ?>%)</span>
                                                    </span>
                                                </div>
                                                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                                                    <div class="bg-primary h-full rounded-full" style="width: <?= e($pct) ?>%;"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="pt-3 border-top mt-4 d-flex justify-content-between align-items-center">
                                <span class="text-muted text-xs">Total egresos mes: <strong class="text-primary"><?= e(formatearMoneda($resumen['total_gastos_mes'] ?? 0)) ?></strong></span>
                                <a href="/admin/gastos" class="btn btn-sm btn-outline-secondary rounded-xl text-xs font-bold px-3 py-1.5 d-inline-flex align-items-center gap-1">
                                    <span>Gestionar Gastos</span>
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila 3: Cuentas Bancarias & Últimos Movimientos Procesados (Solo Lectura) -->
            <div class="row g-4">
                <!-- Columna Izquierda: Cuentas Bancarias Autorizadas -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-header bg-white py-3.5 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-primary fs-5">account_balance</span>
                                <h5 class="fw-bold mb-0 text-dark fs-6">Cuentas Bancarias Autorizadas</h5>
                            </div>
                            <span class="badge bg-slate-100 text-slate-700 border text-xs"><?= count($cuentas_bancarias) ?> Activas</span>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($cuentas_bancarias)): ?>
                                <div class="text-center py-6 text-muted">
                                    <span class="material-symbols-outlined fs-1 text-slate-300 mb-2">account_balance</span>
                                    <p class="small fw-semibold mb-0">No hay cuentas bancarias activas configuradas.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($cuentas_bancarias as $cta): ?>
                                        <div class="p-3 rounded-2xl bg-slate-50/80 border border-slate-200">
                                            <div class="d-flex justify-content-between align-items-start mb-1.5">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="material-symbols-outlined text-primary text-base">payments</span>
                                                    <h6 class="fw-bold mb-0 text-dark text-xs"><?= e($cta['banco']) ?></h6>
                                                </div>
                                                <span class="badge bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full">Receptora</span>
                                            </div>
                                            <div class="text-xs text-muted space-y-1">
                                                <div><strong>Nro:</strong> <span class="font-mono"><?= e($cta['numero_cuenta']) ?></span> (<?= e(ucfirst($cta['tipo_cuenta'])) ?>)</div>
                                                <div><strong>Titular:</strong> <?= e($cta['titular']) ?> - <?= e($cta['tipo_identificacion']) ?>-<?= e($cta['identificacion']) ?></div>
                                                <?php if (!empty($cta['telefono_pago_movil'])): ?>
                                                    <div><strong>Pago Móvil:</strong> <span class="font-mono"><?= e($cta['telefono_pago_movil']) ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="pt-3 border-top mt-4 text-end">
                                <a href="/admin/cuentas-bancarias" class="btn btn-sm btn-outline-secondary rounded-xl text-xs font-bold px-3 py-1.5 d-inline-flex align-items-center gap-1">
                                    <span>Administrar Cuentas</span>
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Últimos Movimientos Procesados (Solo Lectura) -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                        <div class="card-header bg-white py-3.5 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <div>
                                <h5 class="text-lg font-bold text-on-surface mb-0 d-flex align-items-center gap-2">
                                    <span class="material-symbols-outlined text-primary fs-5">history</span>
                                    Últimos Movimientos Financieros
                                </h5>
                                <span class="text-xs text-on-surface-variant">Registro de pagos verificados o procesados (solo lectura)</span>
                            </div>
                            <a href="/admin/comprobantes" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5">
                                <span>Ver Historial</span>
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($ultimos_comprobantes)): ?>
                                <div class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-2">history</span>
                                    <p class="small fw-semibold mb-0">No hay movimientos financieros registrados recientemente.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="w-full text-left text-sm border-collapse">
                                        <thead>
                                            <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                                <th class="py-3 px-4">Residente</th>
                                                <th class="py-3 px-4">Monto</th>
                                                <th class="py-3 px-4 text-end">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-background">
                                            <?php foreach ($ultimos_comprobantes as $c): ?>
                                                <tr class="hover:bg-background/40 transition-colors">
                                                    <td class="py-4 px-4">
                                                        <div class="font-semibold text-on-surface text-xs"><?= e($c['residente']) ?></div>
                                                        <?php if (!empty($c['cedula'])): ?>
                                                            <div class="text-[11px] text-on-surface-variant"><?= e($c['cedula']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-4 px-4 font-bold text-on-surface text-xs"><?= e(formatearMoneda($c['monto'])) ?></td>
                                                    <td class="py-4 px-4 text-end">
                                                        <?= badgeEstado($c['estado']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
