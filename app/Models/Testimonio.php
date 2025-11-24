<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'titulo', 'texto', 'rating', 'imagen', 'publicado'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
