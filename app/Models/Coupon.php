<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    // ---------------------------
    // 🔹 Campos asignables
    // ---------------------------
    protected $fillable = [
        'code', 'type', 'discount', 'user_id', 'used', 'used_count', 'max_uses',
        'min_purchase', 'product_id', 'category_id', 'expires_at', 'campaign',
        'source', 'customer_type', 'is_active'
    ];

    // ---------------------------
    // 🔹 Casts automáticos
    // ---------------------------
    protected $casts = [
        'used' => 'boolean',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'max_uses' => 'integer',
        'discount' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    // ---------------------------
    // 🔹 Relaciones
    // ---------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // ---------------------------
    // 🔹 Métodos útiles
    // ---------------------------

    // Verifica si el cupón está expirado
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // Verifica si el cupón alcanzó su límite
    public function hasReachedLimit(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    // Incrementa el contador de uso
    public function incrementUsage(): void
    {
        $this->increment('used_count');
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            $this->used = true;
            $this->save();
        }
    }
}
