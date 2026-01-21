<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_id',
        'name',
        'type',
        'price',        // ✅ precio individual del item
        'image_url',    // ✅ imagen del item
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    /* ======================
       Relationships
    ====================== */

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}
