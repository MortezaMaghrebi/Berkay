<?php

namespace Webkul\Berkay\Services;

use RuntimeException;
use SoapClient;
use Throwable;

class MellatGateway
{
    /** Resolve merchant credentials from encrypted store settings. */
    public function __construct(protected IntegrationSettings $settings) {}

    /** Report whether the merchant and SOAP runtime are ready. */
    public function ready(): bool
    {
        return class_exists(SoapClient::class)
            && collect(BankGateways::CREDENTIALS['mellat'])->every(fn ($key) => filled($this->settings->get('mellat.'.$key)));
    }

    /** Freeze terminal credentials and a fresh provider order reference. */
    public function context(): array
    {
        return [
            'terminalId' => (int) $this->settings->get('mellat.terminal_id'),
            'userName' => $this->settings->get('mellat.username'),
            'userPassword' => $this->settings->get('mellat.password'),
            'reference' => random_int(1_000_000_000_000, 9_999_999_999_999_999),
            'sandbox' => false,
        ];
    }

    /** Start a BehPardakht sale for the exact rial amount. */
    public function purchase(int $amountRial, string $callbackUrl, array $context): string
    {
        $time = now('Asia/Tehran');
        [$code, $authority] = array_pad(explode(',', $this->call('bpPayRequest', $this->credentials($context) + [
            'orderId' => $context['reference'], 'amount' => $amountRial,
            'localDate' => $time->format('Ymd'), 'localTime' => $time->format('His'),
            'additionalData' => '', 'callBackUrl' => $callbackUrl, 'payerId' => 0,
        ]), 2), 2, '');

        if ($code !== '0' || ! preg_match('/^[A-Za-z0-9]{10,100}$/D', $authority)) {
            throw new RuntimeException('BehPardakht did not issue a payment reference.');
        }

        return $authority;
    }

    /** Match the bank ref and merchant reference before settlement. */
    public function verify(string $authority, int $amountRial, array $context, array $callback): string
    {
        $receipt = $callback['SaleReferenceId'] ?? '';

        if (
            (string) ($callback['SaleOrderId'] ?? '') !== (string) $context['reference']
            || ! is_scalar($receipt)
            || ! preg_match('/^[0-9]{1,18}$/D', (string) $receipt)
        ) {
            throw new RuntimeException('BehPardakht confirmation does not match the order.');
        }

        $params = $this->credentials($context) + [
            'orderId' => $context['reference'], 'saleOrderId' => $context['reference'], 'saleReferenceId' => (int) $receipt,
        ];

        if (! in_array($this->call('bpVerifyRequest', $params), ['0', '43', '45'], true)) {
            throw new RuntimeException('BehPardakht verification was rejected.');
        }

        if (! in_array($this->call('bpSettleRequest', $params), ['0', '45'], true)) {
            throw new RuntimeException('BehPardakht settlement was rejected.');
        }

        return 'mellat:'.$receipt;
    }

    /** Return the terminal parameters accepted by the SOAP operations. */
    protected function credentials(array $context): array
    {
        return array_intersect_key($context, array_flip(['terminalId', 'userName', 'userPassword']));
    }

    /** Call the bank WSDL over TLS with a bounded timeout and no credential traces. */
    protected function call(string $operation, array $parameters): string
    {
        try {
            $client = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl', [
                'connection_timeout' => 5,
                'location' => 'https://bpm.shaparak.ir/pgwchannel/services/pgw',
                'stream_context' => stream_context_create(['http' => ['timeout' => 15]]),
            ]);
            $response = $client->__soapCall($operation, [$parameters]);

            return trim((string) (is_object($response) ? $response->return : $response));
        } catch (Throwable) {
            throw new RuntimeException('BehPardakht did not respond to the payment operation.');
        }
    }
}
