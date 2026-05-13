<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\RedsysValidator;

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

            // =========================
            // 🔥 VALIDACIONES NEGOCIO
            // =========================
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

            // =========================
            // 💰 IMPORTE EN CENTIMOS
            // =========================
            $amountCents = (int) round($order->total * 100);

            // =========================
            // 🧾 ORDER REDSYS (MAX 12)
            // =========================
            $orderCode = str_pad((string)$order->id, 12, "0", STR_PAD_LEFT);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // =========================
            // 🔐 CONFIG
            // =========================
            $merchantCode = (string) config('getnet.merchant');
            $terminal     = (string) config('getnet.terminal');
            $secretKey    = (string) config('getnet.secret');
            $gatewayUrl   = (string) config('getnet.url');

            // =========================
            // 📦 PARAMS REDSYS
            // =========================
            $params = [
                "DS_MERCHANT_AMOUNT"          => (string) $amountCents,
                "DS_MERCHANT_ORDER"           => $orderCode,
                "DS_MERCHANT_MERCHANTCODE"    => $merchantCode,
                "DS_MERCHANT_CURRENCY"        => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL"        => $terminal,

                // 🔥 BACKEND NOTIFY (OBLIGATORIO)
                "DS_MERCHANT_MERCHANTURL"     => config('app.url') . "/api/v1/payment/notify",

                // 🔥 FRONTEND REDIRECT
                "DS_MERCHANT_URLOK"           => rtrim(env('FRONTEND_URL'), '/') . "/gracias",
                "DS_MERCHANT_URLKO"           => rtrim(env('FRONTEND_URL'), '/') . "/checkout?error=pago",
            ];

            // =========================
            // 🔐 ORDER FIX (IMPORTANTE)
            // =========================
            //ksort($params);

            // =========================
            // 📦 JSON SEGURO
            // =========================
            $jsonParams = json_encode(
                $params,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if (!$jsonParams) {
                throw new \Exception('JSON inválido en parámetros Redsys');
            }

            $paramsBase64 = base64_encode($jsonParams);
            $validation = RedsysValidator::validate($params, $paramsBase64, 'temp');
            if (!$validation['valid']) {
                Log::error('REDSYS VALIDATION ERROR', $validation['errors']);
                throw new \Exception('Error validando parámetros Redsys');
            }

            // =========================
            // 🔐 FIRMA
            // =========================
            $signature = $this->generateSignature($paramsBase64, $secretKey, $orderCode);

            // =========================
            // 🔍 LOGS DEBUG
            // =========================
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

    // ==========================================
    // 🔐 FIRMA REDSYS OFICIAL (HMAC SHA256)
    // ==========================================
    private function generateSignature($paramsBase64, $secretKey, $orderCode)
    {
        $key = $secretKey; // ❌ NO base64_decode

        // 🔥 IV estándar Redsys
        $iv = "\0\0\0\0\0\0\0\0";

        // 🔥 SIN padding manual
        $derivedKey = openssl_encrypt(
            $orderCode,
            'DES-EDE3-CBC',
            $key,
            OPENSSL_RAW_DATA,
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
