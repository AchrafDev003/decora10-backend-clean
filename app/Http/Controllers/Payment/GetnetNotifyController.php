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

            // 🔑 Activar debug manual
            $debug = $request->get('debug') == 1;

            // ==========================
            // 📦 INPUT
            // ==========================
            $merchantParams = $request->input('Ds_MerchantParameters');
            $signature      = $request->input('Ds_Signature');

            if ($debug) {
                return response()->json([
                    'step' => 'input',
                    'merchantParams' => $merchantParams,
                    'signature' => $signature,
                ]);
            }

            if (!$merchantParams || !$signature) {
                return response('KO', 400);
            }

            // ==========================
            // 📦 DECODE
            // ==========================
            $decoded = json_decode(base64_decode($merchantParams), true);

            if ($debug) {
                return response()->json([
                    'step' => 'decoded',
                    'decoded' => $decoded,
                ]);
            }

            if (!is_array($decoded)) {
                return response('KO', 400);
            }

            Log::info('GETNET NOTIFY RAW', $decoded);

            // ==========================
            // 📦 CAMPOS (IMPORTANTE MAYÚSCULAS)
            // ==========================
            $orderCode = $decoded['DS_ORDER'] ?? null;
            $response  = (int) ($decoded['DS_RESPONSE'] ?? 9999);
            $amount    = (int) ($decoded['DS_AMOUNT'] ?? 0);
            $authCode  = $decoded['DS_AUTHORISATIONCODE'] ?? null;

            if ($debug) {
                return response()->json([
                    'step' => 'fields',
                    'orderCode' => $orderCode,
                    'response' => $response,
                    'amount' => $amount,
                    'authCode' => $authCode,
                ]);
            }

            if (!$orderCode) {
                return response('KO', 400);
            }

            // ==========================
            // 🔐 FIRMA
            // ==========================
            $secretKey = config('getnet.secret');

            $order8 = substr($orderCode, 0, 8);

            $derivedKey = openssl_encrypt(
                $order8,
                'DES-EDE3-ECB',
                $secretKey,
                OPENSSL_RAW_DATA
            );

            $expectedSignature = base64_encode(
                hash_hmac('sha256', $merchantParams, $derivedKey, true)
            );

            if ($debug) {
                return response()->json([
                    'step' => 'signature',
                    'expected' => $expectedSignature,
                    'received' => $signature,
                    'match' => hash_equals($expectedSignature, $signature),
                ]);
            }

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Firma inválida', [
                    'expected' => $expectedSignature,
                    'received' => $signature
                ]);
                return response('KO', 400);
            }

            // ==========================
            // 🧾 BUSCAR ORDER
            // ==========================
            $order = Order::where('order_code_bank', $orderCode)->first();

            if ($debug) {
                return response()->json([
                    'step' => 'order_lookup',
                    'orderCode' => $orderCode,
                    'order' => $order,
                ]);
            }

            if (!$order) {
                return response('KO', 404);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('provider', 'getnet')
                ->first();

            if ($debug) {
                return response()->json([
                    'step' => 'payment_lookup',
                    'payment' => $payment,
                ]);
            }

            if (!$payment) {
                return response('KO', 404);
            }

            // ==========================
            // 🔁 IDEMPOTENCIA
            // ==========================
            if ($payment->status === 'pagado') {
                return response('OK', 200)
                    ->header('Content-Type', 'text/plain');
            }

            // ==========================
            // 💰 VALIDAR IMPORTE
            // ==========================
            $expectedAmount = (int) round($order->total * 100);

            if ($debug) {
                return response()->json([
                    'step' => 'amount_check',
                    'expected' => $expectedAmount,
                    'received' => $amount,
                    'match' => $amount === $expectedAmount,
                ]);
            }

            if ($amount !== $expectedAmount) {
                Log::error('Importe incorrecto', [
                    'expected' => $expectedAmount,
                    'received' => $amount
                ]);
                return response('KO', 400);
            }

            // ==========================
            // 💳 RESULTADO PAGO
            // ==========================
            if ($response === 0) {

                $payment->update([
                    'status' => 'pagado',
                    'transaction_id' => $authCode,
                ]);

                $order->update(['status' => 'pagado']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'pagado',
                    'nota'     => 'Pago confirmado por Getnet',
                ]);

                Log::info('PAGO OK', ['order' => $order->id]);

            } else {

                $payment->update(['status' => 'failed']);
                $order->update(['status' => 'failed']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'failed',
                    'nota'     => 'Pago rechazado por Redsys',
                ]);

                Log::warning('PAGO KO', [
                    'order' => $order->id,
                    'response' => $response
                ]);
            }

            // ==========================
            // ✅ RESPUESTA FINAL
            // ==========================
            return response('OK', 200)
                ->header('Content-Type', 'text/plain');

        } catch (\Throwable $e) {

            Log::error('Notify exception', [
                'message' => $e->getMessage()
            ]);

            return response('KO', 500);
        }
    }
}
