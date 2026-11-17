<?php

namespace Webkul\Berkay\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function verify(string $authority, int $amountRial, ?array $context = null, array $callback = []): string
    {
        $context ??= $this->context();

        return $this->receipt($this->verificationResponse($authority, $amountRial, $context));
    }

    /** Reconcile a successful payment or allow replacement of a closed authority. */
    public function reconcile(string $authority, int $amountRial, array $context): ?string
    {
        $response = $this->verificationResponse($authority, $amountRial, $context);
        $code = (int) ($response['errors']['code'] ?? $response['data']['code'] ?? 0);

        if ($code === -54) {
            return null;
        }

        if ($code === -51) {
            $inquiry = $this->call('inquiry', [
                'merchant_id' => $context['merchant_id'],
                'authority' => $authority,
            ], $context);

            if (
                (int) ($inquiry['errors']['code'] ?? 0) === -54
                || ((int) ($inquiry['data']['code'] ?? 0) === 100
                    && in_array($inquiry['data']['status'] ?? null, ['FAILED', 'REVERSED'], true))
            ) {
                return null;
            }
        }

        return $this->receipt($response);
    }

    /** Return an allowlisted provider redirect target. */
    public function redirectUrl(string $authority, ?array $context = null): string
    {
        $context ??= $this->context();

        return 'https://'.($context['sandbox'] ? 'sandbox.zarinpal.com' : 'www.zarinpal.com').'/pg/StartPay/'.$authority;
    }

    /** Freeze provider identity and mode for an in-flight payment. */
    public function context(): array
    {
        return [
            'merchant_id' => app(IntegrationSettings::class)->get('zarinpal.merchant_id'),
            'sandbox' => (bool) app(IntegrationSettings::class)->get('zarinpal.sandbox'),
        ];
    }

    /** Submit the original authority and exact amount for verification. */
    protected function verificationResponse(string $authority, int $amountRial, array $context): array
    {
        return $this->call('verify', [
            'merchant_id' => $context['merchant_id'],
            'authority' => $authority,
            'amount' => $amountRial,
        ], $context);
    }

    /** Extract a receipt only from a successful verification. */
    protected function receipt(array $response): string
    {
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

    /** Use verified TLS and bounded timeouts without exposing merchant credentials. */
    protected function call(string $action, array $data, array $context): array
    {
        $host = $context['sandbox'] ? 'sandbox.zarinpal.com' : 'api.zarinpal.com';

        try {
            $response = Http::connectTimeout(5)->timeout(20)->acceptJson()
                ->post('https://'.$host.'/pg/v4/payment/'.$action.'.json', $data);

            $body = $response->json();

            if (
                ! is_array($body)
                || (! $response->successful() && ! (
                    $response->clientError()
                    && in_array((int) ($body['errors']['code'] ?? 0), [-51, -54], true)
                ))
            ) {
                Log::warning('Zarinpal returned an unsuccessful HTTP response.', ['operation' => $action, 'http_status' => $response->status()]);

                throw new RuntimeException;
            }

            $code = $body['errors']['code'] ?? null;

            if (is_numeric($code)) {
                Log::warning('Zarinpal rejected a payment operation.', ['operation' => $action, 'provider_code' => (int) $code]);
            }

            return $body;
        } catch (Throwable) {
            throw new RuntimeException('The payment provider could not complete this request.');
        }
    }
}
