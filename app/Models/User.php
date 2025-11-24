<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;


class User extends Authenticatable implements MustVerifyEmail
{
    use  HasApiTokens, HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';
    const ROLE_OWNER = 'dueno';
    const ROLE_CLIENT = 'cliente';
    const ROLE_LOYAL_CLIENT = 'cliente_fiel';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'photo',
        'email_verification_token',
        'email_verified_at',
        'provider',
        'provider_id',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    // Relaciones
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Método genérico para verificar roles
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    // Métodos que ahora pueden utilizar el método genérico `hasRole`
    public function isAdmin()
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isOwner()
    {
        return $this->hasRole(self::ROLE_OWNER);
    }

    public function isClient()
    {
        return $this->hasRole(self::ROLE_CLIENT);
    }

    public function isLoyalClient()
    {
        return $this->hasRole(self::ROLE_LOYAL_CLIENT);
    }
}
