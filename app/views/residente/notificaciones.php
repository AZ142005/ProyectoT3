<div class="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">notifications</span>
                Bandeja de Notificaciones
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">Historial de alertas y avisos del sistema.</p>
        </div>
        <span id="badgeNoLeidas" class="bg-primary text-white font-bold text-xs px-3 py-1.5 rounded-full">
            <?= e($noLeidas) ?> sin leer
        </span>
    </div>

    <!-- Lista de Notificaciones -->
    <?php if (empty($notificaciones)): ?>
        <div class="bg-white rounded-2xl border border-outline-variant p-12 text-center shadow-sm">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-3 d-block">notifications_off</span>
            <h3 class="text-lg font-bold text-on-surface mb-1">Sin notificaciones</h3>
            <p class="text-sm text-slate-500">No posee notificaciones registradas en su bandeja.</p>
        </div>
    <?php else: ?>
        <div class="flex flex-col gap-4">
            <?php foreach ($notificaciones as $n): ?>
                <div id="notif-card-<?= e($n['id']) ?>" class="bg-white rounded-2xl border border-outline-variant p-5 shadow-sm flex items-start justify-between gap-4 <?= $n['leido'] ? 'opacity-75' : 'border-l-4 border-l-primary' ?>">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 <?= $n['tipo'] === 'success' ? 'bg-emerald-100 text-emerald-700' : ($n['tipo'] === 'danger' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                            <span class="material-symbols-outlined text-[22px]">
                                <?= $n['tipo'] === 'success' ? 'check_circle' : ($n['tipo'] === 'danger' ? 'cancel' : 'info') ?>
                            </span>
                        </div>
                        <div>
                            <h4 class="font-bold text-on-surface text-base mb-1"><?= e($n['titulo']) ?></h4>
                            <p class="text-sm text-on-surface-variant mb-2"><?= e($n['mensaje']) ?></p>
                            <span class="text-xs text-slate-400 d-block"><?= date('d/m/Y H:i', strtotime($n['fecha_registro'])) ?></span>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <?php if (!empty($n['enlace'])): ?>
                            <a href="<?= e($n['enlace']) ?>" class="bg-slate-100 hover:bg-slate-200 text-on-surface text-xs font-bold px-3 py-1.5 rounded-lg transition-colors">
                                Ver Detalle
                            </a>
                        <?php endif; ?>

                        <?php if (!$n['leido']): ?>
                            <button type="button" onclick="marcarLeida(<?= e($n['id']) ?>)" class="text-xs font-bold text-primary hover:underline cursor-pointer">
                                Marcar como leída
                            </button>
                        <?php endif; ?>
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

<script>
    function marcarLeida(id) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch('/residente/notificaciones/marcar-leida', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById('notif-card-' + id);
                if (card) {
                    card.classList.remove('border-l-4', 'border-l-primary');
                    card.classList.add('opacity-75');
                }
                const badge = document.getElementById('badgeNoLeidas');
                if (badge) {
                    badge.innerText = data.no_leidas + ' sin leer';
                }
            }
        })
        .catch(err => console.error(err));
    }
</script>
