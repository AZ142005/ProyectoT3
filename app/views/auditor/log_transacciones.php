<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-dark fw-bold">
                <span class="material-symbols-outlined align-middle me-1 text-primary">history</span>
                Registro Inmutable de Auditoría
            </h1>
            <p class="text-muted small mb-0">Trazabilidad histórica de todas las operaciones administrativas, cambios de estado y transacciones.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/auditor/exportar-log" class="btn btn-primary fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined">download</span>
                Exportar CSV
            </a>
        </div>
    </div>

    <!-- Tabla Completa de Log -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Eventos de Auditoría (Total: <?= number_format($paginacion['total']) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-sm">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">ID</th>
                            <th class="py-3">Fecha y Hora</th>
                            <th class="py-3">Usuario Responsable</th>
                            <th class="py-3">Acción</th>
                            <th class="py-3">Entidad / Registro</th>
                            <th class="py-3">Detalle del Evento</th>
                            <th class="py-3 text-end pe-4">Dirección IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">verified</span>
                                    No hay registros de auditoría disponibles.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="ps-4 font-monospace text-muted">#<?= e($l['id']) ?></td>
                                    <td class="font-monospace small text-muted"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($l['usuario_nombre']) ?></div>
                                        <small class="text-muted"><?= e($l['usuario_username'] ?: '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary rounded-pill px-3 py-1 font-monospace"><?= e($l['accion']) ?></span>
                                    </td>
                                    <td class="font-monospace small text-muted">
                                        <?= e($l['tabla_afectada']) ?> #<?= e($l['registro_id'] ?: '-') ?>
                                    </td>
                                    <td class="small text-dark" style="max-width: 320px;">
                                        <?= e($l['detalles']) ?>
                                    </td>
                                    <td class="text-end pe-4 font-monospace small text-muted">
                                        <?= e($l['ip_address'] ?: '127.0.0.1') ?>
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
