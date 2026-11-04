<?php

namespace Webkul\Berkay\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ZarinpalGateway
{
    /** Request a bank authority for the exact stored rial amount. */
    public function purchase(int $amountRial, string $callbackUrl, ?array $context = null): string
    {
        $context ??= $this->context();
        $response = $this->call('request', [
            'merchant_id' => $context['merchant_id'],
            'amount' => $amountRial,
            'callback_url' => $callbackUrl,
            'description' => config('app.name'),
        ], $context);

        $authority = $response['data']['authority'] ?? null;

        if (
            (int) ($response['data']['code'] ?? 0) !== 100
            || ! is_string($authority)
            || ! preg_match('/^[A-Za-z0-9]{20,100}$/', $authority)
        ) {
            throw new RuntimeException('Payment authority was not issued.');
        }

        return $authority;
    }

    /** Confirm payment with the bank rather than trusting browser callback parameters. */
    public function verify(string $authority, int $amountRial, ?array $context = null): string
    {
        $context ??= $this->context();
        $response = $this->call('verify', [
            'merchant_id' => $context['merchant_id'],
            'authority' => $authority,
            'amount' => $amountRial,
        ], $context);

        $receipt = $response['data']['ref_id'] ?? null;

        if (
            ! in_array((int) ($response['data']['code'] ?? 0), [100, 101], true)
            || ! is_scalar($receipt)
            || ! preg_match('/^[0-9]{1,100}$/', (string) $receipt)
        ) {
            throw new RuntimeException('Payment was not verified.');
        }

        return (string) $receipt;
    }

    /** Return an allowlisted provider redirect target. */
    public function redirectUrl(string $authority, ?array $context = null): string
    {
        $context ??= $this->context();

        return 'https://'.($context['sandbox'] ? 'sandbox.zarinpal.com' : 'payment.zarinpal.com').'/pg/StartPay/'.$authority;
    }

    /** Freeze provider identity and mode for an in-flight payment. */
    public function context(): array
    {
        return [
            'merchant_id' => app(IntegrationSettings::class)->get('zarinpal.merchant_id'),
            'sandbox' => (bool) app(IntegrationSettings::class)->get('zarinpal.sandbox'),
        ];
    }

    /** Use verified TLS and bounded timeouts without exposing merchant credentials. */
    protected function call(string $action, array $data, array $context): array
    {
        $host = $context['sandbox'] ? 'sandbox.zarinpal.com' : 'payment.zarinpal.com';

        try {
            $response = Http::connectTimeout(5)->timeout(20)->acceptJson()
                ->post('https://'.$host.'/pg/v4/payment/'.$action.'.json', $data);

            if (! $response->successful()) {
                throw new RuntimeException;
            }

            $body = $response->json();

            if (! is_array($body)) {
                throw new RuntimeException;
            }

            return $body;
        } catch (Throwable) {
            throw new RuntimeException('The payment provider could not complete this request.');
        }
    }
}
