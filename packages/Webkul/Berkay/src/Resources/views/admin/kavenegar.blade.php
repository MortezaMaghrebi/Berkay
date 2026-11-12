<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::sms.panel') }}</x-slot>
    <section class="bk-admin bk-editor bk-sms-panel">
        <div class="bk-toolbar">
            <h1>{{ trans('berkay::sms.panel') }}</h1>
            <a href="{{ route('admin.berkay.integrations.index') }}">{{ trans('berkay::app.back') }}</a>
        </div>
        @include('berkay::admin.seller-feedback')
        <p class="bk-notice">{{ trans('berkay::sms.panel-help') }}</p>
        <form method="post" action="{{ route('admin.berkay.kavenegar.update') }}" class="bk-form" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="bk-form-section">
                <h2>{{ trans('berkay::sms.connection') }}</h2>
                <p>{{ trans('berkay::app.'.($configured ? 'configured' : 'not-configured')) }}</p>
                <label for="api_key">{{ trans('berkay::app.api-key') }}</label>
                <input id="api_key" name="api_key" type="password" autocomplete="new-password" dir="ltr" maxlength="512" placeholder="{{ trans('berkay::app.keep-secret') }}">
                <label for="enabled">{{ trans('berkay::app.enable-sms') }}</label>
                <select id="enabled" name="enabled">
                    <option value="0" @selected(!old('enabled', $enabled))>{{ trans('berkay::app.no') }}</option>
                    <option value="1" @selected(old('enabled', $enabled))>{{ trans('berkay::app.yes') }}</option>
                </select>
            </div>
            @foreach (['account' => ['login', 'register', 'change', 'delete'], 'orders' => $orderEvents, 'extras' => ['coupon', 'test']] as $group => $events)
                <div class="bk-form-section">
                    <h2>{{ trans('berkay::sms.group-'.$group) }}</h2>
                    <div class="bk-sms-template-grid">
                        @foreach ($events as $event)
                            @include('berkay::admin.kavenegar-template-card')
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if (bouncer()->hasPermission('berkay.integrations.edit'))
                <button type="submit">{{ trans('berkay::app.save') }}</button>
            @endif
        </form>
        @if (bouncer()->hasPermission('berkay.integrations.edit'))
            <form method="post" action="{{ route('admin.berkay.kavenegar.send') }}" class="bk-form bk-form-section">
                @csrf
                <h2>{{ trans('berkay::sms.manual') }}</h2>
                <label for="message_type">{{ trans('berkay::sms.message-type') }}</label>
                <select id="message_type" name="message_type">
                    <option value="test" @selected(old('message_type') === 'test')>{{ trans('berkay::sms.template-test') }}</option>
                    <option value="coupon" @selected(old('message_type') === 'coupon')>{{ trans('berkay::sms.template-coupon') }}</option>
                </select>
                <label for="recipient">{{ trans('berkay::sms.phone') }}</label>
                <input id="recipient" name="recipient" type="tel" value="{{ old('recipient') }}" dir="ltr" maxlength="32" required>
                <label for="coupon">{{ trans('berkay::sms.coupon-code') }}</label>
                <input id="coupon" name="coupon" value="{{ old('coupon') }}" dir="ltr" maxlength="100">
                <p class="bk-help">{{ trans('berkay::sms.manual-help') }}</p>
                <button type="submit">{{ trans('berkay::sms.send-message') }}</button>
            </form>
        @endif
    </section>
    @pushOnce('scripts')
        <script type="module" src="{{ asset('berkay/sms-settings.js') }}?v={{ filemtime(public_path('berkay/sms-settings.js')) }}"></script>
    @endPushOnce
</x-admin::layouts>
