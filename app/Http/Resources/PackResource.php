<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'original_price' => (float) $this->original_price,
            'promo_price' => (float) $this->promo_price,

            'discount_percentage' => $this->original_price > 0
                ? round(100 - ($this->promo_price * 100 / $this->original_price))
                : 0,

            'start_date' => $this->start_date,
            'end_date' => $this->end_date,

            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'active' : 'inactive',

            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/' . $img->path),
                ]);
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
