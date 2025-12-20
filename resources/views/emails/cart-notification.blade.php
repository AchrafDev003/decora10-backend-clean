<!DOCTYPE html>
<html lang="es">
<body style="font-family: Arial, sans-serif; color:#111">

<h3>Nuevo producto añadido al carrito</h3>

<p><strong>Producto:</strong> {{ $product->name }}</p>
<p><strong>Cantidad añadida:</strong> {{ $quantity }}</p>
<p><strong>Stock restante:</strong> {{ $product->quantity - $quantity }}</p>

<hr>

<p><strong>Usuario:</strong> {{ optional($cart->user)->email ?? 'Invitado' }}</p>
<p><strong>Carrito ID:</strong> {{ $cart->id }}</p>
<p><strong>Fecha:</strong> {{ now()->format('d/m/Y H:i') }}</p>

</body>
</html>

