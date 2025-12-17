<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripeController extends Controller
{
    public function createIntent(Request $request)
    {
        // ============================
        // 1. Validación estricta
        // ============================
        $request->validate([
            'order_id'       => 'required|integer|exists:orders,id',
            'payment_method' => 'required|string|in:card,bizum',
            'user_id'        => 'required|integer',
        ]);

        try {
            // ============================
            // 2. Obtener pedido (LOCK)
            // ============================
            $order = Order::with('orderItems')
                ->lockForUpdate()
                ->findOrFail($request->order_id);

            // Seguridad básica
            if ($order->user_id !== (int) $request->user_id) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Pedido no autorizado',
                ], 403);
            }

            if ($order->status !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'error'   => 'El pedido ya no está disponible para pago',
                ], 409);
            }

            // ============================
            // 3. Reutilizar PaymentIntent
            // ============================
            $existingPayment = Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'success'       => true,
                    'clientSecret'  => $existingPayment->client_secret,
                    'amount'        => $order->total,
                    'currency'      => 'eur',
                    'reused'        => true,
                ]);
            }

            // ============================
            // 4. Calcular importe FINAL
            // ============================
            $amount = (int) round($order->total * 100); // euros → céntimos

            if ($amount <= 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Importe inválido',
                ], 422);
            }

            // ============================
            // 5. Configuración Stripe
            // ============================
            $stripeMode = env('STRIPE_MODE', 'test');

            $secretKey = $stripeMode === 'test'
                ? trim(env('STRIPE_SECRET_TEST'), '"')
                : trim(env('STRIPE_SECRET_LIVE'), '"');

            Stripe::setApiKey($secretKey);

            // Métodos permitidos
            $paymentMethod = strtolower($request->payment_method);

            if ($stripeMode === 'test' && $paymentMethod === 'bizum') {
                $paymentMethod = 'sofort'; // simulación
            }

            // ============================
            // 6. Crear PaymentIntent
            // ============================
            $intent = PaymentIntent::create([
                'amount'   => $amount,
                'currency' => 'eur',
                'payment_method_types' => [$paymentMethod],
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id'  => $order->user_id,
                    'env'      => $stripeMode,
                ],
            ]);

            // ============================
            // 7. Guardar referencia local
            // ============================
            Payment::create([
                'order_id'      => $order->id,
                'user_id'       => $order->user_id,
                'reference'     => $intent->id,
                'client_secret' => $intent->client_secret,
                'amount'        => $order->total,
                'currency'      => 'EUR',
                'status'        => 'pending',
            ]);

            Log::info('Stripe PaymentIntent creado', [
                'intent_id' => $intent->id,
                'order_id'  => $order->id,
                'amount'    => $amount,
                'method'    => $paymentMethod,
                'env'       => $stripeMode,
            ]);

            return response()->json([
                'success'      => true,
                'clientSecret' => $intent->client_secret,
                'amount'       => $order->total,
                'currency'     => 'eur',
                'env'          => $stripeMode,
            ]);

        } catch (\Throwable $e) {

            Log::error('Stripe createIntent error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'No se pudo iniciar el pago',
            ], 500);
        }
    }
}
