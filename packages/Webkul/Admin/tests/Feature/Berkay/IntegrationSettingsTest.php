<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Berkay\Listeners\OrderSmsListener;
use Webkul\Berkay\Models\IntegrationSetting;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Berkay\Services\SmsTemplates;
use Webkul\Customer\Models\Customer;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->integrationData = [
        'zarinpal_active' => 0,
        'zarinpal_sandbox' => 1,
        'zarinpal_merchant_id' => '00000000-0000-4000-8000-000000000001',
        'torob_enabled' => 0,
    ];
    $this->smsSettings = ['enabled' => 0, 'api_key' => 'TESTCREDENTIAL123456789'];
});

it('stores bank terminal settings encrypted and keeps blank credentials on later saves', function () {
    $this->loginAsAdmin();
    $settings = app(IntegrationSettings::class);
    $url = route('admin.berkay.integrations.payment.update', 'mellat');
    $secret = 'temporary-mellat-password';

    $this->put($url, ['enabled' => 1, 'terminal_id' => '12345678', 'username' => 'terminal-user', 'password' => $secret])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->put($url, ['enabled' => 0, 'terminal_id' => '', 'username' => '', 'password' => ''])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($settings->get('mellat.password'))->toBe($secret)
        ->and($settings->get('mellat.enabled'))->toBeFalse();
    expect(DB::table('berkay_integration_settings')->where('name', 'mellat.password')->value('value'))->not->toContain($secret);

    $this->get(route('admin.berkay.integrations.index'))->assertOk()->assertDontSee($secret)
        ->assertSee('بانک ملت')->assertSee('بانک تجارت');
});

it('lets staff configure the same transfer instructions shown in customer orders', function () {
    $this->loginAsAdmin();
    $this->put(route('admin.berkay.integrations.payment.update', 'moneytransfer'), ['instructions' => 'Example branch transfer instructions'])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect(core()->getConfigData('sales.payment_methods.moneytransfer.mailing_address'))->toBe('Example branch transfer instructions');
});

it('encrypts administrator credentials and never includes them in the rendered settings', function () {
    $this->loginAsAdmin();
    $this->put(route('admin.berkay.integrations.update'), $this->integrationData)->assertRedirect();
    $this->put(route('admin.berkay.kavenegar.update'), $this->smsSettings)->assertSessionHasNoErrors()->assertRedirect();

    $raw = DB::table('berkay_integration_settings')->where('name', 'kavenegar.api_key')->value('value');
    expect($raw)->not->toContain('TESTCREDENTIAL123456789');
    expect(app(IntegrationSettings::class)->get('kavenegar.api_key'))->toBe('TESTCREDENTIAL123456789');
    expect(IntegrationSetting::query()->where('name', 'kavenegar.api_key')->first()->toArray())->not->toHaveKey('value');
    $this->get(route('admin.berkay.integrations.index'))->assertOk()
        ->assertDontSee('TESTCREDENTIAL123456789')->assertDontSee($this->integrationData['zarinpal_merchant_id']);
    Http::assertNothingSent();
});

it('preserves blank keys and uses the stored provider configuration', function () {
    $this->loginAsAdmin();
    $this->put(route('admin.berkay.integrations.update'), $this->integrationData)->assertRedirect();
    $this->put(route('admin.berkay.integrations.update'), array_replace($this->integrationData, ['zarinpal_merchant_id' => '']))->assertRedirect();
    $this->put(route('admin.berkay.kavenegar.update'), $this->smsSettings)->assertSessionHasNoErrors()->assertRedirect();
    $this->put(route('admin.berkay.kavenegar.update'), ['enabled' => 0, 'api_key' => ''])->assertSessionHasNoErrors()->assertRedirect();
    expect(app(IntegrationSettings::class)->get('kavenegar.api_key'))->toBe('TESTCREDENTIAL123456789');

    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);
    app(KavenegarSms::class)->lookup('09123456789', 'orderCreated', 'BK123');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/TESTCREDENTIAL123456789/'));
});

