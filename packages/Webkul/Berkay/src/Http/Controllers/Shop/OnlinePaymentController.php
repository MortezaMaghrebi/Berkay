<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use Webkul\Berkay\Listeners\OrderSmsListener;
use Webkul\Berkay\Repositories\PaymentAttemptRepository;
use Webkul\Berkay\Services\BankGateways;
use Webkul\Berkay\Services\IranianMoney;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Berkay\Services\TorobPayGateway;
use Webkul\Berkay\Services\ZarinpalGateway;
use Webkul\Berkay\Shipping\ShippingQuote;
use Webkul\Checkout\Contracts\Cart as CartContract;
use Webkul\Checkout\Facades\Cart;
use Webkul\Payment\Facades\Payment;
use Webkul\Sales\Contracts\Order;
use Webkul\Sales\Models\Invoice;
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

    /** Return a private reminder independently of shared storefront caches. */
    public function notice(): JsonResponse
    {
        $customerId = auth()->guard('customer')->id();
        $order = $customerId ? $this->orders->latestUnpaidForCustomer($customerId, core()->getCurrentChannel()->id) : null;

        return response()->json(['order' => $order ? [
            'id' => $order->id,
            'label' => trans('berkay::workflow.order', ['number' => $order->increment_id]),
            'url' => route('shop.customers.account.orders.view', $order->id),
        ] : null])->header('Cache-Control', 'private, no-store');
    }

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

                [$token, $context, $authority] = $this->authorizePayment($provider, $amount, $cart);
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

            return $this->paymentResult(false);
        }
    }

    /** Open payment for a payable order owned by the signed-in customer. */
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
        $methods = ['moneytransfer', ...array_map(fn ($name) => 'berkay_'.$name, array_keys(BankGateways::METHODS))];
        $requested = $request->validate(['method' => ['nullable', 'string', Rule::in($methods)]])['method'] ?? null;
        if ($workflow->waiting($order)) {
            return redirect()->route('shop.customers.account.orders.view', $order->id)
                ->with('info', trans('berkay::workflow.waiting'));
        }

        if ($workflow->ended($order) || $workflow->paid($order) || ! $order->canInvoice()) {
            return redirect()->route('shop.customers.account.orders.view', $order->id);
        }

        try {
            return Cache::lock('berkay.payment.order.'.$order->id, 60)->block(5, function () use ($order, $workflow, $requested) {
                $order = $order->fresh();
                if (
                    $workflow->waiting($order)
                    || $workflow->ended($order)
                    || $workflow->paid($order)
                    || ! $order->canInvoice()
                    || $order->invoices()->where('state', Invoice::STATUS_PAID)->exists()
                ) {
                    return redirect()->route('shop.customers.account.orders.view', $order->id);
                }

                $attempt = $this->attempts->findOneWhere(['order_id' => $order->id]);
                $method = $order->payment->method;
                $provider = $attempt?->provider ?? Str::after($method, 'berkay_');

                if (
                    filled($requested)
                    && $requested !== $method
                    && $attempt
                ) {
                    return redirect()->route('shop.customers.account.orders.view', $order->id)
                        ->with('error', 'روش پرداخت این سفارش قبلاً ثبت شده است. از همان روش ادامه دهید.');
                }

                if ($attempt?->status === 'verified') {
                    $this->finalizePayment($attempt->id);

                    return $this->paymentResult(true, $order);
                }

                if ($attempt && $attempt->status !== 'pending') {
                    return redirect()->route('shop.customers.account.orders.view', $order->id);
                }

                if ($attempt?->provider === 'torobpay' && $this->recoverTorobPayment($attempt, $workflow)) {
                    return $this->paymentResult(true, $order);
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
                    [$token, $context, $authority] = $this->authorizePayment($provider, $amount, $order);
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

            return $this->paymentResult(false, $order);
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
            'torobpay' => ['transactionId', 'state', 'OK'],
            default => ['Authority', 'Status', 'OK'],
        };
        $authority = request()->input($authorityKey);
        $order = $this->orders->findOrFail($attempt->order_id);
        $expected = $provider === 'torobpay' ? ($attempt->gateway_context['transaction_id'] ?? '') : $attempt->authority;

        if (
            ! is_string($authority)
            || $expected === ''
            || ! hash_equals($expected, $authority)
            || $workflow->waiting($order)
        ) {
            return $this->failed();
        }

        try {
            $attempt = Cache::lock('berkay.payment.attempt.'.$attempt->id, 90)->block(5, function () use ($attempt, $provider, $statusKey, $success, $workflow) {
                $current = $this->attempts->findOrFail($attempt->id);

                if ($current->status === 'pending') {
                    $order = $this->orders->findOrFail($current->order_id);
                    if (
                        request()->input($statusKey) !== $success
                        || ! $order->canInvoice()
                        || ($provider === 'torobpay' && (
                            ! $this->banks->gateway($provider)->matchesAmount(request()->input('amount'), $current->amount_rial)
                            || $workflow->ended($order)
                            || $order->payment?->method !== 'berkay_torobpay'
                            || $this->money->toRial($order->base_grand_total, $order->base_currency_code) !== $current->amount_rial
                        ))
                    ) {
                        return $current;
                    }

                    $gateway = $this->banks->gateway($provider);
                    $receipt = $gateway instanceof TorobPayGateway
                        ? $gateway->verify($current->authority, $current->amount_rial, $current->gateway_context)
                        : $gateway->verify($current->authority, $current->amount_rial, $current->gateway_context, request()->only([
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

                return redirect()->route('shop.checkout.onepage.success')->with('berkay.payment.confirmed', true);
            }
            if ($attempt->status === 'paid') {
                return $this->paymentResult(true, $order);
            }
        } catch (Throwable $exception) {
            $this->logFailure($provider, 'callback', $exception);

            return $this->failed($token, $order);
        }

        return $this->failed($token, $order);
    }

    /** Resume the same unpaid authority for the browser that initiated payment. */
    public function resume(string $token, OrderWorkflow $workflow): RedirectResponse|View
    {
        abort_unless(hash_equals((string) session('berkay.payment.token'), $token), 403);
        $attempt = $this->attempts->findOneByField('token', $token);
        abort_unless($attempt, 404);
        if ($attempt->provider === 'torobpay') {
            try {
                if ($this->recoverTorobPayment($attempt, $workflow)) {
                    session()->flash('order_id', $attempt->order_id);

                    return redirect()->route('shop.checkout.onepage.success')->with('berkay.payment.confirmed', true);
                }
            } catch (Throwable $exception) {
                $this->logFailure('torobpay', 'resume', $exception);

                return $this->failed($token);
            }
        }
        abort_unless($attempt->status === 'pending', 404);
        abort_unless($this->orders->findOrFail($attempt->order_id)->canInvoice(), 409);

        return $this->openBank($attempt);
    }

    /** Return a generic failure without disclosing customer or provider data. */
    protected function failed(?string $token = null, ?Order $order = null): View
    {
        if ($token && hash_equals((string) session('berkay.payment.token'), $token)) {
            $attempt = $this->attempts->findOneWhere(['token' => $token]);

            return $this->paymentResult(
                false,
                $attempt ? $this->orders->find($attempt->order_id) : null,
                $token,
                $attempt?->status === 'pending' || ($attempt?->provider === 'torobpay' && $attempt->status === 'verified'),
            );
        }

        return $this->paymentResult(false, $order);
    }

    /** Only expose links to orders that belong to the current customer. */
    protected function paymentResult(bool $paid, ?Order $order = null, ?string $token = null, bool $canRetry = false): View
    {
        $orderUrl = $order && auth()->guard('customer')->check()
            && (int) $order->customer_id === (int) auth()->guard('customer')->id()
            && (int) $order->channel_id === (int) core()->getCurrentChannel()->id
            ? route('shop.customers.account.orders.view', $order->id)
            : null;

        return view('berkay::shop.payment-result', compact('paid', 'orderUrl', 'token', 'canRetry'));
    }

    /** Create the gateway authority and freeze the merchant context for verification. */
    protected function authorizePayment(string $provider, int $amount, CartContract|Order $source): array
    {
        $token = Str::random(64);
        $gateway = $this->banks->gateway($provider);
        $context = $gateway->context();
        $callback = $provider === 'zarinpal'
            ? route('shop.berkay.zarinpal.callback', $token)
            : route('shop.berkay.bank.callback', [$token, $provider]);

        if ($gateway instanceof TorobPayGateway) {
            [$authority, $context['redirect_url']] = $gateway->purchase($amount, $callback, $context, $source);

            return [$token, $context, $authority];
        }

        return [$token, $context, $gateway->purchase($amount, $callback, $context)];
    }

    /** Reconcile a settled or verified TorobPay token when the customer resumes. */
    protected function recoverTorobPayment($attempt, OrderWorkflow $workflow): bool
    {
        return Cache::lock('berkay.payment.attempt.'.$attempt->id, 90)->block(5, function () use ($attempt, $workflow) {
            $current = $this->attempts->findOrFail($attempt->id);
            $order = $this->orders->findOrFail($current->order_id);

            if ($current->status === 'paid') {
                return true;
            }

            if (
                $workflow->waiting($order)
                || $workflow->ended($order)
                || ! $order->canInvoice()
                || $order->payment?->method !== 'berkay_torobpay'
                || $this->money->toRial($order->base_grand_total, $order->base_currency_code) !== $current->amount_rial
            ) {
                throw new \RuntimeException('The TorobPay order requires reconciliation.');
            }

            if ($current->status === 'pending') {
                $receipt = $this->banks->gateway('torobpay')->verify($current->authority, $current->amount_rial, $current->gateway_context, recoverOnly: true);
                if ($receipt === null) {
                    return false;
                }
                $this->attempts->recordVerification($current->id, $receipt);
            }

            return $this->finalizePayment($current->id)->status === 'paid';
        });
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

        if ($gateway instanceof TorobPayGateway) {
            return redirect()->away($gateway->redirectUrl($attempt->gateway_context['redirect_url'] ?? null))
                ->header('Referrer-Policy', 'no-referrer');
        }

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
