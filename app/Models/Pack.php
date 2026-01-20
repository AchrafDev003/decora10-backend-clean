<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'original_price',
        'promo_price',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_active' => 'boolean',
        'original_price' => 'decimal:2',
        'promo_price'    => 'decimal:2',
    ];

    /* ======================
       Relationships
    ====================== */

    public function items()
    {
        return $this->hasMany(PackItem::class);
    }

    public function images()
    {
        return $this->hasMany(PackImage::class);
    }

    /* ======================
       Scopes
    ====================== */

    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
