<?php
$role = \App\Core\Auth::role();
$isAdmin = ($role === 'admin');
$isAuditor = ($role === 'auditor');
$estado = strtoupper(trim($pago['estado'] ?? 'PENDIENTE'));
$archivo = $pago['archivo'] ?? '';
$hasArchivo = !empty($archivo);
$archivoUrl = $hasArchivo ? '/comprobante-proxy.php?file=' . urlencode($archivo) : '#';
$archivoDescargaUrl = $hasArchivo ? '/comprobante-proxy.php?file=' . urlencode($archivo) . '&download=1' : '#';
$isPDF = $hasArchivo && (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'pdf');

// Normalización polimórfica de campos
$pagoId = $pago['id'] ?? 0;
$residenteNombre = $pago['residente_nombre'] ?? $pago['residente'] ?? 'Residente';
$residenteCedula = $pago['residente_cedula'] ?? $pago['cedula'] ?? '';
$unidadNumero = $pago['unidad_numero'] ?? $pago['unidad'] ?? '';
$edificioNombre = $pago['edificio_nombre'] ?? '';
$tipoOrigen = $pago['tipo_origen'] ?? 'pago'; // 'pago' o 'comprobante'
$actionUrl = $pago['action_url'] ?? '/pagos/cambiar-estado';

$tieneFactura = !empty($pago['numero_factura']) || !empty($pago['factura_id']);
$numeroFactura = $pago['numero_factura'] ?? '';
$saldoFactura = floatval($pago['saldo_factura'] ?? $pago['saldo'] ?? 0);
$montoPago = floatval($pago['monto'] ?? 0);
$saldoRestante = isset($pago['saldo_restante']) ? floatval($pago['saldo_restante']) : ($saldoFactura - $montoPago);
$fromParam = $_GET['from'] ?? '';
$origenForm = match($fromParam) {
    'conciliacion' => 'conciliacion',
    'dashboard'    => 'dashboard',
    default        => 'detalle',
};
?>
<?php if ($isAdmin || $isAuditor): ?>
<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = ($tipoOrigen === 'comprobante') ? 'comprobantes' : 'pagos'; 
    if ($isAuditor) {
        require VIEWS_PATH . '/layouts/auditor_sidebar.php';
    } else {
        require VIEWS_PATH . '/layouts/admin_sidebar.php';
    }
    ?>
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Detalle del Pago #<?= e(str_pad($pagoId, 6, '0', STR_PAD_LEFT)) ?></h1>
            </div>
            <a href="<?= $isAuditor ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="max-w-6xl mx-auto space-y-6">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- Barra de Navegación y Descarga -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <a href="/pagos" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2 rounded-xl border border-slate-200 transition-colors inline-flex items-center gap-1.5 shadow-sm">
                            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                            <span>Volver a Pagos</span>
                        </a>
                        <a href="/admin/comprobantes" class="bg-slate-50 hover:bg-slate-100 text-slate-600 font-semibold text-xs px-3.5 py-2 rounded-xl border border-slate-200 transition-colors inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">history</span>
                            <span>Historial de Pagos</span>
                        </a>
                        <?php if ($fromParam === 'conciliacion'): ?>
                            <a href="/admin/conciliacion" class="bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs px-3.5 py-2 rounded-xl border border-primary/20 transition-colors inline-flex items-center gap-1.5 shadow-sm">
                                <span class="material-symbols-outlined text-[16px]">sync_alt</span>
                                <span>Volver a Conciliación</span>
                            </a>
                        <?php elseif ($fromParam === 'dashboard'): ?>
                            <a href="/admin/dashboard" class="bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs px-3.5 py-2 rounded-xl border border-primary/20 transition-colors inline-flex items-center gap-1.5 shadow-sm">
                                <span class="material-symbols-outlined text-[16px]">dashboard</span>
                                <span>Volver al Dashboard</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasArchivo): ?>
                        <a href="<?= e($archivoDescargaUrl) ?>" download="<?= e($archivo) ?>" class="bg-primary hover:bg-primary-hover text-white text-xs font-bold px-4 py-2 rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 active:scale-95">
                            <span class="material-symbols-outlined text-[16px]">download</span>
                            <span>Descargar Comprobante</span>
                        </a>
                    <?php endif; ?>
                </div>
