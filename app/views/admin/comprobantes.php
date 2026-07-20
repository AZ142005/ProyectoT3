<div class="flex flex-1 min-h-screen w-full">
    <!-- Sidebar Administrativa -->
    <aside id="adminSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transition-all duration-300 fixed md:relative -translate-x-full md:translate-x-0 z-30 h-full min-h-screen">
        <div class="p-6 border-b border-slate-800 flex items-center gap-3">
            <span class="material-symbols-outlined text-primary-container text-3xl">domain</span>
            <div>
                <h2 class="text-white font-bold text-lg leading-tight">Condominio</h2>
                <small class="text-xs text-slate-500 font-medium">Panel de Control</small>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 flex flex-col gap-1.5">
            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">dashboard</span>
                Dashboard
            </a>
            <a href="/admin/comprobantes" class="flex items-center gap-3 px-4 py-3 bg-slate-800 text-white font-bold rounded-xl transition-all">
                <span class="material-symbols-outlined">payments</span>
                Verificar Pagos
            </a>
            <a href="/admin/comprobantes" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">history</span>
                Historial Pagos
            </a>
            <a href="/admin/facturas/generar" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">receipt_long</span>
                Generar Facturas
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-white font-bold uppercase">
                <?= e(substr($_SESSION['admin_usuario'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate"><?= e($_SESSION['admin_nombre'] ?? 'Administrador') ?></p>
                <small class="text-xs text-slate-500 block truncate"><?= e($_SESSION['admin_rol'] ?? 'Admin') ?></small>
            </div>
        </div>
    </aside>

    <!-- Overlay para móvil -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-20" onclick="toggleSidebar()"></div>

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
            <?php if (!empty($mensaje)): ?>
                <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2">
                    <span class="material-symbols-outlined text-[20px] shrink-0">check_circle</span>
                    <span><?= e($mensaje) ?></span>
                </div>
            <?php endif; ?>

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
                    <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= count($comprobantes) ?></span>
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
                                            <a href="/uploads/comprobantes/<?= e($c['archivo']) ?>" target="_blank" class="text-primary font-bold hover:underline inline-flex items-center gap-1 text-xs">
                                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                Ver Archivo
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-on-surface-variant">Sin archivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e(date('d/m/Y', strtotime($c['fecha_pago']))) ?></td>
                                    <td class="py-4 px-4">
                                        <?php
                                        $statusClass = [
                                            'pendiente' => 'bg-yellow-50 text-yellow-700',
                                            'verificado' => 'bg-blue-50 text-blue-700',
                                            'aprobado' => 'bg-green-50 text-green-700',
                                            'rechazado' => 'bg-red-50 text-red-700'
                                        ][$c['estado']] ?? 'bg-gray-50 text-gray-700';
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $statusClass ?>">
                                            <?= e(ucfirst($c['estado'])) ?>
                                        </span>
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
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
</script>
