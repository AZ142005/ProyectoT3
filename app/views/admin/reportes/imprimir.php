<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Reporte Oficial de Deuda') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; color: #1e293b; background: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .table-light { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
            .badge { border: 1px solid #000; color: #000 !important; }
        }
        /* Botones de barra superior alineados al estilo de Conciliación (R1/R2) */
        .no-print .btn { border-radius: .75rem; font-size: .75rem; line-height: 1rem; font-weight: 700; padding: .625rem 1.25rem; display: inline-flex; align-items: center; gap: .375rem; transition: all .15s ease; }
        .no-print .btn-primary { background-color: #27ae60; border-color: #27ae60; color: #fff; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .no-print .btn-primary:hover { background-color: #1e8449; border-color: #1e8449; color: #fff; }
        .no-print .btn-outline-secondary { background-color: #f1f5f9; border-color: #e2e8f0; color: #334155; }
        .no-print .btn-outline-secondary:hover { background-color: #e2e8f0; border-color: #e2e8f0; color: #334155; }
        .no-print .btn-warning { background-color: #fbbf24; border-color: #fbbf24; color: #451a03; }
        .no-print .btn-warning:hover { background-color: #f59e0b; border-color: #f59e0b; color: #451a03; }
        .no-print .btn-success { background-color: #16a34a; border-color: #16a34a; color: #fff; }
        .no-print .btn-success:hover { background-color: #15803d; border-color: #15803d; color: #fff; }
        .no-print .btn:disabled, .no-print .btn.disabled { background-color: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; }
    </style>
</head>
<body class="p-4">
    <div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 border rounded-3">
        <div>
            <strong>Modo Vista Previa de Impresión</strong> — Presione el botón para imprimir o guardar como PDF.
        </div>
        <button onclick="window.print()" class="btn btn-primary fw-bold">Imprimir / Guardar PDF</button>
    </div>

    <?php if (!empty($truncado)): ?>
    <div class="alert alert-warning no-print mb-3" role="alert">
        ⚠️ <strong>Atención:</strong> Este reporte contiene 5,000 registros (máximo permitido). Algunos morosos podrían no aparecer. Use filtros para reducir el resultado.
    </div>
    <?php endif; ?>

    <!-- Cabecera Oficial -->
    <div class="text-center mb-4 border-bottom pb-3">
        <img src="/img/logo_condominio.png" alt="Conjunto Residencial Las Mesetas" style="height:72px;width:auto;" class="mb-2">
        <h2 class="fw-bold mb-1">CONJUNTO RESIDENCIAL "LAS MESETAS DE MORÓN"</h2>
        <h5 class="text-secondary fw-semibold mb-2">REPORTE OFICIAL DE DEUDA Y ESTADO FINANCIERO DE UNIDADES</h5>
        <p class="small text-muted mb-0">Fecha de Emisión: <?= date('d/m/Y H:i:s') ?> | Sistema de Cobranzas y Conjunto Digital</p>
    </div>

    <!-- Métricas Resumidas -->
    <div class="row text-center mb-4 g-2">
        <div class="col-3">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Cartera Vencida Total</small>
                <span class="fs-5 fw-bold text-danger"><?= formatearMoneda($kpis['total_deuda']) ?></span>
            </div>
        </div>
        <div class="col-3">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Unidades con Deuda</small>
                <span class="fs-5 fw-bold text-dark"><?= e($kpis['unidades_morosas']) ?> Unidades</span>
            </div>
        </div>
        <div class="col-3">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Unidades Solventes</small>
                <span class="fs-5 fw-bold text-success"><?= e($kpis['unidades_solventes'] ?? ($kpis['total_unidades'] - $kpis['unidades_morosas'])) ?> Unidades</span>
            </div>
        </div>
        <div class="col-3">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Solvencia / Morosidad</small>
                <span class="fs-5 fw-bold text-primary"><?= e($kpis['tasa_solvencia'] ?? round((100 - $kpis['tasa_morosidad']), 1)) ?>% <small class="fs-6 text-muted">(Mora: <?= e($kpis['tasa_morosidad']) ?>%)</small></span>
            </div>
        </div>
    </div>

    <!-- Tabla Oficial -->
    <table class="table table-bordered align-middle text-sm">
        <thead class="table-light text-uppercase small">
            <tr>
                <th>#</th>
                <th>Edificio / Torre</th>
                <th>Unidad</th>
                <th>Propietario / Residente</th>
                <th>Cédula</th>
                <th>Contacto</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Cuotas Vencidas</th>
                <th class="text-center">Días Mora</th>
                <th class="text-end">Deuda Total (Bs)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($morosos)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4">No se registran unidades para este criterio.</td>
                </tr>
            <?php else: ?>
                <?php $i = 1; foreach ($morosos as $m): ?>
                    <?php $esSolvente = ($m['estado_financiero'] ?? 'solvente') === 'solvente'; ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td><?= e($m['edificio_nombre']) ?></td>
                        <td class="fw-bold">Unidad <?= e($m['unidad_numero']) ?></td>
                        <td><?= e($m['propietario_nombre']) ?></td>
                        <td><?= e($m['propietario_cedula']) ?></td>
                        <td><?= e($m['propietario_telefono']) ?></td>
                        <td class="text-center fw-bold <?= $esSolvente ? 'text-success' : 'text-danger' ?>">
                            <?= $esSolvente ? 'Solvente' : 'Con Deuda' ?>
                        </td>
                        <td class="text-center"><?= e($m['facturas_vencidas']) ?></td>
                        <td class="text-center fw-bold <?= $esSolvente ? 'text-success' : 'text-danger' ?>">
                            <?= $esSolvente ? 'Al día' : e($m['dias_mora_max']) . ' días' ?>
                        </td>
                        <td class="text-end font-monospace fw-bold <?= $esSolvente ? 'text-muted' : 'text-danger' ?>"><?= formatearMoneda($m['total_deuda']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-5 pt-4 border-top d-flex justify-content-between text-center small text-muted">
        <div>
            ____________________________________<br>
            Administración del Conjunto
        </div>
        <div>
            ____________________________________<br>
            Auditoría y Junta de Conjunto
        </div>
    </div>
</body>
</html>
