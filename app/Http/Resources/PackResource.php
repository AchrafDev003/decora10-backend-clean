<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();

        // Status real basado en fechas
        $status = match (true) {
            !$this->is_active => 'inactive',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'expired',
            default => 'active',
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'original_price' => (float) $this->original_price,
            'promo_price' => (float) $this->promo_price,

            'discount_percentage' => $this->original_price > 0
                ? round(100 - ($this->promo_price * 100 / $this->original_price))
                : 0,

            // ✅ MAPEO CORRECTO DE FECHAS
            'start_date' => $this->starts_at,
            'end_date'   => $this->ends_at,

            'is_active' => $this->is_active,
            'status' => $status,

            // ✅ CLOUDINARY DIRECTO (SIN asset(), SIN storage)
            'images' => $this->whenLoaded('images', function () {
                return $this->images
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($img) => [
                        'id' => $img->id,
                        'url' => $img->image_path,
                        'is_main' => $img->is_main,
                        'sort_order' => $img->sort_order,
                    ]);
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
