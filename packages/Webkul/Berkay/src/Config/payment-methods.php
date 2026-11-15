<?php

use Webkul\Berkay\Payment\IranKish;
use Webkul\Berkay\Payment\Mellat;
use Webkul\Berkay\Payment\Zarinpal;

return [
    'berkay_zarinpal' => [
        'class' => Zarinpal::class,
        'code' => 'berkay_zarinpal',
        'title' => 'زرین‌پال',
        'description' => 'پرداخت امن با کارت بانکی',
        'active' => false,
        'sort' => 1,
        'logo' => 'zarinpal',
    ],
    'berkay_mellat' => ['class' => Mellat::class, 'code' => 'berkay_mellat', 'title' => 'بانک ملت (به‌پرداخت)', 'sort' => 2, 'logo' => 'mellat'],
    'berkay_irankish' => ['class' => IranKish::class, 'code' => 'berkay_irankish', 'title' => 'بانک تجارت (ایران‌کیش)', 'sort' => 3, 'logo' => 'tejarat'],
];
