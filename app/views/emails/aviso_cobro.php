<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carta de Cobro Oficial</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        .header { background: #ffffff; border-bottom: 2px solid #27ae60; padding: 24px; text-align: left; }
        .header h3 { margin: 0 0 4px 0; color: #27ae60; font-size: 18px; font-weight: bold; }
        .header p { margin: 0; font-size: 12px; color: #64748b; }
        .aviso-badge { display: inline-block; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; border-radius: 6px; padding: 6px 12px; font-family: monospace; font-weight: bold; font-size: 12px; margin-top: 10px; }
        .content { padding: 28px 24px; line-height: 1.6; }
        .title-carta { text-align: center; margin: 10px 0 24px 0; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .title-carta h4 { margin: 0; color: #0f172a; font-size: 16px; font-weight: bold; letter-spacing: 0.5px; }
        .title-carta span { font-size: 12px; color: #64748b; }
        .info-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px; }
        .info-card p { margin: 4px 0; }
        .table-deuda { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        .table-deuda th, .table-deuda td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
        .table-deuda th { background-color: #f1f5f9; color: #334155; font-size: 12px; text-transform: uppercase; }
        .total-row { background-color: #fef2f2; font-weight: bold; }
        .firmas { margin-top: 32px; padding-top: 24px; border-top: 1px dashed #cbd5e1; text-align: center; font-size: 12px; }
        .firmas-col { display: inline-block; width: 48%; vertical-align: top; }
        .footer { background: #0f172a; color: rgba(255,255,255,0.7); padding: 16px; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3>CONJUNTO RESIDENCIAL "LAS MESETAS DE MORÓN"</h3>
            <p>Junta de Condominio & Administración General</p>
            <p>RIF: J-30948572-0 | Morón, Estado Trujillo</p>
            <div class="aviso-badge">
                AVISO OFICIAL: COB-<?= date('Ym') ?>-<?= e($numeroUnidad ?? '') ?>
            </div>
        </div>

        <div class="content">
            <div class="title-carta">
                <h4>CARTA DE COBRO / RECORDATORIO DE MOROSIDAD</h4>
                <span>Documento Formal de Cobranzas del Condominio</span>
            </div>

            <div class="info-card">
                <p><strong>Destinatario:</strong> <?= e($nombrePropietario ?? 'Propietario') ?> <?= !empty($cedulaPropietario) ? '(C.I: ' . e($cedulaPropietario) . ')' : '' ?></p>
                <p><strong>Inmueble / Unidad:</strong> Apto/Unidad <?= e($numeroUnidad ?? '') ?> (<?= e($nombreEdificio ?? 'Sin Torre') ?>)</p>
                <p><strong>Fecha de Emisión:</strong> <?= date('d/m/Y') ?></p>
            </div>

            <p style="font-size: 13px; margin-bottom: 12px;">Estimado(a) propietario, por medio de la presente comunicación formal se le notifica el balance deudor y las cuotas vencidas acumuladas correspondientes a su unidad habitacional:</p>

            <table class="table-deuda">
                <thead>
                    <tr>
                        <th>Concepto / Mes</th>
                        <th>Vencimiento</th>
                        <th style="text-align:center;">Días Vencido</th>
                        <th style="text-align:right;">Monto Adeudado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($facturas)): ?>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td><?= e($f['descripcion'] ?? 'Cuota de Condominio') ?></td>
                                <td><?= e($f['fecha_vencimiento'] ?? '') ?></td>
                                <td style="text-align:center;"><?= e($f['dias_vencido'] ?? 0) ?> días</td>
                                <td style="text-align:right; font-weight:bold; color:#dc2626;">Bs. <?= number_format(floatval($f['saldo'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td colspan="3" style="text-align:right; font-weight:bold;">TOTAL GENERAL ADEUDADO:</td>
                        <td style="text-align:right; font-weight:bold; color:#dc2626; font-size:14px;">Bs. <?= number_format(floatval($totalDeuda ?? 0), 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <p style="font-size:13px; color:#475569; margin-top:20px;">
                Le exhortamos cordialmente a regularizar su situación de pago a la brevedad posible y reportar su comprobante a través de la plataforma en línea para evitar recargos o medidas administrativas.
            </p>

            <div class="firmas">
                <div class="firmas-col">
                    ____________________________________<br>
                    <strong>ADMINISTRACIÓN GENERAL</strong><br>
                    <span style="color:#64748b;">Las Mesetas de Morón</span>
                </div>
                <div class="firmas-col">
                    ____________________________________<br>
                    <strong>JUNTA DE CONDOMINIO</strong><br>
                    <span style="color:#64748b;">Firma Autorizada</span>
                </div>
            </div>
        </div>

        <div class="footer">
            &copy; <?= date('Y') ?> Conjunto Residencial Las Mesetas de Morón &bull; Sistema de Gestión de Cobranzas
        </div>
    </div>
</body>
</html>
