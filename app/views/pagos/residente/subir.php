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

    <form id="formPago" method="POST" action="/pagos/subir" enctype="multipart/form-data" class="bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
        <!-- Token CSRF Obligatorio -->
        <?= csrf_field() ?>

        <div class="p-6 md:p-8 flex flex-col gap-8">
            
            <!-- Zona de Carga (Dropzone) -->
            <div>
                <h3 class="text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">cloud_upload</span>
                    Paso 1: Subir Comprobante
                </h3>
                
                <div id="dropzone" class="border-2 border-dashed border-outline-variant hover:border-primary bg-slate-50 hover:bg-blue-50/30 rounded-2xl p-8 transition-colors text-center cursor-pointer relative group flex flex-col items-center justify-center min-h-[200px]">
                    <input type="file" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required
                           class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10">
                    
                    <!-- Estado Inicial -->
                    <div id="dropzoneInitial" class="flex flex-col items-center pointer-events-none">
                        <div class="w-16 h-16 bg-white rounded-full shadow-sm border border-outline-variant flex items-center justify-center mb-4 group-hover:scale-110 group-hover:text-primary transition-transform">
                            <span class="material-symbols-outlined text-4xl text-slate-400 group-hover:text-primary">upload_file</span>
                        </div>
                        <p class="font-bold text-on-surface text-lg">Haz clic o arrastra tu comprobante aquí</p>
                        <p class="text-sm text-slate-500 mt-1">Soporta JPG, PNG y PDF (Máx. 5MB)</p>
                    </div>

                    <!-- Estado con Archivo (Previsualización) -->
                    <div id="dropzonePreview" class="hidden flex-col items-center pointer-events-none w-full">
                        <!-- Imagen (JPEG/PNG) -->
                        <img id="imagePreview" src="" alt="Vista previa" class="hidden max-h-48 max-w-full rounded-lg object-contain shadow-sm border border-outline-variant bg-white">
                        
                        <!-- PDF -->
                        <div id="pdfPreview" class="hidden flex flex-col items-center">
                            <span class="material-symbols-outlined text-6xl text-red-500 mb-2">picture_as_pdf</span>
                            <span id="pdfName" class="text-sm font-bold text-on-surface text-center break-all max-w-xs"></span>
                        </div>
                        
                        <p class="text-xs text-primary font-bold mt-4 bg-primary/10 px-3 py-1 rounded-lg">Haz clic para cambiar el archivo</p>
                    </div>
                </div>

                <div class="mt-4 flex justify-center">
                    <!-- Botón Extraer Datos (OCR) -->
                    <button type="button" id="btnOCR" disabled class="bg-slate-800 hover:bg-slate-700 text-white disabled:bg-slate-200 disabled:text-slate-400 font-bold px-6 py-3 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer disabled:cursor-not-allowed w-full sm:w-auto">
                        <span class="material-symbols-outlined text-[20px]">document_scanner</span>
                        <span id="ocrText">Extraer datos automáticamente</span>
                        <div id="ocrSpinner" class="hidden animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent"></div>
                    </button>
                </div>
            </div>

            <hr class="border-background">

            <!-- Campos del Formulario -->
            <div>
                <h3 class="text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">edit_document</span>
                    Paso 2: Verificar o completar datos
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Monto Pagado (Bs.) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant font-bold">Bs.</span>
                            <input type="number" id="monto" name="monto" step="0.01" min="0.01" required placeholder="0.00"
                                   class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-bold text-lg">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Banco Pagador (Origen)</label>
                        <input type="text" id="banco_pagador" name="banco_pagador" placeholder="Ej. Banesco"
                               class="w-full px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-medium">
                    </div>

                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Cuenta Bancaria Destino (Autorizada) <span class="text-red-500">*</span></label>
                        <select id="cuenta_bancaria_id" name="cuenta_bancaria_id" required onchange="actualizarInfoCuenta(this)"
                                class="w-full px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-semibold">
                            <option value="">-- Seleccione la cuenta receptora autorizada --</option>
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

                        <!-- Tarjeta con datos oficiales de la cuenta seleccionada -->
                        <div id="cardInfoCuenta" class="hidden mt-2 p-3.5 bg-blue-50/80 border border-blue-200 rounded-xl text-xs text-blue-950 shadow-sm">
                            <div class="font-bold text-sm text-primary mb-2 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">verified</span>
                                <span id="infoBanco"></span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                <div><span class="text-slate-500 block">Número de Cuenta (20 dígitos):</span> <span id="infoNumero" class="font-mono font-bold text-dark text-xs select-all"></span></div>
                                <div><span class="text-slate-500 block">Titular:</span> <span id="infoTitular" class="font-bold text-dark"></span></div>
                                <div><span class="text-slate-500 block">RIF / Cédula:</span> <span id="infoDoc" class="font-bold text-dark select-all"></span></div>
                                <div id="wrapperInfoTelefono"><span class="text-slate-500 block">Teléfono Pago Móvil:</span> <span id="infoTelefono" class="font-bold text-success select-all"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Fecha de Pago <span class="text-red-500">*</span></label>
                        <input type="date" id="fecha_pago" name="fecha_pago" value="<?= date('Y-m-d') ?>" required
                               class="w-full px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-medium">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Número de Referencia</label>
                        <input type="text" id="referencia" name="referencia" placeholder="Nro. de confirmación u operación"
                               class="w-full px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-medium font-mono">
                    </div>

                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Notas Adicionales</label>
                        <textarea id="observaciones" name="observaciones" rows="2" placeholder="Cualquier información adicional (Opcional)"
                                  class="w-full px-4 py-3 bg-slate-50 border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-medium resize-none"></textarea>
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

