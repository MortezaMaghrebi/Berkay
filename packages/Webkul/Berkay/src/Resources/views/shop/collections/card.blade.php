@php
    $image = product_image()->getProductBaseImage($product);
    $type = $product->getTypeInstance();
    $salePrice = $type->getMinimalPrice();
    $regularPrice = $type->getRegularMinimalPrice();
    $price = core()->formatPrice($salePrice);
    $hasDiscount = $regularPrice > 0 && $salePrice !== null && $salePrice < $regularPrice;
    $discount = $hasDiscount ? 100 * ($regularPrice - $salePrice) / $regularPrice : null;
@endphp
<a class="bk-collection-card" href="{{ route('shop.product_or_category.index', $product->url_key) }}">
    <span class="bk-collection-card-media">
        <img src="{{ $image['medium_image_url'] }}" alt="" width="360" height="360" loading="lazy">
        @if ($hasDiscount)
            <span class="bk-collection-sale-price">{{ \Illuminate\Support\Number::percentage($discount, maxPrecision: 1, locale: app()->getLocale()) }}</span>
        @endif
    </span>
    <span class="bk-collection-card-name">{{ $product->name }}</span>
    @if ($isSuggestion ?? false)
        <small class="bk-collection-card-note">{{ trans('berkay::collections.random-selection') }}</small>
    @endif
    @if ($collection === 'discounted' && $product->type === 'configurable' && ! $hasDiscount)
        <small>{{ trans('berkay::collections.variant-discount-label') }}</small>
    @endif
    <span class="bk-collection-card-price">
        @if ($hasDiscount)
            <del>{{ core()->formatPrice($regularPrice) }}</del>
        @endif
        <strong>{{ $price }}</strong>
    </span>
</a>
