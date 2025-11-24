<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #{{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { max-height: 80px; }
        .details, .summary, .footer { width: 100%; margin-top: 20px; }
        .details td, .summary th, .summary td { padding: 6px; border: 1px solid #ccc; border-collapse: collapse; }
        .summary th { background-color: #f0f0f0; text-align: left; }
        .total { text-align: right; font-weight: bold; }
        .footer p { margin-top: 40px; font-size: 10px; text-align: center; color: #888; }
    </style>
</head>
<body>

<div class="header">
    {{-- Usa una ruta absoluta válida para DomPDF --}}
    <img src="{{ public_path('images/decora10.png') }}" alt="Decora10" class="logo">
    <h2>Factura de compra</h2>
</div>

<table class="details">
    <tr>
        <td><strong>Cliente:</strong> {{ $user->name }}</td>
        <td><strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td><strong>Correo:</strong> {{ $user->email }}</td>
        <td><strong>ID Pedido:</strong> #{{ $order->id }}</td>
    </tr>
    <tr>
        <td colspan="2"><strong>Dirección de envío:</strong> {{ $order->shipping_address }}</td>
    </tr>
</table>

<h3>Resumen del pedido</h3>
<table class="summary">
    <thead>
    <tr>
        <th>Producto</th>
        <th>Precio unitario</th>
        <th>Cantidad</th>
        <th>Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($summary as $item)
        <tr>
            <td>{{ $item['product_name'] }}</td>
            <td>{{ number_format($item['unit_price'], 2) }} €</td>
            <td>{{ $item['quantity'] }}</td>
            <td>{{ number_format($item['total_price'], 2) }} €</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="3" class="total">Total pagado:</td>
        <td>{{ number_format($order->total, 2) }} €</td>
    </tr>
    </tbody>
</table>

<div class="footer">
    <p>Gracias por comprar en <strong>Decora10</strong>. Esta factura sirve como comprobante de tu pedido.</p>
    <p>Si tienes alguna duda, contáctanos en <a href="mailto:soporte@decora10.com">soporte@decora10.com</a></p>
</div>

</body>
</html>
