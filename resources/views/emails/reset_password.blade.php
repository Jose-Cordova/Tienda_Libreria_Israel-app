<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recuperación de contraseña</title>
</head>
<body>
    <h2>Hola {{ $userName }},</h2>
    <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>
    <p>Para crear una nueva contraseña, haz clic en el siguiente enlace (válido hasta {{ $expires }}):</p>
    <p><a href="{{ $link }}">{{ $link }}</a></p>
    <p>Si no solicitaste este cambio, ignora este mensaje.</p>
</body>
</html>
