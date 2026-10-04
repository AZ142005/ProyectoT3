                <!-- Encabezado interno y Filtros de la pestaña -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-primary fs-4">manage_accounts</span>
                        <h2 class="text-lg font-bold text-on-surface mb-0">Cambios de Datos de Residentes</h2>
                    </div>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtros de estado de cambios de datos">
                        <a href="/admin/usuarios?tab=cambios" class="<?= empty($estadoCambio) ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Todas<?php if (empty($estadoCambio)): ?> (<?= e($paginacionCambios['total']) ?>)<?php endif; ?>
                        </a>
                        <a href="/admin/usuarios?tab=cambios&estado=pendiente" class="<?= ($estadoCambio === 'pendiente') ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Pendientes
                            <?php if ($cambiosPendientesCount > 0): ?>
                                <span class="bg-amber-300 text-amber-950 ms-1 rounded-full px-1.5 text-[10px] font-bold"><?= e($cambiosPendientesCount) ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/admin/usuarios?tab=cambios&estado=aprobado" class="<?= ($estadoCambio === 'aprobado') ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Aprobadas
                        </a>
                        <a href="/admin/usuarios?tab=cambios&estado=rechazado" class="<?= ($estadoCambio === 'rechazado') ? 'px-3 py-1.5 rounded-full border border-primary bg-primary text-white text-xs font-bold' : 'px-3 py-1.5 rounded-full border border-slate-200 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors' ?>">
                            Rechazadas
                        </a>
                    </div>
                </div>

                <!-- Tabla de Solicitudes de Cambio de Datos -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="text-lg font-bold text-on-surface mb-0">Bandeja de Cambios de Datos</h5>
                        <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant"><?= e($paginacionCambios['total']) ?> Registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead>
                                    <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                        <th class="py-3 px-4">Residente</th>
                                        <th class="py-3 px-4">Unidad / Edificio</th>
                                        <th class="py-3 px-4">Cambios Solicitados</th>
                                        <th class="py-3 px-4 text-center">Estado</th>
                                        <th class="py-3 px-4">Fecha / Revisión</th>
                                        <th class="py-3 px-4 text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-background">
                                    <?php if (empty($solicitudesCambio)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-12 text-on-surface-variant">
                                                <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">manage_accounts</span>
                                                No hay solicitudes de cambio de datos <?= !empty($estadoCambio) ? 'con estado ' . e($estadoCambio) : 'registradas' ?>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        $etiquetasCampos = [
                                            'telefono'       => 'Teléfono',
                                            'email'          => 'Correo',
                                            'direccion'      => 'Dirección',
                                            'vehiculo_placa' => 'Vehículo (placa)',
                                        ];
                                        ?>
                                        <?php foreach ($solicitudesCambio as $s): ?>
                                            <?php
                                            $datosNuevos = json_decode($s['datos_nuevos_json'] ?? '', true);
                                            if (!is_array($datosNuevos)) {
                                                $datosNuevos = [];
                                            }
                                            $valoresActuales = [
                                                'telefono' => $s['residente_telefono_actual'] ?? null,
                                                'email'    => $s['residente_email_actual'] ?? null,
                                            ];
                                            ?>
                                            <tr class="hover:bg-background/40 transition-colors">
                                                <td class="py-4 px-4">
                                                    <div class="font-semibold text-on-surface"><?= e($s['residente_nombre']) ?></div>
                                                    <div class="text-xs text-on-surface-variant">
                                                        <span>C.I: <strong><?= e($s['residente_cedula']) ?></strong></span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="font-semibold text-on-surface">
                                                        <?= e($s['edificio_nombre'] ?: 'Sin Torre') ?> — Apto. <?= e($s['unidad_numero'] ?: 'N/A') ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <?php if (empty($datosNuevos)): ?>
                                                        <span class="text-xs text-on-surface-variant fst-italic">Sin datos nuevos</span>
                                                    <?php else: ?>
                                                        <?php foreach ($datosNuevos as $campo => $valorSolicitado): ?>
                                                            <?php
                                                            $etiquetaCampo = $etiquetasCampos[$campo] ?? ucfirst(str_replace('_', ' ', (string)$campo));
                                                            $valorActual = $valoresActuales[$campo] ?? null;
                                                            $hayActual = $valorActual !== null && trim((string)$valorActual) !== '';
                                                            $sinCambio = $hayActual && trim((string)$valorActual) === trim((string)$valorSolicitado);
                                                            ?>
                                                            <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                                                <span class="text-xs fw-bold text-on-surface" style="min-width: 110px;"><?= e($etiquetaCampo) ?>:</span>
                                                                <?php if ($hayActual): ?>
                                                                    <span class="badge bg-light text-on-surface-variant border text-decoration-line-through" title="Valor actual">Actual: <?= e($valorActual) ?></span>
                                                                    <span class="material-symbols-outlined text-[14px] text-on-surface-variant">arrow_forward</span>
                                                                <?php endif; ?>
                                                                <span class="badge bg-green-50 text-green-700 border border-green-200" title="Valor solicitado">Solicitado: <?= e($valorSolicitado) ?></span>
                                                                <?php if ($sinCambio): ?>
                                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">Sin cambio</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php if ($s['estado'] === 'aprobado'): ?>
                                                        <span class="badge bg-success rounded-pill px-3 py-1.5">Aprobada</span>
                                                    <?php elseif ($s['estado'] === 'rechazado'): ?>
                                                        <span class="badge bg-danger rounded-pill px-3 py-1.5">Rechazada</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-on-surface rounded-pill px-3 py-1.5">Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4 px-4 text-xs text-on-surface-variant">
                                                    <div><?= !empty($s['fecha_solicitud']) ? date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) : 'N/A' ?></div>
                                                    <?php if (!empty($s['fecha_respuesta'])): ?>
                                                        <div class="text-xs text-on-surface-variant mt-1">
                                                            Revisado: <?= date('d/m/Y H:i', strtotime($s['fecha_respuesta'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($s['motivo_admin'])): ?>
                                                        <div class="text-danger mt-1 text-xs">
                                                            <strong>Motivo:</strong> <?= e($s['motivo_admin']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4 px-4 text-end">
                                                    <?php if ($s['estado'] === 'pendiente' && \App\Core\Auth::role() !== 'auditor'): ?>
                                                        <form method="POST" action="/admin/usuarios/procesar-solicitud-cambio" class="d-flex flex-column gap-1" style="min-width: 200px;">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                                                            <input type="text" name="motivo" maxlength="255"
                                                                   class="form-control form-control-sm text-xs"
                                                                   placeholder="Motivo (obligatorio al rechazar)"
                                                                   onkeydown="if (event.key === 'Enter') { event.preventDefault(); }">
                                                            <div class="d-flex gap-1 justify-content-end">
                                                                <button type="submit" name="accion" value="aprobar"
                                                                        class="bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                                                                        onclick="return confirm('¿Aprobar y aplicar el cambio de datos de <?= e($s['residente_nombre']) ?>?');">
                                                                    <span class="material-symbols-outlined text-[16px] align-middle">check</span>
                                                                    Aprobar
                                                                </button>
                                                                <button type="submit" name="accion" value="rechazar"
                                                                        class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                                                                        onclick="return validarRechazoCambio(this);">
                                                                    <span class="material-symbols-outlined text-[16px] align-middle">close</span>
                                                                    Rechazar
                                                                </button>
                                                            </div>
                                                        </form>
                                                    <?php elseif ($s['estado'] === 'pendiente'): ?>
                                                        <span class="badge bg-warning text-on-surface rounded-pill px-3 py-1.5">Pendiente</span>
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
                            $filtros = ['estado' => $estadoCambio ?? '', 'tab' => 'cambios'];
                            $paginacion = $paginacionCambios;
                            include VIEWS_PATH . '/components/pagination.php';
                            ?>
                        </div>
                    </div>
                </div>

<script>
    /**
     * Exige el motivo únicamente al rechazar; aprobar no lo requiere.
     */
    function validarRechazoCambio(btn) {
        const form = btn.form;
        const motivo = form ? form.querySelector('input[name="motivo"]') : null;
        if (!motivo) {
            return true;
        }
        if (motivo.value.trim() === '') {
            motivo.setCustomValidity('Debe indicar el motivo para rechazar la solicitud de cambio de datos.');
            motivo.reportValidity();
            motivo.setCustomValidity('');
            return false;
        }
        return true;
    }
</script>
