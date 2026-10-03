<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de la página -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold">Mis Pagos</h2>
            <p class="text-sm opacity-90 mt-1">Unidad <?= e($residente['unidad_numero'] ?? 'N/A') ?> (<?= e($residente['torre'] ?? 'N/A') ?>) — todos los movimientos de la unidad</p>
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

    <!-- Tabla unificada de movimientos (pagos + comprobantes de la unidad) -->
    <div class="bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">

        <?php if (empty($pagos)): ?>
            <div class="text-center py-20 text-on-surface-variant flex flex-col items-center">
                <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">payments</span>
                <p class="text-xl font-bold text-on-surface">Aún no hay pagos registrados para tu unidad</p>
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
                            <th class="py-4 px-6">Reportado por</th>
                            <th class="py-4 px-6 text-center">Estado</th>
                            <th class="py-4 px-6 text-center">Comprobante</th>
                            <th class="py-4 px-6 text-right">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php foreach ($pagos as $p): ?>
                        <?php
                            $esComprobante = ($p['tipo_origen'] ?? '') === 'comprobante';
                            $detalleHref = '/pagos/detalle/' . e($p['id']) . ($esComprobante ? '?tipo=comprobante' : '');
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-6 font-medium whitespace-nowrap"><?= e(date('d/m/Y', strtotime($p['fecha_pago']))) ?></td>
                            <td class="py-4 px-6 font-black text-on-surface text-base whitespace-nowrap"><?= e(formatearMoneda($p['monto'])) ?></td>
                            <td class="py-4 px-6 font-mono text-xs text-slate-600"><?= e($p['referencia'] ?: 'Sin Referencia') ?></td>
                            <td class="py-4 px-6">
                                <div class="font-medium text-on-surface"><?= e($p['residente_nombre'] ?: 'Pago directo (sin usuario)') ?></div>
                                <div class="text-[11px] text-slate-500"><?= $esComprobante ? 'Formulario del residente' : 'Portal público / administración' ?></div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?= badgeEstado($p['estado']) ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php if (!empty($p['archivo'])): ?>
                                    <button type="button" onclick="abrirModal('/comprobante-proxy.php?file=<?= e($p['archivo']) ?>')"
                                            class="w-10 h-10 rounded-lg border border-outline-variant overflow-hidden hover:scale-105 transition-transform inline-flex items-center justify-center bg-background" title="Ver comprobante">
                                        <img src="/comprobante-proxy.php?file=<?= e($p['archivo']) ?>" alt="Comprobante" class="w-full h-full object-cover">
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-on-surface-variant">Sin imagen</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <a href="<?= e($detalleHref) ?>" class="inline-flex items-center gap-1 bg-white hover:bg-slate-100 text-primary border border-outline-variant font-bold text-xs px-4 py-2 rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    Ver Detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php include VIEWS_PATH . '/components/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para ver imágenes de comprobantes -->
<div id="modalComprobante" onclick="cerrarModal(event)" class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4">
    <button type="button" onclick="cerrarModalForce()" class="absolute top-4 right-4 text-white text-4xl hover:rotate-90 transition-transform">&times;</button>
    <img id="modalImg" src="" alt="Comprobante ampliado" class="max-w-full max-h-[90vh] rounded-xl shadow-2xl">
</div>

<script>
    function abrirModal(src) {
        document.getElementById('modalImg').src = src;
        const modal = document.getElementById('modalComprobante');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function cerrarModal(event) {
        if (event.target === event.currentTarget) {
            cerrarModalForce();
        }
    }

    function cerrarModalForce() {
        const modal = document.getElementById('modalComprobante');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrarModalForce();
        }
    });
</script>
