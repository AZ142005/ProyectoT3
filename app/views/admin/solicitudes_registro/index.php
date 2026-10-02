                <!-- Encabezado interno y Filtros de la pestaña -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-primary fs-4">how_to_reg</span>
                        <h2 class="fs-6 fw-bold mb-0 text-dark">Solicitudes de Registro</h2>
                    </div>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtros de estado">
                        <a href="/admin/usuarios?tab=solicitudes" class="<?= empty($estadoSolicitud) ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Todas (<?= e($paginacionSolicitudes['total']) ?>)
                        </a>
                        <a href="/admin/usuarios?tab=solicitudes&estado=pendiente" class="<?= ($estadoSolicitud === 'pendiente') ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Pendientes
                            <?php if ($pendientesCount > 0): ?>
                                <span class="bg-amber-300 text-amber-950 ms-1 rounded-full px-1.5 text-[10px] font-bold"><?= e($pendientesCount) ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/admin/usuarios?tab=solicitudes&estado=aprobada" class="<?= ($estadoSolicitud === 'aprobada') ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Aprobadas
                        </a>
                        <a href="/admin/usuarios?tab=solicitudes&estado=rechazada" class="<?= ($estadoSolicitud === 'rechazada') ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Rechazadas
                        </a>
                    </div>
                </div>

                <!-- Tabla de Solicitudes -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark">Bandeja de Solicitudes de Residentes</h5>
                        <span class="badge bg-primary rounded-pill"><?= e($paginacionSolicitudes['total']) ?> Registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 py-3">Solicitante</th>
                                        <th class="py-3">Contacto</th>
                                        <th class="py-3">Apartamento Asignado</th>
                                        <th class="py-3 text-center">Estado</th>
                                        <th class="py-3">Fecha / Revisión</th>
                                        <th class="py-3 text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($solicitudes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">how_to_reg</span>
                                                No hay solicitudes de registro <?= !empty($estadoSolicitud) ? 'con estado ' . e($estadoSolicitud) : 'pendientes o registradas' ?>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($solicitudes as $s): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold text-dark"><?= e($s['nombre']) ?> <?= e($s['apellido']) ?></div>
                                                    <div class="small text-muted">
                                                        <span>C.I: <strong><?= e($s['cedula']) ?></strong></span>
                                                        <span class="ms-2 badge bg-light text-dark border">
                                                            <?= e($s['numero_residentes'] ?? 1) ?> <?= ((int)($s['numero_residentes'] ?? 1) === 1) ? 'habitante' : 'habitantes' ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <a href="mailto:<?= e($s['email']) ?>" class="text-decoration-none text-dark">
                                                            <span class="material-symbols-outlined text-[15px] align-middle text-muted">mail</span>
                                                            <?= e($s['email']) ?>
                                                        </a>
                                                    </div>
                                                    <div class="small text-muted mt-0.5">
                                                        <span class="material-symbols-outlined text-[15px] align-middle text-muted">call</span>
                                                        <?= e($s['telefono']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark">
                                                        <?= e($s['edificio_nombre'] ?: 'Inmueble') ?> — Apto. <?= e($s['unidad_numero'] ?: 'N/A') ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        Unidad #<?= e($s['unidad_id']) ?>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($s['estado'] === 'aprobada'): ?>
                                                        <span class="badge bg-success rounded-pill px-3 py-1.5">Aprobada</span>
                                                    <?php elseif ($s['estado'] === 'rechazada'): ?>
                                                        <span class="badge bg-danger rounded-pill px-3 py-1.5">Rechazada</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5">Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-muted">
                                                    <div><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></div>
                                                    <?php if (!empty($s['reviewed_at'])): ?>
                                                        <div class="text-xs text-slate-500 mt-1">
                                                            Revisado: <?= date('d/m/Y H:i', strtotime($s['reviewed_at'])) ?>
                                                            <?php if (!empty($s['admin_nombre'])): ?>
                                                                <br>por <em><?= e($s['admin_nombre']) ?></em>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($s['motivo_rechazo'])): ?>
                                                        <div class="text-danger mt-1 text-xs">
                                                            <strong>Motivo:</strong> <?= e($s['motivo_rechazo']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($s['estado'] === 'pendiente'): ?>
                                                        <form method="POST" action="/admin/solicitudes-registro/aprobar" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                                                            <button type="submit" class="btn btn-success btn-sm fw-bold px-3" onclick="return confirm('¿Aprobar el registro de <?= e($s['nombre']) ?> <?= e($s['apellido']) ?> en el Apto. <?= e($s['unidad_numero']) ?>? Se activará su cuenta de residente.');">
                                                                <span class="material-symbols-outlined text-[16px] align-middle">check</span>
                                                                Aprobar
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-outline-danger btn-sm fw-bold px-3 ms-1" onclick="abrirModalRechazoRegistro(<?= e($s['id']) ?>, '<?= e(addslashes($s['nombre'] . ' ' . $s['apellido'])) ?>')">
                                                            <span class="material-symbols-outlined text-[16px] align-middle">close</span>
                                                            Rechazar
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border">Finalizada</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="p-3 border-top bg-light">
                            <?php
                            $filtros = ['estado' => $estadoSolicitud ?? '', 'tab' => 'solicitudes'];
                            $paginacion = $paginacionSolicitudes;
                            include VIEWS_PATH . '/components/pagination.php';
                            ?>
                        </div>
                    </div>
                </div>

<!-- Modal para Rechazar Solicitud de Registro -->
<div class="modal fade" id="modalRechazarSolicitudRegistro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <form method="POST" action="/admin/solicitudes-registro/rechazar">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="solicitudRegistroId" value="">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold">Rechazar Solicitud de Registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Indique el motivo por el cual se rechaza la solicitud del residente <strong id="nombreSolicitanteRechazo"></strong>:
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Motivo del Rechazo <span class="text-danger">*</span></label>
                        <textarea name="motivo_rechazo" id="motivo_rechazo" rows="3" required placeholder="Ej. Documentación no coincide con el registro de propietarios..." class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger fw-bold">Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirModalRechazoRegistro(id, nombre) {
        document.getElementById('solicitudRegistroId').value = id;
        document.getElementById('nombreSolicitanteRechazo').textContent = nombre;
        document.getElementById('motivo_rechazo').value = '';
        const modal = new bootstrap.Modal(document.getElementById('modalRechazarSolicitudRegistro'));
        modal.show();
    }
</script>
