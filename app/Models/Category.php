<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // Laravel maneja automáticamente los timestamps
    public $timestamps = true;

    // Definimos los campos que se pueden asignar masivamente
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    // Relación con productos
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
