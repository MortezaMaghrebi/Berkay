<?php

namespace Webkul\Berkay\Payment;

use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Payment\Payment\Payment;

class Zarinpal extends Payment
{
    /** Identify the Iranian payment method at checkout. */
    protected $code = 'berkay_zarinpal';

    /** Show the gateway only when explicitly enabled and correctly configured. */
    public function isAvailable()
    {
        $currency = core()->getCurrentChannel()?->base_currency?->code;

        return parent::isAvailable()
            && filled(app(IntegrationSettings::class)->get('zarinpal.merchant_id'))
            && in_array($currency, ['IRR', 'IRT'], true);
    }

    /** Start the server-controlled gateway handshake. */
    public function getRedirectUrl()
    {
        return route('shop.berkay.zarinpal.start');
    }
}
