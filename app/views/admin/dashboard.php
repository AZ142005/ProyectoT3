<div class="flex flex-1 min-h-screen w-full">
    <!-- Sidebar Administrativa -->
    <aside id="adminSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transition-all duration-300 fixed md:sticky md:top-0 z-30 h-screen -translate-x-full md:translate-x-0">
        <div class="p-6 border-b border-slate-800 flex items-center gap-3">
            <span class="material-symbols-outlined text-primary-container text-3xl">domain</span>
            <div>
                <h2 class="text-white font-bold text-lg leading-tight">Condominio</h2>
                <small class="text-xs text-slate-500 font-medium">Panel de Control</small>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 flex flex-col gap-1.5">
            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 bg-slate-800 text-white font-bold rounded-xl transition-all">
                <span class="material-symbols-outlined">dashboard</span>
                Dashboard
            </a>
            <a href="/pagos" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">payments</span>
                Verificar Pagos (Módulo 3)
            </a>
            <a href="/admin/comprobantes" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">history</span>
                Historial Pagos
            </a>
            <a href="/admin/facturas/generar" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">receipt_long</span>
                Generar Facturas
            </a>
            <a href="/admin/estructura" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">domain</span>
                Estructura
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
                <h1 class="text-xl font-bold text-on-surface">Panel de Control</h1>
            </div>
            <a href="/admin/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">logout</span>
                Cerrar Sesión
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <!-- Sección: Comprobantes Pendientes -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">pending_actions</span>
                        Comprobantes Pendientes
                    </h3>
                    <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full"><?= count($comprobantes_pendientes) ?></span>
                </div>

                <?php if (empty($comprobantes_pendientes)): ?>
                    <div class="text-center py-12 text-on-surface-variant">
                        <span class="material-symbols-outlined text-5xl text-primary/30 mb-2" style="font-size:48px;">task_alt</span>
                        <p class="font-semibold text-primary">No hay comprobantes pendientes</p>
                        <div class="text-sm text-on-surface-variant mt-1">Todos los pagos han sido verificados.</div>
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
                                    <th class="py-3 px-4">Fecha</th>
                                    <th class="py-3 px-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-background">
                                <?php foreach ($comprobantes_pendientes as $c): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-semibold text-on-surface"><?= e($c['residente']) ?></td>
                                    <td class="py-4 px-4"><?= e($c['unidad']) ?></td>
                                    <td class="py-4 px-4 font-mono text-xs">#<?= e($c['numero_factura']) ?></td>
                                    <td class="py-4 px-4 font-bold text-on-surface"><?= e(formatearMoneda($c['monto'])) ?></td>
                                    <td class="py-4 px-4">
                                        <?php if ($c['archivo']): ?>
                                            <a href="/uploads/comprobantes/<?= e($c['archivo']) ?>" target="_blank" class="text-primary font-bold hover:underline inline-flex items-center gap-1 text-xs">
                                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                Ver
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-on-surface-variant">Sin archivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e(date('d/m/Y', strtotime($c['fecha_envio']))) ?></td>
                                    <td class="py-4 px-4">
                                        <a href="/admin/comprobante/verificar?id=<?= e($c['id']) ?>" class="bg-primary hover:bg-primary-hover text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-sm transition-transform active:scale-95 inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">verified</span>
                                            Verificar
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sección: Comprobantes Recientes Procesados -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">history</span>
                        Últimos Comprobantes Procesados
                    </h3>
                </div>

                <?php if (empty($ultimos_comprobantes)): ?>
                    <div class="text-center py-12 text-on-surface-variant">
                        <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-2" style="font-size:48px;">history</span>
                        <p class="font-semibold">No hay comprobantes procesados recientemente.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                    <th class="py-3 px-4">Residente</th>
                                    <th class="py-3 px-4">Factura</th>
                                    <th class="py-3 px-4">Monto</th>
                                    <th class="py-3 px-4">Estado</th>
                                    <th class="py-3 px-4">Fecha</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-background">
                                <?php foreach ($ultimos_comprobantes as $c): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-semibold text-on-surface"><?= e($c['residente']) ?></td>
                                    <td class="py-4 px-4 font-mono text-xs">#<?= e($c['numero_factura']) ?></td>
                                    <td class="py-4 px-4 font-bold text-on-surface"><?= e(formatearMoneda($c['monto'])) ?></td>
                                    <td class="py-4 px-4">
                                        <?php
                                        $statusClass = [
                                            'aprobado' => 'bg-green-50 text-green-700',
                                            'rechazado' => 'bg-red-50 text-red-700'
                                        ][$c['estado']] ?? 'bg-gray-50 text-gray-700';
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $statusClass ?>">
                                            <?= e(ucfirst($c['estado'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e(date('d/m/Y', strtotime($c['fecha_envio']))) ?></td>
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
