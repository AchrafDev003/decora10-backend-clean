<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CartItem;
use Carbon\Carbon;

class ReleaseExpiredReservations extends Command
{
    protected $signature = 'release:reservations';
    protected $description = 'Libera productos reservados en carritos después de que expire el tiempo de reserva.';

    public function handle()
    {
        $now = Carbon::now();

        // Obtener todos los items de carrito expirados
        $expiredItems = CartItem::with('product')
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', $now)
            ->get();

        $count = 0;

        if ($expiredItems->isEmpty()) {
            $this->info("No hay reservas expiradas por liberar.");
            return;
        }

        DB::transaction(function () use ($expiredItems, &$count) {
            foreach ($expiredItems as $item) {
                $product = $item->product;

                if ($product) {
                    // Reponer la cantidad del producto
                    $product->increment('quantity', $item->quantity);
                    Log::info("🕒 Liberando reserva expirada: CartItem {$item->id}, Producto {$product->name} (x{$item->quantity})");

                } else {
                    Log::warning("Producto no encontrado para CartItem ID: {$item->id}");
                }

                // Eliminar el item del carrito
                $item->delete();
                $count++;
            }
        });

        $this->info("✅ Reservas expiradas liberadas correctamente: {$count}");
        Log::info("✅ [ReleaseReservations] Total liberadas: {$count}");
    }
}
