<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de Deuda</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f0f7f0; color: #2c3e50; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #d35400; color: #ffffff; padding: 24px; text-align: center; }
        .content { padding: 32px 24px; line-height: 1.6; }
        .badge-warning { display: inline-block; background: #fdebd0; color: #b9770e; font-weight: bold; padding: 6px 16px; border-radius: 20px; font-size: 14px; margin-bottom: 16px; }
        .table-deuda { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .table-deuda th, .table-deuda td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
        .table-deuda th { background-color: #f8fafc; }
        .footer { background: #1a252f; color: rgba(255,255,255,0.6); padding: 16px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0;">Condominio Digital</h2>
            <p style="margin:4px 0 0 0; font-size:14px; opacity:0.9;">Las Mesetas de Morón</p>
        </div>
        <div class="content">
            <span class="badge-warning">⚠️ Aviso Oficial de Deuda Vencida</span>
            <p>Estimado(a) residente <strong><?= e($nombrePropietario ?? 'Propietario') ?></strong>,</p>
            <p>Le notificamos que la unidad <strong>Apto/Unidad <?= e($numeroUnidad ?? '') ?></strong> (<?= e($nombreEdificio ?? '') ?>) presenta las siguientes cuotas de condominio pendientes de pago:</p>
            
            <table class="table-deuda">
                <thead>
                    <tr>
                        <th>Concepto / Mes</th>
                        <th>Vencimiento</th>
                        <th>Monto Deuda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($facturas)): ?>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td><?= e($f['descripcion'] ?? 'Cuota de Condominio') ?></td>
                                <td><?= e($f['fecha_vencimiento'] ?? '') ?></td>
                                <td style="font-weight:bold; color:#c0392b;">Bs. <?= number_format(floatval($f['saldo'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="font-size: 16px; font-weight: bold; text-align: right;">Total Acumulado Deuda: <span style="color:#c0392b;">Bs. <?= number_format(floatval($totalDeuda ?? 0), 2) ?></span></p>

            <p style="font-size:13px; color:#7f8c8d;">Le agradecemos cancelar o ponerse en contacto con la administración a la brevedad para evitar recargos o acciones administrativas.</p>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> Conjunto Residencial Las Mesetas de Morón. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
