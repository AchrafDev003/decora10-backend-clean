<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Pedido</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            padding: 20px;
        }
        h2 {
            color: #4CAF50;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>

<h2>¡Gracias por tu pedido, {{ $order->user->name }}!</h2>

<p>Tu pedido con número <strong>#{{ $order->tracking_number }}</strong> ha sido recibido correctamente.</p>

<p>Adjunto encontrarás tu factura en formato PDF.</p>

<p>Si tienes alguna pregunta o necesitas ayuda con tu pedido, no dudes en contactarnos.</p>

<div class="footer">
    <p>Este correo ha sido enviado automáticamente por <strong>Decora10</strong>.</p>
    <p>Contacto: <a href="mailto:soporte@decora10.com">soporte@decora10.com</a></p>
</div>

</body>
</html>
