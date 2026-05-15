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

            $debug = $request->get('debug') == 1;

            $params = $request->input('Ds_MerchantParameters');
            $signature = $request->input('Ds_Signature');

            if (!$params || !$signature) {
                return $this->debug($debug, 'missing_params');
            }

            $decoded = json_decode(base64_decode($params), true);

            if (!is_array($decoded)) {
                return $this->debug($debug, 'invalid_json');
            }

            $orderCode = $decoded['DS_ORDER'] ?? null;
            $response  = (int) ($decoded['DS_RESPONSE'] ?? 9999);
            $amount    = (int) ($decoded['DS_AMOUNT'] ?? 0);
            $auth      = $decoded['DS_AUTHORISATIONCODE'] ?? null;

            if (!$orderCode) {
                return $this->debug($debug, 'no_order');
            }

            $secret = config('getnet.secret');

            $key = openssl_encrypt(
                substr($orderCode, 0, 8),
                'DES-EDE3-ECB',
                $secret,
                OPENSSL_RAW_DATA
            );

            $expected = base64_encode(hash_hmac('sha256', $params, $key, true));

            if (!hash_equals($expected, $signature)) {
                Log::warning('INVALID SIGNATURE', compact('expected', 'signature'));
                return response('KO', 400);
            }

            $order = Order::where('order_code_bank', $orderCode)->first();

            if (!$order) return response('KO', 404);

            $payment = $order->payment;

            if (!$payment) return response('KO', 404);

            // idempotencia
            if ($payment->status === 'pagado') {
                return response('OK', 200)->header('Content-Type', 'text/plain');
            }

            $expectedAmount = (int) round($order->total * 100);

            if ($amount !== $expectedAmount) {
                Log::error('AMOUNT MISMATCH', [
                    'expected' => $expectedAmount,
                    'received' => $amount
                ]);
                return response('KO', 400);
            }

            // OK PAYMENT
            if ($response === 0) {

                $payment->update([
                    'status' => 'pagado',
                    'transaction_id' => $auth
                ]);

                $order->update(['status' => 'pagado']);

                Log::info('PAYMENT OK', ['order' => $order->id]);

            } else {

                $payment->update(['status' => 'failed']);
                $order->update(['status' => 'failed']);

                Log::warning('PAYMENT FAILED', [
                    'order' => $order->id,
                    'response' => $response
                ]);
            }

            return response('OK', 200)->header('Content-Type', 'text/plain');

        } catch (\Throwable $e) {

            Log::error('NOTIFY CRASH', [
                'message' => $e->getMessage()
            ]);

            return response('KO', 500);
        }
    }
    private function debug($enabled, $step)
    {
        if (!$enabled) return response('KO', 400);

        return response()->json([
            'debug_step' => $step,
            'time' => now()
        ]);
    }
}
