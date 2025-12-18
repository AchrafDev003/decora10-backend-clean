<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

// IMPORTANTE: añade este use para registrar tu middleware
use App\Http\Middleware\CheckUserRole;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'CheckUserRole' => \App\Http\Middleware\CheckUserRole::class, // ✅ Asegúrate que esto está bien escrito
    ];
    protected $middlewareGroups = [
        'api' => [
            \Fruitcake\Cors\HandleCors::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];



}
