@php
    $currentDiscount = \Webkul\Berkay\Support\ProductDiscount::percent((float) $product?->price, (float) $product?->special_price);
@endphp

<label for="discount_percent">{{ trans('berkay::table.discount') }}</label>
<input
    id="discount_percent"
    name="discount_percent"
    type="number"
    inputmode="decimal"
    min="0"
    max="99.99"
    step="0.01"
    value="{{ old('discount_percent') }}"
    aria-describedby="bk-sale-help"
>
<small id="bk-sale-help">
    @if ($currentDiscount !== null)
        {{ trans('berkay::collections.current-discount', ['percent' => $currentDiscount]) }}.
    @endif
    {{ trans('berkay::collections.discount-percent-help') }}
</small>
