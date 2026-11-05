@inject('categoryRepository', \Webkul\Category\Repositories\CategoryRepository::class)
@php
    $navigationCategories = $categoryRepository->getVisibleCategoryTree(core()->getCurrentChannel()->root_category_id);
    $brandName = core()->getConfigData('berkay.store.identity.name') ?: trans('berkay::app.brand');
@endphp
<header class="bk-header">
    <div class="bk-header-main">
        <a class="bk-brand" href="{{ route('shop.home.index') }}" aria-label="{{ $brandName }}">
            <svg width="38" height="44" viewBox="0 0 38 44" aria-hidden="true"><path d="M7 2h24l5 9v28l-17 4L2 39V11z" fill="currentColor"/><path d="M13 13v20m0-20h7a5 5 0 0 1 0 10h-7m0 0h8a5 5 0 0 1 0 10h-8" fill="none" stroke="#f4eee5" stroke-width="2"/><circle cx="19" cy="7" r="2" fill="#f4eee5"/></svg>
            <span><strong>{{ $brandName }}</strong><small>{{ core()->getConfigData('berkay.store.identity.tagline') ?: trans('berkay::app.tagline-default') }}</small></span>
        </a>
        <form class="bk-search" action="{{ route('shop.search.index') }}" role="search">
            <label class="sr-only" for="bk-search">{{ trans('berkay::app.search') }}</label>
            <input type="search" id="bk-search" name="query" value="{{ request('query') }}" placeholder="{{ trans('berkay::app.search') }}">
            <button type="submit" aria-label="{{ trans('berkay::app.search') }}"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button>
        </form>
        <div class="bk-account-actions">
            <a href="{{ auth()->guard('customer')->check() ? route('shop.customers.account.profile.index') : route('shop.customer.session.create') }}">{{ trans('berkay::app.account') }}</a>
            @include('shop::checkout.cart.mini-cart')
        </div>
    </div>
    <nav class="bk-navigation" aria-label="{{ trans('berkay::app.catalog') }}">
        <details class="bk-category-menu">
            <summary>{{ trans('berkay::app.seller.catalog-menu') }}</summary>
            <div class="bk-mega-menu">
                @forelse ($navigationCategories as $category)
                    <div>
                        <a href="{{ $category->url }}">{{ $category->name }}</a>
                        @foreach ($category->children as $child)
                            <a class="bk-subcategory" href="{{ $child->url }}">{{ $child->name }}</a>
                        @endforeach
                    </div>
                @empty
                    <a href="{{ route('shop.search.index') }}">{{ trans('berkay::app.all-products') }}</a>
                @endforelse
            </div>
        </details>
        <a href="{{ route('shop.search.index') }}">{{ trans('berkay::app.all-products') }}</a>
        <a href="{{ route('shop.berkay.magazine.index') }}">{{ trans('berkay::app.magazine') }}</a>
        @if (core()->getConfigData('berkay.store.identity.custom_orders'))
            <a href="{{ route('shop.berkay.custom_order.create') }}">{{ trans('berkay::app.custom-orders') }}</a>
        @endif
        <a href="{{ route('shop.home.contact_us') }}">{{ trans('berkay::app.contact') }}</a>
    </nav>
</header>
