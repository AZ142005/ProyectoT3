<div class="flex flex-1 min-h-screen w-full">
    <?php $activeRoute = 'comprobantes'; require VIEWS_PATH . '/layouts/admin_sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Verificación de Pagos</h1>
            </div>
            <a href="/admin/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">logout</span>
                Cerrar Sesión
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <!-- Alertas / Mensajes -->
            <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

            <!-- Filtros de búsqueda (Stitch / Tailwind UI inspired) -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8">
                <form method="GET" action="/admin/comprobantes" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div class="flex flex-col gap-1.5">
                        <label for="estado" class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Filtrar por Estado</label>
                        <select name="estado" id="estado" class="w-full px-4 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-sm">
                            <option value="">Todos los Estados</option>
                            <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="aprobado" <?= $estado === 'aprobado' ? 'selected' : '' ?>>Aprobados</option>
                            <option value="rechazado" <?= $estado === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="buscar" class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Buscar por Texto</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/80 text-[18px]">search</span>
                            <input type="text" name="buscar" id="buscar" placeholder="Residente, Cédula, Factura..." value="<?= e($buscar) ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-sm">
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-6 py-2.5 rounded-xl shadow-sm text-sm transition-all flex items-center justify-center gap-1 flex-1">
                            <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                            Filtrar
                        </button>
                        <a href="/admin/comprobantes" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm transition-all flex items-center justify-center">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Datos -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <h3 class="text-lg font-bold text-on-surface">Comprobantes Recibidos</h3>
                    <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= e($paginacion['total']) ?></span>
                </div>

                <?php if (empty($comprobantes)): ?>
                    <div class="text-center py-12 text-on-surface-variant">
                        <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-2" style="font-size: 48px;">payments</span>
                        <p class="font-semibold">No se encontraron comprobantes de pago.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                    <th class="py-3 px-4">Residente</th>
                                    <th class="py-3 px-4">Unidad</th>
                                    <th class="py-3 px-4">Factura</th>
                                    <th class="py-3 px-4">Monto</th>
                                    <th class="py-3 px-4">Comprobante</th>
                                    <th class="py-3 px-4">Fecha Pago</th>
                                    <th class="py-3 px-4">Estado</th>
                                    <th class="py-3 px-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-background">
                                <?php foreach ($comprobantes as $c): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-semibold text-on-surface">
                                        <?= e($c['residente']) ?>
                                        <div class="text-xs text-on-surface-variant font-normal"><?= e($c['cedula']) ?></div>
                                    </td>
                                    <td class="py-4 px-4"><?= e($c['unidad']) ?></td>
                                    <td class="py-4 px-4 font-mono text-xs">#<?= e($c['numero_factura']) ?></td>
                                    <td class="py-4 px-4 font-bold text-on-surface"><?= e(formatearMoneda($c['monto'])) ?></td>
                                    <td class="py-4 px-4">
                                        <?php if ($c['archivo']): ?>
                                            <a href="/comprobante-proxy.php?file=<?= e($c['archivo']) ?>" target="_blank" class="text-primary font-bold hover:underline inline-flex items-center gap-1 text-xs">
                                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                Ver Archivo
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-on-surface-variant">Sin archivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e(date('d/m/Y', strtotime($c['fecha_pago']))) ?></td>
                                    <td class="py-4 px-4">
                                        <?= badgeEstado($c['estado']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if ($c['estado'] === 'pendiente'): ?>
                                            <a href="/admin/comprobante/verificar?id=<?= e($c['id']) ?>" class="bg-primary hover:bg-primary-hover text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-sm transition-transform active:scale-95 inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">verified</span>
                                                Verificar
                                            </a>
                                        <?php else: ?>
                                            <a href="/admin/comprobante/verificar?id=<?= e($c['id']) ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-3 py-1.5 rounded-lg inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">search</span>
                                                Detalles
                                            </a>
                                        <?php endif; ?>
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
