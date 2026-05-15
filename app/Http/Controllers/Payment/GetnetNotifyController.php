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
        $params = $request->input('Ds_MerchantParameters');
        $signature = $request->input('Ds_Signature');

        if (!$params || !$signature) {
            return response('KO', 400);
        }

        // 🔥 FIX BASE64 URL SAFE
        $decodedJson = base64_decode(strtr($params, '-_', '+/'));
        $decoded = json_decode($decodedJson, true);

        if (!is_array($decoded)) {
            return response('KO', 400);
        }

        $orderCode = $decoded['DS_MERCHANT_ORDER'] ?? null;
        $amount = (int) ($decoded['DS_MERCHANT_AMOUNT'] ?? 0);
        $response = (int) ($decoded['DS_RESPONSE'] ?? 9999);
        $auth = $decoded['DS_AUTHORISATIONCODE'] ?? null;

        if (!$orderCode) return response('KO', 400);

        $order = Order::where('order_code_bank', $orderCode)->first();
        if (!$order) return response('KO', 404);

        $payment = $order->payment;
        if (!$payment) return response('KO', 404);

        // 🔁 IDEMPOTENCIA
        if ($payment->status === 'pagado') {
            return response('OK', 200);
        }

        // 💰 VALIDACIÓN
        $expected = (int) round($order->total * 100);

        if ($expected !== $amount) {
            Log::error('AMOUNT MISMATCH', compact('expected', 'amount'));
            return response('KO', 400);
        }

        // 🔐 FIRMA
        $key = openssl_encrypt(
            substr($orderCode, 0, 8),
            'DES-EDE3-ECB',
            config('getnet.secret'),
            OPENSSL_RAW_DATA
        );

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $params, $key, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            Log::error('INVALID SIGNATURE');
            return response('KO', 400);
        }

        // 💳 RESULTADO
        if ($response === 0) {

            $payment->update([
                'status' => 'pagado',
                'transaction_id' => $auth
            ]);

            $order->update(['status' => 'pagado']);

        } else {

            $payment->update(['status' => 'failed']);
            $order->update(['status' => 'fallo_pago']);
        }

        return response('OK', 200);
    }
}
