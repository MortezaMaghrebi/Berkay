<?php

namespace Webkul\Berkay\Payment;

use Webkul\Berkay\Services\BankGateways;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Payment\Payment\Payment;

abstract class DirectBank extends Payment
{
    /** Identify the direct gateway independently of its checkout code. */
    protected string $provider;

    /** Offer the method only when its merchant credentials and runtime are ready. */
    public function isAvailable(): bool
    {
        $settings = app(IntegrationSettings::class);

        return (bool) $settings->get($this->provider.'.enabled')
            && in_array(core()->getCurrentChannel()?->base_currency?->code, ['IRT', 'IRR'], true)
            && app(BankGateways::class)->gateway($this->provider)->ready();
    }

    /** Show the provider's translated name at checkout and on receipts. */
    public function getTitle(): string
    {
        return config('payment_methods.'.$this->code.'.title');
    }

    /** Open the bank handshake on the storefront. */
    public function getRedirectUrl(): string
    {
        return route('shop.berkay.bank.start', $this->provider);
    }
}
