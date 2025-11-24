<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de correo | Decora10</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background-color: #000000; /* negro */
            padding: 20px;
            text-align: center;
        }
        .header img {
            max-width: 150px;
        }
        .content {
            padding: 30px;
            text-align: center;
        }
        .content h1 {
            color: #000000;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            color: #555555;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: #ff6600; /* naranja */
            color: #ffffff; /* blanco */
            text-decoration: none;
            font-size: 16px;
            border-radius: 30px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #e65c00;
        }
        .footer {
            background-color: #000000;
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #ffffff;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="{{ asset('storage/DECORA10.png') }}" alt="Decora10" style="color: #735c0f">
    </div>
    <div class="content">
        <h1>¡Hola {{ $user->name }}!</h1>
        <p>Gracias por registrarte en <strong>Decora10</strong>. Para completar tu registro y acceder a nuestra tienda de muebles y decoración, verifica tu correo haciendo clic en el botón a continuación:</p>
        <a href="http://localhost:5173/" class="btn">Verificar mi correo</a>
        <p>Si no creaste esta cuenta, puedes ignorar este mensaje.</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Decora10. Todos los derechos reservados.
    </div>
</div>
</body>
</html>
