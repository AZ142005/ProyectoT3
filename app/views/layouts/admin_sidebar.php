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
    <div class="p-6 border-b border-slate-800 flex items-center gap-3">
        <span class="material-symbols-outlined text-primary-container text-3xl">domain</span>
        <div>
            <h2 class="text-white font-bold text-lg leading-tight">Condominio</h2>
            <small class="text-xs text-slate-500 font-medium">Panel de Control</small>
        </div>
    </div>

    <nav class="flex-1 px-4 py-6 flex flex-col gap-1.5">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= e($item['url']) ?>" 
               class="flex items-center gap-3 px-4 py-3 <?= ($activeRoute ?? '') === $item['route'] ? 'bg-slate-800 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?> rounded-xl transition-all">
                <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-slate-800 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-white font-bold uppercase">
            <?php $_au = App\Core\Auth::user(); ?>
            <?= e(substr($_au['email'] ?? 'A', 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-white truncate"><?= e($_au['name'] ?? 'Administrador') ?></p>
            <small class="text-xs text-slate-500 block truncate"><?= e(ucfirst($_au['role'] ?? 'admin')) ?></small>
        </div>
    </div>
</aside>

<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-20" onclick="toggleSidebar()"></div>