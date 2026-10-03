<div class="flex flex-1 min-h-screen w-full">
    <?php $activeRoute = 'comunicados'; require VIEWS_PATH . '/layouts/admin_sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Comunicados y Avisos</h1>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Tabla de Comunicados -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h5 class="text-lg font-bold text-on-surface mb-0">Historial de Comunicados Emitidos</h5>
                <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacion['total']) ?> Publicados</span>
            </div>
            <button type="button" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalNuevoComunicado">
                <span class="material-symbols-outlined text-[16px]">add_comment</span>
                <span>Nuevo Comunicado</span>
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Título</th>
                            <th class="py-3 px-4">Alcance / Destino</th>
                            <th class="py-3 px-4 text-center">Urgencia</th>
                            <th class="py-3 px-4">Publicado por</th>
                            <th class="py-3 px-4">Fecha de Emisión</th>
                            <th class="py-3 px-4 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php if (empty($comunicados)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">campaign</span>
                                    No se han publicado comunicados en la cartelera digital aún.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($comunicados as $c): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-semibold text-on-surface">
                                        <?= e($c['titulo']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if ($c['unidad_numero']): ?>
                                            <span class="badge bg-light text-on-surface border">
                                                Apto <?= e($c['unidad_numero']) ?> (<?= e($c['edificio_nombre']) ?>)
                                            </span>
                                        <?php elseif ($c['edificio_id']): ?>
                                            <span class="badge bg-info text-on-surface">
                                                Edificio <?= e($c['edificio_nombre']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                🌐 Todo el Condominio
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if ($c['nivel_urgencia'] === 'urgente'): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1">Urgente</span>
                                        <?php elseif ($c['nivel_urgencia'] === 'importante'): ?>
                                            <span class="badge bg-warning text-on-surface rounded-pill px-3 py-1">Importante</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-3 py-1">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs"><?= e($c['admin_nombre']) ?></td>
                                    <td class="py-4 px-4 text-xs text-on-surface-variant"><?= date('d/m/Y H:i', strtotime($c['fecha_publicacion'])) ?></td>
                                    <td class="py-4 px-4 text-end">
                                        <form method="POST" action="/admin/comunicados/eliminar" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este comunicado de la cartelera?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm border-0" title="Eliminar Comunicado">
                                                <span class="material-symbols-outlined align-middle fs-6">delete</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="p-3 border-top bg-light">
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear Comunicado -->
<div class="modal fade" id="modalNuevoComunicado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form method="POST" action="/admin/comunicados/guardar">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">add_campaign</span>
                        Publicar Nuevo Comunicado en Cartelera
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Columna izquierda: formulario -->
                        <div class="col-lg-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-on-surface-variant">Título del Comunicado <span class="text-danger">*</span></label>
                                <input type="text" name="titulo" required placeholder="Ej: Mantenimiento programado del tanque de agua" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-on-surface-variant">Dirigido a Edificio / Torre</label>
                                    <select name="edificio_id" class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                                        <option value="">-- Todos los Edificios (Global) --</option>
                                        <?php foreach ($edificios as $ed): ?>
                                            <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-on-surface-variant">Nivel de Urgencia <span class="text-danger">*</span></label>
                                    <select name="nivel_urgencia" required class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors cursor-pointer">
                                        <option value="normal">Normal (Información habitual)</option>
                                        <option value="importante">Importante (Resaltado)</option>
                                        <option value="urgente">Urgente (Notificación al instante)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-on-surface-variant">Contenido del Aviso <span class="text-danger">*</span></label>
                                <textarea name="contenido" rows="5" required placeholder="Escriba los detalles del comunicado..." class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors"></textarea>
                            </div>

                            <div class="form-check form-switch p-3 bg-background rounded-xl border border-outline-variant">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="enviar_email" value="1" id="checkEnviarEmail">
                                <label class="form-check-label fw-bold text-on-surface" for="checkEnviarEmail">
                                    Enviar también por correo electrónico a los residentes seleccionados
                                </label>
                            </div>
                        </div>

                        <!-- Columna derecha: vista previa para residentes -->
                        <div class="col-lg-5">
                            <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-2">Vista previa para residentes</p>
                            <div id="previewComunicado" class="bg-white rounded-2xl border border-outline-variant p-5 shadow-sm">
                                <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span id="previewUrgencia" class="bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Normal</span>
                                        <span id="previewFecha" class="text-xs text-on-surface-variant">--/--/---- --:--</span>
                                    </div>
                                    <span id="previewEdificio" class="text-xs font-bold text-primary bg-primary/10 px-3 py-1 rounded-lg">Todos los Edificios</span>
                                </div>
                                <h4 id="previewTitulo" class="font-bold text-on-surface">El título del comunicado aparecerá aquí</h4>
                                <p id="previewContenido" class="text-sm text-on-surface-variant mt-1" style="white-space: pre-wrap;">El contenido del comunicado aparecerá aquí.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">send</span>
                        Publicar Comunicado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalNuevoComunicado');
    if (!modal) {
        return;
    }

    const form = modal.querySelector('form');
    const inputTitulo = form.querySelector('[name="titulo"]');
    const inputContenido = form.querySelector('[name="contenido"]');
    const selectUrgencia = form.querySelector('[name="nivel_urgencia"]');
    const selectEdificio = form.querySelector('[name="edificio_id"]');

    const previewTitulo = document.getElementById('previewTitulo');
    const previewContenido = document.getElementById('previewContenido');
    const previewUrgencia = document.getElementById('previewUrgencia');
    const previewFecha = document.getElementById('previewFecha');
    const previewEdificio = document.getElementById('previewEdificio');

    const PLACEHOLDER_TITULO = 'El título del comunicado aparecerá aquí';
    const PLACEHOLDER_CONTENIDO = 'El contenido del comunicado aparecerá aquí.';

    function formatearFecha(fecha) {
        const pad = function (numero) {
            return String(numero).padStart(2, '0');
        };
        return pad(fecha.getDate()) + '/' + pad(fecha.getMonth() + 1) + '/' + fecha.getFullYear()
            + ' ' + pad(fecha.getHours()) + ':' + pad(fecha.getMinutes());
    }

    function actualizarTitulo() {
        const valor = inputTitulo.value.trim();
        if (valor === '') {
            previewTitulo.textContent = PLACEHOLDER_TITULO;
            previewTitulo.className = 'font-bold text-on-surface-variant/60';
        } else {
            previewTitulo.textContent = valor;
            previewTitulo.className = 'font-bold text-on-surface';
        }
    }

    function actualizarContenido() {
        const valor = inputContenido.value.trim();
        if (valor === '') {
            previewContenido.textContent = PLACEHOLDER_CONTENIDO;
            previewContenido.className = 'text-sm text-on-surface-variant/60 mt-1';
        } else {
            previewContenido.textContent = inputContenido.value;
            previewContenido.className = 'text-sm text-on-surface-variant mt-1';
        }
    }

    function actualizarUrgencia() {
        const clasesBase = 'text-xs font-bold px-3 py-1 rounded-full uppercase';
        if (selectUrgencia.value === 'urgente') {
            previewUrgencia.className = 'bg-red-100 text-red-700 ' + clasesBase;
            previewUrgencia.textContent = 'Urgente';
        } else if (selectUrgencia.value === 'importante') {
            previewUrgencia.className = 'bg-amber-100 text-amber-800 ' + clasesBase;
            previewUrgencia.textContent = 'Importante';
        } else {
            previewUrgencia.className = 'bg-slate-100 text-slate-700 ' + clasesBase;
            previewUrgencia.textContent = 'Normal';
        }
    }

    function actualizarEdificio() {
        if (selectEdificio.value === '') {
            previewEdificio.textContent = 'Todos los Edificios';
        } else {
            previewEdificio.textContent = selectEdificio.options[selectEdificio.selectedIndex].text;
        }
    }

    function actualizarFecha() {
        previewFecha.textContent = formatearFecha(new Date());
    }

    function actualizarPreview() {
        actualizarTitulo();
        actualizarContenido();
        actualizarUrgencia();
        actualizarEdificio();
        actualizarFecha();
    }

    inputTitulo.addEventListener('input', actualizarTitulo);
    inputContenido.addEventListener('input', actualizarContenido);
    selectUrgencia.addEventListener('change', actualizarUrgencia);
    selectEdificio.addEventListener('change', actualizarEdificio);

    modal.addEventListener('shown.bs.modal', actualizarPreview);
    document.addEventListener('DOMContentLoaded', actualizarPreview);
    actualizarPreview();
})();
</script>
