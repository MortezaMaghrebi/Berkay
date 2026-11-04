<?php

return [
    ['key' => 'berkay', 'name' => 'berkay::app.operations', 'route' => 'admin.berkay.index', 'sort' => 20],
    ['key' => 'berkay.overview', 'name' => 'berkay::app.operations', 'route' => 'admin.berkay.index', 'sort' => 0],
    ['key' => 'berkay.articles', 'name' => 'berkay::app.magazine', 'route' => 'admin.berkay.articles.index', 'sort' => 1],
    ['key' => 'berkay.articles.create', 'name' => 'berkay::app.create', 'route' => ['admin.berkay.articles.create', 'admin.berkay.articles.store', 'admin.berkay.articles.parse'], 'sort' => 1],
    ['key' => 'berkay.articles.edit', 'name' => 'berkay::app.edit', 'route' => ['admin.berkay.articles.edit', 'admin.berkay.articles.update'], 'sort' => 2],
    ['key' => 'berkay.articles.delete', 'name' => 'berkay::app.delete', 'route' => 'admin.berkay.articles.destroy', 'sort' => 3],
    ['key' => 'berkay.custom-orders', 'name' => 'berkay::app.custom-orders', 'route' => 'admin.berkay.custom_orders.index', 'sort' => 2],
    ['key' => 'berkay.custom-orders.edit', 'name' => 'berkay::app.edit', 'route' => 'admin.berkay.custom_orders.update', 'sort' => 1],
    ['key' => 'berkay.payments', 'name' => 'berkay::app.payments', 'route' => 'admin.berkay.payments.index', 'sort' => 3],
    ['key' => 'berkay.integrations', 'name' => 'berkay::app.integrations', 'route' => 'admin.berkay.integrations.index', 'sort' => 4],
    ['key' => 'berkay.integrations.edit', 'name' => 'berkay::app.edit', 'route' => 'admin.berkay.integrations.update', 'sort' => 1],
];
