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

            // ==========================
            // 📥 Obtener datos
            // ==========================
            $merchantParams = $request->input('Ds_MerchantParameters');
            $signature      = $request->input('Ds_Signature');

            if (!$merchantParams || !$signature) {
                Log::warning('Getnet notify inválido - faltan parámetros');
                return response('KO', 400);
            }

            // ==========================
            // 🔓 Decodificar parámetros
            // ==========================
            $decodedParams = json_decode(base64_decode($merchantParams), true);

            if (!$decodedParams) {
                Log::error('Error decodificando parámetros Getnet');
                return response('KO', 400);
            }

            Log::info('Getnet RAW', $decodedParams);

            // ==========================
            // 📊 Extraer datos
            // ==========================
            $orderCode = $decodedParams['Ds_Order'] ?? null;
            $response  = intval($decodedParams['Ds_Response'] ?? 9999);
            $authCode  = $decodedParams['Ds_AuthorisationCode'] ?? null;
            $amount    = intval($decodedParams['Ds_Amount'] ?? 0);

            if (!$orderCode) {
                return response('KO', 400);
            }

            // ==========================
            // 🔐 Validar firma
            // ==========================
            $secretKey = base64_decode(env('GETNET_SECRET'));

            $derivedKey = openssl_encrypt(
                $orderCode,
                'AES-128-ECB',
                $secretKey,
                OPENSSL_RAW_DATA
            );

            $expectedSignature = base64_encode(
                hash_hmac('sha256', $merchantParams, $derivedKey, true)
            );

            // 🔥 Comparación segura
            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Firma inválida', [
                    'expected' => $expectedSignature,
                    'received' => $signature
                ]);
                return response('KO', 400);
            }

            // ==========================
            // 🔎 Buscar pedido (CORRECTO)
            // ==========================
            $order = Order::where('order_code_bank', $orderCode)->first();

            if (!$order) {
                Log::error('Pedido no encontrado', ['order_code' => $orderCode]);
                return response('KO', 404);
            }

            // ==========================
            // 🔎 Obtener pago
            // ==========================
            $payment = Payment::where('order_id', $order->id)
                ->where('provider', 'getnet')
                ->first();

            if (!$payment) {
                Log::error('Pago no encontrado', ['order_id' => $order->id]);
                return response('KO', 404);
            }

            // ==========================
            // 🛡️ IDEMPOTENCIA
            // ==========================
            if ($payment->status === 'pagado') {
                return response('OK', 200);
            }

            // ==========================
            // 💰 Validar importe
            // ==========================
            $expectedAmount = (int) round($order->total * 100);

            if ($amount !== $expectedAmount) {
                Log::error('Importe no coincide', [
                    'order_id' => $order->id,
                    'expected' => $expectedAmount,
                    'received' => $amount
                ]);
                return response('KO', 400);
            }

            // ==========================
            // 💳 Procesar resultado
            // ==========================
            if ($response >= 0 && $response <= 99) {

                // ✔ PAGO CORRECTO
                $payment->update([
                    'status' => 'pagado',
                    'transaction_id' => $authCode,
                ]);

                $order->update([
                    'status' => 'pagado'
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'pagado',
                    'nota'     => 'Pago confirmado por Getnet',
                ]);

                Log::info('Pago confirmado', [
                    'order_id' => $order->id,
                    'auth_code' => $authCode
                ]);

            } else {

                // ❌ PAGO FALLIDO
                $payment->update([
                    'status' => 'failed',
                ]);

                $order->update([
                    'status' => 'failed'
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'failed',
                    'nota'     => 'Pago rechazado por Getnet',
                ]);

                Log::warning('Pago rechazado', [
                    'order_id' => $order->id,
                    'response' => $response
                ]);
            }

            return response('OK', 200);

        } catch (\Throwable $e) {

            Log::error('Error en notify Getnet', [
                'message' => $e->getMessage(),
            ]);

            return response('KO', 500);
        }
    }
}
