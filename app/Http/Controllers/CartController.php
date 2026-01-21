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

use App\Models\Pack;


class CartController extends Controller
{
    private const MAX_QUANTITY = 5;
    private const MAX_TOTAL = 2000;

    /**
     * Obtener carrito del usuario autenticado
     */
    private function getCart()
    {
        $user = Auth::user();
        if (!$user) abort(401, 'Debes iniciar sesión.');

        return Cart::with(['items.product', 'items.pack'])->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Ver carrito
     */
    public function index()
    {
        $cart = $this->getCart();

        $items = $cart->items->map(function ($item) {
            $type = $item->product ? 'product' : 'pack';
            $entity = $item->product ?? $item->pack;

            return [
                'id' => $item->id,
                'type' => $type,
                'entity_id' => $entity->id,
                'name' => $entity->name ?? $entity->title,
                'price' => (float) ($entity->promo_price ?? $entity->price ?? $entity->original_price),
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->total_price,
                'image' => optional($entity->images->sortBy('sort_order')->first())->image_path ?? null,
                'images' => $entity->images
                    ->sortBy('sort_order')
                    ->map(fn($img) => ['image_path' => $img->image_path])
                    ->values(),
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
     * Ver todos los carritos (admin)
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
                'price' => $item->product->promo_price ?? $item->product->price ?? $item->pack->promo_price ?? $item->pack->original_price ?? 0,
                'quantity' => $item->quantity,
                'subtotal' => $item->total_price ?? 0,
                'image' => optional($item->product ?? $item->pack)->images->sortBy('sort_order')->first()?->image_path,
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Añadir producto o pack al carrito
     */
    public function add(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,pack',
            'id' => 'required|integer',
            'quantity' => 'integer|min:1',
        ]);

        $cart = $this->getCart();
        $quantity = $request->input('quantity', 1);

        $itemType = $request->type;
        $entityId = $request->id;

        $item = null;

        if ($itemType === 'product') {
            $product = Product::findOrFail($entityId);
            $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id]);
            $item->setRelation('product', $product);
            $price = $product->promo_price ?? $product->price;
            $availableStock = $product->quantity - $item->quantity;
            if ($quantity > $availableStock) return response()->json(['success' => false, 'message' => "Solo quedan {$availableStock} unidades de {$product->name}"], 400);
        } else {
            $pack = Pack::findOrFail($entityId);
            $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'pack_id' => $pack->id]);
            $item->setRelation('pack', $pack);
            $price = $pack->promo_price ?? $pack->original_price;
        }

        $newQuantity = min(self::MAX_QUANTITY, ($item->quantity ?? 0) + $quantity);

        // Validar total máximo
        if ($this->willExceedTotal($cart, $item, $newQuantity)) {
            return response()->json(['success' => false, 'message' => "No se puede agregar más. Total máximo: ".self::MAX_TOTAL."€"], 400);
        }

        // Dentro de add()
        $item->quantity = $newQuantity;
        $price = $item->product
            ? ($item->product->promo_price ?? $item->product->price)
            : ($item->pack->promo_price ?? $item->pack->original_price);

// Asignamos total_price
        $item->total_price = $price * $item->quantity;

        $item->reserved_until = now()->addDays(2);
        $item->save();
        $cart->touch();


        return $this->index();
    }

    /**
     * Actualizar cantidad
     */
    public function update(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,pack',
            'id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getCart();
        $quantity = $request->quantity;
        $type = $request->type;
        $id = $request->id;

        $item = $cart->items()
            ->when($type === 'product', fn($q) => $q->where('product_id', $id))
            ->when($type === 'pack', fn($q) => $q->where('pack_id', $id))
            ->firstOrFail();

        $price = $item->product ? ($item->product->promo_price ?? $item->product->price) : ($item->pack->promo_price ?? $item->pack->original_price);
// Dentro de update()
        $item->quantity = min(self::MAX_QUANTITY, $quantity);

        $price = $item->product
            ? ($item->product->promo_price ?? $item->product->price)
            : ($item->pack->promo_price ?? $item->pack->original_price);

// Asignamos total_price
        $item->total_price = $price * $item->quantity;

        if ($this->willExceedTotal($cart, $item, $item->quantity)) {
            return response()->json([
                'success' => false,
                'message' => "No se puede actualizar. Total máximo: " . self::MAX_TOTAL . "€"
            ], 400);
        }

        $item->save();
        $cart->touch();


        return $this->index();
    }

    /**
     * Remover item
     */
    public function remove(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,pack',
            'id' => 'required|integer',
        ]);

        $cart = $this->getCart();

        $cart->items()
            ->when($request->type === 'product', fn($q) => $q->where('product_id', $request->id))
            ->when($request->type === 'pack', fn($q) => $q->where('pack_id', $request->id))
            ->delete();

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
     * Chequear total máximo
     */
    private function willExceedTotal(Cart $cart, CartItem $item, int $newQuantity): bool
    {
        $currentTotal = $cart->items->sum('total_price');
        $currentItemTotal = $item->exists ? $item->total_price : 0;
        $price = $item->product ? ($item->product->promo_price ?? $item->product->price) : ($item->pack->promo_price ?? $item->pack->original_price);
        $newTotal = ($currentTotal - $currentItemTotal) + ($price * $newQuantity);
        return $newTotal > self::MAX_TOTAL;
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


}
