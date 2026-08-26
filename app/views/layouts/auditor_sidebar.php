<?php
$navItems = [
    ['route' => 'dashboard',    'url' => '/auditor/dashboard',         'icon' => 'policy',         'label' => 'Dashboard Fiscal'],
    ['route' => 'logs',         'url' => '/auditor/log-transacciones', 'icon' => 'history',        'label' => 'Log Auditoría'],
    ['route' => 'conciliacion', 'url' => '/admin/conciliacion',        'icon' => 'sync_alt',       'label' => 'Conciliaciones (Solo Lectura)'],
    ['route' => 'gastos',       'url' => '/admin/gastos',              'icon' => 'inventory_2',    'label' => 'Gastos Comunes (Solo Lectura)'],
    ['route' => 'morosidad',    'url' => '/admin/reportes/morosidad',  'icon' => 'warning',        'label' => 'Reporte Morosidad'],
];
?>
<aside id="adminSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transition-all duration-300 fixed md:sticky md:top-0 z-30 h-screen -translate-x-full md:translate-x-0 shrink-0">
    <div class="p-6 border-b border-slate-800 flex items-center gap-3">
        <span class="material-symbols-outlined text-primary-container text-3xl">verified_user</span>
        <div>
            <h2 class="text-white font-bold text-lg leading-tight">Auditoría</h2>
            <small class="text-xs text-slate-500 font-medium">Solo Lectura / Fiscal</small>
        </div>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto custom-scrollbar">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= e($item['url']) ?>" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 text-slate-400 hover:bg-slate-800 hover:text-slate-200">
                <span class="material-symbols-outlined text-xl"><?= e($item['icon']) ?></span>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-slate-800">
        <a href="/auth/logout" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm text-red-400 hover:bg-red-500/10 transition-colors w-full">
            <span class="material-symbols-outlined text-xl">logout</span>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
