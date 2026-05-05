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
            // 🔎 Obtener pedido real
            // ==========================
            $order = Order::findOrFail($request->order_id);

            // ==========================
            // 🛡️ Validación de estado
            // ==========================
            if (!in_array($order->status, ['pendiente', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'El pedido no está disponible para pago',
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
            // 💰 Importe REAL
            // ==========================
            $amountCents = (int) round($order->total * 100);

            if ($amountCents <= 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Cantidad inválida',
                ], 422);
            }

            // ==========================
            // 🔥 OrderCode banco (12 dígitos)
            // ==========================
            $orderCode = str_pad($order->id, 12, '0', STR_PAD_LEFT);

            // Guardarlo para trazabilidad
            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // ==========================
            // 🔐 Configuración
            // ==========================
            $merchantCode = env('GETNET_MERCHANT');
            $terminal     = env('GETNET_TERMINAL');
            $secretKey    = env('GETNET_SECRET');

            // ==========================
            // 📦 Parámetros TPV
            // ==========================
            $params = [
                "DS_MERCHANT_AMOUNT"          => $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal,

                // 🔥 URLs
                "DS_MERCHANT_MERCHANTURL" => route('payment.notify'),
                "DS_MERCHANT_URLOK"       => route('payment.ok'),
                "DS_MERCHANT_URLKO"       => route('payment.ko'),
            ];

            // ==========================
            // 🔄 Codificar
            // ==========================
            $paramsBase64 = base64_encode(json_encode($params));

            // ==========================
            // 🔐 Firma CORRECTA (clave derivada)
            // ==========================
            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            Log::info('Getnet pago iniciado', [
                'order_id'   => $order->id,
                'order_code' => $orderCode,
                'amount'     => $order->total,
            ]);

            return response()->json([
                'success'    => true,
                'gatewayUrl' => env('GETNET_URL'),
                'params'     => $paramsBase64,
                'signature'  => $signature,
                'version'    => 'HMAC_SHA256_V1',
            ]);

        } catch (\Throwable $e) {

            Log::error('Getnet error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Error iniciando pago',
            ], 500);
        }
    }

    /**
     * 🔐 Firma estilo Redsys/Getnet real
     */
    private function generateSignature($paramsBase64, $secretKey, $orderCode)
    {
        $key = base64_decode($secretKey);

        // Derivar clave con orderCode
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
