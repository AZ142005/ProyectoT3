<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de Identificación del Residente -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold"><?= e($residente['nombre'] . ' ' . $residente['apellido']) ?></h2>
            <p class="text-sm opacity-90 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                <span><strong>Unidad:</strong> <?= e($residente['unidad_numero'] ?? 'N/A') ?></span>
                <span><strong>Torre:</strong> <?= e($residente['torre'] ?? 'N/A') ?></span>
                <span><strong>Cédula:</strong> <?= e($residente['cedula'] ?? 'N/A') ?></span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/pagos" class="bg-white/20 hover:bg-white/30 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl border border-white/20 transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">payments</span>
                Gestionar Pagos (Módulo 3)
            </a>
            <a href="/logout" class="bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">logout</span>
                Salir
            </a>
        </div>
    </div>

    <!-- Grid de Estadísticas Financieras -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Tarjeta Deuda -->
        <div class="bg-white p-6 rounded-2xl border-l-4 border-red-500 shadow-sm hover:shadow-md transition-shadow flex justify-between items-center">
            <div>
                <span class="text-sm font-semibold text-on-surface-variant uppercase tracking-wider">Deuda Total</span>
                <div class="text-3xl font-black text-red-500 mt-1">
                    <?= e(formatearMoneda($total_deuda)) ?>
                </div>
            </div>
            <span class="material-symbols-outlined text-4xl text-red-500/20">account_balance_wallet</span>
        </div>

        <!-- Tarjeta Saldo a Favor -->
        <div class="bg-white p-6 rounded-2xl border-l-4 border-primary shadow-sm hover:shadow-md transition-shadow flex justify-between items-center">
            <div>
                <span class="text-sm font-semibold text-on-surface-variant uppercase tracking-wider">Saldo a Favor</span>
                <div class="text-3xl font-black text-primary mt-1">
                    <?= e(formatearMoneda($saldo_a_favor_mostrar)) ?>
                </div>
            </div>
            <span class="material-symbols-outlined text-4xl text-primary/20">savings</span>
        </div>
    </div>

    <!-- Sección: Facturas Pendientes -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8">
        <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
            <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">receipt_long</span>
                Facturas Pendientes
            </h3>
            <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full"><?= count($facturas_pendientes) ?></span>
        </div>

        <?php if (empty($facturas_pendientes)): ?>
            <div class="text-center py-12 text-on-surface-variant">
                <span class="material-symbols-outlined text-5xl text-primary/30 mb-2">check_circle</span>
                <p class="font-semibold text-primary">No tienes facturas pendientes</p>
                <div class="text-sm text-on-surface-variant mt-1">Su estado de cuenta está al día.</div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Periodo</th>
                            <th class="py-3 px-4">Monto</th>
                            <th class="py-3 px-4">Saldo</th>
                            <th class="py-3 px-4">Vencimiento</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php foreach ($facturas_pendientes as $f): 
                            $dias = diasHastaVencimiento($f['fecha_vencimiento']);
                            $moroso = $dias !== null && $dias < 0;
                        ?>
                        <tr class="hover:bg-background/40 transition-colors">
                            <td class="py-4 px-4 font-medium"><?= e(nombreMes($f['mes'])) ?> <?= e($f['anio']) ?></td>
                            <td class="py-4 px-4"><?= e(formatearMoneda($f['monto_total'])) ?></td>
                            <td class="py-4 px-4 font-bold <?= $moroso ? 'text-red-500' : 'text-yellow-600' ?>">
                                <?= e(formatearMoneda($f['saldo'])) ?>
                            </td>
                            <td class="py-4 px-4">
                                <div><?= e(date('d/m/Y', strtotime($f['fecha_vencimiento']))) ?></div>
                                <?php if ($moroso): ?>
                                    <span class="text-xs text-red-500 font-medium">Vencida hace <?= e(abs($dias)) ?>d</span>
                                <?php elseif ($dias !== null && $dias <= 5 && $dias >= 0): ?>
                                    <span class="text-xs text-yellow-600 font-medium">Vence en <?= e($dias) ?>d</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($moroso): ?>
                                    <span class="bg-red-50 text-red-700 text-xs px-2.5 py-1 rounded-full font-bold">Vencida</span>
                                <?php elseif ($f['tiene_pendiente'] > 0): ?>
                                    <span class="bg-yellow-50 text-yellow-700 text-xs px-2.5 py-1 rounded-full font-bold">Enviado</span>
                                <?php else: ?>
                                    <span class="bg-green-50 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if (!$f['tiene_pendiente']): ?>
                                    <a href="/residente/enviar-pago?factura=<?= e($f['id']) ?>" class="inline-flex items-center gap-1 bg-primary hover:bg-primary-hover text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-transform active:scale-95">
                                        <span class="material-symbols-outlined text-[14px]">payments</span>
                                        Pagar
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-on-surface-variant font-medium">Esperando aprobación</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sección: Comprobantes Recientes -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
        <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
            <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">history</span>
                Comprobantes Recientes
            </h3>
            <a href="/residente/historial" class="text-xs font-bold text-primary hover:text-primary-hover flex items-center gap-1">
                Ver todos
                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </div>

        <?php if (empty($comprobantes)): ?>
            <div class="text-center py-12 text-on-surface-variant">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-2">payments</span>
                <p class="font-semibold">No has enviado comprobantes de pago aún.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Factura</th>
                            <th class="py-3 px-4">Monto</th>
                            <th class="py-3 px-4">Método</th>
                            <th class="py-3 px-4">Fecha Pago</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4">Comprobante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php foreach ($comprobantes as $c): ?>
                        <tr class="hover:bg-background/40 transition-colors">
                            <td class="py-4 px-4 font-medium font-mono">#<?= e($c['numero_factura']) ?></td>
                            <td class="py-4 px-4"><?= e(formatearMoneda($c['monto'])) ?></td>
                            <td class="py-4 px-4 text-xs font-medium uppercase"><?= e(str_replace('_', ' ', $c['metodo_pago'])) ?></td>
                            <td class="py-4 px-4"><?= e(date('d/m/Y', strtotime($c['fecha_pago']))) ?></td>
                            <td class="py-4 px-4">
                                <?= badgeEstado($c['estado']) ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($c['archivo']): ?>
                                    <button onclick="abrirModal('/comprobante-proxy.php?file=<?= e($c['archivo']) ?>')" 
                                            class="w-12 h-12 rounded-lg border border-outline-variant overflow-hidden hover:scale-105 transition-transform flex items-center justify-center bg-background">
                                        <img src="/comprobante-proxy.php?file=<?= e($c['archivo']) ?>" alt="Recibo" class="w-full h-full object-cover">
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
