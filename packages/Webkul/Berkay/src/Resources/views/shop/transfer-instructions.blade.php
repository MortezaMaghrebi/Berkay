@php
    $details = \Webkul\Payment\Facades\Payment::getAdditionalDetails('moneytransfer');
@endphp

<details class="bk-checkout-extra" id="bank-transfer">
    <summary class="bk-button">نمایش نحوهٔ واریز</summary>

    @if (filled($details['value'] ?? null))
        <p class="whitespace-pre-line">{{ $details['value'] }}</p>
    @else
        <p>راهنمای واریز هنوز ثبت نشده است. برای دریافت اطلاعات پرداخت با فروشگاه تماس بگیرید.</p>
        <a href="{{ route('shop.home.contact_us') }}">{{ trans('berkay::app.contact') }}</a>
    @endif
</details>
