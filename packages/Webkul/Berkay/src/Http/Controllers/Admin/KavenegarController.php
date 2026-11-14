<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Webkul\Berkay\Http\Requests\KavenegarSettingsRequest;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Berkay\Services\PhoneVerification;
use Webkul\Berkay\Services\SmsTemplates;
use Webkul\CartRule\Models\CartRuleCouponProxy;
use Webkul\Customer\Repositories\CustomerRepository;

class KavenegarController
{
    public function __construct(
        protected IntegrationSettings $settings,
        protected SmsTemplates $templates,
        protected KavenegarSms $sms,
        protected PhoneVerification $verification,
    ) {}

    public function index(): View
    {
        abort_unless(bouncer()->hasPermission('berkay.integrations'), 403);

        return view('berkay::admin.kavenegar', [
            'configured' => filled($this->settings->get('kavenegar.api_key')),
            'enabled' => (bool) $this->settings->get('kavenegar.enabled'),
            'orderEvents' => SmsTemplates::ORDER_EVENTS,
            'adminEvents' => SmsTemplates::ADMIN_EVENTS,
            'adminRecipient' => $this->templates->adminRecipient(),
            'notifications' => collect(SmsTemplates::NOTIFICATION_EVENTS)->mapWithKeys(fn ($event) => [
                $event => $this->templates->eventEnabled($event),
            ]),
            'templates' => collect(SmsTemplates::EVENTS)->mapWithKeys(fn ($event) => [
                $event => $this->settings->get('kavenegar.'.$event.'_template'),
            ]),
        ]);
    }

    public function update(KavenegarSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($data['enabled'] && (! ($data['api_key'] ?? $this->settings->get('kavenegar.api_key')) || empty($data['templates']['login']))) {
            return back()->withErrors(['enabled' => trans('berkay::sms.enable-requirements')])
                ->withInput($request->except('api_key'));
        }

        $recipient = array_key_exists('admin_recipient', $data)
            ? (filled($data['admin_recipient']) ? $this->sms->normalizePhone($data['admin_recipient']) : null)
            : $this->templates->adminRecipient();

        foreach (SmsTemplates::ADMIN_EVENTS as $event) {
            if (! ($data['notifications'][$event] ?? $this->templates->eventEnabled($event))) {
                continue;
            }

            if (! $recipient || blank(array_key_exists($event, $data['templates'] ?? []) ? $data['templates'][$event] : $this->settings->get('kavenegar.'.$event.'_template'))) {
                return back()->withErrors(['admin_recipient' => trans('berkay::sms.admin-requirements')])
                    ->withInput($request->except('api_key'));
            }
        }

        DB::transaction(function () use ($data, $recipient) {
            if (array_key_exists('admin_recipient', $data)) {
                $this->settings->put('kavenegar.admin_recipient', $recipient);
            }

            if (filled($data['api_key'] ?? null)) {
                $this->settings->put('kavenegar.api_key', $data['api_key']);
            }

            $this->settings->put('kavenegar.enabled', (bool) $data['enabled']);

            foreach ($data['notifications'] ?? [] as $event => $enabled) {
                $this->settings->put('kavenegar.'.$event.'_enabled', (bool) $enabled);
            }
            foreach (SmsTemplates::EVENTS as $event) {
                if (in_array($event, SmsTemplates::ADMIN_EVENTS, true) && ! array_key_exists($event, $data['templates'] ?? [])) {
                    continue;
                }

                $this->settings->put('kavenegar.'.$event.'_template', $data['templates'][$event] ?? null);
            }
        });

        return redirect()->route('admin.berkay.kavenegar.index')->with('success', trans('berkay::app.saved'));
    }

    public function send(Request $request, CustomerRepository $customers): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.integrations.edit'), 403);

        $data = $request->validate([
            'message_type' => ['required', Rule::in(['test', 'coupon'])],
            'recipient' => ['required', 'string', 'max:32'],
            'coupon' => ['required_if:message_type,coupon', 'nullable', 'string', 'max:100', 'regex:/^\S+$/u'],
        ]);
        $phone = $this->verification->normalize($data['recipient']);
        $template = $this->templates->template($data['message_type']);

        if ($data['message_type'] === 'test') {
            $template ??= $this->templates->template('login');
            $token = (string) random_int(100000, 999999);
        } else {
            $channelId = core()->getCurrentChannel()->id;
            $customer = $customers->findOneWhere(['phone' => $phone, 'channel_id' => $channelId, 'status' => 1, 'is_verified' => 1]);
            $coupon = CartRuleCouponProxy::modelClass()::query()
                ->where('code', $data['coupon'])
                ->whereHas('cart_rule', fn ($rule) => $rule
                    ->where('status', 1)
                    ->where(fn ($dates) => $dates->whereNull('starts_from')->orWhereDate('starts_from', '<=', today()))
                    ->where(fn ($dates) => $dates->whereNull('ends_till')->orWhereDate('ends_till', '>=', today()))
                    ->whereHas('cart_rule_channels', fn ($channels) => $channels->where('channels.id', $channelId)))
                ->first();

            if (! $customer || $customer->is_suspended || ! $coupon) {
                throw ValidationException::withMessages(['coupon' => trans('berkay::sms.coupon-invalid')]);
            }

            $token = $coupon->code;
        }

        if (! $template) {
            throw ValidationException::withMessages(['recipient' => trans('berkay::sms.unavailable')]);
        }

        try {
            $this->sms->lookup($phone, $template, $token);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['recipient' => trans('berkay::sms.send-failed')]);
        }

        return redirect()->route('admin.berkay.kavenegar.index')->with('success', trans('berkay::sms.accepted'));
    }
}
