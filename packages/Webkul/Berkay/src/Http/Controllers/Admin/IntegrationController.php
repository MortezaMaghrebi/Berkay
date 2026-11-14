<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\Http\Requests\IntegrationSettingsRequest;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Core\Repositories\CoreConfigRepository;

class IntegrationController extends Controller
{
    /** Inject credential storage and standard payment activation configuration. */
    public function __construct(protected IntegrationSettings $settings, protected CoreConfigRepository $configuration) {}

    /** Render provider status without sending any stored credential to the browser. */
    public function index(): View
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
        ]);
    }

    /** Save provider settings atomically while preserving blank credential fields. */
    public function update(IntegrationSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($data['zarinpal_active'] && blank($data['zarinpal_merchant_id'] ?? $this->settings->get('zarinpal.merchant_id'))) {
            return back()->withErrors(['zarinpal_merchant_id' => trans('berkay::integrations.merchant-required')])
                ->withInput($request->except('zarinpal_merchant_id'));
        }

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
}
