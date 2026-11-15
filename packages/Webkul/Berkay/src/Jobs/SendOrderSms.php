<?php

namespace Webkul\Berkay\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Berkay\Services\SmsTemplates;
use Webkul\Sales\Repositories\OrderRepository;

class SendOrderSms implements ShouldQueue
{
    use Queueable;

    /** The maximum number of attempts. */
    public int $tries = 3;

    /** The maximum duration of one attempt in seconds. */
    public int $timeout = 25;

    /** Queue the notification only for committed order changes. */
    public function __construct(public int $orderId, public string $event = 'created', public ?string $token2 = null)
    {
        $this->afterCommit();
    }

    /** Prevent concurrent attempts for the same order event. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('berkay-sms-'.$this->orderId.'-'.$this->event))->expireAfter(60)];
    }

    /** Return the delays between retry attempts. */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /** Send the configured template once per order event. */
    public function handle(OrderRepository $orders, KavenegarSms $sms, SmsTemplates $templates): void
    {
        $event = match (true) {
            $this->event === 'created' => 'order',
            str_starts_with($this->event, 'confirmed-') => 'confirmed',
            str_starts_with($this->event, 'status-') => 'status',
            str_starts_with($this->event, 'shipped-') => 'shipped',
            str_starts_with($this->event, 'refunded-') => 'refunded',
            default => $this->event,
        };

        $token2 = $event === 'status' ? substr($this->event, strlen('status-')) : $this->token2;
        $template = $templates->template($event);

        if (
            ! $template
            && $templates->eventEnabled($event)
        ) {
            $status = match ($event) {
                'stage_approved' => 'تأیید',
                'confirmed' => 'فاکتور',
                'stage_building' => 'ساخت',
                'shipped' => 'ارسال',
                default => null,
            };

            if ($status) {
                $template = $templates->template('status');
                $token2 = $status;
            }
        }

        if (! $template) {
            return;
        }

        $forAdmin = in_array($event, SmsTemplates::ADMIN_EVENTS, true);
        $recipient = $forAdmin ? $templates->adminRecipient() : null;

        $key = 'berkay:sms:'.$this->orderId.':'.$this->event;

        if ($forAdmin) {
            if (! $recipient) {
                return;
            }

            $key .= ':'.hash('sha256', $recipient);
        }
        $order = $orders->find($this->orderId);

        if (
            ! $order
            || Cache::has($key)
        ) {
            return;
        }

        if (in_array($event, ['stage_review', 'stage_approved'], true)) {
            $workflow = app(OrderWorkflow::class);
            $expected = $event === 'stage_review' ? 'waiting' : 'approved';

            if (
                $workflow->record($order)?->review !== $expected
                || $workflow->ended($order)
                || (float) $order->base_grand_total <= 0
                || $workflow->paid($order)
            ) {
                return;
            }
        }

        $phone = null;

        $candidates = $forAdmin ? [$recipient] : [$order->customer?->phone, $order->billing_address?->phone, $order->shipping_address?->phone];

        foreach ($candidates as $candidate) {
            try {
                $phone = $sms->normalizePhone((string) $candidate);
                break;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        if (! $phone) {
            return;
        }

        $sms->lookup($phone, $template, (string) $order->increment_id, $token2);
        Cache::put($key, true, now()->addYear());
    }
}
