<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-dark fw-bold">
                <span class="material-symbols-outlined align-middle me-1 text-primary">backup</span>
                Respaldos Automatizados de Base de Datos
            </h1>
            <p class="text-muted small mb-0">Gestión de copias de seguridad con compresión GZIP, firma criptográfica SHA-256 y retención de 7 días (RNF 3).</p>
        </div>
        <form method="POST" action="/admin/respaldos/generar">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined">add_circle</span>
                Generar Respaldo Ahora
            </button>
        </form>
    </div>

    <!-- Mensajes Flash -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Banner Informativo -->
    <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center gap-3 mb-4">
        <span class="material-symbols-outlined fs-2 text-success">security</span>
        <div>
            <strong>Política de Continuidad Operativa:</strong> Las copias se almacenan de forma segura fuera de la raíz web en <code>storage/backups/</code> y se rotan automáticamente eliminando aquellas con más de 7 días de antigüedad.
        </div>
    </div>

    <!-- Tabla de Respaldos -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">Historial de Copias de Seguridad</h5>
            <span class="badge bg-primary rounded-pill"><?= count($respaldos) ?> Respaldos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-sm">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Nombre del Archivo</th>
                            <th class="py-3">Fecha y Hora</th>
                            <th class="py-3">Tamaño</th>
                            <th class="py-3">Tablas</th>
                            <th class="py-3">Firma SHA-256</th>
                            <th class="py-3 text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($respaldos)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <span class="material-symbols-outlined display-4 d-block mb-2 text-muted">cloud_off</span>
                                    No hay copias de seguridad registradas todavía.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($respaldos as $r): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-dark">
                                        <span class="material-symbols-outlined align-middle fs-6 me-1 text-success">folder_zip</span>
                                        <?= e($r['nombre_archivo']) ?>
                                    </td>
                                    <td class="font-monospace small text-muted"><?= date('d/m/Y H:i:s', strtotime($r['fecha_respaldo'])) ?></td>
                                    <td class="font-monospace fw-bold"><?= round($r['tamano_bytes'] / 1024, 2) ?> KB</td>
                                    <td>
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1"><?= e($r['tablas_respaldadas']) ?> tablas</span>
                                    </td>
                                    <td class="font-monospace small text-muted" style="max-width: 200px;">
                                        <span class="text-truncate d-inline-block" style="max-width: 180px;" title="<?= e($r['hash_sha256']) ?>">
                                            <?= e($r['hash_sha256']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="/admin/respaldos/descargar/<?= e($r['id']) ?>" class="btn btn-outline-primary btn-sm fw-bold d-inline-flex align-items-center gap-1">
                                            <span class="material-symbols-outlined fs-6">download</span> Descargar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
