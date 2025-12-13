<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'method',
        'provider',
        'status',
        'paid_at',
        'amount',
        'transaction_id',
        'meta',
    ];

    protected $casts = [
        'paid_at' => 'datetime:Y-m-d H:i:s',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
