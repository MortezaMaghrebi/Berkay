<?php

namespace Webkul\Berkay\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class IranKishGateway
{
    /** Resolve merchant credentials from encrypted store settings. */
    public function __construct(protected IntegrationSettings $settings) {}

    /** Report whether the merchant and encryption runtime are ready. */
    public function ready(): bool
    {
        return function_exists('openssl_public_encrypt')
            && collect(BankGateways::CREDENTIALS['irankish'])->every(fn ($key) => filled($this->settings->get('irankish.'.$key)));
    }

    /** Freeze the terminal identity and provider request reference. */
    public function context(): array
    {
        return [
            'terminalId' => $this->settings->get('irankish.terminal_id'),
            'acceptorId' => $this->settings->get('irankish.acceptor_id'),
            'password' => $this->settings->get('irankish.password'),
            'public_key' => $this->settings->get('irankish.public_key'),
            'reference' => bin2hex(random_bytes(10)),
            'sandbox' => false,
        ];
    }

    /** Request an Iran Kish token for a standard retail payment. */
    public function purchase(int $amountRial, string $callbackUrl, array $context): string
    {
        if ($amountRial > 999_999_999_999) {
            throw new RuntimeException('The amount exceeds the Iran Kish protocol limit.');
        }

        $body = $this->call('tokenization/make', [
            'authenticationEnvelope' => $this->envelope($amountRial, $context),
            'request' => [
                'terminalId' => $context['terminalId'], 'acceptorId' => $context['acceptorId'],
                'amount' => $amountRial, 'requestId' => $context['reference'], 'transactionType' => 'Purchase',
                'revertUri' => $callbackUrl, 'requestTimestamp' => now()->timestamp,
            ],
        ]);

        $token = $body['result']['token'] ?? '';

        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{10,48}$/D', $token)) {
            throw new RuntimeException('Iran Kish did not issue a payment token.');
        }

        return $token;
    }

    /** Confirm the named transaction for the frozen token, reference and rial amount. */
    public function verify(string $authority, int $amountRial, array $context, array $callback): string
    {
        $receipt = $callback['retrievalReferenceNumber'] ?? '';
        $trace = $callback['systemTraceAuditNumber'] ?? '';

        if (
            ($callback['requestId'] ?? $callback['RequestId'] ?? null) !== $context['reference']
            || ($callback['acceptorId'] ?? null) !== $context['acceptorId']
            || ! $this->matchesAmount($callback['amount'] ?? null, $amountRial)
            || ! is_scalar($receipt)
            || ! preg_match('/^[0-9]{12}$/D', (string) $receipt)
            || ! is_scalar($trace)
            || ! preg_match('/^[0-9]{1,6}$/D', (string) $trace)
        ) {
            throw new RuntimeException('Iran Kish confirmation does not match the order.');
        }

        $body = $this->call('confirmation/purchase', [
            'terminalId' => $context['terminalId'], 'tokenIdentity' => $authority,
            'retrievalReferenceNumber' => (string) $receipt, 'systemTraceAuditNumber' => str_pad((string) $trace, 6, '0', STR_PAD_LEFT),
            'invoiceId' => $context['reference'], 'amount' => (string) $amountRial,
        ]);

        $result = $body['result'] ?? [];

        if (
            ($result['responseCode'] ?? null) !== '00'
            || ! $this->matchesAmount($result['amount'] ?? null, $amountRial)
            || (string) ($result['retrievalReferenceNumber'] ?? '') !== (string) $receipt
        ) {
            throw new RuntimeException('Iran Kish verification was rejected.');
        }

        return 'irankish:'.$receipt;
    }

    /** Build the provider's AES and RSA authentication envelope. */
    protected function envelope(int $amountRial, array $context): array
    {
        $key = random_bytes(16);
        $iv = random_bytes(16);
        $payload = hex2bin($context['terminalId'].$context['password'].str_pad((string) $amountRial, 12, '0', STR_PAD_LEFT).'00');
        $encrypted = $payload === false ? false : openssl_encrypt($payload, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false || ! openssl_public_encrypt($key.hash('sha256', $encrypted, true), $envelope, $context['public_key'], OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('Iran Kish could not encode the merchant request.');
        }

        return ['iv' => strtoupper(bin2hex($iv)), 'data' => strtoupper(bin2hex($envelope))];
    }

    /** Compare a provider amount without accepting nonnumeric callback values. */
    protected function matchesAmount(mixed $amount, int $expected): bool
    {
        return is_scalar($amount) && preg_match('/^[0-9]{1,12}$/D', (string) $amount) && (int) $amount === $expected;
    }

    /** Call the allowlisted provider API without propagating its response body. */
    protected function call(string $operation, array $parameters): array
    {
        try {
            $response = Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->acceptJson()
                ->post('https://ikc.shaparak.ir/api/v3/'.$operation, $parameters);
        } catch (Throwable) {
            throw new RuntimeException('Iran Kish did not respond to the payment operation.');
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['status'] ?? false) !== true || ($body['responseCode'] ?? null) !== '00') {
            throw new RuntimeException('Iran Kish rejected the payment operation.');
        }

        return $body;
    }
}
