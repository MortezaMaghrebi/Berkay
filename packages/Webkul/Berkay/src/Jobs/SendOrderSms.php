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

    public int $tries = 3;

    public int $timeout = 25;

    public function __construct(public int $orderId, public string $event = 'created', public ?string $token2 = null)
    {
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('berkay-sms-'.$this->orderId.'-'.$this->event))->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(OrderRepository $orders, KavenegarSms $sms, SmsTemplates $templates): void
    {
        $event = match (true) {
            $this->event === 'created' => 'order',
            str_starts_with($this->event, 'status-') => 'status',
            str_starts_with($this->event, 'shipped-') => 'shipped',
            str_starts_with($this->event, 'refunded-') => 'refunded',
            default => $this->event,
        };

        if (! $template = $templates->template($event)) {
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

        if (! $order || Cache::has($key)) {
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

        $candidates = $forAdmin ? [$recipient] : [$order->customer?->phone, $order->billing_address?->phone];

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

        $token2 = $event === 'status' ? substr($this->event, strlen('status-')) : ($this->token2 ?? null);
        $sms->lookup($phone, $template, (string) $order->increment_id, $token2);
        Cache::put($key, true, now()->addYear());
    }
}
