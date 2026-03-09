<?php

return [
    'mode' => env('PAYDUNYA_MODE', 'test'),

    'test' => [
        'master_key' => env('PAYDUNYA_TEST_MASTER_KEY'),
        'public_key' => env('PAYDUNYA_TEST_PUBLIC_KEY'),
        'private_key' => env('PAYDUNYA_TEST_PRIVATE_KEY'),
        'token' => env('PAYDUNYA_TEST_TOKEN'),
    ],

    'live' => [
        'master_key' => env('PAYDUNYA_LIVE_MASTER_KEY'),
        'public_key' => env('PAYDUNYA_LIVE_PUBLIC_KEY'),
        'private_key' => env('PAYDUNYA_LIVE_PRIVATE_KEY'),
        'token' => env('PAYDUNYA_LIVE_TOKEN'),
    ],

    'store' => [
        'name' => 'BioSen 100',
        'tagline' => 'Produits bio du Sénégal',
        'phone_number' => '+221782904830',
        'address' => 'Dakar, Sénégal',
        'website_url' => env('APP_FRONTEND_URL', 'http://localhost:4200'),
        'logo_url' => env('APP_FRONTEND_URL', 'http://localhost:4200') . '/assets/img/logo.png',
    ],
];
