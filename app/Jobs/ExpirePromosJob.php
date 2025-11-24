<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpirePromosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $now = Carbon::now();

        try {
            // Obtener todos los productos en promoción con fecha de fin
            $products = Product::where('is_promo', true)
                ->whereNotNull('promo_ends_at')
                ->get();

            // Filtrar los que ya expiraron (seguro aunque sea string)
            $expiredProducts = $products->filter(function($product) use ($now) {
                return Carbon::parse($product->promo_ends_at)->lessThan($now);
            });

            $count = $expiredProducts->count();

            foreach ($expiredProducts as $product) {
                $product->update([
                    'is_promo' => false,
                    'promo_price' => null,
                ]);
            }

            Log::info("ExpirePromosJob ejecutado. Productos expirados: $count");

        } catch (\Exception $e) {
            Log::error("ExpirePromosJob error: {$e->getMessage()}");
        }
    }
}
