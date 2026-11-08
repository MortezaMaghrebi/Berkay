<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.seller.'.($product ? 'edit-product' : 'new-product')) }}</x-slot>
    <section class="bk-admin bk-seller-editor">
        <div class="bk-toolbar">
            <div><p class="bk-eyebrow">{{ trans('berkay::app.seller.your-store') }}</p><h1>{{ trans('berkay::app.seller.'.($product ? 'edit-product' : 'new-product')) }}</h1></div>
            <a href="{{ route('admin.berkay.simple_product.index') }}">{{ trans('berkay::app.back') }}</a>
        </div>
        @if ($product && $product->url_key)
            <a class="bk-button" href="{{ route('shop.product_or_category.index', $product->url_key) }}" target="_blank" rel="noopener">{{ trans('berkay::app.product-editor.view-site') }}</a>
        @endif
        <p class="bk-help">{{ trans('berkay::app.seller.product-intro') }}</p>
        @include('berkay::admin.seller-feedback')
        <form class="bk-form" method="post" enctype="multipart/form-data" action="{{ $product ? route('admin.berkay.simple_product.update', $product->id) : route('admin.berkay.simple_product.store') }}">
            @csrf
            @if ($product) @method('PUT') @endif
            <div class="bk-form-section">
                <h2>{{ trans('berkay::app.seller.product-basics') }}</h2>
                <label for="name">{{ trans('berkay::app.seller.name') }}</label>
                <input id="name" name="name" value="{{ old('name', $product?->name) }}" required maxlength="200" placeholder="{{ trans('berkay::app.seller.name-example') }}">
                <div class="bk-form-pair">
                    <div><label for="price">{{ trans('berkay::app.seller.price') }}</label><input id="price" name="price" inputmode="decimal" value="{{ old('price', $product ? number_format((float) $product->price, 0, '.', ',') : null) }}" required><small>{{ trans('berkay::app.seller.price-help') }}</small></div>
                    @php
                        $sourceId = core()->getCurrentChannel()->inventory_sources->first()?->id;
                        $stock = $product?->inventories->where('inventory_source_id', $sourceId)->sum('qty') ?? 1;
                    @endphp
                    <div><label for="quantity">{{ trans('berkay::app.seller.quantity') }}</label><input id="quantity" name="quantity" inputmode="numeric" value="{{ old('quantity', $stock) }}" required><small>{{ trans('berkay::app.seller.quantity-help') }}</small></div>
                </div>
                <label for="category_id">{{ trans('berkay::app.seller.category_id') }}</label>
                <select id="category_id" name="category_id" required>
                    <option value="">{{ trans('berkay::app.seller.choose-category') }}</option>
                    @foreach ($categories as $node)
                        @include('berkay::admin.category-options', ['node' => $node, 'depth' => 0, 'selected' => old('category_id', $product?->categories->first()?->id), 'excluded' => null])
                    @endforeach
                </select>
                @if (bouncer()->hasPermission('catalog.categories.create'))
                    <a class="bk-text-link" href="{{ route('admin.berkay.simple_category.create') }}" target="_blank">{{ trans('berkay::app.seller.missing-category') }}</a>
                @endif
            </div>
            <div class="bk-form-section">
                <h2>{{ trans('berkay::app.seller.images') }}</h2>
                @if ($product && $product->images->isNotEmpty())
                    <div class="bk-existing-images">@foreach ($product->images as $image)<img src="{{ asset('storage/'.$image->path) }}" width="88" height="88" alt="{{ $product->name }}">@endforeach</div>
                    <p class="bk-help">{{ trans('berkay::app.seller.keep-images') }}</p>
                @endif
                <label class="bk-upload" for="images">{{ trans('berkay::app.seller.choose-images') }}</label>
                <input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @required(!$product)>
                <small>{{ trans('berkay::app.seller.image-help') }}</small>
                <label for="description">{{ trans('berkay::app.seller.description') }}</label>
                <textarea id="description" name="description" rows="5" maxlength="20000">{{ old('description', strip_tags($product?->description ?? '')) }}</textarea>
                <small>{{ trans('berkay::app.seller.description-help') }}</small>
            </div>
            <details class="bk-advanced" @if(old('featured', $product?->featured) || old('special_price', $product?->special_price) || $errors->has('special_price')) open @endif>
                <summary>{{ trans('berkay::collections.featured') }} / {{ trans('berkay::collections.discounted') }}</summary>
                <label for="featured">{{ trans('berkay::collections.featured-product') }}</label>
                <select id="featured" name="featured">
                    <option value="0" @selected(! old('featured', $product?->featured))>@lang('berkay::collections.no')</option>
                    <option value="1" @selected(old('featured', $product?->featured))>@lang('berkay::collections.yes')</option>
                </select>
                <label for="special_price">{{ trans('berkay::collections.special-price') }}</label>
                <input id="special_price" name="special_price" inputmode="decimal" value="{{ old('special_price', $product?->special_price) }}" aria-describedby="bk-sale-help">
                <small id="bk-sale-help">{{ trans('berkay::collections.special-price-help') }}</small>
            </details>
            <label for="status">{{ trans('berkay::app.seller.status') }}</label>
            <select id="status" name="status">
                <option value="1" @selected((int) old('status', $product?->status ?? 1) === 1)>{{ trans('berkay::app.seller.visible') }}</option>
                <option value="0" @selected((int) old('status', $product?->status ?? 1) === 0)>{{ trans('berkay::app.seller.hidden') }}</option>
            </select>
            <button class="bk-button" type="submit">{{ trans('berkay::app.seller.save-product') }}</button>
            <details class="bk-advanced">
                <summary>{{ trans('berkay::app.seller.advanced-product') }}</summary>
                <p>{{ trans('berkay::app.seller.advanced-product-help') }}</p>
                <a href="{{ $product ? route('admin.catalog.products.edit', $product->id) : route('admin.catalog.products.index') }}">{{ trans('berkay::app.seller.open-advanced') }}</a>
            </details>
        </form>
    </section>
</x-admin::layouts>
