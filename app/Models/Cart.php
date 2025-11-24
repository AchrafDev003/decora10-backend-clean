<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_email',
    ];

    /**
     * Relación con usuario autenticado
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // app/Models/Cart.php
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }


    /**
     * Relación con los items del carrito
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Total de todos los items considerando promo_price si existe
     */
    public function getTotalAttribute(): float
    {
        return $this->items->sum(fn($item) => $item->quantity * ($item->product->promo_price ?? $item->product->price));
    }

    /**
     * Ver si el carrito supera un límite de valor
     */
    public function exceedsLimit(float $limit = 2000.00): bool
    {
        return $this->total > $limit;
    }
}
