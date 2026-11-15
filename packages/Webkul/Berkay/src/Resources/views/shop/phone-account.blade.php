@php
    $settings = in_array($purpose, ['change', 'delete'], true);
    $mobileLogin = (bool) core()->getConfigData('berkay.store.authentication.mobile_login');
    $registrationEnabled = (bool) core()->getConfigData('berkay.store.authentication.registration');
    $passwordTab = $purpose === 'login' && (! $mobileLogin || request('tab') === 'password');
    $heading = $passwordTab
        ? trans('berkay::app.authentication.password')
        : trans('berkay::sms.'.($registration ? 'complete-profile' : ($challenge ? 'confirm-phone' : $purpose)));
    $pageRoute = match ($purpose) {
        'login' => 'shop.customer.session.index',
        'register' => 'shop.customers.register.index',
        default => 'shop.berkay.phone.settings',
    };
    $params = $settings ? ['purpose' => $purpose] : [];
    $sendRoute = match ($purpose) {
        'login' => 'shop.customer.session.create',
        'register' => 'shop.customers.register.store',
        default => 'shop.berkay.phone.settings.send',
    };
    $verifyRoute = $settings ? 'shop.berkay.phone.settings.verify' : 'shop.berkay.phone.'.$purpose.'.verify';
@endphp
<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ $heading }}</x-slot>
    <dialog open class="bk-section bk-phone-account bk-auth-dialog" aria-labelledby="bk-auth-title">
        <header class="bk-phone-heading">
            <img src="{{ asset('berkay/brand-logo.png') }}" alt="" width="64" height="64">
            <h1 id="bk-auth-title">{{ $heading }}</h1>
        </header>
        @if ($purpose === 'login' && $mobileLogin)
            <nav class="bk-auth-tabs" aria-label="{{ trans('berkay::app.authentication.settings') }}">
                <a href="{{ route('shop.customer.session.index', ['tab' => 'mobile']) }}" @if (! $passwordTab) aria-current="page" @endif>
                    {{ trans('berkay::app.authentication.mobile') }}
                </a>
                <a href="{{ route('shop.customer.session.index', ['tab' => 'password']) }}" @if ($passwordTab) aria-current="page" @endif>
                    {{ trans('berkay::app.authentication.password') }}
                </a>
            </nav>
        @endif

        @foreach (['success', 'info', 'warning', 'error'] as $type)
            @if (session($type))
                <p class="bk-notice" role="status">{{ session($type) }}</p>
            @endif
        @endforeach
        @if ($errors->any())
            <ul class="bk-error" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if ($passwordTab)
            <form method="post" class="bk-form" action="{{ route('shop.customer.session.password') }}">
                @csrf
                <label for="login-identity">{{ trans('berkay::app.authentication.identity') }}</label>
                <input id="login-identity" name="email" dir="ltr" autocomplete="username" value="{{ old('email') }}" maxlength="255" required>
                <label for="login-password">{{ trans('berkay::app.authentication.password-field') }}</label>
                <input id="login-password" type="password" name="password" dir="ltr" autocomplete="current-password" maxlength="255" required>
                @if (core()->getConfigData('customer.captcha.credentials.status'))
                    {!! \Webkul\Customer\Facades\Captcha::render() !!}
                @endif
                <button type="submit" class="bk-button">{{ trans('shop::app.customers.login-form.button-title') }}</button>
            </form>
        @elseif ($registration)
            <p>{{ trans('berkay::sms.verified-profile') }} <bdi>{{ $registration['phone'] }}</bdi></p>
            <form method="post" class="bk-form" action="{{ route($verifyRoute, $params) }}">
                @csrf
                <label for="first_name">{{ trans('berkay::app.users.first-name') }}</label>
                <input id="first_name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}" maxlength="100" required>
                <label for="last_name">{{ trans('berkay::app.users.last-name') }}</label>
                <input id="last_name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}" maxlength="100" required>
                <button class="bk-button" type="submit">{{ trans('berkay::sms.complete-profile') }}</button>
            </form>
            <a href="{{ route($pageRoute, $params + ['start' => 1]) }}">{{ trans('berkay::sms.start-over') }}</a>
        @elseif ($challenge)
            <p>{{ trans('berkay::sms.sent', ['phone' => $challenge['phone']]) }}</p>
            <form method="post" class="bk-form" action="{{ route($verifyRoute, $params) }}">
                @csrf
                @php($length = $challenge['length'] ?? 6)
                <div class="bk-otp" data-otp>
                    <div data-otp-fallback>
                        <label for="code">{{ trans('berkay::sms.code') }}</label>
                        <input id="code" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" minlength="{{ $length }}" maxlength="{{ $length }}" autofocus required>
                    </div>
                    <fieldset hidden dir="ltr">
                        <legend class="sr-only">{{ trans('berkay::sms.code') }}</legend>
                        @for ($digit = 1; $digit <= $length; $digit++)
                            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="{{ $digit === 1 ? 'one-time-code' : 'off' }}"
                                aria-label="{{ trans('berkay::sms.code-digit', ['position' => $digit, 'length' => $length]) }}" disabled>
                        @endfor
                    </fieldset>
                </div>
                <button class="bk-button" type="submit">{{ trans('berkay::sms.verify') }}</button>
            </form>
            <a href="{{ route($pageRoute, $params + ['start' => 1]) }}">{{ trans('berkay::sms.start-over') }}</a>
        @else
            <p>{{ trans('berkay::sms.'.$purpose.'-help') }}</p>
            <form method="post" class="bk-form" action="{{ route($sendRoute, $params) }}">
                @csrf
                @if ($purpose !== 'delete')
                    <label for="phone">{{ trans('berkay::sms.phone') }}</label>
                    <input type="tel" id="phone" name="phone" dir="ltr" autocomplete="tel" inputmode="tel" placeholder="09123456789" maxlength="32" value="{{ old('phone') }}" required>
                @endif
                <button class="bk-button" type="submit">{{ trans('berkay::sms.send-code') }}</button>
            </form>
        @endif

        <nav aria-label="{{ trans('berkay::sms.account-options') }}">
            @if ($settings)
                <a href="{{ route('shop.customers.account.profile.index') }}">{{ trans('berkay::app.back') }}</a>
            @else
                @if ($purpose === 'login' && $registrationEnabled)
                    <a href="{{ route('shop.customers.register.index') }}">{{ trans('berkay::sms.register') }}</a>
                @elseif ($purpose === 'register')
                    <a href="{{ route('shop.customer.session.index') }}">{{ trans('berkay::sms.login') }}</a>
                @endif
                @if ($passwordTab)
                    <a href="{{ route('admin.forget_password.create') }}">{{ trans('berkay::app.authentication.staff-recovery') }}</a>
                @endif
            @endif
        </nav>
    </dialog>
    @if ($challenge)
        @pushOnce('scripts')
            <script type="module" src="{{ asset('berkay/phone-code.js') }}?v={{ filemtime(public_path('berkay/phone-code.js')) }}"></script>
        @endPushOnce
    @endif
</x-shop::layouts>
