<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de la página -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold">Historial de Pagos</h2>
            <p class="text-sm opacity-90 mt-1"><?= e($residente['nombre'] . ' ' . $residente['apellido']) ?> - Unidad <?= e($residente['unidad_numero'] ?? 'N/A') ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/residente/dashboard" class="bg-white/15 hover:bg-white/25 border border-white/20 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition-transform active:scale-95 flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">dashboard</span>
                Volver al Panel
            </a>
            <a href="/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-rose-600/80 hover:bg-rose-600 text-white p-2.5 rounded-xl transition-all flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </div>
    </div>

    <!-- Historial de Comprobantes -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
        <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
            <h3 class="text-lg font-bold text-on-surface">Todos los Comprobantes Enviados</h3>
            <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= count($comprobantes) ?></span>
        </div>

        <?php if (empty($comprobantes)): ?>
            <div class="text-center py-12 text-on-surface-variant">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-2">payments</span>
                <p class="font-semibold">No has reportado ningún pago todavía.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Periodo</th>
                            <th class="py-3 px-4">Monto</th>
                            <th class="py-3 px-4">Método</th>
                            <th class="py-3 px-4">Referencia</th>
                            <th class="py-3 px-4">Fecha Pago</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4">Fecha Envío</th>
                            <th class="py-3 px-4">Comprobante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php foreach ($comprobantes as $c): ?>
                        <tr class="hover:bg-background/40 transition-colors">
                            <td class="py-4 px-4 font-medium"><?= e(nombreMes($c['mes'])) ?> <?= e($c['anio']) ?></td>
                            <td class="py-4 px-4 font-bold text-on-surface"><?= e(formatearMoneda($c['monto'])) ?></td>
                            <td class="py-4 px-4 text-xs font-medium uppercase"><?= e(str_replace('_', ' ', $c['metodo_pago'])) ?></td>
                            <td class="py-4 px-4 font-mono text-xs"><?= e($c['referencia'] ?: '-') ?></td>
                            <td class="py-4 px-4"><?= e(date('d/m/Y', strtotime($c['fecha_pago']))) ?></td>
                            <td class="py-4 px-4">
                                <?= badgeEstado($c['estado']) ?>
                            </td>
                            <td class="py-4 px-4 text-xs text-on-surface-variant"><?= e(date('d/m/Y H:i', strtotime($c['fecha_envio']))) ?></td>
                            <td class="py-4 px-4">
                                <?php if ($c['archivo']): ?>
                                    <button onclick="abrirModal('/comprobante-proxy.php?file=<?= e($c['archivo']) ?>')" 
                                            class="w-12 h-12 rounded-lg border border-outline-variant overflow-hidden hover:scale-105 transition-transform flex items-center justify-center bg-background">
                                        <img src="/comprobante-proxy.php?file=<?= e($c['archivo']) ?>" alt="Comprobante" class="w-full h-full object-cover">
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-on-surface-variant">Sin imagen</span>
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

<!-- Modal para ver imágenes de comprobantes -->
<div id="modalComprobante" onclick="cerrarModal(event)" class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4">
    <button onclick="cerrarModalForce()" class="absolute top-4 right-4 text-white text-4xl hover:rotate-90 transition-transform">&times;</button>
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalForce();
        }
    });
</script>
