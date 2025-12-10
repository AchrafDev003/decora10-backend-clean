<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME', 'decora10.colchon10@gmail.com'), // correo completo
            'password' => env('MAIL_PASSWORD'), // tu App Password de Gmail
            'timeout' => null,
            'auth_mode' => null,
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL', 'stack'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers'  => ['smtp', 'log'],
            'retry_after' => 60,
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'decora10.colchon10@gmail.com'),
        'name'    => env('MAIL_FROM_NAME', 'Decora10'),
    ],

];
