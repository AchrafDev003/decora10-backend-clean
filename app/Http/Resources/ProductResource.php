<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'promo_price' => $this->promo_price !== null ? (float) $this->promo_price : null,
            'display_price' => $this->promo_price !== null ? (float) $this->promo_price : (float) $this->price,
            'is_promo' => (bool) $this->is_promo,
            'slug' => $this->slug,
            'quantity' => $this->quantity,

            // Relación categoría
            'category' => new CategoryResource($this->category),

            // Relación imágenes
            'images' => $this->images
                ->sortBy('position')
                ->map(fn($img) => [
                    'id' => $img->id,
                    'image_path' => $img->image_path ? url('storage/' . $img->image_path) : null,
                    'position' => $img->position,
                ])
                ->values(), // reset keys
        ];
    }
}
