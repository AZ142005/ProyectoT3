<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'conciliacion'; 
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
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-dark hover:bg-light rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-dark">menu</span>
                </button>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-dark fs-4">sync_alt</span>
                    <h1 class="text-xl font-bold text-dark mb-0">Conciliación Bancaria y Verificación de Pagos</h1>
                </div>
            </div>
            <a href="<?= \App\Core\Auth::role() === 'auditor' ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-light hover:bg-dark hover:text-white text-dark font-bold p-2.5 rounded-lg border border-dark transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- Barra de Acciones del Contenido -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bolder text-dark mb-1">Centro de Verificación y Conciliación</h4>
                        <p class="text-dark small mb-0 fw-normal">Módulo exclusivo para verificación, aprobación y cruce bancario de pagos reportados</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if (!empty($lotes)): ?>
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-dark fw-bold text-nowrap">Lote activo:</label>
                                <select class="form-select form-select-sm text-dark border-dark shadow-sm" onchange="window.location.href = '/admin/conciliacion' + (this.value ? '?lote=' + encodeURIComponent(this.value) : '')">
                                    <option value="" <?= empty($loteActual) ? 'selected' : '' ?>>Todos los movimientos pendientes</option>
                                    <?php foreach ($lotes as $l): ?>
                                        <option value="<?= e($l['lote_importacion']) ?>" <?= ($loteActual === $l['lote_importacion']) ? 'selected' : '' ?>>
                                            <?= e($l['lote_importacion']) ?> (<?= e($l['banco']) ?>) [<?= (int)$l['pendientes'] ?> pend.]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-dark btn-sm fw-bold text-white d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportarExtracto">
                            <span class="material-symbols-outlined fs-6 text-white">upload_file</span>
                            <span class="text-white">Importar Extracto</span>
                        </button>
                    </div>
                </div>

                <!-- Métricas Rápidas / Indicadores Clave -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border border-dark shadow-sm rounded-3 p-3 bg-white h-100" role="button" onclick="document.getElementById('seccionPagosPendientes').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Pagos por Verificar">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-dark small fw-bold text-uppercase d-block">Pagos por Verificar</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($pagosPendientes) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-dark opacity-75">pending_actions</span>
                            </div>
                            <small class="text-dark fw-normal mt-2 d-block">Listado general en espera</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border border-dark shadow-sm rounded-3 p-3 bg-white h-100" role="button" onclick="document.getElementById('exactas-tab').click(); document.getElementById('seccionCruceInteligente').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Coincidencias Exactas">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-dark small fw-bold text-uppercase d-block">Coincidencias Exactas</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($resultadoCruce['coincidencias_exactas']) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-dark opacity-75">verified</span>
                            </div>
                            <small class="text-dark fw-normal mt-2 d-block">Coincidencia por referencia y monto</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border border-dark shadow-sm rounded-3 p-3 bg-white h-100" role="button" onclick="document.getElementById('sugeridas-tab').click(); document.getElementById('seccionCruceInteligente').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Coincidencias Sugeridas">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-dark small fw-bold text-uppercase d-block">Coincidencias Sugeridas</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($resultadoCruce['coincidencias_sugeridas']) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-dark opacity-75">rule</span>
                            </div>
                            <small class="text-dark fw-normal mt-2 d-block">Sugerencias por fecha y monto</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border border-dark shadow-sm rounded-3 p-3 bg-white h-100" role="button" onclick="document.getElementById('sin-coincidencia-tab').click(); document.getElementById('seccionCruceInteligente').scrollIntoView({behavior: 'smooth'});" style="cursor: pointer;" title="Ver Movimientos Sin Coincidencia">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-dark small fw-bold text-uppercase d-block">Sin Coincidencia</span>
                                    <span class="h3 fw-bolder text-dark mb-0"><?= count($resultadoCruce['sin_coincidencia']) ?></span>
                                </div>
                                <span class="material-symbols-outlined fs-1 text-dark opacity-75">help</span>
                            </div>
                            <small class="text-dark fw-normal mt-2 d-block">Movimientos sin asociar</small>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 1: LISTADO GENERAL DE PAGOS PENDIENTES POR VERIFICAR -->
                <div class="card border border-dark shadow-sm rounded-3 mb-4" id="seccionPagosPendientes">
                    <div class="card-header bg-white py-3 border-bottom border-dark d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0 fw-bolder text-dark d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-dark">hourglass_top</span>
                                <span>Pagos por Verificar</span>
                            </h5>
                            <span class="small text-dark fw-normal">Listado centralizado de todos los pagos reportados que requieren verificación y resolución</span>
                        </div>
                        <span class="badge bg-dark text-white fw-bold px-3 py-1.5 border border-dark">Total: <?= count($pagosPendientes) ?></span>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($pagosPendientes)): ?>
                            <div class="text-center py-5 text-dark">
                                <span class="material-symbols-outlined fs-1 text-dark d-block mb-2">task_alt</span>
                                <strong class="text-dark">No hay pagos pendientes de verificación en el sistema.</strong>
                                <p class="small text-dark mt-1 mb-0">Todos los pagos ingresados han sido conciliados, aprobados o rechazados.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light border-bottom border-dark text-dark fw-bold">
                                        <tr>
                                            <th class="ps-4 py-3 text-dark">Residente</th>
                                            <th class="py-3 text-dark">Inmueble</th>
                                            <th class="py-3 text-dark">Factura</th>
                                            <th class="py-3 text-center text-dark">Referencia</th>
                                            <th class="py-3 text-end text-dark">Monto (Bs.)</th>
                                            <th class="py-3 text-dark">Fecha Pago</th>
                                            <th class="py-3 text-dark">Comprobante</th>
                                            <th class="py-3 text-end pe-4 text-dark">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($pagosPendientes as $p): ?>
                                            <tr class="border-bottom">
                                                <td class="ps-4">
                                                    <div class="fw-bold text-dark"><?= e($p['residente_nombre'] ?: 'Residente') ?></div>
                                                    <small class="text-dark font-monospace fw-normal"><?= e($p['residente_cedula']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark">Unidad <?= e($p['unidad_numero'] ?: 'S/N') ?></div>
                                                    <small class="text-dark fw-normal"><?= e($p['edificio_nombre'] ?: 'Condominio') ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($p['numero_factura'])): ?>
                                                        <span class="font-monospace fw-bold text-dark">#<?= e($p['numero_factura']) ?></span>
                                                    <?php elseif (!empty($p['factura_id'])): ?>
                                                        <span class="font-monospace fw-bold text-dark">#<?= e($p['factura_id']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-dark small fw-bold">General</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center font-monospace fw-bold text-dark">
                                                    <?= e($p['referencia'] ?: 'S/R') ?>
                                                </td>
                                                <td class="text-end font-monospace fw-bolder text-dark">
                                                    <?= e(formatearMoneda($p['monto'])) ?>
                                                </td>
                                                <td class="text-dark small fw-normal">
                                                    <?= e(date('d/m/Y', strtotime($p['fecha_pago']))) ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($p['archivo'])): ?>
                                                        <div class="d-inline-flex align-items-center gap-1">
                                                            <a href="/comprobante-proxy.php?file=<?= urlencode($p['archivo']) ?>" target="_blank"
                                                               class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1 py-1 px-2"
                                                               title="Ver comprobante en pestaña nueva">
                                                                <span class="material-symbols-outlined fs-6 text-dark">visibility</span>
                                                                <span class="text-dark">Ver</span>
                                                            </a>
                                                            <a href="/comprobante-proxy.php?file=<?= urlencode($p['archivo']) ?>&download=1"
                                                               download="<?= e($p['archivo']) ?>"
                                                               class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1 py-1 px-2"
                                                               title="Descargar comprobante">
                                                                <span class="material-symbols-outlined fs-6 text-dark">download</span>
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-dark small fw-normal">Sin archivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4 text-nowrap">
                                                    <div class="d-inline-flex align-items-center gap-1">
                                                        <button type="button" class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1"
                                                                onclick='verDetallePagoDirecto(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                                title="Ver Detalles Completos">
                                                            <span class="material-symbols-outlined fs-6 text-dark">visibility</span>
                                                            <span class="text-dark d-none d-md-inline">Detalles</span>
                                                        </button>

                                                        <form method="POST" action="/admin/conciliacion/verificar" class="d-inline" onsubmit="return confirm('¿Confirma la verificación y aprobación de este pago?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="pago_id" value="<?= e($p['id']) ?>">
                                                            <input type="hidden" name="origen_tipo" value="<?= e($p['origen_tabla']) ?>">
                                                            <button type="submit" class="btn btn-dark btn-sm text-white fw-bold d-inline-flex align-items-center gap-1"
                                                                    title="Verificar y Aprobar Pago">
                                                                <span class="material-symbols-outlined fs-6 text-white">check</span>
                                                                <span class="text-white d-none d-md-inline">Aprobar</span>
                                                            </button>
                                                        </form>

                                                        <button type="button" class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1"
                                                                onclick="abrirModalRechazo(<?= (int)$p['id'] ?>, '<?= e($p['origen_tabla']) ?>', '<?= e(addslashes($p['residente_nombre'] ?? 'Residente')) ?>', '<?= e(addslashes($p['referencia'] ?? 'S/R')) ?>')"
                                                                title="Rechazar Pago">
                                                            <span class="material-symbols-outlined fs-6 text-dark">cancel</span>
                                                            <span class="text-dark d-none d-md-inline">Rechazar</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SECCIÓN 2: CRUCE INTELIGENTE DE PAGOS (EXTRACTOS BANCARIOS) -->
                <div class="card border border-dark shadow-sm rounded-3 mb-4" id="seccionCruceInteligente">
                    <div class="card-header bg-white py-3 border-bottom border-dark d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0 fw-bolder text-dark d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-dark">auto_awesome</span>
                                <span>Cruce Inteligente de Pagos</span>
                            </h5>
                            <span class="small text-dark fw-normal">Detección y emparejamiento automático entre el extracto bancario y los pagos reportados</span>
                        </div>

                        <?php if (!empty($resultadoCruce['coincidencias_exactas'])): ?>
                            <button type="button" class="btn btn-dark btn-sm fw-bold text-white d-inline-flex align-items-center gap-1" onclick="conciliarLoteExactas()">
                                <span class="material-symbols-outlined fs-6 text-white">done_all</span>
                                <span class="text-white">Conciliar Todas las Exactas (1-Clic)</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="card-header bg-light py-2 border-bottom border-dark">
                        <ul class="nav nav-pills card-header-pills" id="cruceTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-bold text-dark" id="exactas-tab" data-bs-toggle="tab" data-bs-target="#exactas" type="button">
                                    Coincidencias Exactas (<?= count($resultadoCruce['coincidencias_exactas']) ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold text-dark" id="sugeridas-tab" data-bs-toggle="tab" data-bs-target="#sugeridas" type="button">
                                    Coincidencias Sugeridas (<?= count($resultadoCruce['coincidencias_sugeridas']) ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold text-dark" id="inconsistencias-tab" data-bs-toggle="tab" data-bs-target="#inconsistencias" type="button">
                                    Inconsistencias (<?= count($resultadoCruce['inconsistencias']) ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold text-dark" id="sin-coincidencia-tab" data-bs-toggle="tab" data-bs-target="#sin-coincidencia" type="button">
                                    Sin Coincidencia (<?= count($resultadoCruce['sin_coincidencia']) ?>)
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-0">
                        <div class="tab-content" id="cruceTabsContent">
                            <!-- TAB 1: COINCIDENCIAS EXACTAS -->
                            <div class="tab-pane fade show active" id="exactas" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light border-bottom border-dark text-dark fw-bold">
                                            <tr>
                                                <th class="ps-4 py-3 text-dark">Movimiento Banco (Extracto)</th>
                                                <th class="py-3 text-dark">Pago Reportado (Residente)</th>
                                                <th class="py-3 text-center text-dark">Referencia</th>
                                                <th class="py-3 text-end text-dark">Monto (Bs.)</th>
                                                <th class="py-3 text-center text-dark">Estado Cruce</th>
                                                <th class="py-3 text-end pe-4 text-dark">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($resultadoCruce['coincidencias_exactas'])): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-dark">
                                                        <span class="material-symbols-outlined fs-1 text-dark d-block mb-2">task_alt</span>
                                                        <strong class="text-dark">No hay coincidencias exactas pendientes por conciliar.</strong>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($resultadoCruce['coincidencias_exactas'] as $match): ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="fw-bold text-dark"><?= e($match['extracto']['banco']) ?></div>
                                                            <small class="text-dark fw-normal"><?= date('d/m/Y', strtotime($match['extracto']['fecha_movimiento'])) ?> | <?= e(substr($match['extracto']['descripcion_banco'], 0, 30)) ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold text-dark"><?= e($match['pago']['residente_nombre']) ?></div>
                                                            <small class="text-dark fw-normal">Apto <?= e($match['pago']['unidad_numero']) ?> (<?= e($match['pago']['edificio_nombre']) ?>)</small>
                                                        </td>
                                                        <td class="text-center font-monospace fw-bold text-dark">
                                                            <?= e($match['extracto']['referencia_bancaria']) ?>
                                                        </td>
                                                        <td class="text-end font-monospace fw-bolder text-dark">
                                                            <?= e(formatearMoneda($match['extracto']['monto'])) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-dark text-white rounded-pill px-3 py-1 fw-bold">Exacta</span>
                                                        </td>
                                                        <td class="text-end pe-4 text-nowrap">
                                                            <div class="d-inline-flex align-items-center gap-1">
                                                                <button type="button" class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1"
                                                                        onclick='verDetalleConciliacion(<?= json_encode($match, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                                        title="Ver Detalle y Comprobante">
                                                                    <span class="material-symbols-outlined fs-6 text-dark">visibility</span>
                                                                    <span class="text-dark d-none d-xl-inline">Detalles</span>
                                                                </button>
                                                                <?php if (!empty($match['pago']['archivo'])): ?>
                                                                    <a href="/comprobante-proxy.php?file=<?= urlencode($match['pago']['archivo']) ?>&download=1"
                                                                       download="<?= e($match['pago']['archivo']) ?>"
                                                                       class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center"
                                                                       title="Descargar Comprobante">
                                                                        <span class="material-symbols-outlined fs-6 text-dark">download</span>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <form method="POST" action="/admin/conciliacion/conciliar" class="d-inline">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="extracto_id" value="<?= e($match['extracto']['id']) ?>">
                                                                    <input type="hidden" name="pago_id" value="<?= e($match['pago']['id']) ?>">
                                                                    <input type="hidden" name="origen_tipo" value="<?= e($match['pago']['origen_tabla'] ?? 'pago') ?>">
                                                                    <button type="submit" class="btn btn-dark btn-sm text-white fw-bold d-inline-flex align-items-center gap-1"
                                                                            onclick="return confirm('¿Confirma la conciliación y aprobación de este pago?');"
                                                                            title="Aprobar y Conciliar">
                                                                        <span class="material-symbols-outlined fs-6 text-white">check</span>
                                                                        <span class="text-white d-none d-md-inline">Conciliar</span>
                                                                    </button>
                                                                </form>
                                                                <button type="button" class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center"
                                                                        onclick="abrirModalRechazo(<?= (int)$match['pago']['id'] ?>, '<?= e($match['pago']['origen_tabla'] ?? 'pago') ?>', '<?= e(addslashes($match['pago']['residente_nombre'] ?? 'Residente')) ?>', '<?= e(addslashes($match['pago']['referencia'] ?? 'S/R')) ?>')"
                                                                        title="Rechazar Pago">
                                                                    <span class="material-symbols-outlined fs-6 text-dark">cancel</span>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB 2: COINCIDENCIAS SUGERIDAS -->
                            <div class="tab-pane fade" id="sugeridas" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light border-bottom border-dark text-dark fw-bold">
                                            <tr>
                                                <th class="ps-4 py-3 text-dark">Movimiento Banco</th>
                                                <th class="py-3 text-dark">Pago Sugerido</th>
                                                <th class="py-3 text-center text-dark">Ref. Banco vs Pago</th>
                                                <th class="py-3 text-end text-dark">Monto (Bs.)</th>
                                                <th class="py-3 text-center text-dark">Similitud</th>
                                                <th class="py-3 text-end pe-4 text-dark">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($resultadoCruce['coincidencias_sugeridas'])): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-dark">
                                                        <span class="material-symbols-outlined fs-1 text-dark d-block mb-2">rule</span>
                                                        <strong class="text-dark">No hay coincidencias sugeridas pendientes.</strong>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($resultadoCruce['coincidencias_sugeridas'] as $match): ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="fw-bold text-dark"><?= e($match['extracto']['banco']) ?></div>
                                                            <small class="text-dark fw-normal"><?= date('d/m/Y', strtotime($match['extracto']['fecha_movimiento'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold text-dark"><?= e($match['pago']['residente_nombre']) ?></div>
                                                            <small class="text-dark fw-normal">Apto <?= e($match['pago']['unidad_numero']) ?> | Fecha: <?= date('d/m/Y', strtotime($match['pago']['fecha_pago'])) ?></small>
                                                            <?php if (!empty($match['alerta'])): ?>
                                                                <div><span class="badge bg-dark text-white border border-dark mt-1" style="font-size: 0.75rem;"><span class="material-symbols-outlined align-middle fs-6 text-white">warning</span> <?= e($match['alerta']) ?></span></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center font-monospace small text-dark">
                                                            <span class="text-dark fw-normal">Bco:</span> <strong class="text-dark"><?= e($match['extracto']['referencia_bancaria']) ?></strong><br>
                                                            <span class="text-dark fw-normal">Res:</span> <strong class="text-dark"><?= e($match['pago']['referencia']) ?></strong>
                                                        </td>
                                                        <td class="text-end font-monospace fw-bolder text-dark">
                                                            <?= e(formatearMoneda($match['extracto']['monto'])) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-light text-dark border border-dark rounded-pill px-3 py-1 fw-bold">
                                                                <?= e($match['similitud']) ?>% Similitud
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4 text-nowrap">
                                                            <div class="d-inline-flex align-items-center gap-1">
                                                                <button type="button" class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1"
                                                                        onclick='verDetalleConciliacion(<?= json_encode($match, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                                        title="Ver Detalle y Comprobante">
                                                                    <span class="material-symbols-outlined fs-6 text-dark">visibility</span>
                                                                    <span class="text-dark d-none d-xl-inline">Detalles</span>
                                                                </button>
                                                                <?php if (!empty($match['pago']['archivo'])): ?>
                                                                    <a href="/comprobante-proxy.php?file=<?= urlencode($match['pago']['archivo']) ?>&download=1"
                                                                       download="<?= e($match['pago']['archivo']) ?>"
                                                                       class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center"
                                                                       title="Descargar Comprobante">
                                                                        <span class="material-symbols-outlined fs-6 text-dark">download</span>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <form method="POST" action="/admin/conciliacion/conciliar" class="d-inline">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="extracto_id" value="<?= e($match['extracto']['id']) ?>">
                                                                    <input type="hidden" name="pago_id" value="<?= e($match['pago']['id']) ?>">
                                                                    <input type="hidden" name="origen_tipo" value="<?= e($match['pago']['origen_tabla'] ?? 'pago') ?>">
                                                                    <button type="submit" class="btn btn-dark btn-sm text-white fw-bold d-inline-flex align-items-center gap-1"
                                                                            onclick="return confirm('¿Confirmar conciliación sugerida?');"
                                                                            title="Aprobar Cruce Sugerido">
                                                                        <span class="material-symbols-outlined fs-6 text-white">check</span>
                                                                        <span class="text-white d-none d-md-inline">Aprobar Cruce</span>
                                                                    </button>
                                                                </form>
                                                                <button type="button" class="btn btn-outline-dark btn-sm text-dark fw-bold d-inline-flex align-items-center"
                                                                        onclick="abrirModalRechazo(<?= (int)$match['pago']['id'] ?>, '<?= e($match['pago']['origen_tabla'] ?? 'pago') ?>', '<?= e(addslashes($match['pago']['residente_nombre'] ?? 'Residente')) ?>', '<?= e(addslashes($match['pago']['referencia'] ?? 'S/R')) ?>')"
                                                                        title="Rechazar Pago">
                                                                    <span class="material-symbols-outlined fs-6 text-dark">cancel</span>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB 3: INCONSISTENCIAS -->
                            <div class="tab-pane fade" id="inconsistencias" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light border-bottom border-dark text-dark fw-bold">
                                            <tr>
                                                <th class="ps-4 py-3 text-dark">Fecha</th>
                                                <th class="py-3 text-dark">Banco / Descripción</th>
                                                <th class="py-3 text-dark">Referencia</th>
                                                <th class="py-3 text-end text-dark">Monto (Bs.)</th>
                                                <th class="py-3 text-dark">Detalle Inconsistencia</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($resultadoCruce['inconsistencias'])): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-5 text-dark">
                                                        <span class="material-symbols-outlined fs-1 text-dark d-block mb-2">task_alt</span>
                                                        <strong class="text-dark">No se detectaron inconsistencias en el extracto analizado.</strong>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($resultadoCruce['inconsistencias'] as $inc): ?>
                                                    <tr>
                                                        <td class="ps-4 small text-dark fw-normal"><?= date('d/m/Y', strtotime($inc['extracto']['fecha_movimiento'])) ?></td>
                                                        <td class="text-dark"><?= e($inc['extracto']['banco']) ?> - <?= e(substr($inc['extracto']['descripcion_banco'], 0, 40)) ?></td>
                                                        <td class="font-monospace fw-bold text-dark"><?= e($inc['extracto']['referencia_bancaria'] ?: 'N/A') ?></td>
                                                        <td class="text-end font-monospace fw-bolder text-dark"><?= e(formatearMoneda($inc['extracto']['monto'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-dark text-white rounded-pill px-3 py-1 fw-bold"><?= e($inc['motivo']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TAB 4: SIN COINCIDENCIA -->
                            <div class="tab-pane fade" id="sin-coincidencia" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light border-bottom border-dark text-dark fw-bold">
                                            <tr>
                                                <th class="ps-4 py-3 text-dark">Fecha Movimiento</th>
                                                <th class="py-3 text-dark">Banco / Descripción</th>
                                                <th class="py-3 text-center text-dark">Referencia Banco</th>
                                                <th class="py-3 text-end text-dark">Monto (Crédito)</th>
                                                <th class="py-3 text-center text-dark">Estado Cruce</th>
                                                <th class="py-3 text-end pe-4 text-dark">Lote</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($resultadoCruce['sin_coincidencia'])): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-dark">
                                                        <span class="material-symbols-outlined fs-1 text-dark d-block mb-2">task_alt</span>
                                                        <strong class="text-dark">Todos los créditos del extracto cuentan con coincidencias registradas.</strong>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($resultadoCruce['sin_coincidencia'] as $sc): ?>
                                                    <tr>
                                                        <td class="ps-4 small text-dark">
                                                            <div class="fw-bold text-dark"><?= date('d/m/Y', strtotime($sc['extracto']['fecha_movimiento'])) ?></div>
                                                        </td>
                                                        <td class="text-dark">
                                                            <div class="fw-bold text-dark"><?= e($sc['extracto']['banco']) ?></div>
                                                            <small class="text-dark fw-normal"><?= e(substr($sc['extracto']['descripcion_banco'] ?: ($sc['extracto']['descripcion'] ?? ''), 0, 50)) ?></small>
                                                        </td>
                                                        <td class="text-center font-monospace fw-bold text-dark">
                                                            <?= e($sc['extracto']['referencia_bancaria'] ?: ($sc['extracto']['referencia'] ?? 'N/A')) ?>
                                                        </td>
                                                        <td class="text-end font-monospace fw-bolder text-dark">
                                                            <?= e(formatearMoneda($sc['extracto']['monto'])) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-light text-dark border border-dark rounded-pill px-3 py-1 fw-bold">Sin Pago Pendiente</span>
                                                        </td>
                                                        <td class="text-end pe-4 text-dark small font-monospace">
                                                            <?= e($sc['extracto']['lote_importacion'] ?? '') ?>
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
</div>

<!-- Modal para Importar Extracto Bancario -->
<div class="modal fade" id="modalImportarExtracto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border border-dark shadow-lg">
            <form method="POST" action="/admin/conciliacion/importar" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold text-white flex-fill d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-white">upload_file</span>
                        <span class="text-white">Importar Extracto Bancario</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Entidad Bancaria *</label>
                        <select name="banco" required class="form-select border-dark text-dark">
                            <option value="venezuela">Banco de Venezuela (CSV / TXT / PDF)</option>
                            <option value="mercantil">Banco Mercantil (CSV / TXT)</option>
                            <option value="banesco">Banesco (CSV / TXT)</option>
                            <option value="provincial">BBVA Provincial (CSV / TXT)</option>
                            <option value="generico_csv">Formato CSV Genérico</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Archivo de Extracto (.CSV, .TXT o .PDF) *</label>
                        <input type="file" name="archivo_extracto" required accept=".csv,.txt,.pdf" class="form-control border-dark text-dark">
                        <div class="form-text small text-dark">Soporta estados de cuenta PDF de Banco de Venezuela y archivos CSV/TXT. Los débitos se clasificarán automáticamente como descartados.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top border-dark">
                    <button type="button" class="btn btn-light border border-dark fw-bold text-dark" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark fw-bold text-white">Procesar e Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ventana de Detalles de Pago en Conciliación -->
<div class="modal fade" id="modalDetallePago" tabindex="-1" aria-labelledby="modalDetallePagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border border-dark shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined fs-4 text-white">receipt_long</span>
                    <h5 class="modal-title fw-bold text-white mb-0" id="modalDetallePagoLabel">Detalle del Pago #<span id="mdlPagoId"></span></h5>
                    <span id="mdlEstadoBadge" class="badge bg-light text-dark border border-dark ms-2 fw-bold">PENDIENTE</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Columna Izquierda: Información Financiera y Cruce -->
                    <div class="col-lg-6">
                        <!-- Comparativa Banco vs Residente -->
                        <div class="card border border-dark shadow-sm rounded-3 mb-3">
                            <div class="card-header bg-white py-2 fw-bold small text-uppercase text-dark border-bottom border-dark d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined fs-6 text-dark">compare_arrows</span>
                                <span class="text-dark">Comparativa: Extracto Bancario vs Pago Reportado</span>
                            </div>
                            <div class="card-body p-3 bg-white">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered border-dark align-middle mb-0">
                                        <thead class="bg-light small text-dark">
                                            <tr>
                                                <th class="text-dark fw-bold">Campo</th>
                                                <th class="text-dark fw-bold">Extracto Bancario</th>
                                                <th class="text-dark fw-bold">Pago Reportado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small text-dark">
                                            <tr>
                                                <td class="fw-bold text-dark">Monto</td>
                                                <td class="fw-bolder text-dark font-monospace" id="mdlExtMonto">-</td>
                                                <td class="fw-bolder text-dark font-monospace" id="mdlPagoMonto">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-dark">Referencia</td>
                                                <td class="fw-bold text-dark font-monospace" id="mdlExtRef">-</td>
                                                <td class="fw-bold text-dark font-monospace" id="mdlPagoRef">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-dark">Fecha</td>
                                                <td class="text-dark" id="mdlExtFecha">-</td>
                                                <td class="text-dark" id="mdlPagoFecha">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-dark">Banco / Canal</td>
                                                <td class="text-dark" id="mdlExtBanco">-</td>
                                                <td class="text-dark" id="mdlPagoBanco">-</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Ficha de Datos del Residente e Inmueble -->
                        <div class="card border border-dark shadow-sm rounded-3 mb-3">
                            <div class="card-header bg-white py-2 fw-bold small text-uppercase text-dark border-bottom border-dark d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined fs-6 text-dark">person</span>
                                <span class="text-dark">Información del Residente e Inmueble</span>
                            </div>
                            <div class="card-body p-3 bg-white small text-dark">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <span class="text-dark d-block fw-normal">Residente:</span>
                                        <strong class="text-dark fw-bold" id="mdlResidente">-</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-dark d-block fw-normal">Cédula:</span>
                                        <strong class="text-dark fw-bold font-monospace" id="mdlCedula">-</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-dark d-block fw-normal">Inmueble / Unidad:</span>
                                        <strong class="text-dark fw-bold" id="mdlUnidad">-</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-dark d-block fw-normal">Método de Pago:</span>
                                        <strong class="text-dark fw-bold text-uppercase" id="mdlMetodo">-</strong>
                                    </div>
                                    <div class="col-12" id="mdlFacturaWrapper" style="display: none;">
                                        <div class="bg-light border border-dark rounded p-2 text-dark small">
                                            <strong class="text-dark">Factura Relacionada:</strong> #<span id="mdlFacturaNumero" class="text-dark font-monospace fw-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="col-12" id="mdlObsWrapper" style="display: none;">
                                        <span class="text-dark d-block fw-normal">Observaciones del Residente:</span>
                                        <div class="p-2 bg-light border border-dark rounded text-dark fst-italic" id="mdlObservaciones"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Vista Previa y Descarga de Comprobante -->
                    <div class="col-lg-6">
                        <div class="card border border-dark shadow-sm rounded-3 h-100 d-flex flex-column">
                            <div class="card-header bg-white py-2 border-bottom border-dark d-flex justify-content-between align-items-center">
                                <span class="fw-bold small text-uppercase text-dark d-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined fs-6 text-dark">attach_file</span>
                                    <span class="text-dark">Comprobante Adjunto</span>
                                </span>
                                <a id="mdlBtnDescargar" href="#" download="" class="btn btn-sm btn-dark text-white fw-bold d-inline-flex align-items-center gap-1">
                                    <span class="material-symbols-outlined fs-6 text-white">download</span>
                                    <span class="text-white">Descargar Comprobante</span>
                                </a>
                            </div>
                            <div class="card-body p-3 flex-grow-1 d-flex flex-column align-items-center justify-content-center bg-white" style="min-height: 350px;">
                                <!-- Contenedor Imagen -->
                                <div id="mdlPreviewImgContainer" class="w-100 text-center" style="display: none;">
                                    <img id="mdlImgPreview" src="" alt="Comprobante de pago" class="img-fluid rounded border border-dark shadow-sm" style="max-height: 400px; object-fit: contain;">
                                </div>
                                <!-- Contenedor PDF -->
                                <div id="mdlPreviewPdfContainer" class="w-100 h-100" style="display: none; min-height: 400px;">
                                    <iframe id="mdlPdfPreview" src="" class="w-100 h-100 rounded border border-dark" style="min-height: 400px;"></iframe>
                                </div>
                                <!-- Sin Archivo -->
                                <div id="mdlSinArchivoContainer" class="text-center text-dark p-4" style="display: none;">
                                    <span class="material-symbols-outlined display-4 text-dark mb-2">attachment</span>
                                    <p class="mb-0 fw-bold text-dark">No se adjuntó archivo de comprobante para este pago.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top border-dark py-3 d-flex justify-content-between flex-wrap gap-2">
                <a id="mdlLinkPantallaCompleta" href="#" class="btn btn-outline-dark btn-sm fw-bold text-dark d-inline-flex align-items-center gap-1">
                    <span class="material-symbols-outlined fs-6 text-dark">open_in_new</span>
                    <span class="text-dark">Ver Pantalla Completa de Detalle</span>
                </a>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-light btn-sm fw-bold text-dark border border-dark" data-bs-dismiss="modal">Cerrar</button>
                    <!-- Formulario Conciliar / Aprobar dentro del modal -->
                    <form id="mdlFormConciliar" method="POST" action="/admin/conciliacion/conciliar" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="extracto_id" id="mdlInputExtractoId" value="">
                        <input type="hidden" name="pago_id" id="mdlInputPagoId" value="">
                        <input type="hidden" name="origen_tipo" id="mdlInputOrigenTipo" value="pago">
                        <button type="submit" id="mdlBtnConfirmarConciliacion" class="btn btn-dark btn-sm fw-bold text-white d-inline-flex align-items-center gap-1"
                                onclick="return confirm('¿Confirma la conciliación y aprobación de este pago?');">
                            <span class="material-symbols-outlined fs-6 text-white">check</span>
                            <span class="text-white">Aprobar y Conciliar</span>
                        </button>
                    </form>
                    <!-- Botón Rechazar dentro del Modal -->
                    <button type="button" id="mdlBtnRechazarModal" class="btn btn-outline-dark btn-sm fw-bold text-dark d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6 text-dark">cancel</span>
                        <span class="text-dark">Rechazar Pago</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Rechazar Pago con Motivo Obligatorio -->
<div class="modal fade" id="modalRechazarConciliacion" tabindex="-1" aria-labelledby="modalRechazarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border border-dark shadow-lg rounded-3">
            <form method="POST" action="/admin/conciliacion/rechazar" id="formRechazarConciliacion">
                <?= csrf_field() ?>
                <input type="hidden" name="pago_id" id="rechazoModalPagoId" value="">
                <input type="hidden" name="origen_tipo" id="rechazoModalOrigenTipo" value="pago">

                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="modalRechazarLabel">
                        <span class="material-symbols-outlined text-white">cancel</span>
                        <span class="text-white">Rechazar Pago</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert bg-light border border-dark text-dark mb-3 py-2 px-3 small rounded-3">
                        El pago será marcado como <strong>RECHAZADO</strong> y se notificará de inmediato al residente con el motivo especificado.
                    </div>
                    
                    <div class="mb-3 small text-dark">
                        <span class="text-dark d-block fw-normal">Residente:</span>
                        <strong id="rechazoModalResidente" class="text-dark fw-bold">-</strong>
                        <span class="text-dark d-block mt-1 fw-normal">Referencia:</span>
                        <strong id="rechazoModalReferencia" class="text-dark font-monospace fw-bold">-</strong>
                    </div>

                    <div class="mb-3">
                        <label for="rechazoMotivo" class="form-label fw-bold small text-dark text-uppercase">
                            Motivo de Rechazo *
                        </label>
                        <textarea class="form-control border-dark text-dark" id="rechazoMotivo" name="motivo" rows="3" required minlength="5"
                                  placeholder="Indique el motivo claro del rechazo (mínimo 5 caracteres)..."></textarea>
                        <div class="form-text small text-dark">Mínimo 5 caracteres. Este texto será visible para el residente.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top border-dark py-3">
                    <button type="button" class="btn btn-light border border-dark btn-sm fw-bold text-dark" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark btn-sm fw-bold text-white d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6 text-white">gavel</span>
                        <span class="text-white">Confirmar Rechazo</span>
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

    function verDetallePagoDirecto(pago) {
        verDetalleConciliacion({
            extracto: null,
            pago: pago
        });
    }

    function verDetalleConciliacion(match) {
        const ext = match.extracto || null;
        const pago = match.pago || match || {};
        const isComp = (pago.origen_tabla === 'comprobante');

        document.getElementById('mdlPagoId').textContent = String(pago.id || 0).padStart(6, '0');
        document.getElementById('mdlEstadoBadge').textContent = (pago.estado || 'PENDIENTE').toUpperCase();

        // Comparativa
        if (ext) {
            document.getElementById('mdlExtMonto').textContent = 'Bs. ' + Number(ext.monto || 0).toLocaleString('es-VE', {minimumFractionDigits: 2});
            document.getElementById('mdlExtRef').textContent = ext.referencia_bancaria || ext.referencia || 'N/A';
            document.getElementById('mdlExtFecha').textContent = ext.fecha_movimiento || '-';
            document.getElementById('mdlExtBanco').textContent = ext.banco || '-';
            document.getElementById('mdlFormConciliar').action = '/admin/conciliacion/conciliar';
            document.getElementById('mdlInputExtractoId').value = ext.id || 0;
            document.getElementById('mdlBtnConfirmarConciliacion').querySelector('span:last-child').textContent = 'Aprobar y Conciliar';
        } else {
            document.getElementById('mdlExtMonto').textContent = 'Sin extracto vinculado';
            document.getElementById('mdlExtRef').textContent = 'N/A';
            document.getElementById('mdlExtFecha').textContent = '-';
            document.getElementById('mdlExtBanco').textContent = '-';
            document.getElementById('mdlFormConciliar').action = '/admin/conciliacion/verificar';
            document.getElementById('mdlInputExtractoId').value = '0';
            document.getElementById('mdlBtnConfirmarConciliacion').querySelector('span:last-child').textContent = 'Verificar y Aprobar';
        }

        document.getElementById('mdlPagoMonto').textContent = 'Bs. ' + Number(pago.monto || 0).toLocaleString('es-VE', {minimumFractionDigits: 2});
        document.getElementById('mdlPagoRef').textContent = pago.referencia || 'S/R';
        document.getElementById('mdlPagoFecha').textContent = pago.fecha_pago || '-';
        document.getElementById('mdlPagoBanco').textContent = (pago.banco_pagador || pago.banco_origen || 'No especificado');

        // Residente
        document.getElementById('mdlResidente').textContent = pago.residente_nombre || 'Residente';
        document.getElementById('mdlCedula').textContent = pago.residente_cedula || 'N/A';
        document.getElementById('mdlUnidad').textContent = (pago.edificio_nombre ? pago.edificio_nombre + ' - ' : '') + 'Unidad ' + (pago.unidad_numero || 'S/N');
        document.getElementById('mdlMetodo').textContent = (pago.metodo_pago || 'Transferencia').replace('_', ' ');

        // Factura
        const factWrap = document.getElementById('mdlFacturaWrapper');
        if (pago.numero_factura || pago.factura_id) {
            factWrap.style.display = 'block';
            document.getElementById('mdlFacturaNumero').textContent = pago.numero_factura || pago.factura_id;
        } else {
            factWrap.style.display = 'none';
        }

        // Observaciones
        const obsWrap = document.getElementById('mdlObsWrapper');
        if (pago.observaciones && pago.observaciones.trim() !== '') {
            obsWrap.style.display = 'block';
            document.getElementById('mdlObservaciones').textContent = pago.observaciones;
        } else {
            obsWrap.style.display = 'none';
        }

        // Archivo / Comprobante
        const archivo = pago.archivo || '';
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

        // Parámetros formulario
        document.getElementById('mdlInputPagoId').value = pago.id || 0;
        document.getElementById('mdlInputOrigenTipo').value = pago.origen_tabla || 'pago';

        // Link a pantalla completa
        const detailUrl = isComp 
            ? '/admin/comprobante/verificar?id=' + pago.id + '&from=conciliacion'
            : '/pagos/detalle/' + pago.id + '?from=conciliacion';
        document.getElementById('mdlLinkPantallaCompleta').href = detailUrl;

        // Configurar botón Rechazar dentro del modal de detalle
        document.getElementById('mdlBtnRechazarModal').onclick = function() {
            const modalDetalle = bootstrap.Modal.getInstance(document.getElementById('modalDetallePago'));
            if (modalDetalle) modalDetalle.hide();
            abrirModalRechazo(pago.id, pago.origen_tabla || 'pago', pago.residente_nombre, pago.referencia);
        };

        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('modalDetallePago'));
        modal.show();
    }

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
