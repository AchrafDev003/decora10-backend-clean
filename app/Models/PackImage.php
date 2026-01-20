<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_id',
        'image_path',
        'is_main',
        'sort_order',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}
