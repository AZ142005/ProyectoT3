<div class="flex flex-grow min-h-screen w-full">
    <?php $activeRoute = 'pagos'; require VIEWS_PATH . '/layouts/admin_sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="flex-grow flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Administración y Auditoría de Pagos</h1>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="p-6 overflow-y-auto w-full">
            <!-- Mensajes de Alerta -->
            <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

            <!-- Barra de Filtros Ultra-Compacta (Toolbar Permanente) -->
            <div class="bg-white rounded-2xl border border-outline-variant p-2.5 shadow-sm mb-4">
                <form method="GET" action="/pagos" class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1 text-primary shrink-0 pe-2 border-r border-slate-100 hidden sm:flex">
                        <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Filtros</span>
                    </div>

                    <!-- Estado -->
                    <div class="flex-1 sm:flex-initial min-w-[150px]">
                        <select name="estado" id="estado" aria-label="Estado" class="w-full px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                            <option value="">Todos los estados</option>
                            <option value="PENDIENTE" <?= ($filtros['estado'] === 'PENDIENTE') ? 'selected' : '' ?>>Pendientes</option>
                            <option value="EN REVISIÓN" <?= ($filtros['estado'] === 'EN REVISIÓN') ? 'selected' : '' ?>>En revisión</option>
                            <option value="APROBADO" <?= ($filtros['estado'] === 'APROBADO') ? 'selected' : '' ?>>Aprobados</option>
                            <option value="RECHAZADO" <?= ($filtros['estado'] === 'RECHAZADO') ? 'selected' : '' ?>>Rechazados</option>
                        </select>
                    </div>

                    <!-- Edificio -->
                    <div class="flex-1 sm:flex-initial min-w-[180px]">
                        <select name="edificio" id="edificio" aria-label="Edificio / Torre" class="w-full px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                            <option value="">Todos los edificios</option>
                            <?php foreach ($edificios as $ed): ?>
                                <option value="<?= e($ed['id']) ?>" <?= ($filtros['edificio'] == $ed['id']) ? 'selected' : '' ?>><?= e($ed['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fecha -->
                    <div class="w-full sm:w-auto">
                        <input type="date" name="fecha" id="fecha" value="<?= e($filtros['fecha']) ?>" aria-label="Fecha de Pago" title="Fecha de Pago"
                               class="w-full sm:w-auto px-2.5 py-1.5 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
                    </div>

                    <!-- Botones de Acción Inline -->
                    <div class="flex items-center gap-1.5 ms-auto">
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-3 py-1.5 rounded-xl shadow-sm text-xs transition-all flex items-center justify-center gap-1 active:scale-95">
                            <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                            Filtrar
                        </button>
                        <a href="/pagos" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-1.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1" title="Limpiar Filtros">
                            <span class="material-symbols-outlined text-[16px]">clear_all</span>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Pagos -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="flex justify-between items-center pb-4 border-b border-background mb-6">
                    <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">payments</span>
                        Lista General de Pagos
                    </h3>
                    <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacion['total']) ?> Registros</span>
                </div>

                <?php if (empty($pagos)): ?>
                    <div class="text-center py-12 text-on-surface-variant">
                        <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-3" style="font-size: 56px;">payments</span>
                        <p class="font-bold text-on-surface">No se encontraron pagos.</p>
                        <p class="text-xs text-on-surface-variant mt-1">Pruebe cambiando los filtros aplicados arriba.</p>
                    </div>
                <?php else: ?>
                    <form id="formAprobacionMasiva" action="/admin/pagos/aprobar-masivo" method="POST" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
                        <?= csrf_field() ?>

                        <!-- Barra Flotante de Acciones Masivas -->
                        <div id="barraMasiva" class="d-none bg-primary text-white p-3 rounded-lg mb-3 flex items-center justify-between shadow-md">
                            <div class="flex items-center gap-2 font-bold text-sm">
                                <span class="material-symbols-outlined">check_box</span>
                                <span id="contadorSeleccionados">0</span> pago(s) seleccionado(s) (Máximo 50)
                            </div>
                            <button type="submit" class="bg-white text-primary font-bold px-4 py-2 rounded-lg hover:bg-slate-100 transition-colors text-xs flex items-center gap-1 shadow-sm" onclick="return confirm('¿Está seguro de aprobar todos los pagos seleccionados?');">
                                <span class="material-symbols-outlined text-sm">done_all</span>
                                Aprobar Seleccionados
                            </button>
                        </div>

                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                                <thead>
                                    <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                        <th class="py-3 px-4 text-center w-10">
                                            <input type="checkbox" id="checkAllPagos" class="rounded border-slate-300 text-primary focus:ring-primary cursor-pointer" onclick="toggleAllPagos(this)">
                                        </th>
                                        <th class="py-3 px-4">Residente</th>
                                        <th class="py-3 px-4">Unidad</th>
                                        <th class="py-3 px-4">Monto / Ref.</th>
                                        <th class="py-3 px-4 text-center">Estado</th>
                                        <th class="py-3 px-4 text-right min-w-[250px]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-background">
                                    <?php foreach ($pagos as $p): ?>
                                    <tr class="hover:bg-background/40 transition-colors">
                                        <td class="py-4 px-4 text-center">
                                            <?php if (in_array($p['estado'], ['PENDIENTE', 'EN REVISIÓN'])): ?>
                                                <input type="checkbox" name="pago_ids[]" value="<?= e($p['id']) ?>" class="pago-checkbox rounded border-slate-300 text-primary focus:ring-primary cursor-pointer" onchange="actualizarBarraMasiva()">
                                            <?php else: ?>
                                                <span class="text-slate-300 material-symbols-outlined text-sm">block</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-semibold text-on-surface"><?= e($p['residente_nombre']) ?></div>
                                            <div class="text-xs text-on-surface-variant"><?= e(date('d/m/Y', strtotime($p['fecha_pago']))) ?></div>
                                        </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-on-surface"><?= e($p['edificio_nombre']) ?></div>
                                        <div class="text-xs text-on-surface-variant">Unidad: <?= e($p['unidad_numero']) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-black text-primary text-base"><?= e(formatearMoneda($p['monto'])) ?></div>
                                        <div class="text-[10px] font-mono text-on-surface-variant"><?= e($p['referencia'] ?: 'S/R') ?></div>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?= badgeEstado($p['estado']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center justify-end gap-1.5">
                                            
                                            <?php 
                                            $detalleHref = ($p['tipo_origen'] ?? '') === 'comprobante'
                                                ? '/admin/comprobante/verificar?id=' . e($p['id']) . '&from=comprobantes'
                                                : '/pagos/detalle/' . e($p['id']) . '?from=comprobantes';
                                            ?>
                                            <a href="<?= e($detalleHref) ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition-colors border border-slate-200" title="Ver Detalle / Auditoría">
                                                <span class="material-symbols-outlined text-[18px] block">visibility</span>
                                            </a>
                                            
                                            <?php if (!empty($p['archivo'])): ?>
                                            <a href="/comprobante-proxy.php?file=<?= e($p['archivo']) ?>&download=1" download="<?= e($p['archivo']) ?>" class="p-1.5 bg-slate-100 hover:bg-primary hover:text-white text-slate-600 rounded-lg transition-colors border border-slate-200" title="Descargar Comprobante">
                                                <span class="material-symbols-outlined text-[18px] block">download</span>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <!-- Formulario Inline para En Revisión (Rápido) -->
                                            <?php if (in_array($p['estado'], ['PENDIENTE'])): ?>
                                            <form method="POST" action="/pagos/cambiar-estado" class="inline" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="pago_id" value="<?= e($p['id']) ?>">
                                                <input type="hidden" name="nuevo_estado" value="EN REVISIÓN">
                                                <button type="submit" class="bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors" title="Poner en revisión">
                                                    En revisión
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <!-- Formulario Inline para Aprobar (Rápido) -->
                                            <?php if (in_array($p['estado'], ['PENDIENTE', 'EN REVISIÓN'])): ?>
                                            <form method="POST" action="/pagos/cambiar-estado" class="inline" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="pago_id" value="<?= e($p['id']) ?>">
                                                <input type="hidden" name="nuevo_estado" value="APROBADO">
                                                <button type="submit" class="bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors" title="Aprobar pago">
                                                    Aprobar
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <!-- Botón para Rechazar (Abre Modal pidiendo motivo) -->
                                            <?php if (in_array($p['estado'], ['PENDIENTE', 'EN REVISIÓN'])): ?>
                                            <button type="button" onclick="openRechazarModal(<?= e($p['id']) ?>)" class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors" title="Rechazar pago">
                                                Rechazar
                                            </button>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </form>
                <?php endif; ?>
                
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Simple para Motivo de Rechazo -->
<div id="modalRechazo" class="hidden fixed inset-0 bg-black/60 items-center justify-center p-4 z-50 transition-opacity">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl overflow-hidden border border-outline-variant">
        <form method="POST" action="/pagos/cambiar-estado" id="formRechazo" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
            <!-- CSRF Token Obligatorio -->
            <?= csrf_field() ?>
            <input type="hidden" name="pago_id" id="rechazoPagoId" value="">
            <input type="hidden" name="nuevo_estado" value="RECHAZADO">
            
            <div class="p-5 border-b border-background flex justify-between items-center bg-red-50">
                <h3 class="font-bold text-red-700 flex items-center gap-1.5 text-base">
                    <span class="material-symbols-outlined">cancel</span>
                    Rechazar Pago
                </h3>
            </div>
            
            <div class="p-5 flex flex-col gap-3">
                <p class="text-xs text-on-surface-variant mb-2">Por favor, indique el motivo obligatorio por el cual está rechazando este comprobante de pago.</p>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase">Motivo del rechazo <span class="text-red-500">*</span></label>
                    <textarea name="motivo" id="rechazoMotivo" rows="3" required placeholder="Ej: Imagen borrosa, monto incorrecto..."
                              class="w-full px-3 py-2 bg-background border border-outline-variant rounded-xl text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all resize-none"></textarea>
                </div>
            </div>
            
            <div class="p-4 border-t border-background flex justify-end gap-2 bg-slate-50">
                <button type="button" onclick="closeRechazarModal()" class="bg-slate-200 hover:bg-slate-300 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-all cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-md transition-all cursor-pointer">
                    Confirmar Rechazo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRechazarModal(pagoId) {
        document.getElementById('rechazoPagoId').value = pagoId;
        document.getElementById('rechazoMotivo').value = '';
        const modal = document.getElementById('modalRechazo');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeRechazarModal() {
        const modal = document.getElementById('modalRechazo');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function toggleAllPagos(source) {
        const checkboxes = document.querySelectorAll('.pago-checkbox');
        let count = 0;
        checkboxes.forEach(cb => {
            if (count < 50 || !source.checked) {
                cb.checked = source.checked;
                if (source.checked) count++;
            }
        });
        actualizarBarraMasiva();
    }

    function actualizarBarraMasiva() {
        const checkboxes = document.querySelectorAll('.pago-checkbox:checked');
        const barra = document.getElementById('barraMasiva');
        const contador = document.getElementById('contadorSeleccionados');
        
        if (checkboxes.length > 50) {
            alert('Solo puede seleccionar un máximo de 50 pagos por lote.');
            // Desmarcar los excedentes
            for (let i = 50; i < checkboxes.length; i++) {
                checkboxes[i].checked = false;
            }
        }
        
        const total = document.querySelectorAll('.pago-checkbox:checked').length;
        if (contador) contador.innerText = total;

        if (barra) {
            if (total > 0) {
                barra.classList.remove('d-none');
                barra.classList.add('flex');
            } else {
                barra.classList.add('d-none');
                barra.classList.remove('flex');
            }
        }
    }
</script>
