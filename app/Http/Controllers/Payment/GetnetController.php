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

        $params = [];
        $orderCode = null;
        $amountCents = null;

        try {

            $order = Order::with('payment')->findOrFail($request->order_id);

            // 🔥 Validaciones
            if ($order->status !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Pedido no disponible para pago',
                ], 400);
            }

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

            // 🔥 IMPORTE EN CENTIMOS
            $amountCents = (int) round($order->total * 100);

            // 🔥 ORDER FORMAT REDSYS (máx 12 chars)
            $orderCode = str_pad((string)$order->id, 12, "0", STR_PAD_LEFT);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // 🔐 CONFIG
            $merchantCode = config('getnet.merchant');
            $terminal     = config('getnet.terminal');
            $secretKey    = config('getnet.secret');
            $gatewayUrl   = config('getnet.url');

            // 🔥 PARAMS REDSYS (ORDEN FIJO OBLIGATORIO)
            $params = [
                "DS_MERCHANT_AMOUNT"          => $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal,
                "DS_MERCHANT_MERCHANTURL"     => route('payment.notify'),
                // 🔥 FRONTEND (REDIRECCIÓN USUARIO)
                "DS_MERCHANT_URLOK"           => env('FRONTEND_URL') . "/gracias",
                "DS_MERCHANT_URLKO"           => env('FRONTEND_URL') . "/checkout?error=pago",
            ];

            // 🔥 IMPORTANTE: ordenar SIEMPRE antes de firmar
            ksort($params);

            // 🔐 JSON estable
            $jsonParams = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $paramsBase64 = base64_encode($jsonParams);

            // 🔐 FIRMA
            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            // 🔍 LOG DEBUG (CLAVE PARA ERRORES REDSYS)
            Log::info('REDSYS PAYLOAD FINAL', [
                'merchant'  => $merchantCode,
                'terminal'  => $terminal,
                'order'     => $orderCode,
                'amount'    => $amountCents,
                'json'      => $jsonParams,
                'base64'    => $paramsBase64,
                'signature' => $signature,
            ]);

            return response()->json([
                'success'    => true,
                'gatewayUrl' => $gatewayUrl,
                'params'     => $paramsBase64,
                'signature'  => $signature,
                'version'    => 'HMAC_SHA256_V1',
            ]);

        } catch (\Throwable $e) {

            Log::error('GETNET ERROR', [
                'message' => $e->getMessage(),
                'order'   => $orderCode,
                'amount'  => $amountCents,
                'params'  => $params,
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Error iniciando pago',
            ], 500);
        }
    }

    /**
     * 🔐 FIRMA REDSYS (HMAC_SHA256_V1)
     */
    private function generateSignature($paramsBase64, $secretKey, $orderCode)
    {
        $key = base64_decode($secretKey);

        $orderPadded = str_pad(
            $orderCode,
            ceil(strlen($orderCode) / 8) * 8,
            "\0"
        );

        $iv = "\0\0\0\0\0\0\0\0";

        $derivedKey = openssl_encrypt(
            $orderPadded,
            'DES-EDE3-CBC',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        $signature = hash_hmac(
            'sha256',
            $paramsBase64,
            $derivedKey,
            true
        );

        return base64_encode($signature);
    }
}
