<?php

use Webkul\Berkay\Services\ProductCollections;

return [
    ['key' => 'berkay', 'name' => 'berkay::app.brand', 'info' => 'berkay::app.settings-info', 'sort' => 1],
    ['key' => 'berkay.home', 'name' => 'berkay::collections.settings', 'info' => 'berkay::collections.settings-info', 'sort' => 2],
    [
        'key' => 'berkay.home.collections',
        'name' => 'berkay::collections.settings',
        'info' => 'berkay::collections.settings-info',
        'sort' => 1,
        'fields' => array_merge(...array_map(
            fn ($key) => [
                ['name' => $key.'_enabled', 'title' => 'berkay::collections.'.$key, 'type' => 'boolean', 'default' => 1, 'channel_based' => true],
                ['name' => $key.'_limit', 'title' => 'berkay::collections.'.$key.'-limit', 'type' => 'number', 'validation' => 'required|integer|min:'.($key === 'discounted' ? 4 : 5).'|max:20', 'default' => 10, 'channel_based' => true],
            ],
            ProductCollections::KEYS
        )),
    ],
    ['key' => 'berkay.workshop', 'name' => 'berkay::workflow.settings', 'info' => 'berkay::workflow.settingsHelp', 'sort' => 3],
    [
        'key' => 'berkay.workshop.review',
        'name' => 'berkay::workflow.settings',
        'info' => 'berkay::workflow.settingsHelp',
        'sort' => 1,
        'fields' => [
            ['name' => 'auto_approve', 'title' => 'berkay::workflow.auto', 'type' => 'boolean', 'default' => 1, 'channel_based' => true],
            ['name' => 'quantity_limit', 'title' => 'berkay::workflow.quantity', 'type' => 'number', 'validation' => 'nullable|integer|min:0|max:100000', 'default' => 0, 'channel_based' => true],
            ['name' => 'amount_limit', 'title' => 'berkay::workflow.amount', 'type' => 'number', 'validation' => 'nullable|numeric|min:0|max:99999999999', 'default' => 0, 'channel_based' => true],
        ],
    ],
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
    ...array_map(fn ($code) => [
        'key' => 'sales.carriers.'.$code,
        'name' => 'berkay::delivery.'.$code,
        'info' => 'berkay::delivery.settings-help',
        'sort' => $code === 'berkay_post' ? 1 : 2,
        'fields' => [
            ['name' => 'active', 'title' => 'berkay::app.enable', 'type' => 'boolean', 'default' => 1, 'channel_based' => true],
            ['name' => 'charge_mode', 'title' => 'berkay::delivery.charge-mode', 'type' => 'select', 'default' => 'collect',
                'validation' => 'required|in:collect,fixed', 'channel_based' => true, 'options' => [
                    ['value' => 'collect', 'title' => 'berkay::delivery.collect'],
                    ['value' => 'fixed', 'title' => 'berkay::delivery.fixed'],
                ],
            ],
            ['name' => 'fixed_price', 'title' => 'berkay::delivery.fixed-price', 'type' => 'number', 'validation' => 'nullable|numeric|min:0|max:99999999', 'default' => 0, 'channel_based' => true],
            ['name' => 'free_threshold', 'title' => 'berkay::delivery.free-threshold', 'type' => 'number', 'validation' => 'nullable|numeric|min:0|max:99999999999', 'default' => 0, 'channel_based' => true],
        ],
    ], ['berkay_post', 'berkay_freight']),
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
