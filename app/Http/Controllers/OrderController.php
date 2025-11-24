<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request; //
use App\Http\Controllers\Payment\StripeController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Cart;
use App\Models\NewsletterSubscription;
use App\Models\Address;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Coupon;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\OrderConfirmation;
use App\Services\StripeService;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Mostrar todos los pedidos
    public function index(Request $request)
    {
        $query = Order::with('user', 'orderItems.product', 'address');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('user', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($orders);
    }

    // Mostrar un pedido específico
    public function show($id)
    {
        $order = Order::with('orderItems.product', 'statusHistory', 'address')->findOrFail($id);
        return response()->json(['order' => $order]);
    }

    // Mostrar todos los pedidos para admin o dueño (ERP)
    public function adminOrders(Request $request)
    {
        // Solo usuarios con rol admin o dueño deberían acceder (ya lo controla el middleware)
        $query = Order::with(['user', 'orderItems.product', 'address'])
            ->orderBy('created_at', 'desc');

        // Filtros opcionales
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('order_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->paginate($perPage);

        // Estructurar datos compatibles con React (tabla ERP)
        $data = $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'user' => [
                    'id' => $order->user->id ?? null,
                    'name' => $order->user->name ?? 'N/A',
                    'email' => $order->user->email ?? 'N/A',
                ],
                'total' => $order->total,
                'discount' => $order->discount,
                'status' => $order->status,
                'shipping_address' => $order->shipping_address,
                'payment_method' => $order->payment_method,
                'tracking_number' => $order->tracking_number,
                'courier' => $order->courier,
                'mobile1' => $order->mobile1,
                'mobile2' => $order->mobile2,
                'estimated_delivery_date' => $order->estimated_delivery_date,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product->name ?? 'Producto eliminado',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
    }


    // Crear un nuevo pedido
    public function store(StoreOrderRequest $request)
    {
        $user = auth()->user();
        $request->merge(['promo_code' => trim($request->promo_code)]);

        // ------------------- Buscar carrito -------------------
        $cart = Cart::with('items.product')->where('user_id', $user->id)->firstOrFail();
        if ($cart->items->isEmpty()) {
            return response()->json(['error' => 'El carrito está vacío.'], 400);
        }

        // ------------------- Validar dirección -------------------
        $validatedAddress = $request->validated();

        // ------------------- Totales y stock -------------------
        $totals = $this->calculateCartTotals($cart);
        if (!empty($totals['outOfStock'])) {
            return response()->json([
                'error' => 'Productos sin stock suficiente: ' . implode(', ', $totals['outOfStock'])
            ], 400);
        }

        // ------------------- Cupón -------------------
        $couponData = $this->applyCoupon($request->promo_code, $user, $totals['subtotal']);
        $subtotal = $totals['subtotal'];
        $discount = $couponData['discount'] ?? 0;
        $couponType = $couponData['coupon_type'] ?? null;
        $couponCode = $couponData['coupon_code'] ?? null;

        $taxRate = 21;
        $tax = $subtotal * ($taxRate / 100);
        $finalTotal = max($subtotal + $tax - $discount, 0);

        DB::beginTransaction();
        try {
            // ------------------- Crear dirección -------------------
            $address = Address::create([
                'user_id' => $user->id,
                'type' => $validatedAddress['type'] ?? 'domicilio',
                'line1' => $validatedAddress['line1'],
                'line2' => $validatedAddress['line2'] ?? null,
                'city' => $validatedAddress['city'],
                'zipcode' => $validatedAddress['zipcode'] ?? null,
                'country' => $validatedAddress['country'],
                'mobile1' => $validatedAddress['mobile1'],
                'mobile2' => $validatedAddress['mobile2'] ?? null,
                'additional_info' => $validatedAddress['additional_info'] ?? null,
                'is_default' => true,
            ]);

            // ------------------- Crear pedido -------------------
            $tracking_number = 'DEC-ORD-' . strtoupper(Str::random(8));
            $estimatedDelivery = Carbon::now()->addDays(5);

            $order = Order::create([
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'tax_rate' => $taxRate,
                'total' => $finalTotal,
                'shipping_address' => trim($address->line1 . ' ' . ($address->line2 ?? '')),
                'address_id' => $address->id,
                'mobile1' => $address->mobile1,
                'mobile2' => $address->mobile2,
                'payment_method' => $request->payment_method,
                'status' => 'pendiente',
                'tracking_number' => $tracking_number,
                'courier' => null,
                'estimated_delivery_date' => $estimatedDelivery,
                'promo_code' => $couponCode,
                'coupon_type' => $couponType,
            ]);

            // ------------------- Crear items y reducir stock -------------------
            foreach ($cart->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if (!$product || $product->quantity < $item->quantity) {
                    throw new \Exception("Stock insuficiente para {$item->product->name}");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                    'cost' => $product->cost ?? null,
                ]);

                $product->decrement('quantity', $item->quantity);
            }

            // ------------------- Historial de estado -------------------
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pendiente',
                'nota' => 'Pedido creado correctamente',
            ]);

            // ------------------- Validar método de pago -------------------
            $paymentOptions = ['card', 'paypal'];
            if ($request->type === 'local') {
                $paymentOptions[] = 'cash';
                $paymentOptions[] = 'bizum';
            }
            if ($request->type === 'domicilio' && $request->country === 'ES') {
                $paymentOptions[] = 'bizum';
            }
            $request->validate(['payment_method' => ['required', Rule::in($paymentOptions)]]);

            // ------------------- Crear Payment -------------------
            $transactionId = null;
            $paymentStatus = 'pendiente';
            $paidAt = null;
            $meta = null;
            $provider = $request->payment_method;
            $clientSecret = null;

            if (in_array($request->payment_method, ['card', 'bizum'])) {
                $paymentIntent = StripeService::createIntent([
                    'amount' => $finalTotal,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'method' => $request->payment_method,
                    'description' => 'Pago Decora10 pedido #' . $tracking_number,
                ]);

                $transactionId = $paymentIntent->id;
                $meta = json_encode($paymentIntent);
                $clientSecret = $paymentIntent->client_secret;

                if ($paymentIntent->status === 'succeeded') {
                    $paymentStatus = 'paid';
                    $paidAt = now();
                }
            }

            $payment = Payment::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'method' => $request->payment_method,
                'provider' => $provider,
                'status' => $paymentStatus,
                'paid_at' => $paidAt,
                'amount' => $finalTotal,
                'transaction_id' => $transactionId,
                'meta' => $meta,
            ]);

            // ------------------- Vaciar carrito -------------------
            $cart->items()->delete();

            // ------------------- Actualizar cupón -------------------
            if (isset($couponData['coupon'])) {
                $couponData['coupon']->increment('used_count');
                if ($couponData['coupon']->max_uses !== null && $couponData['coupon']->used_count >= $couponData['coupon']->max_uses) {
                    $couponData['coupon']->update(['is_active' => false]);
                }
            }

            DB::commit();

            // ------------------- Generar PDF y enviar email -------------------
            $this->generateOrderPDF($order);

            return response()->json([
                'message' => 'Pedido creado exitosamente.',
                'order' => $order->load('orderItems.product', 'statusHistory', 'address'),
                'tracking_number' => $order->tracking_number,
                'discount' => $discount,
                'promo_applied' => $couponCode,
                'payment_client_secret' => $clientSecret,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al procesar el pedido.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Calcular totales del carrito y verificar stock
     */
    private function calculateCartTotals($cart)
    {
        $subtotal = 0;
        $outOfStock = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product || $product->quantity < $item->quantity) {
                $outOfStock[] = $product->name ?? 'Producto eliminado';
            }
            $subtotal += $item->quantity * ($product->price ?? 0);
        }
        return compact('subtotal','outOfStock');
    }

    /**
     * Aplicar cupón y validar reglas
     */
    private function applyCoupon($couponCode, $user, $cartTotal, array $cartProducts = [])
    {
        if (!$couponCode) {
            return ['valid' => false, 'message' => 'No se proporcionó ningún código.'];
        }

        // --------------------------
        // 1️⃣ Revisar newsletter
        // --------------------------
        $newsletterCoupon = NewsletterSubscription::where('promo_code', $couponCode)
            ->where('email', $user->email)
            ->where('redeemed', false)
            ->first();

        if ($newsletterCoupon) {
            if ($cartTotal < 99) {
                return [
                    'valid' => false,
                    'message' => 'El total del carrito debe ser superior a 99€ para usar este cupón de newsletter.',
                ];
            }
            // No se marca como redeemed todavía, se hará al confirmar la orden
            $discount = $newsletterCoupon->discount ?? 10; // default 10%

            return [
                'valid' => true,
                'discount' => $discount,
                'type' => $newsletterCoupon->type ?? 'percent',
                'source' => 'newsletter',
                'coupon_code' => $couponCode,
                'redeem_newsletter' => true, // bandera para usar luego
                'newsletter_id' => $newsletterCoupon->id,
            ];
        }

        // --------------------------
        // 2️⃣ Revisar cupon promo
        // --------------------------
        $coupon = Coupon::where('code', $couponCode)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Cupón no válido o expirado.'];
        }

        // --------------------------
        // 3️⃣ Validaciones
        // --------------------------
        if ($coupon->user_id && $coupon->user_id !== $user->id) {
            return ['valid' => false, 'message' => 'Este cupón pertenece a otro usuario.'];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => 'Este cupón ha alcanzado su límite de usos.'];
        }

        if ($coupon->product_id && !in_array($coupon->product_id, $cartProducts)) {
            return ['valid' => false, 'message' => 'Este cupón solo aplica a productos específicos.'];
        }

        if ($coupon->category_id) {
            $categoryMatch = Product::whereIn('id', $cartProducts)
                ->where('category_id', $coupon->category_id)
                ->exists();

            if (!$categoryMatch) {
                return ['valid' => false, 'message' => 'Este cupón solo aplica a una categoría específica.'];
            }
        }

        if ($coupon->min_purchase && $cartTotal < $coupon->min_purchase) {
            return ['valid' => false, 'message' => 'El total no cumple el mínimo requerido para usar este cupón.'];
        }



        $coupon = $coupon->fresh();

        $discount = $coupon->type === 'percent'
            ? $cartTotal * ($coupon->discount / 100)
            : $coupon->discount;

        return [
            'valid' => true,
            'discount' => $discount,
            'type' => $coupon->type,
            'source' => 'promo',
            'coupon_code' => $couponCode,
            'used_count' => $coupon->used_count,
            'max_uses' => $coupon->max_uses,
            'expires_at' => $coupon->expires_at,
        ];
    }

    /**
     * Generar PDF y enviar email
     */
    private function generateOrderPDF($order)
    {
        $basePath = public_path('storage/photos');
        $logoHeader = file_exists($basePath . '/header.png') ? 'data:image/png;base64,' . base64_encode(file_get_contents($basePath . '/header.png')) : '';
        $firmaSrc = file_exists($basePath . '/Decor@10.png') ? 'data:image/png;base64,' . base64_encode(file_get_contents($basePath . '/Decor@10.png')) : '';
        $telefonoIcono = file_exists($basePath . '/telefono.png') ? 'data:image/png;base64,' . base64_encode(file_get_contents($basePath . '/telefono.png')) : '';

        $pdf = Pdf::loadView('pdf.order', [
            'order' => $order->load('orderItems.product', 'user'),
            'logoHeader' => $logoHeader,
            'firmaSrc' => $firmaSrc,
            'telefonoIcono' => $telefonoIcono,
        ]);

        $pdfPath = storage_path("app/public/invoices/order_{$order->id}.pdf");
        $pdf->save($pdfPath);

        Mail::to($order->user->email)->send(new OrderConfirmation($order, $pdfPath));
    }





    public function adminUpdate(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'shipping_address'        => 'string|nullable',
            'mobile1'                 => 'string|nullable',
            'mobile2'                 => 'string|nullable',
            'courier'                 => 'string|nullable',
            'tracking_number'         => 'string|nullable',
            'estimated_delivery_date' => 'date|nullable',
            'status'                  => 'string|in:pendiente,procesando,enviado,en_ruta,entregado,cancelado|nullable',
            'address_id'              => 'exists:addresses,id|nullable',
        ]);

        // Si se proporciona address_id, actualizar shipping_address y móviles
        if (!empty($validated['address_id'])) {
            $address = Address::find($validated['address_id']);
            $validated['shipping_address'] = $address->line1 . ' ' . $address->line2;
            $validated['mobile1'] = $address->mobile1;
            $validated['mobile2'] = $address->mobile2;
        }

        $oldStatus = $order->status;
        $order->update($validated);
        $order->refresh();

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => $validated['status'],
                'nota'     => 'Actualización manual desde admin',
            ]);
        }

        return response()->json([
            'message' => 'Pedido actualizado correctamente',
            'data'    => $order->load('orderItems.product', 'statusHistory', 'address'),
        ]);
    }

    public function update(UpdateOrderRequest $request, $id)
    {
        $order = Order::findOrFail($id);

        $order->update(['status' => $request->status]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status'   => $request->status,
            'nota'     => $request->nota ?? null
        ]);

        return response()->json([
            'message' => 'Estado del pedido actualizado',
            'order'   => $order->load('statusHistory')
        ]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);

        // Solo permite eliminar pedidos pendientes
        if ($order->status === 'pendiente') {
            $order->delete();
            return response()->json(['success' => true, 'message' => 'Pedido eliminado correctamente.']);
        }

        return response()->json(['success' => false, 'error' => 'No se puede eliminar un pedido que no está en estado "pendiente".'], 400);
    }


    // Mostrar todas las órdenes del usuario autenticado
    public function getOrders()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado'], 401);
        }

        $orders = Order::with('orderItems.product', 'statusHistory', 'address')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    public function trackOrder($tracking_number)
    {
        // Cargar la orden con relaciones necesarias
        $order = Order::with(['orderItems.product', 'statusHistory', 'address'])
            ->where('tracking_number', $tracking_number)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Preparar los productos del pedido
        $items = $order->orderItems->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Producto eliminado',
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->quantity * $item->price,
            ];
        });

        // Preparar timeline de estados
        $timeline = $order->statusHistory->map(function ($status) {
            $date = $status->cambiado_en;
            if ($date && !($date instanceof \Carbon\Carbon)) {
                $date = \Carbon\Carbon::parse($date);
            }
            return [
                'status' => $status->status,
                'nota' => $status->nota,
                'cambiado_en' => $date?->format('Y-m-d H:i:s'),
            ];
        });

        // Responder con todos los datos importantes
        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'tracking_number' => $order->tracking_number,
                'status' => $order->status,
                'estimated_delivery_date' => $order->estimated_delivery_date?->format('Y-m-d'),
                'shipping_address' => $order->shipping_address,
                'total' => $order->total,
                'discount' => $order->discount,
                'total_after_discount' => $order->total_after_discount,
                'items' => $items,
                'timeline' => $timeline,
                'address' => [
                    'line1' => $order->address->line1 ?? '',
                    'city' => $order->address->city ?? '',
                    'country' => $order->address->country ?? '',
                    'mobile1' => $order->address->mobile1 ?? '',
                    'mobile2' => $order->address->mobile2 ?? '',
                ],
            ]
        ]);
    }



    // Métodos de estadísticas
    public function getTotalRevenue()
    {
        $totalRevenue = Order::where('status', 'entregado')->sum('total');
        return response()->json([
            'success' => true,
            'total_revenue' => round($totalRevenue, 2)
        ]);
    }

    public function getRevenueStats(Request $request)
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'entregado');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $totalRevenue = $query->sum('total');
        $totalOrders  = $query->count();
        $averageOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return response()->json([
            'success'       => true,
            'total_revenue' => round($totalRevenue, 2),
            'total_orders'  => $totalOrders,
            'avg_per_order' => round($averageOrder, 2),
        ]);
    }

    public function getMonthlyRevenue()
    {
        $revenues = Order::selectRaw('MONTH(created_at) as month, SUM(total) as total')
            ->where('status', 'entregado')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get();

        $formatted = $revenues->map(function ($item) {
            return [
                'month' => date('F', mktime(0, 0, 0, $item->month, 1)),
                'total' => round($item->total, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

}
