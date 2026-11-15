<?php

use Illuminate\Support\Facades\Http;
use Webkul\Berkay\Repositories\PaymentAttemptRepository;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Checkout\Models\Cart;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Customer\Models\Customer;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
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

it('preserves a high-value cart and holds its payment until management approves', function (bool $directStart) {
    $authority = str_repeat('D', 36);
    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]]),
        'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 99887767]]),
    ]);
    $this->setConfig('berkay.workshop.review.amount_limit', 100_000_000);
    $customer = $this->loginAsCustomer(Customer::factory()->create([
        'channel_id' => core()->getCurrentChannel()->id,
        'phone' => '09120000000',
    ]));
    $product = $this->createSimpleProduct(['price' => ['float_value' => 30_000_000], 'special_price' => ['float_value' => null]]);
    $cartId = $this->prepareCartForCheckout($product, ['email' => null], 'berkay_post_berkay_post', 'berkay_zarinpal', 4);
    $cart = Cart::findOrFail($cartId);
    expect((float) $cart->items->first()->base_total)->toBe(120_000_000.0)
        ->and((float) $cart->base_sub_total)->toBe(120_000_000.0);

    $this->getJson(route('shop.api.checkout.cart.index'))
        ->assertOk()->assertJsonPath('data.requires_review', true);

    if ($directStart) {
        $this->get(route('shop.berkay.zarinpal.start'))->assertRedirect();
    } else {
        $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();
    }

    $order = Order::query()->where('cart_id', $cartId)->firstOrFail();
    $workflow = app(OrderWorkflow::class);
    expect($workflow->waiting($order))->toBeTrue()
        ->and((float) $order->base_sub_total)->toBe(120_000_000.0)
        ->and((float) $order->items->first()->base_total)->toBe(120_000_000.0)
        ->and($order->canInvoice())->toBeFalse();
    $this->get(route('shop.customers.account.orders.view', $order->id))
        ->assertDontSee(route('shop.berkay.zarinpal.approved', $order->id));
    Http::assertNothingSent();

    $this->post(route('shop.berkay.zarinpal.approved', $order->id))
        ->assertRedirect(route('shop.customers.account.orders.view', $order->id));
    Http::assertNothingSent();
    $workflow->advance($order, 'approve');
    $this->get(route('shop.customers.account.orders.view', $order->id))
        ->assertSee(route('shop.berkay.zarinpal.approved', $order->id));
    $this->post(route('shop.berkay.zarinpal.approved', $order->id))->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);

    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);
    $this->get(route('shop.berkay.zarinpal.callback', $attempt->token).'?Authority='.$authority.'&Status=OK')
        ->assertRedirect(route('shop.checkout.onepage.success'));
    expect($attempt->fresh()->status)->toBe('paid')
        ->and($attempt->amount_rial)->toBe((int) round((float) $order->base_grand_total * 10))
        ->and((float) $order->invoices()->firstOrFail()->base_sub_total)->toBe(120_000_000.0)
        ->and($workflow->paid($order->fresh()))->toBeTrue()
        ->and((int) $order->customer_id)->toBe($customer->id);
    Http::assertSentCount(2);
})->with(['checkout API' => [false], 'direct bank start' => [true]]);

it('compares the combined merchandise subtotal with the configurable toman limit', function () {
    $cart = new Cart([
        'base_currency_code' => 'IRT',
        'base_sub_total' => 100_000_000,
        'base_grand_total' => 105_000_000,
    ]);
    $cart->setRelation('items', collect());
    $workflow = app(OrderWorkflow::class);

    $this->setConfig('berkay.workshop.review.amount_limit', 0);
    expect($workflow->requiresReview($cart))->toBeFalse();

    $cart->base_sub_total = 100_000_001;
    $cart->base_grand_total = 90_000_000;
    expect($workflow->requiresReview($cart))->toBeTrue();

    app(CoreConfigRepository::class)->create([
        'channel' => core()->getCurrentChannelCode(),
        'berkay' => ['workshop' => ['review' => ['amount_limit' => 150_000_000]]],
    ]);
    expect($workflow->requiresReview($cart))->toBeFalse();

    $cart->base_currency_code = 'IRR';
    $cart->base_sub_total = 1_500_000_001;
    expect($workflow->requiresReview($cart))->toBeTrue();
});

it('defers automatic invoicing for a high-value bank transfer order', function () {
    $this->setConfig([
        'berkay.workshop.review.amount_limit' => 100_000_000,
        'sales.payment_methods.moneytransfer.generate_invoice' => 1,
        'sales.payment_methods.moneytransfer.invoice_status' => Invoice::STATUS_PAID,
        'sales.payment_methods.moneytransfer.mailing_address' => 'Test transfer instructions',
    ]);
    $this->loginAsCustomer();
    $product = $this->createSimpleProduct(['price' => ['float_value' => 60_000_000], 'special_price' => ['float_value' => null]]);
    $cartId = $this->prepareCartForCheckout($product, paymentMethod: 'moneytransfer');
    $anotherProduct = $this->createSimpleProduct(['price' => ['float_value' => 45_000_000], 'special_price' => ['float_value' => null]]);
    $this->addProductByType($anotherProduct)->assertOk();
    $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'berkay_post_berkay_post'])->assertOk();

    $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();

    $order = Order::query()->where('cart_id', $cartId)->firstOrFail();
    expect((float) $order->base_sub_total)->toBe(105_000_000.0)
        ->and(app(OrderWorkflow::class)->waiting($order))->toBeTrue()
        ->and($order->invoices()->exists())->toBeFalse()
        ->and($order->canInvoice())->toBeFalse();
    $this->get(route('shop.customers.account.orders.view', $order->id))->assertDontSee('Test transfer instructions');

    app(OrderWorkflow::class)->advance($order, 'approve');
    expect($order->fresh()->canInvoice())->toBeTrue();
    $this->get(route('shop.customers.account.orders.view', $order->id))->assertSee('Test transfer instructions');
    Http::assertNothingSent();
});
