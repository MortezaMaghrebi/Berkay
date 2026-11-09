<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Berkay\Services\PhoneVerification;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Sales\Models\Order;

class PhoneAccountController
{
    public function __construct(
        protected PhoneVerification $verification,
        protected CustomerRepository $customers,
        protected CustomerGroupRepository $groups,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (auth()->guard('customer')->check()) {
            return redirect()->route('shop.customers.account.profile.index');
        }

        $purpose = $request->routeIs('shop.customers.register.index') ? 'register' : 'login';

        return $this->form($request, $purpose);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_if(auth()->guard('customer')->check(), 403);
        $purpose = $request->routeIs('shop.customers.register.store') ? 'register' : 'login';

        return $this->sendCode($request, $purpose);
    }

    public function verify(Request $request): RedirectResponse
    {
        $guard = auth()->guard('customer');
        abort_if($guard->check(), 403);
        $purpose = $request->routeIs('shop.berkay.phone.register.verify') ? 'register' : 'login';
        $rules = ['code' => ['required', 'string', 'max:12']];

        if ($purpose === 'register') {
            $rules += [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
            ];
        }

        $data = $request->validate($rules);
        $phone = $this->verification->consume($request, $purpose, $data['code']);
        $channelId = core()->getCurrentChannel()->id;
        $customer = $this->customers->findOneWhere(['phone' => $phone]);

        if ($customer && ($customer->channel_id !== $channelId || ! $customer->status || $customer->is_suspended)) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.account-unavailable')]);
        }

        if (! $customer) {
            if ($purpose !== 'register') {
                return redirect()->route('shop.customers.register.index')->withInput(['phone' => $phone])
                    ->with('info', trans('berkay::sms.register-first'));
            }

            $group = $this->groups->findOneByField('code', core()->getConfigData('customer.settings.create_new_account_options.default_group') ?: 'general')
                ?? $this->groups->findOneByField('code', 'general');

            try {
                $customer = $this->customers->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $phone,
                    'channel_id' => $channelId,
                    'customer_group_id' => $group?->id,
                    'status' => 1,
                    'is_verified' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['phone' => trans('berkay::sms.account-unavailable')]);
            }

            Event::dispatch('customer.create.after', $customer);
        } elseif (! $customer->is_verified) {
            $customer->forceFill(['is_verified' => true])->save();
        }

        $guard->login($customer);
        $request->session()->regenerate();
        $request->session()->put('berkay.phone.verified', ['phone' => $phone, 'at' => now()->timestamp]);
        Event::dispatch('customer.after.login', $customer);

        if ($intended = $request->session()->pull('shop.url.intended')) {
            return redirect()->to($intended);
        }

        return redirect()->route(core()->getConfigData('customer.settings.login_options.redirected_to_page') === 'account'
            ? 'shop.customers.account.profile.index'
            : 'shop.home.index');
    }

    public function settings(Request $request, string $purpose): View
    {
        return $this->form($request, $purpose, auth()->guard('customer')->id());
    }

    public function sendSettingsCode(Request $request, string $purpose): RedirectResponse
    {
        $customer = auth()->guard('customer')->user();

        if ($purpose === 'change' && ! $this->recentlyVerified($request)) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.relogin')]);
        }

        return $this->sendCode($request, $purpose, $customer->id, $purpose === 'delete' ? (string) $customer->phone : null);
    }

    public function verifySettings(Request $request, string $purpose): RedirectResponse
    {
        $customer = auth()->guard('customer')->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        if ($purpose === 'change' && ! $this->recentlyVerified($request)) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.relogin')]);
        }

        $phone = $this->verification->consume($request, $purpose, $data['code'], $customer->id);

        if ($purpose === 'delete') {
            abort_unless($phone === $customer->phone, 403);

            if ($customer->orders()->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PENDING_PAYMENT, Order::STATUS_PROCESSING])->exists()) {
                return redirect()->route('shop.customers.account.profile.index')
                    ->with('error', trans('shop::app.customers.account.profile.index.order-pending'));
            }

            $this->customers->delete($customer->id);
            auth()->guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('shop.customer.session.index')
                ->with('success', trans('shop::app.customers.account.profile.index.delete-success'));
        }

        try {
            $customer->forceFill(['phone' => $phone, 'remember_token' => null])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone' => trans('berkay::sms.phone-in-use')]);
        }

        $request->session()->regenerate();
        Event::dispatch('customer.update.after', $customer);

        return redirect()->route('shop.customers.account.profile.index')->with('success', trans('berkay::sms.phone-updated'));
    }

    private function form(Request $request, string $purpose, ?int $customerId = null): View
    {
        return view('berkay::shop.phone-account', [
            'purpose' => $purpose,
            'challenge' => $request->boolean('start') ? null : $this->verification->pending($request, $purpose, $customerId),
        ]);
    }

    private function sendCode(Request $request, string $purpose, ?int $customerId = null, ?string $registeredPhone = null): RedirectResponse
    {
        $phone = $registeredPhone ?? $request->validate(['phone' => ['required', 'string', 'max:32']])['phone'];
        $this->verification->issue($request, $purpose, $this->verification->normalize($phone), $customerId);

        return match ($purpose) {
            'login' => redirect()->route('shop.customer.session.index'),
            'register' => redirect()->route('shop.customers.register.index'),
            default => redirect()->route('shop.berkay.phone.settings', $purpose),
        };
    }

    private function recentlyVerified(Request $request): bool
    {
        $verified = $request->session()->get('berkay.phone.verified');

        return $verified
            && $verified['phone'] === auth()->guard('customer')->user()->phone
            && $verified['at'] >= now()->subMinutes(10)->timestamp;
    }
}
