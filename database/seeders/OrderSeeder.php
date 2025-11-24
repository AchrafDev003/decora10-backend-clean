<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        $products = Product::all();

        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->info('No hay usuarios o productos para crear órdenes.');
            return;
        }

        $paymentMethods = ['card', 'paypal', 'cash'];

        foreach ($users as $user) {
            $ordersCount = rand(1, 3);

            for ($i = 0; $i < $ordersCount; $i++) {
                // Simula el "carrito": selecciona entre 1 y 4 productos únicos
                $cartItems = $products->random(rand(1, min(4, $products->count())));
                $cartData = [];

                $total = 0;

                foreach ($cartItems as $product) {
                    $quantity = rand(1, min(3, $product->stock));

                    if ($quantity < 1 || $product->stock < $quantity) {
                        continue; // Simula validación de stock
                    }

                    $cartData[] = [
                        'product_id' => $product->id,
                        'product'    => $product,
                        'quantity'   => $quantity,
                        'price'      => $product->price,
                    ];

                    $total += $quantity * $product->price;
                }

                if (empty($cartData)) {
                    continue; // No hay stock suficiente, salta este pedido
                }

                DB::beginTransaction();

                try {
                    $order = Order::create([
                        'user_id'          => $user->id,
                        'total'            => $total,
                        'shipping_address' => 'Dirección de prueba ' . Str::random(10),
                        'payment_method'   => $paymentMethods[array_rand($paymentMethods)],
                        'status'           => 'pending',
                    ]);

                    foreach ($cartData as $item) {
                        OrderItem::create([
                            'order_id'   => $order->id,
                            'product_id' => $item['product_id'],
                            'quantity'   => $item['quantity'],
                            'price'      => $item['price'],
                        ]);

                        // Disminuir el stock
                        $item['product']->decrement('stock', $item['quantity']);
                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->command->error("Error al crear orden: " . $e->getMessage());
                }
            }
        }

        $this->command->info('Órdenes generadas correctamente.');
    }
}
