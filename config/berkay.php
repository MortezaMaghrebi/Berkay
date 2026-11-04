<?php

return [
    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => env('ZARINPAL_SANDBOX', true),
    ],
    'torob' => ['enabled' => env('TOROB_ENABLED', false)],
    'kavenegar' => [
        'enabled' => env('KAVENEGAR_ENABLED', false),
        'api_key' => env('KAVENEGAR_API_KEY'),
        'order_template' => env('KAVENEGAR_ORDER_TEMPLATE'),
        'status_template' => env('KAVENEGAR_STATUS_TEMPLATE'),
    ],
];
