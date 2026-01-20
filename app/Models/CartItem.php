<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    /**
     * Campos asignables masivamente
     */
    protected $fillable = [
        'cart_id',
        'product_id', // Producto opcional
        'pack_id',    // Pack opcional
        'quantity',
        'reserved_until',
        'notified_expiry',
    ];

    /**
     * Conversión de campos a tipos nativos
     */
    protected $casts = [
        'reserved_until' => 'datetime',
        'notified_expiry' => 'boolean',
    ];

    /**
     * Relación con el carrito
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Relación con el producto (si existe)
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con el pack (si existe)
     */
    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }

    /**
     * Accesor dinámico: total del item (producto o pack)
     */
    public function getTotalPriceAttribute(): float
    {
        // Producto
        if ($this->product_id && $this->product) {
            return $this->quantity * ($this->product->promo_price ?? $this->product->price);
        }

        // Pack
        if ($this->pack_id && $this->pack) {
            return $this->quantity * ($this->pack->promo_price ?? $this->pack->original_price);
        }

        return 0;
    }

    /**
     * Accesor opcional: subtotal (alias de total_price)
     */
    public function getSubtotalAttribute(): float
    {
        return $this->total_price;
    }
}
