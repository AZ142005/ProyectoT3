<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Aprobado</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f0f7f0; color: #2c3e50; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #27ae60; color: #ffffff; padding: 24px; text-align: center; }
        .content { padding: 32px 24px; line-height: 1.6; }
        .badge-success { display: inline-block; background: #d5f5e3; color: #1e8449; font-weight: bold; padding: 6px 16px; border-radius: 20px; font-size: 14px; margin-bottom: 16px; }
        .details { background: #f8faf9; border-left: 4px solid #27ae60; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .footer { background: #1a252f; color: rgba(255,255,255,0.6); padding: 16px; text-align: center; font-size: 12px; }
        .btn-whatsapp { display: inline-block; background: #25D366; color: #ffffff; font-weight: bold; text-decoration: none; padding: 10px 20px; border-radius: 8px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0;">Condominio Digital</h2>
            <p style="margin:4px 0 0 0; font-size:14px; opacity:0.9;">Las Mesetas de Morón</p>
        </div>
        <div class="content">
            <span class="badge-success">✔ ¡Comprobante de Pago Aprobado!</span>
            <p>Estimado(a) residente <strong><?= e($nombreResidente ?? 'Residente') ?></strong>,</p>
            <p>Le notificamos que su comprobante de pago por el monto de <strong>Bs. <?= e(number_format(floatval($monto ?? 0), 2)) ?></strong> ha sido verificado y aprobado satisfactoriamente por la administración.</p>
            
            <div class="details">
                <p style="margin:4px 0;"><strong>Referencia:</strong> <?= e($referencia ?? 'N/A') ?></p>
                <p style="margin:4px 0;"><strong>Fecha de Pago:</strong> <?= e($fechaPago ?? date('d/m/Y')) ?></p>
                <p style="margin:4px 0;"><strong>Estado Actual:</strong> <span style="color:#27ae60; font-weight:bold;">APROBADO</span></p>
            </div>

            <?php if (!empty($enlaceWhatsapp)): ?>
                <div style="text-align: center;">
                    <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" class="btn-whatsapp">📲 Abrir Confirmación por WhatsApp</a>
                </div>
            <?php endif; ?>

            <p style="margin-top:24px; font-size:13px; color:#7f8c8d;">Gracias por mantener al día sus obligaciones con la comunidad.</p>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> Conjunto Residencial Las Mesetas de Morón. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
