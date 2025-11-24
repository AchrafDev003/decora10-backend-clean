<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'product_name',
        'cost', // nuevo campo
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2', // nuevo
        'quantity' => 'integer',
    ];


    public function getProfitAttribute(): float
    {
        return round(($this->price - ($this->cost ?? 0)) * $this->quantity, 2);
    }


    // ===========================
    // 🔗 RELACIONES
    // ===========================

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withDefault([
            'name' => 'Producto eliminado',
            'price' => 0,
        ]);
    }

    // ===========================
    // 💡 ACCESORES
    // ===========================

    public function getSubtotalAttribute(): float
    {
        return round($this->quantity * $this->price, 2);
    }

    // ===========================
    // 📦 FORMATO JSON
    // ===========================

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'product_name' => $this->product_name ?? ($this->product->name ?? 'Producto eliminado'),
            'quantity'     => $this->quantity,
            'price'        => (float) $this->price,
            'cost'         => (float) ($this->cost ?? 0),
            'subtotal'     => $this->subtotal,
            'profit'       => $this->profit,
        ];
    }

}
