<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invitación al sistema</title>
</head>
<body>
    <h2>Hola {{ $userName }},</h2>
    <p>Has sido invitado a formar parte del sistema de la Tienda y Librería Israel.</p>
    <p>Para establecer tu contraseña y activar tu cuenta, haz clic en el siguiente enlace (válido hasta {{ $expires }}):</p>
    <p><a href="{{ $link }}">{{ $link }}</a></p>
    <p>Si no solicitaste esta invitación, ignora este correo.</p>
</body>
</html>
