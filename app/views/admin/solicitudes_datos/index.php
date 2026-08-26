<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-dark fw-bold">
                <span class="material-symbols-outlined align-middle me-1 text-primary">manage_accounts</span>
                Solicitudes de Actualización de Datos
            </h1>
            <p class="text-muted small mb-0">Revisión y aprobación de cambios de datos personales solicitados por residentes.</p>
        </div>
    </div>

    <!-- Mensajes Flash -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Tabla de Solicitudes -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Bandeja de Solicitudes</h5>
            <span class="badge bg-primary rounded-pill"><?= e($paginacion['total']) ?> Solicitudes</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Residente / Unidad</th>
                            <th class="py-3">Datos Actuales</th>
                            <th class="py-3">Datos Solicitados</th>
                            <th class="py-3 text-center">Estado</th>
                            <th class="py-3">Fecha Solicitud</th>
                            <th class="py-3 text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($solicitudes)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">verified</span>
                                    No hay solicitudes de cambio de datos registradas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($solicitudes as $s): ?>
                                <?php $json = json_decode($s['datos_nuevos_json'], true); ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= e($s['residente_nombre']) ?></div>
                                        <small class="text-muted d-block">
                                            C.I: <?= e($s['residente_cedula']) ?> | Apto <?= e($s['unidad_numero'] ?: 'N/A') ?> (<?= e($s['edificio_nombre']) ?>)
                                        </small>
                                    </td>
                                    <td class="small text-muted">
                                        Email: <?= e($s['residente_email_actual'] ?: 'N/A') ?><br>
                                        Tel: <?= e($s['residente_telefono_actual'] ?: 'N/A') ?>
                                    </td>
                                    <td>
                                        <?php if (is_array($json)): ?>
                                            <?php foreach ($json as $k => $v): ?>
                                                <span class="badge bg-info text-dark d-inline-block mb-1">
                                                    <strong><?= e(ucfirst($k)) ?>:</strong> <?= e($v) ?>
                                                </span><br>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($s['estado'] === 'aprobado'): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-1">Aprobado</span>
                                        <?php elseif ($s['estado'] === 'rechazado'): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1">Rechazado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></td>
                                    <td class="text-end pe-4">
                                        <?php if ($s['estado'] === 'pendiente'): ?>
                                            <form method="POST" action="/admin/solicitudes-datos/procesar" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                                                <input type="hidden" name="accion" value="aprobado">
                                                <button type="submit" class="btn btn-success btn-sm font-weight-bold" onclick="return confirm('¿Aprobar solicitud y actualizar datos del residente?');">
                                                    Aprobar
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold" onclick="abrirModalRechazo(<?= e($s['id']) ?>)">
                                                Rechazar
                                            </button>
                                        <?php else: ?>
                                            <span class="small text-muted italic"><?= e($s['motivo_admin'] ?: 'Procesado') ?></span>
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
                <?php include VIEWS_PATH . '/components/pagination.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Rechazar Solicitud -->
<div class="modal fade" id="modalRechazarSolicitud" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <form method="POST" action="/admin/solicitudes-datos/procesar">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="solicitudRechazoId" value="">
                <input type="hidden" name="accion" value="rechazado">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold">Rechazar Solicitud de Datos</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Motivo del Rechazo <span class="text-danger">*</span></label>
                        <textarea name="motivo_admin" rows="3" required placeholder="Indique la razón por la cual se rechaza la actualización..." class="form-control"></textarea>
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
    function abrirModalRechazo(id) {
        document.getElementById('solicitudRechazoId').value = id;
        const modal = new bootstrap.Modal(document.getElementById('modalRechazarSolicitud'));
        modal.show();
    }
</script>
