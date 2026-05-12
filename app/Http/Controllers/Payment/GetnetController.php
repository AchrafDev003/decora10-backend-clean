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

            // 🔥 Estado correcto
            if ($order->status !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Pedido no disponible para pago',
                ], 400);
            }

            // 🔥 Evitar doble pago
            if ($order->payment && $order->payment->status === 'pagado') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Pedido ya pagado',
                ], 400);
            }

            if (!$order->total || $order->total <= 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Total inválido',
                ], 422);
            }

            $amountCents = (int) round($order->total * 100);

            // 🔥 Código bancario válido
            $orderCode = str_pad($order->id, 12, "0", STR_PAD_LEFT);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // 🔐 Config
            $merchantCode = config('getnet.merchant');
            $terminal     = config('getnet.terminal');
            $secretKey    = config('getnet.secret');

            $params = [
                "DS_MERCHANT_AMOUNT"          => $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal,
                "DS_MERCHANT_MERCHANTURL"     => route('payment.notify'),
                "DS_MERCHANT_URLOK"           => route('payment.ok'),
                "DS_MERCHANT_URLKO"           => route('payment.ko'),
            ];

            ksort($params, SORT_STRING);

            $paramsBase64 = base64_encode(
                json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            return response()->json([
                'success'    => true,
                'gatewayUrl' => config('getnet.url'),
                'params'     => $paramsBase64,
                'signature'  => $signature,
                'version'    => 'HMAC_SHA256_V1',
            ]);

        } catch (\Throwable $e) {
            Log::info('GETNET PARAMS FINAL', $params);
            Log::info('ORDER CODE', [$orderCode]);
            Log::info('AMOUNT CENTS', [$amountCents]);

            Log::error('Getnet ERROR', [
                'message' => $e->getMessage(),
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
