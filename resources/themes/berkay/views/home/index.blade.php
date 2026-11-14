@inject('categoryRepository', \Webkul\Category\Repositories\CategoryRepository::class)
@php
    $channel = core()->getCurrentChannel();
    $heroTitle = core()->getConfigData('berkay.store.identity.hero_title') ?: trans('berkay::app.hero-default');

    // The illustrated sprite is arranged in four columns, in row order.
    $categoryIcons = [
        'women-bags' => 0,
        'men-bags' => 1,
        'wallets' => 2,
        'backpacks' => 3,
        'accessories' => 4,
        'collection-01m6rcw8xgr3wsz710z0089kjx' => 0, // Handmade bags
        'collection-01m6rcwekh37wr1k9vk3nc6nrs' => 4, // Handmade accessories
        'collection-01m6rcx2hw9ay2gceqbc79n5mh' => 5, // Travel bags
        'collection-01m6rcx9c8881wfng16a4wr9k4' => 8, // Car accessories
        'collection-01m6rcx9metvcxggx8pxw23p2s' => 7, // Power bank accessories
        'collection-01m6rcxaqrh3kqk5051ky7wgsv' => 6, // Cosmetics cases
        'collection-01m6rcxfeb50h45zzdt10m9n6b' => 2, // Men's accessories
        'collection-01m6rcxhf6805p01nq2mpckmmg' => 0, // Girls' bags
        'collection-01m6rcy539629ftn2wdr42vzrm' => 5, // Sports bags
        'collection-01m6rcy7w7wyr51gcxdv44ptsy' => 11, // School supplies
        'collection-01m6rcyhh7ngkr53tv9qnw8c2b' => 6, // Girls' accessories
        'collection-01m6rcym2tm9w6yfr0j0jajbp1' => 4, // Key rings
        'collection-01m6rcynqpd96bat1mk5kjaz15' => 12, // Mouse pads
        'collection-01m6rcyxtg15y4ddddka54dqbd' => 6, // Clothing organizers
        'collection-01m6rcz060s2dvaszygnpmtsqx' => 8, // Car alarms
        'collection-01m6rcz3bqrjpx3s38j8chw3b2' => 13, // Workwear
        'collection-01m6rczfcja1r2ahhg6v3wtx51' => 9, // Phone and tablet cases
        'collection-01m6rczfk9djagx0z5k3nnx5b1' => 10, // Gardening tools
        'collection-01m6rczr0a5ebjq2dv01qcsx4p' => 15, // Prayer cards
        'collection-01m6rd00g9w73adrnb60s82dwm' => 14, // Mirrors and frames
    ];
@endphp
@push('meta')
    <meta name="description" content="{{ ($channel->home_seo['meta_description'] ?? '') ?: trans('berkay::app.home-meta-description') }}">
    <meta name="keywords" content="{{ ($channel->home_seo['meta_keywords'] ?? '') ?: trans('berkay::app.home-meta-keywords') }}">
    <link rel="canonical" href="{{ route('shop.home.index') }}">
@endpush
<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ ($channel->home_seo['meta_title'] ?? '') ?: trans('berkay::app.home-meta-title') }}</x-slot>
    <div class="bk-home">
        <section class="bk-hero" aria-labelledby="bk-hero-title">
            <div class="bk-hero-copy"><p class="bk-hero-signature">{{ core()->getConfigData('berkay.store.identity.name') ?: trans('berkay::app.brand') }}</p>
                <h1 id="bk-hero-title">{{ $heroTitle }}</h1>
                <p>{{ core()->getConfigData('berkay.store.identity.hero_description') ?: trans('berkay::app.hero-subtitle') }}</p>
                <a class="bk-button" href="{{ route('shop.search.index') }}">{{ trans('berkay::app.catalog') }} <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg></a>
            </div>
            <div class="bk-hero-photo"><img src="{{ asset('berkay/hero-v2.webp') }}" width="900" height="900" fetchpriority="high" alt="{{ trans('berkay::app.tagline-default') }}"></div>
        </section>
        <div data-campaign-banner="{{ route('shop.berkay.campaign.banner') }}"></div>
        @pushOnce('scripts')
            <script type="module" src="{{ asset('berkay/campaign.js') }}?v={{ filemtime(public_path('berkay/campaign.js')) }}"></script>
        @endPushOnce
        <section class="bk-collection-intro">
            <h2>{{ trans('berkay::app.categories') }}</h2>
            <a href="{{ route('shop.search.index') }}">{{ trans('berkay::app.view-all') }} ←</a>
        </section>
        <nav class="bk-category-strip bk-drag-scroll" tabindex="0" aria-label="{{ trans('berkay::app.categories') }}">
            @foreach ($categoryRepository->getVisibleCategoryTree($channel->root_category_id) as $category)
                <a href="{{ $category->url }}">
                    <span class="bk-category-art">
                        @if ($category->logo_url)
                            <img src="{{ $category->logo_url }}" alt="" width="90" height="90" loading="lazy">
                        @else
                            @php $icon = $categoryIcons[$category->slug] ?? 4; @endphp
                            <span class="bk-category-illustration" style="--bk-icon-column: {{ $icon % 4 }}; --bk-icon-row: {{ intdiv($icon, 4) }}" aria-hidden="true"></span>
                        @endif
                    </span>
                    <span>{{ $category->name }}</span>
                </a>
            @endforeach
        </nav>
        @include('berkay::shop.leather-guide-card')
        <div data-bk-collections="{{ route('shop.berkay.collections.rows') }}"></div>
        @if (core()->getConfigData('berkay.store.identity.custom_orders'))
            <section class="bk-bespoke">
                <img src="{{ asset('berkay/wallet-sample.webp') }}" width="750" height="800" loading="lazy" alt="{{ trans('berkay::app.custom-orders') }}">
                <div><h2>{{ trans('berkay::app.custom-title') }}</h2><p>{{ trans('berkay::app.custom-description') }}</p><a class="bk-button" href="{{ route('shop.berkay.custom_order.create') }}">{{ trans('berkay::app.send') }} ←</a></div>
            </section>
        @endif
        <section class="bk-magazine-promo"><div class="bk-magazine-symbol" aria-hidden="true"><svg width="72" height="72" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M9 12h20l5 4 5-4h16v39H39l-5 4-5-4H9zM34 16v39M16 22h10m-10 8h10m-10 8h10m16-16h7m-7 8h7m-7 8h7"/></svg></div>
            <h2>{{ trans('berkay::app.magazine') }}</h2>
            <p>{{ trans('berkay::app.magazine-description') }}</p>
            <a href="{{ route('shop.berkay.magazine.index') }}">{{ trans('berkay::app.read') }} ←</a>
        </section>
        @include('berkay::shop.home-guides')
    </div>
</x-shop::layouts>
