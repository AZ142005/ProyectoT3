<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Rechazado</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f0f7f0; color: #2c3e50; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #e74c3c; color: #ffffff; padding: 24px; text-align: center; }
        .content { padding: 32px 24px; line-height: 1.6; }
        .badge-danger { display: inline-block; background: #fadbd8; color: #78281f; font-weight: bold; padding: 6px 16px; border-radius: 20px; font-size: 14px; margin-bottom: 16px; }
        .motivo-box { background: #fdf2e9; border-left: 4px solid #e67e22; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .footer { background: #1a252f; color: rgba(255,255,255,0.6); padding: 16px; text-align: center; font-size: 12px; }
        .btn-action { display: inline-block; background: #27ae60; color: #ffffff; font-weight: bold; text-decoration: none; padding: 10px 20px; border-radius: 8px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0;">Condominio Digital</h2>
            <p style="margin:4px 0 0 0; font-size:14px; opacity:0.9;">Las Mesetas de Morón</p>
        </div>
        <div class="content">
            <span class="badge-danger">✖ Comprobante de Pago Rechazado</span>
            <p>Estimado(a) residente <strong><?= e($nombreResidente ?? 'Residente') ?></strong>,</p>
            <p>Lamentamos informarle que su comprobante de pago por el monto de <strong>Bs. <?= e(number_format(floatval($monto ?? 0), 2)) ?></strong> (Ref: <?= e($referencia ?? 'N/A') ?>) no ha sido validado por la administración.</p>
            
            <div class="motivo-box">
                <p style="margin:0 0 6px 0; font-weight:bold; color:#d35400;">Motivo del Rechazo:</p>
                <p style="margin:0; font-style:italic; text-align:justify;"><?= nl2br(e($motivoRechazo ?? 'No especificado')) ?></p>
            </div>

            <p>Le invitamos a verificar la información de la transferencia y subir un nuevo comprobante correcto ingresando a la plataforma.</p>

            <div style="text-align: center;">
                <a href="<?= e($appUrl ?? '#') ?>/pagos/subir" class="btn-action">📤 Volver a Subir Comprobante</a>
            </div>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> Conjunto Residencial Las Mesetas de Morón. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
