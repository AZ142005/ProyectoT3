<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($tituloComunicado ?? 'Comunicado del Condominio') ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f0f7f0; color: #2c3e50; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #2980b9; color: #ffffff; padding: 24px; text-align: center; }
        .content { padding: 32px 24px; line-height: 1.6; }
        .badge-info { display: inline-block; background: #d4efdf; color: #196f3d; font-weight: bold; padding: 6px 16px; border-radius: 20px; font-size: 14px; margin-bottom: 16px; }
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
            <span class="badge-info">📢 Comunicado Oficial del Condominio</span>
            <h3 style="margin-top:12px; color:#2c3e50;"><?= e($tituloComunicado ?? '') ?></h3>
            
            <div style="margin:20px 0; text-align:justify; color:#34495e;">
                <?= nl2br(strip_tags($contenidoComunicado ?? '', '<b><strong><i><em><u><ul><ol><li><p><br><a>')) ?>
            </div>

            <p style="font-size:12px; color:#95a5a6; border-top:1px solid #ecf0f1; padding-top:12px;">Publicado el: <?= e($fechaPublicacion ?? date('d/m/Y H:i')) ?></p>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> Conjunto Residencial Las Mesetas de Morón. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
