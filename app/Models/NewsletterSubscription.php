<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscription extends Model
{
    protected $table = 'newsletters'; // especifica el nombre correcto de la tabla
    protected $fillable = ['email', 'promo_code', 'redeemed'];
}
