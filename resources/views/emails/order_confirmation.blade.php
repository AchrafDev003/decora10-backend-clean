<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Pedido</title>
    <style>
        body {
            font-family: 'Arial', Helvetica, sans-serif;
            background-color: #111;
            color: #fff;
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #1a1a1a;
            padding: 30px 40px;
            border-radius: 12px;
            border-top: 6px solid #ff7a00;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }
        h2 {
            color: #ff7a00;
            margin-bottom: 20px;
            font-size: 28px;
        }
        p {
            margin: 12px 0;
            font-size: 16px;
            color: #ddd;
        }
        .order-box {
            background: #2a2a2a;
            padding: 20px;
            border-left: 6px solid #ff7a00;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 15px;
        }
        .order-box strong {
            color: #fff;
        }
        .status {
            color: #ff7a00;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #aaa;
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 15px;
        }
        a {
            color: #ff7a00;
            text-decoration: none;
            font-weight: bold;
        }
        /* Botón opcional para contacto rápido */
        .btn-contact {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #ff7a00;
            color: #111;
            text-decoration: none;
            font-weight: bold;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .btn-contact:hover {
            background-color: #ff9500;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>¡Gracias por tu pedido, {{ $order->user->name }}!</h2>

    <p>
        Hemos recibido correctamente tu pedido y ya se encuentra en proceso de preparación.
    </p>

    <div class="order-box">
        <strong>Número de seguimiento:</strong> #{{ $order->tracking_number }}<br>
        <strong>Estado:</strong> <span class="status">En preparación</span>
    </div>

    <p>
        Te notificaremos por correo electrónico cuando tu pedido sea enviado.
    </p>

    <p>
        Si tienes cualquier duda o necesitas asistencia, nuestro equipo estará encantado de ayudarte.
    </p>

    <a href="mailto:decora10.colchon10@gmail.com" class="btn-contact">Contactar Soporte</a>

    <div class="footer">
        <p>Este correo ha sido enviado automáticamente por <strong>Decora10</strong>.</p>
        <p>Contacto: <a href="mailto:decora10.colchon10@gmail.com">soporte@decora10.com</a></p>
    </div>

</div>

</body>
</html>
