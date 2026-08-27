<div class="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">campaign</span>
                Cartelera Digital del Condominio
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">Avisos oficiales y comunicaciones dirigidas a su residencia.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/residente/dashboard" class="bg-slate-100 hover:bg-slate-200 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-colors inline-flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-sm">dashboard</span>
                Volver al Panel
            </a>
            <a href="/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-rose-50 hover:bg-rose-100 text-rose-600 p-2.5 rounded-xl border border-rose-200 transition-colors inline-flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-sm">logout</span>
            </a>
        </div>
    </div>

    <!-- Lista de Comunicados -->
    <?php if (empty($comunicados)): ?>
        <div class="bg-white rounded-2xl border border-outline-variant p-12 text-center shadow-sm">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-3 d-block">verified</span>
            <h3 class="text-lg font-bold text-on-surface mb-1">¡Sin avisos pendientes!</h3>
            <p class="text-sm text-slate-500">No hay comunicados recientes publicados para su edificio o comunidad.</p>
        </div>
    <?php else: ?>
        <div class="flex flex-col gap-6">
            <?php foreach ($comunicados as $c): ?>
                <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3 border-b border-background pb-3">
                        <div class="flex items-center gap-2">
                            <?php if ($c['nivel_urgencia'] === 'urgente'): ?>
                                <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Urgente</span>
                            <?php elseif ($c['nivel_urgencia'] === 'importante'): ?>
                                <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full uppercase">Importante</span>
                            <?php else: ?>
                                <span class="bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Normal</span>
                            <?php endif; ?>

                            <span class="text-xs text-slate-400">Publicado: <?= date('d/m/Y H:i', strtotime($c['fecha_publicacion'])) ?></span>
                        </div>
                        <span class="text-xs font-bold text-primary bg-primary/10 px-3 py-1 rounded-lg">
                            <?= e($c['edificio_nombre']) ?>
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-on-surface mb-3"><?= e($c['titulo']) ?></h3>
                    
                    <div class="text-sm text-on-surface-variant leading-relaxed text-justify">
                        <?php
                        $allowed = '<b><strong><i><em><u><ul><ol><li><p><br>';
                        $safe = strip_tags($c['contenido'], $allowed);
                        $safe = preg_replace('/<(\w+)\s+[^>]*on\w+\s*=/i', '<$1', $safe);
                        echo nl2br(e($safe));
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginación -->
        <div class="mt-8">
            <?php include VIEWS_PATH . '/components/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>
