<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history'; // Nombre exacto de la tabla

    protected $fillable = [
        'order_id',
        'status',
        'nota',
        'cambiado_en',
    ];

    public $timestamps = false; // Usamos 'cambiado_en' en lugar de created_at

    protected $dates = [
        'cambiado_en',
    ];

    // Relación con el pedido
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Accesor para devolver fecha formateada
    public function getCambiadoEnFormattedAttribute()
    {
        return $this->cambiado_en?->format('Y-m-d H:i:s');
    }

    // Accesor para devolver info resumida para frontend
    public function toArray()
    {
        return [
            'id'          => $this->id,
            'status'      => $this->status,
            'nota'        => $this->nota,
            'cambiado_en' => $this->cambiado_en_formatted,
        ];
    }
}
