<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'gastos'; 
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
                <div class="d-flex align-items-center gap-2">
                    <a href="/admin/gastos" class="text-decoration-none text-muted d-flex align-items-center">
                        <span class="material-symbols-outlined fs-5">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-on-surface mb-0">Ingesta y Parseo de PDF Maestro de Gastos</h1>
                </div>
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

                <!-- Encabezado Informativo -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Carga y Extracción de Relación Mensual de Gastos</h4>
                        <p class="text-muted small mb-0">
                            Procesamiento asistido e independiente de PDFs consolidados (RF 30, RF 31, RF 32) para desglose estructurado.
                        </p>
                    </div>
                    <a href="/admin/gastos" class="btn btn-outline-secondary btn-sm fw-bold d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6">list</span>
                        <span>Volver al Historial</span>
                    </a>
                </div>

                <!-- Tarjeta de Carga y Parseo -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined text-primary">upload_file</span>
                            <span>1. Seleccionar Documento Maestro o Ingresar Datos</span>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="/admin/gastos/parsear-maestro" method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-muted small">Mes del Período *</label>
                                    <select name="mes" class="form-select" required>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= e($m) ?>" <?= ($m === intval($mes)) ? 'selected' : '' ?>>
                                                <?= nombreMes($m) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-muted small">Año del Período *</label>
                                    <select name="anio" class="form-select" required>
                                        <?php 
                                        $currentYear = intval(date('Y'));
                                        for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): 
                                        ?>
                                            <option value="<?= e($y) ?>" <?= ($y === intval($anio)) ? 'selected' : '' ?>>
                                                <?= e($y) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active fw-bold small" id="pills-pdf-tab" data-bs-toggle="pill" data-bs-target="#pills-pdf" type="button" role="tab">
                                        <span class="material-symbols-outlined align-middle fs-6 me-1">picture_as_pdf</span>
                                        Archivo PDF Maestro (Recomendado)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link fw-bold small" id="pills-texto-tab" data-bs-toggle="pill" data-bs-target="#pills-texto" type="button" role="tab">
                                        <span class="material-symbols-outlined align-middle fs-6 me-1">text_snippet</span>
                                        Texto Plano / Tabular
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="pills-tabContent">
                                <!-- Pestaña PDF -->
                                <div class="tab-pane fade show active" id="pills-pdf" role="tabpanel">
                                    <div class="border rounded-3 p-4 bg-light text-center">
                                        <span class="material-symbols-outlined text-primary fs-1 mb-2 d-block">cloud_upload</span>
                                        <p class="text-dark fw-bold mb-1">Subir PDF Maestro de Gastos del Mes</p>
                                        <p class="text-muted small mb-3">Soporta documentos consolidados de facturas y relaciones mensuales en formato PDF (Máx. 10MB).</p>
                                        <input type="file" name="pdf_maestro" id="pdf_maestro" class="form-control mx-auto" style="max-width: 450px;" accept="application/pdf">
                                    </div>
                                </div>

                                <!-- Pestaña Texto -->
                                <div class="tab-pane fade" id="pills-texto" role="tabpanel">
                                    <div class="mb-2">
                                        <label for="texto_manual" class="form-label fw-bold text-muted small">Copiar y pegar texto de relación de gastos</label>
                                        <textarea name="texto_manual" id="texto_manual" rows="6" class="form-control font-monospace text-sm" placeholder="Ejemplo:&#10;15/09/2026 HidroServicios C.A. Factura 1029 Mantenimiento de bombas Bs. 4.500,00&#10;18/09/2026 CORPOELEC Electricidad areas comunes Bs. 1.250,50"></textarea>
                                        <small class="text-muted">El analizador detectará fechas, montos, proveedores y números de factura automáticamente.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 text-end">
                                <button type="submit" class="btn btn-primary fw-bold d-inline-flex align-items-center gap-1 px-4 py-2 shadow-sm">
                                    <span class="material-symbols-outlined fs-6">auto_awesome</span>
                                    <span>Analizar y Extraer Renglones</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Previsualización y Confirmación de Renglones -->
                <?php if (!empty($renglones)): ?>
                    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-success">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                    <span class="material-symbols-outlined text-success">fact_check</span>
                                    <span>2. Renglones Extraídos para Revisión (<?= count($renglones) ?> detectados)</span>
                                </h5>
                                <small class="text-muted">
                                    Archivo soporte vinculado: <strong><?= e($archivo ?: 'Carga manual') ?></strong> |
                                    Período: <strong><?= e($mes) ?>/<?= e($anio) ?></strong>
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success-subtle text-success fs-6 fw-bold px-3 py-2 border border-success-subtle">
                                    Total: Bs. <?= number_format(array_sum(array_column($renglones, 'monto_total')), 2) ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <form action="/admin/gastos/importar-maestro" method="POST" id="formImportarMaestro">
                                <?= csrf_field() ?>
                                <input type="hidden" name="mes" value="<?= e($mes) ?>">
                                <input type="hidden" name="anio" value="<?= e($anio) ?>">
                                <input type="hidden" name="archivo_maestro" value="<?= e($archivo) ?>">

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="tablaRenglones">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3 py-3" style="width: 40px;">#</th>
                                                <th class="py-3" style="width: 130px;">Fecha</th>
                                                <th class="py-3" style="width: 170px;">Categoría</th>
                                                <th class="py-3" style="width: 180px;">Proveedor</th>
                                                <th class="py-3" style="width: 130px;">Nro. Factura</th>
                                                <th class="py-3">Descripción</th>
                                                <th class="py-3" style="width: 130px;">Monto (Bs.)</th>
                                                <th class="py-3" style="width: 70px;">Pág.</th>
                                                <th class="py-3 text-center pe-3" style="width: 50px;">Quitar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($renglones as $idx => $r): ?>
                                                <tr id="fila-<?= e($idx) ?>">
                                                    <td class="ps-3 fw-bold text-muted small"><?= e($idx + 1) ?></td>
                                                    <td>
                                                        <input type="date" name="gastos[<?= e($idx) ?>][fecha_gasto]" value="<?= e($r['fecha_gasto']) ?>" class="form-control form-control-sm" required>
                                                    </td>
                                                    <td>
                                                        <select name="gastos[<?= e($idx) ?>][categoria_id]" class="form-select form-select-sm" required>
                                                            <?php foreach ($categorias as $c): ?>
                                                                <option value="<?= e($c['id']) ?>" <?= (intval($c['id']) === intval($r['categoria_id'])) ? 'selected' : '' ?>>
                                                                    <?= e($c['nombre']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="gastos[<?= e($idx) ?>][proveedor]" value="<?= e($r['proveedor']) ?>" class="form-control form-control-sm" required maxlength="150">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="gastos[<?= e($idx) ?>][nro_factura_proveedor]" value="<?= e($r['nro_factura_proveedor'] ?? '') ?>" class="form-control form-control-sm" maxlength="100" placeholder="S/N">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="gastos[<?= e($idx) ?>][descripcion]" value="<?= e($r['descripcion']) ?>" class="form-control form-control-sm" required maxlength="255">
                                                        <?php if (!empty($r['extracto_texto'])): ?>
                                                            <input type="hidden" name="gastos[<?= e($idx) ?>][extracto_texto]" value="<?= e($r['extracto_texto']) ?>">
                                                            <small class="text-muted d-block text-truncate mt-1" style="max-width: 320px;" title="<?= e($r['extracto_texto']) ?>">
                                                                <em>Extracto: <?= e($r['extracto_texto']) ?></em>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" min="0.01" name="gastos[<?= e($idx) ?>][monto_total]" value="<?= e($r['monto_total']) ?>" class="form-control form-control-sm text-end fw-bold" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" min="1" name="gastos[<?= e($idx) ?>][pagina_soporte]" value="<?= e($r['pagina_soporte'] ?? 1) ?>" class="form-control form-control-sm text-center">
                                                    </td>
                                                    <td class="text-center pe-3">
                                                        <button type="button" class="btn btn-outline-danger btn-sm p-1 d-inline-flex align-items-center justify-center rounded-2" onclick="eliminarFila(<?= e($idx) ?>)" title="Descartar este renglón">
                                                            <span class="material-symbols-outlined fs-6">delete</span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <a href="/admin/gastos/maestro?mes=<?= e($mes) ?>&anio=<?= e($anio) ?>" class="btn btn-outline-secondary btn-sm fw-bold">
                                        Limpiar y Volver a Subir
                                    </a>
                                    <button type="submit" class="btn btn-success fw-bold d-inline-flex align-items-center gap-1 shadow-sm px-4 py-2">
                                        <span class="material-symbols-outlined fs-6">check_circle</span>
                                        <span>Confirmar e Importar Gastos al Sistema</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
function eliminarFila(idx) {
    const fila = document.getElementById('fila-' + idx);
    if (fila) {
        fila.remove();
    }
}
</script>
