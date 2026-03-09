<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignCatalog extends Model
{
    use HasFactory;

    protected $table = 'design_catalog';

    protected $fillable = [
        'product_id',
        'style',
        'room',
        'color',
        'width',
        'depth',
        'height',
        'priority'
    ];

    // Relación con Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
