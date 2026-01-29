<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f7e6; padding: 40px 20px; margin: 0; }
        .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .logo { text-align: center; margin-bottom: 24px; color: #2E7D32; font-size: 24px; font-weight: bold; }
        h2 { color: #2C3E50; font-size: 20px; margin-bottom: 16px; }
        p { color: #4a5568; font-size: 14px; line-height: 1.6; }
        .btn { display: inline-block; background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; margin: 24px 0; }
        .footer { margin-top: 32px; font-size: 12px; color: #718096; text-align: center; }
        .warning { font-size: 12px; color: #718096; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">AgriFlor</div>
        <h2>Restablecer Contrase&ntilde;a</h2>
        <p>Hola {{ $userName }},</p>
        <p>Recibimos una solicitud para restablecer la contrase&ntilde;a de su cuenta. Haga clic en el siguiente bot&oacute;n para crear una nueva contrase&ntilde;a:</p>
        <p style="text-align: center;">
            <a href="{{ $resetUrl }}" class="btn">Restablecer Contrase&ntilde;a</a>
        </p>
        <p class="warning">Este enlace expirar&aacute; en 60 minutos. Si no solicit&oacute; este cambio, puede ignorar este correo.</p>
        <div class="footer">
            &copy; {{ date('Y') }} AgriFlor - Sistema de Gesti&oacute;n Agr&iacute;cola
        </div>
    </div>
</body>
</html>
