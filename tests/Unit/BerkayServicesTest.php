<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Webkul\Berkay\Services\IranianMoney;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Berkay\Services\TorobToken;
use Webkul\Berkay\Services\ZarinpalGateway;

uses(TestCase::class);

it('normalizes Iranian phone formats without accepting other numbers', function (string $input) {
    expect((new KavenegarSms)->normalizePhone($input))->toBe('09123456789');
})->with(['09123456789', '۰۹۱۲۳۴۵۶۷۸۹', '٠٩١٢٣٤٥٦٧٨٩', '+989123456789', '00989123456789', '0912 345 6789']);

it('rejects malformed and foreign phone numbers', function (string $input) {
    expect(fn () => (new KavenegarSms)->normalizePhone($input))->toThrow(InvalidArgumentException::class);
})->with(['', '+15551234567', '0912345678', '091234567890', 'not-a-phone']);

it('preserves rial precision while converting tomans explicitly', function () {
    $money = new IranianMoney;

    expect($money->toRial(1005, 'IRR'))->toBe(1005)
        ->and($money->toRial(125000, 'IRT'))->toBe(1250000)
        ->and($money->toToman(1250000, 'IRR'))->toBe(125000);
});

it('rejects foreign and negative payment amounts', function () {
    $money = new IranianMoney;
    expect(fn () => $money->toRial(100, 'USD'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $money->toRial(-1, 'IRT'))->toThrow(InvalidArgumentException::class);
});

it('uses the stored authority and exact amount for Zarinpal verification', function () {
    config(['berkay.zarinpal.merchant_id' => 'test-merchant', 'berkay.zarinpal.sandbox' => true]);
    Http::preventStrayRequests();
    Http::fake(['sandbox.zarinpal.com/*' => Http::response(['data' => ['code' => 101, 'ref_id' => 123456]], 200)]);

    expect((new ZarinpalGateway)->verify(str_repeat('A', 36), 1250000))->toBe('123456');
    Http::assertSent(fn ($request) => $request['amount'] === 1250000 && $request['authority'] === str_repeat('A', 36));
});

it('never treats a failed bank verification as a paid receipt', function () {
    config(['berkay.zarinpal.sandbox' => true]);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['data' => ['code' => -51]], 200)]);

    expect(fn () => (new ZarinpalGateway)->verify(str_repeat('A', 36), 1250000))->toThrow(RuntimeException::class);
});

it('sends Kavenegar template fields without making real network calls', function () {
    config(['berkay.kavenegar.api_key' => 'TESTKEY123']);
    Http::preventStrayRequests();
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]], 200)]);

    (new KavenegarSms)->lookup('۰۹۱۲۳۴۵۶۷۸۹', 'orderCreated', 'BK123');
    Http::assertSent(fn ($request) => $request['receptor'] === '09123456789' && $request['token'] === 'BK123' && $request['template'] === 'orderCreated');
});

it('does not disclose the SMS credential when the provider fails', function () {
    config(['berkay.kavenegar.api_key' => 'PRIVATEKEY123']);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response([], 500)]);

    try {
        (new KavenegarSms)->lookup('09123456789', 'orderCreated', '123');
        test()->fail('Provider failure should throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->not->toContain('PRIVATEKEY123');
    }
});

it('rejects unsigned malformed and forged Torob tokens', function (string $token) {
    expect((new TorobToken)->verify($token, 'example.com'))->toBeFalse();
})->with(['', 'a.b.c', 'eyJhbGciOiJub25lIn0.e30.AAAA', '....']);

it('compares translation file names relative to locale directories on Windows and Unix', function () {
    $this->artisan('bagisto:translations:check', ['--package' => 'Berkay', '--locale' => 'fa'])
        ->assertExitCode(0);
});
