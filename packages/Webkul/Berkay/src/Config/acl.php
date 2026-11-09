<?php

return [
    ['key' => 'berkay.attributes', 'name' => 'berkay::app.variant-settings.title', 'route' => ['admin.berkay.attributes.index', 'admin.berkay.attributes.save'], 'sort' => 6],
    ['key' => 'berkay', 'name' => 'berkay::app.operations', 'route' => 'admin.berkay.index', 'sort' => 20],
    ['key' => 'berkay.users', 'name' => 'berkay::app.users.title', 'route' => ['admin.berkay.users.index', 'admin.berkay.users.create', 'admin.berkay.users.store', 'admin.berkay.users.edit', 'admin.berkay.users.update'], 'sort' => 5],
    ['key' => 'berkay.overview', 'name' => 'berkay::app.operations', 'route' => 'admin.berkay.index', 'sort' => 0],
    ['key' => 'berkay.articles', 'name' => 'berkay::app.magazine', 'route' => 'admin.berkay.articles.index', 'sort' => 1],
    ['key' => 'berkay.articles.create', 'name' => 'berkay::app.create', 'route' => ['admin.berkay.articles.create', 'admin.berkay.articles.store', 'admin.berkay.articles.parse'], 'sort' => 1],
    ['key' => 'berkay.articles.edit', 'name' => 'berkay::app.edit', 'route' => ['admin.berkay.articles.edit', 'admin.berkay.articles.update'], 'sort' => 2],
    ['key' => 'berkay.articles.delete', 'name' => 'berkay::app.delete', 'route' => 'admin.berkay.articles.destroy', 'sort' => 3],
    ['key' => 'berkay.custom-orders', 'name' => 'berkay::app.custom-orders', 'route' => ['admin.berkay.custom_orders.index', 'admin.berkay.custom_orders.image'], 'sort' => 2],
    ['key' => 'berkay.custom-orders.edit', 'name' => 'berkay::app.edit', 'route' => 'admin.berkay.custom_orders.update', 'sort' => 1],
    ['key' => 'berkay.payments', 'name' => 'berkay::app.payments', 'route' => 'admin.berkay.payments.index', 'sort' => 3],
    ['key' => 'berkay.integrations', 'name' => 'berkay::app.integrations', 'route' => ['admin.berkay.integrations.index', 'admin.berkay.kavenegar.index'], 'sort' => 4],
    ['key' => 'berkay.integrations.edit', 'name' => 'berkay::app.edit', 'route' => ['admin.berkay.integrations.update', 'admin.berkay.kavenegar.update', 'admin.berkay.kavenegar.send'], 'sort' => 1],
];
