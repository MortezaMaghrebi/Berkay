<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;
use Webkul\Berkay\Listeners\OrderSmsListener;
use Webkul\Berkay\Repositories\PaymentAttemptRepository;
use Webkul\Berkay\Services\BankGateways;
use Webkul\Berkay\Services\IranianMoney;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Berkay\Services\ZarinpalGateway;
use Webkul\Berkay\Shipping\ShippingQuote;
use Webkul\Checkout\Facades\Cart;
use Webkul\Payment\Facades\Payment;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class OnlinePaymentController extends Controller
{
    /** Inject payment, order and invoice boundaries. */
    public function __construct(
        protected PaymentAttemptRepository $attempts,
        protected OrderRepository $orders,
        protected InvoiceRepository $invoices,
        protected OrderTransactionRepository $transactions,
        protected BankGateways $banks,
        protected IranianMoney $money
    ) {}

    /** Freeze an agreed order and reserve its inventory before bank redirection. */
    public function start(OrderWorkflow $workflow, string $provider = 'zarinpal'): RedirectResponse|View
    {
        $cart = Cart::getCart();

        if (
            ! $cart
            || ! $cart->is_active
            || $cart->payment?->method !== 'berkay_'.$provider
            || ! $cart->billing_address
            || Cart::hasError()
            || ! app(BankGateways::METHODS[$provider])->isAvailable()
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
            return $workflow->lockCheckout($cart->id, function () use ($cart, $workflow, $provider) {
                $current = $cart->fresh();

                if (
                    ! $current?->is_active
                    || $current->payment?->method !== 'berkay_'.$provider
                ) {
                    return $this->failed();
                }

                Cart::setCart($current);
                Cart::collectTotals();

                if (! app(ShippingQuote::class)->refresh(Cart::getCart())) {
                    return redirect()->route('shop.checkout.onepage.index')
                        ->with('warning', trans('berkay::delivery.quote-changed'));
                }

                $cart = Cart::getCart();

                if ($review = $workflow->deferCheckout($cart)) {
                    return $review;
                }

                $amount = $this->money->toRial($cart->base_grand_total, $cart->base_currency_code);

                if ($amount < 1000) {
                    return $this->failed();
                }

                [$token, $context, $authority] = $this->authorizePayment($provider, $amount);
                $order = $this->orders->create((new OrderResource($cart))->jsonSerialize());
                $attempt = $this->attempts->create([
                    'token' => $token,
                    'order_id' => $order->id,
                    'cart_id' => $cart->id,
                    'provider' => $provider,
                    'authority' => $authority,
                    'amount_rial' => $amount,
                    'gateway_context' => $context,
                    'status' => 'pending',
                ]);

                session()->put('berkay.payment.token', $attempt->token);
                Cart::deActivateCart();

                return $this->openBank($attempt);
            });
        } catch (Throwable $exception) {
            $this->logFailure($provider, 'start', $exception);

            return redirect()->route('shop.checkout.cart.index')->with('error', trans('berkay::workflow.gatewayDisabled'));
        }
    }

    /** Open payment for an approved order owned by the signed-in customer. */
    public function payApproved(int $id, OrderWorkflow $workflow, Request $request): RedirectResponse|View
    {
        $order = $this->orders->findOrFail($id);
        $provider = Str::after((string) $order->payment?->method, 'berkay_');
        abort_unless(
            (int) $order->customer_id === auth()->guard('customer')->id()
            && (int) $order->channel_id === core()->getCurrentChannel()->id
            && (isset(BankGateways::METHODS[$provider]) || $order->payment?->method === 'moneytransfer'),
            404
        );
        $requested = $request->validate(['method' => ['nullable', 'string', 'in:berkay_zarinpal,berkay_mellat,berkay_irankish,moneytransfer']])['method'] ?? null;
        if ($workflow->record($order)?->review !== 'approved') {
            return redirect()->route('shop.customers.account.orders.view', $order->id)
                ->with('info', trans('berkay::workflow.waiting'));
        }

        if ($workflow->ended($order) || $workflow->paid($order) || ! $order->canInvoice()) {
            return redirect()->route('shop.customers.account.orders.view', $order->id);
        }

        try {
            return Cache::lock('berkay.payment.order.'.$order->id, 60)->block(5, function () use ($order, $workflow, $requested) {
                $order = $order->fresh();
                if ($workflow->ended($order) || $workflow->paid($order) || ! $order->canInvoice()) {
                    return redirect()->route('shop.customers.account.orders.view', $order->id);
                }

                $attempt = $this->attempts->findOneWhere(['order_id' => $order->id]);
                $method = $order->payment->method;
                $provider = $attempt?->provider ?? Str::after($method, 'berkay_');

                if (
                    filled($requested)
                    && $requested !== $method
                    && ($attempt || $method === 'moneytransfer')
                ) {
                    return redirect()->route('shop.customers.account.orders.view', $order->id)
                        ->with('error', 'روش پرداخت این سفارش قبلاً ثبت شده است. از همان روش ادامه دهید.');
                }

                if ($attempt?->status === 'verified') {
                    $this->finalizePayment($attempt->id);

                    return redirect()->route('shop.customers.account.orders.view', $order->id)
                        ->with('success', trans('berkay::app.payment-completed'));
                }

                if ($attempt && $attempt->status !== 'pending') {
                    return redirect()->route('shop.customers.account.orders.view', $order->id);
                }

                if (! $attempt) {
                    $selected = $requested ?? $method;

                    if ($selected === 'moneytransfer' && $method === 'moneytransfer') {
                        return redirect(route('shop.customers.account.orders.view', $order->id).'#bank-transfer');
                    }

                    if (! collect(Payment::getPaymentMethods())->contains('method', $selected)) {
                        return redirect()->route('shop.customers.account.orders.view', $order->id)
                            ->with('error', trans('berkay::workflow.gatewayDisabled'));
                    }

                    if ($selected === 'moneytransfer') {
                        $order->payment()->update(['method' => $selected]);

                        return redirect(route('shop.customers.account.orders.view', $order->id).'#bank-transfer');
                    }

                    $provider = Str::after($selected, 'berkay_');
                    $amount = $this->money->toRial($order->base_grand_total, $order->base_currency_code);
                    [$token, $context, $authority] = $this->authorizePayment($provider, $amount);
                    $attempt = DB::transaction(function () use ($order, $selected, $provider, $token, $context, $authority, $amount) {
                        $order->payment()->update(['method' => $selected]);

                        return $this->attempts->create([
                            'token' => $token,
                            'order_id' => $order->id,
                            'cart_id' => $order->cart_id,
                            'provider' => $provider,
                            'authority' => $authority,
                            'amount_rial' => $amount,
                            'gateway_context' => $context,
                            'status' => 'pending',
                        ]);
                    });
                }

                session()->put('berkay.payment.token', $attempt->token);

                return $this->openBank($attempt);
            });
        } catch (Throwable $exception) {
            $this->logFailure($requested ? Str::after($requested, 'berkay_') : $provider, 'approved-order', $exception);

            return redirect()->route('shop.customers.account.orders.view', $order->id)
                ->with('error', trans('berkay::workflow.gatewayDisabled'));
        }
    }

    /** Verify bank authority and finalize an invoice idempotently. */
    public function callback(string $token, OrderWorkflow $workflow, string $provider = 'zarinpal'): RedirectResponse|View
    {
        $attempt = $this->attempts->findOneWhere(['token' => $token, 'provider' => $provider]);
        abort_unless($attempt, 404);
        [$authorityKey, $statusKey, $success] = match ($provider) {
            'mellat' => ['RefId', 'ResCode', '0'],
            'irankish' => ['token', 'responseCode', '00'],
            default => ['Authority', 'Status', 'OK'],
        };
        $authority = request()->input($authorityKey);
        $order = $this->orders->findOrFail($attempt->order_id);

        if (
            ! is_string($authority)
            || ! hash_equals($attempt->authority, $authority)
            || $workflow->waiting($order)
        ) {
            return $this->failed();
        }

        try {
            $attempt = Cache::lock('berkay.payment.attempt.'.$attempt->id, 90)->block(5, function () use ($attempt, $provider, $statusKey, $success) {
                $current = $this->attempts->findOrFail($attempt->id);

                if ($current->status === 'pending') {
                    if (
                        request()->input($statusKey) !== $success
                        || ! $this->orders->findOrFail($current->order_id)->canInvoice()
                    ) {
                        return $current;
                    }

                    $receipt = $this->banks->gateway($provider)->verify($current->authority, $current->amount_rial, $current->gateway_context, request()->only([
                        'SaleOrderId', 'SaleReferenceId', 'requestId', 'RequestId', 'acceptorId', 'amount', 'retrievalReferenceNumber', 'systemTraceAuditNumber',
                    ]));
                    $current = $this->attempts->recordVerification($current->id, $receipt);
                }

                return $this->finalizePayment($current->id);
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
        } catch (Throwable $exception) {
            $this->logFailure($provider, 'callback', $exception);

            return $this->failed($token);
        }

        return $this->failed($token);
    }

    /** Resume the same unpaid authority for the browser that initiated payment. */
    public function resume(string $token): RedirectResponse|View
    {
        abort_unless(hash_equals((string) session('berkay.payment.token'), $token), 403);
        $attempt = $this->attempts->findOneWhere(['token' => $token, 'status' => 'pending']);
        abort_unless($attempt, 404);
        abort_unless($this->orders->findOrFail($attempt->order_id)->canInvoice(), 409);

        return $this->openBank($attempt);
    }

    /** Return a generic failure without disclosing customer or provider data. */
    protected function failed(?string $token = null): RedirectResponse|View
    {
        if ($token && hash_equals((string) session('berkay.payment.token'), $token)) {
            $attempt = $this->attempts->findOneWhere(['token' => $token]);

            return view('berkay::shop.payment-result', [
                'token' => $token,
                'canRetry' => $attempt?->status === 'pending',
            ]);
        }

        return redirect()->route('shop.checkout.cart.index')
            ->with('error', trans('berkay::app.payment-not-completed'));
    }

    /** Create the gateway authority and freeze the merchant context for verification. */
    protected function authorizePayment(string $provider, int $amount): array
    {
        $token = Str::random(64);
        $gateway = $this->banks->gateway($provider);
        $context = $gateway->context();
        $callback = $provider === 'zarinpal'
            ? route('shop.berkay.zarinpal.callback', $token)
            : route('shop.berkay.bank.callback', [$token, $provider]);

        return [$token, $context, $gateway->purchase($amount, $callback, $context)];
    }

    /** Reconcile a verified payment with the saved order exactly once. */
    protected function finalizePayment(int $attemptId): mixed
    {
        return $this->attempts->finalize($attemptId, function ($verified) {
            $order = $this->orders->findOrFail($verified->order_id);

            if (
                ! $order->canInvoice()
                || $order->payment?->method !== 'berkay_'.$verified->provider
                || $this->money->toRial($order->base_grand_total, $order->base_currency_code) !== $verified->amount_rial
            ) {
                throw new \RuntimeException('The order requires payment reconciliation.');
            }

            $items = $order->items->filter(fn ($item) => $item->qty_to_invoice > 0)->mapWithKeys(fn ($item) => [$item->id => $item->qty_to_invoice]);
            $invoice = $this->invoices->create(['order_id' => $order->id, 'invoice' => ['items' => $items->all()]]);
            $this->transactions->create([
                'transaction_id' => $verified->receipt,
                'status' => 'paid',
                'type' => $verified->provider,
                'payment_method' => 'berkay_'.$verified->provider,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'amount' => $order->grand_total,
            ]);

            if (($verified->gateway_context['sandbox'] ?? null) === false) {
                app(OrderSmsListener::class)->paid($order->id);
            }
        });
    }

    /** Redirect or submit the authority to the selected provider's fixed endpoint. */
    protected function openBank($attempt): RedirectResponse|View
    {
        $gateway = $this->banks->gateway($attempt->provider);

        if ($gateway instanceof ZarinpalGateway) {
            return redirect()->away($gateway->redirectUrl($attempt->authority, $attempt->gateway_context))
                ->header('Referrer-Policy', 'no-referrer');
        }

        [$url, $field] = match ($attempt->provider) {
            'mellat' => ['https://bpm.shaparak.ir/pgwchannel/startpay.mellat', 'RefId'],
            'irankish' => ['https://ikc.shaparak.ir/iuiv3/IPG/Index/', 'tokenIdentity'],
        };

        return view('berkay::shop.bank-handoff', ['url' => $url, 'field' => $field, 'authority' => $attempt->authority]);
    }

    /** Log the payment phase without leaking provider payloads or credentials. */
    protected function logFailure(string $provider, string $operation, Throwable $exception): void
    {
        Log::warning('A Berkay payment operation failed.', ['provider' => $provider, 'operation' => $operation, 'exception_type' => $exception::class]);
    }
}
