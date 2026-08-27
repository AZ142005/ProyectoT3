<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Reporte Oficial de Morosidad') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; color: #1e293b; background: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .table-light { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
            .badge { border: 1px solid #000; color: #000 !important; }
        }
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
        <h2 class="fw-bold mb-1">CONJUNTO RESIDENCIAL "LAS MESETAS DE MORÓN"</h2>
        <h5 class="text-secondary fw-semibold mb-2">REPORTE OFICIAL DE DEUDA Y MOROSIDAD ACUMULADA</h5>
        <p class="small text-muted mb-0">Fecha de Emisión: <?= date('d/m/Y H:i:s') ?> | Sistema de Cobranzas y Condominio Digital</p>
    </div>

    <!-- Métricas Resumidas -->
    <div class="row text-center mb-4 g-2">
        <div class="col-4">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Monto Total en Arreos</small>
                <span class="fs-5 fw-bold text-danger"><?= formatearMoneda($kpis['total_deuda']) ?></span>
            </div>
        </div>
        <div class="col-4">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Unidades con Cartera Vencida</small>
                <span class="fs-5 fw-bold text-dark"><?= e($kpis['unidades_morosas']) ?> Unidades</span>
            </div>
        </div>
        <div class="col-4">
            <div class="border p-2 rounded">
                <small class="text-muted text-uppercase d-block fw-bold">Índice de Morosidad Global</small>
                <span class="fs-5 fw-bold text-dark"><?= e($kpis['tasa_morosidad']) ?>%</span>
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
                <th class="text-center">Cuotas Vencidas</th>
                <th class="text-center">Días Mora</th>
                <th class="text-end">Deuda Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($morosos)): ?>
                <tr>
                    <td colspan="9" class="text-center py-4">No se registran unidades morosas para este criterio.</td>
                </tr>
            <?php else: ?>
                <?php $i = 1; foreach ($morosos as $m): ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td><?= e($m['edificio_nombre']) ?></td>
                        <td class="fw-bold">Unidad <?= e($m['unidad_numero']) ?></td>
                        <td><?= e($m['propietario_nombre']) ?></td>
                        <td><?= e($m['propietario_cedula']) ?></td>
                        <td><?= e($m['propietario_telefono']) ?></td>
                        <td class="text-center"><?= e($m['facturas_vencidas']) ?></td>
                        <td class="text-center fw-bold text-danger"><?= e($m['dias_mora_max']) ?> días</td>
                        <td class="text-end font-monospace fw-bold"><?= formatearMoneda($m['total_deuda']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-5 pt-4 border-top d-flex justify-content-between text-center small text-muted">
        <div>
            ____________________________________<br>
            Administración del Condominio
        </div>
        <div>
            ____________________________________<br>
            Auditoría y Junta de Condominio
        </div>
    </div>
</body>
</html>
