<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory;

    // ===========================
    // 📌 MASS ASSIGNMENT
    // ===========================

    protected $fillable = [
        'order_code',
        'user_id',
        'address_id',

        'subtotal',
        'tax',
        'tax_rate',
        'shipping_cost',
        'discount',
        'total',

        'promo_code',
        'coupon_type',

        'shipping_address',
        'payment_method',
        'status',

        'tracking_number',
        'courier',
        'estimated_delivery_date',

        'mobile1',
        'mobile2',
    ];

    // ===========================
    // 🔄 CASTS
    // ===========================

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_rate' => 'decimal:2',

        'estimated_delivery_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===========================
    // 🔗 RELACIONES
    // ===========================

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Usuario eliminado',
            'email' => 'N/A',
        ]);
    }

    public function address()
    {
        return $this->belongsTo(Address::class)->withDefault([
            'line1' => 'Sin dirección asociada',
            'city' => 'N/A',
            'country' => 'N/A',
        ]);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)
            ->orderBy('cambiado_en', 'asc');
    }

    // ===========================
    // 💰 ACCESORES
    // ===========================

    public function getTotalCostAttribute(): float
    {
        return (float) $this->orderItems->sum(fn ($item) =>
            ($item->cost ?? $item->price) * $item->quantity
        );
    }

    public function getProfitAttribute(): float
    {
        return (float) ($this->total - $this->total_cost);
    }

    public function getLastStatusAttribute(): string
    {
        return $this->statusHistory()
            ->latest('cambiado_en')
            ->value('status') ?? $this->status;
    }

    public function getTimelineAttribute()
    {
        return $this->statusHistory->map(function ($item) {
            $date = $item->cambiado_en instanceof Carbon
                ? $item->cambiado_en
                : Carbon::parse($item->cambiado_en);

            return [
                'status' => $item->status,
                'nota' => $item->nota,
                'cambiado_en' => $date->format('Y-m-d H:i:s'),
            ];
        });
    }

    // ===========================
    // ⚙️ EVENTOS
    // ===========================

    protected static function booted()
    {
        static::creating(function (self $order) {
            if (!$order->order_code) {
                $order->order_code = 'DEC-' . strtoupper(Str::random(10));
            }

            $order->discount = $order->discount ?? 0;
            $order->shipping_cost = $order->shipping_cost ?? 0;
            $order->tax = $order->tax ?? 0;
        });
    }


// ===========================
    // 📦 FORMATO JSON (Frontend)
    // ===========================

    public function toArray()
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'address' => [
                'id' => $this->address->id ?? null,
                'line1' => $this->address->line1 ?? '',
                'city' => $this->address->city ?? '',
                'country' => $this->address->country ?? '',
                'mobile1' => $this->address->mobile1 ?? '',
                'mobile2' => $this->address->mobile2 ?? '',
            ],
            'items' => $this->orderItems->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Producto eliminado',
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->quantity * $item->price,
            ]),
            'status' => $this->status,
            'last_status' => $this->last_status,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'total' => $this->total,
            'discount' => $this->discount,
            'total_after_discount' => $this->total_after_discount,
            'promo_code' => $this->promo_code,
            'coupon_type' => $this->coupon_type,
            'shipping_address' => $this->shipping_address,
            'payment_method' => $this->payment_method,
            'tracking_number' => $this->tracking_number,
            'courier' => $this->courier,
            'estimated_delivery_date' => $this->estimated_delivery_date?->format('Y-m-d'),
            'tax' => $this->tax,
            'tax_rate' => $this->tax_rate,
            'profit' => $this->profit,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'timeline' => $this->timeline,
        ];
    }
}
