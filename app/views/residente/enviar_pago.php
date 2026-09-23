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

            <!-- Formulario de Reporte de Pago -->
            <form id="formEnviarPago" method="POST" action="/residente/enviar-pago" enctype="multipart/form-data" class="flex flex-col gap-6">
                <!-- CSRF Token -->
                <?= csrf_field() ?>

                <!-- Selección de Factura -->
                <div class="flex flex-col gap-1.5">
                    <label for="factura_id" class="text-sm font-semibold text-on-surface-variant">Factura a Pagar <span class="text-red-500">*</span></label>
                    <select id="factura_id" name="factura_id" class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium" required>
                        <option value="">Seleccione una factura...</option>
                        <?php foreach ($facturas_pendientes as $f): ?>
                            <option value="<?= e($f['id']) ?>" <?= ($factura_id == $f['id']) ? 'selected' : '' ?>>
                                Factura #<?= e($f['numero_factura']) ?> - <?= e(nombreMes($f['mes'])) ?> <?= e($f['anio']) ?> (Saldo: <?= e(formatearMoneda($f['saldo'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-xs text-on-surface-variant/70">Seleccione la factura que desea reportar.</span>
                </div>

                <!-- Paso 1: Subida de Comprobante con Extracción Automática -->
                <div class="bg-slate-50/70 border border-outline-variant rounded-2xl p-5 md:p-6 flex flex-col gap-4">
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

                    <!-- Dropzone interactivo -->
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

                <!-- Paso 2: Datos del Pago -->
                <div class="flex flex-col gap-5">
                    <div class="pb-2 border-b border-background">
                        <h4 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[20px]">edit_document</span>
                            Paso 2: Datos del Pago (Verifique o complete)
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Monto Pagado -->
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <label for="monto" class="text-sm font-semibold text-on-surface-variant">Monto Pagado <span class="text-red-500">*</span></label>
                                <span id="badge-monto" class="hidden text-[11px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[13px]">magic_button</span> Auto-completado
                                </span>
                            </div>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant font-bold">Bs.</span>
                                <input type="number" id="monto" name="monto" step="0.01" min="0.01" required placeholder="0.00"
                                       class="w-full pl-12 pr-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all font-semibold">
                            </div>
                            <div id="inconsistencia-monto"></div>
                            <span class="text-xs text-on-surface-variant/70">Monto exacto de la transferencia o pago móvil.</span>
                        </div>

                        <!-- Fecha de Pago -->
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <label for="fecha_pago" class="text-sm font-semibold text-on-surface-variant">Fecha de Pago <span class="text-red-500">*</span></label>
                                <span id="badge-fecha_pago" class="hidden text-[11px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[13px]">magic_button</span> Auto-completado
                                </span>
                            </div>
                            <input type="date" id="fecha_pago" name="fecha_pago" value="" required
                                   class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all font-medium">
                            <div id="inconsistencia-fecha_pago"></div>
                            <span class="text-xs text-on-surface-variant/70">Fecha en que se ejecutó la operación.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Método de Pago -->
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <label for="metodo_pago" class="text-sm font-semibold text-on-surface-variant">Método de Pago <span class="text-red-500">*</span></label>
                                <span id="badge-metodo_pago" class="hidden text-[11px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[13px]">magic_button</span> Auto-completado
                                </span>
                            </div>
                            <select id="metodo_pago" name="metodo_pago" class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer font-medium" required>
                                <option value="">Seleccione un método...</option>
                                <option value="transferencia">Transferencia Bancaria</option>
                                <option value="pago_movil">Pago Móvil</option>
                            </select>
                            <div id="inconsistencia-metodo_pago"></div>
                        </div>

                        <!-- Número de Referencia -->
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <label for="referencia" class="text-sm font-semibold text-on-surface-variant">Número de Referencia <span class="text-red-500">*</span></label>
                                <span id="badge-referencia" class="hidden text-[11px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[13px]">magic_button</span> Auto-completado
                                </span>
                            </div>
                            <input type="text" id="referencia" name="referencia" placeholder="Ej. 12345678" required
                                   class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all font-mono font-medium">
                            <div id="inconsistencia-referencia"></div>
                            <span class="text-xs text-on-surface-variant/70">Número de operación del banco.</span>
                        </div>
                    </div>

                    <!-- Cuenta Bancaria Destino -->
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label for="cuenta_bancaria_id" class="text-sm font-semibold text-on-surface-variant">Cuenta Bancaria Destino (Autorizada) <span class="text-red-500">*</span></label>
                            <span id="badge-cuenta_bancaria_id" class="hidden text-[11px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-300 px-2 py-0.5 rounded-full items-center gap-0.5">
                                <span class="material-symbols-outlined text-[13px]">magic_button</span> Auto-completado
                            </span>
                        </div>
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
                        <div id="inconsistencia-cuenta_bancaria_id"></div>

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

                    <!-- Observaciones -->
                    <div class="flex flex-col gap-1.5">
                        <label for="observaciones" class="text-sm font-semibold text-on-surface-variant">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="2" placeholder="Información adicional sobre el pago..."
                                  class="w-full px-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="flex gap-4 pt-4 border-t border-background flex-wrap">
                    <button type="submit" id="btnSubmit" class="bg-primary hover:bg-primary-hover text-white font-bold px-8 py-3 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center gap-1">
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

