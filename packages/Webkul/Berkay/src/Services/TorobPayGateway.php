<?php

namespace Webkul\Berkay\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Sales\Contracts\Order;

class TorobPayGateway
{
    /** Use the merchant API published in the integration guide. */
    private const API = 'https://cpg.torobpay.com/api/online/';

    /** Inject settings, Iranian currency conversion and phone normalization. */
    public function __construct(
        protected IntegrationSettings $settings,
        protected IranianMoney $money,
        protected KavenegarSms $phones,
    ) {}

    /** Determine whether all merchant credentials are configured. */
    public function ready(): bool
    {
        return collect(BankGateways::CREDENTIALS['torobpay'])->every(fn ($key) => filled($this->settings->get('torobpay.'.$key)));
    }

    /** Freeze merchant credentials and assign a unique merchant transaction ID. */
    public function context(): array
    {
        $credentials = collect(BankGateways::CREDENTIALS['torobpay'])->mapWithKeys(fn ($key) => [$key => $this->settings->get('torobpay.'.$key)]);

        return $credentials->all() + ['transaction_id' => Str::random(32), 'sandbox' => false];
    }

    /** Request a payment token for an agreed server-side cart or order. */
    public function purchase(int $amount, string $callback, array $context, Cart|Order $source): array
    {
        $address = $source->shipping_address ?? $source->billing_address;
        $currency = $source->base_currency_code;
        $customer = $source->customer;
        $isCart = $source instanceof Cart;
        $shipping = $isCart ? $source->selected_shipping_rate?->base_price : $source->base_shipping_amount;
        $tax = $isCart ? $source->base_tax_total : $source->base_tax_amount;
        $details = [
            'address' => trim((string) $address?->address),
            'postalCode' => KavenegarSms::westernDigits(trim((string) $address?->postcode)),
            'customer_full_name' => trim((string) $address?->name),
            'city' => trim((string) $address?->city),
            'province' => trim((string) $address?->state),
            'registration_phone_number' => $this->phones->normalizePhone((string) $customer?->phone),
        ];

        if (
            $amount <= 0
            || $source->items->isEmpty()
            || in_array('', $details, true)
        ) {
            throw new RuntimeException('TorobPay requires a complete customer address.');
        }

        $items = $source->items->map(function ($item) use ($isCart, $currency) {
            $product = $item->product;

            return [
                'id' => (string) $item->id,
                'name' => (string) $item->name,
                'count' => (int) ($isCart ? $item->quantity : $item->qty_ordered),
                'amount' => $this->money->toRial($item->base_price, $currency),
                'category' => (string) ($product?->categories->first()?->name ?? $product?->parent?->categories->first()?->name ?? config('app.name')),
            ];
        })->values()->all();

        $response = $this->response($this->authenticated($context)->post('payment/v1/token', $details + [
            'amount' => $amount,
            'discountAmount' => $this->money->toRial($source->base_discount_amount ?? 0, $currency),
            'paymentMethodTypeDto' => 'ONLINE_CREDIT',
            'returnURL' => $callback,
            'transactionId' => $context['transaction_id'],
            'cartList' => [[
                'cartId' => (string) ($isCart ? $source->id : ($source->cart_id ?? $source->id)),
                'totalAmount' => $amount,
                'shippingAmount' => $this->money->toRial($shipping ?? 0, $currency),
                'taxAmount' => $this->money->toRial($tax ?? 0, $currency),
                'isTaxIncluded' => (float) $tax > 0,
                'isShipmentIncluded' => $source->shipping_address !== null,
                'cartItems' => $items,
            ]],
        ]));
        $token = $response['paymentToken'] ?? null;
        $url = $response['paymentPageUrl'] ?? null;

        if (
            ! is_string($token)
            || $token === ''
            || strlen($token) > 255
            || ! $this->matchesAmount($response['amount'] ?? $amount, $amount)
        ) {
            throw new RuntimeException('Invalid TorobPay payment token or amount.');
        }

        return [$token, $this->redirectUrl($url)];
    }

    /** Only redirect to an HTTPS page hosted by TorobPay. */
    public function redirectUrl(mixed $url): string
    {
        $parts = is_string($url) && filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : [];
        $host = strtolower($parts['host'] ?? '');

        if (
            ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || ($parts['port'] ?? 443) !== 443
            || (! Str::endsWith($host, ['.torobpay.com']) && ! in_array($host, ['torobpay.com', 'pay.torob.com'], true))
        ) {
            throw new RuntimeException('Invalid TorobPay redirect URL.');
        }

        return $url;
    }

    /** Verify and settle the saved token after confirming the provider's amount. */
    public function verify(string $authority, int $amount, array $context, bool $recoverOnly = false): ?string
    {
        $http = $this->authenticated($context);
        $payload = ['paymentToken' => $authority];
        $status = $this->response($http->get('payment/v1/status', $payload));

        if (! $this->matchesAmount($status['amount'] ?? null, $amount)) {
            throw new RuntimeException('TorobPay amount mismatch.');
        }

        $state = $status['status'] ?? null;
        if ($state === 'SETTLE') {
            return $this->receipt($status);
        }

        if ($recoverOnly && $state !== 'VERIFY') {
            return null;
        }

        if ($state !== 'VERIFY') {
            $this->receipt($this->response($http->post('payment/v1/verify', $payload)));
        }

        return $this->receipt($this->response($http->post('payment/v1/settle', $payload)));
    }

    /** Compare whole-rial values without accepting truncated input. */
    public function matchesAmount(mixed $actual, int $expected): bool
    {
        return (is_int($actual) || is_string($actual)) && (string) $actual === (string) $expected;
    }

    /** Get a server-side access token before sending merchant API requests. */
    private function authenticated(array $context): PendingRequest
    {
        $http = Http::baseUrl(self::API)->acceptJson()->asJson()->connectTimeout(3)->timeout(8)->withoutRedirecting();
        $response = (clone $http)->withBasicAuth($context['client_id'], $context['client_secret'])->post('v1/oauth/token', [
            'username' => $context['username'],
            'password' => $context['password'],
        ]);
        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('TorobPay authentication failed.');
        }

        return $http->withToken($token);
    }

    /** Accept successful provider payloads only. */
    private function response(Response $response): array
    {
        $body = $response->json();

        if (
            ! $response->successful()
            || ($body['successful'] ?? null) !== true
            || ! is_array($body['response'] ?? null)
        ) {
            throw new RuntimeException('TorobPay rejected the payment operation.');
        }

        return $body['response'];
    }

    /** Extract a stable receipt suitable for local payment reconciliation. */
    private function receipt(array $response): string
    {
        $receipt = $response['transactionId'] ?? null;

        if ((! is_string($receipt) && ! is_int($receipt)) || $receipt === '' || strlen((string) $receipt) > 120) {
            throw new RuntimeException('TorobPay did not return a valid receipt.');
        }

        return 'torobpay:'.$receipt;
    }
}
