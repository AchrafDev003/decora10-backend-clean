<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
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

            // ⚡ Modo Stripe
            $stripeMode = env('STRIPE_MODE', 'live');
            $secretKey = $stripeMode === 'test'
                ? trim(env('STRIPE_SECRET_TEST'), '"')
                : trim(env('STRIPE_SECRET_LIVE'), '"');

            Stripe::setApiKey($secretKey);

            // Métodos de pago válidos
            $validMethods = $stripeMode === 'test' ? ['card', 'sofort'] : ['card', 'bizum'];

            if ($stripeMode === 'test' && $paymentMethod === 'bizum') {
                $paymentMethod = 'sofort';
            }

            if (!in_array($paymentMethod, $validMethods)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Método de pago no soportado',
                ], 400);
            }

            // Crear PaymentIntent
            $intent = PaymentIntent::create([
                'amount' => (int) ($amount * 100),
                'currency' => 'eur',
                'payment_method_types' => [$paymentMethod],
                'metadata' => [
                    'description' => "Decora10 pago $paymentMethod" . ($orderId ? " - Pedido #$orderId" : ""),
                    'user_id' => $userId,
                    'method' => $paymentMethod,
                    'env' => $stripeMode,
                ],
            ]);

            // Log para depuración en producción
            Log::info('Stripe PaymentIntent creado', [
                'id' => $intent->id,
                'clientSecret' => $intent->client_secret,
                'method' => $paymentMethod,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'env' => $stripeMode,
            ]);

            return response()->json([
                'success' => true,
                'clientSecret' => $intent->client_secret,
                'payment_method' => $paymentMethod,
                'env' => $stripeMode,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
            ]);

        } catch (\Exception $e) {
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
