<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Webkul\Berkay\Jobs\SendOrderSms;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Customer\Models\Customer;
use Webkul\Sales\Models\Order;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

beforeEach(function () {
    $settings = app(IntegrationSettings::class);
    $settings->put('kavenegar.enabled', true);
    $settings->put('kavenegar.api_key', str_repeat('x', 32));
    $settings->put('kavenegar.login_template', 'berkay-login');
    $settings->put('kavenegar.register_template', 'berkay-register');
    Http::preventStrayRequests();
});

it('signs in a customer by Iranian mobile and rejects a wrong code', function () {
    $customer = Customer::factory()->create([
        'phone' => '09990000121',
        'channel_id' => core()->getCurrentChannel()->id,
        'status' => true,
        'is_verified' => false,
    ]);

    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);

    post(route('shop.customer.session.create'), ['phone' => '+۹۸ ۹۹۹ ۰۰۰ ۰۱۲۱'])
        ->assertRedirect(route('shop.customer.session.index'));

    Http::assertSent(fn (Request $request) => $request['receptor'] === $customer->phone && $request['template'] === 'berkay-login');
    $code = Http::recorded()->first()[0]['token'];

    post(route('shop.berkay.phone.login.verify'), ['code' => $code === '123456' ? '123457' : '123456'])
        ->assertSessionHasErrors('code');
    assertGuest('customer');

    post(route('shop.berkay.phone.login.verify'), ['code' => $code])->assertRedirect();
    assertAuthenticatedAs($customer, 'customer');
    expect($customer->fresh()->is_verified)->toBeTrue();
    expect(session()->has('berkay.phone.challenge'))->toBeFalse();
});

it('registers a phone-only account after verification', function () {
    $phone = '09990000122';
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);

    post(route('shop.customers.register.store'), ['phone' => $phone])
        ->assertRedirect(route('shop.customers.register.index'));

    expect(Customer::where('phone', $phone)->exists())->toBeFalse();
    Http::assertSent(fn (Request $request) => $request['template'] === 'berkay-register');

    post(route('shop.berkay.phone.register.verify'), [
        'code' => Http::recorded()->first()[0]['token'],
        'first_name' => 'کاربر',
        'last_name' => 'آزمایشی',
    ])->assertRedirect();

    $customer = Customer::where('phone', $phone)->firstOrFail();
    assertAuthenticatedAs($customer, 'customer');
    expect($customer->email)->toBeNull()
        ->and($customer->password)->toBeNull();
});

it('does not create a challenge when Kavenegar rejects the message', function () {
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 424]], 424)]);

    post(route('shop.customer.session.create'), ['phone' => '09990000123'])
        ->assertSessionHasErrors('phone');

    expect(session()->has('berkay.phone.challenge'))->toBeFalse();
    assertGuest('customer');
});

it('sends a shipment template with tracking and prevents duplicate sends', function () {
    app(IntegrationSettings::class)->put('kavenegar.shipped_template', 'berkay-shipped');

    $customer = Customer::factory()->create(['phone' => '09990000124']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);

    $job = new SendOrderSms($order->id, 'shipped-1234', '1234567890');
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['receptor'] === $customer->phone
        && $request['template'] === 'berkay-shipped'
        && $request['token'] === (string) $order->increment_id
        && $request['token2'] === '1234567890');
});

it('sends a manual approval only while the order is still awaiting payment', function () {
    app(IntegrationSettings::class)->put('kavenegar.stage_approved_template', 'order-approved');
    $customer = Customer::factory()->create(['phone' => '09990000124']);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'base_grand_total' => 100]);
    $workflow = app(OrderWorkflow::class);
    $workflow->query()->insert(['order_id' => $order->id, 'review' => 'approved']);

    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]])]);
    $job = new SendOrderSms($order->id, 'stage_approved');
    config(['berkay.kavenegar.paused' => true]);
    app()->call([$job, 'handle']);
    Http::assertNothingSent();

    config(['berkay.kavenegar.paused' => false]);
    $workflow->query()->where('order_id', $order->id)->update(['review' => 'waiting']);
    app()->call([$job, 'handle']);
    Http::assertNothingSent();

    $workflow->query()->where('order_id', $order->id)->update(['review' => 'approved']);
    app()->call([$job, 'handle']);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['template'] === 'order-approved');
});
