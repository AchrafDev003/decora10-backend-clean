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

            // ==========================
            // 🔎 Obtener pedido
            // ==========================
            $order = Order::findOrFail($request->order_id);

            // ==========================
            // 🛡️ Estado válido
            // ==========================
            if (!in_array($order->status, ['pendiente', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Pedido no disponible para pago',
                ], 400);
            }

            // ==========================
            // 🛡️ Evitar doble pago
            // ==========================
            if ($order->payment && $order->payment->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Pedido ya pagado',
                ], 400);
            }

            // ==========================
            // 💰 Validación total
            // ==========================
            if (!$order->total || $order->total <= 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Total inválido',
                ], 422);
            }

            $amountCents = (int) round($order->total * 100);

            // ==========================
            // 🔥 OrderCode seguro (12 chars max)
            // ==========================
            $orderCode = substr((string) ($order->id . time()), 0, 12);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // ==========================
            // 🔐 Config
            // ==========================
            $merchantCode = env('GETNET_MERCHANT');
            $terminal     = env('GETNET_TERMINAL');
            $secretKey    = env('GETNET_SECRET');

            // ==========================
            // 📦 Params
            // ==========================
            $params = [
                "DS_MERCHANT_AMOUNT"          => $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal,

                "DS_MERCHANT_MERCHANTURL" => route('payment.notify'),
                "DS_MERCHANT_URLOK"       => route('payment.ok'),
                "DS_MERCHANT_URLKO"       => route('payment.ko'),
            ];

            // ==========================
            // 🔄 FIX FIRMA ESTABLE
            // ==========================
            ksort($params);

            $paramsBase64 = base64_encode(
                json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            Log::info('Getnet INIT', [
                'order_id'   => $order->id,
                'order_code' => $orderCode,
                'amount'     => $amountCents,
            ]);

            return response()->json([
                'success'    => true,
                'gatewayUrl' => env('GETNET_URL'),
                'params'     => $paramsBase64,
                'signature'  => $signature,
                'version'    => 'HMAC_SHA256_V1',
            ]);

        } catch (\Throwable $e) {

            Log::error('Getnet ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Error iniciando pago',
            ], 500);
        }
    }

    /**
     * 🔐 Firma Redsys compatible estable
     */
    private function generateSignature($paramsBase64, $secretKey, $orderCode)
    {
        $key = base64_decode($secretKey);

        $derivedKey = openssl_encrypt(
            $orderCode,
            'AES-128-ECB',
            $key,
            OPENSSL_RAW_DATA
        );

        return base64_encode(
            hash_hmac('sha256', $paramsBase64, $derivedKey, true)
        );
    }
}
