<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Pedido #{{ $order->order_code }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 14px;
            padding: 25px;
            line-height: 1.45;
        }

        h1 {
            text-align: center;
            color: #27ae60;
            margin-bottom: 5px;
            font-size: 26px;
            letter-spacing: 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #e0e0e0;
            padding: 10px;
        }

        th {
            background-color: #27ae60;
            color: #fff;
            text-align: center;
            font-weight: 600;
        }

        td {
            vertical-align: top;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #27ae60;
            margin: 25px 0 5px;
        }

        .invoice-header {
            border-bottom: 2px solid #27ae60;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        .totales {
            width: 320px;
            float: right;
            margin-top: 25px;
            border: 1px solid #ddd;
            padding: 12px;
            background-color: #f9fdf9;
        }

        .totales table {
            width: 100%;
            border: none;
            margin: 0;
        }

        .totales th {
            background: none;
            color: #333;
            border: none;
            text-align: left;
            padding: 5px 0;
        }

        .totales td {
            border: none;
            text-align: right;
            padding: 5px 0;
        }

        .totales strong {
            font-size: 16px;
            color: #27ae60;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            margin-top: 60px;
            padding-top: 15px;
            border-top: 2px solid #27ae60;
            color: #555;
        }

    </style>
</head>
<body>

{{-- Logo --}}
<div style="text-align:center;margin-bottom:20px;">
    @if($logoHeader)
        <img src="{{ $logoHeader }}" style="width:400px;height:auto;object-fit:contain;">
    @else
        <h2>Decora10</h2>
    @endif
</div>


<div class="invoice-header">
    <h1 style="text-align:center;color:#27ae60;margin-bottom:5px;">FACTURA</h1>
    <p style="text-align:center;margin:0;font-size:14px;">
        Nº: {{ $order->tracking_number }} — {{ $order->created_at->format('d/m/Y') }}
    </p>
</div>


{{-- Información Cliente / Empresa --}}
<table style="width:100%;margin-bottom:20px;border-collapse:collapse;">
    <tr>
        <td style="width:50%;padding:10px;border:1px solid #ccc;">
            <strong>Cliente</strong><br>
            {{ $order->user->name }}<br>
            {{ $order->user->email }}<br>
            {{ $order->shipping_address }}
        </td>

        <td style="width:50%;padding:10px;border:1px solid #ccc;text-align:right;">
            <strong>Empresa</strong><br>
            Decora10<br>
            <a href="https://www.decora10.com" target="_blank">www.decora10.com</a><br>
            @if($telefonoIcono)
                <img src="{{ $telefonoIcono }}" style="width:14px;height:14px;vertical-align:middle;margin-right:5px;">
            @endif
            953-581-802<br>
            Avenida Andalucía 8, Alcalá la Real
        </td>
    </tr>
</table>



{{-- Productos --}}
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
    <tr style="background:#27ae60;color:#fff;">
        <th>Producto</th>
        <th>Cantidad</th>
        <th>Precio</th>
        <th>Subtotal</th>
    </tr>
    @foreach($order->orderItems as $i => $item)
        <tr style="background: {{ $i % 2 == 0 ? '#fff' : '#f9f9f9' }}">
            <td style="word-wrap:break-word;white-space:normal;max-width:150px;">
                {{ $item->product->name ?? 'Producto eliminado' }}
            </td>
            <td style="text-align:center;">{{ $item->quantity }}</td>
            <td style="text-align:right;">{{ number_format($item->price, 2) }} €</td>
            <td style="text-align:right;">{{ number_format($item->price * $item->quantity, 2) }} €</td>
        </tr>
    @endforeach
</table>

{{-- Totales --}}
<div class="totales">
    <table>
        <tr>
            <th>Subtotal:</th>
            <td>€{{ number_format($order->total + $order->discount, 2) }}</td>
        </tr>
        @if($order->discount > 0)
            <tr>
                <th>Descuento:</th>
                <td>-€{{ number_format($order->discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <th style="color:#27ae60;">Total Final:</th>
            <td><strong>€{{ number_format($order->total, 2) }}</strong></td>
        </tr>
    </table>
</div>

{{-- Notas --}}
@if(!empty($order->notes))
    <div style="width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;margin-top:20px;">
        <strong>Notas</strong><br>
        {!! nl2br(e($order->notes)) !!}
    </div>
@endif

{{-- Firma --}}
<div style="text-align:right;margin-bottom:25px;">
    @if($firmaSrc)
        <img src="{{ $firmaSrc }}" style="width:160px;height:auto;">
    @endif
</div>

{{-- Footer --}}
<div class="footer">
    Gracias por su confianza 💚<br>
    Visite <a href="https://www.decora10.com" target="_blank">www.decora10.com</a>
</div>

</body>
</html>