it('does not flash credential values when validation fails', function () {
    $this->loginAsAdmin();
    $this->from(route('admin.berkay.integrations.index'))
        ->put(route('admin.berkay.integrations.update'), array_merge($this->integrationData, ['torob_enabled' => 'invalid']))
        ->assertSessionHasErrors('torob_enabled');
    expect(session()->getOldInput())->not->toHaveKey('zarinpal_merchant_id');
    $this->from(route('admin.berkay.kavenegar.index'))
        ->put(route('admin.berkay.kavenegar.update'), array_replace($this->smsSettings, ['enabled' => 'invalid']))
        ->assertSessionHasErrors('enabled');
    expect(session()->getOldInput())->not->toHaveKey('api_key');
    expect(IntegrationSetting::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('denies provider changes to staff with only read access', function () {
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.integrations']);
    $this->get(route('admin.berkay.integrations.index'))->assertOk();
    $this->put(route('admin.berkay.integrations.update'), $this->integrationData)->assertUnauthorized();
    expect(IntegrationSetting::query()->count())->toBe(0);
});

it('keeps a saved pattern while disabling its order notification', function () {
    $this->loginAsAdmin();
    $settings = app(IntegrationSettings::class);
    $settings->put('kavenegar.api_key', 'TESTCREDENTIAL123456789');
    $url = route('admin.berkay.kavenegar.update');
    $payload = ['enabled' => 1, 'templates' => ['login' => 'otp', 'shipped' => 'order-shipped']];
    $templates = app(SmsTemplates::class);

    $this->put($url, $payload + ['notifications' => ['shipped' => 0]])->assertSessionHasNoErrors()->assertRedirect();
    expect($templates->template('shipped'))->toBeNull()
        ->and($settings->get('kavenegar.shipped_template'))->toBe('order-shipped')
        ->and($templates->template('login'))->toBe('otp');

    $this->get(route('admin.berkay.kavenegar.index'))->assertOk()->assertSee('%token2')->assertDontSee('TESTCREDENTIAL123456789');
    $this->put($url, $payload + ['notifications' => ['shipped' => 1]])->assertSessionHasNoErrors();
    expect($templates->template('shipped'))->toBe('order-shipped');
});

it('stores a normalized manager recipient and enables each alert independently', function () {
    $this->loginAsAdmin();
    $settings = app(IntegrationSettings::class);
    $settings->put('kavenegar.api_key', 'TESTCREDENTIAL123456789');
    $payload = [
        'enabled' => 1,
        'admin_recipient' => '+۹۸۹۱۲۳۴۵۶۷۸۹',
        'templates' => ['login' => 'otp', 'admin_new_order' => 'new-order-admin', 'admin_order_paid' => 'order-paid-admin'],
        'notifications' => ['admin_new_order' => 1, 'admin_order_paid' => 0],
    ];

    $this->put(route('admin.berkay.kavenegar.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $templates = app(SmsTemplates::class);
    expect($templates->adminRecipient())->toBe('09123456789')
        ->and($templates->template('admin_new_order'))->toBe('new-order-admin')
        ->and($templates->template('admin_order_paid'))->toBeNull();

    $this->get(route('admin.berkay.kavenegar.index'))->assertOk()->assertSee('09123456789');
    Http::assertNothingSent();
});

it('requires a manager number and pattern before enabling an alert', function () {
    $this->loginAsAdmin();
    $url = route('admin.berkay.kavenegar.update');
    $payload = ['enabled' => 0, 'admin_recipient' => '123', 'notifications' => ['admin_new_order' => 1]];

    $this->put($url, $payload)->assertSessionHasErrors('admin_recipient');
    $this->put($url, array_replace($payload, ['admin_recipient' => '09123456789']))
        ->assertSessionHasErrors('admin_recipient');
    expect(app(SmsTemplates::class)->eventEnabled('admin_new_order'))->toBeFalse();
});

it('saves order SMS settings with an empty key and preserves omitted patterns', function () {
    $this->loginAsAdmin();
    $settings = app(IntegrationSettings::class);
    $settings->put('kavenegar.api_key', 'TESTCREDENTIAL123456789');
    $settings->put('kavenegar.login_template', 'login-pattern');

    $this->put(route('admin.berkay.kavenegar.update'), [
        'enabled' => 1,
        'api_key' => '',
        'templates' => ['order' => 'new-order-user'],
        'notifications' => ['order' => 1],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($settings->get('kavenegar.api_key'))->toBe('TESTCREDENTIAL123456789')
        ->and($settings->get('kavenegar.login_template'))->toBe('login-pattern')
        ->and(app(SmsTemplates::class)->template('order'))->toBe('new-order-user');
});

it('sends a customer order confirmation after the transaction commits', function () {
    $this->withoutDefer();
    config(['berkay.kavenegar.paused' => false, 'queue.default' => 'sync']);
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);

    $settings = app(IntegrationSettings::class);
    $settings->put('kavenegar.enabled', true);
    $settings->put('kavenegar.api_key', 'TESTCREDENTIAL123456789');
    $settings->put('kavenegar.order_template', 'orderCreated');

    $customer = Customer::factory()->create(['phone' => '09123456789']);
    $order = DB::transaction(function () use ($customer) {
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        app(OrderSmsListener::class)->created($order);
        Http::assertNothingSent();

        return $order;
    });

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['receptor'] === '09123456789'
        && $request['template'] === 'orderCreated'
        && $request['token'] === (string) $order->increment_id);
});

it('notifies the customer of approval, invoicing, production and shipment without a queue worker', function () {
    $this->loginAsAdmin();
    $this->withoutDefer();
    config(['berkay.kavenegar.paused' => false, 'queue.default' => 'database']);
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);

    $settings = app(IntegrationSettings::class);
    foreach ([
        'enabled' => true,
        'api_key' => 'TESTCREDENTIAL123456789',
        'stage_approved_template' => 'order-approved',
        'confirmed_template' => 'invoice-issued',
        'shipped_template' => 'shipped-with-tracking',
        'status_template' => 'order-status',
        'completed_enabled' => false,
    ] as $key => $value) {
        $settings->put('kavenegar.'.$key, $value);
    }

    $customer = Customer::factory()->create(['phone' => '09123456789']);
    $order = $this->createOrder(items: [['product' => $this->createSimpleProduct()]], customer: $customer);
    DB::table('berkay_order_workflows')->insert(['order_id' => $order->id, 'review' => 'waiting']);

    $this->post(route('admin.berkay.workshop.advance', [$order->id, 'approve']))->assertSessionHas('success');
    $this->invoiceOrder($order, state: Invoice::STATUS_PAID);
    $this->post(route('admin.berkay.workshop.advance', [$order->id, 'building']))->assertSessionHas('success');
    $this->post(route('admin.berkay.workshop.advance', [$order->id, 'building']))->assertSessionHas('success');
    $shipment = $this->shipOrder($order);

    $requests = Http::recorded()->map(fn ($pair) => $pair[0]);

    expect($requests->map(fn ($request) => $request['template'])->all())
        ->toBe(['order-approved', 'invoice-issued', 'order-status', 'shipped-with-tracking'])
        ->and($requests->map(fn ($request) => $request['receptor'])->unique()->values()->all())->toBe(['09123456789'])
        ->and($requests[2]['token2'])->toBe('ساخت')
        ->and($requests[3]['token2'])->toBe($shipment->track_number);
});
