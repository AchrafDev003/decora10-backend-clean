<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Status real basado en estado + fechas
        $status = match (true) {
            !$this->is_active => 'inactive',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'expired',
            default => 'active',
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,

            // ✅ imagen principal del pack
            'image' => $this->image_url,

            'original_price' => (float) $this->original_price,
            'promo_price' => (float) $this->promo_price,

            'discount_percentage' => $this->discount_percentage,

            // ✅ fechas correctas
            'start_date' => $this->starts_at,
            'end_date'   => $this->ends_at,

            'is_active' => $this->is_active,
            'status' => $status,

            // ✅ items del pack (cada uno con imagen y precio)
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'price' => (float) $item->price,
                    'quantity' => $item->quantity,
                    'image' => $item->image_url,
                ]);
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
