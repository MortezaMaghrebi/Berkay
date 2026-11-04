<?php

use Webkul\Berkay\Payment\Zarinpal;

return [
    'berkay_zarinpal' => [
        'class' => Zarinpal::class,
        'code' => 'berkay_zarinpal',
        'title' => 'زرین‌پال',
        'description' => 'پرداخت امن با کارت بانکی',
        'active' => false,
        'sort' => 1,
    ],
];
