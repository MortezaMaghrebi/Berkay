@php
    $labels = ['terminal_id' => 'شمارهٔ پایانه', 'username' => 'نام کاربری', 'password' => 'رمز پذیرنده', 'acceptor_id' => 'شمارهٔ پذیرنده', 'public_key' => 'کلید عمومی (PEM)', 'client_id' => 'Client ID', 'client_secret' => 'Client Secret'];
@endphp

@foreach ($bankStates as $bank => $state)
    <form method="post" id="payment-{{ $bank }}" action="{{ route('admin.berkay.integrations.payment.update', $bank) }}" class="bk-form bk-integration-card" autocomplete="off">
        @csrf
        @method('PUT')
        <header>
            <h2>{{ config('payment_methods.berkay_'.$bank.'.title') }}</h2>
            <p>{{ $state['ready'] ? ($state['enabled'] ? 'فعال' : 'غیرفعال') : 'اطلاعات پذیرنده یا پیش‌نیاز سرور آماده نیست.' }}</p>
            @if ($bank === 'mellat' && ! extension_loaded('soap'))
                <p>برای فعال شدن درگاه ملت، افزونهٔ PHP SOAP باید روی سرور فعال باشد.</p>
            @endif
        </header>
        <fieldset @disabled(! $canEdit)>
            <details class="bk-integration-help" @if ($errors->getBag($bank)->any()) open @endif>
                <summary>ثبت یا تغییر اطلاعات پذیرنده</summary>
                <p>برای حفظ اطلاعات ذخیره‌شده، کادرهای مربوط را خالی بگذارید. درگاه بعد از تکمیل اطلاعات پذیرنده در تسویه‌حساب در دسترس خواهد بود.</p>
            @foreach ($state['saved'] as $key => $saved)
                <label for="{{ $bank }}-{{ $key }}">{{ $labels[$key] }}</label>
                @if ($key === 'public_key')
                    <textarea id="{{ $bank }}-{{ $key }}" name="{{ $key }}" dir="ltr" maxlength="4096" rows="4" placeholder="{{ $saved ? trans('berkay::app.keep-secret') : '' }}"></textarea>
                @else
                    <input id="{{ $bank }}-{{ $key }}" name="{{ $key }}" type="password" autocomplete="new-password" dir="ltr" maxlength="128" placeholder="{{ $saved ? trans('berkay::app.keep-secret') : '' }}">
                @endif
                @error($key, $bank)
                    <p class="bk-error" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
            </details>
            <label for="{{ $bank }}-active">نمایش درگاه در تسویه‌حساب</label>
            <select id="{{ $bank }}-active" name="enabled">
                <option value="0" @selected(! $state['enabled'])>غیرفعال</option>
                <option value="1" @selected($state['enabled'])>فعال</option>
            </select>
            @error('enabled', $bank)
                <p class="bk-error" role="alert">{{ $message }}</p>
            @enderror
        </fieldset>
        @if ($canEdit)
            <button class="bk-button" type="submit">{{ trans('berkay::app.save') }}</button>
        @endif
    </form>
@endforeach

<form method="post" id="payment-transfer" action="{{ route('admin.berkay.integrations.payment.update', 'moneytransfer') }}" class="bk-form bk-integration-card">
    @csrf
    @method('PUT')
    <header>
        <h2>واریز بانکی با تأیید فروشگاه</h2>
        <p>این متن برای سفارش‌های آمادهٔ پرداخت به مشتری نمایش داده می‌شود.</p>
    </header>
    <fieldset @disabled(! $canEdit)>
        <label for="transfer-instructions">راهنمای واریز</label>
        <textarea id="transfer-instructions" name="instructions" rows="6" maxlength="1500">{{ $transferInstructions }}</textarea>
        @error('instructions', 'moneytransfer')
            <p class="bk-error" role="alert">{{ $message }}</p>
        @enderror
    </fieldset>
    @if ($canEdit)
        <button class="bk-button" type="submit">{{ trans('berkay::app.save') }}</button>
    @endif
</form>
