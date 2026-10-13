<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carta de Deuda</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        .header { background: #ffffff; border-bottom: 2px solid #27ae60; padding: 24px; text-align: left; }
        .header h3 { margin: 0 0 4px 0; color: #27ae60; font-size: 18px; font-weight: bold; }
        .header p { margin: 0; font-size: 12px; color: #64748b; }
        .content { padding: 28px 24px; line-height: 1.6; font-size: 14px; }
        .content p { margin: 0 0 14px 0; }
        .saldo { font-weight: bold; color: #dc2626; }
        .footer { background: #0f172a; color: rgba(255,255,255,0.7); padding: 16px; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3>CONDOMINIO CONJUNTO LAS MESETAS</h3>
            <p>RIF. J-400577870</p>
            <p>Av. Ppal. de Morón. Oficina Administrativa &bull; Urb. Las Mesetas. Teléf. 0271-2259170 - Valera, Edo. Trujillo</p>
        </div>

        <div class="content">
            <p>Estimado(a) <?= e($nombrePropietario ?? 'Propietario') ?>,</p>

            <p>Le notificamos que la unidad <strong><?= e($numeroUnidad ?? '') ?></strong> (<?= e($nombreEdificio ?? 'Sin Torre') ?>) mantiene un saldo pendiente de <span class="saldo"><?= e(formatearMoneda($totalDeuda ?? 0)) ?></span>.</p>

            <p>Adjuntamos la Carta de Deuda oficial en formato PDF, con el detalle mensual completo, para su revisión y pago.</p>

            <p>Agradecemos su atención y le exhortamos a regularizar su situación a la brevedad posible.</p>
        </div>

        <div class="footer">
            <p style="margin:0;">Este es un mensaje automático; por favor no responda a este correo.</p>
        </div>
    </div>
</body>
</html>
