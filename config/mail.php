<?php

return [

    'default' => env('MAIL_MAILER', 'sendgrid'),

    'mailers' => [
        'sendgrid' => [
            'transport' => 'sendgrid',
            'api_key'  => env('SENDGRID_API_KEY'),
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
            'mailers'  => ['sendgrid', 'log'],
            'retry_after' => 60,
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'decora10.colchon10@gmail.com'),
        'name'    => env('MAIL_FROM_NAME', 'Decora10'),
    ],

];
