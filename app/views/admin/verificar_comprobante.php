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
                <h1 class="text-xl font-bold text-on-surface">Verificar Comprobante</h1>
            </div>
            <a href="/admin/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">logout</span>
                Cerrar Sesión
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto max-w-4xl mx-auto w-full">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2">
                    <span class="material-symbols-outlined text-[20px] shrink-0">error</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Tarjeta de Detalles del Comprobante -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <h3 class="text-lg font-bold text-on-surface">Información General</h3>
                    <?php
                    $statusClass = [
                        'pendiente' => 'bg-yellow-50 text-yellow-700',
                        'aprobado' => 'bg-green-50 text-green-700',
                        'rechazado' => 'bg-red-50 text-red-700'
                    ][$comprobante['estado']] ?? 'bg-gray-50 text-gray-700';
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $statusClass ?>">
                        <?= e($comprobante['estado']) ?>
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Residente</span>
                        <span class="text-sm font-semibold text-on-surface mt-0.5"><?= e($comprobante['residente']) ?> (C.I: <?= e($comprobante['cedula']) ?>)</span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Unidad / Propiedad</span>
                        <span class="text-sm font-semibold text-on-surface mt-0.5">Unidad N° <?= e($comprobante['unidad']) ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Factura Relacionada</span>
                        <span class="text-sm font-medium text-on-surface mt-0.5 font-mono">#<?= e($comprobante['numero_factura']) ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Saldo Total Factura</span>
                        <span class="text-sm font-bold text-on-surface mt-0.5"><?= e(formatearMoneda($comprobante['saldo'])) ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Monto a Verificar</span>
                        <span class="text-sm font-black text-primary mt-0.5 text-lg"><?= e(formatearMoneda($comprobante['monto'])) ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2 bg-background/30 px-3 py-1.5 rounded-lg border-l-4 <?= $saldo_restante < 0 ? 'border-green-500' : ($saldo_restante > 0 ? 'border-red-500' : 'border-blue-500') ?>">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Saldo Restante</span>
                        <span class="text-sm font-black mt-0.5 <?= $saldo_restante < 0 ? 'text-green-600' : ($saldo_restante > 0 ? 'text-red-500' : 'text-blue-600') ?>">
                            <?= e(formatearMoneda($saldo_restante)) ?>
                            <?php if ($saldo_restante < 0): ?>
                                <span class="text-xs font-normal text-green-600 block">(Generará saldo a favor de <?= e(formatearMoneda(abs($saldo_restante))) ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Método de Pago</span>
                        <span class="text-sm font-medium text-on-surface mt-0.5 uppercase text-xs"><?= e(str_replace('_', ' ', $comprobante['metodo_pago'])) ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Referencia</span>
                        <span class="text-sm font-medium text-on-surface mt-0.5 font-mono"><?= e($comprobante['referencia'] ?: '-') ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Fecha de Pago</span>
                        <span class="text-sm text-on-surface mt-0.5"><?= e(date('d/m/Y', strtotime($comprobante['fecha_pago']))) ?></span>
                    </div>

                    <div class="flex flex-col border-b border-background pb-2">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Fecha de Envío</span>
                        <span class="text-sm text-on-surface mt-0.5"><?= e(date('d/m/Y H:i', strtotime($comprobante['fecha_envio']))) ?></span>
                    </div>
                </div>

                <?php if ($comprobante['observaciones']): ?>
                    <div class="flex flex-col mt-4 bg-background/50 p-4 rounded-xl">
                        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Observaciones del Residente</span>
                        <span class="text-sm text-on-surface mt-1 whitespace-pre-line"><?= e($comprobante['observaciones']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col mt-6">
                    <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider mb-2">Comprobante Digital</span>
                    <?php if ($comprobante['archivo']): ?>
                        <div class="border border-outline-variant rounded-xl overflow-hidden max-w-lg bg-background p-2">
                            <a href="/uploads/comprobantes/<?= e($comprobante['archivo']) ?>" target="_blank" class="block relative overflow-hidden group">
                                <img src="/uploads/comprobantes/<?= e($comprobante['archivo']) ?>" alt="Recibo digital" class="w-full max-h-96 object-contain rounded-lg">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-bold gap-1">
                                    <span class="material-symbols-outlined">zoom_in</span>
                                    Ampliar Comprobante
                                </div>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="border border-dashed border-outline-variant p-6 rounded-xl text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-4xl text-on-surface-variant/40 mb-1">no_photography</span>
                            <div class="text-sm">No se cargó imagen física para este comprobante.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario de Acciones (Solo visible en Pendiente) -->
            <?php if ($comprobante['estado'] === 'pendiente'): ?>
                <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                    <div class="pb-4 border-b border-background mb-6">
                        <h3 class="text-lg font-bold text-on-surface">Procesar Verificación</h3>
                    </div>

                    <form method="POST" action="/admin/comprobante/verificar?id=<?= e($comprobante['id']) ?>" class="flex flex-col gap-5">
                        <!-- CSRF Field -->
                        <?= csrf_field() ?>

                        <div class="flex flex-col gap-1.5">
                            <label for="observaciones" class="text-sm font-semibold text-on-surface-variant">Observaciones del Administrador (Opcional)</label>
                            <textarea name="observaciones" id="observaciones" rows="3" placeholder="Ej. Aprobado por conciliación exitosa, o error en la referencia..."
                                      class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary resize-none"></textarea>
                        </div>

                        <div class="flex gap-4 pt-4 border-t border-background flex-wrap">
                            <button type="submit" name="accion" value="aprobar" class="bg-primary hover:bg-primary-hover text-white font-bold px-8 py-3 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1">
                                <span class="material-symbols-outlined">check_circle</span>
                                Aprobar Pago
                            </button>
                            <button type="submit" name="accion" value="rechazar" class="bg-red-600 hover:bg-red-700 text-white font-bold px-8 py-3 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1">
                                <span class="material-symbols-outlined">cancel</span>
                                Rechazar Pago
                            </button>
                            <a href="/admin/comprobantes" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-6 py-3 rounded-xl text-center transition-all duration-200 active:scale-95">
                                Volver al Listado
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm text-center">
                    <p class="text-on-surface-variant font-medium text-sm">Este comprobante de pago ya se encuentra procesado (Estado: <strong class="capitalize"><?= e($comprobante['estado']) ?></strong>).</p>
                    <a href="/admin/comprobantes" class="mt-4 bg-primary hover:bg-primary-hover text-white font-bold px-6 py-2 rounded-xl text-xs inline-flex items-center gap-1 transition-transform active:scale-95">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        Volver al Listado
                    </a>
                </div>
            <?php endif; ?>
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
