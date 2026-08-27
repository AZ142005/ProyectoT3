<div class="max-w-5xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">analytics</span>
                Rendición de Cuentas y Justificación de Gastos
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">Transparencia comunitaria: desglose de egresos con facturas y soportes digitales.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-2 rounded-xl">
                Período: <?= e($mes) ?>/<?= e($anio) ?>
            </span>
            <a href="/residente/dashboard" class="bg-slate-100 hover:bg-slate-200 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-colors inline-flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-sm">dashboard</span>
                Volver al Panel
            </a>
            <a href="/logout" onclick="return confirm('¿Está seguro de que desea cerrar sesión?');" class="bg-rose-50 hover:bg-rose-100 text-rose-600 p-2.5 rounded-xl border border-rose-200 transition-colors inline-flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-sm">logout</span>
            </a>
        </div>
    </div>

    <!-- Tarjetas de Resumen Financiero y Alícuota -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide d-block mb-1">Gasto Total del Condominio</span>
            <div class="text-2xl font-bold text-on-surface">Bs. <?= number_format($totalMes, 2) ?></div>
            <p class="text-xs text-slate-500 mt-2">Suma de todos los gastos comunes aprobados.</p>
        </div>

        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide d-block mb-1">Unidades Habitacionales Activas</span>
            <div class="text-2xl font-bold text-primary"><?= e($unidadesActivas) ?> Apartamentos</div>
            <p class="text-xs text-slate-500 mt-2">Base comunitaria de distribución equitativa.</p>
        </div>

        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm border-l-4 border-l-primary">
            <span class="text-xs font-bold text-primary uppercase tracking-wide d-block mb-1">Cuota Alícuota por Unidad</span>
            <div class="text-2xl font-bold text-primary">Bs. <?= number_format($alicuotaEstimada, 2) ?></div>
            <p class="text-xs text-slate-500 mt-2">Monto estimado correspondiente a su residencia.</p>
        </div>
    </div>

    <!-- Desglose de Gastos Comunes -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm mb-8">
        <h3 class="font-bold text-on-surface text-base mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">receipt</span>
            Facturas y Egresos Detallados del Período
        </h3>

        <?php if (empty($gastos)): ?>
            <div class="text-center py-12 text-slate-400">
                <span class="material-symbols-outlined text-5xl mb-2 d-block">verified</span>
                No hay egresos registrados para el período seleccionado.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-background text-xs font-bold text-slate-400 uppercase">
                            <th class="py-3 px-3">Categoría</th>
                            <th class="py-3 px-3">Proveedor / Concepto</th>
                            <th class="py-3 px-3 text-center">Fecha</th>
                            <th class="py-3 px-3 text-end">Monto Total</th>
                            <th class="py-3 px-3 text-center">Soporte Digital</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php foreach ($gastos as $g): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-3">
                                    <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full text-white" style="background-color: <?= e($g['categoria_color']) ?>;">
                                        <span class="material-symbols-outlined text-sm"><?= e($g['categoria_icono']) ?></span>
                                        <?= e($g['categoria_nombre']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-on-surface"><?= e($g['proveedor']) ?></div>
                                    <small class="text-slate-500"><?= e($g['descripcion']) ?> (Fac: <?= e($g['nro_factura_proveedor'] ?: 'S/N') ?>)</small>
                                </td>
                                <td class="py-3 px-3 text-center text-xs text-slate-500">
                                    <?= e(date('d/m/Y', strtotime($g['fecha_gasto']))) ?>
                                </td>
                                <td class="py-3 px-3 text-end font-monospace font-bold text-on-surface">
                                    Bs. <?= number_format($g['monto_total'], 2) ?>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <?php if (!empty($g['soporte_digital'])): ?>
                                        <button type="button" onclick="verSoporte('/uploads/soportes/<?= e($g['soporte_digital']) ?>', '<?= e($g['proveedor']) ?>')" class="bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">description</span> Ver Factura
                                        </button>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">Sin Soporte</span>
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

<!-- Modal Visor de Soporte Digital -->
<div id="modalVisorSoporte" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-background flex items-center justify-between bg-slate-50">
            <h3 id="visorTitulo" class="font-bold text-on-surface text-base">Soporte Digital de Gasto</h3>
            <button type="button" onclick="cerrarModalVisor()" class="text-slate-400 hover:text-on-surface">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-4 flex-1 overflow-auto flex items-center justify-center min-h-[400px]">
            <iframe id="visorFrame" src="" class="w-full h-[500px] border-0 rounded-xl"></iframe>
        </div>
    </div>
</div>

<script>
    function verSoporte(url, proveedor) {
        document.getElementById('visorTitulo').innerText = 'Factura / Soporte: ' + proveedor;
        document.getElementById('visorFrame').src = url;
        document.getElementById('modalVisorSoporte').classList.remove('hidden');
    }

    function cerrarModalVisor() {
        document.getElementById('modalVisorSoporte').classList.add('hidden');
        document.getElementById('visorFrame').src = '';
    }
</script>
