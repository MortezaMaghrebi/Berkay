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

        return view('berkay::admin.integrations', [
            'merchantConfigured' => filled($this->settings->get('zarinpal.merchant_id')),
            'smsConfigured' => filled($this->settings->get('kavenegar.api_key')),
            'sandbox' => $this->settings->get('zarinpal.sandbox') ?? true,
            'smsEnabled' => $this->settings->get('kavenegar.enabled') ?? false,
            'torobEnabled' => $this->settings->get('torob.enabled') ?? false,
            'orderTemplate' => $this->settings->get('kavenegar.order_template'),
            'statusTemplate' => $this->settings->get('kavenegar.status_template'),
        ]);
    }

    /** Save provider settings atomically while preserving blank credential fields. */
    public function update(IntegrationSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data) {
            foreach (['zarinpal_merchant_id' => 'zarinpal.merchant_id', 'kavenegar_api_key' => 'kavenegar.api_key'] as $field => $key) {
                if (filled($data[$field] ?? null)) {
                    $this->settings->put($key, $data[$field]);
                }
            }
            foreach (['zarinpal_sandbox' => 'zarinpal.sandbox', 'kavenegar_enabled' => 'kavenegar.enabled', 'torob_enabled' => 'torob.enabled'] as $field => $key) {
                $this->settings->put($key, (bool) $data[$field]);
            }
            $this->settings->put('kavenegar.order_template', $data['kavenegar_order_template'] ?? null);
            $this->settings->put('kavenegar.status_template', $data['kavenegar_status_template'] ?? null);
            $this->configuration->create([
                'channel' => core()->getCurrentChannelCode(),
                'sales' => ['payment_methods' => ['berkay_zarinpal' => ['active' => (int) $data['zarinpal_active']]]],
            ]);
        });

        return redirect()->route('admin.berkay.integrations.index')->with('success', trans('berkay::app.saved'));
    }
}
