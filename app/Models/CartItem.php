<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'reserved_until' => 'datetime',
    ];

    /**
     * Relación con el carrito
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Relación con el producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Accesor dinámico: total del item
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->quantity * ($this->product->promo_price ?? $this->product->price);
    }

    /**
     * Accesor opcional: subtotal (alias)
     */
    public function getSubtotalAttribute(): float
    {
        return $this->total_price;
    }
}
