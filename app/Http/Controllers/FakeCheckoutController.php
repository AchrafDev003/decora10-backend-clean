<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class FakeCheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Debes iniciar sesión para realizar el checkout'
            ], 401);
        }

        $cartItems = $user->cartItems()->get();
        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'Tu carrito está vacío'
            ]);
        }

        $total = $cartItems->sum(fn($item) => ($item->promo_price ?? $item->price) * $item->quantity);

        // Simulamos pago aprobado
        $order = Order::create([
            'user_id' => $user->id,
            'total' => $total,
            'shipping_address' => $request->shipping_address,
            'payment_method' => 'fake_bank',
            'status' => 'paid',
        ]);

        // Vaciamos carrito
        $user->cartItems()->delete();

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => 'Pago simulado exitoso'
        ]);
    }
}
