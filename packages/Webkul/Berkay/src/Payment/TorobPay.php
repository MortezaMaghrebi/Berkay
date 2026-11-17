<?php

namespace Webkul\Berkay\Payment;

use InvalidArgumentException;
use Webkul\Berkay\Services\KavenegarSms;

class TorobPay extends DirectBank
{
    /** Identify this checkout method. */
    protected $code = 'berkay_torobpay';

    /** Identify the provider independently of its checkout code. */
    protected string $provider = 'torobpay';

    /** Require the registered mobile number expected by the provider. */
    public function isAvailable(): bool
    {
        if (! parent::isAvailable()) {
            return false;
        }

        try {
            app(KavenegarSms::class)->normalizePhone((string) auth()->guard('customer')->user()?->phone);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
