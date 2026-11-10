<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.seller.products') }}</x-slot>
    <section class="bk-admin">
        <div class="bk-toolbar"><div><h1>{{ trans('berkay::app.seller.products') }}</h1><p>{{ trans('berkay::app.seller.products-help') }}</p></div>
            @if (bouncer()->hasPermission('catalog.products.create'))<a class="bk-button" href="{{ route('admin.berkay.simple_product.create') }}">＋ {{ trans('berkay::app.seller.new-product') }}</a>@endif
        </div>
        @if (bouncer()->hasPermission('catalog.products.edit'))
            <a class="bk-button" href="{{ route('admin.berkay.catalog_table.index') }}">{{ trans('berkay::table.title') }}</a>
        @endif
        @include('berkay::admin.seller-feedback')
        <form class="bk-list-search" method="get"><label class="sr-only" for="query">{{ trans('berkay::app.seller.search-products') }}</label><input id="query" name="query" value="{{ request('query') }}" placeholder="{{ trans('berkay::app.seller.search-products') }}"><button type="submit">{{ trans('berkay::app.search') }}</button></form>
        <div class="bk-product-list">
            @forelse ($products as $product)
                <article>
                    @php $photo = $product->images->first(); @endphp
                    @if ($photo)<img src="{{ asset('storage/'.$photo->path) }}" width="72" height="72" alt="{{ $product->name }}" loading="lazy">@else<span class="bk-no-photo" aria-hidden="true">◇</span>@endif
                    <div class="bk-product-name"><h2>{{ $product->name ?: $product->sku }}</h2><small>{{ trans('berkay::app.seller.'.($product->status ? 'visible' : 'hidden')) }}</small></div>
                    <p>{{ core()->formatBasePrice($product->type === 'configurable' ? $product->variants->min('price') : $product->price) }}</p>
                    <p>{{ trans('berkay::app.seller.stock') }}: {{ $product->type === 'configurable' ? $product->variants->sum(fn ($variant) => $variant->inventories->sum('qty')) : $product->inventories->sum('qty') }}</p>
                    @if (bouncer()->hasPermission('catalog.products.edit'))<a class="bk-text-link" href="{{ route('admin.berkay.simple_product.edit', $product->id) }}">{{ trans('berkay::app.seller.edit-product') }}</a>@endif
                </article>
            @empty
                <p class="bk-empty">{{ trans('berkay::app.seller.no-products') }}</p>
            @endforelse
        </div>
        {{ $products->links() }}
        <details class="bk-advanced"><summary>{{ trans('berkay::app.seller.advanced-product') }}</summary><a href="{{ route('admin.catalog.products.index') }}">{{ trans('berkay::app.seller.advanced-catalog') }}</a></details>
    </section>
</x-admin::layouts>
