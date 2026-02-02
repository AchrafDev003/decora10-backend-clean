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
        'pack_id',       // nuevo: pack
        'quantity',
        'price',
        'product_name',  // opcional, para override
        'cost',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // ---------------------------
    // 💰 Ganancia
    // ---------------------------
    public function getProfitAttribute(): float
    {
        return round(($this->price - ($this->cost ?? 0)) * $this->quantity, 2);
    }

    // ---------------------------
    // 🔗 Relaciones
    // ---------------------------
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

    public function pack()
    {
        return $this->belongsTo(Pack::class)->withDefault([
            'name' => 'Pack eliminado',
            'price' => 0,
        ]);
    }

    // ---------------------------
    // 💡 Accesor: subtotal
    // ---------------------------
    public function getSubtotalAttribute(): float
    {
        return round($this->quantity * $this->price, 2);
    }

    // ---------------------------
    // 📦 Formato JSON
    // ---------------------------
    public function toArray(): array
    {
        $name = $this->product_name
            ?? ($this->product_id ? $this->product->name : null)
            ?? ($this->pack_id ? $this->pack->name : 'Item eliminado');

        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'pack_id'      => $this->pack_id,
            'product_name' => $name,
            'quantity'     => $this->quantity,
            'price'        => (float) $this->price,
            'cost'         => (float) ($this->cost ?? 0),
            'subtotal'     => $this->subtotal,
            'profit'       => $this->profit,
        ];
    }
}
