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
            'sandbox' => $this->settings->get('zarinpal.sandbox') ?? true,
            'torobEnabled' => $this->settings->get('torob.enabled') ?? false,
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
}
