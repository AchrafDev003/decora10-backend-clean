<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    // -----------------------------
    // 🔹 Suscribirse y generar código
    // -----------------------------
    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:newsletters,email',
        ]);

        // Generar código único
        $promoCode = strtoupper(Str::random(8));

        $subscription = NewsletterSubscription::create([
            'email' => $request->email,
            'promo_code' => $promoCode,
            'redeemed' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Suscripción exitosa',
            'promo_code' => $subscription->promo_code,
            'source' => 'newsletter', // para integrarlo con cupones unificados
        ], 201);
    }

    // -----------------------------
    // 🔹 Validar código antes del checkout
    // -----------------------------
    public function validateCode(Request $request)
    {
        $request->validate([
            'promo_code' => 'required|string',
            'email' => 'required|email',
        ]);

        $subscription = NewsletterSubscription::where('promo_code', $request->promo_code)
            ->where('email', $request->email)
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Código no válido'
            ], 404);
        }

        if ($subscription->redeemed) {
            return response()->json([
                'success' => false,
                'message' => 'El código ya fue utilizado'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Código válido',
            'discount' => 10, // porcentaje fijo por newsletter
            'type' => 'percent',
            'source' => 'newsletter',
        ]);
    }

    // -----------------------------
    // 🔹 Marcar código como usado después del checkout
    // -----------------------------
    public function markAsUsed(Request $request)
    {
        $request->validate([
            'promo_code' => 'required|string',
            'email' => 'required|email',
        ]);

        $subscription = NewsletterSubscription::where('promo_code', $request->promo_code)
            ->where('email', $request->email)
            ->first();

        if ($subscription) {
            $subscription->update(['redeemed' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Código marcado como usado'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Código no encontrado'
        ], 404);
    }
}
