<?php

use Illuminate\Support\Facades\Http;
use Webkul\Berkay\Repositories\PaymentAttemptRepository;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\MellatGateway;
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
    $this->get(route('shop.customers.account.orders.view', $order->id))
        ->assertSeeText('نمایش نحوهٔ واریز')->assertSeeText('Test transfer instructions');
    Http::assertNothingSent();
});

it('starts real-mode Zarinpal payments at the documented API and handoff hosts', function () {
    config(['berkay.zarinpal.sandbox' => false]);
    $authority = str_repeat('Z', 36);
    Http::fake(['api.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]])]);

    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null]]);
    $this->prepareCartForCheckout($product, paymentMethod: 'berkay_zarinpal');
    $this->get(route('shop.berkay.zarinpal.start'))
        ->assertRedirect('https://www.zarinpal.com/pg/StartPay/'.$authority);

    Http::assertSentCount(1);
});

it('confirms an Iran Kish payment only for the stored order reference and amount', function () {
    $publicKey = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsttX0+RiBv2zrvFBgOXc
        3fUBGuH4UrwCPu/9quJQzKHn+1XXy8tJ+jKgegCcxDzJv3tM45bIlHAOvZ8OcpyX
        Y3Q98dK8ME7W/p2T4ZSjDFCO5Zb2d46iBdkkh0Ngafy4zotEDLt64BrrPJBLqTIr
        +uTkuvTXT7gLIzeWO4fqbrgwmIEm71jB5C08gOhdaBdkPlY39WWWTFbbGJ1dXPdi
        iWa1MO6Vb42VSwIbYc/DNd9G/qXacGWxYcWAdYsx5YGOskNtzuFh0txgr00dFoF0
        EvAHKKJDHXXRByDl8llGUz0Mec5NzhWuTDIpKDQvj6j5pHXJGluqMSrJl/9efasU
        iwIDAQAB
        -----END PUBLIC KEY-----
        PEM;
    $settings = app(IntegrationSettings::class);
    foreach (['enabled' => true, 'terminal_id' => '01000001', 'acceptor_id' => '123450000000001', 'password' => 'A123456789ABCDEF', 'public_key' => $publicKey] as $name => $value) {
        $settings->put('irankish.'.$name, $value);
    }

    $authority = str_repeat('A', 36);
    $receipt = '123456789012';
    Http::fake([
        'ikc.shaparak.ir/api/v3/tokenization/make' => Http::response(['status' => true, 'responseCode' => '00', 'result' => ['token' => $authority]]),
        'ikc.shaparak.ir/api/v3/confirmation/purchase' => fn ($request) => Http::response([
            'status' => true, 'responseCode' => '00',
            'result' => ['responseCode' => '00', 'amount' => $request['amount'], 'retrievalReferenceNumber' => $receipt],
        ]),
    ]);

    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null]]);
    $this->prepareCartForCheckout($product, paymentMethod: 'berkay_irankish');
    $this->get(route('shop.berkay.bank.start', 'irankish'))->assertOk()->assertViewIs('berkay::shop.bank-handoff')
        ->assertViewHas('field', 'tokenIdentity');

    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);
    $callback = route('shop.berkay.bank.callback', [$attempt->token, 'irankish']);
    $response = [
        'token' => $authority, 'responseCode' => '00', 'requestId' => $attempt->gateway_context['reference'],
        'acceptorId' => '123450000000001', 'retrievalReferenceNumber' => $receipt, 'systemTraceAuditNumber' => '012345',
        'amount' => (string) $attempt->amount_rial,
    ];

    $this->post($callback, array_replace($response, ['amount' => '1000']))->assertViewIs('berkay::shop.payment-result');
    Http::assertSentCount(1);
    $this->post($callback, $response)->assertRedirect(route('shop.checkout.onepage.success'));
    $this->post($callback, $response)->assertRedirect(route('shop.checkout.onepage.success'));

    Http::assertSentCount(2);
    expect($attempt->fresh()->status)->toBe('paid')->and($attempt->fresh()->receipt)->toBe('irankish:'.$receipt);
    expect(Invoice::query()->where('order_id', $attempt->order_id)->count())->toBe(1);
});

