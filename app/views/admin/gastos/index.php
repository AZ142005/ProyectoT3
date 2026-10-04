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
                <h1 class="text-xl font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">receipt_long</span>
                    <span>Gastos y Facturación</span>
                </h1>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- ESTILOS ESPECÍFICOS PARA PESTAÑAS -->
                <style>
                .nav-pills .nav-link {
                    color: #475569;
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    transition: all 0.2s ease-in-out;
                }
                .nav-pills .nav-link:hover:not(.active) {
                    background-color: #f1f5f9;
                    color: #0f172a;
                }
                .nav-pills .nav-link.active {
                    background-color: #27ae60 !important;
                    border-color: #27ae60 !important;
                    color: #ffffff !important;
                    box-shadow: 0 4px 6px -1px rgba(39, 174, 96, 0.25);
                }
                </style>

                <!-- NAVEGACIÓN EN DOS PESTAÑAS: REGISTRO DE GASTOS VS EMISIÓN DE FACTURAS -->
                <div class="bg-white rounded-2xl border border-outline-variant p-2 shadow-sm mb-4">
                    <ul class="nav nav-pills nav-fill gap-2" id="gastosTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($tabActual === 'gastos') ? 'active' : '' ?> py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2"
                                    id="tab-gastos-btn"
                                    data-bs-toggle="pill"
                                    data-bs-target="#tab-gastos"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-gastos"
                                    aria-selected="<?= ($tabActual === 'gastos') ? 'true' : 'false' ?>">
                                <span class="material-symbols-outlined text-[20px]">receipt</span>
                                <span>Registro de Gastos</span>
                                <span class="badge bg-slate-200 text-slate-700 rounded-full px-2 py-0.5 text-xs"><?= e($paginacion['total']) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($tabActual === 'facturacion') ? 'active' : '' ?> py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2"
                                    id="tab-facturacion-btn"
                                    data-bs-toggle="pill"
                                    data-bs-target="#tab-facturacion"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-facturacion"
                                    aria-selected="<?= ($tabActual === 'facturacion') ? 'true' : 'false' ?>">
                                <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                                <span>Emisión de Facturas</span>
                                <?php if ($facturas_existentes > 0): ?>
                                    <span class="badge bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full px-2 py-0.5 text-xs"><?= e($facturas_existentes) ?> generadas</span>
                                <?php else: ?>
                                    <span class="badge bg-amber-100 text-amber-800 border border-amber-300 rounded-full px-2 py-0.5 text-xs">Pendiente</span>
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="gastosTabsContent">
                    <!-- PESTAÑA 1: REGISTRO DE GASTOS -->
                    <div class="tab-pane fade <?= ($tabActual === 'gastos') ? 'show active' : '' ?>" id="tab-gastos" role="tabpanel" aria-labelledby="tab-gastos-btn">

    <!-- Barra de Acciones del Contenido -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-lg font-bold text-on-surface mb-1">Registro de Gastos</h4>
            <p class="text-xs text-on-surface-variant mb-0">Control de facturas, tipología de egresos (comunes/individuales) y soporte digital</p>
        </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/admin/gastos/maestro?mes=<?= e($filtros['mes']) ?>&anio=<?= e($filtros['anio']) ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                    <span>Ingesta PDF Maestro</span>
                </a>
                <button type="button" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    <span>Nuevo Gasto</span>
                </button>
            </div>
    </div>

    <!-- Barra de Filtros Ultra-Compacta (Toolbar Permanente) -->
    <div class="bg-white rounded-2xl border border-outline-variant p-2.5 shadow-sm mb-4">
        <form method="GET" action="/admin/gastos" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="gastos">
            <div class="flex items-center gap-1 text-primary shrink-0 pe-2 border-r border-slate-100 hidden sm:flex">
                <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Filtros</span>
            </div>

            <!-- Mes -->
            <div class="w-full sm:w-auto">
                <select name="mes" id="mes" aria-label="Mes" class="w-full sm:w-auto px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= e($m) ?>" <?= $m === intval($filtros['mes']) ? 'selected' : '' ?>><?= e(nombreMes($m)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Año -->
            <div class="w-full sm:w-24">
                <input type="number" name="anio" id="anio" value="<?= e($filtros['anio']) ?>" placeholder="Año" aria-label="Año" title="Año"
                       class="w-full px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
            </div>

            <!-- Categoría -->
            <div class="flex-1 sm:flex-initial min-w-[160px]">
                <select name="categoria_id" id="categoria_id" aria-label="Categoría" class="w-full px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                    <option value="">Todas las Categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= e($cat['id']) ?>" <?= (!empty($filtros['categoria_id']) && intval($filtros['categoria_id']) === intval($cat['id'])) ? 'selected' : '' ?>><?= e($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tipo de Gasto -->
            <div class="flex-1 sm:flex-initial min-w-[150px]">
                <select name="tipo_gasto" id="tipo_gasto" aria-label="Tipo de Gasto" class="w-full px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                    <option value="">Todos los tipos</option>
                    <option value="comun" <?= ($filtros['tipo_gasto'] ?? '') === 'comun' ? 'selected' : '' ?>>Común (Global)</option>
                    <option value="individual" <?= ($filtros['tipo_gasto'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual (Por Edificio)</option>
                </select>
            </div>

            <!-- Botones de Acción Inline -->
            <div class="flex items-center gap-1.5 ms-auto">
                <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-3 py-1.5 rounded-xl shadow-sm text-xs transition-all flex items-center justify-center gap-1 active:scale-95">
                    <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                    Filtrar
                </button>
                <a href="/admin/gastos?tab=gastos" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-1.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1" title="Limpiar filtros">
                    <span class="material-symbols-outlined text-[16px]">clear_all</span>
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Resumen de Totales por Categoría -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Total Gastos del Mes</span>
                        <span class="h3 fw-bold text-dark mb-0"><?= e(formatearMoneda($totalMes)) ?></span>
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
                            <span class="h4 fw-bold text-dark mb-0"><?= e(formatearMoneda($tc['total_monto'])) ?></span>
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

    <!-- Tabla de Gastos -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="text-lg font-bold text-on-surface mb-0">Historial de Gastos del Período</h5>
            <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacion['total']) ?> Gastos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Categoría</th>
                            <th class="py-3 px-4">Tipo / Alcance</th>
                            <th class="py-3 px-4">Proveedor / Nro. Factura</th>
                            <th class="py-3 px-4">Descripción</th>
                            <th class="py-3 px-4 text-center">Fecha Gasto</th>
                            <th class="py-3 px-4 text-end">Monto Total</th>
                            <th class="py-3 px-4 text-center">Soporte Digital</th>
                            <th class="py-3 px-4 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php if (empty($gastos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">receipt_long</span>
                                    No hay gastos registrados para este período.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gastos as $g): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4">
                                        <span class="badge rounded-pill px-3 py-1 text-white" style="background-color: <?= e($g['categoria_color']) ?>;">
                                            <span class="material-symbols-outlined align-middle fs-6 me-1"><?= e($g['categoria_icono']) ?></span>
                                            <?= e($g['categoria_nombre']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (($g['tipo_gasto'] ?? 'comun') === 'individual'): ?>
                                            <span class="badge bg-purple-100 text-purple-800 border border-purple-200 rounded-pill px-2.5 py-1 text-xs font-semibold" style="background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe;">
                                                <span class="material-symbols-outlined align-middle text-sm me-0.5">apartment</span>
                                                Individual: <?= e($g['edificio_nombre'] ?? 'Torre') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-blue-50 text-blue-700 border border-blue-200 rounded-pill px-2.5 py-1 text-xs font-semibold">
                                                <span class="material-symbols-outlined align-middle text-sm me-0.5">public</span>
                                                Común (Global)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-on-surface"><?= e($g['proveedor']) ?></div>
                                        <small class="text-on-surface-variant">Fac: <?= e($g['nro_factura_proveedor'] ?: 'S/N') ?></small>
                                    </td>
                                    <td class="py-4 px-4 text-xs text-on-surface-variant" style="max-width: 250px;">
                                        <?= e($g['descripcion']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-center text-xs"><?= e(date('d/m/Y', strtotime($g['fecha_gasto']))) ?></td>
                                    <td class="py-4 px-4 text-end font-bold text-on-surface">
                                        <?= e(formatearMoneda($g['monto_total'])) ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if (!empty($g['soporte_digital'])): ?>
                                            <a href="/uploads/soportes/<?= e($g['soporte_digital']) ?>" target="_blank" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                                <span class="material-symbols-outlined fs-6">visibility</span> Ver Doc
                                            </a>
                                            <?php if (!empty($g['pagina_soporte'])): ?>
                                                <span class="badge bg-light text-primary border border-primary-subtle d-block mt-1" style="font-size: 11px;">
                                                    Pág. <?= e($g['pagina_soporte']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white">Sin Soporte</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-end">
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

                    <!-- PESTAÑA 2: EMISIÓN DE FACTURAS -->
                    <div class="tab-pane fade <?= ($tabActual === 'facturacion') ? 'show active' : '' ?>" id="tab-facturacion" role="tabpanel" aria-labelledby="tab-facturacion-btn">
                        <!-- Barra de Acciones del Contenido -->
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <div>
                                <h4 class="text-lg font-bold text-on-surface mb-1">Emisión de Facturación Mensual</h4>
                                <p class="text-xs text-on-surface-variant mb-0">Cálculo dinámico de cuotas condominales y distribución entre las unidades del conjunto</p>
                            </div>
                        </div>

                        <!-- Tarjeta de Generación Masiva -->
                        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-6">
                            <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                                <h3 class="text-lg font-bold text-on-surface flex items-center gap-2 mb-0">
                                    <span class="material-symbols-outlined text-primary">receipt_long</span>
                                    <span>Resumen y Generación de Cuotas</span>
                                </h3>
                                <span class="badge bg-slate-100 text-slate-700 border px-3 py-1.5 rounded-xl font-bold text-xs">
                                    Período: <?= nombreMes($mes) ?> <?= e($anio) ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 bg-background/50 p-6 rounded-2xl border border-outline-variant mb-6">
                                <div class="flex flex-col gap-1 text-center md:text-left">
                                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Período</span>
                                    <span class="text-lg font-bold text-on-surface mt-1"><?= nombreMes($mes) ?> <?= e($anio) ?></span>
                                </div>

                                <div class="flex flex-col gap-1 text-center md:text-left border-y md:border-y-0 md:border-x border-outline-variant py-4 md:py-0 md:px-4">
                                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Gastos Declarados</span>
                                    <span class="text-lg font-bold text-primary mt-1"><?= formatearMoneda($totalGastosMes ?? 0) ?></span>
                                </div>

                                <div class="flex flex-col gap-1 text-center md:text-left border-b md:border-b-0 md:border-r border-outline-variant pb-4 md:pb-0 md:pr-4">
                                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Cuota Base Común</span>
                                    <span class="text-lg font-bold text-emerald-700 mt-1"><?= formatearMoneda($distribucion['cuota_global_unidad'] ?? 0) ?></span>
                                </div>

                                <div class="flex flex-col gap-1 text-center md:text-left">
                                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Facturas Creadas</span>
                                    <span class="text-lg font-bold mt-1 <?= $facturas_existentes > 0 ? 'text-primary' : 'text-on-surface-variant/60' ?>">
                                        <?= $facturas_existentes > 0 ? e($facturas_existentes) . ' creadas' : 'Ninguna creada' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Resumen de Cálculo Dinámico de Deudas -->
                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-6 text-xs text-slate-700">
                                <div class="flex items-center gap-2 mb-2 font-bold text-slate-800 text-sm">
                                    <span class="material-symbols-outlined text-primary text-base">calculate</span>
                                    <span>Cálculo Dinámico de Cuotas a Facturar</span>
                                </div>
                                <p class="mb-2 leading-relaxed text-slate-600">
                                    La deuda de cada unidad no es fija: se calcula automáticamente como la suma de la <strong>Fracción Global</strong> (gastos comunes divididos entre las <?= e($distribucion['total_unidades'] ?? count($unidades)) ?> unidades activas) más la <strong>Fracción Individual</strong> de su edificio (si la torre tuvo gastos específicos registrados).
                                </p>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="badge bg-white text-slate-700 border px-3 py-2 rounded-xl">
                                        Gastos Comunes Globales: <strong><?= formatearMoneda($distribucion['total_global'] ?? 0) ?></strong> (<?= formatearMoneda($distribucion['cuota_global_unidad'] ?? 0) ?> / unidad)
                                    </span>
                                    <?php if (!empty($distribucion['edificios'])): ?>
                                        <?php foreach ($distribucion['edificios'] as $ed): ?>
                                            <?php if (($ed['total_gastos_individual'] ?? 0) > 0): ?>
                                                <span class="badge bg-purple-50 text-purple-900 border border-purple-200 px-3 py-2 rounded-xl">
                                                    Gastos Edificio #<?= e($ed['edificio_id']) ?>: <strong><?= formatearMoneda($ed['total_gastos_individual']) ?></strong> (+<?= formatearMoneda($ed['cuota_individual_unidad']) ?> / unidad)
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Advertencia o info de conciliación -->
                            <?php if ($facturas_existentes > 0): ?>
                                <div class="bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[22px] text-yellow-700 shrink-0">warning</span>
                                    <div>
                                        <p class="font-bold">Facturas ya generadas</p>
                                        <p class="mt-0.5 text-xs text-yellow-600/90 leading-relaxed">
                                            Ya se han generado las facturas para el mes actual de <strong><?= nombreMes($mes) ?> <?= e($anio) ?></strong>. Si decides presionar el botón "Re-generar Facturas", se recalculará y duplicará la facturación mensual para los residentes. Utiliza esta acción únicamente si eliminaste las facturas anteriores.
                                        </p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[22px] text-blue-700 shrink-0">info</span>
                                    <div>
                                        <p class="font-bold">Proceso Automatizado de Conciliación</p>
                                        <p class="mt-0.5 text-xs text-blue-600/90 leading-relaxed">
                                            Al generar las facturas, el sistema buscará de manera automática cualquier saldo a favor de meses anteriores (facturas con saldos negativos) para cada unidad condominal, y lo aplicará como abono a la cuota del presente mes. Si el saldo a favor cubre el total de la cuota, la nueva factura nacerá marcada en estado <strong>Pagada</strong>.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                                <form method="POST" action="/admin/gastos/generar-facturas" class="flex justify-center items-center pt-4 border-t border-background">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="mes" value="<?= e($mes) ?>">
                                    <input type="hidden" name="anio" value="<?= e($anio) ?>">
                                    <button type="submit" name="generar" value="1"
                                            class="font-bold px-8 py-3.5 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1.5 text-sm <?= $facturas_existentes > 0 ? 'bg-slate-200 text-slate-700 hover:bg-slate-300' : 'bg-primary hover:bg-primary-hover text-white' ?>">
                                        <span class="material-symbols-outlined">autorenew</span>
                                        <?= $facturas_existentes > 0 ? 'Re-generar Facturas' : 'Generar Facturas del Mes' ?>
                                    </button>
                                </form>
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
                        Registrar Gasto del Condominio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Tipología de Gasto -->
                    <div class="row g-3 mb-3 bg-light p-2.5 rounded-3 border border-slate-200">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-dark">Tipo / Alcance del Gasto <span class="text-danger">*</span></label>
                            <select name="tipo_gasto" id="modal_tipo_gasto" required class="form-select" onchange="toggleEdificioModal(this.value)">
                                <option value="comun">Gasto Común (Global - Todo el Condominio)</option>
                                <option value="individual">Gasto Individual (Afecta a un solo Edificio)</option>
                            </select>
                            <div class="form-text small text-muted">Los comunes se dividen entre todas las unidades; los individuales solo entre las del edificio.</div>
                        </div>
                        <div class="col-md-6" id="modal_edificio_container" style="display: none;">
                            <label class="form-label fw-bold small text-dark">Edificio / Torre Afectada <span class="text-danger">*</span></label>
                            <select name="edificio_id" id="modal_edificio_id" class="form-select">
                                <option value="">Seleccione un edificio...</option>
                                <?php foreach ($edificios as $ed): ?>
                                    <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

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
                    <button type="submit" class="btn btn-primary fw-bold">Guardar Gasto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleEdificioModal(tipo) {
    const container = document.getElementById('modal_edificio_container');
    const selectEd = document.getElementById('modal_edificio_id');
    if (tipo === 'individual') {
        container.style.display = 'block';
        selectEd.setAttribute('required', 'required');
    } else {
        container.style.display = 'none';
        selectEd.removeAttribute('required');
        selectEd.value = '';
    }
}

document.querySelectorAll('#gastosTabs button[data-bs-toggle="pill"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function(e) {
        const target = e.target.getAttribute('data-bs-target');
        const tab = target === '#tab-facturacion' ? 'facturacion' : 'gastos';
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    });
});
</script>

            </div>
        </div>
    </div>
</div>
