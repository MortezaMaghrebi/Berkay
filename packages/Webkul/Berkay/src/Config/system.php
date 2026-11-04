<?php

return [
    ['key' => 'berkay', 'name' => 'berkay::app.brand', 'info' => 'berkay::app.settings-info', 'sort' => 1],
    ['key' => 'berkay.store', 'name' => 'berkay::app.store-settings', 'info' => 'berkay::app.settings-info', 'sort' => 1],
    [
        'key' => 'berkay.store.identity',
        'name' => 'berkay::app.identity',
        'info' => 'berkay::app.identity-info',
        'sort' => 1,
        'fields' => [
            ['name' => 'name', 'title' => 'berkay::app.brand-name', 'type' => 'text', 'validation' => 'nullable|max:80'],
            ['name' => 'tagline', 'title' => 'berkay::app.tagline', 'type' => 'text', 'validation' => 'nullable|max:180'],
            ['name' => 'hero_title', 'title' => 'berkay::app.hero-title', 'type' => 'text', 'validation' => 'nullable|max:100'],
            ['name' => 'hero_description', 'title' => 'berkay::app.hero-description', 'type' => 'textarea', 'validation' => 'nullable|max:500'],
            ['name' => 'support_phone', 'title' => 'berkay::app.support-phone', 'type' => 'text', 'validation' => 'nullable|max:30'],
            ['name' => 'custom_orders', 'title' => 'berkay::app.custom-orders-enabled', 'type' => 'boolean'],
        ],
    ],
    [
        'key' => 'sales.payment_methods.berkay_zarinpal',
        'name' => 'berkay::app.zarinpal',
        'info' => 'berkay::app.zarinpal-info',
        'sort' => 1,
        'fields' => [
            ['name' => 'active', 'title' => 'berkay::app.enable', 'type' => 'boolean'],
            ['name' => 'title', 'title' => 'berkay::app.title', 'type' => 'text', 'validation' => 'nullable|max:100'],
            ['name' => 'description', 'title' => 'berkay::app.description', 'type' => 'textarea', 'validation' => 'nullable|max:500'],
            ['name' => 'sort', 'title' => 'berkay::app.sort', 'type' => 'number', 'validation' => 'nullable|integer|min:0|max:100'],
        ],
    ],
];
