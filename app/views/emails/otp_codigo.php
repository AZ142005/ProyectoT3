<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Código de Verificación 2FA</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #27ae60; padding: 25px; text-align: center; color: #ffffff;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: bold;">Condominio Las Mesetas de Morón</h1>
                            <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">Seguridad y Autenticación en Dos Pasos (2FA)</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="font-size: 16px; margin-top: 0;">Estimado(a) <strong><?= htmlspecialchars($nombreResidente ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong>,</p>
                            <p style="font-size: 14px; line-height: 1.6; color: #555;">
                                Se ha solicitado un inicio de sesión en su cuenta. Para completar el acceso, ingrese el siguiente código de verificación de 6 dígitos:
                            </p>
                            
                            <div style="background-color: #eafaf1; border: 2px dashed #27ae60; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0;">
                                <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #27ae60; font-family: monospace;">
                                    <?= htmlspecialchars($codigoOtp ?? '000000', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <p style="font-size: 13px; color: #888; margin-bottom: 0;">
                                ⏱️ <strong>Nota de Seguridad:</strong> Este código expira en <strong>5 minutos</strong> y solo permite un máximo de 3 intentos. Si usted no intentó iniciar sesión, ignore este correo o cambie su contraseña de inmediato.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eeeeee;">
                            Condominio Digital &copy; <?= date('Y') ?> — Todos los derechos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
