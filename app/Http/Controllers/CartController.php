<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Pack;

class CartController extends Controller
{
    private const MAX_QUANTITY = 5;
    private const MAX_TOTAL = 2000;

    private const MEASURE_ADJUST = [
        "90x190"  => -100,
        "135x190" => 0,
        "150x190" => 80,
    ];

    /**
     * Obtener carrito del usuario autenticado
     */
    private function getCart(): Cart
    {
        $user = Auth::user();
        if (!$user) abort(401, 'Debes iniciar sesión.');

        return Cart::with(['items.product', 'items.pack'])
            ->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Ver carrito
     */
    public function index()
    {
        $cart = $this->getCart();

        $items = $cart->items->map(function (CartItem $item) {
            $entity = $item->product ?? $item->pack;

            // ------------------- Precio unitario -------------------
            $price = $item->product
                ? (
                $item->product->category?->id === 76
                    ? ($item->product->promo_price ?? $item->product->price)
                    + $this->getMeasurePriceAdjustment($item->measure)
                    : ($item->product->promo_price ?? $item->product->price)
                )
                : ($item->pack->promo_price ?? $item->pack->original_price);

            // ------------------- Tipo logístico -------------------
            $logisticType = $item->product
                ? ($item->product->logistic_type ?? 'small')
                : ($item->pack->logistic_type ?? 'small');

            return [
                'id' => $item->id,
                'type' => $item->product ? 'product' : 'pack',
                'entity_id' => $entity->id,
                'name' => $entity->name ?? $entity->title,
                'price' => (float) $price,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->total_price,
                'measure' => $item->measure,

                // 🔥 CLAVE PARA CHECKOUT
                'logistic_type' => $logisticType,

                // ✅ IMAGEN CORRECTA SEGÚN TIPO
                'image' => $item->product
                    ? $item->product->images
                        ->sortBy('sort_order')
                        ->first()?->image_path
                    : $item->pack->image_url,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => (float) $items->sum('subtotal'),
            ],
        ]);
    }



    /**
     * Añadir producto o pack
     * POST /cart/items
     */
    public function add(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,pack',
            'id' => 'required|integer',
            'quantity' => 'integer|min:1',
            'measure' => 'nullable|string',
        ]);

        $cart = $this->getCart();
        $quantity = $request->input('quantity', 1);
        $measure = $request->measure;

        if ($request->type === 'product') {
            $product = Product::findOrFail($request->id);

            $item = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'pack_id' => null,           // 🔹 importante
                'measure' => $measure,
            ]);

            $price = $product->category?->id === 76
                ? ($product->promo_price ?? $product->price) + $this->getMeasurePriceAdjustment($measure)
                : ($product->promo_price ?? $product->price);

        } else { // pack
            $pack = Pack::findOrFail($request->id);

            $item = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'pack_id' => $pack->id,
                'product_id' => null,       // 🔹 importante
            ]);

            $price = $pack->promo_price ?? $pack->original_price;

            // Asignar measure solo si el pack requiere medida
            if ($pack->requires_measure) {
                if (!$measure) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes seleccionar una medida para este pack'
                    ], 422);
                }
                $item->measure = $measure;
            }
        }

        $newQuantity = min(self::MAX_QUANTITY, ($item->quantity ?? 0) + $quantity);

        if ($this->willExceedTotal($cart, $item, $newQuantity, $price)) {
            return response()->json([
                'success' => false,
                'message' => 'Total máximo permitido: ' . self::MAX_TOTAL . '€'
            ], 400);
        }

        $item->quantity = $newQuantity;
        $item->total_price = $price * $newQuantity;
        $item->reserved_until = now()->addDays(2);
        $item->save();

        $cart->touch();

        return $this->index();
    }


    /**
     * Actualizar cantidad
     * PUT /cart/items/{item}
     */
    public function update(Request $request, CartItem $item)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:' . self::MAX_QUANTITY,
            'measure' => 'nullable|string',
        ]);

        $cart = $this->getCart();

        abort_unless($item->cart_id === $cart->id, 403);

        $measure = $request->measure ?? $item->measure;

        $price = $item->product
            ? ($item->product->category?->id === 76
                ? ($item->product->promo_price ?? $item->product->price) + $this->getMeasurePriceAdjustment($measure)
                : ($item->product->promo_price ?? $item->product->price))
            : ($item->pack->promo_price ?? $item->pack->original_price);

        if ($this->willExceedTotal($cart, $item, $request->quantity, $price)) {
            return response()->json(['success' => false, 'message' => 'Total máximo superado'], 400);
        }

        $item->update([
            'quantity' => $request->quantity,
            'measure' => $measure,
            'total_price' => $price * $request->quantity,
        ]);

        $cart->touch();

        return $this->index();
    }

    /**
     * Eliminar item
     * DELETE /cart/items/{item}
     */
    public function remove(CartItem $item)
    {
        $cart = $this->getCart();

        abort_unless($item->cart_id === $cart->id, 403);

        $item->delete();
        $cart->touch();

        return $this->index();
    }

    /**
     * Vaciar carrito
     */
    public function empty()
    {
        $cart = $this->getCart();
        $cart->items()->delete();
        return $this->index();
    }

    /**
     * Total del carrito
     */
    public function total()
    {
        $cart = $this->getCart();
        return response()->json([
            'success' => true,
            'data' => ['total' => $cart->items->sum('total_price')],
        ]);
    }

    /**
     * Ajuste por medida
     */
    private function getMeasurePriceAdjustment(?string $measure): float
    {
        return self::MEASURE_ADJUST[$measure] ?? 0;
    }

    /**
     * Control total máximo
     */
    private function willExceedTotal(Cart $cart, CartItem $item, int $newQuantity, float $price): bool
    {
        $currentTotal = $cart->items->sum('total_price');
        $currentItemTotal = $item->exists ? $item->total_price : 0;
        $newTotal = ($currentTotal - $currentItemTotal) + ($price * $newQuantity);
        return $newTotal > self::MAX_TOTAL;
    }



