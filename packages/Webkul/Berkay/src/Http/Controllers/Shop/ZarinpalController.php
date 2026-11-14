<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;
use Webkul\Berkay\Listeners\OrderSmsListener;
use Webkul\Berkay\Payment\Zarinpal;
use Webkul\Berkay\Repositories\PaymentAttemptRepository;
use Webkul\Berkay\Services\IranianMoney;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Berkay\Services\ZarinpalGateway;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class ZarinpalController extends Controller
{
    /** Inject payment, order and invoice boundaries. */
    public function __construct(
        protected PaymentAttemptRepository $attempts,
        protected OrderRepository $orders,
        protected InvoiceRepository $invoices,
        protected OrderTransactionRepository $transactions,
        protected ZarinpalGateway $gateway,
        protected IranianMoney $money
    ) {}

    /** Freeze an agreed order and reserve its inventory before bank redirection. */
    public function start(): RedirectResponse
    {
        $cart = Cart::getCart();

        if (
            ! $cart
            || ! $cart->is_active
            || $cart->payment?->method !== 'berkay_zarinpal'
            || ! $cart->billing_address
            || Cart::hasError()
            || ! app(Zarinpal::class)->isAvailable()
        ) {
            return $this->failed();
        }

        if (
            $cart->haveStockableItems()
            && (! $cart->shipping_address || ! $cart->selected_shipping_rate)
        ) {
            return $this->failed();
        }

        try {
            Cart::collectTotals();
            if (! app(\Webkul\Berkay\Shipping\ShippingQuote::class)->refresh(Cart::getCart())) {
                return redirect()->route('shop.checkout.onepage.index')
                    ->with('warning', trans('berkay::delivery.quote-changed'));
            }
            $cart = Cart::getCart();

            if ($review = app(OrderWorkflow::class)->deferCheckout($cart)) {
                return $review;
            }

            return Cache::lock('berkay.payment.cart.'.$cart->id, 60)->block(5, function () use ($cart) {
                if ($this->orders->findOneWhere(['cart_id' => $cart->id])) {
                    return $this->failed();
                }

                Cart::collectTotals();
                $cart = Cart::getCart();
                $amount = $this->money->toRial($cart->base_grand_total, $cart->base_currency_code);

                if ($amount < 1000) {
                    return $this->failed();
                }

                $token = Str::random(64);
                $context = $this->gateway->context();
                $authority = $this->gateway->purchase($amount, route('shop.berkay.zarinpal.callback', $token), $context);
                $order = $this->orders->create((new OrderResource($cart))->jsonSerialize());
                $attempt = $this->attempts->create([
                    'token' => $token,
                    'order_id' => $order->id,
                    'cart_id' => $cart->id,
                    'provider' => 'zarinpal',
                    'authority' => $authority,
                    'amount_rial' => $amount,
                    'gateway_context' => $context,
                    'status' => 'pending',
                ]);

                session()->put('berkay.payment.token', $attempt->token);
                Cart::deActivateCart();

                return redirect()->away($this->gateway->redirectUrl($authority, $context));
            });
        } catch (Throwable) {
            return $this->failed();
        }
    }

    /** Open payment for an approved order owned by the signed-in customer. */
    public function payApproved(int $id, OrderWorkflow $workflow): RedirectResponse
    {
        $order = $this->orders->findOrFail($id);
        abort_unless(
            (int) $order->customer_id === auth()->guard('customer')->id()
            && (int) $order->channel_id === core()->getCurrentChannel()->id
            && $order->payment?->method === 'berkay_zarinpal',
            404
        );
        if ($workflow->record($order)?->review !== 'approved') {
            return redirect()->route('shop.customers.account.orders.view', $order->id)
                ->with('info', trans('berkay::workflow.waiting'));
        }

        if ($workflow->ended($order) || $workflow->paid($order) || ! $order->canInvoice()) {
            return redirect()->route('shop.customers.account.orders.view', $order->id);
        }

        try {
            return Cache::lock('berkay.payment.order.'.$order->id, 60)->block(5, function () use ($order, $workflow) {
                $order = $order->fresh();
                if ($workflow->ended($order) || $workflow->paid($order) || ! $order->canInvoice()) {
                    return redirect()->route('shop.customers.account.orders.view', $order->id);
                }

                $attempt = $this->attempts->findOneWhere(['order_id' => $order->id, 'provider' => 'zarinpal']);
                if ($attempt && $attempt->status !== 'pending') {
                    return redirect()->route('shop.customers.account.orders.view', $order->id);
                }

                if (! $attempt) {
                    if (! app(Zarinpal::class)->isAvailable()) {
                        return redirect()->route('shop.customers.account.orders.view', $order->id)
                            ->with('error', trans('berkay::workflow.gateway-disabled'));
                    }

                    $amount = $this->money->toRial($order->base_grand_total, $order->base_currency_code);
                    $token = Str::random(64);
                    $context = $this->gateway->context();
                    $authority = $this->gateway->purchase($amount, route('shop.berkay.zarinpal.callback', $token), $context);
                    $attempt = $this->attempts->create([
                        'token' => $token,
                        'order_id' => $order->id,
                        'cart_id' => $order->cart_id,
                        'provider' => 'zarinpal',
                        'authority' => $authority,
                        'amount_rial' => $amount,
                        'gateway_context' => $context,
                        'status' => 'pending',
                    ]);
                }

                session()->put('berkay.payment.token', $attempt->token);

                return redirect()->away($this->gateway->redirectUrl($attempt->authority, $attempt->gateway_context))
                    ->header('Referrer-Policy', 'no-referrer');
            });
        } catch (Throwable) {
            return redirect()->route('shop.customers.account.orders.view', $order->id)
                ->with('error', trans('berkay::app.payment-not-completed'));
        }
    }

    /** Verify bank authority and finalize an invoice idempotently. */
    public function callback(string $token): RedirectResponse|View
    {
        $attempt = $this->attempts->findOneWhere(['token' => $token, 'provider' => 'zarinpal']);
        abort_unless($attempt, 404);

        if (! hash_equals($attempt->authority, (string) request('Authority'))) {
            return $this->failed();
        }

        try {
            $attempt = Cache::lock('berkay.payment.attempt.'.$attempt->id, 90)->block(5, function () use ($attempt) {
                $current = $this->attempts->findOrFail($attempt->id);

                if ($current->status === 'pending') {
                    if (request('Status') !== 'OK') {
                        return $current;
                    }

                    $receipt = $this->gateway->verify($current->authority, $current->amount_rial, $current->gateway_context);
                    $current = $this->attempts->recordVerification($current->id, $receipt);
                }

                return $this->attempts->finalize($current->id, function ($verified) {
                    $order = $this->orders->findOrFail($verified->order_id);

                    if (
                        ! $order->canInvoice()
                        || $order->payment?->method !== 'berkay_zarinpal'
                        || $this->money->toRial($order->base_grand_total, $order->base_currency_code) !== $verified->amount_rial
                    ) {
                        throw new \RuntimeException('The order requires payment reconciliation.');
                    }

                    $items = [];

                    foreach ($order->items as $item) {
                        if ($item->qty_to_invoice > 0) {
                            $items[$item->id] = $item->qty_to_invoice;
                        }
                    }

                    $invoice = $this->invoices->create(['order_id' => $order->id, 'invoice' => ['items' => $items]]);
                    $this->transactions->create([
                        'transaction_id' => $verified->receipt,
                        'status' => 'paid',
                        'type' => 'zarinpal',
                        'payment_method' => 'berkay_zarinpal',
                        'order_id' => $order->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $order->grand_total,
                    ]);

                    if ($verified->amount_rial > 0 && ($verified->gateway_context['sandbox'] ?? null) === false) {
                        app(OrderSmsListener::class)->paid($order->id);
                    }
                });
            });

            if (
                $attempt->status === 'paid'
                && hash_equals((string) session('berkay.payment.token'), $token)
            ) {
                session()->flash('order_id', $attempt->order_id);

                return redirect()->route('shop.checkout.onepage.success');
            }
            if ($attempt->status === 'paid') {
                return redirect()->route('shop.home.index')->with('success', trans('berkay::app.payment-completed'));
            }
        } catch (Throwable) {
            return $this->failed($token);
        }

        return $this->failed($token);
    }

    /** Resume the same unpaid authority for the browser that initiated payment. */
    public function resume(string $token): RedirectResponse
    {
        abort_unless(hash_equals((string) session('berkay.payment.token'), $token), 403);
        $attempt = $this->attempts->findOneWhere(['token' => $token, 'provider' => 'zarinpal', 'status' => 'pending']);
        abort_unless($attempt, 404);
        abort_unless($this->orders->findOrFail($attempt->order_id)->canInvoice(), 409);

        return redirect()->away($this->gateway->redirectUrl($attempt->authority, $attempt->gateway_context))
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Return a generic failure without disclosing customer or provider data. */
    protected function failed(?string $token = null): RedirectResponse|View
    {
        if ($token && hash_equals((string) session('berkay.payment.token'), $token)) {
            $attempt = $this->attempts->findOneWhere(['token' => $token, 'provider' => 'zarinpal']);

            return view('berkay::shop.payment-result', [
                'token' => $token,
                'canRetry' => $attempt?->status === 'pending',
            ]);
        }

        return redirect()->route('shop.checkout.cart.index')
            ->with('error', trans('berkay::app.payment-not-completed'));
    }
}
