<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrderRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\{
    Order, OrderItem, OrderStatusHistory, Cart,
    NewsletterSubscription, Address, Product, Payment, Coupon
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\OrderConfirmation;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // ==========================
    // Mostrar todos los pedidos
    // ==========================
    public function index(Request $request)
    {
        $query = Order::with('user', 'orderItems.product', 'address');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 10));
        return response()->json($orders);
    }

    // ==========================
    // Mostrar un pedido específico
    // ==========================
    public function show($id)
    {
        $order = Order::with('orderItems.product', 'statusHistory', 'address')->findOrFail($id);

        $orderArray = $order->toArray(); // Usamos toArray alineado
        return response()->json(['order' => $orderArray]);
    }

    // ==========================
    // Pedidos admin/ERP
    // ==========================
    public function adminOrders(Request $request)
    {
        $query = Order::with(['user','orderItems.product','address'])->orderBy('created_at','desc');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('created_at','>=',$request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at','<=',$request->date_to);
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search){
                $q->where('id',$search)
                    ->orWhere('order_code','like',"%{$search}%")
                    ->orWhereHas('user', fn($q2) => $q2->where('name','like',"%{$search}%")
                        ->orWhere('email','like',"%{$search}%"));
            });
        }

        $orders = $query->paginate($request->get('per_page',10));

        $data = $orders->getCollection()->transform(fn($order) => [
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
            'items' => $order->orderItems->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'pack_id' => $item->pack_id ?? null,
                'product_name' => $item->product_name
                    ?? ($item->product_id ? $item->product->name : null)
                        ?? ($item->pack_id ? $item->pack->name : 'Item eliminado'),
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
                'profit' => $item->profit,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
    }

    // ==========================
    // Crear un pedido
    // ==========================
    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'payment_method'=>'required|string|in:card,paypal,cash,bizum',
            'line1'=>'required|string','city'=>'required|string','country'=>'required|string',
            'mobile1'=>'required|string',
            'items'=>'required|array|min:1',
            'items.*.product_id'=>'nullable|integer',
            'items.*.pack_id'=>'nullable|integer',
            'items.*.quantity'=>'required|integer|min:1',
            'items.*.price'=>'required|numeric|min:0',
            'items.*.cost'=>'nullable|numeric|min:0',
            'subtotal'=>'required|numeric|min:0',
            'discount'=>'nullable|numeric|min:0',
            'total'=>'required|numeric|min:0',
            'payment_intent'=>'nullable|string',
            'promo_code'=>'nullable|string',
            'coupon_type'=>'nullable|string|in:percent,fixed',
            'address_type'=>'nullable|string',
            'line2'=>'nullable|string',
            'zipcode'=>'nullable|string',
            'mobile2'=>'nullable|string',
            'additional_info'=>'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $address = Address::create([
                'user_id'=>$user->id,
                'type'=>$request->address_type ?? 'default',
                'line1'=>$request->line1,
                'line2'=>$request->line2 ?? null,
                'city'=>$request->city,
                'zipcode'=>$request->zipcode ?? null,
                'country'=>$request->country,
                'mobile1'=>$request->mobile1,
                'mobile2'=>$request->mobile2 ?? null,
                'additional_info'=>$request->additional_info ?? '',
                'is_default'=>1,
            ]);

            $tracking_number = 'DEC-ORD-' . strtoupper(Str::random(8));
            $estimatedDelivery = Carbon::now()->addDays(5);

            $order = Order::create([
                'order_code'=>'DEC-' . strtoupper(Str::random(10)),
                'user_id'=>$user->id,
                'subtotal'=>$request->subtotal ?? 0.00,
                'total'=>$request->total ?? 0.00,
                'discount'=>$request->discount ?? 0.00,
                'shipping_cost'=>$request->transport_fee ?? 0.00,
                'tax'=>0.00,
                'tax_rate'=>null,
                'shipping_address'=>trim($address->line1 . ' ' . ($address->line2 ?? '')),
                'address_id'=>$address->id,
                'mobile1'=>$address->mobile1,
                'mobile2'=>$address->mobile2 ?? null,
                'payment_method'=>$request->payment_method,
                'status'=>'pendiente',
                'tracking_number'=>$tracking_number,
                'courier'=>null,
                'estimated_delivery_date'=>$estimatedDelivery,
                'promo_code'=>$request->promo_code ?? null,
                'coupon_type'=>in_array($request->coupon_type,['percent','fixed']) ? $request->coupon_type : null,
            ]);

            foreach($request->items as $item){
                OrderItem::create([
                    'order_id'=>$order->id,
                    'product_id'=>$item['product_id'] ?? null,
                    'pack_id'=>$item['pack_id'] ?? null,
                    'quantity'=>$item['quantity'],
                    'price'=>$item['price'],
                    'cost'=>$item['cost'] ?? null,
                ]);
            }

            OrderStatusHistory::create([
                'order_id'=>$order->id,
                'status'=>'pendiente',
                'nota'=>'Pedido creado correctamente',
            ]);

            Payment::create([
                'user_id'=>$user->id,
                'order_id'=>$order->id,
                'method'=>$request->payment_method,
                'provider'=>'stripe',
                'status'=>'pending',
                'amount'=>$request->total,
                'transaction_id'=>$request->payment_intent,
                'meta'=>json_encode($request->all()),
            ]);

            DB::commit();

            $this->sendOrderConfirmationEmail($order);

            return response()->json([
                'message'=>'Pedido creado exitosamente. Confirma el pago para procesarlo.',
                'order'=>$order->load('orderItems.product','orderItems.pack','address'),
                'tracking_number'=>$tracking_number,
                'payment_client_secret'=>$request->payment_intent,
            ],201);

        } catch(\Throwable $e){
            DB::rollBack();
            Log::error('Error creando pedido', [
                'error'=>$e->getMessage(),
                'trace'=>$e->getTraceAsString(),
                'payload'=>$request->all(),
            ]);

            return response()->json([
                'error'=>'Error al crear el pedido',
                'details'=>$e->getMessage(),
            ],500);
        }
    }

    // ==========================
    // Métodos track, getOrders y estadísticas
    // ==========================
    public function trackOrder($tracking_number)
    {
        $order = Order::with(['orderItems.product','orderItems.pack','statusHistory','address'])
            ->where('tracking_number',$tracking_number)
            ->first();

        if(!$order){
            return response()->json(['error'=>'Pedido no encontrado'],404);
        }

        $items = $order->orderItems->map(fn($item) => [
            'id'=>$item->id,
            'product_id'=>$item->product_id,
            'pack_id'=>$item->pack_id ?? null,
            'product_name'=>$item->product_name
                ?? ($item->product_id ? $item->product->name : null)
                    ?? ($item->pack_id ? $item->pack->name : 'Item eliminado'),
            'quantity'=>$item->quantity,
            'price'=>$item->price,
            'subtotal'=>$item->subtotal,
            'profit'=>$item->profit,
        ]);

        $timeline = $order->statusHistory->map(fn($status) => [
            'status'=>$status->status,
            'nota'=>$status->nota,
            'cambiado_en'=>($status->cambiado_en instanceof Carbon)
                ? $status->cambiado_en->format('Y-m-d H:i:s')
                : Carbon::parse($status->cambiado_en)?->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'order'=>[
                'id'=>$order->id,
                'order_code'=>$order->order_code,
                'tracking_number'=>$order->tracking_number,
                'status'=>$order->status,
                'estimated_delivery_date'=>$order->estimated_delivery_date?->format('Y-m-d'),
                'shipping_address'=>$order->shipping_address,
                'total'=>$order->total,
                'discount'=>$order->discount,
                'total_after_discount'=>$order->total_after_discount,
                'items'=>$items,
                'timeline'=>$timeline,
                'address'=>[
                    'line1'=>$order->address->line1 ?? '',
                    'city'=>$order->address->city ?? '',
                    'country'=>$order->address->country ?? '',
                    'mobile1'=>$order->address->mobile1 ?? '',
                    'mobile2'=>$order->address->mobile2 ?? '',
                ],
            ]
        ]);
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
        // URLs desde Cloudinary
        $logoHeader    = 'https://res.cloudinary.com/dvo9uq7io/image/upload/v1764235771/decora10-test/DECORA10.png';
        $firmaSrc      = 'https://res.cloudinary.com/dvo9uq7io/image/upload/v1764244411/blade-resources/Decor_10.png';
        $telefonoIcono = 'https://res.cloudinary.com/dvo9uq7io/image/upload/v1764244416/blade-resources/telefono.png';
        $dec10         = 'https://res.cloudinary.com/dvo9uq7io/image/upload/v1764244408/blade-resources/dec10.jpg'; // si lo necesitas en algún sitio extra

        // Convertir a base64 para que dompdf lo renderice
        $logoHeader    = 'data:image/png;base64,' . base64_encode(file_get_contents($logoHeader));
        $firmaSrc      = 'data:image/png;base64,' . base64_encode(file_get_contents($firmaSrc));
        $telefonoIcono = 'data:image/png;base64,' . base64_encode(file_get_contents($telefonoIcono));


        $pdf = Pdf::loadView('pdf.order', [
            'order'        => $order->load('orderItems.product', 'user'),
            'logoHeader'   => $logoHeader,
            'firmaSrc'     => $firmaSrc,
            'telefonoIcono'=> $telefonoIcono,

        ]);

        $pdfPath = storage_path("app/public/invoices/order_{$order->id}.pdf");
        $pdf->save($pdfPath);

        Mail::to($order->user->email)
            ->bcc(['hrafartist@gmail.com', 'decora10.colchon10@gmail.com'])
            ->send(new OrderConfirmation($order, $pdfPath));

    }
    private function sendOrderConfirmationEmail($order)
    {
        // Cargar relaciones necesarias
        $order->load('orderItems.product', 'user');

        Mail::to($order->user->email)
            ->bcc([
                'hrafartist@gmail.com',
                'decora10.colchon10@gmail.com'
            ])
            ->send(new OrderConfirmation($order));
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