/**
     * Carrito completo para admin
     */
    public function adminIndex()
    {
        $carts = Cart::with(['user', 'items.product', 'items.pack'])->orderByDesc('updated_at')->get();

        $data = $carts->map(fn($cart) => [
            'id' => $cart->id,
            'user' => [
                'id' => $cart->user->id ?? null,
                'name' => $cart->user->name ?? 'N/A',
                'email' => $cart->user->email ?? 'N/A',
                'role' => $cart->user->role ?? 'cliente',
            ],
            'total_items' => $cart->items->sum('quantity'),
            'total_price' => $cart->items->sum('total_price'),
            'last_updated' => $cart->updated_at->format('d/m/Y H:i:s'),
            'items' => $cart->items->map(fn($item) => [
                'id' => $item->id,
                'type' => $item->product ? 'product' : 'pack',
                'name' => $item->product->name ?? $item->pack->title ?? 'N/A',
                'price' => $item->product
                    ? ($item->product->category?->id === 76
                        ? ($item->product->promo_price ?? $item->product->price) + $this->getMeasurePriceAdjustment($item->measure)
                        : $item->product->promo_price ?? $item->product->price)
                    : ($item->pack->promo_price ?? $item->pack->original_price),
                'quantity' => $item->quantity,
                'subtotal' => $item->total_price ?? 0,
                'image' => optional($item->product ?? $item->pack)->images->sortBy('sort_order')->first()?->image_path,
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }




    /**
     * Checkout del carrito
     */
    public function checkout(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success'=>false,'error'=>'Debes iniciar sesión'],401);
        }

        $cart = $this->getCart();
        if (!$cart || $cart->items()->count() === 0) {
            return response()->json(['success'=>false,'error'=>'Carrito vacío']);
        }

        $cartItems = $cart->items()->with(['product', 'pack'])->get();

        $cartProducts = $cartItems->pluck('product_id')->toArray();
        $total = $cartItems->sum(fn($item) =>
            ($item->product->category?->id === 76
                ? ($item->product->promo_price ?? $item->product->price) + $this->getMeasurePriceAdjustment($item->measure)
                : ($item->product->promo_price ?? $item->product->price))
            * $item->quantity
        );

        $discount = 0;
        $couponCode = $request->coupon ?? null;
        $couponSource = null;

        if ($couponCode) {
            $res = app(\App\Http\Controllers\CouponController::class)
                ->validateCoupon(new \Illuminate\Http\Request([
                    'code' => $couponCode,
                    'email' => $user->email,
                    'cart_total' => $total,
                    'cart_products' => $cartProducts,
                ]));

            $resData = $res->getData();

            if ($resData->valid) {
                $couponSource = $resData->source;
                $discount = $resData->type === 'percent'
                    ? ($total * $resData->discount) / 100
                    : $resData->discount;

                // Marcar como usado
                if ($couponSource === 'newsletter') {
                    \App\Models\NewsletterSubscription::where('promo_code', $couponCode)
                        ->where('email', $user->email)
                        ->update(['redeemed' => true]);
                } else {
                    $coupon = \App\Models\Coupon::where('code', $couponCode)->first();
                    if ($coupon) {
                        $coupon->increment('used');
                    }
                }
            }
        }

        $totalWithDiscount = max(0, $total - $discount);

        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'total' => $totalWithDiscount,
            'shipping_address' => $request->shipping_address,
            'payment_method' => $request->payment_method ?? 'fake_bank',
            'status' => 'pendiente',
            'discount' => $discount,
            'coupon_code' => $couponCode,
        ]);

        foreach ($cartItems as $item) {
            $price = $item->product
                ? ($item->product->category?->id === 76
                    ? ($item->product->promo_price ?? $item->product->price) + $this->getMeasurePriceAdjustment($item->measure)
                    : ($item->product->promo_price ?? $item->product->price))
                : ($item->pack->promo_price ?? $item->pack->original_price);

            $order->items()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $price,
            ]);
        }

        $cart->items()->delete();

        return response()->json([
            'success' => true,
            'order' => $order->load('items'),
            'total' => $total,
            'discount' => $discount,
            'message' => 'Pago simulado exitoso',
        ]);
    }
}
