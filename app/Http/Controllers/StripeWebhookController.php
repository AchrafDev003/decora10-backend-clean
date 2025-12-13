<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Cart;
use App\Models\Coupon;
use App\Jobs\GenerateOrderPdfJob;
use App\Jobs\SendOrderConfirmationEmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Webhook;
use function Laravel\Prompts\alert;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        match ($event->type) {
            'payment_intent.succeeded'      => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    protected function handlePaymentSucceeded($paymentIntent): void
    {

        DB::transaction(function () use ($paymentIntent) {

            $payment = Payment::where('reference', $paymentIntent->id)
                ->lockForUpdate()
                ->first();

            if (!$payment || $payment->status !== 'pending') {
                return;
            }

            $order = Order::with('orderItems.product')
                ->lockForUpdate()
                ->find($payment->order_id);

            if (!$order || $order->status !== 'pendiente') {
                return;
            }

            // 🔐 Verificación de importe (antifraude)
            $expectedAmount = (int) round($order->total * 100);
            if ($paymentIntent->amount_received !== $expectedAmount) {
                $order->update(['status' => 'cancelado']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'cancelado',
                    'nota'     => 'Monto recibido no coincide con el pedido',
                ]);

                return;
            }

            // 1. Marcar pago como pagado
            $payment->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            // 2. Pedido a procesando
            $order->update([
                'status' => 'procesando',
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => 'procesando',
                'nota'     => 'Pago confirmado por Stripe',
            ]);

            // 3. Reducir stock
            foreach ($order->orderItems as $item) {
                $product = $item->product;

                if ($product->quantity < $item->quantity) {
                    $order->update(['status' => 'cancelado']);

                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'status'   => 'cancelado',
                        'nota'     => 'Cancelado por inconsistencia de stock tras pago',
                    ]);

                    return;
                }

                $product->decrement('quantity', $item->quantity);
            }

            // 4. Consumir cupón
            if ($order->promo_code) {
                Coupon::where('code', $order->promo_code)
                    ->increment('used_count');
            }

            // 5. Vaciar carrito
            Cart::where('user_id', $order->user_id)
                ->first()?->items()->delete();

            // 6. Jobs AFTER COMMIT
            DB::afterCommit(function () use ($order) {
                GenerateOrderPdfJob::dispatch($order);
                SendOrderConfirmationEmailJob::dispatch($order);
            });
        });
    }

    protected function handlePaymentFailed($paymentIntent): void
    {
        $payment = Payment::where('reference', $paymentIntent->id)->first();

        if (!$payment || $payment->status !== 'pending') {
            return;
        }

        $payment->update(['status' => 'failed']);

        $order = Order::find($payment->order_id);

        if ($order && $order->status === 'pendiente') {
            $order->update(['status' => 'cancelado']);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => 'cancelado',
                'nota'     => 'Pago fallido',
            ]);
        }
    }
}
