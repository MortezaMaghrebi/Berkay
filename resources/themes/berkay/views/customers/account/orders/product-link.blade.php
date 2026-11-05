@if ($item?->product?->url_key)
    <a href="{{ route('shop.product_or_category.index', $item->product->url_key) }}" class="bk-order-product-link">
        {{ $item->name }}
    </a>
@else
    {{ $item->name }}
@endif
