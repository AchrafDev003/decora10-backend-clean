<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Estado real del pack según flags y fechas
        $status = match (true) {
            !$this->is_active => 'inactive',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'expired',
            default => 'active',
        };

        return [
            // ---------------- Identidad ----------------
            'id'    => $this->id,
            'title' => $this->title,
            'slug'  => $this->slug,

            // ---------------- Contenido ----------------
            'description' => $this->description,
            'image_url'   => $this->image_url,

            // ---------------- Precios ----------------
            'original_price'      => (float) $this->original_price,
            'promo_price'         => (float) $this->promo_price,
            'discount_percentage' => $this->discount_percentage,

            // ---------------- Medidas ----------------
            'requires_measure' => (bool) $this->requires_measure,

            // ---------------- Fechas ----------------
            'start_date' => $this->starts_at?->toISOString(),
            'end_date'   => $this->ends_at?->toISOString(),

            // ---------------- Estado ----------------
            'is_active' => (bool) $this->is_active,
            'status'    => $status,

            // ---------------- Items ----------------
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id'          => $item->id,
                        'name'        => $item->name,
                        'description' => $item->description, // ✅ NUEVO
                        'type'        => $item->type,
                        'price'       => (float) $item->price,
                        'quantity'    => (int) $item->quantity,
                        'image_url'   => $item->image_url,
                    ];
                });
            }),

            // ---------------- Timestamps ----------------
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
