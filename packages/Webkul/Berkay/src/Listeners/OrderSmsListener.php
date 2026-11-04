<?php

namespace Webkul\Berkay\Listeners;

use Webkul\Berkay\Jobs\SendOrderSms;
use Webkul\Berkay\Services\IntegrationSettings;

class OrderSmsListener
{
    /** Enqueue a new-order notification only when the provider is configured. */
    public function created($order): void
    {
        if (
            app(IntegrationSettings::class)->get('kavenegar.enabled')
            && app(IntegrationSettings::class)->get('kavenegar.api_key')
            && app(IntegrationSettings::class)->get('kavenegar.order_template')
        ) {
            SendOrderSms::dispatch((int) $order->id);
        }
    }

    /** Enqueue a distinct notification for each order status. */
    public function statusChanged($order): void
    {
        if (
            app(IntegrationSettings::class)->get('kavenegar.enabled')
            && app(IntegrationSettings::class)->get('kavenegar.api_key')
            && app(IntegrationSettings::class)->get('kavenegar.status_template')
        ) {
            SendOrderSms::dispatch((int) $order->id, 'status-'.$order->status);
        }
    }
}
