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
<div class="min-h-screen bg-gradient-to-br from-slate-50 via-green-50/30 to-slate-100 px-4 py-10">
    <div class="w-full max-w-3xl mx-auto">
        <!-- Encabezado -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-primary-hover shadow-lg shadow-primary/20 mb-4">
                <span class="material-symbols-outlined text-white" style="font-size:32px;">apartment</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-on-surface tracking-tight">Condominio Digital</h1>
            <p class="text-on-surface-variant mt-2">Pago de condominio sin iniciar sesión</p>
        </div>

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
            <div class="bg-white rounded-3xl border border-outline-variant shadow-lg shadow-slate-200/40 p-6 md:p-8">
                <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">location_on</span>
                    Identifica tu unidad
                </h2>
                <p class="text-sm text-on-surface-variant mt-1 mb-5">Selecciona el edificio y el apartamento para consultar su deuda.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="flex flex-col gap-2">
                        <label for="edificio" class="text-sm font-bold text-on-surface">Edificio</label>
                        <select id="edificio" name="edificio"
                                class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer font-medium">
                            <option value="">Todos los edificios</option>
                            <?php foreach ($edificios as $edif): ?>
                                <option value="<?= e($edif['id']) ?>" <?= ($oldEdificio === (string)$edif['id']) ? 'selected' : '' ?>>
                                    <?= e($edif['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="unidad_select" class="text-sm font-bold text-on-surface">Unidad / Apartamento <span class="text-red-500">*</span></label>
                        <select id="unidad_select" name="unidad_id" required
                                class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer font-medium">
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
            <div id="panelDeuda" class="hidden bg-white rounded-3xl border border-outline-variant shadow-lg shadow-slate-200/40 p-6 md:p-8">
                <div id="deudaLoading" class="flex items-center gap-3 text-on-surface-variant">
                    <div class="animate-spin rounded-full h-5 w-5 border-2 border-primary border-t-transparent"></div>
                    Consultando deuda...
                </div>

                <div id="deudaError" class="hidden bg-amber-50 text-amber-800 border border-amber-300 rounded-2xl p-4 items-start gap-3">
                    <span class="material-symbols-outlined text-amber-600 shrink-0 mt-0.5">warning</span>
                    <span>No se pudo consultar la deuda de la unidad. Intente de nuevo.</span>
                </div>

                <div id="deudaContenido" class="hidden">
                    <div class="flex items-center justify-between flex-wrap gap-3 border-b border-outline-variant pb-4 mb-4">
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
            <div class="bg-white rounded-3xl border border-outline-variant shadow-lg shadow-slate-200/40 p-6 md:p-8">
                <h2 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">payments</span>
                    Datos del pago
                </h2>
                <p class="text-sm text-on-surface-variant mt-1 mb-5">Complete la información de la operación realizada.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Monto -->
                    <div class="flex flex-col gap-2">
                        <label for="monto" class="text-sm font-bold text-on-surface">Monto (Bs.) <span class="text-red-500">*</span></label>
                        <input type="number" id="monto" name="monto" step="0.01" min="0.01" max="999999.99" required placeholder="0.00"
                               value="<?= e($old['monto'] ?? '') ?>"
                               class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-bold text-lg">
                    </div>

                    <!-- Fecha de pago -->
                    <div class="flex flex-col gap-2">
                        <label for="fecha_pago" class="text-sm font-bold text-on-surface">Fecha de realización <span class="text-red-500">*</span></label>
                        <input type="date" id="fecha_pago" name="fecha_pago" required
                               value="<?= e($old['fecha_pago'] ?? date('Y-m-d')) ?>"
                               class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                    </div>

                    <!-- Método de pago -->
                    <div class="flex flex-col gap-2">
                        <label for="metodo_pago" class="text-sm font-bold text-on-surface">Método de pago <span class="text-red-500">*</span></label>
                        <select id="metodo_pago" name="metodo_pago" required
                                class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer font-medium">
                            <option value="">Seleccione un método...</option>
                            <option value="transferencia" <?= (($old['metodo_pago'] ?? '') === 'transferencia') ? 'selected' : '' ?>>Transferencia Bancaria</option>
                            <option value="pago_movil" <?= (($old['metodo_pago'] ?? '') === 'pago_movil') ? 'selected' : '' ?>>Pago Móvil</option>
                        </select>
                    </div>

                    <!-- Referencia -->
                    <div class="flex flex-col gap-2">
                        <label for="referencia" class="text-sm font-bold text-on-surface">Número de referencia <span class="text-red-500">*</span></label>
                        <input type="text" id="referencia" name="referencia" required maxlength="100" placeholder="Ej. 12345678"
                               value="<?= e($old['referencia'] ?? '') ?>"
                               class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-mono font-medium">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <!-- Banco emisor -->
                    <div class="flex flex-col gap-2">
                        <label for="banco_pagador" class="text-sm font-bold text-on-surface">Banco emisor / pagador</label>
                        <select id="banco_pagador" name="banco_pagador"
                                class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer font-medium">
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
                                   class="w-full px-4 py-2.5 bg-white border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 text-sm font-medium">
                        </div>
                    </div>

                    <!-- Cuenta bancaria receptora -->
                    <div class="flex flex-col gap-2">
                        <label for="cuenta_bancaria_id" class="text-sm font-bold text-on-surface">Cuenta bancaria destino <span class="text-red-500">*</span></label>
                        <select id="cuenta_bancaria_id" name="cuenta_bancaria_id" required
                                class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer font-medium">
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

                <!-- Comprobante -->
                <div class="mt-5 flex flex-col gap-2">
                    <label for="comprobante" class="text-sm font-bold text-on-surface">Comprobante de pago <span class="text-red-500">*</span></label>
                    <input type="file" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required
                           class="w-full px-4 py-3 bg-background border-2 border-dashed border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary transition-all file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-primary/10 file:text-primary file:font-bold file:cursor-pointer cursor-pointer">
                    <span class="text-xs text-on-surface-variant/70">Formatos permitidos: JPG, PNG y PDF (Máx. 5MB).</span>
                </div>

                <!-- Observaciones -->
                <div class="mt-5 flex flex-col gap-2">
                    <label for="observaciones" class="text-sm font-bold text-on-surface">Observaciones (opcional)</label>
                    <textarea id="observaciones" name="observaciones" rows="2" placeholder="Información adicional sobre el pago..."
                              class="w-full px-4 py-3 bg-background border-2 border-outline-variant rounded-2xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all resize-none text-sm"><?= e($old['observaciones'] ?? '') ?></textarea>
                </div>

                <!-- Nota informativa -->
                <p class="mt-5 text-xs text-on-surface-variant bg-slate-50 border border-slate-200 rounded-2xl p-3.5 leading-relaxed">
                    <strong>Nota:</strong> Su pago quedará asociado a la unidad seleccionada y será verificado por la administración.
                </p>

                <!-- Botón de envío -->
                <button type="submit"
                        class="mt-6 w-full bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white font-bold text-lg py-4 rounded-2xl shadow-lg shadow-primary/20 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">send</span>
                    Reportar pago
                </button>
            </div>
        </form>

        <!-- Enlaces de navegación -->
        <div class="flex items-center justify-center gap-4 mt-8 text-sm flex-wrap">
            <a href="/auth/login" class="text-primary hover:underline font-semibold">¿Tienes cuenta? Inicia sesión</a>
            <span class="text-on-surface-variant/40">|</span>
            <a href="/" class="text-on-surface-variant hover:text-primary font-semibold">Volver al inicio</a>
        </div>
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
})();
</script>
