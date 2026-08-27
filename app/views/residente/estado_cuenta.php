<div class="max-w-5xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">account_balance_wallet</span>
                Mi Estado de Cuenta y Libro Mayor
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">Historial inmutable de cargos por cuotas y abonos por pagos aprobados.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/residente/estado-cuenta/imprimir" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-colors inline-flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-sm">print</span>
                <span class="hidden sm:inline">Imprimir</span>
            </a>
            <a href="/residente/dashboard" class="bg-slate-100 hover:bg-slate-200 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-colors inline-flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-sm">dashboard</span>
                Volver al Panel
            </a>
            <a href="/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-rose-50 hover:bg-rose-100 text-rose-600 p-2.5 rounded-xl border border-rose-200 transition-colors inline-flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-sm">logout</span>
            </a>
        </div>
    </div>

    <!-- Tarjeta de Saldo Consolidado -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide d-block mb-1">Inmueble / Unidad</span>
            <div class="text-xl font-bold text-on-surface">Apto/Unidad <?= e($unidad['numero'] ?? 'N/A') ?></div>
            <p class="text-xs text-slate-500 mt-1"><?= e($unidad['edificio_nombre'] ?? 'Sin Torre') ?></p>
        </div>

        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm md:col-span-2 <?= $saldoActual > 0 ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-emerald-500' ?>">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wide d-block mb-1">Saldo Actual Consolidado</span>
                    <div class="text-3xl font-bold font-monospace <?= $saldoActual > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                        Bs. <?= number_format($saldoActual, 2) ?>
                    </div>
                </div>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full <?= $saldoActual > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' ?>">
                    <?= $saldoActual > 0 ? 'Saldo Deudor Pendiente' : 'Solvente / Saldo a Favor' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Libro Mayor de Movimientos -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
        <h3 class="font-bold text-on-surface text-base mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">format_list_numbered</span>
            Libro Mayor de Movimientos
        </h3>

        <?php if (empty($movimientos)): ?>
            <div class="text-center py-12 text-slate-400">
                <span class="material-symbols-outlined text-5xl mb-2 d-block">receipt_long</span>
                No hay movimientos contables registrados para esta unidad habitacional.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-background text-xs font-bold text-slate-400 uppercase">
                            <th class="py-3 px-3">Fecha</th>
                            <th class="py-3 px-3">Tipo</th>
                            <th class="py-3 px-3">Descripción</th>
                            <th class="py-3 px-3 text-end">Monto</th>
                            <th class="py-3 px-3 text-end">Saldo Anterior</th>
                            <th class="py-3 px-3 text-end">Saldo Posterior</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background font-mono text-xs">
                        <?php foreach ($movimientos as $m): ?>
                            <tr class="hover:bg-slate-50 transition-colors font-sans">
                                <td class="py-3 px-3 text-xs text-slate-500 font-mono">
                                    <?= e(date('d/m/Y H:i', strtotime($m['fecha_movimiento']))) ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if ($m['tipo'] === 'cargo_factura'): ?>
                                        <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded uppercase">Cargo</span>
                                    <?php elseif ($m['tipo'] === 'abono_pago'): ?>
                                        <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded uppercase">Abono</span>
                                    <?php else: ?>
                                        <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded uppercase">Ajuste</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-on-surface">
                                    <?= e($m['descripcion']) ?>
                                </td>
                                <td class="py-3 px-3 text-end font-mono font-bold <?= $m['tipo'] === 'abono_pago' ? 'text-emerald-600' : 'text-red-600' ?>">
                                    <?= $m['tipo'] === 'abono_pago' ? '-' : '+' ?>Bs. <?= number_format($m['monto'], 2) ?>
                                </td>
                                <td class="py-3 px-3 text-end font-mono text-slate-400">
                                    Bs. <?= number_format($m['saldo_anterior'], 2) ?>
                                </td>
                                <td class="py-3 px-3 text-end font-mono font-bold text-on-surface">
                                    Bs. <?= number_format($m['saldo_posterior'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
