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
                    <span>Carga y Emisión</span>
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

                <!-- SECCIÓN 1: REGISTRO DE GASTOS -->
                <div id="seccion-gastos">

    <!-- Barra de Acciones del Contenido -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-lg font-bold text-on-surface mb-1">Registro de Gastos</h4>
            <p class="text-xs text-on-surface-variant mb-0">Control de egresos (comunes/individuales) y soporte digital</p>
        </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/admin/cartelera" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                    <span>Cargar Reporte de Cartelera</span>
                </a>
                <button type="button" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    <span>Nuevo Gasto</span>
                </button>
            </div>
    </div>

    <!-- Historial de Reportes -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="text-lg font-bold text-on-surface mb-0">Historial de Reportes</h5>
                <small class="text-on-surface-variant">Se muestran las cargas del último mes</small>
            </div>
            <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacion['total']) ?> Reportes</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Reporte</th>
                            <th class="py-3 px-4 text-center">Fecha de Carga</th>
                            <th class="py-3 px-4 text-center">Fecha del Reporte</th>
                            <th class="py-3 px-4">Resultado</th>
                            <th class="py-3 px-4">Cargado por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php if (empty($historial)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">description</span>
                                    No hay reportes cargados en el último mes.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historial as $r): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-on-surface flex items-center gap-2">
                                            <span class="material-symbols-outlined text-primary text-[18px]">picture_as_pdf</span>
                                            <?= e($r['nombre_original'] ?: $r['archivo']) ?>
                                        </div>
                                        <?php if (!empty($r['archivo_existe'])): ?>
                                            <a href="/soporte-proxy.php?file=<?= urlencode($r['archivo']) ?>" target="_blank" class="text-xs text-primary font-semibold inline-flex items-center gap-1 mt-1">
                                                <span class="material-symbols-outlined text-[14px]">visibility</span> Ver PDF
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-center text-xs"><?= e(date('d/m/Y H:i', strtotime($r['fecha_carga']))) ?></td>
                                    <td class="py-4 px-4 text-center text-xs"><?= e($r['fecha_emision'] ? date('d/m/Y', strtotime($r['fecha_emision'])) : '—') ?></td>
                                    <td class="py-4 px-4">
                                        <span class="badge bg-blue-50 text-blue-700 border border-blue-200 rounded-pill px-2.5 py-1 text-xs font-semibold"><?= e($r['facturas_creadas']) ?> facturas</span>
                                        <?php if (!empty($r['facturas_actualizadas'])): ?>
                                            <span class="badge bg-slate-100 text-slate-700 border rounded-pill px-2.5 py-1 text-xs font-semibold"><?= e($r['facturas_actualizadas']) ?> actualizadas</span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['facturas_omitidas'])): ?>
                                            <span class="badge bg-amber-100 text-amber-800 border border-amber-200 rounded-pill px-2.5 py-1 text-xs font-semibold"><?= e($r['facturas_omitidas']) ?> omitidas</span>
                                        <?php endif; ?>
                                        <?php
                                        $nuevos = [];
                                        if (!empty($r['edificios_creados'])) { $nuevos[] = $r['edificios_creados'] . ' edificios'; }
                                        if (!empty($r['unidades_creadas'])) { $nuevos[] = $r['unidades_creadas'] . ' unidades'; }
                                        if (!empty($nuevos)):
                                        ?>
                                            <small class="d-block text-on-surface-variant mt-1"><?= e(implode(' · ', $nuevos)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e($r['usuario_nombre'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top bg-light">
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        </div>
    </div>
</div>

                    <!-- SECCIÓN 2: EMISIÓN DE DEUDA -->
                    <div id="seccion-emision" class="mt-5 pt-5 border-top border-background">
                        <!-- Barra de Acciones del Contenido -->
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <div>
                                <h4 class="text-lg font-bold text-on-surface mb-1">Emisión de Deuda Mensual</h4>
                            </div>
                        </div>

                        <!-- Tarjeta de Emisión Masiva -->
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

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-background/50 p-6 rounded-2xl border border-outline-variant mb-6">
                                <div class="flex flex-col gap-1 text-center md:text-left">
                                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Período</span>
                                    <span class="text-lg font-bold text-on-surface mt-1"><?= nombreMes($mes) ?> <?= e($anio) ?></span>
                                </div>

                                <div class="flex flex-col gap-1 text-center md:text-left border-y md:border-y-0 md:border-x border-outline-variant py-4 md:py-0 md:px-4">
                                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Deudas Creadas</span>
                                    <span class="text-lg font-bold mt-1 <?= $facturas_existentes > 0 ? 'text-primary' : 'text-on-surface-variant/60' ?>">
                                        <?= $facturas_existentes > 0 ? e($facturas_existentes) . ' creadas' : 'Ninguna creada' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Advertencia o info de conciliación -->
                            <?php if ($facturas_existentes > 0): ?>
                                <div class="bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[22px] text-yellow-700 shrink-0">warning</span>
                                    <div>
                                        <p class="font-bold">Deudas ya enviadas</p>
                                        <p class="mt-0.5 text-xs text-yellow-600/90 leading-relaxed">
                                            Ya se han enviado las deudas de <strong><?= nombreMes($mes) ?> <?= e($anio) ?></strong>. Si presionas «Re-enviar Deuda», el sistema recalculará y <strong>actualizará</strong> las facturas del período (no se duplican): se conserva lo ya pagado y se aplican las deudas manuales guardadas.
                                        </p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[22px] text-blue-700 shrink-0">info</span>
                                    <div>
                                        <p class="font-bold">Proceso Automatizado de Conciliación</p>
                                        <p class="mt-0.5 text-xs text-blue-600/90 leading-relaxed">
                                            Al enviar las deudas, el sistema buscará de manera automática cualquier saldo a favor de meses anteriores (saldos negativos) para cada unidad del conjunto, y lo aplicará como abono a la cuota del presente mes. Si el saldo a favor cubre el total de la cuota, la nueva deuda nacerá marcada en estado <strong>Pagada</strong>.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($cartelera_existentes)): ?>
                                <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[22px] text-blue-700 shrink-0">info</span>
                                    <div>
                                        <p class="font-bold">Deudas de cartelera del período</p>
                                        <p class="mt-0.5 text-xs text-blue-600/90 leading-relaxed">
                                            Este período tiene <?= e($cartelera_existentes) ?> deudas de cartelera cargadas (importadas del reporte). No se tocan ni cuentan como deudas emitidas del sistema.<?php if (empty($facturas_existentes)): ?> Al presionar «Enviar Deuda» se emitirán además las cuotas/deudas manuales del sistema para este período.<?php endif; ?>
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
                                        <?= $facturas_existentes > 0 ? 'Re-enviar Deuda' : 'Enviar Deuda' ?>
                                    </button>
                                </form>
                        </div>

                        <!-- Tarjeta de Deudas Manuales del período -->
                        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-6">
                            <div class="flex justify-between items-center pb-4 border-b border-background mb-4">
                                <h3 class="text-lg font-bold text-on-surface flex items-center gap-2 mb-0">
                                    <span class="material-symbols-outlined text-primary">edit_note</span>
                                    <span>Deudas manuales — <?= nombreMes($mes) ?> <?= e($anio) ?></span>
                                </h3>
                                <span class="badge bg-slate-100 text-slate-700 border px-3 py-1.5 rounded-xl font-bold text-xs">
                                    <?= count($deudasManuales) ?> unidades
                                </span>
                            </div>

                            <?php if (empty($deudasManuales)): ?>
                                <p class="text-sm text-on-surface-variant mb-0">No hay deudas manuales guardadas para este período. Usa «Nuevo Gasto» → «Deuda manual por unidad» para cargarlas: se aplicarán al enviar/re-enviar la deuda del mes.</p>
                            <?php else: ?>
                                <?php $totalDeudasManuales = 0.0; ?>
                                <div class="table-responsive">
                                    <table class="w-full text-left text-sm border-collapse">
                                        <thead>
                                            <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                                <th class="py-2 px-3">Edificio</th>
                                                <th class="py-2 px-3">Unidad</th>
                                                <th class="py-2 px-3 text-right">Monto por unidad</th>
                                                <th class="py-2 px-3 text-center">Quitar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-background">
                                            <?php foreach ($deudasManuales as $dm): ?>
                                                <?php $totalDeudasManuales += floatval($dm['monto']); ?>
                                                <tr class="hover:bg-background/40 transition-colors">
                                                    <td class="py-2 px-3"><?= e($dm['edificio_nombre'] ?: 'Sin edificio') ?></td>
                                                    <td class="py-2 px-3 font-semibold">Apto <?= e($dm['unidad_numero']) ?></td>
                                                    <td class="py-2 px-3 text-right font-bold"><?= formatearMoneda($dm['monto']) ?></td>
                                                    <td class="py-2 px-3 text-center">
                                                        <form method="POST" action="/admin/gastos/deuda-manual/eliminar" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="unidad_id" value="<?= e($dm['unidad_id']) ?>">
                                                            <input type="hidden" name="mes" value="<?= e($mes) ?>">
                                                            <input type="hidden" name="anio" value="<?= e($anio) ?>">
                                                            <button type="submit" class="text-red-600 hover:text-red-700" title="Quitar deuda manual de esta unidad">
                                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-4">
                                    <p class="text-sm font-bold text-on-surface mb-0">Total del período: <?= formatearMoneda($totalDeudasManuales) ?></p>
                                    <form method="POST" action="/admin/gastos/deuda-manual/limpiar" onsubmit="return confirm('¿Eliminar todas las deudas manuales de <?= nombreMes($mes) ?> <?= e($anio) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="mes" value="<?= e($mes) ?>">
                                        <input type="hidden" name="anio" value="<?= e($anio) ?>">
                                        <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-4 py-2.5 rounded-xl text-xs border border-red-200 transition-all inline-flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                                            <span>Limpiar período</span>
                                        </button>
                                    </form>
                                </div>

                                <?php if ($facturas_existentes > 0): ?>
                                    <div class="bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-2xl p-3 text-xs mt-4 flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[18px] text-yellow-700 shrink-0">warning</span>
                                        <span>Hay facturas emitidas para este período: presiona Re-enviar Deuda para aplicar los cambios.</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

<!-- Modal para Registrar Nuevo Gasto -->
<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined">receipt</span>
                    Registrar Gasto o Deuda Manual
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pb-0">
                <!-- Tipo de registro -->
                <div class="row g-3 mb-3 bg-background p-2.5 rounded-xl border border-outline-variant">
                    <div class="col-md-12">
                        <label class="form-label fw-bold small text-on-surface-variant">Tipo de registro <span class="text-danger">*</span></label>
                        <select id="modal_tipo_registro" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer" onchange="toggleTipoRegistroModal(this.value)">
                            <option value="deuda_manual" selected>Deuda manual por unidad</option>
                            <option value="gasto">Gasto del condominio</option>
                        </select>
                        <div class="text-xs text-on-surface-variant">La deuda manual se aplica directo a las unidades elegidas (sin repartir); el gasto del condominio se prorratea entre las unidades al emitir la deuda del mes.</div>
                    </div>
                </div>
            </div>

            <!-- MODO DEUDA MANUAL -->
            <form id="formDeudaManual" method="POST" action="/admin/gastos/deuda-manual" data-modo="deuda_manual">
                <?= csrf_field() ?>
                <div class="modal-body p-4 pt-2">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Alcance <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="alcance" id="alcance_seleccion" value="seleccion" checked onchange="toggleAlcanceDeudaManual()">
                                    <label class="form-check-label small fw-bold" for="alcance_seleccion">Selección libre</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="alcance" id="alcance_todo" value="todo" onchange="toggleAlcanceDeudaManual()">
                                    <label class="form-check-label small fw-bold" for="alcance_todo">Todo el condominio</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-on-surface-variant">Monto por unidad (Bs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="999999.99" name="monto_unidad" id="monto_unidad" required placeholder="0.00" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors" oninput="actualizarContadorDeudaManual()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-on-surface-variant">Mes <span class="text-danger">*</span></label>
                            <select name="mes" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= e($m) ?>" <?= $m === intval($mes) ? 'selected' : '' ?>><?= e($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-on-surface-variant">Año <span class="text-danger">*</span></label>
                            <input type="number" name="anio" value="<?= e($anio) ?>" min="2000" max="2100" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        </div>
                    </div>

                    <?php
                    $unidadesPorEdificioDeuda = [];
                    foreach ($unidades ?? [] as $u) {
                        $edIdGrupo = intval($u['edificio_id'] ?? 0);
                        $unidadesPorEdificioDeuda[$edIdGrupo][] = $u;
                    }
                    ?>
                    <div class="mb-3" id="bloquePickerDeudaManual">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <label class="form-label fw-bold small text-on-surface-variant mb-0">Unidades y edificios</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-2 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" onclick="seleccionarTodoDeudaManual(true)">Seleccionar todo</button>
                                <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-2 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" onclick="seleccionarTodoDeudaManual(false)">Limpiar selección</button>
                            </div>
                        </div>
                        <input type="text" id="buscadorUnidadesDeudaManual" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors mb-2" placeholder="Buscar unidad o edificio...">
                        <div class="max-h-64 overflow-y-auto p-3 bg-background rounded-xl border border-outline-variant" id="contenedorUnidadesDeudaManual">
                            <?php if (empty($unidadesPorEdificioDeuda)): ?>
                                <p class="text-xs text-on-surface-variant mb-0">No hay unidades activas registradas.</p>
                            <?php else: ?>
                                <?php foreach ($unidadesPorEdificioDeuda as $edIdGrupo => $unidadesGrupo): ?>
                                    <?php $nombreGrupo = !empty($unidadesGrupo[0]['edificio_nombre']) ? $unidadesGrupo[0]['edificio_nombre'] : 'Sin edificio'; ?>
                                    <div class="deuda-grupo py-2">
                                        <?php if (intval($edIdGrupo) > 0): ?>
                                            <label class="d-flex align-items-center gap-2 mb-1" style="cursor: pointer;">
                                                <input type="checkbox" name="edificios[]" value="<?= e($edIdGrupo) ?>" class="form-check-input m-0 deuda-check-edificio">
                                                <span class="text-xs font-bold text-on-surface-variant uppercase"><?= e($nombreGrupo) ?> (todo el edificio)</span>
                                            </label>
                                        <?php else: ?>
                                            <p class="text-xs font-bold text-on-surface-variant uppercase mb-1"><?= e($nombreGrupo) ?></p>
                                        <?php endif; ?>
                                        <?php foreach ($unidadesGrupo as $u): ?>
                                            <label class="deuda-unidad-item d-flex align-items-center gap-2 py-1.5 ps-3" style="cursor: pointer;" data-busqueda="<?= e(mb_strtolower($nombreGrupo . ' ' . $u['numero'])) ?>">
                                                <input type="checkbox" name="unidades[]" value="<?= e($u['id']) ?>" class="form-check-input m-0 deuda-check-unidad" data-edificio="<?= e($edIdGrupo) ?>">
                                                <span class="text-sm">Apto <?= e($u['numero']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-on-surface-variant">Marca edificios completos y/o unidades puntuales: se combinan sin duplicarse.</div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-3">
                        <p class="text-sm font-bold text-blue-700 mb-0" id="contadorDeudaManual">0 unidades seleccionadas · Total a facturar Bs. 0,00</p>
                        <p class="text-xs text-blue-600/90 mb-0 mt-1">El monto se aplica completo a cada unidad seleccionada (no se divide entre ellas). Al guardar se reemplaza el monto anterior de esas unidades en el período.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Guardar Deuda Manual
                    </button>
                </div>
            </form>

            <!-- MODO GASTO CLÁSICO -->
            <form id="formRegistrarGasto" method="POST" action="/admin/gastos/guardar" enctype="multipart/form-data" data-modo="gasto" style="display: none;">
                <?= csrf_field() ?>
                <div class="modal-body p-4 pt-2">
                    <!-- Tipología de Gasto -->
                    <div class="row g-3 mb-3 bg-background p-2.5 rounded-xl border border-outline-variant">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Tipo / Alcance del Gasto <span class="text-danger">*</span></label>
                            <select name="tipo_gasto" id="modal_tipo_gasto" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer" onchange="toggleEdificioModal(this.value)">
                                <option value="comun">Gasto Común (Global - Todo el Conjunto)</option>
                                <option value="individual">Gasto Individual (Afecta a un solo Edificio)</option>
                            </select>
                            <div class="text-xs text-on-surface-variant">Los comunes se dividen entre todas las unidades; los individuales solo entre las del edificio.</div>
                        </div>
                        <div class="col-md-6" id="modal_edificio_container" style="display: none;">
                            <label class="form-label fw-bold small text-on-surface-variant">Edificio / Torre Afectada <span class="text-danger">*</span></label>
                            <select name="edificio_id" id="modal_edificio_id" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                                <option value="">Seleccione un edificio...</option>
                                <?php foreach ($edificios as $ed): ?>
                                    <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Categoría de Gasto <span class="text-danger">*</span></label>
                            <select name="categoria_id" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= e($cat['id']) ?>"><?= e($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-on-surface-variant">Mes <span class="text-danger">*</span></label>
                            <select name="mes" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= e($m) ?>" <?= $m === intval(date('n')) ? 'selected' : '' ?>><?= e($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-on-surface-variant">Año <span class="text-danger">*</span></label>
                            <input type="number" name="anio" value="<?= date('Y') ?>" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Proveedor / Empresa <span class="text-danger">*</span></label>
                            <input type="text" name="proveedor" required placeholder="Ej: Hidroeléctrica / Bombas del Centro C.A." class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Nro. Factura Fiscal</label>
                            <input type="text" name="nro_factura_proveedor" placeholder="Ej: FAC-009842" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Monto Total (Bs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="monto_total" required placeholder="0.00" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-on-surface-variant">Fecha del Gasto <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_gasto" value="<?= date('Y-m-d') ?>" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Descripción del Gasto <span class="text-danger">*</span></label>
                        <textarea name="descripcion" rows="3" required placeholder="Describa el trabajo realizado o concepto..." class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Soporte Digital Adjunto (PDF, JPG, PNG)</label>
                        <input type="file" name="soporte_digital" accept=".pdf,.jpg,.jpeg,.png" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                        <div class="text-xs text-on-surface-variant">Factura o recibo digital que podrán consultar los residentes como justificación.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Guardar Gasto
                    </button>
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

function toggleTipoRegistroModal(tipo) {
    const formDeuda = document.getElementById('formDeudaManual');
    const formGasto = document.getElementById('formRegistrarGasto');
    if (!formDeuda || !formGasto) {
        return;
    }
    const esDeuda = tipo !== 'gasto';
    formDeuda.style.display = esDeuda ? '' : 'none';
    formGasto.style.display = esDeuda ? 'none' : '';
    if (esDeuda) {
        toggleAlcanceDeudaManual();
    }
}

function toggleAlcanceDeudaManual() {
    const bloque = document.getElementById('bloquePickerDeudaManual');
    const alcanceTodo = document.getElementById('alcance_todo');
    if (!bloque || !alcanceTodo) {
        return;
    }
    bloque.style.display = alcanceTodo.checked ? 'none' : '';
    actualizarContadorDeudaManual();
}

function unidadesDeudaSeleccionadas() {
    const contenedor = document.getElementById('contenedorUnidadesDeudaManual');
    const alcanceTodo = document.getElementById('alcance_todo');
    if (!contenedor) {
        return 0;
    }
    if (alcanceTodo && alcanceTodo.checked) {
        return contenedor.querySelectorAll('.deuda-check-unidad').length;
    }
    return contenedor.querySelectorAll('.deuda-check-unidad:checked').length;
}

function actualizarContadorDeudaManual() {
    const contador = document.getElementById('contadorDeudaManual');
    const montoInput = document.getElementById('monto_unidad');
    if (!contador) {
        return;
    }
    const cantidad = unidadesDeudaSeleccionadas();
    const monto = montoInput ? parseFloat(montoInput.value || '0') : 0;
    const montoValido = isNaN(monto) ? 0 : monto;
    const total = montoValido * cantidad;
    contador.textContent = cantidad + ' unidades seleccionadas · Total a facturar Bs. ' +
        total.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function seleccionarTodoDeudaManual(marcar) {
    const contenedor = document.getElementById('contenedorUnidadesDeudaManual');
    if (!contenedor) {
        return;
    }
    const casillas = contenedor.querySelectorAll('.deuda-check-unidad, .deuda-check-edificio');
    for (let i = 0; i < casillas.length; i++) {
        casillas[i].checked = !!marcar;
    }
    actualizarContadorDeudaManual();
}

(function () {
    const contenedor = document.getElementById('contenedorUnidadesDeudaManual');
    if (!contenedor) {
        return;
    }

    const buscador = document.getElementById('buscadorUnidadesDeudaManual');

    contenedor.querySelectorAll('.deuda-grupo').forEach(function (grupo) {
        const checkEdificio = grupo.querySelector('.deuda-check-edificio');
        const unidadesGrupo = grupo.querySelectorAll('.deuda-check-unidad');

        if (checkEdificio) {
            checkEdificio.addEventListener('change', function () {
                unidadesGrupo.forEach(function (checkUnidad) {
                    checkUnidad.checked = checkEdificio.checked;
                });
                actualizarContadorDeudaManual();
            });
        }

        unidadesGrupo.forEach(function (checkUnidad) {
            checkUnidad.addEventListener('change', function () {
                if (checkEdificio) {
                    const marcadas = grupo.querySelectorAll('.deuda-check-unidad:checked').length;
                    checkEdificio.checked = unidadesGrupo.length > 0 && marcadas === unidadesGrupo.length;
                }
                actualizarContadorDeudaManual();
            });
        });
    });

    if (buscador) {
        buscador.addEventListener('input', function () {
            const termino = buscador.value.trim().toLowerCase();
            contenedor.querySelectorAll('.deuda-grupo').forEach(function (grupo) {
                let visibles = 0;
                grupo.querySelectorAll('.deuda-unidad-item').forEach(function (item) {
                    const coincide = termino === '' || (item.getAttribute('data-busqueda') || '').indexOf(termino) !== -1;
                    item.style.display = coincide ? '' : 'none';
                    if (coincide) {
                        visibles++;
                    }
                });
                grupo.style.display = visibles > 0 ? '' : 'none';
            });
        });
    }

    actualizarContadorDeudaManual();
})();
</script>

            </div>
        </div>
    </div>
</div>