it('settles a BehPardakht payment after matching the callback with the authorized order', function () {
    $settings = app(IntegrationSettings::class);
    $settings->put('mellat.enabled', true);
    $gateway = Mockery::mock(MellatGateway::class, [$settings])->makePartial()->shouldAllowMockingProtectedMethods();
    $authority = 'M123456789000011';
    $gateway->shouldReceive('ready')->andReturn(true);
    $gateway->shouldReceive('call')->once()->with('bpPayRequest', Mockery::on(fn ($params) => $params['amount'] > 0))->andReturn('0,'.$authority);
    $gateway->shouldReceive('call')->once()->with('bpVerifyRequest', Mockery::on(fn ($params) => $params['saleReferenceId'] === 123456789))->andReturn('0');
    $gateway->shouldReceive('call')->once()->with('bpSettleRequest', Mockery::on(fn ($params) => $params['saleReferenceId'] === 123456789))->andReturn('0');
    $this->app->instance(MellatGateway::class, $gateway);

    $this->setConfig('berkay.workshop.review.amount_limit', 100_000);
    $this->loginAsCustomer();
    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null]]);
    $cartId = $this->prepareCartForCheckout($product, paymentMethod: 'berkay_mellat');
    $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();
    $order = Order::query()->where('cart_id', $cartId)->firstOrFail();
    $pay = route('shop.berkay.zarinpal.approved', $order->id);
    $this->post($pay)->assertRedirect(route('shop.customers.account.orders.view', $order->id));
    app(OrderWorkflow::class)->advance($order, 'approve');
    $this->post($pay)->assertOk()->assertViewIs('berkay::shop.bank-handoff')
        ->assertViewHas('field', 'RefId');

    $attempt = app(PaymentAttemptRepository::class)->findOneByField('authority', $authority);
    $callback = route('shop.berkay.bank.callback', [$attempt->token, 'mellat']);
    $response = [
        'RefId' => $authority, 'ResCode' => '0',
        'SaleOrderId' => (string) $attempt->gateway_context['reference'], 'SaleReferenceId' => '123456789',
    ];

    $this->post($callback, array_replace($response, ['SaleOrderId' => '123']))->assertViewIs('berkay::shop.payment-result');
    $this->post($callback, $response)->assertRedirect(route('shop.checkout.onepage.success'));
    $this->post($callback, $response)->assertRedirect(route('shop.checkout.onepage.success'));

    expect($attempt->fresh()->status)->toBe('paid')->and($attempt->fresh()->receipt)->toBe('mellat:123456789');
    expect(OrderTransaction::query()->where('order_id', $attempt->order_id)->count())->toBe(1);
});

it('recovers an approved order whose bank payment was verified before invoicing', function () {
    $authority = str_repeat('Q', 36);
    Http::fake(['sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]])]);
    $this->setConfig('berkay.workshop.review.amount_limit', 100_000);
    $this->loginAsCustomer();
    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null]]);
    $cartId = $this->prepareCartForCheckout($product, paymentMethod: 'berkay_zarinpal');
    $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();
    $order = Order::query()->where('cart_id', $cartId)->firstOrFail();
    app(OrderWorkflow::class)->advance($order, 'approve');

    $pay = route('shop.berkay.zarinpal.approved', $order->id);
    $this->post($pay)->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);
    $attempts = app(PaymentAttemptRepository::class);
    $attempt = $attempts->findOneByField('authority', $authority);
    $attempts->recordVerification($attempt->id, '91235678');

    $this->post($pay)->assertRedirect(route('shop.customers.account.orders.view', $order->id))
        ->assertSessionHas('success', trans('berkay::app.payment-completed'));
    Http::assertSentCount(1);
    expect($attempt->fresh()->status)->toBe('paid')->and($order->invoices()->count())->toBe(1);
});

it('lets an approved order choose an available gateway and preserves an existing bank attempt', function () {
    $authority = str_repeat('R', 36);
    Http::fake(['sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => $authority]])]);
    $this->setConfig('berkay.workshop.review.amount_limit', 100_000);
    $this->loginAsCustomer();
    $product = $this->createSimpleProduct(['price' => ['float_value' => 250000], 'special_price' => ['float_value' => null]]);
    $cartId = $this->prepareCartForCheckout($product, paymentMethod: 'berkay_zarinpal');
    $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();
    $order = Order::query()->where('cart_id', $cartId)->firstOrFail();
    $order->payment()->update(['method' => 'berkay_mellat']);

    $action = route('shop.berkay.zarinpal.approved', $order->id);
    $view = route('shop.customers.account.orders.view', $order->id);
    $this->post($action, ['method' => 'berkay_zarinpal'])->assertRedirect($view);
    Http::assertNothingSent();

    app(OrderWorkflow::class)->advance($order, 'approve');
    $this->get($view)->assertOk()->assertSee('value="berkay_zarinpal"', false)
        ->assertSee('berkay/payment-logos/zarinpal.svg')->assertDontSee('value="berkay_mellat"', false);
    $this->post($action, ['method' => 'berkay_zarinpal'])->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);
    $this->post($action, ['method' => 'moneytransfer'])->assertRedirect($view)->assertSessionHas('error');

    expect($order->fresh()->payment->method)->toBe('berkay_zarinpal');
    Http::assertSentCount(1);
});
