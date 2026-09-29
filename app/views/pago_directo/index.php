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
        <div>
            <h2 class="text-2xl font-bold">Pago de Condominio</h2>
            <p class="text-sm opacity-90 mt-1">Reporta tu pago sin iniciar sesión</p>
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

            <!-- Card 1: Identificación de la unidad -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
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
            </div>

            <!-- Panel de deuda de la unidad -->
            <div id="panelDeuda" class="hidden bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
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

                    <button type="button" id="btnUsarMonto"
                            class="hidden mt-4 bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2.5 rounded-xl transition-all active:scale-95 items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">input</span>
                        Usar este monto
                    </button>
                </div>
            </div>

            <!-- Card 2: Datos del pago -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
                <div class="pb-4 border-b border-background mb-6">
                    <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">payments</span>
                        Datos del pago
                    </h2>
                    <p class="text-sm text-on-surface-variant mt-1">Complete los pasos para reportar su pago.</p>
                </div>

                <!-- Paso 1: Subir comprobante -->
                <div class="bg-slate-50/70 border border-outline-variant rounded-2xl p-5 md:p-6 flex flex-col gap-4">
                    <div>
                        <h4 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[20px]">document_scanner</span>
                            Paso 1: Subir Comprobante <span class="text-red-500">*</span>
                        </h4>
                        <p class="text-xs text-slate-500 mt-0.5">Cargue la imagen o el PDF de su comprobante de pago.</p>
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
                            <p class="text-xs text-slate-500 mt-1">Soporta JPG, PNG y PDF (Máx. 5MB)</p>
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
                </div>

                <!-- Paso 2: Datos del pago -->
                <div class="flex items-center justify-between pb-2 border-b border-background mt-6 mb-4">
                    <h4 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-primary text-[20px]">edit_document</span>
                        Paso 2: Datos del Pago (Verifique o complete)
                    </h4>
                    <span class="text-xs text-slate-400 font-medium">* Campos obligatorios</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Monto -->
                    <div class="flex flex-col gap-2">
                        <label for="monto" class="text-sm font-semibold text-on-surface-variant">Monto (Bs.) <span class="text-red-500">*</span></label>
                        <input type="number" id="monto" name="monto" step="0.01" min="0.01" max="999999.99" required placeholder="0.00"
                               value="<?= e($old['monto'] ?? '') ?>"
                               class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all font-bold text-lg">
                    </div>

                    <!-- Fecha de pago -->
                    <div class="flex flex-col gap-2">
                        <label for="fecha_pago" class="text-sm font-semibold text-on-surface-variant">Fecha de realización <span class="text-red-500">*</span></label>
                        <input type="date" id="fecha_pago" name="fecha_pago" required
                               value="<?= e($old['fecha_pago'] ?? date('Y-m-d')) ?>"
                               class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all font-medium">
                    </div>

                    <!-- Método de pago -->
                    <div class="flex flex-col gap-2">
                        <label for="metodo_pago" class="text-sm font-semibold text-on-surface-variant">Método de pago <span class="text-red-500">*</span></label>
                        <select id="metodo_pago" name="metodo_pago" required
                                class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium">
                            <option value="">Seleccione un método...</option>
                            <option value="transferencia" <?= (($old['metodo_pago'] ?? '') === 'transferencia') ? 'selected' : '' ?>>Transferencia Bancaria</option>
                            <option value="pago_movil" <?= (($old['metodo_pago'] ?? '') === 'pago_movil') ? 'selected' : '' ?>>Pago Móvil</option>
                        </select>
                    </div>

                    <!-- Referencia -->
                    <div class="flex flex-col gap-2">
                        <label for="referencia" class="text-sm font-semibold text-on-surface-variant">Número de referencia <span class="text-red-500">*</span></label>
                        <input type="text" id="referencia" name="referencia" required maxlength="100" placeholder="Ej. 12345678"
                               value="<?= e($old['referencia'] ?? '') ?>"
                               class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all font-mono font-medium">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <!-- Banco emisor -->
                    <div class="flex flex-col gap-2">
                        <label for="banco_pagador" class="text-sm font-semibold text-on-surface-variant">Banco emisor / pagador</label>
                        <select id="banco_pagador" name="banco_pagador"
                                class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium">
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
                        <div id="contenedor_banco_otro" class="hidden">
                            <input type="text" id="banco_pagador_otro" name="banco_pagador_otro" maxlength="100" placeholder="Especifique el nombre del banco emisor..."
                                   value="<?= e($old['banco_pagador_otro'] ?? '') ?>"
                                   class="w-full px-4 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-sm font-medium">
                        </div>
                    </div>

                    <!-- Cuenta bancaria receptora -->
                    <div class="flex flex-col gap-2">
                        <label for="cuenta_bancaria_id" class="text-sm font-semibold text-on-surface-variant">Cuenta bancaria destino <span class="text-red-500">*</span></label>
                        <select id="cuenta_bancaria_id" name="cuenta_bancaria_id" required
                                class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium">
                            <option value="">-- Seleccione la cuenta autorizada receptora --</option>
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
                                        <span id="infoNumero" class="font-mono font-bold text-slate-800 text-xs truncate block select-all"></span>
                                    </div>
                                    <button type="button" data-copiar="infoNumero" class="shrink-0 bg-blue-50 hover:bg-blue-100 text-primary font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar número de cuenta">
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Copiar
                                    </button>
                                </div>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Titular autorizado</span>
                                        <span id="infoTitular" class="font-bold text-slate-800 text-xs truncate block select-all"></span>
                                    </div>
                                </div>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">RIF / Identificación</span>
                                        <span id="infoDoc" class="font-mono font-bold text-slate-800 text-xs truncate block select-all"></span>
                                    </div>
                                    <button type="button" data-copiar="infoDoc" class="shrink-0 bg-blue-50 hover:bg-blue-100 text-primary font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar RIF">
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Copiar
                                    </button>
                                </div>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Teléfono Pago Móvil</span>
                                        <span id="infoTelefono" class="font-mono font-bold text-emerald-700 text-xs truncate block select-all"></span>
                                    </div>
                                    <button type="button" data-copiar="infoTelefono" class="shrink-0 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar teléfono">
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Copiar
                                    </button>
                                </div>
                            </div>
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
                <p class="mt-5 text-xs text-on-surface-variant bg-slate-50 border border-slate-200 rounded-2xl p-3.5 leading-relaxed">
                    <strong>Nota:</strong> Su pago quedará asociado a la unidad seleccionada y será verificado por la administración.
                </p>

                <!-- Botones de envío -->
                <div class="flex flex-wrap gap-4 pt-4 border-t border-background">
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
    var btnUsarMonto = document.getElementById('btnUsarMonto');
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
        btnUsarMonto.classList.toggle('hidden', total <= 0);

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

    function toggleBancoOtro() {
        var contenedor = document.getElementById('contenedor_banco_otro');
        contenedor.classList.toggle('hidden', bancoSelect.value !== 'OTRO');
    }

    function actualizarInfoCuenta() {
        var opt = cuentaSelect.options[cuentaSelect.selectedIndex];
        var card = document.getElementById('cardInfoCuenta');
        if (!cuentaSelect.value || !opt) {
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

    btnUsarMonto.addEventListener('click', function () {
        if (ultimoTotal > 0) {
            montoInput.value = ultimoTotal.toFixed(2);
        }
    });

    edificioSelect.addEventListener('change', function () {
        filtrarUnidades();
        consultarDeuda(unidadSelect.value);
    });

    unidadSelect.addEventListener('change', function () {
        consultarDeuda(unidadSelect.value);
    });

    bancoSelect.addEventListener('change', toggleBancoOtro);
    cuentaSelect.addEventListener('change', actualizarInfoCuenta);

    Array.prototype.forEach.call(document.querySelectorAll('[data-copiar]'), function (btn) {
        btn.addEventListener('click', function () {
            copiarDato(btn.getAttribute('data-copiar'));
        });
    });

    // Estado inicial: respeta los valores repoblados tras un error de validación
    if (edificioSelect.value) {
        filtrarUnidades();
    }
    toggleBancoOtro();
    actualizarInfoCuenta();
    if (unidadSelect.value) {
        consultarDeuda(unidadSelect.value);
    }

    // Previsualización del comprobante (solo presentación)
    var comprobanteInput = document.getElementById('comprobante');
    var dropzone = document.getElementById('dropzone');
    var dropzoneInitial = document.getElementById('dropzoneInitial');
    var dropzonePreview = document.getElementById('dropzonePreview');
    var imagePreview = document.getElementById('imagePreview');
    var pdfPreview = document.getElementById('pdfPreview');
    var pdfName = document.getElementById('pdfName');
    var comprobanteObjectURL = null;

    function mostrarPrevisualizacionComprobante() {
        var archivo = comprobanteInput && comprobanteInput.files ? comprobanteInput.files[0] : null;
        if (!archivo) { return; }

        if (comprobanteObjectURL) {
            URL.revokeObjectURL(comprobanteObjectURL);
            comprobanteObjectURL = null;
        }

        dropzoneInitial.classList.add('hidden');
        dropzonePreview.classList.remove('hidden');
        dropzonePreview.classList.add('flex');

        if (archivo.type === 'application/pdf') {
            imagePreview.classList.add('hidden');
            pdfPreview.classList.remove('hidden');
            pdfPreview.classList.add('flex');
            pdfName.textContent = archivo.name;
        } else {
            pdfPreview.classList.add('hidden');
            pdfPreview.classList.remove('flex');
            imagePreview.classList.remove('hidden');
            comprobanteObjectURL = URL.createObjectURL(archivo);
            imagePreview.src = comprobanteObjectURL;
        }
    }

    if (comprobanteInput) {
        comprobanteInput.addEventListener('change', mostrarPrevisualizacionComprobante);
    }

    // Permite arrastrar y soltar el comprobante sobre el área
    if (dropzone && comprobanteInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (evento) {
            dropzone.addEventListener(evento, function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(function (evento) {
            dropzone.addEventListener(evento, function () {
                dropzone.classList.add('border-primary', 'bg-blue-50/50');
            });
        });

        ['dragleave', 'drop'].forEach(function (evento) {
            dropzone.addEventListener(evento, function () {
                dropzone.classList.remove('border-primary', 'bg-blue-50/50');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            var archivos = e.dataTransfer ? e.dataTransfer.files : null;
            if (archivos && archivos.length > 0) {
                comprobanteInput.files = archivos;
                mostrarPrevisualizacionComprobante();
            }
        });
    }
})();
</script>
