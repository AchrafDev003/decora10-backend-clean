<?php

return [

    // Qué rutas deben permitir CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Métodos permitidos
    'allowed_methods' => ['*'],

    // Orígenes permitidos (ajusta según entorno)
    'allowed_origins' => [
        'http://localhost:5173', // Vite dev
        'http://localhost:5174',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://localhost:3000', // React dev
        'http://127.0.0.1:3000',
        env('FRONTEND_URL', '') // Producción (lee de .env)

    ],

    // Patrón de orígenes (puede usar regex)
    'allowed_origins_patterns' => [],

    // Cabeceras permitidas
    'allowed_headers' => ['*'],

    // Cabeceras expuestas al frontend
    'exposed_headers' => ['Authorization', 'Content-Type'],

    // Max-age de preflight (opcional)
    'max_age' => 0,

    // Credenciales: si usas cookies de sesión, pon true; si solo Bearer token, false
    'supports_credentials' => false,
];


