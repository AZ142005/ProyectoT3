<?php
// $activeRoute debe estar definido antes de incluir este partial
// Opciones: 'dashboard', 'comprobantes', 'facturas', 'estructura', 'pagos'

$navItems = [
    ['route' => 'dashboard',        'url' => '/admin/dashboard',          'icon' => 'dashboard',       'label' => 'Dashboard'],
    ['route' => 'comprobantes',     'url' => '/admin/comprobantes',       'icon' => 'payments',        'label' => 'Verificar Pagos'],
    ['route' => 'conciliacion',     'url' => '/admin/conciliacion',       'icon' => 'sync_alt',        'label' => 'Conciliación'],
    ['route' => 'facturas',         'url' => '/admin/facturas/generar',   'icon' => 'receipt_long',    'label' => 'Generar Facturas'],
    ['route' => 'gastos',           'url' => '/admin/gastos',             'icon' => 'inventory_2',     'label' => 'Gastos Comunes'],
    ['route' => 'estructura',       'url' => '/admin/estructura',         'icon' => 'domain',          'label' => 'Estructura'],
    ['route' => 'estacionamientos', 'url' => '/admin/estacionamientos',   'icon' => 'directions_car',  'label' => 'Estacionamientos'],
    ['route' => 'comunicados',      'url' => '/admin/comunicados',        'icon' => 'campaign',        'label' => 'Comunicados'],
    ['route' => 'solicitudes',      'url' => '/admin/solicitudes-datos',  'icon' => 'manage_accounts', 'label' => 'Solicitudes Datos'],
    ['route' => 'morosidad',        'url' => '/admin/reportes/morosidad', 'icon' => 'warning',         'label' => 'Reporte Morosidad'],
    ['route' => 'respaldos',        'url' => '/admin/respaldos',          'icon' => 'backup',          'label' => 'Respaldos BD'],
];

// Para la vista de pagos del admin, mantenemos consistente
if (isset($activeRoute) && $activeRoute === 'pagos') {
    $navItems = [
        ['route' => 'dashboard',        'url' => '/admin/dashboard',          'icon' => 'dashboard',       'label' => 'Dashboard'],
        ['route' => 'pagos',            'url' => '/pagos',                    'icon' => 'payments',        'label' => 'Verificar Pagos'],
        ['route' => 'conciliacion',     'url' => '/admin/conciliacion',       'icon' => 'sync_alt',        'label' => 'Conciliación'],
        ['route' => 'facturas',         'url' => '/admin/facturas/generar',   'icon' => 'receipt_long',    'label' => 'Generar Facturas'],
        ['route' => 'gastos',           'url' => '/admin/gastos',             'icon' => 'inventory_2',     'label' => 'Gastos Comunes'],
        ['route' => 'estructura',       'url' => '/admin/estructura',         'icon' => 'domain',          'label' => 'Estructura'],
        ['route' => 'estacionamientos', 'url' => '/admin/estacionamientos',   'icon' => 'directions_car',  'label' => 'Estacionamientos'],
        ['route' => 'comunicados',      'url' => '/admin/comunicados',        'icon' => 'campaign',        'label' => 'Comunicados'],
        ['route' => 'solicitudes',      'url' => '/admin/solicitudes-datos',  'icon' => 'manage_accounts', 'label' => 'Solicitudes Datos'],
        ['route' => 'morosidad',        'url' => '/admin/reportes/morosidad', 'icon' => 'warning',         'label' => 'Reporte Morosidad'],
        ['route' => 'respaldos',        'url' => '/admin/respaldos',          'icon' => 'backup',          'label' => 'Respaldos BD'],
    ];
}
?>
<aside id="adminSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transition-all duration-300 fixed md:sticky md:top-0 z-30 h-screen -translate-x-full md:translate-x-0 shrink-0">
    <div class="p-5 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary-container text-3xl">domain</span>
            <div>
                <h2 class="text-white font-bold text-base leading-tight">Condominio</h2>
                <small class="text-xs text-slate-500 font-medium">Panel de Control</small>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white p-1">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>

    <nav class="flex-1 px-3 py-4 flex flex-col gap-1 overflow-y-auto custom-scrollbar">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= e($item['url']) ?>" 
               class="flex items-center gap-3 px-3 py-2.5 <?= ($activeRoute ?? '') === $item['route'] ? 'bg-primary text-white font-bold shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> rounded-xl transition-all text-sm">
                <span class="material-symbols-outlined text-[20px]"><?= e($item['icon']) ?></span>
                <span class="truncate"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-3 border-t border-slate-800 flex items-center justify-between gap-2">
        <a href="/perfil" class="flex items-center gap-2.5 min-w-0 flex-1 hover:bg-slate-800/60 p-1.5 rounded-xl transition-colors" title="Ver mi perfil">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-white font-bold text-sm uppercase shrink-0">
                <?php $_au = App\Core\Auth::user(); ?>
                <?= e(substr($_au['email'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-white truncate"><?= e($_au['name'] ?? 'Administrador') ?></p>
                <small class="text-[11px] text-slate-400 block truncate"><?= e(ucfirst($_au['role'] ?? 'admin')) ?></small>
            </div>
        </a>
        <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="text-slate-400 hover:text-red-400 p-2 rounded-lg hover:bg-slate-800 transition-colors flex items-center justify-center" title="Cerrar Sesión">
            <span class="material-symbols-outlined text-[18px]">logout</span>
        </a>
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