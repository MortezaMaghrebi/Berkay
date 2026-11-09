<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.integrations') }}</x-slot>
    <section class="bk-admin bk-editor">
        <h1>{{ trans('berkay::app.integrations') }}</h1>
        <p class="bk-notice">{{ trans('berkay::app.credentials-help') }}</p>
        @if (session('success'))<p role="status">{{ session('success') }}</p>@endif
        @if ($errors->any())
            <ul class="bk-error">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        <form method="post" action="{{ route('admin.berkay.integrations.update') }}" class="bk-form" autocomplete="off">
            @csrf @method('PUT')
            <h2>{{ trans('berkay::app.zarinpal') }}</h2>
            <p>{{ trans('berkay::app.credential-status') }}: {{ trans('berkay::app.'.($merchantConfigured ? 'configured' : 'not-configured')) }}</p>
            <label for="zarinpal_merchant_id">{{ trans('berkay::app.merchant-id') }}</label>
            <input id="zarinpal_merchant_id" name="zarinpal_merchant_id" type="password" autocomplete="new-password" dir="ltr" maxlength="36" value="" placeholder="{{ trans('berkay::app.keep-secret') }}">
            @foreach (['zarinpal_active' => ['enable', core()->getConfigData('sales.payment_methods.berkay_zarinpal.active')], 'zarinpal_sandbox' => ['sandbox', $sandbox]] as $field => [$label, $value])
                <label for="{{ $field }}">{{ trans('berkay::app.'.$label) }}</label>
                <select id="{{ $field }}" name="{{ $field }}">@foreach ([0, 1] as $option)<option value="{{ $option }}" @selected((int) old($field, $value) === $option)>{{ trans('berkay::app.'.($option ? 'yes' : 'no')) }}</option>@endforeach</select>
            @endforeach
            <h2>{{ trans('berkay::app.kavenegar') }}</h2>
            <p><a class="bk-text-link" href="{{ route('admin.berkay.kavenegar.index') }}">{{ trans('berkay::sms.panel') }}</a></p>

            <h2>{{ trans('berkay::app.torob') }}</h2>
            <p>{{ trans('berkay::app.torob-help') }}</p>
            <label for="torob_enabled">{{ trans('berkay::app.enable') }}</label>
            <select id="torob_enabled" name="torob_enabled">@foreach ([0, 1] as $option)<option value="{{ $option }}" @selected((int) old('torob_enabled', $torobEnabled) === $option)>{{ trans('berkay::app.'.($option ? 'yes' : 'no')) }}</option>@endforeach</select>
            @if (bouncer()->hasPermission('berkay.integrations.edit'))<button class="bk-button" type="submit">{{ trans('berkay::app.save') }}</button>@endif
        </form>
    </section>
</x-admin::layouts>
