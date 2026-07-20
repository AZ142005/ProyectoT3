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
            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">dashboard</span>
                Dashboard
            </a>
            <a href="/admin/comprobantes" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">payments</span>
                Verificar Pagos
            </a>
            <a href="/admin/comprobantes" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl transition-all">
                <span class="material-symbols-outlined">history</span>
                Historial Pagos
            </a>
            <a href="/admin/facturas/generar" class="flex items-center gap-3 px-4 py-3 bg-slate-800 text-white font-bold rounded-xl transition-all">
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
                <h1 class="text-xl font-bold text-on-surface">Generación de Facturas</h1>
            </div>
            <a href="/admin/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">logout</span>
                Cerrar Sesión
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto max-w-4xl mx-auto w-full">
            <!-- Mensajes y Alertas -->
            <?php if (!empty($mensaje)): ?>
                <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2">
                    <span class="material-symbols-outlined text-[20px] shrink-0">check_circle</span>
                    <span><?= e($mensaje) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2">
                    <span class="material-symbols-outlined text-[20px] shrink-0">error</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Tarjeta de Generación Masiva (Stitch-like) -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">receipt_long</span>
                        Generar Facturación Mensual
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-background/50 p-6 rounded-2xl border border-outline-variant mb-6">
                    <div class="flex flex-col gap-1 text-center md:text-left">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Período de Facturación</span>
                        <span class="text-lg font-bold text-on-surface mt-1"><?= nombreMes($mes) ?> <?= e($anio) ?></span>
                    </div>

                    <div class="flex flex-col gap-1 text-center md:text-left border-y md:border-y-0 md:border-x border-outline-variant py-4 md:py-0 md:px-6">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Unidades Condominales</span>
                        <span class="text-lg font-bold text-on-surface mt-1"><?= count($unidades) ?> activas</span>
                    </div>

                    <div class="flex flex-col gap-1 text-center md:text-left">
                        <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Facturas Creadas</span>
                        <span class="text-lg font-bold mt-1 <?= $facturas_existentes > 0 ? 'text-primary' : 'text-on-surface-variant/60' ?>">
                            <?= $facturas_existentes > 0 ? $facturas_existentes . ' creadas' : 'Ninguna creada' ?>
                        </span>
                    </div>
                </div>

                <!-- Advertencia en caso de que existan facturas en este periodo -->
                <?php if ($facturas_existentes > 0): ?>
                    <div class="bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-[22px] text-yellow-700 shrink-0">warning</span>
                        <div>
                            <p class="font-bold">Facturas ya generadas</p>
                            <p class="mt-0.5 text-xs text-yellow-600/90 leading-relaxed">
                                Ya se han generado las facturas para el mes actual de <strong><?= nombreMes($mes) ?> <?= e($anio) ?></strong>. Si decides presionar el botón "Generar de Nuevo", se duplicará la facturación mensual para los residentes. Utiliza esta acción únicamente si eliminaste las facturas anteriores.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-2xl p-4 text-sm mb-6 flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-[22px] text-blue-700 shrink-0">info</span>
                        <div>
                            <p class="font-bold">Proceso Automatizado de Conciliación</p>
                            <p class="mt-0.5 text-xs text-blue-600/90 leading-relaxed">
                                Al generar las facturas, el sistema buscará de manera automática cualquier saldo a favor de meses anteriores (facturas con saldos negativos) para cada unidad condominal, y lo aplicará como abono a la cuota del presente mes. Si el saldo a favor cubre el total de la cuota, la nueva factura nacerá marcada en estado **Pagada**.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/admin/facturas/generar" class="flex gap-4 items-center flex-wrap pt-4 border-t border-background">
                    <!-- CSRF Field -->
                    <?= csrf_field() ?>

                    <button type="submit" name="generar" value="1"
                            class="font-bold px-8 py-3.5 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1.5 text-sm <?= $facturas_existentes > 0 ? 'bg-slate-200 text-slate-700 hover:bg-slate-300' : 'bg-primary hover:bg-primary-hover text-white' ?>">
                        <span class="material-symbols-outlined">autorenew</span>
                        <?= $facturas_existentes > 0 ? 'Re-generar Facturas' : 'Generar Facturas del Mes' ?>
                    </button>

                    <a href="/admin/dashboard" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-6 py-3.5 rounded-xl text-sm text-center transition-all duration-200 active:scale-95">
                        Volver al Dashboard
                    </a>
                </form>
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
