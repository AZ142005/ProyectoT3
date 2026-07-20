<div class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado -->
    <div class="bg-gradient-to-r from-slate-800 to-slate-900 text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold flex items-center gap-2">
                <span class="material-symbols-outlined">receipt_long</span>
                Detalle del Pago #<?= e(str_pad($pago['id'], 6, '0', STR_PAD_LEFT)) ?>
            </h2>
            <p class="text-sm text-slate-300 mt-1">Residente: <?= e($pago['residente_nombre']) ?> (C.I: <?= e($pago['residente_cedula']) ?>)</p>
        </div>
        <a href="/pagos" class="bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-all flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Volver a la lista
        </a>
    </div>

    <!-- Contenido -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Columna Izquierda: Detalles y Archivo -->
        <div class="lg:col-span-2 flex flex-col gap-8">
            
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <h3 class="text-lg font-bold text-on-surface mb-6 border-b border-background pb-3">Información del Pago</h3>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Monto Pagado</span>
                        <p class="text-2xl font-black text-primary">Bs. <?= e(formatearMoneda($pago['monto'])) ?></p>
                    </div>
                    
                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Estado Actual</span>
                        <?php
                        $statusClasses = [
                            'PENDIENTE' => 'bg-yellow-100 text-yellow-800',
                            'EN REVISIÓN' => 'bg-blue-100 text-blue-800',
                            'APROBADO'  => 'bg-green-100 text-green-800',
                            'RECHAZADO' => 'bg-red-100 text-red-800'
                        ];
                        $badge = $statusClasses[$pago['estado']] ?? 'bg-slate-100 text-slate-800';
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-bold inline-block <?= $badge ?>">
                            <?= e($pago['estado']) ?>
                        </span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Unidad Involucrada</span>
                        <p class="text-sm font-semibold text-on-surface"><?= e($pago['edificio_nombre']) ?> - Unidad <?= e($pago['unidad_numero']) ?></p>
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
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Bancos</span>
                        <p class="text-xs text-on-surface font-medium leading-tight">
                            <span class="text-on-surface-variant">Origen:</span> <?= e($pago['banco_pagador'] ?: 'No indicado') ?><br>
                            <span class="text-on-surface-variant">Destino:</span> <?= e($pago['banco_receptor'] ?: 'No indicado') ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($pago['observaciones'])): ?>
                    <div class="mt-6 pt-4 border-t border-background">
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant block mb-1">Observaciones / Notas</span>
                        <p class="text-sm text-on-surface-variant italic bg-slate-50 p-3 rounded-lg"><?= nl2br(e($pago['observaciones'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <h3 class="text-lg font-bold text-on-surface mb-6 border-b border-background pb-3">Comprobante Físico</h3>
                
                <?php 
                $archivoUrl = '/uploads/' . $pago['archivo'];
                $isPDF = (strtolower(pathinfo($pago['archivo'], PATHINFO_EXTENSION)) === 'pdf');
                ?>
                
                <?php if ($isPDF): ?>
                    <div class="flex flex-col items-center justify-center p-8 border border-dashed border-outline-variant rounded-xl bg-slate-50">
                        <span class="material-symbols-outlined text-6xl text-red-500 mb-4">picture_as_pdf</span>
                        <p class="text-sm font-semibold text-on-surface mb-4"><?= e($pago['archivo']) ?></p>
                        <a href="<?= $archivoUrl ?>" target="_blank" class="bg-red-50 text-red-600 hover:bg-red-100 font-bold px-6 py-3 rounded-xl border border-red-200 transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined">open_in_new</span>
                            Abrir / Descargar PDF
                        </a>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl overflow-hidden border border-outline-variant shadow-inner bg-slate-100 flex justify-center p-4">
                        <a href="<?= $archivoUrl ?>" target="_blank" title="Haz clic para ver tamaño completo">
                            <img src="<?= $archivoUrl ?>" alt="Comprobante de pago" class="max-w-full max-h-[500px] object-contain rounded-lg shadow-sm hover:opacity-90 transition-opacity">
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        
        <!-- Columna Derecha: Línea de tiempo (Auditoría) -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm sticky top-24">
                <h3 class="text-lg font-bold text-on-surface mb-6 border-b border-background pb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">history</span>
                    Historial de Auditoría
                </h3>
                
                <div class="relative pl-6 border-l-2 border-slate-200 flex flex-col gap-6 ml-2">
                    <!-- Evento Inicial: Creación -->
                    <div class="relative">
                        <div class="absolute -left-[35px] top-1 bg-yellow-500 h-4 w-4 rounded-full border-4 border-white shadow-sm ring-4 ring-yellow-500/20"></div>
                        <p class="text-sm font-bold text-on-surface">Pago Registrado (PENDIENTE)</p>
                        <p class="text-xs font-semibold text-on-surface-variant mt-0.5">Por <?= e($pago['residente_nombre']) ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d/m/Y h:i A', strtotime($pago['fecha_registro'])) ?></p>
                    </div>

                    <!-- Eventos subsecuentes -->
                    <?php if (!empty($pago['log_auditoria'])): ?>
                        <?php 
                        // El log está ordenado DESC, así que lo invertimos para mostrarlo cronológicamente (ASC)
                        $logs = array_reverse($pago['log_auditoria']);
                        foreach ($logs as $log): 
                            $dotColor = 'bg-slate-400 ring-slate-400/20';
                            if ($log['estado_nuevo'] === 'APROBADO') $dotColor = 'bg-green-500 ring-green-500/20';
                            if ($log['estado_nuevo'] === 'EN REVISIÓN') $dotColor = 'bg-blue-500 ring-blue-500/20';
                            if ($log['estado_nuevo'] === 'RECHAZADO') $dotColor = 'bg-red-500 ring-red-500/20';
                        ?>
                        <div class="relative">
                            <div class="absolute -left-[35px] top-1 <?= $dotColor ?> h-4 w-4 rounded-full border-4 border-white shadow-sm ring-4"></div>
                            <p class="text-sm font-bold text-on-surface">Cambio a <?= e($log['estado_nuevo']) ?></p>
                            <p class="text-xs font-semibold text-on-surface-variant mt-0.5">Por <?= e($log['admin_nombre']) ?></p>
                            <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d/m/Y h:i A', strtotime($log['fecha_registro'])) ?></p>
                            
                            <?php if (!empty($log['motivo'])): ?>
                                <div class="mt-2 text-xs bg-slate-50 border border-slate-200 p-3 rounded-lg text-on-surface-variant italic">
                                    "<?= e($log['motivo']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>
