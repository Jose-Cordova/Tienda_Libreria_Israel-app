<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación al sistema</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7f6; font-family:Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:40px 15px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

                    <!-- Encabezado -->
                    <tr>
                        <td style="background-color:#0a3622; padding:30px 40px; text-align:center;">
                            <h1 style="color:#ffffff; font-size:22px; font-weight:bold; margin:0;">Tienda y Librería Israel</h1>
                            <p style="color:#c6e5d3; font-size:14px; margin:8px 0 0;">Invitación al sistema</p>
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:35px 40px;">
                            <h2 style="color:#0a3622; font-size:20px; margin:0 0 20px;">¡Hola {{ $userName }}!</h2>

                            <p style="color:#333333; font-size:15px; line-height:1.6; margin:0 0 20px;">
                                Has sido invitado a formar parte del sistema de la <strong>Tienda y Librería Israel</strong>.
                            </p>

                            <p style="color:#333333; font-size:15px; line-height:1.6; margin:0 0 30px;">
                                Para establecer tu contraseña y activar tu cuenta, hacé clic en el siguiente botón.
                                <br>
                                <small style="color:#888888;">Este enlace es válido hasta {{ $expires }}.</small>
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom:30px;">
                                        <a href="{{ $link }}" style="display:inline-block; background-color:#0a3622; color:#ffffff; text-decoration:none; padding:14px 35px; border-radius:6px; font-size:16px; font-weight:bold;">
                                            Activar mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color:#555555; font-size:13px; line-height:1.5; margin:0;">
                                Si no solicitaste esta invitación, ignorá este correo.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="background-color:#f0f5f0; padding:20px 40px; text-align:center; border-top:1px solid #c6e5d3;">
                            <p style="color:#777777; font-size:12px; margin:0;">
                                © {{ date('Y') }} Tienda y Librería Israel. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
