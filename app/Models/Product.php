<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_product',
        'name',
        'slug',
        'description',
        'price',
        'promo_price',
        'is_promo',
        'promo_ends_at',
        'quantity',

        'category_id',
    ];


    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }


    /**
     * Generar automáticamente el slug a partir del nombre
     */
    protected static function booted(): void
    {
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);

                // Verificar si el slug ya existe
                $originalSlug = $product->slug;
                $counter = 1;
                while (static::where('slug', $product->slug)->exists()) {
                    $product->slug = $originalSlug . '-' . $counter++;
                }
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name')) {
                $product->slug = Str::slug($product->name);

                // Verificar si el nuevo slug ya existe
                $originalSlug = $product->slug;
                $counter = 1;
                while (static::where('slug', $product->slug)->where('id', '!=', $product->id)->exists()) {
                    $product->slug = $originalSlug . '-' . $counter++;
                }
            }
        });
    }

    // Scope para ordenar promociones primero
    public function scopePromoFirst(Builder $query)
    {
        return $query->orderByRaw("
            CASE
                WHEN is_promo = 1 AND (promo_ends_at IS NULL OR promo_ends_at > ?) THEN 0
                ELSE 1
            END
        ", [now()]);
    }

    // Relaciones
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
