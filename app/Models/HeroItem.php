<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class HeroItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'subtitle', 'descripcion', 'link',
        'media_filename', 'media_type', 'order', 'status',
    ];

    // Este campo se añade automáticamente a la salida JSON
    protected $appends = ['media_url'];

    public function getMediaUrlAttribute()
    {
        return $this->media_filename
            ? asset('storage/hero/' . $this->media_filename)
            : null;
    }
}
