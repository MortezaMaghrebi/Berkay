<?php

namespace Webkul\Berkay\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Jobs\SendOrderSms;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;

class OrderWorkflow
{
    public const STEPS = ['review', 'approval', 'paid', 'building', 'shipped', 'delivered'];

    public function __construct(protected OrderRepository $orders) {}

    public function requiresReview($cart): bool
    {
        $auto = core()->getConfigData('berkay.workshop.review.auto_approve') ?? true;
        $quantity = (int) core()->getConfigData('berkay.workshop.review.quantity_limit');
        $amount = (float) core()->getConfigData('berkay.workshop.review.amount_limit');

        return ! $auto
            || ($quantity > 0 && $cart->items->sum('quantity') > $quantity)
            || ($amount > 0 && (float) $cart->base_grand_total > $amount);
    }

    /** Persist the reviewed cart as an order before allowing a payment attempt. */
    public function deferCheckout($cart): ?RedirectResponse
    {
        if (! $this->requiresReview($cart)) {
            return null;
        }

        $customer = auth()->guard('customer')->user();

        if (! $customer) {
            session()->put('shop.url.intended', route('shop.checkout.onepage.index'));

            return redirect()->route('shop.customer.session.index')->with('info', trans('berkay::workflow.sign-in'));
        }

        $order = Cache::lock('berkay.payment.cart.'.$cart->id, 60)->block(5, function () use ($cart, $customer) {
            $existing = $this->orders->findOneWhere(['cart_id' => $cart->id]);
            if ($existing) {
                abort_unless((int) $existing->customer_id === $customer->id, 403);

                return $existing;
            }

            return DB::transaction(function () use ($cart) {
                $order = $this->orders->create((new OrderResource($cart))->jsonSerialize());
                $this->query()->insert([
                    'order_id' => $order->id,
                    'review' => 'waiting',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $order;
            });
        });

        Cart::deActivateCart();

        return redirect()->route('shop.customers.account.orders.view', $order->id)
            ->with('info', trans('berkay::workflow.waiting'));
    }

    public function query()
    {
        return DB::table('berkay_order_workflows');
    }

    public function record(Order $order): ?object
    {
        return $this->query()->where('order_id', $order->id)->first();
    }

    public function waiting(Order $order): bool
    {
        return $this->query()->where('order_id', $order->id)->where('review', 'waiting')->exists();
    }

    public function advance(Order $order, string $action): void
    {
        DB::transaction(function () use ($order, $action) {
            $order = $order->newQuery()->lockForUpdate()->findOrFail($order->id);
            abort_if($this->ended($order), 409);
            $current = $this->record($order);

            if ($action === 'approve') {
                abort_unless($current?->review === 'waiting', 409);
                $this->query()->where('order_id', $order->id)->update([
                    'review' => 'approved', 'reviewed_at' => now(), 'updated_at' => now(),
                ]);

                return;
            }

            abort_if($current?->review === 'waiting', 409);

            if ($action === 'building') {
                abort_unless($this->paid($order) && ! $order->shipments()->exists(), 409);
            } else {
                abort_unless($this->paid($order) && $order->shipments()->exists() && ! $order->canShip(), 409);
            }

            $this->query()->insertOrIgnore([
                'order_id' => $order->id, 'review' => 'automatic', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->query()->where('order_id', $order->id)->whereNull($action.'_at')
                ->update([$action.'_at' => now(), 'updated_at' => now()]);
        });

        if ($action === 'approve') {
            SendOrderSms::dispatch($order->id, 'confirmed');
        }
    }

    public function paid(Order $order): bool
    {
        if ((float) $order->base_grand_total === 0.0) {
            return ! $this->waiting($order);
        }

        $invoiced = $order->invoices()->where('state', Invoice::STATUS_PAID)->sum('base_grand_total');

        return (float) $invoiced + 0.0001 >= (float) $order->base_grand_total;
    }

    public function ended(Order $order): bool
    {
        return in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_CLOSED, Order::STATUS_FRAUD], true);
    }

    public function timeline(Order $order): array
    {
        $record = $this->record($order);
        $waiting = $record?->review === 'waiting';
        $paid = ! $waiting && $this->paid($order);
        $hasShipment = $order->shipments()->exists();
        $shipped = $hasShipment && ! $order->canShip();
        $done = [
            'review' => ! $waiting,
            'approval' => ! $waiting && $paid,
            'paid' => $paid,
            'building' => filled($record?->building_at) || $shipped,
            'shipped' => $shipped,
            'delivered' => filled($record?->delivered_at),
        ];

        return [
            'record' => $record,
            'waiting' => $waiting,
            'paid' => $paid,
            'hasShipment' => $hasShipment,
            'shipped' => $shipped,
            'ended' => $this->ended($order),
            'done' => $done,
            'current' => collect($done)->search(false, true),
        ];
    }
}