<!-- Tesseract.js v5 CDN para OCR en navegador -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

<script>
const fileInput = document.getElementById('comprobante');
const dropzone = document.getElementById('dropzone');
const dropzoneInitial = document.getElementById('dropzoneInitial');
const dropzonePreview = document.getElementById('dropzonePreview');
const imagePreview = document.getElementById('imagePreview');
const pdfPreview = document.getElementById('pdfPreview');
const pdfName = document.getElementById('pdfName');
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

// Drag & Drop
if (dropzone) {
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
            procesarArchivoSeleccionado(files[0]);
        }
    }, false);
}

if (fileInput) {
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            procesarArchivoSeleccionado(this.files[0]);
        }
    });
}

if (btnReanalizar) {
    btnReanalizar.addEventListener('click', function(e) {
        e.preventDefault();
        if (archivoActual) {
            ejecutarExtraccion(archivoActual);
        }
    });
}

function procesarArchivoSeleccionado(file) {
    if (file.size > 5 * 1024 * 1024) {
        alert("El archivo excede el tamaño máximo permitido de 5MB.");
        fileInput.value = '';
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
        alert("Formato no válido. Solo se permiten imágenes JPG, PNG o documentos PDF.");
        fileInput.value = '';
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
    resumenExtraccion.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-900', 'border-emerald-200', 'bg-amber-50', 'text-amber-900', 'border-amber-200', 'bg-rose-50', 'text-rose-900', 'border-rose-200', 'border');
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
        const formData = new FormData();
        const csrfInput = document.querySelector('input[name="csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
        if (csrfInput) {
            formData.append('csrf_token', csrfInput.value || csrfInput.content);
        }

        if (file.type.startsWith('image/')) {
            actualizarEstadoOCR('ocr', 'Iniciando motor OCR...');
            if (typeof Tesseract === 'undefined') {
                throw new Error("Librería OCR no disponible. Verifique su conexión.");
            }

            const result = await Tesseract.recognize(file, 'spa', {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        const pct = Math.round((m.progress || 0) * 100);
                        actualizarEstadoOCR('ocr', `Leyendo imagen (${pct}%)...`);
                    } else if (m.status === 'loading tesseract core' || m.status === 'initializing tesseract') {
                        actualizarEstadoOCR('ocr', 'Cargando motor de visión...');
                    }
                }
            });

            const textoExtraido = result?.data?.text || '';
            formData.append('texto_extraido', textoExtraido);
            actualizarEstadoOCR('analizando', 'Estructurando datos bancarios...');
        } else {
            actualizarEstadoOCR('analizando', 'Analizando PDF en el servidor...');
            formData.append('comprobante', file);
        }

        const response = await fetch('/pagos/extraer', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            ultimosDatosExtraidos = data;
            aplicarExtraccionInteligente(data);
        } else {
            mostrarResumenExtraccion('info', data.error || 'No se pudieron extraer datos del comprobante.');
        }
    } catch (err) {
        console.error("Error en extracción:", err);
        mostrarResumenExtraccion('info', "Extracción automática no completada (" + (err.message || "error") + "). Ingrese los datos manualmente.");
    } finally {
        finalizarEstadoOCR();
    }
}

