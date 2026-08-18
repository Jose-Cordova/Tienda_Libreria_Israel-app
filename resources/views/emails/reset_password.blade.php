<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
    <style>
        @media only screen and (max-width: 640px) {
            .outer-padding {
                padding: 20px 10px !important;
            }
            .container {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
            .inner-scroll {
                height: auto !important;
                max-height: none !important;
                overflow-y: visible !important;
            }
            .content-padding {
                padding: 20px 20px !important;
            }
            .header-padding {
                padding: 20px 20px !important;
            }
            .footer-padding {
                padding: 15px 20px !important;
            }
            .btn {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                box-sizing: border-box !important;
            }
            .title {
                font-size: 18px !important;
            }
            .subtitle {
                font-size: 13px !important;
            }
            .text {
                font-size: 14px !important;
            }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f7f6; font-family:Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" class="outer-padding" style="padding:40px 15px;">
                <table role="presentation" class="container" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="padding:0;">
                            <div class="inner-scroll" style="height:400px; overflow-y:auto; -webkit-overflow-scrolling:touch;">

                                <!-- Encabezado -->
                                <div class="header-padding" style="background-color:#0a3622; padding:30px 40px; text-align:center;">
                                    <h1 class="title" style="color:#ffffff; font-size:22px; font-weight:bold; margin:0;">Tienda y Librería Israel</h1>
                                    <p class="subtitle" style="color:#c6e5d3; font-size:14px; margin:8px 0 0;">Recuperación de contraseña</p>
                                </div>

                                <!-- Cuerpo -->
                                <div class="content-padding" style="padding:35px 40px;">
                                    <h2 class="title" style="color:#0a3622; font-size:20px; margin:0 0 20px;">Hola {{ $userName }},</h2>

                                    <p class="text" style="color:#333333; font-size:15px; line-height:1.6; margin:0 0 20px;">
                                        Hemos recibido una solicitud para restablecer tu contraseña.
                                    </p>

                                    <p class="text" style="color:#333333; font-size:15px; line-height:1.6; margin:0 0 30px;">
                                        Para crear una nueva contraseña, hacé clic en el siguiente botón.
                                        <br>
                                        <small style="color:#888888;">Este enlace es válido hasta {{ $expires }}.</small>
                                    </p>

                                    <div align="center" style="padding-bottom:30px;">
                                        <a href="{{ $link }}" class="btn" style="display:inline-block; background-color:#0a3622; color:#ffffff; text-decoration:none; padding:14px 35px; border-radius:6px; font-size:16px; font-weight:bold;">
                                            Restablecer contraseña
                                        </a>
                                    </div>

                                    <p class="text" style="color:#555555; font-size:13px; line-height:1.5; margin:0;">
                                        Si no solicitaste este cambio, ignorá este mensaje.
                                    </p>
                                </div>

                                <!-- Pie -->
                                <div class="footer-padding" style="background-color:#f0f5f0; padding:20px 40px; text-align:center; border-top:1px solid #c6e5d3;">
                                    <p style="color:#777777; font-size:12px; margin:0;">
                                        © {{ date('Y') }} Tienda y Librería Israel. Todos los derechos reservados.
                                    </p>
                                </div>

                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
