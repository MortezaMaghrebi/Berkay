<?php

use Illuminate\Support\Facades\Http;
use Webkul\Berkay\Repositories\PaymentAttemptRepository;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\OrderTransaction;

beforeEach(function () {
    config(['berkay.zarinpal.merchant_id' => 'test-merchant', 'berkay.zarinpal.sandbox' => true]);
    $this->setConfig([
        'sales.payment_methods.berkay_zarinpal.active' => 1,
        'sales.payment_methods.berkay_zarinpal.title' => 'Zarinpal',
        'sales.checkout.shopping_cart.allow_guest_checkout' => 1,
    ]);
    Http::preventStrayRequests();
});

it('places and invoices a bank-confirmed order exactly once across repeated callbacks', function () {
    $authority = str_repeat('A', 36);
    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]]),
        'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 99887766]]),
    ]);

    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null], 'guest_checkout' => ['boolean_value' => 1]]);
    $this->prepareCartForCheckout($product, [], 'berkay_post_berkay_post', 'berkay_zarinpal');
    $this->get(route('shop.berkay.zarinpal.start'))->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);

    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);
    expect($attempt)->not->toBeNull()->and($attempt->status)->toBe('pending');

    config(['berkay.zarinpal.merchant_id' => 'rotated-merchant', 'berkay.zarinpal.sandbox' => false]);

    $callback = route('shop.berkay.zarinpal.callback', $attempt->token).'?Authority='.$authority.'&Status=OK&amount=1';
    $this->get($callback)->assertRedirect(route('shop.checkout.onepage.success'));
    $this->get($callback)->assertRedirect(route('shop.checkout.onepage.success'));

    expect($attempt->fresh()->status)->toBe('paid')
        ->and(Invoice::query()->where('order_id', $attempt->order_id)->count())->toBe(1)
        ->and(OrderTransaction::query()->where('order_id', $attempt->order_id)->count())->toBe(1);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/verify.json') && $request['amount'] === $attempt->amount_rial && $request['merchant_id'] === 'test-merchant');
    Http::assertSentCount(2);
});

it('rejects a callback authority from another transaction before contacting the bank', function () {
    $authority = str_repeat('B', 36);
    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]]),
    ]);

    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null], 'guest_checkout' => ['boolean_value' => 1]]);
    $this->prepareCartForCheckout($product, [], 'berkay_post_berkay_post', 'berkay_zarinpal');
    $this->get(route('shop.berkay.zarinpal.start'))->assertRedirect();

    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);
    expect($attempt)->not->toBeNull();
    $this->get(route('shop.berkay.zarinpal.callback', $attempt->token).'?Authority=another-transaction&Status=OK')
        ->assertRedirect(route('shop.checkout.cart.index'));

    expect($attempt->fresh()->status)->toBe('pending')
        ->and(Invoice::query()->where('order_id', $attempt->order_id)->count())->toBe(0);
    Http::assertSentCount(1);
});

it('lets the original browser resume a cancelled payment without creating another charge', function () {
    $authority = str_repeat('C', 36);
    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]]),
    ]);
    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null], 'guest_checkout' => ['boolean_value' => 1]]);
    $this->prepareCartForCheckout($product, [], 'berkay_post_berkay_post', 'berkay_zarinpal');
    $this->get(route('shop.berkay.zarinpal.start'))->assertRedirect();
    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);

    $this->get(route('shop.berkay.zarinpal.callback', $attempt->token).'?Authority='.$authority.'&Status=NOK')
        ->assertOk()->assertViewIs('berkay::shop.payment-result')->assertViewHas('canRetry', true);
    $this->get(route('shop.berkay.zarinpal.resume', $attempt->token))
        ->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);
    $this->withSession(['berkay.payment.token' => 'another-session'])
        ->get(route('shop.berkay.zarinpal.resume', $attempt->token))->assertForbidden();

    expect($attempt->fresh()->status)->toBe('pending')
        ->and(Invoice::query()->where('order_id', $attempt->order_id)->count())->toBe(0);
    Http::assertSentCount(1);
});

it('holds a workshop order before starting bank payment and releases it for its customer', function () {
    $authority = str_repeat('D', 36);
    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]]),
        'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 99887767]]),
    ]);
    $this->setConfig([
        'berkay.workshop.review.auto_approve' => 1,
        'berkay.workshop.review.amount_limit' => 100000,
    ]);
    $customer = $this->loginAsCustomer(\Webkul\Customer\Models\Customer::factory()->create([
        'channel_id' => core()->getCurrentChannel()->id,
        'phone' => '09120000000',
    ]));
    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null]]);
    $cartId = $this->prepareCartForCheckout($product, ['email' => null], 'berkay_post_berkay_post', 'berkay_zarinpal');

    $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();
    $order = Order::query()->where('cart_id', $cartId)->firstOrFail();
    $workflow = app(OrderWorkflow::class);
    expect($workflow->waiting($order))->toBeTrue()
        ->and($order->canInvoice())->toBeFalse();
    Http::assertNothingSent();

    $this->post(route('shop.berkay.zarinpal.approved', $order->id))
        ->assertRedirect(route('shop.customers.account.orders.view', $order->id));
    Http::assertNothingSent();
    $workflow->advance($order, 'approve');
    $this->post(route('shop.berkay.zarinpal.approved', $order->id))->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);

    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);
    $this->get(route('shop.berkay.zarinpal.callback', $attempt->token).'?Authority='.$authority.'&Status=OK')
        ->assertRedirect(route('shop.checkout.onepage.success'));
    expect($attempt->fresh()->status)->toBe('paid')
        ->and($workflow->paid($order->fresh()))->toBeTrue()
        ->and((int) $order->customer_id)->toBe($customer->id);
    Http::assertSentCount(2);
});