function aplicarExtraccionInteligente(data) {
    let camposLlenados = 0;
    let inconsistencias = 0;

    // Regla de Negocio:
    // 1. "solo completar los vacíos" -> Si el campo está vacío, completar con el valor extraído.
    // 2. "indicar inconsistencias" -> Si ya tiene un valor y difiere del comprobante, alertar sin sobreescribir.

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
                    mostrarAlertaInconsistencia('monto', data.monto, `Bs. ${data.monto}`, `Bs. ${valActual}`, (val) => {
                        el.value = val;
                        marcarCampoAutollenado('monto');
                    });
                    inconsistencias++;
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
                mostrarAlertaInconsistencia('fecha_pago', data.fecha_pago, data.fecha_pago, valActual, (val) => {
                    el.value = val;
                    marcarCampoAutollenado('fecha_pago');
                });
                inconsistencias++;
            }
        }
    }

    // 3. Método de Pago
    if (data.metodo_pago) {
        const el = document.getElementById('metodo_pago');
        if (el) {
            const valActual = el.value.trim();
            const nombresMetodos = {
                'pago_movil': 'Pago Móvil',
                'transferencia': 'Transferencia Bancaria'
            };
            const labelDetectado = nombresMetodos[data.metodo_pago] || data.metodo_pago;
            if (valActual === '') {
                el.value = data.metodo_pago;
                marcarCampoAutollenado('metodo_pago');
                camposLlenados++;
            } else if (valActual !== data.metodo_pago) {
                const labelActual = nombresMetodos[valActual] || valActual;
                mostrarAlertaInconsistencia('metodo_pago', data.metodo_pago, labelDetectado, labelActual, (val) => {
                    el.value = val;
                    marcarCampoAutollenado('metodo_pago');
                });
                inconsistencias++;
            }
        }
    }

    // 4. Número de Referencia
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
                    mostrarAlertaInconsistencia('referencia', data.referencia, data.referencia, valActual, (val) => {
                        el.value = val;
                        marcarCampoAutollenado('referencia');
                    });
                    inconsistencias++;
                }
            }
        }
    }

    // 5. Cuenta Bancaria Destino Autorizada
    const selCuenta = document.getElementById('cuenta_bancaria_id');
    if (selCuenta && (data.cuenta_bancaria_id || data.banco_receptor)) {
        let matchedIndex = -1;
        let detectedLabel = '';

        if (data.cuenta_bancaria_id) {
            for (let i = 0; i < selCuenta.options.length; i++) {
                if (selCuenta.options[i].value === String(data.cuenta_bancaria_id)) {
                    matchedIndex = i;
                    detectedLabel = selCuenta.options[i].text;
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
                    detectedLabel = selCuenta.options[i].text;
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
                const currentLabel = selCuenta.options[selCuenta.selectedIndex].text;
                const targetValue = selCuenta.options[matchedIndex].value;
                mostrarAlertaInconsistencia('cuenta_bancaria_id', targetValue, detectedLabel, currentLabel, (val) => {
                    selCuenta.value = val;
                    actualizarInfoCuenta(selCuenta);
                    marcarCampoAutollenado('cuenta_bancaria_id');
                });
                inconsistencias++;
            }
        }
    }

    // Resumen en la UI
    if (inconsistencias > 0) {
        mostrarResumenExtraccion('inconsistencia', `Se autocompletaron ${camposLlenados} campo(s). Se detectaron ${inconsistencias} inconsistencia(s) con valores ya ingresados.`);
    } else if (camposLlenados > 0) {
        mostrarResumenExtraccion('exito', `Se autocompletaron ${camposLlenados} campo(s) exitosamente desde el comprobante.`);
    } else {
        mostrarResumenExtraccion('info', 'Los datos del comprobante coinciden con los ya ingresados en el formulario.');
    }
}

function mostrarAlertaInconsistencia(campoId, valorDetectado, textoDetectado, textoActual, callbackAplicar) {
    const contenedor = document.getElementById(`inconsistencia-${campoId}`);
    if (!contenedor) return;

    contenedor.innerHTML = `
        <div class="mt-1.5 flex items-center justify-between gap-2 p-2.5 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-900 shadow-xs transition-all">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="material-symbols-outlined text-[17px] text-amber-600 shrink-0">warning</span>
                <span class="truncate">
                    Comprobante indica: <strong class="font-bold text-amber-950 font-mono">${escapeHtml(textoDetectado)}</strong>
                    <span class="text-amber-700 hidden sm:inline">(actual: ${escapeHtml(textoActual)})</span>
                </span>
            </div>
            <button type="button" class="btn-aplicar px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition-all shrink-0 active:scale-95 flex items-center gap-1 shadow-xs cursor-pointer">
                <span class="material-symbols-outlined text-[13px]">check</span>
                Aplicar
            </button>
        </div>
    `;

    const btn = contenedor.querySelector('.btn-aplicar');
    btn.addEventListener('click', () => {
        if (callbackAplicar) callbackAplicar(valorDetectado);
        contenedor.innerHTML = '';
        const badge = document.getElementById(`badge-${campoId}`);
        if (badge) {
            badge.classList.remove('hidden');
            badge.classList.add('inline-flex');
        }
    });

    // Escuchar si el usuario corrige manualmente el campo para limpiar la alerta
    const el = document.getElementById(campoId);
    if (el) {
        const handler = () => {
            const valNow = el.value.trim();
            if (valNow === String(valorDetectado).trim()) {
                contenedor.innerHTML = '';
                el.removeEventListener('input', handler);
                el.removeEventListener('change', handler);
            }
        };
        el.addEventListener('input', handler);
        el.addEventListener('change', handler);
    }
}

function marcarCampoAutollenado(campoId) {
    const el = document.getElementById(campoId);
    if (el) {
        el.classList.add('bg-emerald-50', 'border-emerald-500', 'ring-2', 'ring-emerald-200');
        setTimeout(() => {
            el.classList.remove('ring-2', 'ring-emerald-200');
        }, 1500);
    }
    const badge = document.getElementById(`badge-${campoId}`);
    if (badge) {
        badge.classList.remove('hidden');
        badge.classList.add('inline-flex');
    }
}

function limpiarInconsistencias() {
    ['monto', 'fecha_pago', 'metodo_pago', 'referencia', 'cuenta_bancaria_id'].forEach(id => {
        const c = document.getElementById(`inconsistencia-${id}`);
        if (c) c.innerHTML = '';
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