<script>
    const fileInput = document.getElementById('comprobante');
    const dropzone = document.getElementById('dropzone');
    const dropzoneInitial = document.getElementById('dropzoneInitial');
    const dropzonePreview = document.getElementById('dropzonePreview');
    const imagePreview = document.getElementById('imagePreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const pdfName = document.getElementById('pdfName');
    const btnOCR = document.getElementById('btnOCR');
    const ocrText = document.getElementById('ocrText');
    const ocrSpinner = document.getElementById('ocrSpinner');

    let currentObjectURL = null;

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

        dropzoneInitial.classList.add('hidden');
        dropzonePreview.classList.remove('hidden');
        dropzonePreview.classList.add('flex');
        btnOCR.disabled = false;

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
        } else if (file.type === 'image/jpeg' || file.type === 'image/png') {
            pdfPreview.classList.add('hidden');
            pdfPreview.classList.remove('flex');
            imagePreview.classList.remove('hidden');
            imagePreview.src = currentObjectURL;
        } else {
            alert("Formato de archivo no válido. Solo JPG, PNG o PDF.");
            fileInput.value = '';
            resetPreview();
        }
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
        dropzoneInitial.classList.remove('hidden');
        dropzonePreview.classList.add('hidden');
        dropzonePreview.classList.remove('flex');
        btnOCR.disabled = true;
    }

    // Autocompletar OCR híbrido (Tesseract.js para imágenes en cliente, extracción directa en servidor para PDF)
    btnOCR.addEventListener('click', async function(e) {
        e.preventDefault();
        
        const file = fileInput.files[0];
        if (!file) return;

        btnOCR.disabled = true;
        ocrSpinner.classList.remove('hidden');

        try {
            const formData = new FormData();
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                formData.append('csrf_token', csrfInput.value);
            }

            // Flujo A: Imágenes (JPG / PNG) analizadas con Tesseract.js en el navegador
            if (file.type.startsWith('image/')) {
                ocrText.textContent = "Iniciando motor OCR...";

                if (typeof Tesseract === 'undefined') {
                    throw new Error("Librería OCR no disponible. Verifique su conexión a internet.");
                }

                const result = await Tesseract.recognize(file, 'spa', {
                    logger: m => {
                        if (m.status === 'recognizing text') {
                            const pct = Math.round((m.progress || 0) * 100);
                            ocrText.textContent = `Leyendo imagen (${pct}%)...`;
                        } else if (m.status === 'loading tesseract core' || m.status === 'initializing tesseract') {
                            ocrText.textContent = "Cargando motor de visión...";
                        }
                    }
                });

                const textoExtraido = result?.data?.text || '';
                formData.append('texto_extraido', textoExtraido);
                ocrText.textContent = "Estructurando datos bancarios...";
            } else {
                // Flujo B: PDF analizado directamente en el servidor mediante streams nativos FlateDecode
                ocrText.textContent = "Analizando PDF...";
                formData.append('comprobante', file);
            }

            const response = await fetch('/pagos/extraer', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const inputs = {
                    'monto': data.monto,
                    'referencia': data.referencia,
                    'fecha_pago': data.fecha_pago,
                    'banco_pagador': data.banco_pagador
                };

                let camposLlenados = 0;
                for (const [id, value] of Object.entries(inputs)) {
                    const el = document.getElementById(id);
                    if (el && value) {
                        el.value = value;
                        camposLlenados++;
                        el.classList.add('bg-emerald-50', 'border-primary');
                        setTimeout(() => {
                            el.classList.remove('bg-emerald-50', 'border-primary');
                        }, 1200);
                    }
                }

                // Autoseleccionar cuenta bancaria receptora autorizada coincidente
                if (data.banco_receptor) {
                    const selCuenta = document.getElementById('cuenta_bancaria_id');
                    const bncLower = data.banco_receptor.toLowerCase();
                    for (let i = 0; i < selCuenta.options.length; i++) {
                        const optBnc = (selCuenta.options[i].getAttribute('data-banco') || '').toLowerCase();
                        if (optBnc && (optBnc.includes(bncLower) || bncLower.includes(optBnc))) {
                            selCuenta.selectedIndex = i;
                            actualizarInfoCuenta(selCuenta);
                            camposLlenados++;
                            break;
                        }
                    }
                }

                if (!data.detectado || camposLlenados < 2) {
                    alert(data.mensaje || "Se extrajeron datos parciales. Por favor complete o revise los campos requeridos.");
                }
            } else {
                alert(data.error || "No se pudieron extraer datos del comprobante.");
            }
        } catch (err) {
            console.error("Error en proceso OCR:", err);
            alert("No se pudo completar el reconocimiento automático (" + (err.message || "error inesperado") + "). Por favor ingrese los datos manualmente.");
        } finally {
            btnOCR.disabled = false;
            ocrText.textContent = "Extraer datos automáticamente";
            ocrSpinner.classList.add('hidden');
        }
    });
</script>
