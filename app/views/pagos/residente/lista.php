<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de la página -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold">Mis Pagos</h2>
            <p class="text-sm opacity-90 mt-1">Unidad <?= e($residente['unidad_numero'] ?? 'N/A') ?> (<?= e($residente['torre'] ?? 'N/A') ?>)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/residente/dashboard" class="bg-white/15 hover:bg-white/25 border border-white/20 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition-transform active:scale-95 flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">dashboard</span>
                Panel Residente
            </a>
            <a href="/pagos/nuevo" class="bg-white text-primary hover:bg-slate-50 font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                Registrar Nuevo Pago
            </a>
        </div>
    </div>

    <!-- Mensajes de Alerta -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Tabla de Pagos -->
    <div class="bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
        
        <?php if (empty($pagos)): ?>
            <div class="text-center py-20 text-on-surface-variant flex flex-col items-center">
                <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">payments</span>
                <p class="text-xl font-bold text-on-surface">Aún no has registrado ningún pago</p>
                <p class="text-sm text-slate-500 mt-2">Haz clic en "Registrar Nuevo Pago" para enviar tu primer comprobante.</p>
                <a href="/pagos/nuevo" class="mt-6 bg-primary hover:bg-primary-hover text-white font-bold py-3 px-6 rounded-xl shadow-md transition-all active:scale-95">
                    Registrar Pago Ahora
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase text-slate-500 font-bold border-b border-outline-variant">
                            <th class="py-4 px-6">Fecha Pago</th>
                            <th class="py-4 px-6">Monto</th>
                            <th class="py-4 px-6">Referencia</th>
                            <th class="py-4 px-6 text-center">Estado</th>
                            <th class="py-4 px-6 text-right">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php foreach ($pagos as $p): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-6 font-medium"><?= e(date('d/m/Y', strtotime($p['fecha_pago']))) ?></td>
                            <td class="py-4 px-6 font-black text-on-surface text-base"><?= e(formatearMoneda($p['monto'])) ?></td>
                            <td class="py-4 px-6 font-mono text-xs text-slate-600"><?= e($p['referencia'] ?: 'Sin Referencia') ?></td>
                            <td class="py-4 px-6 text-center">
                                <?php
                                $badgeClass = 'bg-slate-100 text-slate-800'; // Default
                                if ($p['estado'] === 'PENDIENTE') {
                                    $badgeClass = 'bg-yellow-100 text-yellow-800';
                                } else if ($p['estado'] === 'EN REVISIÓN') {
                                    $badgeClass = 'bg-blue-100 text-blue-800';
                                } else if ($p['estado'] === 'APROBADO') {
                                    $badgeClass = 'bg-green-100 text-green-800';
                                } else if ($p['estado'] === 'RECHAZADO') {
                                    $badgeClass = 'bg-red-100 text-red-800';
                                }
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap <?= e($badgeClass) ?>">
                                    <?= e($p['estado']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <a href="/pagos/detalle/<?= e($p['id']) ?>" class="inline-flex items-center gap-1 bg-white hover:bg-slate-100 text-primary border border-outline-variant font-bold text-xs px-4 py-2 rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    Ver Detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
