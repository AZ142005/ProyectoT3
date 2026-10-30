                <!-- Encabezado interno y Filtros de la pestaña -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-primary fs-4">how_to_reg</span>
                        <h2 class="text-lg font-bold text-on-surface mb-0">Solicitudes de Registro</h2>
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
                        <h5 class="text-lg font-bold text-on-surface mb-0">Bandeja de Solicitudes de Residentes</h5>
                        <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacionSolicitudes['total']) ?> Registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead>
                                    <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                        <th class="py-3 px-4">Solicitante</th>
                                        <th class="py-3 px-4">Contacto</th>
                                        <th class="py-3 px-4">Apartamento Asignado</th>
                                        <th class="py-3 px-4 text-center">Estado</th>
                                        <th class="py-3 px-4">Fecha / Revisión</th>
                                        <th class="py-3 px-4 text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-background">
                                    <?php if (empty($solicitudes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-12 text-on-surface-variant">
                                                <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">how_to_reg</span>
                                                No hay solicitudes de registro <?= !empty($estadoSolicitud) ? 'con estado ' . e($estadoSolicitud) : 'pendientes o registradas' ?>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($solicitudes as $s): ?>
                                            <tr class="hover:bg-background/40 transition-colors">
                                                <td class="py-4 px-4">
                                                    <div class="font-semibold text-on-surface"><?= e($s['nombre']) ?> <?= e($s['apellido']) ?></div>
                                                    <div class="text-xs text-on-surface-variant">
                                                        <span>C.I: <strong><?= e($s['cedula']) ?></strong></span>
                                                        <span class="ms-2 badge bg-light text-on-surface border">
                                                            <?= e($s['numero_residentes'] ?? 1) ?> <?= ((int)($s['numero_residentes'] ?? 1) === 1) ? 'habitante' : 'habitantes' ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="text-xs">
                                                        <a href="mailto:<?= e($s['email']) ?>" class="text-decoration-none text-on-surface">
                                                            <span class="material-symbols-outlined text-[15px] align-middle text-on-surface-variant">mail</span>
                                                            <?= e($s['email']) ?>
                                                        </a>
                                                    </div>
                                                    <div class="text-xs text-on-surface-variant mt-0.5">
                                                        <span class="material-symbols-outlined text-[15px] align-middle text-on-surface-variant">call</span>
                                                        <?= e($s['telefono']) ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="font-semibold text-on-surface">
                                                        <?= e($s['edificio_nombre'] ?: 'Inmueble') ?> — Apto. <?= e($s['unidad_numero'] ?: 'N/A') ?>
                                                    </div>
                                                    <div class="text-xs text-on-surface-variant">
                                                        Unidad #<?= e($s['unidad_id']) ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php if ($s['estado'] === 'aprobada'): ?>
                                                        <span class="badge bg-success rounded-pill px-3 py-1.5">Aprobada</span>
                                                    <?php elseif ($s['estado'] === 'rechazada'): ?>
                                                        <span class="badge bg-danger rounded-pill px-3 py-1.5">Rechazada</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-on-surface rounded-pill px-3 py-1.5">Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4 px-4 text-xs text-on-surface-variant">
                                                    <div><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></div>
                                                    <?php if (!empty($s['reviewed_at'])): ?>
                                                        <div class="text-xs text-on-surface-variant mt-1">
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
                                                <td class="py-4 px-4 text-end">
                                                    <?php if ($s['estado'] === 'pendiente'): ?>
                                                        <button type="button" class="bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors" onclick="abrirModalAprobarRegistro(<?= (int)$s['id'] ?>, <?= e(json_encode($s['nombre'] . ' ' . $s['apellido'], JSON_UNESCAPED_UNICODE)) ?>, <?= e(json_encode($s['unidad_numero'], JSON_UNESCAPED_UNICODE)) ?>)">
                                                            <span class="material-symbols-outlined text-[16px] align-middle">check</span>
                                                            Aprobar
                                                        </button>
                                                        <button type="button" class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors ms-1" onclick="abrirModalRechazoRegistro(<?= e($s['id']) ?>, '<?= e(addslashes($s['nombre'] . ' ' . $s['apellido'])) ?>')">
                                                            <span class="material-symbols-outlined text-[16px] align-middle">close</span>
                                                            Rechazar
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-on-surface-variant border">Finalizada</span>
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
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form method="POST" action="/admin/solicitudes-registro/rechazar">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="solicitudRegistroId" value="">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">block</span>
                        Rechazar Solicitud de Registro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-on-surface-variant small mb-3">
                        Indique el motivo por el cual se rechaza la solicitud del residente <strong id="nombreSolicitanteRechazo"></strong>:
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-on-surface-variant">Motivo del Rechazo <span class="text-danger">*</span></label>
                        <textarea name="motivo_rechazo" id="motivo_rechazo" rows="3" required placeholder="Ej. Documentación no coincide con el registro de propietarios..." class="w-full bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:border-primary transition-colors"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">gavel</span>
                        <span>Confirmar Rechazo</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Aprobar Solicitud de Registro -->
<div class="modal fade" id="modalAprobarSolicitudRegistro" tabindex="-1" aria-labelledby="modalAprobarSolicitudRegistroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form method="POST" action="/admin/solicitudes-registro/aprobar">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="aprobarRegistroId" value="">

                <div class="modal-header bg-success text-white py-3">
                    <h5 class="modal-title fw-bold flex-fill d-flex align-items-center gap-2" id="modalAprobarSolicitudRegistroLabel">
                        <span class="material-symbols-outlined">how_to_reg</span>
                        Aprobar Solicitud de Registro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-on-surface-variant small mb-3">
                        ¿Confirmas la aprobación del registro de <strong id="aprobarRegistroNombre"></strong> en el Apto. <strong id="aprobarRegistroUnidad"></strong>?
                    </p>
                    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-xs">
                        <span class="material-symbols-outlined text-[18px] align-middle me-1">check_circle</span>
                        Se activará su cuenta de residente y podrá iniciar sesión.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">verified_user</span>
                        <span>Confirmar Aprobación</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirModalAprobarRegistro(id, nombre, unidad) {
        document.getElementById('aprobarRegistroId').value = id;
        document.getElementById('aprobarRegistroNombre').textContent = nombre;
        document.getElementById('aprobarRegistroUnidad').textContent = unidad;
        new bootstrap.Modal(document.getElementById('modalAprobarSolicitudRegistro')).show();
    }

    function abrirModalRechazoRegistro(id, nombre) {
        document.getElementById('solicitudRegistroId').value = id;
        document.getElementById('nombreSolicitanteRechazo').textContent = nombre;
        document.getElementById('motivo_rechazo').value = '';
        const modal = new bootstrap.Modal(document.getElementById('modalRechazarSolicitudRegistro'));
        modal.show();
    }
</script>
