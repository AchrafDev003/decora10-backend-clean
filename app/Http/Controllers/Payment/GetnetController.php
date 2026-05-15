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

            // ==========================
            // 💰 IMPORTE EN CÉNTIMOS
            // ==========================
            $amountCents = (int) round($order->total * 100);

            // ==========================
            // 🧾 ORDER 12 DIGITOS FIJO
            // (EVITA RANDOM + PROBLEMAS REDSYS)
            // ==========================
            $orderCode = str_pad((string) $order->id, 12, '0', STR_PAD_LEFT);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // ==========================
            // 🔐 CONFIG
            // ==========================
            $merchantCode = config('getnet.merchant');
            $terminal     = (string) config('getnet.terminal'); // IMPORTANTE STRING
            $secretKey    = config('getnet.secret');
            $gatewayUrl   = config('getnet.url');

            // ==========================
            // 📦 PARAMS REDSYS
            // ==========================
            $params = [
                "DS_MERCHANT_AMOUNT"          => (string) $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal, // FIX CRÍTICO
                "DS_MERCHANT_MERCHANTURL"     => rtrim(config('app.url'), '/') . "/api/v1/payment/notify",
                "DS_MERCHANT_URLOK"           => rtrim(env('FRONTEND_URL'), '/') . "/gracias",
                "DS_MERCHANT_URLKO"           => rtrim(env('FRONTEND_URL'), '/') . "/checkout?error=pago",
            ];

            // ==========================
            // 🔥 JSON + BASE64
            // ==========================
            $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!$json) {
                throw new \Exception('JSON inválido en Redsys params');
            }

            $paramsBase64 = base64_encode($json);

            // ==========================
            // 🔐 FIRMA
            // ==========================
            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            // ==========================
            // 🧪 LOG DEBUG (IMPORTANTE)
            // ==========================
            Log::info('REDSYS PAYMENT INIT', [
                'order' => $orderCode,
                'amount' => $amountCents,
                'terminal' => $terminal,
                'merchant' => $merchantCode,
            ]);

            return response()->json([
                'success' => true,
                'gatewayUrl' => $gatewayUrl,
                'params' => $paramsBase64,
                'signature' => $signature,
                'version' => 'HMAC_SHA256_V1',
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

    // ==========================
    // 🔐 FIRMA REDSYS CORRECTA
    // ==========================
    private function generateSignature($paramsBase64, $secretKey, $orderCode)
    {
        $key = $secretKey;

        // SOLO 8 primeros caracteres (REGLA REDSYS)
        $order8 = substr($orderCode, 0, 8);

        // derivación clave
        $derivedKey = openssl_encrypt(
            $order8,
            'DES-EDE3-ECB',
            $key,
            OPENSSL_RAW_DATA
        );

        // firma final
        return base64_encode(
            hash_hmac('sha256', $paramsBase64, $derivedKey, true)
        );
    }
}
