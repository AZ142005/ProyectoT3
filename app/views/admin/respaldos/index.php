<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'respaldos'; 
    if (\App\Core\Auth::role() === 'auditor') {
        require VIEWS_PATH . '/layouts/auditor_sidebar.php';
    } else {
        require VIEWS_PATH . '/layouts/admin_sidebar.php';
    }
    ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Respaldos Automatizados del Servidor</h1>
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

    <!-- Banner Informativo de Automatización en el Servidor -->
    <div class="alert alert-info border-0 shadow-sm rounded-3 p-4 d-flex align-items-start gap-3 mb-4">
        <span class="material-symbols-outlined fs-2 text-primary">schedule</span>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Respaldos 100% Automatizados en el Servidor</h6>
            <p class="mb-0 small text-muted">
                La generación manual desde la interfaz web ha sido reemplazada por <strong>procesos desatendidos a nivel de servidor (Cron / Tareas Programadas)</strong>. 
                Los volcados de base de datos (<code>.sql.gz</code>) y sus firmas criptográficas SHA-256 se generan y guardan automáticamente dentro del directorio local seguro del servidor (<code>storage/backups/</code>), aplicando rotación autónoma de 7 días.
            </p>
        </div>
    </div>

    <!-- Tabla de Respaldos -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h5 class="text-lg font-bold text-on-surface mb-0">Historial de Respaldos Generados por el Servidor</h5>
                <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= count($respaldos) ?> Registros</span>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-monospace small">
                <span class="material-symbols-outlined fs-6 align-middle me-1">autorenew</span>Automático (CLI / Cron)
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                            <th class="py-3 px-4">Nombre del Archivo</th>
                            <th class="py-3 px-4">Fecha y Hora</th>
                            <th class="py-3 px-4">Tamaño</th>
                            <th class="py-3 px-4">Tablas</th>
                            <th class="py-3 px-4">Firma SHA-256</th>
                            <th class="py-3 px-4 text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-background">
                        <?php if (empty($respaldos)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-12 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">cloud_off</span>
                                    No hay copias de seguridad registradas todavía.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($respaldos as $r): ?>
                                <tr class="hover:bg-background/40 transition-colors">
                                    <td class="py-4 px-4 font-mono text-xs font-bold text-on-surface">
                                        <span class="material-symbols-outlined align-middle fs-6 me-1 text-success">folder_zip</span>
                                        <?= e($r['nombre_archivo']) ?>
                                    </td>
                                    <td class="py-4 px-4 font-monospace text-xs text-on-surface-variant"><?= date('d/m/Y H:i:s', strtotime($r['fecha_respaldo'])) ?></td>
                                    <td class="py-4 px-4 font-monospace fw-bold"><?= round($r['tamano_bytes'] / 1024, 2) ?> KB</td>
                                    <td class="py-4 px-4">
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1"><?= e($r['tablas_respaldadas']) ?> tablas</span>
                                    </td>
                                    <td class="py-4 px-4 font-monospace text-xs text-on-surface-variant" style="max-width: 200px;">
                                        <span class="text-truncate d-inline-block" style="max-width: 180px;" title="<?= e($r['hash_sha256']) ?>">
                                            <?= e($r['hash_sha256']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-end">
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

            </div>
        </div>
    </div>
</div>