<?php else: ?>
<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Mensajes Flash -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Encabezado Residente -->
    <div class="bg-gradient-to-r from-slate-800 to-slate-900 text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold flex items-center gap-2">
                <span class="material-symbols-outlined">receipt_long</span>
                Detalle del Pago #<?= e(str_pad($pagoId, 6, '0', STR_PAD_LEFT)) ?>
            </h2>
            <p class="text-sm text-slate-300 mt-1">Residente: <?= e($residenteNombre) ?> <?php if ($residenteCedula): ?>(C.I: <?= e($residenteCedula) ?>)<?php endif; ?></p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($hasArchivo): ?>
                <a href="<?= e($archivoDescargaUrl) ?>" download="<?= e($archivo) ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Descargar Comprobante
                </a>
            <?php endif; ?>
            <a href="/pagos" class="bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Volver a la lista
            </a>
        </div>
    </div>
<?php endif; ?>

    <!-- Contenido Principal -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Columna Izquierda: Información Financiera y Archivo -->
        <div class="lg:col-span-2 flex flex-col gap-8">
            
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6 border-b border-background pb-3">
                    <h3 class="text-lg font-bold text-on-surface">Información General del Pago</h3>
                    <div>
                        <?= badgeEstado($pago['estado']) ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Monto Pagado</span>
                        <p class="text-2xl font-black text-primary"><?= e(formatearMoneda($pago['monto'])) ?></p>
                    </div>
                    
                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Estado Actual</span>
                        <p class="text-sm font-bold text-on-surface uppercase"><?= e($pago['estado']) ?></p>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Inmueble / Unidad</span>
                        <p class="text-sm font-semibold text-on-surface">
                            <?= !empty($edificioNombre) ? e($edificioNombre) . ' - ' : '' ?>Unidad <?= e($unidadNumero) ?>
                        </p>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Fecha de Pago</span>
                        <p class="text-sm font-semibold text-on-surface"><?= e(date('d/m/Y', strtotime($pago['fecha_pago']))) ?></p>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Referencia</span>
                        <p class="text-sm font-semibold text-on-surface font-mono"><?= e($pago['referencia'] ?: 'No especificada') ?></p>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Método de Pago</span>
                        <p class="text-sm font-semibold text-on-surface uppercase text-xs"><?= e(str_replace('_', ' ', $pago['metodo_pago'] ?? 'transferencia')) ?></p>
                    </div>
                    
                    <!-- Bancos si existen -->
                    <?php if (!empty($pago['banco_pagador']) || !empty($pago['banco_receptor'])): ?>
                    <div class="col-span-2 md:col-span-3">
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Bancos y Cuentas</span>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-on-surface-variant font-semibold">Banco Origen (Pagador):</span> 
                                <span class="font-bold text-on-surface"><?= e($pago['banco_pagador'] ?: 'No indicado') ?></span>
                            </div>
                            <div>
                                <span class="text-on-surface-variant font-semibold">Banco Destino (Receptor):</span> 
                                <span class="font-bold text-on-surface"><?= e($pago['banco_receptor'] ?: 'No indicado') ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Desglose de Factura Relacionada si aplica -->
                    <?php if ($tieneFactura): ?>
                    <div class="col-span-2 md:col-span-3 bg-blue-50/50 border border-blue-200 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-blue-900 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">receipt</span>
                            Factura Relacionada #<?= e($numeroFactura) ?>
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span class="text-slate-500 font-semibold block">Saldo de Factura</span>
                                <span class="font-bold text-on-surface text-sm"><?= e(formatearMoneda($saldoFactura)) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-500 font-semibold block">Monto a Aplicar</span>
                                <span class="font-bold text-primary text-sm"><?= e(formatearMoneda($montoPago)) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-500 font-semibold block">Saldo Restante Estimado</span>
                                <span class="font-black text-sm <?= $saldoRestante < 0 ? 'text-green-600' : ($saldoRestante > 0 ? 'text-red-500' : 'text-blue-600') ?>">
                                    <?= e(formatearMoneda($saldoRestante)) ?>
                                    <?php if ($saldoRestante < 0): ?>
                                        <span class="text-[11px] font-normal text-green-600 block">(Generará saldo a favor de <?= e(formatearMoneda(abs($saldoRestante))) ?>)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($pago['observaciones'])): ?>
                    <div class="mt-6 pt-4 border-t border-background">
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Observaciones / Notas del Residente</span>
                        <p class="text-sm text-on-surface-variant italic bg-slate-50 p-3 rounded-lg"><?= nl2br(e($pago['observaciones'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sección de Comprobante Digital / Físico -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6 border-b border-background pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">attach_file</span>
                        <h3 class="text-lg font-bold text-on-surface">Comprobante de Pago Adjunto</h3>
                    </div>
                    <?php if ($hasArchivo): ?>
                        <span class="text-xs bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200">
                            <?= $isPDF ? 'Documento PDF' : 'Imagen' ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (!$hasArchivo): ?>
                    <div class="flex flex-col items-center justify-center p-12 border border-dashed border-outline-variant rounded-xl bg-slate-50 text-slate-400">
                        <span class="material-symbols-outlined text-5xl mb-2 text-slate-300">no_photography</span>
                        <p class="text-sm font-semibold">No se adjuntó archivo de comprobante físico para este pago.</p>
                    </div>
                <?php elseif ($isPDF): ?>
                    <div class="flex flex-col items-center justify-center p-8 border border-dashed border-outline-variant rounded-xl bg-slate-50">
                        <span class="material-symbols-outlined text-6xl text-red-500 mb-3">picture_as_pdf</span>
                        <p class="text-sm font-bold text-on-surface mb-1 font-mono"><?= e($archivo) ?></p>
                        <p class="text-xs text-slate-500 mb-6">El comprobante es un documento digital en formato PDF.</p>
                        
                        <div class="flex flex-wrap gap-3 justify-center">
                            <a href="<?= e($archivoUrl) ?>" target="_blank" class="bg-white hover:bg-slate-50 text-slate-700 font-bold px-5 py-2.5 rounded-xl border border-slate-300 transition-colors flex items-center gap-1.5 text-xs shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                                Abrir en Nueva Pestaña
                            </a>
                            <a href="<?= e($archivoDescargaUrl) ?>" download="<?= e($archivo) ?>" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5 text-xs active:scale-95">
                                <span class="material-symbols-outlined text-[18px]">download</span>
                                Descargar Comprobante PDF
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-4">
                        <div class="rounded-xl overflow-hidden border border-outline-variant shadow-inner bg-slate-100 flex justify-center p-4">
                            <a href="<?= e($archivoUrl) ?>" target="_blank" title="Haz clic para ver tamaño completo" class="relative group block">
                                <img src="<?= e($archivoUrl) ?>" alt="Comprobante de pago" class="max-w-full max-h-[500px] object-contain rounded-lg shadow-sm group-hover:opacity-95 transition-opacity">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-bold gap-1 rounded-lg">
                                    <span class="material-symbols-outlined">zoom_in</span>
                                    Ampliar Comprobante
                                </div>
                            </a>
                        </div>

                        <!-- Botones de Acción sobre el Comprobante -->
                        <div class="flex flex-wrap gap-3 justify-between items-center pt-2">
                            <span class="text-xs text-slate-500 font-mono"><?= e($archivo) ?></span>
                            <div class="flex gap-2">
                                <a href="<?= e($archivoUrl) ?>" target="_blank" class="bg-white hover:bg-slate-100 text-slate-700 font-bold px-4 py-2 rounded-xl border border-slate-300 transition-colors flex items-center gap-1.5 text-xs shadow-sm">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    Ver Tamaño Completo
                                </a>
                                <a href="<?= e($archivoDescargaUrl) ?>" download="<?= e($archivo) ?>" class="bg-primary hover:bg-primary-hover text-white font-bold px-4 py-2 rounded-xl shadow-sm transition-all flex items-center gap-1.5 text-xs active:scale-95">
                                    <span class="material-symbols-outlined text-[16px]">download</span>
                                    Descargar Comprobante
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        
        <!-- Columna Derecha: Flujo de Aprobación y Auditoría -->
        <div class="lg:col-span-1 flex flex-col gap-6">
            
            <!-- TARJETA DE ACCIONES DE APROBACIÓN (ADMINISTRADOR) -->
            <?php if ($isAdmin): ?>
                <?php if (in_array($estado, ['PENDIENTE', 'EN REVISIÓN'])): ?>
                    <div class="bg-white rounded-2xl border-2 border-primary/20 p-6 shadow-md">
                        <div class="flex items-center gap-2 mb-4 pb-3 border-b border-background">
                            <span class="material-symbols-outlined text-primary">gavel</span>
                            <h3 class="text-base font-bold text-on-surface">Flujo de Aprobación</h3>
                        </div>
                        
                        <p class="text-xs text-on-surface-variant mb-5">
                            Revise todos los datos financieros y el comprobante adjunto antes de emitir la resolución del pago.
                        </p>

                        <div class="flex flex-col gap-3">
                            <?php if ($tipoOrigen === 'comprobante'): ?>
                                <!-- Acciones para Comprobantes de Factura -->
                                <form method="POST" action="<?= e($actionUrl) ?>" class="flex flex-col gap-3" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="origen" value="<?= e($origenForm) ?>">
                                    <div class="flex flex-col gap-1.5">
                                        <label for="admin_obs" class="text-xs font-semibold text-slate-600">Observaciones (Opcional en aprobación, obligatoria en rechazo)</label>
                                        <textarea name="observaciones" id="admin_obs" rows="2" placeholder="Notas sobre la verificación..."
                                                  class="w-full px-3 py-2 bg-background border border-outline-variant rounded-xl text-xs resize-none focus:outline-none focus:border-primary"></textarea>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" name="accion" value="aprobar" onclick="return confirm('¿Confirma la aprobación de este comprobante de factura?');"
                                                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-3 rounded-xl shadow-sm text-xs flex items-center justify-center gap-1.5 active:scale-95 cursor-pointer">
                                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                            Aprobar
                                        </button>
                                        <button type="submit" name="accion" value="rechazar" onclick="var obs = document.getElementById('admin_obs').value.trim(); if (obs.length < 5) { alert('Debe ingresar un motivo de rechazo claro (mínimo 5 caracteres) en el campo de observaciones.'); return false; } return confirm('¿Confirma el rechazo de este comprobante?');"
                                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-3 rounded-xl shadow-sm text-xs flex items-center justify-center gap-1.5 active:scale-95 cursor-pointer">
                                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                                            Rechazar
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Acciones para Pagos Generales -->
                                <form method="POST" action="/pagos/cambiar-estado" class="w-full" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="pago_id" value="<?= e($pagoId) ?>">
                                    <input type="hidden" name="nuevo_estado" value="APROBADO">
                                    <input type="hidden" name="origen" value="<?= e($origenForm) ?>">
                                    <button type="submit" onclick="return confirm('¿Confirma la aprobación de este pago? Se aplicará en cascada a las facturas y saldos correspondientes.');"
                                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 text-sm active:scale-95 cursor-pointer">
                                        <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                        Aprobar Pago
                                    </button>
                                </form>

                                <?php if ($estado === 'PENDIENTE'): ?>
                                    <form method="POST" action="/pagos/cambiar-estado" class="w-full" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="pago_id" value="<?= e($pagoId) ?>">
                                        <input type="hidden" name="nuevo_estado" value="EN REVISIÓN">
                                        <input type="hidden" name="origen" value="<?= e($origenForm) ?>">
                                        <button type="submit" 
                                                class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold py-2.5 px-4 rounded-xl border border-blue-200 transition-colors flex items-center justify-center gap-2 text-xs cursor-pointer">
                                            <span class="material-symbols-outlined text-[18px]">pending</span>
                                            Poner En Revisión
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <button type="button" onclick="openRechazarModal(<?= e($pagoId) ?>)" 
                                        class="w-full bg-red-50 hover:bg-red-100 text-red-700 font-bold py-2.5 px-4 rounded-xl border border-red-200 transition-colors flex items-center justify-center gap-2 text-xs cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                                    Rechazar Pago...
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                        <div class="flex items-center gap-2 mb-3 pb-3 border-b border-background">
                            <span class="material-symbols-outlined <?= $estado === 'APROBADO' ? 'text-emerald-600' : 'text-red-600' ?>">
                                <?= $estado === 'APROBADO' ? 'task_alt' : 'block' ?>
                            </span>
                            <h3 class="text-base font-bold text-on-surface">Resolución de Pago</h3>
                        </div>
                        <div class="p-4 rounded-xl <?= $estado === 'APROBADO' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?> text-xs leading-relaxed">
                            <p class="font-bold text-sm mb-1">Pago <?= e($estado) ?></p>
                            <p>
                                <?= $estado === 'APROBADO' 
                                    ? 'Este pago fue verificado y aprobado. Los saldos y facturas han sido liquidados.' 
                                    : 'Este pago fue rechazado. No se generaron modificaciones a las facturas.' ?>
                            </p>
                            <p class="mt-2 text-[11px] opacity-80">
                                La opción de descarga del comprobante permanece activa para fines de auditoría histórica.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- LÍNEA DE TIEMPO (AUDITORÍA) -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <h3 class="text-lg font-bold text-on-surface mb-6 border-b border-background pb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">history</span>
                    Historial de Auditoría
                </h3>
                
                <div class="relative pl-6 border-l-2 border-slate-200 flex flex-col gap-6 ml-2">
                    <!-- Evento Inicial -->
                    <div class="relative">
                        <div class="absolute -left-[35px] top-1 bg-yellow-500 h-4 w-4 rounded-full border-4 border-white shadow-sm ring-4 ring-yellow-500/20"></div>
                        <p class="text-sm font-bold text-on-surface">Pago Registrado (PENDIENTE)</p>
                        <p class="text-xs font-semibold text-on-surface-variant mt-0.5">Por <?= e($residenteNombre) ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5">
                            <?= !empty($pago['fecha_registro']) ? e(date('d/m/Y h:i A', strtotime($pago['fecha_registro']))) : e(date('d/m/Y', strtotime($pago['fecha_pago']))) ?>
                        </p>
                    </div>

                    <!-- Eventos subsecuentes -->
                    <?php if (!empty($pago['log_auditoria'])): ?>
                        <?php 
                        $logs = array_reverse($pago['log_auditoria']);
                        foreach ($logs as $log): 
                            $dotColor = 'bg-slate-400 ring-slate-400/20';
                            if ($log['estado_nuevo'] === 'APROBADO') $dotColor = 'bg-green-500 ring-green-500/20';
                            if ($log['estado_nuevo'] === 'EN REVISIÓN') $dotColor = 'bg-blue-500 ring-blue-500/20';
                            if ($log['estado_nuevo'] === 'RECHAZADO') $dotColor = 'bg-red-500 ring-red-500/20';
                        ?>
                        <div class="relative">
                            <div class="absolute -left-[35px] top-1 <?= e($dotColor) ?> h-4 w-4 rounded-full border-4 border-white shadow-sm ring-4"></div>
                            <p class="text-sm font-bold text-on-surface">Cambio a <?= e($log['estado_nuevo']) ?></p>
                            <p class="text-xs font-semibold text-on-surface-variant mt-0.5">Por <?= e($log['admin_nombre']) ?></p>
                            <p class="text-[10px] text-slate-400 mt-0.5"><?= e(date('d/m/Y h:i A', strtotime($log['fecha_registro']))) ?></p>
                            
                            <?php if (!empty($log['motivo'])): ?>
                                <div class="mt-2 text-xs bg-slate-50 border border-slate-200 p-3 rounded-lg text-on-surface-variant italic">
                                    "<?= e($log['motivo']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif ($estado !== 'PENDIENTE'): ?>
                        <div class="relative">
                            <div class="absolute -left-[35px] top-1 <?= $estado === 'APROBADO' ? 'bg-green-500 ring-green-500/20' : 'bg-red-500 ring-red-500/20' ?> h-4 w-4 rounded-full border-4 border-white shadow-sm ring-4"></div>
                            <p class="text-sm font-bold text-on-surface">Procesado como <?= e($estado) ?></p>
                            <p class="text-xs font-semibold text-on-surface-variant mt-0.5">Por Administración</p>
                            <?php if (!empty($pago['observaciones_admin'])): ?>
                                <div class="mt-2 text-xs bg-slate-50 border border-slate-200 p-3 rounded-lg text-on-surface-variant italic">
                                    "<?= e($pago['observaciones_admin']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
<?php if ($isAdmin || $isAuditor): ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>

<!-- Modal para Motivo de Rechazo (Admin para Pagos Generales) -->
<?php if ($isAdmin && $tipoOrigen === 'pago'): ?>
<div id="modalRechazo" class="hidden fixed inset-0 bg-black/60 items-center justify-center p-4 z-50 transition-opacity">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl overflow-hidden border border-outline-variant">
        <form method="POST" action="/pagos/cambiar-estado" id="formRechazo" onsubmit="this.querySelectorAll('button[type=submit]').forEach(b => b.disabled = true);">
            <?= csrf_field() ?>
            <input type="hidden" name="pago_id" id="rechazoPagoId" value="<?= e($pagoId) ?>">
            <input type="hidden" name="nuevo_estado" value="RECHAZADO">
            <input type="hidden" name="origen" value="<?= e($origenForm) ?>">
            
            <div class="p-5 border-b border-background flex justify-between items-center bg-red-50">
                <h3 class="font-bold text-red-700 flex items-center gap-1.5 text-base">
                    <span class="material-symbols-outlined">cancel</span>
                    Rechazar Pago
                </h3>
            </div>
            
            <div class="p-5 flex flex-col gap-3">
                <p class="text-xs text-on-surface-variant mb-2">Por favor, indique el motivo obligatorio por el cual está rechazando este comprobante de pago.</p>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase">Motivo del rechazo <span class="text-red-500">*</span></label>
                    <textarea name="motivo" id="rechazoMotivo" rows="3" required placeholder="Ej: Imagen borrosa, referencia no coincide, monto incompleto..."
                              class="w-full px-3 py-2 bg-background border border-outline-variant rounded-xl text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all resize-none"></textarea>
                </div>
            </div>
            
            <div class="p-4 border-t border-background flex justify-end gap-2 bg-slate-50">
                <button type="button" onclick="closeRechazarModal()" class="bg-slate-200 hover:bg-slate-300 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-all cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-md transition-all cursor-pointer">
                    Confirmar Rechazo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRechazarModal(pagoId) {
    document.getElementById('rechazoPagoId').value = pagoId;
    var modal = document.getElementById('modalRechazo');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRechazarModal() {
    var modal = document.getElementById('modalRechazo');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
<?php endif; ?>
