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

    /**
     * Logistic types (domain constants)
     */
    public const LOGISTIC_SMALL  = 'small';
    public const LOGISTIC_MEDIUM = 'medium';
    public const LOGISTIC_HEAVY  = 'heavy';

    /**
     * Mass assignable attributes
     */
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

        'logistic_type',   // ✅ logística

        'category_id',
    ];

    /**
     * Default attributes
     */
    protected $attributes = [
        'logistic_type' => self::LOGISTIC_SMALL,
    ];

    /**
     * Casts
     */
    protected $casts = [
        'is_promo'       => 'boolean',
        'promo_ends_at'  => 'datetime',
        'price'          => 'decimal:2',
        'promo_price'    => 'decimal:2',
        'quantity'       => 'integer',
    ];

    /**
     * Relationships
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

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

    /**
     * Model events (slug handling)
     */
    protected static function booted(): void
    {
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);

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

                $originalSlug = $product->slug;
                $counter = 1;

                while (
                static::where('slug', $product->slug)
                    ->where('id', '!=', $product->id)
                    ->exists()
                ) {
                    $product->slug = $originalSlug . '-' . $counter++;
                }
            }
        });
    }

    /**
     * Scopes
     */

    // Promociones primero
    public function scopePromoFirst(Builder $query)
    {
        return $query->orderByRaw(
            "
            CASE
                WHEN is_promo = 1 AND (promo_ends_at IS NULL OR promo_ends_at > ?) THEN 0
                ELSE 1
            END
            ",
            [now()]
        );
    }

    // Solo productos pesados
    public function scopeHeavy(Builder $query)
    {
        return $query->where('logistic_type', self::LOGISTIC_HEAVY);
    }

    // Productos no pesados
    public function scopeNonHeavy(Builder $query)
    {
        return $query->whereIn('logistic_type', [
            self::LOGISTIC_SMALL,
            self::LOGISTIC_MEDIUM,
        ]);
    }
}
