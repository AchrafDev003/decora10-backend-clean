<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderStatusHistory;

class GetnetNotifyController extends Controller
{
    public function notify(Request $request)
    {
        try {

            $merchantParams = $request->input('Ds_MerchantParameters');
            $signature      = $request->input('Ds_Signature');

            if (!$merchantParams || !$signature) {
                return response('KO', 400);
            }

            $decoded = json_decode(base64_decode($merchantParams), true);

            if (!is_array($decoded)) {
                return response('KO', 400);
            }

            Log::info('GETNET NOTIFY RAW', $decoded);

            $orderCode = $decoded['Ds_Order'] ?? null;
            $response  = (int) ($decoded['Ds_Response'] ?? 9999);
            $amount    = (int) ($decoded['Ds_Amount'] ?? 0);
            $authCode  = $decoded['Ds_AuthorisationCode'] ?? null;

            if (!$orderCode) {
                return response('KO', 400);
            }

            // ==========================
            // 🔐 FIRMA (DEBE SER IGUAL QUE CREATE)
            // ==========================
            $secretKey = config('getnet.secret');

            $order8 = substr($orderCode, 0, 8);

            $derivedKey = openssl_encrypt(
                $order8,
                'DES-EDE3-ECB',
                $secretKey,
                OPENSSL_RAW_DATA
            );

            $expectedSignature = base64_encode(
                hash_hmac('sha256', $merchantParams, $derivedKey, true)
            );

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Firma inválida', [
                    'expected' => $expectedSignature,
                    'received' => $signature
                ]);
                return response('KO', 400);
            }

            $order = Order::where('order_code_bank', $orderCode)->first();

            if (!$order) {
                return response('KO', 404);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('provider', 'getnet')
                ->first();

            if (!$payment) {
                return response('KO', 404);
            }

            if ($payment->status === 'pagado') {
                return response('OK', 200);
            }

            $expectedAmount = (int) round($order->total * 100);

            if ($amount !== $expectedAmount) {
                Log::error('Importe incorrecto', [
                    'expected' => $expectedAmount,
                    'received' => $amount
                ]);
                return response('KO', 400);
            }

            // ==========================
            // 💳 SOLO 0000 = OK REAL
            // ==========================
            if ($response === 0) {

                $payment->update([
                    'status' => 'pagado',
                    'transaction_id' => $authCode,
                ]);

                $order->update(['status' => 'pagado']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'pagado',
                    'nota'     => 'Pago confirmado por Getnet',
                ]);

                Log::info('PAGO OK', ['order' => $order->id]);

            } else {

                $payment->update(['status' => 'failed']);
                $order->update(['status' => 'failed']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'failed',
                    'nota'     => 'Pago rechazado por Redsys',
                ]);

                Log::warning('PAGO KO', [
                    'order' => $order->id,
                    'response' => $response
                ]);
            }

            return response('OK', 200);

        } catch (\Throwable $e) {

            Log::error('Notify exception', [
                'message' => $e->getMessage()
            ]);

            return response('KO', 500);
        }
    }
}
