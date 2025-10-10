<?php

return [
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'server_key' => env('MIDTRANS_SERVER_KEY'),

    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => true,
    'is_3ds' => true,

    // Optional: Custom field mappings for order attributes
    'custom_field_mapping' => [
        'order_id' => 'transaction_number',
        'gross_amount' => 'total_price',
    ],
];