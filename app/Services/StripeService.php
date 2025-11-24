<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Log;

class StripeService
{
    /**
     * Crear un PaymentIntent en Stripe
     *
     * @param array $data ['amount' => float, 'user_id' => int|null, 'order_id' => int|null]
     * @return PaymentIntent
     * @throws \Exception
     */
    public static function createIntent(array $data)
    {
        try {
            $amount = $data['amount'] ?? 0;
            if ($amount <= 0) {
                throw new \Exception('Monto inválido');
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            $intent = PaymentIntent::create([
                'amount' => (int) ($amount * 100), // convertir a céntimos
                'currency' => 'eur',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'description' => 'Pago decor@10 pedido',
                    'order_id' => $data['order_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                ],
            ]);

            return $intent;
        } catch (\Exception $e) {
            Log::error('Stripe Error: '.$e->getMessage());
            throw $e;
        }
    }
}
