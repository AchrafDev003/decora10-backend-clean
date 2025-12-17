<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripeController extends Controller
{
    public function createIntent(Request $request)
    {
        $request->validate([
            'amount'          => 'required|numeric|min:0.01',
            'payment_method'  => 'required|string',
            'user_id'         => 'required|integer',
        ]);

        try {
            $amount        = (float) $request->amount;
            $paymentMethod = strtolower($request->payment_method);
            $userId        = $request->user_id;

            // ⚡ Stripe mode
            $stripeMode = trim(env('STRIPE_MODE', 'test'), '"');

            $secretKey = $stripeMode === 'test'
                ? trim(env('STRIPE_SECRET_TEST', ''), '"')
                : trim(env('STRIPE_SECRET_LIVE', ''), '"');

            if (empty($secretKey)) {
                throw new \Exception('Stripe secret key no configurada');
            }

            Stripe::setApiKey($secretKey);

            // Métodos permitidos
            $validMethods = $stripeMode === 'test'
                ? ['card', 'sofort']
                : ['card', 'bizum'];

            if ($stripeMode === 'test' && $paymentMethod === 'bizum') {
                $paymentMethod = 'sofort';
            }

            if (!in_array($paymentMethod, $validMethods, true)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Método de pago no soportado',
                ], 422);
            }

            // 💰 Convertir a céntimos
            $amountCents = (int) round($amount * 100);
            if ($amountCents <= 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Cantidad inválida para Stripe',
                ], 422);
            }

            // Crear PaymentIntent
            $intent = PaymentIntent::create([
                'amount'               => $amountCents,
                'currency'             => 'eur',
                'payment_method_types' => [$paymentMethod],
                'metadata' => [
                    'user_id' => $userId,
                    'env'     => $stripeMode,
                ],
                'description' => 'Decora10 - pago previo a pedido',
            ]);

            // Registrar pago local
            Payment::create([
                'user_id'   => $userId,
                'reference' => $intent->id,
                'amount'    => $amount,
                'currency'  => 'EUR',
                'status'    => 'pending',
            ]);

            Log::info('PaymentIntent creado (payment-first)', [
                'payment_intent' => $intent->id,
                'user_id'        => $userId,
                'amount'         => $amount,
                'method'         => $paymentMethod,
                'env'            => $stripeMode,
            ]);

            return response()->json([
                'success'      => true,
                'clientSecret' => $intent->client_secret,
                'amount'       => $amount,
                'currency'     => 'EUR',
                'method'       => $paymentMethod,
                'env'          => $stripeMode,
            ]);


        }catch (\Throwable $e) {
            Log::error('Stripe PaymentIntent error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Antes:
            // fwrite(STDERR, "Stripe PaymentIntent error: {$e->getMessage()}\n{$e->getTraceAsString()}\n");

            // Ahora:
            fwrite(\STDERR, "Stripe PaymentIntent error: {$e->getMessage()}\n{$e->getTraceAsString()}\n");

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }

    }
}
