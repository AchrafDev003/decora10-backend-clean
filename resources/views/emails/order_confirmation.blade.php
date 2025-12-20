<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Pedido</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            line-height: 1.6;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 6px;
            border-top: 5px solid #ff7a00;
        }
        h2 {
            color: #000000;
            margin-bottom: 20px;
        }
        p {
            margin: 12px 0;
        }
        .order-box {
            background: #fafafa;
            padding: 15px;
            border-left: 4px solid #ff7a00;
            margin: 20px 0;
            font-size: 14px;
        }
        .order-box strong {
            color: #000000;
        }
        .status {
            color: #ff7a00;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #666;
            text-align: center;
            border-top: 1px solid #e5e5e5;
            padding-top: 15px;
        }
        a {
            color: #ff7a00;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Gracias por tu pedido, {{ $order->user->name }}</h2>

    <p>
        Hemos recibido correctamente tu pedido y ya se encuentra en proceso de preparación.
    </p>

    <div class="order-box">
        <strong>Número de pedido:</strong> #{{ $order->tracking_number }}<br>
        <strong>Estado:</strong> <span class="status">En preparación</span>
    </div>

    <p>
        Te notificaremos por correo electrónico cuando tu pedido sea enviado.
    </p>

    <p>
        Si tienes cualquier duda o necesitas asistencia, nuestro equipo estará encantado de ayudarte.
    </p>

    <div class="footer">
        <p>
            Este correo ha sido enviado automáticamente por <strong>Decora10</strong>.
        </p>
        <p>
            Contacto: <a href="mailto:decora10.colchon10@gmail.com">soporte@decora10.com</a>
        </p>
    </div>

</div>

</body>
</html>
