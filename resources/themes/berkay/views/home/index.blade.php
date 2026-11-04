@inject('categoryRepository', \Webkul\Category\Repositories\CategoryRepository::class)
@php
    $channel = core()->getCurrentChannel();
    $heroTitle = core()->getConfigData('berkay.store.identity.hero_title') ?: trans('berkay::app.hero-default');
@endphp
@push('meta')
    <meta name="description" content="{{ $channel->home_seo['meta_description'] ?? trans('berkay::app.hero-subtitle') }}">
    <link rel="canonical" href="{{ route('shop.home.index') }}">
@endpush
<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ ($channel->home_seo['meta_title'] ?? '') ?: trans('berkay::app.brand') }}</x-slot>
    <div class="bk-home">
        <section class="bk-hero">
            <div class="bk-hero-copy">
                <h1>{{ $heroTitle }}</h1>
                <p>{{ core()->getConfigData('berkay.store.identity.hero_description') ?: trans('berkay::app.hero-subtitle') }}</p>
                <a class="bk-button" href="{{ route('shop.search.index') }}">{{ trans('berkay::app.catalog') }} <span aria-hidden="true">←</span></a>
            </div>
            <div class="bk-hero-photo"><img src="{{ asset('berkay/hero-v2.webp') }}" width="900" height="900" fetchpriority="high" alt="{{ trans('berkay::app.tagline-default') }}"></div>
        </section>
        <section class="bk-collection-intro">
            <h2>{{ trans('berkay::app.categories') }}</h2>
            <a href="{{ route('shop.search.index') }}">{{ trans('berkay::app.view-all') }} ←</a>
        </section>
        <nav class="bk-category-strip" aria-label="{{ trans('berkay::app.categories') }}">
            @foreach ($categoryRepository->getVisibleCategoryTree($channel->root_category_id) as $category)
                <a href="{{ $category->url }}">
                    <span class="bk-category-art">
                        @if ($category->logo_url)
                            <img src="{{ $category->logo_url }}" alt="" width="90" height="90" loading="lazy">
                        @else
                            <svg viewBox="0 0 64 64" width="54" height="54" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @switch($category->slug)
                                    @case('wallets')
                                        <rect x="10" y="18" width="44" height="32" rx="5"/><path d="M14 18v-5h34v5M54 29H39v12h15"/><circle cx="44" cy="35" r="1"/>
                                        @break
                                    @case('backpacks')
                                        <path d="M14 52V25a18 18 0 0 1 36 0v27zM25 9V6h14v3"/><rect x="21" y="33" width="22" height="13" rx="3"/><path d="M21 25h22M19 23v5m26-5v5"/>
                                        @break
                                    @case('accessories')
                                        <circle cx="25" cy="20" r="10"/><path d="m31 28 17 19-7 7-18-23M40 45l5-5"/>
                                        @break
                                    @case('men-bags')
                                        <rect x="9" y="23" width="46" height="29" rx="4"/><path d="M23 23v-9h18v9M9 33h46M22 23v29m20-29v29"/><path d="M29 31h6v7h-6z" fill="var(--bk-paper)"/>
                                        @break
                                    @default
                                        <path d="m13 25-4 29h46l-4-29zM23 26V16a9 9 0 0 1 18 0v10M20 25v6m24-6v6"/>
                                @endswitch
                            </svg>
                        @endif
                    </span>
                    <span>{{ $category->name }}</span>
                </a>
            @endforeach
        </nav>
        <x-shop::products.carousel
            :title="trans('berkay::app.featured')"
            :src="route('shop.api.products.index', ['featured' => 1])"
            :navigation-link="route('shop.search.index', ['featured' => 1])"
        />
        @if (core()->getConfigData('berkay.store.identity.custom_orders'))
            <section class="bk-bespoke">
                <img src="{{ asset('berkay/wallet-sample.webp') }}" width="750" height="800" loading="lazy" alt="{{ trans('berkay::app.custom-orders') }}">
                <div><h2>{{ trans('berkay::app.custom-title') }}</h2><p>{{ trans('berkay::app.custom-description') }}</p><a class="bk-button" href="{{ route('shop.berkay.custom_order.create') }}">{{ trans('berkay::app.send') }} ←</a></div>
            </section>
        @endif
        <x-shop::products.carousel
            :title="trans('berkay::app.new-products')"
            :src="route('shop.api.products.index', ['new' => 1])"
            :navigation-link="route('shop.search.index', ['new' => 1])"
        />
        <section class="bk-magazine-promo">
            <h2>{{ trans('berkay::app.magazine') }}</h2>
            <p>{{ trans('berkay::app.magazine-description') }}</p>
            <a href="{{ route('shop.berkay.magazine.index') }}">{{ trans('berkay::app.read') }} ←</a>
        </section>
    </div>
</x-shop::layouts>
