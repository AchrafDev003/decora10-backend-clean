<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    /**
     * Crea un intento de pago (PaymentIntent) en Stripe
     * Compatible con tarjeta y Bizum (en pruebas: Sofort).
     */
    public function createIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'user_id' => 'required|integer',
        ]);


        try {
            $amount = $request->input('amount');
            $paymentMethod = $request->input('payment_method');
            $orderId = $request->input('order_id');
            $userId = $request->input('user_id');

            $isLocal = app()->environment(['local', 'development']);
            $validMethods = $isLocal ? ['card', 'sofort'] : ['card', 'bizum'];
            if ($isLocal && $paymentMethod === 'bizum') $paymentMethod = 'sofort';

            if (!in_array($paymentMethod, $validMethods)) {
                return response()->json(['success' => false, 'error' => 'Método de pago no soportado'], 400);
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            $intent = PaymentIntent::create([
                'amount' => (int) ($amount * 100),
                'currency' => 'eur',
                'payment_method_types' => [$paymentMethod],
                'metadata' => [
                    'description' => 'Pago decor@10 pedido',
                    'user_id' => $userId,
                    'method' => $paymentMethod,
                    'env' => $isLocal ? 'test' : 'production',
                ],
            ]);


            return response()->json([
                'success' => true,
                'clientSecret' => $intent->client_secret,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error al crear el intento de pago',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

}
