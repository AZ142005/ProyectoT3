<?php
$old = $old ?? [];
$error = $error ?? '';
$unidades = $unidades ?? [];
$edificios = $edificios ?? [];
$cuentasBancarias = $cuentasBancarias ?? [];

$oldUnidad = (string)($old['unidad_id'] ?? '');
$oldEdificio = (string)($old['edificio'] ?? '');
$oldBanco = (string)($old['banco_pagador'] ?? '');
$oldCuenta = (string)($old['cuenta_bancaria_id'] ?? '');
?>
<div class="max-w-3xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de la página -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4 border-b-4 border-institutional-brown">
        <div class="flex items-center gap-3">
            <div class="bg-white rounded-xl p-1.5 shadow-sm shrink-0">
                <img src="/img/logo_condominio.png" alt="Conjunto Residencial Las Mesetas" class="h-10 w-auto object-contain block">
            </div>
            <div>
                <h2 class="text-2xl font-bold">Pago de Condominio</h2>
                <p class="text-sm opacity-90 mt-1">Reporta tu pago sin iniciar sesión</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="/auth/login" class="bg-white/20 hover:bg-white/30 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl border border-white/20 transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">login</span>
                Iniciar sesión
            </a>
        </div>
    </div>

    <!-- Mensajes de Alerta -->
    <?php
    // El componente flash reutiliza la variable $error: se conserva el mensaje de validación del controlador.
    $errorValidacion = $error;
    include VIEWS_PATH . '/components/flash_messages.php';
    $error = $errorValidacion;
    ?>

        <form method="POST" action="/pago-directo/reportar" enctype="multipart/form-data" class="flex flex-col gap-6" id="formPagoDirecto">
            <?= csrf_field() ?>

            <!-- Alerta de error -->
            <?php if ($error !== ''): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-2xl p-4 flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-500 shrink-0 mt-0.5">error</span>
                    <span class="leading-relaxed"><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Card unificada de datos del pago (misma estructura que la vista de residentes) -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="pb-4 border-b border-background mb-6">
                    <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">payments</span>
                        Datos del pago
                    </h2>
                    <p class="text-sm text-on-surface-variant mt-1">Complete los pasos para reportar su pago.</p>
                </div>

                <!-- Identificación de la unidad -->
                <div class="pb-4 border-b border-background mb-6">
                    <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">location_on</span>
                        Identifica tu unidad
                    </h2>
                    <p class="text-sm text-on-surface-variant mt-1">Selecciona el edificio y el apartamento para consultar su deuda.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="flex flex-col gap-2">
                        <label for="edificio" class="text-sm font-semibold text-on-surface-variant">Edificio</label>
                        <select id="edificio" name="edificio"
                                class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium">
                            <option value="">Todos los edificios</option>
                            <?php foreach ($edificios as $edif): ?>
                                <option value="<?= e($edif['id']) ?>" <?= ($oldEdificio === (string)$edif['id']) ? 'selected' : '' ?>>
                                    <?= e($edif['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="unidad_select" class="text-sm font-semibold text-on-surface-variant">Unidad / Apartamento <span class="text-red-500">*</span></label>
                        <select id="unidad_select" name="unidad_id" required
                                class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium">
                            <option value="">Seleccione su unidad...</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= e($u['id']) ?>"
                                        data-edificio="<?= e($u['edificio_id']) ?>"
                                        <?= ($oldUnidad === (string)$u['id']) ? 'selected' : '' ?>>
                                    <?= e($u['numero']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Estado de cuenta de la unidad -->
                <div id="panelDeuda" class="hidden mt-6 pt-6 border-t border-background">
                <div id="deudaLoading" class="flex items-center gap-3 text-on-surface-variant">
                    <div class="animate-spin rounded-full h-5 w-5 border-2 border-primary border-t-transparent"></div>
                    Consultando deuda...
                </div>

                <div id="deudaError" class="hidden bg-amber-50 text-amber-800 border border-amber-300 rounded-2xl p-4 items-start gap-3">
                    <span class="material-symbols-outlined text-amber-600 shrink-0 mt-0.5">warning</span>
                    <span>No se pudo consultar la deuda de la unidad. Intente de nuevo.</span>
                </div>

                <div id="deudaContenido" class="hidden">
                    <div class="flex items-center justify-between flex-wrap gap-3 border-b border-background pb-4 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">receipt_long</span>
                                Estado de cuenta de la unidad
                            </h2>
                            <p id="deudaUnidad" class="text-sm text-on-surface-variant mt-1"></p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs uppercase font-bold text-on-surface-variant block">Total pendiente</span>
                            <span id="deudaTotal" class="text-2xl font-black text-rose-600 font-mono">Bs. 0,00</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase text-on-surface-variant border-b border-outline-variant">
                                    <th class="py-2 pr-3 font-bold">Periodo</th>
                                    <th class="py-2 pr-3 font-bold">Vencimiento</th>
                                    <th class="py-2 text-right font-bold">Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="deudaFacturas"></tbody>
                        </table>
                    </div>

                    <p id="deudaSinFacturas" class="hidden mt-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-2xl p-3">
                        Esta unidad no tiene facturas pendientes. Puedes reportar un abono anticipado.
                    </p>
                    <p id="deudaSaldoFavor" class="hidden mt-3 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-2xl p-3"></p>

                    <button type="button" id="btnCopiarMonto"
                            class="hidden mt-4 bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl transition-all active:scale-95 items-center gap-1.5" title="Copiar el monto pendiente al portapapeles">
                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                        Copiar monto
                    </button>
                </div>
            </div>

                <!-- Paso 1: Subida de Comprobante con Extracción Automática -->
                <div class="mt-6 bg-slate-50/70 border border-outline-variant rounded-2xl p-5 md:p-6 flex flex-col gap-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <h4 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-primary text-[20px]">document_scanner</span>
                                Paso 1: Subir Comprobante (Extracción Inteligente)
                            </h4>
                            <p class="text-xs text-slate-500 mt-0.5">Al cargar el comprobante se autocompletarán los campos vacíos del pago.</p>
                        </div>
                        <div id="ocrStatusBadge" class="hidden text-xs font-semibold px-2.5 py-1 rounded-full items-center gap-1.5 bg-blue-100 text-blue-800 border border-blue-200">
                            <div id="ocrSpinner" class="animate-spin rounded-full h-3 w-3 border-2 border-primary border-t-transparent"></div>
                            <span id="ocrStatusText">Analizando...</span>
                        </div>
                    </div>

                    <!-- Cuenta oficial para el pago -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label for="cuenta_bancaria_id" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Cuenta oficial para el pago <span class="text-red-500">*</span></label>
                            <span id="badge-cuenta_bancaria_id" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                            </span>
                        </div>
                        <select id="cuenta_bancaria_id" name="cuenta_bancaria_id" required
                                class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer font-semibold text-sm">
                            <option value="">-- Seleccione la cuenta oficial del condominio --</option>
                            <?php foreach ($cuentasBancarias as $cb): ?>
                                <option value="<?= e($cb['id']) ?>"
                                        data-banco="<?= e($cb['banco']) ?>"
                                        data-cuenta="<?= e($cb['numero_cuenta']) ?>"
                                        data-titular="<?= e($cb['titular']) ?>"
                                        data-doc="<?= e($cb['tipo_identificacion'] . '-' . $cb['identificacion']) ?>"
                                        data-telefono="<?= e($cb['telefono_pago_movil'] ?? '') ?>"
                                        <?= ($oldCuenta === (string)$cb['id']) ? 'selected' : '' ?>>
                                    <?= e($cb['banco']) ?> - <?= e(chunk_split($cb['numero_cuenta'], 4, ' ')) ?> (<?= e($cb['titular']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="inconsistencia-cuenta_bancaria_id"></div>

                        <!-- Datos oficiales de la cuenta seleccionada -->
                        <div id="cardInfoCuenta" class="hidden mt-1 p-4 bg-gradient-to-br from-blue-50/95 to-slate-100/80 border border-blue-200 rounded-2xl text-xs text-blue-950">
                            <div class="flex items-center justify-between border-b border-blue-200/70 pb-2 mb-3">
                                <div class="font-bold text-sm text-primary flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[19px]">verified</span>
                                    <span id="infoBanco"></span>
                                </div>
                                <span class="text-[10px] font-bold bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full uppercase">Cuenta oficial</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Número de cuenta</span>
                                        <span id="infoNumero" class="font-mono font-bold text-slate-800 text-base truncate block select-all"></span>
                                    </div>
                                    <button type="button" data-copiar="infoNumero" class="shrink-0 bg-blue-50 hover:bg-blue-100 text-primary font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar número de cuenta">
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Copiar
                                    </button>
                                </div>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Titular autorizado</span>
                                        <span id="infoTitular" class="font-bold text-slate-800 text-base truncate block select-all"></span>
                                    </div>
                                </div>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">RIF / Identificación</span>
                                        <span id="infoDoc" class="font-mono font-bold text-slate-800 text-base truncate block select-all"></span>
                                    </div>
                                    <button type="button" data-copiar="infoDoc" class="shrink-0 bg-blue-50 hover:bg-blue-100 text-primary font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar RIF">
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Copiar
                                    </button>
                                </div>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Teléfono Pago Móvil</span>
                                        <span id="infoTelefono" class="font-mono font-bold text-emerald-700 text-base truncate block select-all"></span>
                                    </div>
                                    <button type="button" data-copiar="infoTelefono" class="shrink-0 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar teléfono">
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Copiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="dropzone" class="border-2 border-dashed border-outline-variant hover:border-primary bg-white rounded-2xl p-6 transition-all text-center cursor-pointer relative group flex flex-col items-center justify-center min-h-[170px]">
                        <input type="file" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required
                               class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10">

                        <!-- Estado inicial -->
                        <div id="dropzoneInitial" class="flex flex-col items-center pointer-events-none">
                            <div class="w-14 h-14 bg-background rounded-full border border-outline-variant flex items-center justify-center mb-3 group-hover:scale-105 transition-transform text-primary">
                                <span class="material-symbols-outlined text-3xl">upload_file</span>
                            </div>
                            <p class="font-bold text-on-surface text-base">Haz clic o arrastra tu comprobante aquí</p>
                            <p class="text-xs text-slate-500 mt-1">Soporta JPG, PNG y PDF (Máx. 5MB) • Extracción automática</p>
                        </div>

                        <!-- Estado con archivo (previsualización) -->
                        <div id="dropzonePreview" class="hidden flex-col items-center pointer-events-none w-full">
                            <img id="imagePreview" src="" alt="Vista previa" class="hidden max-h-44 max-w-full rounded-lg object-contain shadow-sm border border-outline-variant bg-white">

                            <div id="pdfPreview" class="hidden flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-rose-500 mb-1">picture_as_pdf</span>
                                <span id="pdfName" class="text-xs font-bold text-on-surface text-center break-all max-w-xs"></span>
                            </div>

                            <p class="text-[11px] text-primary font-bold mt-3 bg-primary/10 px-3 py-1 rounded-md">Haz clic para cambiar el archivo</p>
                        </div>
                    </div>

                    <!-- Resumen del resultado de extracción -->
                    <div id="resumenExtraccion" class="hidden p-3 rounded-xl text-xs flex items-center justify-between gap-3 transition-all">
                        <div class="flex items-center gap-2">
                            <span id="resumenIcono" class="material-symbols-outlined text-[18px]"></span>
                            <span id="resumenMensaje" class="font-medium"></span>
                        </div>
                        <button type="button" id="btnReanalizar" class="hidden text-xs font-bold px-3 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition-all active:scale-95 shrink-0 items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-[14px]">refresh</span>
                            Reanalizar
                        </button>
                    </div>
                </div>

                <!-- Paso 2: Datos del Pago (Estructura del flujo residente) -->
                <div class="flex flex-col gap-6 mt-6">
                    <div class="pb-2 border-b border-background flex items-center justify-between">
                        <h4 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[20px]">edit_document</span>
                            Paso 2: Datos del Pago (Verifique o complete)
                        </h4>
                        <span class="text-xs text-slate-400 font-medium">* Campos obligatorios</span>
                    </div>

                    <!-- Tarjeta A: Detalles de la Transacción -->
                    <div class="bg-slate-50/70 border border-outline-variant rounded-2xl p-5 flex flex-col gap-5">
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200/80 pb-2">
                            <span class="material-symbols-outlined text-[17px] text-primary">payments</span>
                            Detalles de la Transacción
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Monto Pagado -->
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="monto" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Monto Pagado (Bs.) <span class="text-red-500">*</span></label>
                                    <span id="badge-monto" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                                    </span>
                                </div>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-black text-base select-none">Bs.</span>
                                    <input type="number" id="monto" name="monto" step="0.01" min="0.01" max="999999.99" required placeholder="0.00"
                                           value="<?= e($old['monto'] ?? '') ?>"
                                           class="w-full pl-12 pr-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-900 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-black text-lg">
                                </div>
                                <div id="inconsistencia-monto"></div>
                                <span class="text-[11px] text-slate-400">Monto exacto registrado en la operación.</span>
                            </div>

                            <!-- Fecha de Pago -->
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="fecha_pago" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Fecha de Realización <span class="text-red-500">*</span></label>
                                    <span id="badge-fecha_pago" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                                    </span>
                                </div>
                                <input type="date" id="fecha_pago" name="fecha_pago" value="<?= e($old['fecha_pago'] ?? '') ?>" required
                                       class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-medium text-sm">
                                <div id="inconsistencia-fecha_pago"></div>
                                <span class="text-[11px] text-slate-400">Fecha en que se ejecutó la transferencia o pago.</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Método de Pago -->
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="metodo_pago" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Método de Pago <span class="text-red-500">*</span></label>
                                    <span id="badge-metodo_pago" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                                    </span>
                                </div>
                                <select id="metodo_pago" name="metodo_pago" required
                                        class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer font-medium text-sm">
                                    <option value="">Seleccione un método...</option>
                                    <option value="transferencia" <?= (($old['metodo_pago'] ?? '') === 'transferencia') ? 'selected' : '' ?>>Transferencia Bancaria</option>
                                    <option value="pago_movil" <?= (($old['metodo_pago'] ?? '') === 'pago_movil') ? 'selected' : '' ?>>Pago Móvil</option>
                                </select>
                                <div id="inconsistencia-metodo_pago"></div>
                            </div>

                            <!-- Número de Referencia -->
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="referencia" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Número de Referencia <span class="text-red-500">*</span></label>
                                    <span id="badge-referencia" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                                    </span>
                                </div>
                                <input type="text" id="referencia" name="referencia" required maxlength="100" placeholder="Ej. 12345678"
                                       value="<?= e($old['referencia'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-900 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-mono font-medium text-sm">
                                <div id="inconsistencia-referencia"></div>
                                <span class="text-[11px] text-slate-400">Número de operación bancaria o confirmación.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta B: Entidades Bancarias y Cuentas -->
                    <div class="bg-slate-50/70 border border-outline-variant rounded-2xl p-5 flex flex-col gap-5">
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200/80 pb-2">
                            <span class="material-symbols-outlined text-[17px] text-primary">account_balance</span>
                            Entidades Bancarias y Cuentas
                        </div>

                        <!-- Banco Emisor / Pagador (Origen) -->
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <label for="banco_pagador" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Banco Emisor / Pagador (Origen)</label>
                                <span id="badge-banco_pagador" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                                </span>
                            </div>
                            <div class="relative">
                                <select id="banco_pagador" name="banco_pagador"
                                        class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer font-semibold text-sm">
                                    <option value="">-- Seleccione el banco desde el cual realizó el pago --</option>
                                    <option value="Banco de Venezuela" <?= ($oldBanco === 'Banco de Venezuela') ? 'selected' : '' ?>>Banco de Venezuela (BDV)</option>
                                    <option value="Banesco" <?= ($oldBanco === 'Banesco') ? 'selected' : '' ?>>Banesco</option>
                                    <option value="Banco Mercantil" <?= ($oldBanco === 'Banco Mercantil') ? 'selected' : '' ?>>Banco Mercantil</option>
                                    <option value="BBVA Provincial" <?= ($oldBanco === 'BBVA Provincial') ? 'selected' : '' ?>>BBVA Provincial</option>
                                    <option value="Bancamiga" <?= ($oldBanco === 'Bancamiga') ? 'selected' : '' ?>>Bancamiga</option>
                                    <option value="Banco Nacional de Crédito" <?= ($oldBanco === 'Banco Nacional de Crédito') ? 'selected' : '' ?>>Banco Nacional de Crédito (BNC)</option>
                                    <option value="Bancaribe" <?= ($oldBanco === 'Bancaribe') ? 'selected' : '' ?>>Bancaribe</option>
                                    <option value="Banco del Tesoro" <?= ($oldBanco === 'Banco del Tesoro') ? 'selected' : '' ?>>Banco del Tesoro</option>
                                    <option value="Banco Exterior" <?= ($oldBanco === 'Banco Exterior') ? 'selected' : '' ?>>Banco Exterior</option>
                                    <option value="Banco Plaza" <?= ($oldBanco === 'Banco Plaza') ? 'selected' : '' ?>>Banco Plaza</option>
                                    <option value="Banco Activo" <?= ($oldBanco === 'Banco Activo') ? 'selected' : '' ?>>Banco Activo</option>
                                    <option value="Banco Fondo Común" <?= ($oldBanco === 'Banco Fondo Común') ? 'selected' : '' ?>>Banco Fondo Común (BFC)</option>
                                    <option value="100% Banco" <?= ($oldBanco === '100% Banco') ? 'selected' : '' ?>>100% Banco</option>
                                    <option value="Banco Sofitasa" <?= ($oldBanco === 'Banco Sofitasa') ? 'selected' : '' ?>>Banco Sofitasa</option>
                                    <option value="Banplus" <?= ($oldBanco === 'Banplus') ? 'selected' : '' ?>>Banplus</option>
                                    <option value="Banco Caroní" <?= ($oldBanco === 'Banco Caroní') ? 'selected' : '' ?>>Banco Caroní</option>
                                    <option value="Bancrecer" <?= ($oldBanco === 'Bancrecer') ? 'selected' : '' ?>>Bancrecer</option>
                                    <option value="Mi Banco" <?= ($oldBanco === 'Mi Banco') ? 'selected' : '' ?>>Mi Banco</option>
                                    <option value="Banco Digital de los Trabajadores" <?= ($oldBanco === 'Banco Digital de los Trabajadores') ? 'selected' : '' ?>>Banco Digital de los Trabajadores (Bicentenario)</option>
                                    <option value="Banco Agrícola de Venezuela" <?= ($oldBanco === 'Banco Agrícola de Venezuela') ? 'selected' : '' ?>>Banco Agrícola de Venezuela</option>
                                    <option value="BANFANB" <?= ($oldBanco === 'BANFANB') ? 'selected' : '' ?>>BANFANB</option>
                                    <option value="OTRO" <?= ($oldBanco === 'OTRO') ? 'selected' : '' ?>>Otro banco...</option>
                                </select>
                            </div>
                            <div id="contenedor_banco_otro" class="hidden mt-2">
                                <input type="text" id="banco_pagador_otro" name="banco_pagador_otro" maxlength="100" placeholder="Especifique el nombre del banco emisor..."
                                       value="<?= e($old['banco_pagador_otro'] ?? '') ?>"
                                       class="w-full px-4 py-2.5 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium">
                            </div>
                            <div id="inconsistencia-banco_pagador"></div>
                            <span class="text-[11px] text-slate-400">Banco del cual salieron los fondos de su cuenta.</span>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mt-5 flex flex-col gap-2">
                    <label for="observaciones" class="text-sm font-semibold text-on-surface-variant">Observaciones (opcional)</label>
                    <textarea id="observaciones" name="observaciones" rows="2" placeholder="Información adicional sobre el pago..."
                              class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none text-sm"><?= e($old['observaciones'] ?? '') ?></textarea>
                </div>

                <!-- Nota informativa -->
                <p class="mt-5 text-xs text-on-surface-variant/80 leading-relaxed">
                    <strong>Nota:</strong> Su pago quedará asociado a la unidad seleccionada y será verificado por la administración.
                </p>

                <!-- Botones de envío -->
                <div class="flex flex-wrap justify-center items-center gap-4 pt-4 border-t border-background">
                    <button type="submit"
                            class="bg-primary hover:bg-primary-hover text-white font-bold px-8 py-3 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1">
                        <span class="material-symbols-outlined">send</span>
                        Enviar Comprobante
                    </button>
                    <a href="/" class="bg-[#95a5a6] hover:bg-[#7f8c8d] text-white font-semibold px-6 py-3 rounded-xl text-center transition-all duration-200 active:scale-95">
                        Cancelar
                    </a>
                </div>
            </div>
        </form>

        <!-- Enlaces de navegación -->
        <div class="flex items-center justify-center gap-4 mt-8 text-sm flex-wrap">
            <a href="/auth/login" class="text-primary hover:underline font-semibold">¿Tienes cuenta? Inicia sesión</a>
            <span class="text-on-surface-variant/40">|</span>
            <a href="/" class="text-on-surface-variant hover:text-primary font-semibold">Volver al inicio</a>
        </div>
</div>

<!-- Tesseract.js v5 CDN para OCR en navegador -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<!-- Módulo compartido de extracción de comprobantes (OCR en dos pasadas) -->
<script src="/js/comprobante-ocr.js"></script>

<script>
(function () {
    'use strict';

    var MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    var edificioSelect = document.getElementById('edificio');
    var unidadSelect = document.getElementById('unidad_select');
    var panelDeuda = document.getElementById('panelDeuda');
    var deudaLoading = document.getElementById('deudaLoading');
    var deudaError = document.getElementById('deudaError');
    var deudaContenido = document.getElementById('deudaContenido');
    var deudaUnidad = document.getElementById('deudaUnidad');
    var deudaTotal = document.getElementById('deudaTotal');
    var deudaFacturas = document.getElementById('deudaFacturas');
    var deudaSinFacturas = document.getElementById('deudaSinFacturas');
    var deudaSaldoFavor = document.getElementById('deudaSaldoFavor');
    var btnCopiarMonto = document.getElementById('btnCopiarMonto');
    var montoInput = document.getElementById('monto');
    var bancoSelect = document.getElementById('banco_pagador');
    var cuentaSelect = document.getElementById('cuenta_bancaria_id');

    var ultimoTotal = 0;

    function formatearBs(valor) {
        var numero = Number(valor) || 0;
        return 'Bs. ' + numero.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ocultarDeuda() {
        panelDeuda.classList.add('hidden');
        deudaContenido.classList.add('hidden');
        deudaError.classList.add('hidden');
        deudaLoading.classList.remove('hidden');
    }

    function filtrarUnidades() {
        var edificio = edificioSelect.value;
        var seleccionVisible = false;

        Array.prototype.forEach.call(unidadSelect.options, function (opt) {
            if (!opt.value) { return; }
            var coincide = !edificio || opt.getAttribute('data-edificio') === edificio;
            opt.hidden = !coincide;
            opt.disabled = !coincide;
            opt.style.display = coincide ? '' : 'none';
            if (coincide && opt.value === unidadSelect.value) { seleccionVisible = true; }
        });

        if (!seleccionVisible && unidadSelect.value) {
            unidadSelect.value = '';
            ocultarDeuda();
        }
    }

    function consultarDeuda(unidadId) {
        if (!unidadId) {
            ocultarDeuda();
            return;
        }

        panelDeuda.classList.remove('hidden');
        deudaLoading.classList.remove('hidden');
        deudaError.classList.add('hidden');
        deudaContenido.classList.add('hidden');

        fetch('/pago-directo/deuda?unidad_id=' + encodeURIComponent(unidadId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (resp) {
                return resp.json().then(function (data) { return { ok: resp.ok, data: data }; });
            })
            .then(function (resultado) {
                if (!resultado.ok || !resultado.data.success) {
                    throw new Error('Consulta fallida');
                }
                mostrarDeuda(resultado.data);
            })
            .catch(function () {
                deudaLoading.classList.add('hidden');
                deudaContenido.classList.add('hidden');
                deudaError.classList.remove('hidden');
            });
    }

    function mostrarDeuda(data) {
        var total = Number(data.total_deuda) || 0;
        var saldoFavor = Number(data.saldo_favor) || 0;
        var facturas = data.facturas || [];

        deudaUnidad.textContent = (data.edificio ? data.edificio + ' - ' : '') + 'Unidad ' + (data.unidad ? data.unidad.numero : '');
        deudaTotal.textContent = formatearBs(total);

        deudaFacturas.innerHTML = '';
        facturas.forEach(function (f) {
            var tr = document.createElement('tr');
            tr.className = 'border-b border-slate-100 last:border-0';

            var tdPeriodo = document.createElement('td');
            tdPeriodo.className = 'py-2 pr-3 font-medium text-on-surface';
            tdPeriodo.textContent = (MESES[f.mes] || '') + ' ' + f.anio;

            var tdVencimiento = document.createElement('td');
            tdVencimiento.className = 'py-2 pr-3 text-on-surface-variant';
            tdVencimiento.textContent = f.fecha_vencimiento || '';

            var tdSaldo = document.createElement('td');
            tdSaldo.className = 'py-2 text-right font-mono font-bold text-rose-600';
            tdSaldo.textContent = formatearBs(f.saldo);

            tr.appendChild(tdPeriodo);
            tr.appendChild(tdVencimiento);
            tr.appendChild(tdSaldo);
            deudaFacturas.appendChild(tr);
        });

        ultimoTotal = total;

        deudaSinFacturas.classList.toggle('hidden', facturas.length > 0);
        btnCopiarMonto.classList.toggle('hidden', total <= 0);

        if (saldoFavor > 0) {
            deudaSaldoFavor.textContent = 'Esta unidad tiene un saldo a favor de ' + formatearBs(saldoFavor) + '.';
            deudaSaldoFavor.classList.remove('hidden');
        } else {
            deudaSaldoFavor.classList.add('hidden');
        }

        deudaLoading.classList.add('hidden');
        deudaError.classList.add('hidden');
        deudaContenido.classList.remove('hidden');
    }

    function toggleBancoOtro(selectEl) {
        var contenedor = document.getElementById('contenedor_banco_otro');
        var inputOtro = document.getElementById('banco_pagador_otro');
        if (!contenedor || !selectEl) { return; }
        if (selectEl.value === 'OTRO') {
            contenedor.classList.remove('hidden');
            if (inputOtro) {
                inputOtro.setAttribute('required', 'required');
                inputOtro.focus();
            }
        } else {
            contenedor.classList.add('hidden');
            if (inputOtro) {
                inputOtro.removeAttribute('required');
                inputOtro.value = '';
            }
        }
    }

    function actualizarInfoCuenta(selectEl) {
        var opt = selectEl.options[selectEl.selectedIndex];
        var card = document.getElementById('cardInfoCuenta');
        if (!selectEl.value || !opt) {
            card.classList.add('hidden');
            return;
        }
        document.getElementById('infoBanco').textContent = opt.getAttribute('data-banco') || '';
        document.getElementById('infoNumero').textContent = opt.getAttribute('data-cuenta') || '';
        document.getElementById('infoTitular').textContent = opt.getAttribute('data-titular') || '';
        document.getElementById('infoDoc').textContent = opt.getAttribute('data-doc') || '';
        document.getElementById('infoTelefono').textContent = opt.getAttribute('data-telefono') || '';
        card.classList.remove('hidden');
    }

    function copiarDato(id) {
        var elemento = document.getElementById(id);
        if (!elemento || !elemento.textContent) { return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(elemento.textContent).catch(function () {});
        }
    }

    btnCopiarMonto.addEventListener('click', function () {
        if (ultimoTotal <= 0 || !navigator.clipboard || !navigator.clipboard.writeText) { return; }
        const textoMonto = ultimoTotal.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const btn = btnCopiarMonto;
        navigator.clipboard.writeText(textoMonto).then(function () {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span> ¡Copiado!';
            setTimeout(function () {
                btn.innerHTML = originalHTML;
            }, 1800);
        }).catch(function () {});
    });

    edificioSelect.addEventListener('change', function () {
        filtrarUnidades();
        consultarDeuda(unidadSelect.value);
    });

    unidadSelect.addEventListener('change', function () {
        consultarDeuda(unidadSelect.value);
    });

    bancoSelect.addEventListener('change', function () {
        toggleBancoOtro(bancoSelect);
    });
    cuentaSelect.addEventListener('change', function () {
        actualizarInfoCuenta(cuentaSelect);
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-copiar]'), function (btn) {
        btn.addEventListener('click', function () {
            copiarDato(btn.getAttribute('data-copiar'));
        });
    });

    // Estado inicial: respeta los valores repoblados tras un error de validación
    if (edificioSelect.value) {
        filtrarUnidades();
    }
    toggleBancoOtro(bancoSelect);
    actualizarInfoCuenta(cuentaSelect);
    if (unidadSelect.value) {
        consultarDeuda(unidadSelect.value);
    }

    ['monto', 'fecha_pago', 'metodo_pago', 'banco_pagador', 'banco_pagador_otro', 'referencia', 'cuenta_bancaria_id'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            var target = id === 'banco_pagador_otro' ? 'banco_pagador' : id;
            el.addEventListener('input', function () { limpiarInconsistenciaCampo(target); });
            el.addEventListener('change', function () { limpiarInconsistenciaCampo(target); });
        }
    });

    // ------------------------------------------------------------------
    // Extracción inteligente del comprobante (OCR en navegador + PDF)
    // ------------------------------------------------------------------
    var comprobanteInput = document.getElementById('comprobante');
    var dropzone = document.getElementById('dropzone');
    var dropzoneInitial = document.getElementById('dropzoneInitial');
    var dropzonePreview = document.getElementById('dropzonePreview');
    var imagePreview = document.getElementById('imagePreview');
    var pdfPreview = document.getElementById('pdfPreview');
    var pdfName = document.getElementById('pdfName');
    var ocrStatusBadge = document.getElementById('ocrStatusBadge');
    var ocrStatusText = document.getElementById('ocrStatusText');
    var ocrSpinner = document.getElementById('ocrSpinner');
    var resumenExtraccion = document.getElementById('resumenExtraccion');
    var resumenIcono = document.getElementById('resumenIcono');
    var resumenMensaje = document.getElementById('resumenMensaje');
    var btnReanalizar = document.getElementById('btnReanalizar');

    var currentObjectURL = null;
    var archivoActual = null;
    var ultimosDatosExtraidos = null;

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Drag & Drop
    if (dropzone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function () {
                dropzone.classList.add('border-primary', 'bg-blue-50/50');
            }, false);
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function () {
                dropzone.classList.remove('border-primary', 'bg-blue-50/50');
            }, false);
        });
    }

    if (dropzone && comprobanteInput) {
        dropzone.addEventListener('drop', function (e) {
            var dt = e.dataTransfer;
            var files = dt ? dt.files : null;
            if (files && files.length > 0) {
                comprobanteInput.files = files;
                procesarArchivoSeleccionado(files[0]);
            }
        }, false);
    }

    if (comprobanteInput) {
        comprobanteInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                procesarArchivoSeleccionado(this.files[0]);
            }
        });
    }

    if (btnReanalizar) {
        btnReanalizar.addEventListener('click', function (e) {
            e.preventDefault();
            if (archivoActual) {
                ejecutarExtraccion(archivoActual);
            }
        });
    }

    function procesarArchivoSeleccionado(file) {
        if (file.size > 5 * 1024 * 1024) {
            alert('El archivo excede el tamaño máximo permitido de 5MB.');
            comprobanteInput.value = '';
            resetDropzone();
            return;
        }

        archivoActual = file;
        dropzoneInitial.classList.add('hidden');
        dropzonePreview.classList.remove('hidden');
        dropzonePreview.classList.add('flex');

        if (currentObjectURL) {
            URL.revokeObjectURL(currentObjectURL);
            currentObjectURL = null;
        }

        currentObjectURL = URL.createObjectURL(file);

        if (file.type === 'application/pdf') {
            imagePreview.classList.add('hidden');
            pdfPreview.classList.remove('hidden');
            pdfPreview.classList.add('flex');
            pdfName.textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
        } else if (file.type === 'image/jpeg' || file.type === 'image/png') {
            pdfPreview.classList.add('hidden');
            pdfPreview.classList.remove('flex');
            imagePreview.classList.remove('hidden');
            imagePreview.src = currentObjectURL;
        } else {
            alert('Formato no válido. Solo se permiten imágenes JPG, PNG o documentos PDF.');
            comprobanteInput.value = '';
            resetDropzone();
            return;
        }

        // Auto-disparar extracción inmediatamente
        ejecutarExtraccion(file);
    }

    function resetDropzone() {
        if (currentObjectURL) {
            URL.revokeObjectURL(currentObjectURL);
            currentObjectURL = null;
        }
        archivoActual = null;
        dropzoneInitial.classList.remove('hidden');
        dropzonePreview.classList.add('hidden');
        dropzonePreview.classList.remove('flex');
        limpiarInconsistencias();
        if (resumenExtraccion) { resumenExtraccion.classList.add('hidden'); }
    }

    function actualizarEstadoOCR(tipo, mensaje) {
        if (!ocrStatusBadge) { return; }
        ocrStatusBadge.classList.remove('hidden', 'bg-blue-100', 'text-blue-800', 'border-blue-200', 'bg-amber-100', 'text-amber-800', 'border-amber-200', 'bg-emerald-100', 'text-emerald-800', 'border-emerald-200');
        ocrStatusBadge.classList.add('flex');
        ocrSpinner.classList.remove('hidden');

        if (tipo === 'ocr') {
            ocrStatusBadge.classList.add('bg-blue-100', 'text-blue-800', 'border-blue-200');
        } else if (tipo === 'analizando') {
            ocrStatusBadge.classList.add('bg-amber-100', 'text-amber-800', 'border-amber-200');
        }
        ocrStatusText.textContent = mensaje;
    }

    function finalizarEstadoOCR() {
        if (!ocrStatusBadge) { return; }
        ocrSpinner.classList.add('hidden');
        setTimeout(function () {
            ocrStatusBadge.classList.add('hidden');
            ocrStatusBadge.classList.remove('flex');
        }, 1500);
    }

    function mostrarResumenExtraccion(tipo, mensaje) {
        if (!resumenExtraccion) { return; }
        resumenExtraccion.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-900', 'border-emerald-200', 'bg-amber-50', 'text-amber-900', 'border-amber-200', 'bg-slate-100', 'text-slate-800', 'border-slate-200', 'border');
        resumenExtraccion.classList.add('flex', 'border');

        if (tipo === 'exito') {
            resumenExtraccion.classList.add('bg-emerald-50', 'text-emerald-900', 'border-emerald-200');
            resumenIcono.textContent = 'check_circle';
            resumenIcono.className = 'material-symbols-outlined text-[18px] text-emerald-600';
        } else if (tipo === 'inconsistencia') {
            resumenExtraccion.classList.add('bg-amber-50', 'text-amber-900', 'border-amber-200');
            resumenIcono.textContent = 'warning';
            resumenIcono.className = 'material-symbols-outlined text-[18px] text-amber-600';
        } else {
            resumenExtraccion.classList.add('bg-slate-100', 'text-slate-800', 'border-slate-200');
            resumenIcono.textContent = 'info';
            resumenIcono.className = 'material-symbols-outlined text-[18px] text-slate-600';
        }

        resumenMensaje.textContent = mensaje;
        if (btnReanalizar) {
            btnReanalizar.classList.remove('hidden');
            btnReanalizar.classList.add('inline-flex');
        }
    }

    async function ejecutarExtraccion(file) {
        actualizarEstadoOCR('ocr', 'Iniciando lectura...');
        limpiarInconsistencias();

        try {
            const resultado = await ComprobanteOCR.procesar(file, {
                endpoint: '/pago-directo/extraer',
                onEstado: function (estado, valor) {
                    if (estado === 'motor') {
                        actualizarEstadoOCR('ocr', 'Cargando motor de visión...');
                    } else if (estado === 'leyendo') {
                        actualizarEstadoOCR('ocr', 'Leyendo imagen (' + valor + '%)...');
                    } else if (estado === 'refuerzo') {
                        actualizarEstadoOCR('analizando', 'Refinando lectura de la captura...');
                    } else if (estado === 'analizando') {
                        actualizarEstadoOCR('analizando', 'Estructurando datos bancarios...');
                    } else if (estado === 'pdf') {
                        actualizarEstadoOCR('analizando', 'Analizando PDF en el servidor...');
                    }
                }
            });

            const data = resultado.datos;

            if (data.success) {
                ultimosDatosExtraidos = data;
                aplicarExtraccionInteligente(data);
            } else {
                mostrarResumenExtraccion('info', data.error || 'No se pudieron extraer datos del comprobante.');
            }
        } catch (err) {
            console.error('Error en extracción:', err);
            const detalleError = (err && err.message) ? err.message : 'el motor OCR no pudo iniciarse';
            mostrarResumenExtraccion('info', 'Extracción automática no completada (' + detalleError + '). Ingrese los datos manualmente.');
        } finally {
            finalizarEstadoOCR();
        }
    }

    function aplicarExtraccionInteligente(data) {
        let camposLlenados = 0;
        let camposActualizados = 0;

        // Regla de Negocio:
        // 1. Si el campo está vacío, se completa con el valor extraído.
        // 2. Si ya tiene un valor distinto, se actualiza automáticamente con el
        //    valor del comprobante, sin botones ni intervención del usuario.

        // 1. Monto Pagado
        if (data.monto) {
            const el = document.getElementById('monto');
            if (el) {
                const valActual = el.value.trim();
                if (valActual === '') {
                    el.value = data.monto;
                    marcarCampoAutollenado('monto');
                    camposLlenados++;
                } else {
                    const numActual = parseFloat(valActual);
                    const numDetectado = parseFloat(data.monto);
                    if (Math.abs(numActual - numDetectado) > 0.005) {
                        el.value = data.monto;
                        marcarCampoAutollenado('monto');
                        camposActualizados++;
                    }
                }
            }
        }

        // 2. Fecha de Pago
        if (data.fecha_pago) {
            const el = document.getElementById('fecha_pago');
            if (el) {
                const valActual = el.value.trim();
                if (valActual === '') {
                    el.value = data.fecha_pago;
                    marcarCampoAutollenado('fecha_pago');
                    camposLlenados++;
                } else if (valActual !== data.fecha_pago) {
                    el.value = data.fecha_pago;
                    marcarCampoAutollenado('fecha_pago');
                    camposActualizados++;
                }
            }
        }

        // 3. Método de Pago
        if (data.metodo_pago) {
            const el = document.getElementById('metodo_pago');
            if (el) {
                const valActual = el.value.trim();
                if (valActual === '') {
                    el.value = data.metodo_pago;
                    marcarCampoAutollenado('metodo_pago');
                    camposLlenados++;
                } else if (valActual !== data.metodo_pago) {
                    el.value = data.metodo_pago;
                    marcarCampoAutollenado('metodo_pago');
                    camposActualizados++;
                }
            }
        }

        // 4. Banco Pagador (Origen)
        if (data.banco_pagador) {
            const el = document.getElementById('banco_pagador');
            if (el) {
                const matchedBanco = seleccionarBancoPagador(data.banco_pagador);
                const valActual = el.value.trim();
                if (valActual === '') {
                    if (matchedBanco === 'OTRO') {
                        el.value = 'OTRO';
                        toggleBancoOtro(el);
                        const inputOtro = document.getElementById('banco_pagador_otro');
                        if (inputOtro) inputOtro.value = data.banco_pagador;
                    } else if (matchedBanco) {
                        el.value = matchedBanco;
                        toggleBancoOtro(el);
                    }
                    marcarCampoAutollenado('banco_pagador');
                    camposLlenados++;
                } else {
                    const currentValNormalized = (valActual === 'OTRO')
                        ? (document.getElementById('banco_pagador_otro')?.value || 'OTRO')
                        : valActual;
                    if (matchedBanco && currentValNormalized.toLowerCase() !== data.banco_pagador.toLowerCase() && valActual !== matchedBanco) {
                        if (matchedBanco === 'OTRO') {
                            el.value = 'OTRO';
                            toggleBancoOtro(el);
                            const inputOtro = document.getElementById('banco_pagador_otro');
                            if (inputOtro) inputOtro.value = data.banco_pagador;
                        } else {
                            el.value = matchedBanco;
                            toggleBancoOtro(el);
                        }
                        marcarCampoAutollenado('banco_pagador');
                        camposActualizados++;
                    }
                }
            }
        }

        // 5. Número de Referencia
        if (data.referencia) {
            const el = document.getElementById('referencia');
            if (el) {
                const valActual = el.value.trim();
                if (valActual === '') {
                    el.value = data.referencia;
                    marcarCampoAutollenado('referencia');
                    camposLlenados++;
                } else {
                    const normActual = valActual.replace(/^0+/, '');
                    const normDetectado = String(data.referencia).replace(/^0+/, '');
                    if (normActual !== normDetectado) {
                        el.value = data.referencia;
                        marcarCampoAutollenado('referencia');
                        camposActualizados++;
                    }
                }
            }
        }

        // 6. Cuenta oficial para el pago
        const selCuenta = document.getElementById('cuenta_bancaria_id');
        if (selCuenta && (data.cuenta_bancaria_id || data.banco_receptor)) {
            let matchedIndex = -1;

            if (data.cuenta_bancaria_id) {
                for (let i = 0; i < selCuenta.options.length; i++) {
                    if (selCuenta.options[i].value === String(data.cuenta_bancaria_id)) {
                        matchedIndex = i;
                        break;
                    }
                }
            }

            if (matchedIndex === -1 && data.banco_receptor) {
                const bncLower = data.banco_receptor.toLowerCase();
                for (let i = 0; i < selCuenta.options.length; i++) {
                    const optBnc = (selCuenta.options[i].getAttribute('data-banco') || '').toLowerCase();
                    if (optBnc && (optBnc.includes(bncLower) || bncLower.includes(optBnc))) {
                        matchedIndex = i;
                        break;
                    }
                }
            }

            if (matchedIndex > 0) {
                const valActual = selCuenta.value;
                if (valActual === '' || selCuenta.selectedIndex <= 0) {
                    selCuenta.selectedIndex = matchedIndex;
                    actualizarInfoCuenta(selCuenta);
                    marcarCampoAutollenado('cuenta_bancaria_id');
                    camposLlenados++;
                } else if (selCuenta.selectedIndex !== matchedIndex) {
                    selCuenta.selectedIndex = matchedIndex;
                    actualizarInfoCuenta(selCuenta);
                    marcarCampoAutollenado('cuenta_bancaria_id');
                    camposActualizados++;
                }
            }
        }

        // Renderizar advertencias de inconsistencias en tiempo real
        let totalInconsistencias = 0;
        if (data.inconsistencias && typeof data.inconsistencias === 'object') {
            Object.keys(data.inconsistencias).forEach(function (campoId) {
                var adv = data.inconsistencias[campoId];
                if (adv) {
                    renderizarInconsistencia(campoId, adv);
                    totalInconsistencias++;
                }
            });
        }

        // Resumen en la UI
        if (totalInconsistencias > 0) {
            mostrarResumenExtraccion('inconsistencia', `Se autocompletaron datos del comprobante, pero se detectaron ${totalInconsistencias} observación(es). Revise los campos señalados antes de enviar.`);
        } else if (camposLlenados > 0 && camposActualizados > 0) {
            mostrarResumenExtraccion('exito', `Se autocompletaron ${camposLlenados} campo(s) y se actualizaron ${camposActualizados} desde el comprobante.`);
        } else if (camposActualizados > 0) {
            mostrarResumenExtraccion('exito', `Se actualizaron ${camposActualizados} campo(s) con los datos del comprobante.`);
        } else if (camposLlenados > 0) {
            mostrarResumenExtraccion('exito', `Se autocompletaron ${camposLlenados} campo(s) exitosamente desde el comprobante.`);
        } else {
            mostrarResumenExtraccion('info', 'Los datos del comprobante coinciden con los ya ingresados en el formulario.');
        }
    }

    function marcarCampoAutollenado(campoId) {
        // El resaltado verde y la etiqueta "Auto-completado" se retiraron a
        // pedido: los datos se aplican sin marcas sobre los campos y el
        // resumen del Paso 1 informa el resultado.
    }

    function renderizarInconsistencia(campoId, mensaje) {
        var contenedor = document.getElementById('inconsistencia-' + campoId);
        if (!contenedor) { return; }
        contenedor.innerHTML =
            '<div class="mt-1.5 flex items-start gap-1.5 text-xs text-amber-800 bg-amber-50 border border-amber-300 rounded-lg p-2 shadow-sm transition-all duration-200">' +
                '<span class="material-symbols-outlined text-[16px] text-amber-600 mt-0.5 shrink-0">warning</span>' +
                '<span class="leading-tight">' + escapeHtml(mensaje) + '</span>' +
            '</div>';
        var input = document.getElementById(campoId);
        if (input) {
            input.classList.add('border-amber-400', 'bg-amber-50/20');
        }
    }

    function limpiarInconsistenciaCampo(campoId) {
        var c = document.getElementById('inconsistencia-' + campoId);
        if (c) { c.innerHTML = ''; }
        var input = document.getElementById(campoId);
        if (input) {
            input.classList.remove('border-amber-400', 'bg-amber-50/20');
        }
    }

    function limpiarInconsistencias() {
        ['monto', 'fecha_pago', 'metodo_pago', 'banco_pagador', 'referencia', 'cuenta_bancaria_id'].forEach(function (id) {
            limpiarInconsistenciaCampo(id);
            var b = document.getElementById('badge-' + id);
            if (b) {
                b.classList.add('hidden');
                b.classList.remove('inline-flex');
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function seleccionarBancoPagador(bancoDetectado) {
        if (!bancoDetectado) return null;
        const sel = document.getElementById('banco_pagador');
        if (!sel) return null;

        const normalize = (s) => (s || '').toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            .replace(/banco\s+de\s+|banco\s+|bbva\s+/g, '')
            .replace(/[^a-z0-9]/g, '')
            .trim();

        const detectNorm = normalize(bancoDetectado);

        for (let i = 1; i < sel.options.length; i++) {
            const opt = sel.options[i];
            if (opt.value === 'OTRO') continue;
            const valNorm = normalize(opt.value);
            const txtNorm = normalize(opt.text);
            if (valNorm === detectNorm || txtNorm === detectNorm ||
                (detectNorm.length >= 4 && (valNorm.includes(detectNorm) || txtNorm.includes(detectNorm) || detectNorm.includes(valNorm)))) {
                return opt.value;
            }
        }
        return 'OTRO';
    }
})();
</script>
