<?php

namespace Webkul\Berkay\Listeners;

use Illuminate\Support\Facades\DB;
use Throwable;
use Webkul\Berkay\Jobs\SendOrderSms;
use Webkul\Berkay\Services\SmsTemplates;
use Webkul\Sales\Models\Order;

class OrderSmsListener
{
    /** Resolve the enabled notification settings. */
    public function __construct(protected SmsTemplates $templates) {}

    /** Notify the customer and staff about a new order. */
    public function created($order): void
    {
        $this->sendAfterCommit($order->id, 'created');
        $this->sendAfterCommit($order->id, 'admin_new_order');
    }

    /** Notify staff when payment is confirmed. */
    public function paid(int $orderId): void
    {
        $this->sendAfterCommit($orderId, 'admin_order_paid');
    }

    /** Notify the customer of an order status change. */
    public function statusChanged($order): void
    {
        if ($order->status === Order::STATUS_PROCESSING) {
            return;
        }

        $event = match ($order->status) {
            Order::STATUS_COMPLETED => 'completed',
            Order::STATUS_CANCELED => 'canceled',
            default => null,
        };

        if (
            $event
            && ! $this->templates->eventEnabled($event)
        ) {
            return;
        }

        if (
            $event
            && $this->templates->template($event)
        ) {
            $this->sendAfterCommit($order->id, $event);

            return;
        }

        $this->sendAfterCommit($order->id, 'status-'.$order->status);
    }

    /** Notify the customer when an invoice is issued. */
    public function invoiced($invoice): void
    {
        $this->sendAfterCommit($invoice->order_id, 'confirmed-'.$invoice->id);
    }

    /** Notify the customer that the order was canceled. */
    public function canceled($order): void
    {
        $this->statusChanged($order);
    }

    /** Send the shipment tracking number to the customer. */
    public function shipped($shipment): void
    {
        $tracking = preg_replace('/[^\pL\pN-]/u', '', (string) $shipment->track_number);

        $this->sendAfterCommit($shipment->order_id, 'shipped-'.$shipment->id, mb_substr($tracking, 0, 100) ?: '-');
    }

    /** Notify the customer that a refund was created. */
    public function refunded($refund): void
    {
        $this->sendAfterCommit($refund->order_id, 'refunded-'.$refund->id);
    }

    /** Attempt delivery after commit and after the HTTP response. */
    public function sendAfterCommit(int $orderId, string $event, ?string $token2 = null): void
    {
        DB::afterCommit(fn () => \Illuminate\Support\defer(fn () => $this->deliver($orderId, $event, $token2))->always());
    }

    /** Attempt delivery without a queue worker and enqueue a retry if Kavenegar fails. */
    private function deliver(int $orderId, string $event, ?string $token2): void
    {
        try {
            SendOrderSms::dispatchSync($orderId, $event, $token2);
        } catch (Throwable $exception) {
            report($exception);

            if (in_array(config('queue.default'), ['sync', 'deferred'], true)) {
                return;
            }

            try {
                SendOrderSms::dispatch($orderId, $event, $token2);
            } catch (Throwable $queueException) {
                report($queueException);
            }
        }
    }
}
