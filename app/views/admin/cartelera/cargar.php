<div class="flex flex-1 min-h-screen w-full">
    <?php
    $activeRoute = 'gastos';
    if (\App\Core\Auth::role() === 'auditor') {
        require VIEWS_PATH . '/layouts/auditor_sidebar.php';
    } else {
        require VIEWS_PATH . '/layouts/admin_sidebar.php';
    }
    ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="d-flex align-items-center gap-2">
                    <a href="/admin/gastos" class="text-decoration-none text-muted d-flex align-items-center">
                        <span class="material-symbols-outlined fs-5">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-on-surface mb-0">Carga del Reporte de Cartelera</h1>
                </div>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- Encabezado Informativo -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Importación de Deuda por Unidad (Dotwood PC Condominios)</h4>
                        <p class="text-muted small mb-0">
                            Extrae del Reporte de Cartelera el Saldo Anterior, las cuotas por mes y la Deuda Total de cada unidad.
                            La importación completa la estructura faltante (edificios/unidades), es idempotente y queda auditada.
                        </p>
                    </div>
                    <a href="/admin/gastos" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">list</span>
                        <span>Volver a Gastos</span>
                    </a>
                </div>

                <!-- Tarjeta de Carga -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="text-lg font-bold text-on-surface mb-0 d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined text-primary">upload_file</span>
                            <span>1. Subir el Reporte de Cartelera en PDF</span>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="/admin/cartelera/parsear" method="POST" enctype="multipart/form-data" id="formParsearCartelera">
                            <?= csrf_field() ?>

                            <div class="border-2 border-dashed border-slate-300 rounded-3 p-4 bg-light text-center">
                                <span class="material-symbols-outlined text-primary fs-1 mb-2 d-block">picture_as_pdf</span>
                                <p class="text-dark fw-bold mb-1">Seleccione el PDF del Reporte de Cartelera</p>
                                <p class="text-muted small mb-3">Soporta el reporte consolidado de Dotwood (Crystal Reports) con deuda por unidad. Máx. 10MB.</p>
                                <input type="file" name="pdf_cartelera" id="pdf_cartelera" class="form-control mx-auto" style="max-width: 450px;" accept="application/pdf" required>
                            </div>

                            <div id="carteleraError" class="d-none alert alert-danger mt-3 mb-0" role="alert"></div>

                            <div class="mt-4 text-end">
                                <button type="submit" id="btnAnalizarCartelera" class="btn btn-primary fw-bold d-inline-flex align-items-center gap-1 px-4 py-2 shadow-sm">
                                    <span class="material-symbols-outlined fs-6">auto_awesome</span>
                                    <span>Analizar y Previsualizar</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Previsualización y Confirmación -->
                <?php if (!empty($filas) && !empty($meta)): ?>
                    <?php
                    $meses = is_array($meta['meses'] ?? null) ? $meta['meses'] : [];
                    $totales = is_array($meta['totales'] ?? null) ? $meta['totales'] : [];
                    $partesConteo = [];
                    foreach (($meta['conteo_por_edificio'] ?? []) as $edificio => $cantidad) {
                        $partesConteo[] = "Torre {$edificio}: {$cantidad}";
                    }
                    ?>
                    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-success">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="text-lg font-bold text-on-surface mb-0 d-flex align-items-center gap-2">
                                    <span class="material-symbols-outlined text-success">fact_check</span>
                                    <span>2. Previsualización del Reporte (<?= e(count($filas)) ?> unidades con deuda)</span>
                                </h5>
                                <small class="text-xs text-on-surface-variant d-block">
                                    Archivo: <strong><?= e($meta['archivo'] ?? '') ?></strong> |
                                    Emisión: <strong><?= e($meta['fecha_emision'] ?? '') ?></strong> |
                                    Páginas: <strong><?= e($meta['paginas'] ?? '') ?></strong> |
                                    Validación: <strong class="text-success">✓ sumas por fila y totales verificados</strong>
                                </small>
                                <?php if (!empty($partesConteo)): ?>
                                    <small class="text-xs text-on-surface-variant d-block">Unidades por edificio: <?= e(implode(' · ', $partesConteo)) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success-subtle text-success fs-6 fw-bold px-3 py-2 border border-success-subtle">
                                    Deuda Total: <?= e(formatearMoneda($totales['total'] ?? 0)) ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <form action="/admin/cartelera/importar" method="POST" id="formImportarCartelera">
                                <?= csrf_field() ?>

                                <div class="table-responsive" style="max-height: 65vh; overflow: auto;">
                                    <table class="w-full text-left text-sm border-collapse">
                                        <thead class="sticky top-0 bg-white">
                                            <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                                <th class="py-3 px-4">Inmueble</th>
                                                <th class="py-3 px-4 text-end">S. Anterior</th>
                                                <?php foreach ($meses as $mesNum): ?>
                                                    <th class="py-3 px-4 text-end"><?= e(nombreMes($mesNum)) ?></th>
                                                <?php endforeach; ?>
                                                <th class="py-3 px-4 text-end">Deuda Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($filas as $fila): ?>
                                                <tr class="border-b border-slate-100">
                                                    <td class="px-4 py-2 fw-bold text-on-surface"><?= e($fila['unidad'] ?? '') ?></td>
                                                    <td class="px-4 py-2 text-end font-monospace">
                                                        <?php $saldoAnterior = (float)($fila['saldo_anterior'] ?? 0); ?>
                                                        <?= $saldoAnterior > 0 ? e(formatearMonto($saldoAnterior)) : '–' ?>
                                                    </td>
                                                    <?php foreach ($meses as $mesNum): ?>
                                                        <td class="px-4 py-2 text-end font-monospace">
                                                            <?php $montoMes = $fila['meses'][$mesNum] ?? null; ?>
                                                            <?= $montoMes !== null ? e(formatearMonto($montoMes)) : '–' ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td class="px-4 py-2 text-end font-monospace fw-bold"><?= e(formatearMonto($fila['total'] ?? 0)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-slate-50 font-bold border-t-2 border-slate-200">
                                                <td class="px-4 py-3">Totales</td>
                                                <td class="px-4 py-3 text-end font-monospace"><?= e(formatearMonto($totales['saldo_anterior'] ?? 0)) ?></td>
                                                <?php foreach ($meses as $mesNum): ?>
                                                    <td class="px-4 py-3 text-end font-monospace"><?= e(formatearMonto($totales['meses'][$mesNum] ?? 0)) ?></td>
                                                <?php endforeach; ?>
                                                <td class="px-4 py-3 text-end font-monospace text-success"><?= e(formatearMonto($totales['total'] ?? 0)) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <a href="/admin/cartelera?descartar=1" class="btn btn-outline-secondary btn-sm fw-bold">
                                        Limpiar y Volver a Subir
                                    </a>
                                    <button type="submit" class="btn btn-success fw-bold d-inline-flex align-items-center gap-1 shadow-sm px-4 py-2">
                                        <span class="material-symbols-outlined fs-6">check_circle</span>
                                        <span>Confirmar e Importar al Sistema</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formParsearCartelera');
    if (!form) { return; }

    var boton = document.getElementById('btnAnalizarCartelera');
    var errorBox = document.getElementById('carteleraError');

    form.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        boton.disabled = true;
        boton.classList.add('opacity-60');

        try {
            var fd = new FormData(form);
            fd.append('ajax', '1');
            var resp = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            var data = null;
            try { data = await resp.json(); } catch (e) { data = null; }

            // El token CSRF rota tras cada POST válido: mantener el formulario al día
            if (data && data.csrf_token) {
                var input = form.querySelector('input[name="csrf_token"]');
                if (input) { input.value = data.csrf_token; }
            }

            if (resp.ok && data && data.success) {
                window.location.href = '/admin/cartelera';
                return;
            }

            var detalle = (data && data.errores && data.errores.length)
                ? data.errores.join(' ')
                : ((data && data.message) ? data.message : 'No se pudo procesar el PDF.');
            errorBox.textContent = detalle;
            errorBox.classList.remove('d-none');
        } catch (e) {
            errorBox.textContent = 'Error de red al enviar el archivo. Intente nuevamente.';
            errorBox.classList.remove('d-none');
        } finally {
            boton.disabled = false;
            boton.classList.remove('opacity-60');
        }
    });
});
</script>
