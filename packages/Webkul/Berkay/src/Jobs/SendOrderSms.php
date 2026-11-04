<?php

namespace Webkul\Berkay\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Sales\Repositories\OrderRepository;

class SendOrderSms implements ShouldQueue
{
    use Queueable;

    /** Bound notification retries independently of order processing. */
    public int $tries = 3;

    /** Allow the network request to finish within the worker timeout. */
    public int $timeout = 25;

    /** Capture the order identity and notification event. */
    public function __construct(public int $orderId, public string $event = 'created')
    {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    /** Serialize duplicate notifications for the same order event. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('berkay-sms-'.$this->orderId.'-'.$this->event))->expireAfter(60)];
    }

    /** Retry temporary provider failures with increasing delays. */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /** Send a configured transactional message after the order commits. */
    public function handle(OrderRepository $orders, KavenegarSms $sms): void
    {
        $template = app(IntegrationSettings::class)->get('kavenegar.'.($this->event === 'created' ? 'order_template' : 'status_template'));

        if (
            ! app(IntegrationSettings::class)->get('kavenegar.enabled')
            || ! app(IntegrationSettings::class)->get('kavenegar.api_key')
            || ! $template
        ) {
            return;
        }

        $order = $orders->find($this->orderId);

        if (! $order) {
            return;
        }

        $key = 'berkay:sms:'.$this->orderId.':'.$this->event;

        if (Cache::has($key)) {
            return;
        }

        try {
            $phone = $sms->normalizePhone((string) ($order->billing_address?->phone ?: $order->customer?->phone));
        } catch (InvalidArgumentException) {
            return;
        }

        $status = $this->event === 'created' ? null : substr($this->event, strlen('status-'));
        $sms->lookup($phone, $template, (string) $order->increment_id, $status);
        Cache::put($key, true, now()->addYear());
    }
}
