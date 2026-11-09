<?php

namespace Webkul\Berkay\Listeners;

use Throwable;
use Webkul\Berkay\Jobs\SendOrderSms;
use Webkul\Berkay\Services\SmsTemplates;
use Webkul\Sales\Models\Order;

class OrderSmsListener
{
    public function __construct(protected SmsTemplates $templates) {}

    public function created($order): void
    {
        $this->enqueue($order->id, 'created', 'order');
    }

    public function statusChanged($order): void
    {
        $event = match ($order->status) {
            Order::STATUS_PROCESSING => 'confirmed',
            Order::STATUS_COMPLETED => 'completed',
            Order::STATUS_CANCELED => 'canceled',
            default => null,
        };

        if ($event && $this->templates->template($event)) {
            $this->enqueue($order->id, $event, $event);

            return;
        }

        $this->enqueue($order->id, 'status-'.$order->status, 'status');
    }

    public function invoiced($invoice): void
    {
        $this->enqueue($invoice->order_id, 'confirmed', 'confirmed');
    }

    public function canceled($order): void
    {
        $this->statusChanged($order);
    }

    public function shipped($shipment): void
    {
        $tracking = preg_replace('/[^\pL\pN-]/u', '', (string) $shipment->track_number);

        $this->enqueue($shipment->order_id, 'shipped-'.$shipment->id, 'shipped', mb_substr($tracking, 0, 100) ?: '-');
    }

    public function refunded($refund): void
    {
        $this->enqueue($refund->order_id, 'refunded-'.$refund->id, 'refunded');
    }

    private function enqueue(int $orderId, string $event, string $template, ?string $token2 = null): void
    {
        try {
            if ($this->templates->template($template)) {
                SendOrderSms::dispatch($orderId, $event, $token2);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
