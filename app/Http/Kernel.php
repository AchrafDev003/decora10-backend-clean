<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

// IMPORTANTE: añade este use para registrar tu middleware
use App\Http\Middleware\CheckUserRole;


    use Illuminate\Http\Middleware\HandleCors;

class Kernel extends HttpKernel
{
    protected $middlewareGroups = [
        'api' => [
            HandleCors::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'CheckUserRole' => \App\Http\Middleware\CheckUserRole::class,
    ];
}




}
