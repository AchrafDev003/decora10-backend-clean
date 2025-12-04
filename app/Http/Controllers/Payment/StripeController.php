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
     * Crea un PaymentIntent en Stripe.
     * Compatible con tarjeta y Bizum (Sofort en test/local).
     */
    public function createIntent(Request $request)
    {
        // Validación de entrada
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'user_id' => 'required|integer',
            'order_id' => 'sometimes|integer',
        ]);

        try {
            $amount = $request->input('amount');
            $paymentMethod = strtolower($request->input('payment_method'));
            $userId = $request->input('user_id');
            $orderId = $request->input('order_id', null);

            // Detectar entorno
            $stripeMode = env('STRIPE_MODE', 'live'); // 'test' o 'live'
            $envLabel = $stripeMode === 'test' ? 'test' : 'production';

            // Validar métodos de pago según entorno
            $validMethods = $stripeMode === 'test' ? ['card', 'sofort'] : ['card', 'bizum'];
            if ($stripeMode === 'test' && $paymentMethod === 'bizum') {
                $paymentMethod = 'sofort'; // Simulación Bizum en test
            }

            if (!in_array($paymentMethod, $validMethods)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Método de pago no soportado',
                ], 400);
            }

            // Seleccionar la clave correcta según el modo
            $secretKey = $stripeMode === 'test'
                ? env('STRIPE_SECRET_TEST')
                : env('STRIPE_SECRET_LIVE');

            Stripe::setApiKey($secretKey);

            // Crear PaymentIntent
            $intent = PaymentIntent::create([
                'amount' => (int) ($amount * 100), // Stripe usa céntimos
                'currency' => 'eur',
                'payment_method_types' => [$paymentMethod],
                'metadata' => [
                    'description' => 'Pago Decora10 pedido' . ($orderId ? " #$orderId" : ""),
                    'user_id' => $userId,
                    'method' => $paymentMethod,
                    'env' => $envLabel,
                ],
            ]);

            return response()->json([
                'success' => true,
                'clientSecret' => $intent->client_secret,
                'payment_method' => $paymentMethod,
                'env' => $envLabel,
            ]);

        } catch (\Exception $e) {
            // Log seguro del error
            Log::error('Stripe Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al crear el PaymentIntent',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
