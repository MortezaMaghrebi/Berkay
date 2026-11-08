@foreach ($rows as $key => ['products' => $products, 'isFallback' => $isFallback, 'suggestedIds' => $suggestedIds])
    @php
        $title = trans('berkay::collections.'.$key);
        $viewAllUrl = $isFallback ? route('shop.search.index') : route('shop.berkay.collections.index', $key);
        $viewAllLabel = trans($isFallback ? 'berkay::app.catalog' : 'berkay::collections.'.$key);
    @endphp
    <section class="bk-product-row bk-product-row--{{ $key }}" aria-labelledby="bk-row-{{ $key }}">
        <header class="bk-product-row-header">
            <div>
                <h2 id="bk-row-{{ $key }}">{{ $title }}</h2>
                @if ($isFallback)
                    <p class="bk-product-row-note">{{ trans('berkay::collections.random-selection') }}</p>
                @endif
            </div>
            <div class="bk-product-row-actions">
                <button type="button" data-bk-scroll="-1" aria-controls="bk-track-{{ $key }}" aria-label="{{ trans('berkay::collections.previous') }}" hidden>‹</button>
                <button type="button" data-bk-scroll="1" aria-controls="bk-track-{{ $key }}" aria-label="{{ trans('berkay::collections.next') }}" hidden>›</button>
                <a href="{{ $viewAllUrl }}" aria-label="{{ trans('berkay::app.view-all').' · '.$viewAllLabel }}">
                    {{ trans('berkay::app.view-all') }}
                </a>
            </div>
        </header>
        <ul class="bk-product-track bk-drag-scroll" id="bk-track-{{ $key }}" tabindex="0" aria-labelledby="bk-row-{{ $key }}">
            @foreach ($products as $product)
                <li>@include('berkay::shop.collections.card', [
                    'collection' => $key,
                    'isSuggestion' => ! $isFallback && in_array($product->id, $suggestedIds, true),
                ])</li>
            @endforeach
        </ul>
    </section>
@endforeach

@if ($recommendations->isNotEmpty())
    <section class="bk-home-recommendations" aria-labelledby="bk-recommendations-title">
        <header class="bk-product-row-header">
            <h2 id="bk-recommendations-title">{{ trans('berkay::collections.recommendations') }}</h2>
        </header>
        <ul class="bk-collection-grid">
            @foreach ($recommendations as $product)
                <li>@include('berkay::shop.collections.card', ['collection' => 'recommendations'])</li>
            @endforeach
        </ul>
    </section>
@endif
