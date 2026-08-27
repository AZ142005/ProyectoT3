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
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-primary btn-sm font-weight-bold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoComunicado">
                    <span class="material-symbols-outlined fs-6">add_comment</span>
                    <span class="hidden sm:inline">Nuevo Comunicado</span>
                </button>
                <a href="/perfil" class="text-slate-600 hover:text-primary font-bold text-xs px-3 py-2 rounded-lg border border-slate-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">account_circle</span>
                    <span class="hidden sm:inline">Perfil</span>
                </a>
                <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                </a>
            </div>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Tabla de Comunicados -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Historial de Comunicados Emitidos</h5>
            <span class="badge bg-primary rounded-pill"><?= e($paginacion['total']) ?> Publicados</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Título</th>
                            <th class="py-3">Alcance / Destino</th>
                            <th class="py-3 text-center">Urgencia</th>
                            <th class="py-3">Publicado por</th>
                            <th class="py-3">Fecha de Emisión</th>
                            <th class="py-3 text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comunicados)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">campaign</span>
                                    No se han publicado comunicados en la cartelera digital aún.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($comunicados as $c): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">
                                        <?= e($c['titulo']) ?>
                                    </td>
                                    <td>
                                        <?php if ($c['unidad_numero']): ?>
                                            <span class="badge bg-light text-dark border">
                                                Apto <?= e($c['unidad_numero']) ?> (<?= e($c['edificio_nombre']) ?>)
                                            </span>
                                        <?php elseif ($c['edificio_id']): ?>
                                            <span class="badge bg-info text-dark">
                                                Edificio <?= e($c['edificio_nombre']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                🌐 Todo el Condominio
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($c['nivel_urgencia'] === 'urgente'): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1">Urgente</span>
                                        <?php elseif ($c['nivel_urgencia'] === 'importante'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Importante</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-3 py-1">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= e($c['admin_nombre']) ?></td>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($c['fecha_publicacion'])) ?></td>
                                    <td class="text-end pe-4">
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
    <div class="modal-dialog modal-lg">
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
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Título del Comunicado <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" required placeholder="Ej: Mantenimiento programado del tanque de agua" class="form-control">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Dirigido a Edificio / Torre</label>
                            <select name="edificio_id" class="form-select">
                                <option value="">-- Todos los Edificios (Global) --</option>
                                <?php foreach ($edificios as $ed): ?>
                                    <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Nivel de Urgencia <span class="text-danger">*</span></label>
                            <select name="nivel_urgencia" required class="form-select">
                                <option value="normal">Normal (Información habitual)</option>
                                <option value="importante">Importante (Resaltado)</option>
                                <option value="urgente">Urgente (Notificación al instante)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Contenido del Aviso <span class="text-danger">*</span></label>
                        <textarea name="contenido" rows="5" required placeholder="Escriba los detalles del comunicado..." class="form-control"></textarea>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded-3 border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="enviar_email" value="1" id="checkEnviarEmail" checked>
                        <label class="form-check-label fw-bold text-dark" for="checkEnviarEmail">
                            Enviar también por correo electrónico a los residentes seleccionados
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Publicar Comunicado</button>
                </div>
            </form>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>
