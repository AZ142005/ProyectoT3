<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'conciliacion'; 
    require VIEWS_PATH . '/layouts/admin_sidebar.php';

    // Campos consumidos realmente por el JS de los modales de detalle (evita embeber PII no usada).
    $proyectarPagoDetalle = function (array $pago): array {
        return array_intersect_key($pago, array_flip([
            'id', 'estado', 'origen_tabla', 'monto', 'referencia', 'fecha_pago',
            'banco_pagador', 'banco_origen', 'residente_nombre', 'residente_cedula',
            'edificio_nombre', 'unidad_numero', 'metodo_pago', 'numero_factura',
            'factura_id', 'observaciones', 'archivo',
        ]));
    };
    $proyectarExtractoDetalle = function (array $extracto): array {
        return array_intersect_key($extracto, array_flip([
            'id', 'banco', 'monto', 'referencia_bancaria', 'referencia', 'fecha_movimiento',
        ]));
    };
    ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-dark hover:bg-light rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-dark">menu</span>
                </button>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary fs-4">sync_alt</span>
                    <h1 class="text-xl font-bold text-dark mb-0">Conciliación Bancaria y Verificación de Pagos</h1>
                </div>
            </div>
            <a href="<?= \App\Core\Auth::role() === 'auditor' ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- Barra de Acciones del Contenido -->
                <div class="d-flex justify-content-end align-items-center mb-4 flex-wrap gap-2">
                    <?php if (!empty($lotes)): ?>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-dark fw-bold text-nowrap">Lote activo:</label>
                            <select class="form-select form-select-sm shadow-sm" onchange="window.location.href = '/admin/conciliacion' + (this.value ? '?lote=' + encodeURIComponent(this.value) : '')">
                                <option value="" <?= empty($loteActual) ? 'selected' : '' ?>>Todos los movimientos pendientes</option>
                                <?php foreach ($lotes as $l): ?>
                                    <option value="<?= e($l['lote_importacion']) ?>" <?= ($loteActual === $l['lote_importacion']) ? 'selected' : '' ?>>
                                        <?= e($l['lote_importacion']) ?> (<?= e($l['banco']) ?>) [<?= (int)$l['pendientes'] ?> pend.]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportarExtracto">
                        <span class="material-symbols-outlined fs-6">upload_file</span>
                        <span>Importar Extracto</span>
                    </button>
                </div>

                <!-- Métricas Rápidas / Indicadores Clave -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning h-100" role="button" onclick="document.getElementById('filtro-todas').click(); document.getElementById('seccionConciliacion').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Pagos por Verificar">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-bold text-uppercase d-block">Pagos por Verificar</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($pagosPendientes) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-warning opacity-75">pending_actions</span>
                            </div>
                            <small class="text-muted mt-2 d-block">Listado general en espera</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success h-100" role="button" onclick="document.getElementById('filtro-exactas').click(); document.getElementById('seccionConciliacion').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Coincidencias Exactas">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-bold text-uppercase d-block">Coincidencias Exactas</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($resultadoCruce['coincidencias_exactas']) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-success opacity-75">verified</span>
                            </div>
                            <small class="text-muted mt-2 d-block">Coincidencia por referencia y monto</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info h-100" role="button" onclick="document.getElementById('filtro-sugeridas').click(); document.getElementById('seccionConciliacion').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Coincidencias Sugeridas">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-bold text-uppercase d-block">Coincidencias Sugeridas</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($resultadoCruce['coincidencias_sugeridas']) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-info opacity-75">rule</span>
                            </div>
                            <small class="text-muted mt-2 d-block">Sugerencias por fecha y monto</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-secondary h-100" role="button" onclick="document.getElementById('filtro-sin-coincidencia').click(); document.getElementById('seccionConciliacion').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Movimientos Sin Coincidencia">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-bold text-uppercase d-block">Sin Coincidencia</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($resultadoCruce['sin_coincidencia']) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-secondary opacity-75">help</span>
                            </div>
                            <small class="text-muted mt-2 d-block">Movimientos sin asociar</small>
                        </div>
                    </div>
                </div>

                <!-- BANDEJA UNIFICADA: PAGOS POR VERIFICAR Y CONCILIAR -->
                <div class="card border-0 shadow-sm rounded-3 mb-4" id="seccionConciliacion">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0 fw-bolder text-dark d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-warning">hourglass_top</span>
                                <span>Pagos por Verificar y Conciliar</span>
                            </h5>
                            <span class="small text-muted">Bandeja única de pagos reportados y movimientos del extracto bancario</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-dark border fw-bold px-3 py-1.5">Total: <?= count($filasConciliacion) ?></span>
                            <?php if (!empty($resultadoCruce['coincidencias_exactas'])): ?>
                                <button type="button" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center gap-1" onclick="conciliarLoteExactas()">
                                    <span class="material-symbols-outlined fs-6">done_all</span>
                                    <span>Conciliar Todas las Exactas (1-Clic)</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Filtro por tipo de coincidencia (independiente de la tabla) -->
                    <div class="card-header bg-light py-2 border-bottom">
                        <ul class="nav nav-pills card-header-pills flex-wrap gap-1" id="filtrosConciliacion" role="tablist">
                            <li class="nav-item">
                                <button type="button" class="nav-link active fw-bold text-dark" id="filtro-todas" data-filtro-categoria="todas">
                                    Todas (<?= (int)$conteosConciliacion['total'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link fw-bold text-dark" id="filtro-exactas" data-filtro-categoria="exacta">
                                    Exactas (<?= (int)$conteosConciliacion['exacta'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link fw-bold text-dark" id="filtro-sugeridas" data-filtro-categoria="sugerida">
                                    Sugeridas (<?= (int)$conteosConciliacion['sugerida'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link fw-bold text-dark" id="filtro-inconsistencias" data-filtro-categoria="inconsistencia">
                                    Inconsistencias (<?= (int)$conteosConciliacion['inconsistencia'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link fw-bold text-dark" id="filtro-sin-coincidencia" data-filtro-categoria="sin_coincidencia">
                                    Sin Coincidencia (<?= (int)$conteosConciliacion['sin_coincidencia'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link fw-bold text-dark" id="filtro-sin-extracto" data-filtro-categoria="sin_extracto">
                                    Sin Extracto (<?= (int)$conteosConciliacion['sin_extracto'] ?>)
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($filasConciliacion)): ?>
                            <div class="text-center py-5 text-muted">
                                <span class="material-symbols-outlined fs-1 text-success d-block mb-2">task_alt</span>
                                <strong class="text-dark">No hay movimientos pendientes de verificación ni conciliación.</strong>
                                <p class="small text-muted mt-1 mb-0">Todos los pagos reportados y los movimientos del extracto bancario ya fueron resueltos.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaConciliacion">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4 py-3">Residente</th>
                                            <th class="py-3">Inmueble</th>
                                            <th class="py-3">Referencia</th>
                                            <th class="py-3 text-end">Monto (Bs.)</th>
                                            <th class="py-3">Fecha</th>
                                            <th class="py-3 text-end pe-4">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($filasConciliacion as $fila): ?>
                                            <?php
                                            $pago = $fila['pago'];
                                            $extracto = $fila['extracto'];

                                            if ($pago !== null) {
                                                $residente  = $pago['residente_nombre'] ?: 'Residente';
                                                $inmueble   = ($pago['edificio_nombre'] ?: 'Sin Torre') . ' - Unidad ' . ($pago['unidad_numero'] ?: 'S/N');
                                                $referencia = $pago['referencia'] ?: 'S/R';
                                                $monto      = $pago['monto'];

                                                if ($extracto !== null) {
                                                    $detalle = ['extracto' => $proyectarExtractoDetalle($extracto), 'pago' => $proyectarPagoDetalle($pago)];
                                                } else {
                                                    $detalle = ['pago' => $proyectarPagoDetalle($pago)];
                                                }
                                            } else {
                                                $residente  = '—';
                                                $inmueble   = '—';
                                                $referencia = ($extracto['referencia_bancaria'] ?: ($extracto['referencia'] ?? '')) ?: 'S/R';
                                                $monto      = $extracto['monto'];
                                                $detalle    = ['extracto' => $proyectarExtractoDetalle($extracto)];
                                            }
                                            ?>
                                            <tr class="border-bottom" data-categoria="<?= e($fila['categoria']) ?>">
                                                <td class="ps-4">
                                                    <div class="fw-bold text-dark"><?= e($residente) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= e($inmueble) ?></div>
                                                </td>
                                                <td class="font-monospace fw-bold text-dark">
                                                    <?= e($referencia) ?>
                                                </td>
                                                <td class="text-end font-monospace fw-bolder text-dark">
                                                    <?= e(formatearMoneda($monto)) ?>
                                                </td>
                                                <td class="text-muted small">
                                                    <?= e(date('d/m/Y', strtotime($fila['fecha']))) ?>
                                                </td>
                                                <td class="text-end pe-4 text-nowrap">
                                                    <button type="button"
                                                            class="inline-flex items-center gap-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-2 rounded-lg border border-slate-200"
                                                            onclick='verDetalleConciliacion(<?= json_encode($detalle, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                            title="Ver Detalle">
                                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                        <span>Detalles</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr id="filtroSinResultados" style="display: none;">
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <span class="material-symbols-outlined fs-1 text-muted d-block mb-2">filter_alt_off</span>
                                                <strong>No hay movimientos en esta categoría.</strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal para Importar Extracto Bancario -->
<div class="modal fade" id="modalImportarExtracto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow-lg">
            <form method="POST" action="/admin/conciliacion/importar" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold text-white flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-white">upload_file</span>
                        <span>Importar Extracto Bancario</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Entidad Bancaria <span class="text-danger">*</span></label>
                        <select name="banco" required class="form-select">
                            <option value="venezuela">Banco de Venezuela (CSV / TXT / PDF)</option>
                            <option value="mercantil">Banco Mercantil (CSV / TXT)</option>
                            <option value="banesco">Banesco (CSV / TXT)</option>
                            <option value="provincial">BBVA Provincial (CSV / TXT)</option>
                            <option value="generico_csv">Formato CSV Genérico</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Archivo de Extracto (.CSV, .TXT o .PDF) <span class="text-danger">*</span></label>
                        <input type="file" name="archivo_extracto" required accept=".csv,.txt,.pdf" class="form-control">
                        <div class="form-text small text-muted">Soporta estados de cuenta PDF de Banco de Venezuela y archivos CSV/TXT. Los débitos se clasificarán automáticamente como descartados.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Procesar e Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ventana de Detalles de Pago en Conciliación -->
<div class="modal fade" id="modalDetallePago" tabindex="-1" aria-labelledby="modalDetallePagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined fs-4 text-primary">receipt_long</span>
                    <h5 class="modal-title fw-bold text-white mb-0" id="modalDetallePagoLabel">Detalle del Pago #<span id="mdlPagoId"></span></h5>
                    <span id="mdlEstadoBadge" class="badge bg-warning text-dark ms-2 fw-bold">PENDIENTE</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Columna Izquierda: Información Financiera y Cruce -->
                    <div class="col-lg-6">
                        <!-- Comparativa Banco vs Residente -->
                        <div class="card border-0 shadow-sm rounded-3 mb-3">
                            <div class="card-header bg-white py-2 fw-bold small text-uppercase text-muted border-bottom d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined fs-6 text-primary">compare_arrows</span>
                                <span class="text-muted">Comparativa: Extracto Bancario vs Pago Reportado</span>
                            </div>
                            <div class="card-body p-3 bg-white">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Campo</th>
                                                <th class="text-primary">Extracto Bancario</th>
                                                <th class="text-success">Pago Reportado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <tr>
                                                <td class="fw-bold text-muted">Monto</td>
                                                <td class="fw-bolder text-dark font-monospace" id="mdlExtMonto">-</td>
                                                <td class="fw-bolder text-dark font-monospace" id="mdlPagoMonto">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Referencia</td>
                                                <td class="fw-bold text-dark font-monospace" id="mdlExtRef">-</td>
                                                <td class="fw-bold text-dark font-monospace" id="mdlPagoRef">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Fecha</td>
                                                <td class="text-dark" id="mdlExtFecha">-</td>
                                                <td class="text-dark" id="mdlPagoFecha">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Banco / Canal</td>
                                                <td class="text-dark" id="mdlExtBanco">-</td>
                                                <td class="text-dark" id="mdlPagoBanco">-</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Ficha de Datos del Residente e Inmueble -->
                        <div class="card border-0 shadow-sm rounded-3 mb-3">
                            <div class="card-header bg-white py-2 fw-bold small text-uppercase text-muted border-bottom d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined fs-6 text-primary">person</span>
                                <span class="text-muted">Información del Residente e Inmueble</span>
                            </div>
                            <div class="card-body p-3 bg-white small">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <span class="text-muted d-block fw-normal">Residente:</span>
                                        <strong class="text-dark fw-bold" id="mdlResidente">-</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block fw-normal">Cédula:</span>
                                        <strong class="text-dark fw-bold font-monospace" id="mdlCedula">-</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block fw-normal">Inmueble / Unidad:</span>
                                        <strong class="text-dark fw-bold" id="mdlUnidad">-</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block fw-normal">Método de Pago:</span>
                                        <strong class="text-dark fw-bold text-uppercase" id="mdlMetodo">-</strong>
                                    </div>
                                    <div class="col-12" id="mdlFacturaWrapper" style="display: none;">
                                        <div class="bg-primary-subtle border border-primary-subtle rounded p-2 text-primary small">
                                            <strong class="text-primary">Factura Relacionada:</strong> #<span id="mdlFacturaNumero" class="text-dark font-monospace fw-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="col-12" id="mdlObsWrapper" style="display: none;">
                                        <span class="text-muted d-block fw-normal">Observaciones del Residente:</span>
                                        <div class="p-2 bg-light rounded text-muted fst-italic" id="mdlObservaciones"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Vista Previa y Descarga de Comprobante -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100 d-flex flex-column">
                            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold small text-uppercase text-muted d-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined fs-6 text-primary">attach_file</span>
                                    <span class="text-muted">Comprobante Adjunto</span>
                                </span>
                                <a id="mdlBtnDescargar" href="#" download="" class="btn btn-sm btn-primary fw-bold d-inline-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined fs-6">download</span>
                                    <span>Descargar Comprobante</span>
                                </a>
                            </div>
                            <div class="card-body p-3 flex-grow-1 d-flex flex-column align-items-center justify-content-center bg-white" style="min-height: 350px;">
                                <!-- Contenedor Imagen -->
                                <div id="mdlPreviewImgContainer" class="w-100 text-center" style="display: none;">
                                    <img id="mdlImgPreview" src="" alt="Comprobante de pago" class="img-fluid rounded border shadow-sm" style="max-height: 400px; object-fit: contain;">
                                </div>
                                <!-- Contenedor PDF -->
                                <div id="mdlPreviewPdfContainer" class="w-100 h-100" style="display: none; min-height: 400px;">
                                    <iframe id="mdlPdfPreview" src="" class="w-100 h-100 rounded border" style="min-height: 400px;"></iframe>
                                </div>
                                <!-- Sin Archivo -->
                                <div id="mdlSinArchivoContainer" class="text-center text-muted p-4" style="display: none;">
                                    <span class="material-symbols-outlined display-4 text-muted mb-2">attachment</span>
                                    <p class="mb-0 fw-bold">No se adjuntó archivo de comprobante para este pago.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-3 d-flex justify-content-between flex-wrap gap-2">
                <a id="mdlLinkPantallaCompleta" href="#" class="btn btn-outline-secondary btn-sm fw-bold d-inline-flex align-items-center gap-1">
                    <span class="material-symbols-outlined fs-6">open_in_new</span>
                    <span>Ver Pantalla Completa de Detalle</span>
                </a>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-light btn-sm fw-bold border" data-bs-dismiss="modal">Cerrar</button>
                    <!-- Formulario Conciliar / Aprobar dentro del modal -->
                    <form id="mdlFormConciliar" method="POST" action="/admin/conciliacion/conciliar" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="extracto_id" id="mdlInputExtractoId" value="">
                        <input type="hidden" name="pago_id" id="mdlInputPagoId" value="">
                        <input type="hidden" name="origen_tipo" id="mdlInputOrigenTipo" value="pago">
                        <button type="submit" id="mdlBtnConfirmarConciliacion" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center gap-1"
                                onclick="return confirm('¿Confirma la conciliación y aprobación de este pago?');">
                            <span class="material-symbols-outlined fs-6">check</span>
                            <span>Aprobar y Conciliar</span>
                        </button>
                    </form>
                    <!-- Botón Rechazar dentro del Modal -->
                    <button type="button" id="mdlBtnRechazarModal" class="btn btn-danger btn-sm fw-bold d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6">cancel</span>
                        <span>Rechazar Pago</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Rechazar Pago con Motivo Obligatorio -->
<div class="modal fade" id="modalRechazarConciliacion" tabindex="-1" aria-labelledby="modalRechazarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form method="POST" action="/admin/conciliacion/rechazar" id="formRechazarConciliacion">
                <?= csrf_field() ?>
                <input type="hidden" name="pago_id" id="rechazoModalPagoId" value="">
                <input type="hidden" name="origen_tipo" id="rechazoModalOrigenTipo" value="pago">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="modalRechazarLabel">
                        <span class="material-symbols-outlined text-white">cancel</span>
                        <span>Rechazar Pago</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-danger-subtle border border-danger-subtle text-danger mb-3 py-2 px-3 small rounded-3">
                        El pago será marcado como <strong>RECHAZADO</strong> y se notificará de inmediato al residente con el motivo especificado.
                    </div>
                    
                    <div class="mb-3 small">
                        <span class="text-muted d-block fw-normal">Residente:</span>
                        <strong id="rechazoModalResidente" class="text-dark fw-bold">-</strong>
                        <span class="text-muted d-block mt-1 fw-normal">Referencia:</span>
                        <strong id="rechazoModalReferencia" class="text-dark font-monospace fw-bold">-</strong>
                    </div>

                    <div class="mb-3">
                        <label for="rechazoMotivo" class="form-label fw-bold small text-muted text-uppercase">
                            Motivo de Rechazo <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="rechazoMotivo" name="motivo" rows="3" required minlength="5"
                                  placeholder="Indique el motivo claro del rechazo (mínimo 5 caracteres)..."></textarea>
                        <div class="form-text small text-muted">Mínimo 5 caracteres. Este texto será visible para el residente.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6">gavel</span>
                        <span>Confirmar Rechazo</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formLoteExactas" method="POST" action="/admin/conciliacion/conciliar-lote" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="items_json" id="itemsJsonInput" value="">
</form>

<script>
    function conciliarLoteExactas() {
        const items = <?= json_encode(array_map(fn($m) => [
            'extracto_id' => $m['extracto']['id'],
            'pago_id'     => $m['pago']['id'],
            'origen_tipo' => $m['pago']['origen_tabla'] ?? 'pago'
        ], $resultadoCruce['coincidencias_exactas'])) ?>;

        if (items.length === 0) return;
        if (!confirm('¿Desea conciliar y aprobar automáticamente ' + items.length + ' pagos con coincidencia exacta?')) return;

        document.getElementById('itemsJsonInput').value = JSON.stringify(items);
        document.getElementById('formLoteExactas').submit();
    }

    function verDetalleConciliacion(match) {
        const ext = match.extracto || null;
        const pago = match.pago || null;
        const isComp = !!(pago && pago.origen_tabla === 'comprobante');

        document.getElementById('mdlPagoId').textContent = pago ? String(pago.id || 0).padStart(6, '0') : '—';
        document.getElementById('mdlEstadoBadge').textContent = pago ? (pago.estado || 'PENDIENTE').toUpperCase() : 'SIN PAGO';

        // Comparativa: lado Extracto Bancario
        if (ext) {
            document.getElementById('mdlExtMonto').textContent = 'Bs. ' + Number(ext.monto || 0).toLocaleString('es-VE', {minimumFractionDigits: 2});
            document.getElementById('mdlExtRef').textContent = ext.referencia_bancaria || ext.referencia || 'N/A';
            document.getElementById('mdlExtFecha').textContent = ext.fecha_movimiento || '-';
            document.getElementById('mdlExtBanco').textContent = ext.banco || '-';
        } else {
            document.getElementById('mdlExtMonto').textContent = 'Sin extracto vinculado';
            document.getElementById('mdlExtRef').textContent = 'N/A';
            document.getElementById('mdlExtFecha').textContent = '-';
            document.getElementById('mdlExtBanco').textContent = '-';
        }

        // Comparativa: lado Pago Reportado
        if (pago) {
            document.getElementById('mdlPagoMonto').textContent = 'Bs. ' + Number(pago.monto || 0).toLocaleString('es-VE', {minimumFractionDigits: 2});
            document.getElementById('mdlPagoRef').textContent = pago.referencia || 'S/R';
            document.getElementById('mdlPagoFecha').textContent = pago.fecha_pago || '-';
            document.getElementById('mdlPagoBanco').textContent = (pago.banco_pagador || pago.banco_origen || 'No especificado');
        } else {
            document.getElementById('mdlPagoMonto').textContent = '—';
            document.getElementById('mdlPagoRef').textContent = 'Sin pago registrado';
            document.getElementById('mdlPagoFecha').textContent = '—';
            document.getElementById('mdlPagoBanco').textContent = '—';
        }

        // Residente e inmueble
        if (pago) {
            document.getElementById('mdlResidente').textContent = pago.residente_nombre || 'Residente';
            document.getElementById('mdlCedula').textContent = pago.residente_cedula || 'N/A';
            document.getElementById('mdlUnidad').textContent = (pago.edificio_nombre ? pago.edificio_nombre + ' - ' : '') + 'Unidad ' + (pago.unidad_numero || 'S/N');
            document.getElementById('mdlMetodo').textContent = (pago.metodo_pago || 'Transferencia').replace('_', ' ');
        } else {
            document.getElementById('mdlResidente').textContent = '—';
            document.getElementById('mdlCedula').textContent = '—';
            document.getElementById('mdlUnidad').textContent = '—';
            document.getElementById('mdlMetodo').textContent = '—';
        }

        // Acciones disponibles solo cuando existe un pago sobre el cual operar
        const linkPantallaCompleta = document.getElementById('mdlLinkPantallaCompleta');
        const formConciliar = document.getElementById('mdlFormConciliar');
        const btnRechazarModal = document.getElementById('mdlBtnRechazarModal');

        if (pago) {
            if (ext) {
                formConciliar.action = '/admin/conciliacion/conciliar';
                document.getElementById('mdlInputExtractoId').value = ext.id || 0;
                document.getElementById('mdlBtnConfirmarConciliacion').querySelector('span:last-child').textContent = 'Aprobar y Conciliar';
            } else {
                formConciliar.action = '/admin/conciliacion/verificar';
                document.getElementById('mdlInputExtractoId').value = '0';
                document.getElementById('mdlBtnConfirmarConciliacion').querySelector('span:last-child').textContent = 'Verificar y Aprobar';
            }

            document.getElementById('mdlInputPagoId').value = pago.id || 0;
            document.getElementById('mdlInputOrigenTipo').value = pago.origen_tabla || 'pago';

            // Link a pantalla completa
            linkPantallaCompleta.href = isComp
                ? '/admin/comprobante/verificar?id=' + pago.id + '&from=conciliacion'
                : '/pagos/detalle/' + pago.id + '?from=conciliacion';

            // Configurar botón Rechazar dentro del modal de detalle
            btnRechazarModal.onclick = function() {
                const modalDetalle = bootstrap.Modal.getInstance(document.getElementById('modalDetallePago'));
                if (modalDetalle) modalDetalle.hide();
                abrirModalRechazo(pago.id, pago.origen_tabla || 'pago', pago.residente_nombre, pago.referencia);
            };

            linkPantallaCompleta.style.display = '';
            formConciliar.style.display = '';
            btnRechazarModal.style.display = '';
        } else {
            document.getElementById('mdlInputPagoId').value = '0';
            document.getElementById('mdlInputOrigenTipo').value = 'pago';

            // Sin pago registrado no hay verificación, conciliación ni rechazo posibles
            linkPantallaCompleta.style.display = 'none';
            formConciliar.style.display = 'none';
            btnRechazarModal.style.display = 'none';
        }

        // Factura
        const factWrap = document.getElementById('mdlFacturaWrapper');
        if (pago && (pago.numero_factura || pago.factura_id)) {
            factWrap.style.display = 'block';
            document.getElementById('mdlFacturaNumero').textContent = pago.numero_factura || pago.factura_id;
        } else {
            factWrap.style.display = 'none';
        }

        // Observaciones
        const obsWrap = document.getElementById('mdlObsWrapper');
        if (pago && pago.observaciones && pago.observaciones.trim() !== '') {
            obsWrap.style.display = 'block';
            document.getElementById('mdlObservaciones').textContent = pago.observaciones;
        } else {
            obsWrap.style.display = 'none';
        }

        // Archivo / Comprobante (la descarga vive solo dentro del modal, igual que el detalle del historial)
        const archivo = (pago && pago.archivo) ? pago.archivo : '';
        const imgContainer = document.getElementById('mdlPreviewImgContainer');
        const pdfContainer = document.getElementById('mdlPreviewPdfContainer');
        const sinContainer = document.getElementById('mdlSinArchivoContainer');
        const btnDescargar = document.getElementById('mdlBtnDescargar');

        imgContainer.style.display = 'none';
        pdfContainer.style.display = 'none';
        sinContainer.style.display = 'none';

        if (archivo) {
            btnDescargar.style.display = 'inline-flex';
            btnDescargar.href = '/comprobante-proxy.php?file=' + encodeURIComponent(archivo) + '&download=1';
            btnDescargar.download = archivo;

            const extension = archivo.split('.').pop().toLowerCase();
            if (extension === 'pdf') {
                pdfContainer.style.display = 'block';
                document.getElementById('mdlPdfPreview').src = '/comprobante-proxy.php?file=' + encodeURIComponent(archivo);
            } else {
                imgContainer.style.display = 'block';
                document.getElementById('mdlImgPreview').src = '/comprobante-proxy.php?file=' + encodeURIComponent(archivo);
            }
        } else {
            btnDescargar.style.display = 'none';
            sinContainer.style.display = 'block';
        }

        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('modalDetallePago'));
        modal.show();
    }

    // Filtro client-side de la bandeja unificada por categoría de coincidencia
    (function inicializarFiltroConciliacion() {
        const contenedor = document.getElementById('filtrosConciliacion');
        if (!contenedor) return;

        const pills = contenedor.querySelectorAll('[data-filtro-categoria]');
        const sinResultados = document.getElementById('filtroSinResultados');

        function aplicarFiltro(categoria) {
            pills.forEach(function (pill) {
                pill.classList.toggle('active', pill.dataset.filtroCategoria === categoria);
            });

            const filas = document.querySelectorAll('#tablaConciliacion tbody tr[data-categoria]');
            let visibles = 0;
            filas.forEach(function (fila) {
                const coincide = categoria === 'todas' || fila.dataset.categoria === categoria;
                fila.style.display = coincide ? '' : 'none';
                if (coincide) visibles++;
            });

            if (sinResultados) {
                sinResultados.style.display = (filas.length > 0 && visibles === 0) ? '' : 'none';
            }
        }

        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                aplicarFiltro(pill.dataset.filtroCategoria);
            });
        });
    })();

    function abrirModalRechazo(pagoId, origenTipo, residenteNombre, referencia) {
        document.getElementById('rechazoModalPagoId').value = pagoId;
        document.getElementById('rechazoModalOrigenTipo').value = origenTipo;
        document.getElementById('rechazoModalResidente').textContent = residenteNombre || 'Residente';
        document.getElementById('rechazoModalReferencia').textContent = referencia || 'S/R';
        document.getElementById('rechazoMotivo').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modalRechazarConciliacion'));
        modal.show();
    }
</script>
