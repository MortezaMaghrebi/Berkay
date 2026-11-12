<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Berkay\Models\IntegrationSetting;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Berkay\Services\SmsTemplates;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->integrationData = [
        'zarinpal_active' => 0,
        'zarinpal_sandbox' => 1,
        'zarinpal_merchant_id' => '00000000-0000-4000-8000-000000000001',
        'kavenegar_enabled' => 0,
        'kavenegar_api_key' => 'TESTCREDENTIAL123456789',
        'kavenegar_order_template' => 'orderCreated',
        'kavenegar_status_template' => 'orderStatus',
        'torob_enabled' => 0,
    ];
});

it('encrypts administrator credentials and never includes them in the rendered settings', function () {
    $this->loginAsAdmin();
    $this->put(route('admin.berkay.integrations.update'), $this->integrationData)->assertRedirect();

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
    $this->put(route('admin.berkay.integrations.update'), array_merge($this->integrationData, [
        'zarinpal_merchant_id' => '', 'kavenegar_api_key' => '',
    ]))->assertRedirect();
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
    expect(session()->getOldInput())->not->toHaveKeys(['kavenegar_api_key', 'zarinpal_merchant_id']);
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
