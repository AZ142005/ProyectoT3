<div class="max-w-3xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de la página -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold">Enviar Comprobante</h2>
            <p class="text-sm opacity-90 mt-1"><?= e($residente['nombre'] . ' ' . $residente['apellido']) ?> - Unidad <?= e($residente['unidad_numero'] ?? 'N/A') ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/residente/dashboard" class="bg-white/15 hover:bg-white/25 border border-white/20 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition-transform active:scale-95 flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">dashboard</span>
                Volver al Panel
            </a>
            <a href="/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-rose-600/80 hover:bg-rose-600 text-white p-2.5 rounded-xl transition-all flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </div>
    </div>

    <!-- Mensajes de Alerta -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <?php if (!empty($facturas_pendientes)): ?>
        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
            <div class="pb-4 border-b border-background mb-6">
                <h3 class="text-lg font-bold text-on-surface">Datos del Pago</h3>
            </div>

            <!-- Importante: method="POST" y enctype="multipart/form-data" para subir archivos -->
            <form method="POST" action="/residente/enviar-pago" enctype="multipart/form-data" class="flex flex-col gap-5">
                <!-- CSRF Token -->
                <?= csrf_field() ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-on-surface-variant">Factura a Pagar <span class="text-red-500">*</span></label>
                    <select name="factura_id" class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer" required>
                        <option value="">Seleccione una factura...</option>
                        <?php foreach ($facturas_pendientes as $f): ?>
                            <option value="<?= e($f['id']) ?>" <?= ($factura_id == $f['id']) ? 'selected' : '' ?>>
                                Factura #<?= e($f['numero_factura']) ?> - <?= e(nombreMes($f['mes'])) ?> <?= e($f['anio']) ?> (Saldo: <?= e(formatearMoneda($f['saldo'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-xs text-on-surface-variant/70">Seleccione la factura que desea reportar.</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-on-surface-variant">Monto Pagado <span class="text-red-500">*</span></label>
                        <input type="number" name="monto" step="0.01" min="0.01" required placeholder="0.00"
                               class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                        <span class="text-xs text-on-surface-variant/70">Monto exacto de la transferencia o pago móvil.</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-on-surface-variant">Fecha de Pago <span class="text-red-500">*</span></label>
                        <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" required
                               class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-on-surface-variant">Método de Pago <span class="text-red-500">*</span></label>
                    <select name="metodo_pago" class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer" required>
                        <option value="">Seleccione un método...</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                        <option value="pago_movil">Pago Móvil</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-on-surface-variant">Cuenta Bancaria Destino (Autorizada) <span class="text-red-500">*</span></label>
                    <select id="cuenta_bancaria_id" name="cuenta_bancaria_id" required onchange="actualizarInfoCuenta(this)"
                            class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-semibold">
                        <option value="">-- Seleccione la cuenta bancaria autorizada --</option>
                        <?php foreach ($cuentasBancarias as $cb): ?>
                            <option value="<?= e($cb['id']) ?>" 
                                    data-banco="<?= e($cb['banco']) ?>"
                                    data-cuenta="<?= e($cb['numero_cuenta']) ?>"
                                    data-titular="<?= e($cb['titular']) ?>"
                                    data-doc="<?= e($cb['tipo_identificacion'] . '-' . $cb['identificacion']) ?>"
                                    data-telefono="<?= e($cb['telefono_pago_movil'] ?? '') ?>">
                                <?= e($cb['banco']) ?> - <?= e(chunk_split($cb['numero_cuenta'], 4, ' ')) ?> (<?= e($cb['titular']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="cardInfoCuenta" class="hidden mt-2 p-3.5 bg-blue-50/80 border border-blue-200 rounded-xl text-xs text-blue-950 shadow-sm">
                        <div class="font-bold text-sm text-primary mb-1.5 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">verified</span>
                            <span id="infoBanco"></span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div><span class="text-slate-500 block">Número de Cuenta (20 dígitos):</span> <span id="infoNumero" class="font-mono font-bold text-dark text-xs select-all"></span></div>
                            <div><span class="text-slate-500 block">Titular:</span> <span id="infoTitular" class="font-bold text-dark"></span></div>
                            <div><span class="text-slate-500 block">RIF / Cédula:</span> <span id="infoDoc" class="font-bold text-dark select-all"></span></div>
                            <div id="wrapperInfoTelefono"><span class="text-slate-500 block">Teléfono Pago Móvil:</span> <span id="infoTelefono" class="font-bold text-success select-all"></span></div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-on-surface-variant">Número de Referencia</label>
                    <input type="text" name="referencia" placeholder="Ej. 12345678"
                           class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                    <span class="text-xs text-on-surface-variant/70">Número de operación del banco.</span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-on-surface-variant">Comprobante de Pago (Archivo)</label>
                    <div class="border-2 border-dashed border-outline-variant hover:border-primary rounded-2xl p-6 bg-background/50 hover:bg-background transition-colors text-center cursor-pointer relative">
                        <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf"
                               class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant/40 mb-1">upload_file</span>
                        <div class="text-sm text-on-surface-variant">Haz clic o arrastra para subir tu comprobante</div>
                        <div class="text-xs text-on-surface-variant/70 mt-1">Formatos permitidos: JPG, PNG, PDF (Máx. 5MB)</div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-on-surface-variant">Observaciones</label>
                    <textarea name="observaciones" rows="2" placeholder="Información adicional sobre el pago..."
                              class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none"></textarea>
                </div>

                <div class="flex gap-4 pt-4 border-t border-background flex-wrap">
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-8 py-3 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1">
                        <span class="material-symbols-outlined">send</span>
                        Enviar Comprobante
                    </button>
                    <a href="/residente/dashboard" class="bg-[#95a5a6] hover:bg-[#7f8c8d] text-white font-semibold px-6 py-3 rounded-xl text-center transition-all duration-200 active:scale-95">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-outline-variant p-10 shadow-sm text-center">
            <span class="material-symbols-outlined text-5xl text-primary/30 mb-2">check_circle</span>
            <h3 class="text-xl font-bold text-on-surface mb-2">No tienes facturas pendientes</h3>
            <p class="text-on-surface-variant text-sm mb-6 max-w-sm mx-auto">Tu estado de cuenta está completamente al día, no necesitas reportar pagos por ahora.</p>
            <a href="/residente/dashboard" class="bg-primary hover:bg-primary-hover text-white font-bold px-6 py-2.5 rounded-lg shadow-sm transition-transform active:scale-95 flex items-center gap-1 inline-flex">
                <span class="material-symbols-outlined">dashboard</span>
                Ir al Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function actualizarInfoCuenta(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const card = document.getElementById('cardInfoCuenta');
    if (!opt || !opt.value) {
        card.classList.add('hidden');
        return;
    }

    document.getElementById('infoBanco').textContent = opt.getAttribute('data-banco') || '';
    document.getElementById('infoNumero').textContent = (opt.getAttribute('data-cuenta') || '').replace(/(\d{4})/g, '$1 ').trim();
    document.getElementById('infoTitular').textContent = opt.getAttribute('data-titular') || '';
    document.getElementById('infoDoc').textContent = opt.getAttribute('data-doc') || '';
    
    const tel = opt.getAttribute('data-telefono');
    const wrapTel = document.getElementById('wrapperInfoTelefono');
    if (tel) {
        document.getElementById('infoTelefono').textContent = tel;
        wrapTel.classList.remove('hidden');
    } else {
        wrapTel.classList.add('hidden');
    }

    card.classList.remove('hidden');
}
</script>
