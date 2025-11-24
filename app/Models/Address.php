<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',            // Ej: 'home', 'work', 'other'
        'line1',           // Calle principal
        'line2',           // Piso, portal, etc.
        'city',
        'zipcode',
        'country',
        'is_default',
        'mobile1',
        'mobile2',
        'additional_info', // 👈 para incluir notas o referencias
    ];

    /**
     * Relación: una dirección pertenece a un usuario.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: una dirección puede estar asociada a muchos pedidos.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
