<div class="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Registrar Nuevo Pago</h2>
            <p class="text-sm text-on-surface-variant mt-1">Sube tu comprobante y extrae los datos automáticamente.</p>
        </div>
        <a href="/pagos" class="text-primary hover:bg-background font-bold text-sm px-4 py-2 rounded-xl transition-all flex items-center gap-1 border border-transparent hover:border-outline-variant">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Cancelar
        </a>
    </div>

    <!-- Mensajes de Alerta -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <!-- Resumen Financiero de la Unidad -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white p-4 rounded-xl border border-outline-variant shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Deuda Pendiente</span>
                <span class="text-xl font-bold <?= ($totalDeuda ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                    <?= e(formatearMoneda($totalDeuda ?? 0)) ?>
                </span>
            </div>
            <span class="material-symbols-outlined text-3xl <?= ($totalDeuda ?? 0) > 0 ? 'text-red-400' : 'text-emerald-400' ?>">account_balance_wallet</span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-outline-variant shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Saldo a Favor Actual</span>
                <span class="text-xl font-bold text-primary">
                    <?= e(formatearMoneda($saldoFavor ?? 0)) ?>
                </span>
            </div>
            <span class="material-symbols-outlined text-3xl text-primary/40">savings</span>
        </div>
    </div>

    <form id="formPago" method="POST" action="/pagos/subir" enctype="multipart/form-data" class="bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
        <!-- Token CSRF Obligatorio -->
        <?= csrf_field() ?>

        <div class="p-6 md:p-8 flex flex-col gap-8">
            
            <!-- Zona de Carga (Dropzone) -->
            <div>
                <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
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

                <!-- Cuenta Oficial para el Pago -->
                <div class="flex flex-col gap-1.5 mb-5">
                    <div class="flex items-center justify-between">
                        <label for="cuenta_bancaria_id" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Cuenta Oficial para el Pago <span class="text-red-500">*</span></label>
                        <span id="badge-cuenta_bancaria_id" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                            <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                        </span>
                    </div>
                    <select id="cuenta_bancaria_id" name="cuenta_bancaria_id" required onchange="actualizarInfoCuenta(this)"
                            class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer font-semibold text-sm">
                        <option value="">-- Seleccione la cuenta oficial del conjunto --</option>
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
                    <div id="inconsistencia-cuenta_bancaria_id"></div>

                    <!-- Tarjeta con datos oficiales de la cuenta y botones de copiado rápido (Visibilidad Mejorada) -->
                    <div id="cardInfoCuenta" class="hidden mt-3 p-4 bg-gradient-to-br from-blue-50/95 to-slate-100/80 border border-blue-200 rounded-2xl text-xs text-blue-950 shadow-xs">
                        <div class="flex items-center justify-between border-b border-blue-200/70 pb-2 mb-3">
                            <div class="font-bold text-sm text-primary flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[19px]">verified</span>
                                <span id="infoBanco"></span>
                            </div>
                            <span class="text-[10px] font-bold bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full uppercase">Cuenta Oficial</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2 shadow-xs">
                                <div class="min-w-0">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Número de Cuenta (20 dígitos)</span>
                                    <span id="infoNumero" class="font-mono font-bold text-slate-800 text-base truncate block select-all"></span>
                                </div>
                                <button type="button" onclick="copiarDatoCuenta('infoNumero', this)" class="shrink-0 bg-blue-50 hover:bg-blue-100 text-primary font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar número de cuenta">
                                    <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                    Copiar
                                </button>
                            </div>

                            <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2 shadow-xs">
                                <div class="min-w-0">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Titular Autorizado</span>
                                    <span id="infoTitular" class="font-bold text-slate-800 text-base truncate block select-all"></span>
                                </div>
                            </div>

                            <div class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2 shadow-xs">
                                <div class="min-w-0">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">RIF / Identificación</span>
                                    <span id="infoDoc" class="font-mono font-bold text-slate-800 text-base truncate block select-all"></span>
                                </div>
                                <button type="button" onclick="copiarDatoCuenta('infoDoc', this)" class="shrink-0 bg-blue-50 hover:bg-blue-100 text-primary font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar RIF">
                                    <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                    Copiar
                                </button>
                            </div>

                            <div id="wrapperInfoTelefono" class="bg-white/90 p-2.5 rounded-xl border border-blue-100 flex items-center justify-between gap-2 shadow-xs">
                                <div class="min-w-0">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Teléfono Pago Móvil</span>
                                    <span id="infoTelefono" class="font-mono font-bold text-emerald-700 text-base truncate block select-all"></span>
                                </div>
                                <button type="button" onclick="copiarDatoCuenta('infoTelefono', this)" class="shrink-0 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2 py-1 rounded-lg text-[11px] transition-all flex items-center gap-1 active:scale-95 cursor-pointer" title="Copiar teléfono">
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
                    
                    <!-- Estado Inicial -->
                    <div id="dropzoneInitial" class="flex flex-col items-center pointer-events-none">
                        <div class="w-14 h-14 bg-background rounded-full border border-outline-variant flex items-center justify-center mb-3 group-hover:scale-105 transition-transform text-primary">
                            <span class="material-symbols-outlined text-3xl">upload_file</span>
                        </div>
                        <p class="font-bold text-on-surface text-base">Haz clic o arrastra tu comprobante aquí</p>
                        <p class="text-xs text-slate-500 mt-1">Soporta JPG, PNG y PDF (Máx. 5MB) • Extracción automática</p>
                    </div>

                    <!-- Estado con Archivo (Previsualización) -->
                    <div id="dropzonePreview" class="hidden flex-col items-center pointer-events-none w-full">
                        <!-- Imagen (JPEG/PNG) -->
                        <img id="imagePreview" src="" alt="Vista previa" class="hidden max-h-44 max-w-full rounded-lg object-contain shadow-sm border border-outline-variant bg-white">
                        
                        <!-- PDF -->
                        <div id="pdfPreview" class="hidden flex flex-col items-center w-full">
                            <iframe id="pdfPreviewFrame" src="" title="Vista previa del comprobante PDF" class="w-full max-w-md rounded-lg border border-outline-variant bg-white" style="height: 280px;"></iframe>
                            <span id="pdfName" class="text-xs font-bold text-on-surface text-center break-all max-w-xs mt-2"></span>
                            <a id="pdfPreviewLink" href="#" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="pointer-events-auto text-[11px] font-bold text-primary underline mt-1">Abrir en pestaña nueva</a>
                        </div>
                        
                        <p class="text-[11px] text-primary font-bold mt-3 bg-primary/10 px-3 py-1 rounded-md">Haz clic para cambiar el archivo</p>
                    </div>
                </div>

                <!-- Resumen del resultado de extracción -->
                <div id="resumenExtraccion" class="hidden mt-4 p-3 rounded-xl text-xs flex items-center justify-between gap-3 transition-all">
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

            <hr class="border-background">

            <!-- Campos del Formulario -->
            <div>
                <div class="pb-2 border-b border-background mb-4 flex items-center justify-between">
                    <h4 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-primary text-[20px]">edit_document</span>
                        Paso 2: Datos del Pago (Verifique o complete)
                    </h4>
                    <span class="text-xs text-slate-400 font-medium">* Campos obligatorios</span>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    
                    <!-- Monto Pagado -->
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <div class="flex items-center justify-between">
                            <label for="monto" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Monto Pagado (Bs.) <span class="text-red-500">*</span></label>
                            <span id="badge-monto" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                            </span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-black text-base select-none">Bs.</span>
                            <input type="number" id="monto" name="monto" step="0.01" min="0.01" required placeholder="0.00"
                                   class="w-full pl-12 pr-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-900 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-black text-lg">
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Cualquier pago que exceda su deuda o pago anticipado se acreditará automáticamente como <strong>Saldo a Favor</strong> para sus próximas cuotas.
                        </p>
                        <div id="inconsistencia-monto"></div>
                    </div>

                    <!-- Banco Pagador (Origen) con Selector -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label for="banco_pagador" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Banco Emisor / Pagador (Origen)</label>
                            <span id="badge-banco_pagador" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                            </span>
                        </div>
                        <div class="relative">
                            <select id="banco_pagador" name="banco_pagador" onchange="toggleBancoOtro(this)"
                                    class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer font-semibold text-sm">
                                <option value="">-- Seleccione el banco emisor --</option>
                                <option value="Banco de Venezuela">Banco de Venezuela (BDV)</option>
                                <option value="Banesco">Banesco</option>
                                <option value="Banco Mercantil">Banco Mercantil</option>
                                <option value="BBVA Provincial">BBVA Provincial</option>
                                <option value="Bancamiga">Bancamiga</option>
                                <option value="Banco Nacional de Crédito">Banco Nacional de Crédito (BNC)</option>
                                <option value="Bancaribe">Bancaribe</option>
                                <option value="Banco del Tesoro">Banco del Tesoro</option>
                                <option value="Banco Exterior">Banco Exterior</option>
                                <option value="Banco Plaza">Banco Plaza</option>
                                <option value="Banco Activo">Banco Activo</option>
                                <option value="Banco Fondo Común">Banco Fondo Común (BFC)</option>
                                <option value="100% Banco">100% Banco</option>
                                <option value="Banco Sofitasa">Banco Sofitasa</option>
                                <option value="Banplus">Banplus</option>
                                <option value="Banco Caroní">Banco Caroní</option>
                                <option value="Bancrecer">Bancrecer</option>
                                <option value="Mi Banco">Mi Banco</option>
                                <option value="Banco Digital de los Trabajadores">Banco Digital de los Trabajadores (Bicentenario)</option>
                                <option value="Banco Agrícola de Venezuela">Banco Agrícola de Venezuela</option>
                                <option value="BANFANB">BANFANB</option>
                                <option value="OTRO">Otro banco...</option>
                            </select>
                        </div>
                        <div id="contenedor_banco_otro" class="hidden mt-2">
                            <input type="text" id="banco_pagador_otro" name="banco_pagador_otro" placeholder="Especifique el nombre del banco emisor..."
                                   class="w-full px-4 py-2.5 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm font-medium">
                        </div>
                        <div id="inconsistencia-banco_pagador"></div>
                    </div>

                    <!-- Fecha de Pago -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label for="fecha_pago" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Fecha de Realización <span class="text-red-500">*</span></label>
                            <span id="badge-fecha_pago" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                            </span>
                        </div>
                        <input type="date" id="fecha_pago" name="fecha_pago" value="" required
                               class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-medium text-sm">
                        <div id="inconsistencia-fecha_pago"></div>
                    </div>

                    <!-- Número de Referencia -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label for="referencia" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Número de Referencia</label>
                            <span id="badge-referencia" class="hidden text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                <span class="material-symbols-outlined text-[12px]">magic_button</span> Auto-completado
                            </span>
                        </div>
                        <input type="text" id="referencia" name="referencia" placeholder="Ej. 12345678"
                               class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-900 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-mono font-medium text-sm">
                        <div id="inconsistencia-referencia"></div>
                    </div>

                    <!-- Observaciones -->
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label for="observaciones" class="text-xs font-bold text-slate-600 uppercase tracking-wide">Observaciones Complementarias</label>
                        <textarea id="observaciones" name="observaciones" rows="2" placeholder="Información adicional sobre el pago..."
                                  class="w-full px-4 py-3 bg-white border border-outline-variant rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all resize-none text-sm"></textarea>
                    </div>

                </div>
            </div>

        </div>

        <!-- Botón de Envío -->
        <div class="p-6 bg-slate-50 border-t border-background flex justify-end">
            <button type="submit" class="w-full sm:w-auto bg-primary hover:bg-primary-hover text-white font-bold px-8 py-3.5 rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">send</span>
                Enviar a Revisión
            </button>
        </div>
    </form>
