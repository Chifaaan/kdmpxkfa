<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('SSO_DIGIKOPERASI_URL', 'https://koperasi.berasumkm.id'),
        'https://images.tokopedia.net',
        'https://prd-app-kf-ehealth-production-s3-ap-southeast-1.imgix.net',
        'https://app.sandbox.midtrans.com',
        'https://js-agent.newrelic.com',
        'https://midtrans.com',
        'https://api.midtrans.com',
        'https://app.midtrans.com',
        'https://snap.midtrans.com',
        'https://app.midtrans.com/snap/v1',
        'https://app.midtrans.com/snap/v2',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 300,

    'supports_credentials' => true,
];
