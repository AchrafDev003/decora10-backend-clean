<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Mail\AdminCartNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
class CartController extends Controller
{
    private const MAX_QUANTITY = 5;
    private const MAX_TOTAL = 2000;

    /**
     * Obtener carrito del usuario autenticado con items y productos
     */
    private function getCart()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Debes iniciar sesión.');
        }

        return Cart::with('items.product')->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Ver carrito
     */
    public function index()
    {
        $cart = $this->getCart();

        $items = $cart->items->map(function ($item) {
            $product = $item->product;

            return [
                'id' => $item->id,
                'product_id' => $product->id,
                'name' => $product->name,

                // 🔥 LOGÍSTICA (CLAVE)
                'logistic_type' => $product->logistic_type ?? 'small',

                // Precio unitario
                'price' => (float) ($product->promo_price ?? $product->price),

                'quantity' => (int) $item->quantity,

                // Subtotal real
                'subtotal' => (float) $item->total_price,

                // Imagen principal (primera)
                'image' => optional(
                    $product->images->sortBy('position')->first()
                )?->image_path,

                // Todas las imágenes
                'images' => $product->images
                    ->sortBy('position')
                    ->map(fn ($img) => [
                        'image_path' => $img->image_path
                    ])
                    ->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => (float) $items->sum('subtotal'),
            ]
        ]);
    }


    // Mostrar todos los carritos con sus usuarios y productos
    public function adminIndex()
    {
        $carts = Cart::with(['user', 'items.product'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = $carts->map(function ($cart) {
            $items = $cart->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product->name ?? 'Producto eliminado',
                    'price' => $item->product->promo_price ?? $item->product->price ?? 0,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->total_price ?? 0,
                    'image' => $item->product->image ?? null,
                ];
            });

            return [
                'id' => $cart->id,
                'user' => [
                    'id' => $cart->user->id ?? null,
                    'name' => $cart->user->name ?? 'N/A',
                    'email' => $cart->user->email ?? 'N/A',
                    'role' => $cart->user->role ?? 'cliente',
                ],
                'total_items' => $items->sum('quantity'),
                'total_price' => $items->sum('subtotal'),
                'last_updated' => $cart->updated_at->format('d/m/Y H:i:s'),
                'items' => $items,
            ];
        });

        // ✅ Devuelve la respuesta JSON para que React la reciba
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }





    /**
     * Añadir producto al carrito
     */
    public function add(Request $request, $productId)
    {
        $request->validate([
            'quantity' => 'integer|min:1'
        ]);

        $cart = $this->getCart();
        $product = Product::findOrFail($productId);

        // Obtener item existente o crear uno nuevo
        $item = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id
        ]);

        // Asegurar relación del producto (evita queries implícitas)
        $item->setRelation('product', $product);

        if (!$item->exists) {
            $item->quantity = 0;
            $item->total_price = 0;
        }

        $quantityToAdd = $request->input('quantity', 1);

        // Validar stock disponible
        $availableStock = $product->quantity - $item->quantity;
        if ($quantityToAdd > $availableStock) {
            return response()->json([
                'success' => false,
                'message' => "Solo quedan {$availableStock} unidades de {$product->name}."
            ], 400);
        }

        $price = $product->promo_price ?? $product->price;
        $newQuantity = min(self::MAX_QUANTITY, $item->quantity + $quantityToAdd);

        // Validar total máximo del carrito (TOTAL REAL)
        if ($this->willExceedTotal($cart, $item, $newQuantity)) {
            return response()->json([
                'success' => false,
                'message' => "No se puede agregar más productos. El total del carrito no puede superar " . self::MAX_TOTAL . "€."
            ], 400);
        }

        // Persistir cambios
        $item->quantity = $newQuantity;
        $item->total_price = $price * $newQuantity;

        // Reservar producto por 2 días
        $item->reserved_until = now()->addDays(2);
        $item->save();

        // Actualizar timestamp del carrito
        $cart->touch();

        // Notificación al admin (no bloquea la respuesta)
        Mail::to('decora10.colchon10@gmail.com')
            ->send(new AdminCartNotification($cart, $product, $quantityToAdd));

        return $this->index();
    }




    /**
     * Actualizar cantidad de un producto
     */
    public function update(Request $request, $productId)
    {
        $request->validate([
            'quantity' => 'integer|min:1'
        ]);

        $cart = $this->getCart();

        $item = $cart->items()
            ->where('product_id', $productId)
            ->firstOrFail();

        $product = $item->product;

        // Garantizar relación (consistencia con add)
        $item->setRelation('product', $product);

        $requestedQuantity = $request->input('quantity', 1);

        // Validar stock disponible
        if ($requestedQuantity > $product->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Solo hay {$product->quantity} unidades disponibles de {$product->name}"
            ], 400);
        }

        $newQuantity = min(self::MAX_QUANTITY, $requestedQuantity);
        $price = $product->promo_price ?? $product->price;

        // Validar total máximo del carrito (TOTAL REAL)
        if ($this->willExceedTotal($cart, $item, $newQuantity)) {
            return response()->json([
                'success' => false,
                'message' => "No se puede actualizar la cantidad. El total del carrito no puede superar " . self::MAX_TOTAL . "€."
            ], 400);
        }

        // Persistir cambios
        $item->quantity = $newQuantity;
        $item->total_price = $price * $newQuantity;
        $item->save();

        // Actualizar timestamp del carrito
        $cart->touch();

        // Notificación al admin (silenciosa)
        Mail::to('decora10.colchon10@gmail.com')
            ->send(new AdminCartNotification($cart, $product, $newQuantity));

        return $this->index();
    }



    /**
     * Eliminar producto del carrito
     */
    public function remove($productId)
    {
        $cart = $this->getCart();
        $cart->items()->where('product_id', $productId)->delete();

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
     * Obtener total del carrito
     */
    public function total()
    {
        $cart = $this->getCart();
        $total = $cart->items->sum('total_price');

        return response()->json(['success' => true, 'data' => ['total' => $total]]);
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

        $cartItems = $cart->items()->with('product')->get();
        $cartProducts = $cartItems->pluck('product_id')->toArray();
        $total = $cartItems->sum(fn($item) => ($item->product->promo_price ?? $item->product->price) * $item->quantity);

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
                        $coupon->increment('used'); // contador de usos
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
            $order->items()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->promo_price ?? $item->product->price,
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




    /**
     * Chequear si el total del carrito excedería el máximo
     */
    private function willExceedTotal(Cart $cart, CartItem $item, int $newQuantity): bool
    {
        $currentCartTotal = $cart->items()->sum('total_price');

        $currentItemTotal = $item->exists
            ? $item->total_price
            : 0;

        $price = $item->product->promo_price ?? $item->product->price;

        $newTotal = ($currentCartTotal - $currentItemTotal) + ($price * $newQuantity);

        return $newTotal > self::MAX_TOTAL;
    }

}
