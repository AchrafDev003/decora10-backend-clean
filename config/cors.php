<?php

return [

    // Qué rutas deben permitir CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Métodos permitidos
    'allowed_methods' => ['*'],

    // Orígenes permitidos
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // Producción en Vercel (deploy por defecto)
        'https://decora10-frontend-5ls7.vercel.app',

        // Tu dominio final
        'https://decora10.com',
        'https://www.decora10.com',

        // Permitir dominio configurado en .env
        env('FRONTEND_URL'),
    ],


    'allowed_origins_patterns' => [],

    // Cabeceras
    'allowed_headers' => ['*'],

    // Cabeceras expuestas
    'exposed_headers' => ['Authorization', 'Content-Type'],

    'max_age' => 0,

    // Solo Bearer token → false
    'supports_credentials' => false,
];
