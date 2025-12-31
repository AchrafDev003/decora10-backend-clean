<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Product;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Identidad
            'id' => $this->id,
            'slug' => $this->slug,

            // Información básica
            'name' => $this->name,
            'description' => $this->description,

            // Precios
            'price' => (float) $this->price,
            'promo_price' => $this->promo_price !== null
                ? (float) $this->promo_price
                : null,
            'display_price' => (float) ($this->promo_price ?? $this->price),
            'is_promo' => (bool) $this->is_promo,

            // Stock
            'quantity' => (int) $this->quantity,

            // 🔥 LOGÍSTICA (CLAVE)
            'logistic_type' => $this->logistic_type ?? Product::LOGISTIC_SMALL,

            // Categoría
            'category' => new CategoryResource($this->whenLoaded('category')),

            // Imágenes
            'images' => $this->images
                ->sortBy('position')
                ->map(fn ($img) => [
                    'id' => $img->id,
                    'image_path' => $img->image_path
                        ? url('storage/' . $img->image_path)
                        : null,
                    'position' => $img->position,
                ])
                ->values(),
        ];
    }
}
