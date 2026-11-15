<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\Http\Requests\BankPaymentSettingsRequest;
use Webkul\Berkay\Http\Requests\IntegrationSettingsRequest;
use Webkul\Berkay\Services\BankGateways;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Core\Repositories\CoreConfigRepository;

class IntegrationController extends Controller
{
    /** Inject credential storage and standard payment activation configuration. */
    public function __construct(protected IntegrationSettings $settings, protected CoreConfigRepository $configuration) {}

    /** Render provider status without sending any stored credential to the browser. */
    public function index(BankGateways $banks): View
    {
        abort_unless(bouncer()->hasPermission('berkay.integrations'), 403);

        $channel = core()->getCurrentChannel();
        $currency = $channel?->base_currency?->code;

        return view('berkay::admin.integrations', [
            'currency' => $currency,
            'currencySupported' => in_array($currency, ['IRR', 'IRT'], true),
            'zarinpalActive' => (bool) core()->getConfigData('sales.payment_methods.berkay_zarinpal.active'),
            'smsConfigured' => filled($this->settings->get('kavenegar.api_key')),
            'smsPaused' => (bool) config('berkay.kavenegar.paused'),
            'smsEnabled' => (bool) $this->settings->get('kavenegar.enabled'),
            'otpConfigured' => filled($this->settings->get('kavenegar.login_template')),
            'merchantConfigured' => filled($this->settings->get('zarinpal.merchant_id')),
            'sandbox' => $this->settings->get('zarinpal.sandbox') ?? true,
            'torobEnabled' => $this->settings->get('torob.enabled') ?? false,
            'bankStates' => collect(array_keys(BankGateways::CREDENTIALS))->mapWithKeys(fn ($bank) => [$bank => [
                'enabled' => (bool) $this->settings->get($bank.'.enabled'),
                'ready' => $banks->gateway($bank)->ready(),
                'saved' => collect(BankGateways::CREDENTIALS[$bank])->mapWithKeys(fn ($key) => [$key => filled($this->settings->get($bank.'.'.$key))]),
            ]]),
            'transferInstructions' => core()->getConfigData('sales.payment_methods.moneytransfer.mailing_address'),
        ]);
    }

    /** Save provider settings atomically while preserving blank credential fields. */
    public function update(IntegrationSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach (['zarinpal_merchant_id' => 'zarinpal.merchant_id'] as $field => $key) {
                if (filled($data[$field] ?? null)) {
                    $this->settings->put($key, $data[$field]);
                }
            }
            foreach (['zarinpal_sandbox' => 'zarinpal.sandbox', 'torob_enabled' => 'torob.enabled'] as $field => $key) {
                $this->settings->put($key, (bool) $data[$field]);
            }
            $this->configuration->create([
                'channel' => core()->getCurrentChannelCode(),
                'sales' => ['payment_methods' => ['berkay_zarinpal' => ['active' => (int) $data['zarinpal_active']]]],
            ]);
        });

        return redirect()->route('admin.berkay.integrations.index')->with('success', trans('berkay::app.saved'));
    }

    /** Save one provider without replacing omitted credentials or unrelated settings. */
    public function updatePayment(BankPaymentSettingsRequest $request, string $provider): RedirectResponse
    {
        $data = $request->validated();

        if ($provider === 'moneytransfer') {
            $this->configuration->create([
                'channel' => core()->getCurrentChannelCode(),
                'sales' => ['payment_methods' => ['moneytransfer' => ['mailing_address' => $data['instructions'] ?? '']]],
            ]);

            return back()->with('success', trans('berkay::app.saved'));
        }

        DB::transaction(function () use ($provider, $data) {
            foreach (BankGateways::CREDENTIALS[$provider] as $key) {
                if (filled($data[$key] ?? null)) {
                    $this->settings->put($provider.'.'.$key, $data[$key]);
                }
            }

            $this->settings->put($provider.'.enabled', (bool) $data['enabled']);
        });

        return back()->with('success', trans('berkay::app.saved'));
    }
}
