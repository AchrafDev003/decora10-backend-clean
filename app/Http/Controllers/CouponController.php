<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\User;
use App\Models\Product;
use App\Models\NewsletterSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    // =======================================
    // 🔹 Obtener todos los cupones
    // =======================================
    public function index()
    {
        return response()->json(Coupon::all());
    }

    public function active()
    {
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->whereNull('user_id') // 👈 evitar mostrar cupones individuales
            ->whereNull('product_id')
            ->whereNull('category_id')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->get();

        return response()->json($coupons);
    }


    public function toggleStatus($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = ! $coupon->is_active;
        $coupon->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del cupón actualizado.',
            'coupon' => $coupon
        ]);
    }



    // =======================================
    // 🔹 Crear nuevo cupón
    // =======================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'discount' => 'required|numeric|min:0',
            'user_id' => 'nullable|exists:users,id',
            'used' => 'boolean',
            'used_count' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'min_purchase' => 'nullable|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'campaign' => 'nullable|string|max:100',
            'source' => 'required|in:manual,newsletter,affiliate',
            'customer_type' => 'required|in:all,cliente,cliente_fiel,admin,dueno',
            'is_active' => 'boolean', // 👈 nuevo campo agregado
        ]);

        // Si no se envía, por defecto activo
        $data['is_active'] = $data['is_active'] ?? true;

        // Garantizar used_count = 0 por defecto
        $data['used_count'] = $data['used_count'] ?? 0;

        // Si el cupón llega con max_uses y ya se alcanzó el límite
        if (!empty($data['max_uses']) && $data['used_count'] >= $data['max_uses']) {
            $data['used'] = true;
        } else {
            $data['used'] = $data['used'] ?? false;
        }

        $coupon = Coupon::create($data);

        return response()->json([
            'message' => 'Cupón creado exitosamente.',
            'coupon' => $coupon
        ], 201);
    }

    // =======================================
    // 🔹 Mostrar cupón por ID
    // =======================================
    public function show($id)
    {
        $coupon = Coupon::findOrFail($id);
        return response()->json($coupon);
    }

    // =======================================
    // 🔹 Actualizar cupón
    // =======================================
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $data = $request->validate([
            'code' => ['string', 'max:50', Rule::unique('coupons', 'code')->ignore($id)],
            'type' => 'in:fixed,percent',
            'discount' => 'numeric|min:0',
            'user_id' => 'nullable|exists:users,id',
            'used' => 'boolean',
            'used_count' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'min_purchase' => 'nullable|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'campaign' => 'nullable|string|max:100',
            'source' => 'in:manual,newsletter,affiliate',
            'customer_type' => 'in:all,cliente,cliente_fiel,admin,dueno',
            'is_active' => 'boolean', // 👈 nuevo campo
        ]);

        // Si no se manda, mantener el valor anterior de is_active
        if (!isset($data['is_active'])) {
            $data['is_active'] = $coupon->is_active;
        }

        // -----------------------------
        // 🔹 Sincronización de estados
        // -----------------------------

        // Si cambian used_count o max_uses, recalcular used flag
        if (isset($data['used_count']) && isset($data['max_uses'])) {
            if ($data['used_count'] >= $data['max_uses']) {
                $data['used'] = true;
            } elseif (!isset($data['used'])) {
                $data['used'] = false;
            }
        } elseif (isset($data['used_count']) && $coupon->max_uses !== null) {
            if ($data['used_count'] >= $coupon->max_uses) {
                $data['used'] = true;
            }
        } elseif (isset($data['max_uses']) && !isset($data['used_count'])) {
            if ($coupon->used_count >= $data['max_uses']) {
                $data['used'] = true;
            }
        }

        // 🔒 Evitar inconsistencias
        if (isset($data['used_count'], $data['max_uses']) && $data['used_count'] > $data['max_uses']) {
            $data['used_count'] = $data['max_uses'];
            $data['used'] = true;
        }

        // Si el cupón se desactiva manualmente, marcarlo como no usable
        if (isset($data['is_active']) && !$data['is_active']) {
            $data['used'] = $data['used'] ?? false; // mantener consistencia
        }

        $coupon->update($data);

        return response()->json([
            'message' => 'Cupón actualizado exitosamente.',
            'coupon' => $coupon->fresh()
        ]);
    }
    // =======================================
    // 🔹 Validar cupón en checkout (y aplicar uso)
    // =======================================
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'email' => 'required|email',
            'cart_total' => 'required|numeric|min:0|max:50000',
            'cart_products' => 'required|array',
        ]);

        $code = $request->input('code');
        $email = $request->input('email');
        $cartTotal = $request->input('cart_total');
        $cartProducts = $request->input('cart_products');
        $user = User::where('email', $email)->first();

        // 1️⃣ Newsletter
        $newsletterCoupon = NewsletterSubscription::where('promo_code', $code)
            ->where('email', $email)
            ->where('redeemed', false)
            ->first();

        if ($newsletterCoupon) {
            return response()->json([
                'valid' => true,
                'discount' => $newsletterCoupon->discount ?? 10,
                'type' => $newsletterCoupon->type ?? 'percent',
                'source' => 'newsletter',
            ]);
        }

        // 2️⃣ Buscar cupón
        $coupon = Coupon::where('code', $code)
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')->orWhere('user_id', $user?->id);
            })
            ->where('used', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Código inválido o expirado.'
            ]);
        }

        // Validaciones de cliente, producto, categoría y mínimo de compra
        $userType = $user?->type ?? 'cliente';
        if ($coupon->customer_type !== 'all' && $coupon->customer_type !== $userType) {
            return response()->json([
                'valid' => false,
                'message' => 'Este cupón no está disponible para tu tipo de cliente.'
            ]);
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            $coupon->update(['used' => true]);
            return response()->json([
                'valid' => false,
                'message' => 'Este cupón ha alcanzado su límite de usos.'
            ]);
        }

        if ($coupon->product_id && !in_array($coupon->product_id, $cartProducts)) {
            return response()->json([
                'valid' => false,
                'message' => 'Este cupón solo aplica a productos específicos.'
            ]);
        }

        if ($coupon->category_id) {
            $categoryMatch = Product::whereIn('id', $cartProducts)
                ->where('category_id', $coupon->category_id)
                ->exists();
            if (!$categoryMatch) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Este cupón solo aplica a una categoría específica.'
                ]);
            }
        }

        if ($coupon->min_purchase && $cartTotal < $coupon->min_purchase) {
            return response()->json([
                'valid' => false,
                'message' => 'El total del carrito no cumple con el mínimo requerido.'
            ]);
        }

        // Incrementar contador de uso seguro
        DB::transaction(function () use ($coupon) {
            $fresh = Coupon::lockForUpdate()->find($coupon->id);
            if ($fresh->max_uses !== null && $fresh->used_count >= $fresh->max_uses) {
                throw new \Exception('Cupón ya agotado');
            }
            $fresh->increment('used_count');
            if ($fresh->max_uses !== null && $fresh->used_count >= $fresh->max_uses) {
                $fresh->used = true;
                $fresh->save();
            }
        });

        $coupon = $coupon->fresh();

        return response()->json([
            'valid' => true,
            'discount' => $coupon->discount,
            'type' => $coupon->type,
            'source' => $coupon->source,
            'used_count' => $coupon->used_count,
            'max_uses' => $coupon->max_uses,
            'expires_at' => $coupon->expires_at,
        ]);
    }


    // =======================================
    // 🔹 Eliminar cupón
    // =======================================
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json(['message' => 'Cupón eliminado exitosamente.'], 204);
    }
}
