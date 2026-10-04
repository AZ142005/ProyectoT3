<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'comprobantes'; 
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
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-2xl">history</span>
                    <h1 class="text-xl font-bold text-on-surface">Historial de Pagos</h1>
                </div>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <!-- Alertas / Mensajes -->
            <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

            <!-- Barra de Filtros Ultra-Compacta (Toolbar Permanente) -->
            <div class="bg-white rounded-2xl border border-outline-variant p-2.5 shadow-sm mb-4">
                <form method="GET" action="/admin/comprobantes" class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1 text-primary shrink-0 pe-2 border-r border-slate-100 hidden sm:flex">
                        <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Filtros</span>
                    </div>

                    <!-- Buscar por Texto -->
                    <div class="relative flex-1 min-w-[200px]">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-[16px]">search</span>
                        <input type="text" name="buscar" id="buscar" placeholder="Residente, Cédula, Factura..." value="<?= e($filtros['buscar'] ?? '') ?>"
                               aria-label="Buscar por texto" class="w-full pl-8 pr-3 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
                    </div>

                    <!-- Edificio -->
                    <div class="w-full sm:w-auto">
                        <select name="edificio_id" id="edificio_id" aria-label="Edificio o Torre" class="w-full sm:w-auto px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                            <option value="">Todos los Edificios</option>
                            <?php if (!empty($edificios)): ?>
                                <?php foreach ($edificios as $ed): ?>
                                    <option value="<?= e($ed['id']) ?>" <?= ((string)($filtros['edificio_id'] ?? '') === (string)$ed['id']) ? 'selected' : '' ?>>
                                        <?= e($ed['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Unidad / Apartamento -->
                    <div class="w-full sm:w-28">
                        <input type="text" name="unidad" id="unidad" placeholder="Unidad / Apto" value="<?= e($filtros['unidad'] ?? '') ?>" aria-label="Unidad o Apartamento"
                               class="w-full px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
                    </div>

                    <!-- Rango: Fechas -->
                    <div class="flex items-center gap-1 w-full sm:w-auto">
                        <input type="date" name="fecha_desde" id="fecha_desde" value="<?= e($filtros['fecha_desde'] ?? '') ?>" title="Fecha Desde" aria-label="Fecha Desde"
                               class="px-2 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
                        <span class="text-on-surface-variant text-xs font-semibold">-</span>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= e($filtros['fecha_hasta'] ?? '') ?>" title="Fecha Hasta" aria-label="Fecha Hasta"
                               class="px-2 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
                    </div>

                    <!-- Estado -->
                    <div class="w-full sm:w-auto">
                        <select name="estado" id="estado" aria-label="Estado de Pago" class="w-full sm:w-auto px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                            <option value="">Todos los Estados</option>
                            <option value="aprobado" <?= (($filtros['estado'] ?? '') === 'aprobado') ? 'selected' : '' ?>>Aprobados</option>
                            <option value="rechazado" <?= (($filtros['estado'] ?? '') === 'rechazado') ? 'selected' : '' ?>>Rechazados</option>
                        </select>
                    </div>

                    <!-- Botones de Acción Inline -->
                    <div class="flex items-center gap-1.5 ms-auto">
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-3 py-1.5 rounded-xl shadow-sm text-xs transition-all flex items-center justify-center gap-1 active:scale-95">
                            <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                            Filtrar
                        </button>
                        <a href="/admin/comprobantes" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-1.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1" title="Limpiar Filtros">
                            <span class="material-symbols-outlined text-[16px]">clear_all</span>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Datos Históricos -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-on-surface">Pagos Procesados</h3>
                        <p class="text-xs text-on-surface-variant">Consulta histórica de pagos aprobados y rechazados.</p>
                    </div>
                    <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant">Total: <?= e($paginacion['total']) ?></span>
                </div>

                <?php if (empty($comprobantes)): ?>
                    <div class="text-center py-12 text-on-surface-variant">
                        <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-2" style="font-size: 48px;">receipt_long</span>
                        <p class="font-semibold">No se encontraron pagos históricos con los criterios especificados.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                    <th class="py-3 px-4">Residente</th>
                                    <th class="py-3 px-4">Edificio</th>
                                    <th class="py-3 px-4">Unidad</th>
                                    <th class="py-3 px-4">Factura</th>
                                    <th class="py-3 px-4">Monto</th>
                                    <th class="py-3 px-4">Comprobante</th>
                                    <th class="py-3 px-4">Fecha Pago</th>
                                    <th class="py-3 px-4">Estado</th>
                                    <th class="py-3 px-4 text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-background">
                                <?php foreach ($comprobantes as $c): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-semibold text-on-surface">
                                        <?= e($c['residente']) ?>
                                        <div class="text-xs text-on-surface-variant font-normal"><?= e($c['cedula']) ?></div>
                                    </td>
                                    <td class="py-4 px-4 text-xs font-medium text-slate-700">
                                        <?= e($c['edificio'] ?? 'Condominio') ?>
                                    </td>
                                    <td class="py-4 px-4 font-semibold text-xs"><?= e($c['unidad']) ?></td>
                                    <td class="py-4 px-4 font-mono text-xs">#<?= e($c['numero_factura']) ?></td>
                                    <td class="py-4 px-4 font-bold text-on-surface"><?= e(formatearMoneda($c['monto'])) ?></td>
                                    <td class="py-4 px-4">
                                        <?php if ($c['archivo']): ?>
                                            <div class="flex items-center gap-2">
                                                <a href="/comprobante-proxy.php?file=<?= e($c['archivo']) ?>" target="_blank" class="text-primary font-bold hover:underline inline-flex items-center gap-1 text-xs" title="Ver comprobante">
                                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                    Ver
                                                </a>
                                                <a href="/comprobante-proxy.php?file=<?= e($c['archivo']) ?>&download=1" download="<?= e($c['archivo']) ?>" class="text-slate-600 hover:text-primary font-bold inline-flex items-center gap-1 text-xs bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded transition-colors" title="Descargar comprobante">
                                                    <span class="material-symbols-outlined text-[16px]">download</span>
                                                    Descargar
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-on-surface-variant">Sin archivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e(date('d/m/Y', strtotime($c['fecha_pago']))) ?></td>
                                    <td class="py-4 px-4">
                                        <?= badgeEstado($c['estado']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-end">
                                        <a href="/admin/comprobante/verificar?id=<?= e($c['id']) ?>&from=comprobantes" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold p-1.5 rounded-lg inline-flex items-center justify-center transition-colors" title="Ver Detalle">
                                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        </div>
    </div>
</div>
