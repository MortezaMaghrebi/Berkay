<?php

namespace Webkul\Berkay\Services;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Listeners\OrderSmsListener;
use Webkul\Checkout\Contracts\Cart as CartContract;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;

class OrderWorkflow
{
    /** Default merchandise subtotal limit in tomans. */
    public const DEFAULT_AMOUNT_LIMIT = 100_000_000;

    public const STEPS = ['review', 'approval', 'paid', 'building', 'shipped', 'delivered'];

    /** Inject order storage and customer notifications. */
    public function __construct(protected OrderRepository $orders, protected OrderSmsListener $notifications) {}

    /** Check the merchandise subtotal before discounts, shipping and tax. */
    public function requiresReview(CartContract $cart): bool
    {
        $auto = core()->getConfigData('berkay.workshop.review.auto_approve') ?? true;
        $quantity = (int) core()->getConfigData('berkay.workshop.review.quantity_limit');
        $amount = (int) core()->getConfigData('berkay.workshop.review.amount_limit');
        $amount = $amount > 0 ? $amount : self::DEFAULT_AMOUNT_LIMIT;
        $baseLimit = $cart->base_currency_code === 'IRR' ? $amount * 10 : $amount;

        return ! $auto
            || ($quantity > 0 && $cart->items->sum('quantity') > $quantity)
            || (float) $cart->base_sub_total > $baseLimit;
    }

    /** Serialize order creation and payment initiation for the same cart. */
    public function lockCheckout(int $cartId, Closure $submit): mixed
    {
        return Cache::lock('berkay.payment.cart.'.$cartId, 60)->block(5, $submit);
    }

    /** Persist the reviewed cart as an order before allowing a payment attempt. */
    public function deferCheckout(CartContract $cart): ?RedirectResponse
    {
        $customer = auth()->guard('customer')->user();
        $existing = $this->orders->findOneWhere(['cart_id' => $cart->id]);

        if ($existing) {
            Cart::deActivateCart();

            if ($customer && (int) $existing->customer_id === $customer->id) {
                return redirect()->route('shop.customers.account.orders.view', $existing->id);
            }

            return redirect()->route('shop.checkout.cart.index');
        }

        if (! $this->requiresReview($cart)) {
            return null;
        }

        if (! $customer) {
            session()->put('shop.url.intended', route('shop.checkout.onepage.index'));

            return redirect()->route('shop.customer.session.index')->with('info', trans('berkay::workflow.signIn'));
        }

        $order = $this->orders->create((new OrderResource($cart))->jsonSerialize() + ['berkay_requires_review' => true]);

        Cart::deActivateCart();

        return redirect()->route('shop.customers.account.orders.view', $order->id)
            ->with('info', trans('berkay::workflow.waiting'));
    }

    /** Record the review state before order-created listeners can act on the order. */
    public function holdWhenRequested(Order $order, array $data): void
    {
        if (! ($data['berkay_requires_review'] ?? false)) {
            return;
        }

        $this->query()->insert([
            'order_id' => $order->id,
            'review' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->notifications->sendAfterCommit($order->id, 'stage_review');
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
                $this->notifications->sendAfterCommit($order->id, 'stage_approved');

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
            $updated = $this->query()->where('order_id', $order->id)->whereNull($action.'_at')
                ->update([$action.'_at' => now(), 'updated_at' => now()]);

            if (
                $updated
                && $action === 'building'
            ) {
                $this->notifications->sendAfterCommit($order->id, 'stage_building');
            }
        });
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
            'approval' => ! $waiting,
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
