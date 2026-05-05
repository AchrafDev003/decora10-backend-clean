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

            $merchantParams = $request->input('Ds_MerchantParameters');
            $signature      = $request->input('Ds_Signature');

            if (!$merchantParams || !$signature) {
                Log::warning('Notify inválido - faltan parámetros');
                return response('KO', 400);
            }

            // ==========================
            // 🔓 Decodificar parámetros
            // ==========================
            $decodedParams = json_decode(base64_decode($merchantParams), true);

            if (!$decodedParams) {
                Log::error('Error decodificando parámetros');
                return response('KO', 400);
            }

            // ==========================
            // 📊 Datos principales
            // ==========================
            $orderCode = $decodedParams['Ds_Order'] ?? null;
            $response  = intval($decodedParams['Ds_Response'] ?? 9999);
            $authCode  = $decodedParams['Ds_AuthorisationCode'] ?? null;
            $amount    = intval($decodedParams['Ds_Amount'] ?? 0);

            if (!$orderCode) {
                return response('KO', 400);
            }

            // ==========================
            // 🔐 VALIDAR FIRMA (CORRECTO)
            // ==========================
            $secretKey = base64_decode(env('GETNET_SECRET'));

            // 🔥 CLAVE DERIVADA (igual que en createPayment)
            $derivedKey = openssl_encrypt(
                $orderCode,
                'AES-128-ECB',
                $secretKey,
                OPENSSL_RAW_DATA
            );

            $expectedSignature = base64_encode(
                hash_hmac('sha256', $merchantParams, $derivedKey, true)
            );

            if ($expectedSignature !== $signature) {
                Log::error('Firma inválida', [
                    'expected' => $expectedSignature,
                    'received' => $signature
                ]);
                return response('KO', 400);
            }

            // ==========================
            // 🔎 Buscar pedido
            // ==========================
            $orderId = (int) $orderCode;
            $order = Order::find($orderId);

            if (!$order) {
                Log::error('Pedido no encontrado', ['orderCode' => $orderCode]);
                return response('KO', 404);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('provider', 'getnet')
                ->first();

            if (!$payment) {
                Log::error('Pago no encontrado', ['order_id' => $order->id]);
                return response('KO', 404);
            }

            // ==========================
            // 🛡️ Idempotencia REAL
            // ==========================
            if ($payment->status === 'paid') {
                return response('OK', 200);
            }

            // ==========================
            // 🔐 Validar importe
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
            // 💳 Resultado del pago
            // ==========================
            if ($response >= 0 && $response <= 99) {

                $payment->update([
                    'status' => 'paid',
                    'transaction_id' => $authCode,
                ]);

                $order->update([
                    'status' => 'procesando'
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'procesando',
                    'nota'     => 'Pago confirmado por Getnet',
                ]);

                Log::info('Pago confirmado', [
                    'order_id' => $order->id,
                    'auth_code' => $authCode
                ]);

            } else {

                $payment->update([
                    'status' => 'failed',
                ]);

                $order->update([
                    'status' => 'cancelado'
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => 'cancelado',
                    'nota'     => 'Pago rechazado por Getnet',
                ]);

                Log::warning('Pago rechazado', [
                    'order_id' => $order->id,
                    'response' => $response
                ]);
            }

            return response('OK', 200);

        } catch (\Throwable $e) {

            Log::error('Error notify', [
                'message' => $e->getMessage(),
            ]);

            return response('KO', 500);
        }
    }
}
