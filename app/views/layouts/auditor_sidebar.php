<?php
$navItems = [
    ['route' => 'dashboard',    'url' => '/auditor/dashboard',         'icon' => 'policy',         'label' => 'Dashboard Fiscal'],
    ['route' => 'logs',         'url' => '/auditor/log-transacciones', 'icon' => 'history',        'label' => 'Log Auditoría'],
    ['route' => 'conciliacion', 'url' => '/admin/conciliacion',        'icon' => 'sync_alt',       'label' => 'Conciliaciones'],
    ['route' => 'gastos',       'url' => '/admin/gastos',              'icon' => 'inventory_2',    'label' => 'Gastos Comunes'],
    ['route' => 'morosidad',    'url' => '/admin/reportes/morosidad',  'icon' => 'warning',        'label' => 'Reporte Morosidad'],
];
?>
<aside id="adminSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transition-all duration-300 fixed md:sticky md:top-0 z-30 h-screen -translate-x-full md:translate-x-0 shrink-0">
    <div class="p-5 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary-container text-3xl">verified_user</span>
            <div>
                <h2 class="text-white font-bold text-base leading-tight">Auditoría</h2>
                <small class="text-xs text-slate-500 font-medium">Solo Lectura / Fiscal</small>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white p-1">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto custom-scrollbar">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= e($item['url']) ?>" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 <?= ($activeRoute ?? '') === $item['route'] ? 'bg-primary text-white font-bold shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <span class="material-symbols-outlined text-[20px]"><?= e($item['icon']) ?></span>
                <span class="truncate"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-3 border-t border-slate-800 bg-slate-950/40">
        <?php $_au = App\Core\Auth::user(); ?>
        <a href="/perfil" 
           class="flex items-center gap-3 p-2.5 rounded-2xl transition-all <?= ($activeRoute ?? '') === 'perfil' ? 'bg-primary/20 border-2 border-primary text-white shadow-md' : 'bg-slate-800/80 border border-slate-700/70 hover:border-primary/60 hover:bg-slate-800 text-slate-200 shadow-sm' ?> group block" 
           title="Acceder a Mi Perfil de Usuario">
            <div class="relative shrink-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-hover border border-white/20 flex items-center justify-center text-white font-bold text-base shadow-sm">
                    <?= e(strtoupper(substr($_au['name'] ?? $_au['email'] ?? 'A', 0, 1))) ?>
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full" title="En línea"></span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-white truncate group-hover:text-primary-container transition-colors"><?= e($_au['name'] ?? 'Auditor') ?></p>
                    <span class="material-symbols-outlined text-[16px] text-slate-400 group-hover:text-primary-container group-hover:translate-x-0.5 transition-all">chevron_right</span>
                </div>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-300 bg-emerald-950/80 px-1.5 py-0.5 rounded border border-emerald-800/50">
                        <span class="material-symbols-outlined text-[11px]">account_circle</span>
                        <span>Mi Perfil</span>
                    </span>
                    <span class="text-[10px] text-slate-400 capitalize truncate"><?= e($_au['role'] ?? 'auditor') ?></span>
                </div>
            </div>
        </a>
        <div class="mt-2.5 pt-2 border-t border-slate-800/60 flex items-center justify-between px-1 text-xs text-slate-400">
            <span class="text-[11px] text-slate-500">Sesión Activa</span>
            <a href="/auth/logout" onclick="return confirmarCierreSesion(event, this.href);" class="text-red-400 hover:text-red-300 hover:bg-red-950/50 px-2 py-1 rounded-lg transition-colors flex items-center gap-1 font-semibold text-[11px]" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[14px]">logout</span>
                <span>Salir</span>
            </a>
        </div>
    </div>
</aside>

<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-20 transition-opacity" onclick="toggleSidebar()"></div>

<script>
if (typeof toggleSidebar !== 'function') {
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && overlay) {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    }
}
</script>
