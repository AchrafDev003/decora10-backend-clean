<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Cart;
use App\Models\Coupon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Refund;

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
                env('STRIPE_WEBHOOK_SECRET')
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature invalid', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        match ($event->type) {
            'payment_intent.succeeded'      => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            default => Log::info('Stripe event ignored', ['type' => $event->type]),
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

            $order = Order::with('orderItems.product', 'user')
                ->lockForUpdate()
                ->find($payment->order_id);

            if (!$order || $order->status !== 'pendiente') {
                return;
            }

            // 🔐 Antifraude: importe y estado
            $expectedAmount = (int) round($order->total * 100);

            if (
                $paymentIntent->amount_received !== $expectedAmount ||
                $paymentIntent->status !== 'succeeded'
            ) {
                $this->cancelOrder($order, 'Monto o estado inválido en Stripe');
                return;
            }

            // 1. Marcar pago como pagado
            $payment->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            // 2. Pedido a procesando
            $order->update(['status' => 'procesando']);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => 'procesando',
                'nota'     => 'Pago confirmado por Stripe',
            ]);

            // 3. Reducir stock (o refund)
            foreach ($order->orderItems as $item) {
                $product = $item->product;

                if ($product->quantity < $item->quantity) {

                    Refund::create([
                        'payment_intent' => $paymentIntent->id,
                    ]);

                    $this->cancelOrder(
                        $order,
                        'Stock insuficiente tras pago. Refund automático emitido.'
                    );

                    Log::error('Pedido cancelado con refund por stock', [
                        'order_id' => $order->id,
                        'payment_intent' => $paymentIntent->id,
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

            // 6. Acciones post-commit (PDF + email)
            DB::afterCommit(function () use ($order) {
                $this->generateOrderPDFAndSendMail($order);
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
            $this->cancelOrder($order, 'Pago fallido en Stripe');
        }
    }

    private function cancelOrder(Order $order, string $reason): void
    {
        $order->update(['status' => 'cancelado']);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status'   => 'cancelado',
            'nota'     => $reason,
        ]);
    }

    private function generateOrderPDFAndSendMail(Order $order): void
    {
        try {
            // ⚠️ Idealmente cachear estos assets en local
            $logoHeader    = $this->toBase64('https://res.cloudinary.com/dvo9uq7io/image/upload/v1764244414/blade-resources/header.png');
            $firmaSrc      = $this->toBase64('https://res.cloudinary.com/dvo9uq7io/image/upload/v1764244411/blade-resources/Decor_10.png');
            $telefonoIcono = $this->toBase64('https://res.cloudinary.com/dvo9uq7io/image/upload/v1764244416/blade-resources/telefono.png');

            $pdf = Pdf::loadView('pdf.order', [
                'order'          => $order->load('orderItems.product', 'user'),
                'logoHeader'     => $logoHeader,
                'firmaSrc'       => $firmaSrc,
                'telefonoIcono'  => $telefonoIcono,
            ]);

            $pdfPath = storage_path("app/public/invoices/order_{$order->id}.pdf");
            $pdf->save($pdfPath);

            Mail::to($order->user->email)
                ->bcc(['hrafartist@gmail.com', 'decora10.colchon10@gmail.com'])
                ->send(new OrderConfirmation($order, $pdfPath));

        } catch (\Throwable $e) {
            Log::error('Error generando PDF o enviando email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function toBase64(string $url): string
    {
        return 'data:image/png;base64,' . base64_encode(file_get_contents($url));
    }
}
