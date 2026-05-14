<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class GetnetController extends Controller
{
    public function createPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        try {

            $order = Order::with('payment')->findOrFail($request->order_id);

            if ($order->status !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido no disponible'
                ], 400);
            }

            if ($order->payment && $order->payment->status === 'pagado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido ya pagado'
                ], 400);
            }

            if (!$order->total || $order->total <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Total inválido'
                ], 422);
            }

            // 💰 importe en céntimos
            $amountCents = (int) round($order->total * 100);

            // 🧾 order (12 chars)
            $orderCode = str_pad((string)$order->id, 12, "0", STR_PAD_LEFT);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // 🔐 config
            $merchantCode = config('getnet.merchant');
            $terminal     = config('getnet.terminal');
            $secretKey    = config('getnet.secret');
            $gatewayUrl   = config('getnet.url');

            // 📦 params
            $params = [
                "DS_MERCHANT_AMOUNT"          => (string) $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal,
                "DS_MERCHANT_MERCHANTURL"     => config('app.url') . "/api/v1/payment/notify",
                "DS_MERCHANT_URLOK"           => rtrim(env('FRONTEND_URL'), '/') . "/gracias",
                "DS_MERCHANT_URLKO"           => rtrim(env('FRONTEND_URL'), '/') . "/checkout?error=pago",
            ];

            $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!$json) {
                throw new \Exception('JSON inválido');
            }

            // 🔥 BASE64 NORMAL (correcto para SHA256)
            $paramsBase64 = base64_encode($json);

            // 🔐 FIRMA
            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            Log::info('REDSYS OK', [
                'order' => $orderCode,
                'amount' => $amountCents,
            ]);

            return response()->json([
                'success' => true,
                'gatewayUrl' => $gatewayUrl,
                'params' => $paramsBase64,
                'signature' => $signature,
                'version' => 'HMAC_SHA256_V1'
            ]);

        } catch (\Throwable $e) {

            Log::error('GETNET ERROR', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error iniciando pago'
            ], 500);
        }
    }

    // 🔐 FIRMA SHA256 CORRECTA (GETNET / REDSYS CLÁSICO)
    private function generateSignature($paramsBase64, $secretKey, $orderCode)
    {
        // ✔ clave RAW (tu caso)
        $key = $secretKey;

        // 🔥 SOLO los primeros 8 caracteres (CRÍTICO)
        $order8 = substr($orderCode, 0, 8);

        // 🔐 derivar clave
        $derivedKey = openssl_encrypt(
            $order8,
            'DES-EDE3-ECB',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        // 🔐 firma final
        return base64_encode(
            hash_hmac('sha256', $paramsBase64, $derivedKey, true)
        );
    }
}