</div>

<!-- Tesseract.js v5 CDN para OCR en cliente (agnóstico de servidor) -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<!-- Módulo compartido de extracción de comprobantes (OCR en dos pasadas) -->
<script src="/js/comprobante-ocr.js"></script>

<script>
    const fileInput = document.getElementById('comprobante');
    const dropzone = document.getElementById('dropzone');
    const dropzoneInitial = document.getElementById('dropzoneInitial');
    const dropzonePreview = document.getElementById('dropzonePreview');
    const imagePreview = document.getElementById('imagePreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const pdfName = document.getElementById('pdfName');
    const pdfPreviewFrame = document.getElementById('pdfPreviewFrame');
    const pdfPreviewLink = document.getElementById('pdfPreviewLink');
    const ocrStatusBadge = document.getElementById('ocrStatusBadge');
    const ocrStatusText = document.getElementById('ocrStatusText');
    const ocrSpinner = document.getElementById('ocrSpinner');
    const resumenExtraccion = document.getElementById('resumenExtraccion');
    const resumenIcono = document.getElementById('resumenIcono');
    const resumenMensaje = document.getElementById('resumenMensaje');
    const btnReanalizar = document.getElementById('btnReanalizar');

    let currentObjectURL = null;
    let archivoActual = null;
    let ultimosDatosExtraidos = null;

    // Manejo de Dropzone visual (Drag and Drop)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('border-primary', 'bg-blue-50/50'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('border-primary', 'bg-blue-50/50'), false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            handleFiles(files[0]);
        }
    }, false);

    // Selección por clic tradicional
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFiles(this.files[0]);
        }
    });

    function handleFiles(file) {
        // Validar tamaño (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert("El archivo excede el tamaño máximo permitido de 5MB.");
            fileInput.value = '';
            resetPreview();
            return;
        }

        archivoActual = file;
        dropzoneInitial.classList.add('hidden');
        dropzonePreview.classList.remove('hidden');
        dropzonePreview.classList.add('flex');

        // Liberar URL previa de memoria si existía
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
            if (pdfPreviewFrame) pdfPreviewFrame.src = currentObjectURL;
            if (pdfPreviewLink) pdfPreviewLink.href = currentObjectURL;
        } else if (file.type === 'image/jpeg' || file.type === 'image/png') {
            pdfPreview.classList.add('hidden');
            pdfPreview.classList.remove('flex');
            if (pdfPreviewFrame) pdfPreviewFrame.src = 'about:blank';
            if (pdfPreviewLink) pdfPreviewLink.href = '#';
            imagePreview.classList.remove('hidden');
            imagePreview.src = currentObjectURL;
        } else {
            alert("Formato de archivo no válido. Solo JPG, PNG o PDF.");
            fileInput.value = '';
            resetPreview();
            return;
        }

        // Auto-disparar extracción inmediatamente al seleccionar archivo
        ejecutarExtraccion(file);
    }

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

    function resetPreview() {
        if (currentObjectURL) {
            URL.revokeObjectURL(currentObjectURL);
            currentObjectURL = null;
        }
        if (pdfPreviewFrame) pdfPreviewFrame.src = 'about:blank';
        if (pdfPreviewLink) pdfPreviewLink.href = '#';
        archivoActual = null;
        dropzoneInitial.classList.remove('hidden');
        dropzonePreview.classList.add('hidden');
        dropzonePreview.classList.remove('flex');
        limpiarInconsistencias();
        if (resumenExtraccion) resumenExtraccion.classList.add('hidden');
    }

    function actualizarEstadoOCR(tipo, mensaje) {
        if (!ocrStatusBadge) return;
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
        if (!ocrStatusBadge) return;
        ocrSpinner.classList.add('hidden');
        setTimeout(() => {
            ocrStatusBadge.classList.add('hidden');
            ocrStatusBadge.classList.remove('flex');
        }, 1500);
    }

    function mostrarResumenExtraccion(tipo, mensaje) {
        if (!resumenExtraccion) return;
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
        if (!file) return;

        actualizarEstadoOCR('ocr', 'Iniciando lectura...');
        limpiarInconsistencias();

        try {
            const resultado = await ComprobanteOCR.procesar(file, {
                endpoint: '/pagos/extraer',
                onEstado: (estado, valor) => {
                    if (estado === 'motor') {
                        actualizarEstadoOCR('ocr', 'Cargando motor de visión...');
                    } else if (estado === 'leyendo') {
                        actualizarEstadoOCR('ocr', `Leyendo imagen (${valor}%)...`);
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
                mostrarResumenExtraccion('info', data.error || "No se pudieron extraer datos del comprobante.");
            }
        } catch (err) {
            console.error("Error en proceso OCR:", err);
            const detalleError = (err && err.message) ? err.message : "el motor OCR no pudo iniciarse";
            mostrarResumenExtraccion('info', "Extracción automática no completada (" + detalleError + "). Ingrese los datos manualmente.");
        } finally {
            finalizarEstadoOCR();
        }
    }

    function aplicarExtraccionInteligente(data) {
        let camposLlenados = 0;
        let camposActualizados = 0;

        // Regla: si el campo está vacío se completa; si tiene otro valor, se
        // actualiza automáticamente con el del comprobante (sin botones).

        // 1. Monto
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

        // 2. Banco Pagador (Origen)
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

        // 3. Fecha de Pago
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

        // 4. Referencia
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

        // 5. Cuenta Oficial para el Pago
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
            Object.keys(data.inconsistencias).forEach(campoId => {
                const adv = data.inconsistencias[campoId];
                if (adv) {
                    renderizarInconsistencia(campoId, adv);
                    totalInconsistencias++;
                }
            });
        }

        // Mostrar resumen en UI
        if (totalInconsistencias > 0) {
            mostrarResumenExtraccion('inconsistencia', `Se autocompletaron datos del comprobante, pero se detectaron ${totalInconsistencias} observación(es). Revise los campos señalados antes de enviar.`);
        } else if (camposLlenados > 0 && camposActualizados > 0) {
            mostrarResumenExtraccion('exito', `Se autocompletaron ${camposLlenados} campo(s) y se actualizaron ${camposActualizados} desde el comprobante.`);
        } else if (camposActualizados > 0) {
            mostrarResumenExtraccion('exito', `Se actualizaron ${camposActualizados} campo(s) con los datos del comprobante.`);
        } else if (camposLlenados > 0) {
            mostrarResumenExtraccion('exito', `Se autocompletaron ${camposLlenados} campo(s) exitosamente a partir del comprobante.`);
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
        const contenedor = document.getElementById(`inconsistencia-${campoId}`);
        if (!contenedor) return;
        contenedor.innerHTML = `
            <div class="mt-1.5 flex items-start gap-1.5 text-xs text-amber-800 bg-amber-50 border border-amber-300 rounded-lg p-2 shadow-sm transition-all duration-200">
                <span class="material-symbols-outlined text-[16px] text-amber-600 mt-0.5 shrink-0">warning</span>
                <span class="leading-tight">${escapeHtml(mensaje)}</span>
            </div>
        `;
        const input = document.getElementById(campoId);
        if (input) {
            input.classList.add('border-amber-400', 'bg-amber-50/20');
        }
    }

    function limpiarInconsistenciaCampo(campoId) {
        const c = document.getElementById(`inconsistencia-${campoId}`);
        if (c) c.innerHTML = '';
        const input = document.getElementById(campoId);
        if (input) {
            input.classList.remove('border-amber-400', 'bg-amber-50/20');
        }
    }

    function limpiarInconsistencias() {
        ['monto', 'banco_pagador', 'fecha_pago', 'referencia', 'cuenta_bancaria_id'].forEach(id => {
            limpiarInconsistenciaCampo(id);
            const b = document.getElementById(`badge-${id}`);
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

    if (btnReanalizar) {
        btnReanalizar.addEventListener('click', function(e) {
            e.preventDefault();
            if (archivoActual) {
                ejecutarExtraccion(archivoActual);
            }
        });
    }

    function toggleBancoOtro(selectEl) {
        const cont = document.getElementById('contenedor_banco_otro');
        const inputOtro = document.getElementById('banco_pagador_otro');
        if (!cont) return;
        if (selectEl.value === 'OTRO') {
            cont.classList.remove('hidden');
            if (inputOtro) {
                inputOtro.setAttribute('required', 'required');
                inputOtro.focus();
            }
        } else {
            cont.classList.add('hidden');
            if (inputOtro) {
                inputOtro.removeAttribute('required');
                inputOtro.value = '';
            }
        }
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

    function copiarDatoCuenta(elementId, btnElement) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const texto = el.textContent.trim().replace(/\s+/g, '');
        if (!texto) return;
        
        navigator.clipboard.writeText(texto).then(() => {
            const originalHTML = btnElement.innerHTML;
            btnElement.innerHTML = `<span class="material-symbols-outlined text-[14px]">check</span> ¡Copiado!`;
            btnElement.classList.add('bg-emerald-100', 'text-emerald-800');
            setTimeout(() => {
                btnElement.innerHTML = originalHTML;
                btnElement.classList.remove('bg-emerald-100', 'text-emerald-800');
            }, 1800);
        }).catch(err => {
            console.error('Error al copiar al portapapeles:', err);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        ['monto', 'banco_pagador', 'banco_pagador_otro', 'fecha_pago', 'referencia', 'cuenta_bancaria_id'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const target = id === 'banco_pagador_otro' ? 'banco_pagador' : id;
                el.addEventListener('input', () => limpiarInconsistenciaCampo(target));
                el.addEventListener('change', () => limpiarInconsistenciaCampo(target));
            }
        });

        const selCuenta = document.getElementById('cuenta_bancaria_id');
        if (selCuenta && selCuenta.value) {
            actualizarInfoCuenta(selCuenta);
        }
        const selBanco = document.getElementById('banco_pagador');
        if (selBanco && selBanco.value === 'OTRO') {
            toggleBancoOtro(selBanco);
        }
    });
</script>
