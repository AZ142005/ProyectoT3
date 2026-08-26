<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Carta Oficial de Deuda') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1e293b; background: #f8fafc; }
        .carta-paper { background: #fff; max-width: 800px; margin: 30px auto; padding: 40px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .carta-paper { box-shadow: none; border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Barra de Acciones (No Imprimible) -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 max-w-800 mx-auto bg-white p-3 border rounded-3 shadow-sm" style="max-width: 800px;">
            <a href="/admin/reportes/morosidad" class="btn btn-outline-secondary btn-sm fw-bold">
                &larr; Volver al Reporte
            </a>
            <div class="d-flex gap-2">
                <form method="POST" action="/admin/reportes/enviar-aviso-cobro" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="unidad_id" value="<?= e($unidad['unidad_id']) ?>">
                    <button type="submit" class="btn btn-warning btn-sm fw-bold d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6">mail</span> Enviar por Email
                    </button>
                </form>

                <?php if (!empty($enlaceWhatsapp)): ?>
                    <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6">chat</span> WhatsApp (1-Clic)
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm fw-bold" disabled title="<?= e($analisisTel['mensaje'] ?? 'Número no móvil') ?>">
                        🚫 WhatsApp Incompatible
                    </button>
                <?php endif; ?>

                <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1">
                    <span class="material-symbols-outlined fs-6">print</span> Imprimir / PDF
                </button>
            </div>
        </div>

        <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

        <!-- Documento Papel Carta -->
        <div class="carta-paper">
            <!-- Membrete -->
            <div class="row border-bottom pb-4 mb-4 align-items-center">
                <div class="col-8">
                    <h3 class="fw-bold text-success mb-1">CONJUNTO RESIDENCIAL "LAS MESETAS DE MORÓN"</h3>
                    <p class="small text-muted mb-0">Junta de Condominio & Administración General</p>
                    <p class="small text-muted mb-0">RIF: J-30948572-0 | Morón, Estado Carabobo</p>
                </div>
                <div class="col-4 text-end">
                    <div class="border p-2 rounded bg-light text-center">
                        <small class="text-uppercase text-muted fw-bold d-block">AVISO OFICIAL</small>
                        <span class="font-monospace fw-bold text-danger">COB-<?= date('Ym') ?>-<?= e($unidad['unidad_numero']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Destinatario -->
            <div class="mb-4">
                <p class="mb-1"><strong>Fecha:</strong> Morón, <?= date('d de m de Y') ?></p>
                <p class="mb-1"><strong>Ciudadano(a):</strong> <?= e($unidad['propietario_nombre'] ?: 'Propietario / Residente') ?></p>
                <p class="mb-1"><strong>Cédula de Identidad:</strong> <?= e($unidad['propietario_cedula'] ?: 'N/A') ?></p>
                <p class="mb-0"><strong>Inmueble:</strong> Edificio <?= e($unidad['edificio_nombre']) ?> - Apto/Unidad <?= e($unidad['unidad_numero']) ?></p>
            </div>

            <!-- Cuerpo de la Carta -->
            <div class="mb-4 text-justify" style="line-height: 1.8;">
                <p>Por medio de la presente, la Administración del Conjunto Residencial <strong>"Las Mesetas de Morón"</strong> se dirige a usted para presentarle la relación detallada del estado de cuenta actualizado de su inmueble. A la presente fecha, se registran cuotas de condominio vencidas acumuladas, por lo que le solicitamos formalmente proceder con la regularización del pago:</p>
            </div>

            <!-- Desglose de Facturas -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle text-sm">
                    <thead class="table-light text-uppercase">
                        <tr>
                            <th>Nro. Factura / Cuota</th>
                            <th>Mes / Año</th>
                            <th>Vencimiento</th>
                            <th class="text-center">Días Mora</th>
                            <th class="text-end">Monto Pendiente (Bs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-3 text-success font-weight-bold">
                                    Esta unidad no posee facturas vencidas registradas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $f): ?>
                                <tr>
                                    <td class="font-monospace">FAC-<?= e($f['numero_factura'] ?: $f['id']) ?></td>
                                    <td><?= e($f['mes']) ?>/<?= e($f['anio']) ?></td>
                                    <td><?= e(date('d/m/Y', strtotime($f['fecha_vencimiento']))) ?></td>
                                    <td class="text-center text-danger fw-bold"><?= e($f['dias_mora']) ?> días</td>
                                    <td class="text-end font-monospace fw-bold text-danger">Bs. <?= number_format(floatval($f['saldo']), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-bold fs-6">TOTAL GENERAL ADEUDADO:</td>
                            <td class="text-end font-monospace fw-bold fs-5 text-danger">Bs. <?= number_format(floatval($totalDeuda), 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Instrucciones de Pago -->
            <div class="bg-light p-3 border rounded-3 mb-4 small">
                <h6 class="fw-bold text-dark mb-2">Cuentas Bancarias Autorizadas del Condominio:</h6>
                <p class="mb-1"><strong>Banco Mercantil (Cuenta Corriente):</strong> Nro. 0105-0000-00-0000000000</p>
                <p class="mb-1"><strong>Pago Móvil:</strong> Banco Mercantil (0105) | C.I/RIF: J-30948572-0 | Teléf: 0414-0000000</p>
                <p class="mb-0 text-muted">Una vez realizado el depósito o transferencia, recuerde cargar su comprobante mediante su portal en línea.</p>
            </div>

            <!-- Firmas -->
            <div class="row text-center mt-5 pt-4">
                <div class="col-6">
                    ____________________________________<br>
                    <small class="fw-bold text-dark d-block mt-1">ADMINISTRACIÓN GENERAL</small>
                    <small class="text-muted">Las Mesetas de Morón</small>
                </div>
                <div class="col-6">
                    ____________________________________<br>
                    <small class="fw-bold text-dark d-block mt-1">JUNTA DE CONDOMINIO</small>
                    <small class="text-muted">Firma Autorizada</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
