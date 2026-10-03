<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de Identificación del Residente -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4 border-b-4 border-institutional-brown">
        <div>
            <h2 class="text-2xl font-bold"><?= e($residente['nombre'] . ' ' . $residente['apellido']) ?></h2>
            <p class="text-sm opacity-90 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                <span><strong>Unidad:</strong> <?= e($residente['unidad_numero'] ?? 'N/A') ?></span>
                <span><strong>Torre:</strong> <?= e($residente['torre'] ?? 'N/A') ?></span>
                <span><strong>Cédula:</strong> <?= e($residente['cedula'] ?? 'N/A') ?></span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/perfil" class="bg-white/20 hover:bg-white/30 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl border border-white/20 transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">account_circle</span>
                Mi Perfil
            </a>
            <a href="/pagos" class="bg-white/20 hover:bg-white/30 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl border border-white/20 transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">payments</span>
                Gestionar Pagos
            </a>
            <a href="/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-rose-600/80 hover:bg-rose-600 text-white p-2.5 rounded-xl transition-all flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
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

    <!-- Sección: Comunicados -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
        <div class="pb-4 border-b border-background mb-6">
            <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">campaign</span>
                Comunicados
            </h3>
        </div>

        <?php if (empty($comunicados)): ?>
            <div class="py-12 text-center">
                <span class="material-symbols-outlined text-6xl text-on-surface-variant/30 mb-3 d-block">verified</span>
                <h3 class="text-lg font-bold text-on-surface mb-1">¡Sin avisos pendientes!</h3>
                <p class="text-sm text-on-surface-variant">No hay comunicados recientes publicados para su edificio o comunidad.</p>
            </div>
        <?php else: ?>
            <div class="max-h-96 overflow-y-auto divide-y divide-background">
                <?php foreach ($comunicados as $c): ?>
                <div class="py-4">
                    <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if ($c['nivel_urgencia'] === 'urgente'): ?>
                                <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Urgente</span>
                            <?php elseif ($c['nivel_urgencia'] === 'importante'): ?>
                                <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full uppercase">Importante</span>
                            <?php else: ?>
                                <span class="bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Normal</span>
                            <?php endif; ?>

                            <span class="text-xs text-on-surface-variant"><?= date('d/m/Y H:i', strtotime($c['fecha_publicacion'])) ?></span>
                        </div>
                        <span class="text-xs font-bold text-primary bg-primary/10 px-3 py-1 rounded-lg">
                            <?= e($c['edificio_nombre']) ?>
                        </span>
                    </div>

                    <h4 class="font-bold text-on-surface"><?= e($c['titulo']) ?></h4>

                    <?php
                    $textoComunicado = trim(preg_replace('/\s+/', ' ', strip_tags($c['contenido'])));
                    $extractoComunicado = mb_substr($textoComunicado, 0, 180);
                    if (mb_strlen($textoComunicado) > 180) {
                        $extractoComunicado .= '...';
                    }
                    ?>
                    <p class="text-sm text-on-surface-variant mt-1"><?= e($extractoComunicado) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
