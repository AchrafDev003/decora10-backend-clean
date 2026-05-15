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
                    'error' => 'Order not available'
                ], 400);
            }

            if ($order->payment?->status === 'pagado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Already paid'
                ], 400);
            }

            if ($order->total <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid total'
                ], 422);
            }

            // ==========================
            // AMOUNT
            // ==========================
            $amount = (int) round($order->total * 100);

            // ==========================
            // ORDER CODE (12 DIGITS FIXED)
            // ==========================
            $orderCode = str_pad((string)$order->id, 12, '0', STR_PAD_LEFT);

            $order->update([
                'order_code_bank' => $orderCode
            ]);

            // ==========================
            // CONFIG
            // ==========================
            $merchant = config('getnet.merchant');
            $terminal = (string) config('getnet.terminal');
            $secret   = config('getnet.secret');
            $url      = config('getnet.url');

            // ==========================
            // PARAMS
            // ==========================
            $params = [
                "DS_MERCHANT_AMOUNT" => (string) $amount,
                "DS_MERCHANT_ORDER" => $orderCode,
                "DS_MERCHANT_MERCHANTCODE" => $merchant,
                "DS_MERCHANT_CURRENCY" => "978",
                "DS_MERCHANT_TRANSACTIONTYPE" => "0",
                "DS_MERCHANT_TERMINAL" => $terminal,
                "DS_MERCHANT_MERCHANTURL" => url("/api/v1/payment/notify"),
                "DS_MERCHANT_URLOK" => env('FRONTEND_URL') . "/gracias",
                "DS_MERCHANT_URLKO" => env('FRONTEND_URL') . "/checkout?error=pago",
            ];

            $base64 = base64_encode(json_encode($params));

            $signature = $this->sign($base64, $secret, $orderCode);

            Log::info('PAYMENT INIT', [
                'order' => $orderCode,
                'amount' => $amount,
            ]);

            return response()->json([
                'success' => true,
                'gatewayUrl' => $url,
                'params' => $base64,
                'signature' => $signature,
                'version' => 'HMAC_SHA256_V1',
            ]);

        } catch (\Throwable $e) {

            Log::error('PAYMENT CREATE ERROR', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal error'
            ], 500);
        }
    }

    private function sign($params, $key, $order)
    {
        $order8 = substr($order, 0, 8);

        $iv = str_repeat(chr(0), 8);

        $key = openssl_encrypt(
            $order8,
            'DES-EDE3-ECB',
            $key,
            OPENSSL_RAW_DATA
        );

        return base64_encode(hash_hmac('sha256', $params, $key, true));
    }
}
