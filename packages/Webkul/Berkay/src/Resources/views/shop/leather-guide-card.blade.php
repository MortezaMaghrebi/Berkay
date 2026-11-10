@inject('leatherArticles', \Webkul\Berkay\Repositories\ArticleRepository::class)

@php
    $guide = $leatherArticles->publishedBySlug(\Webkul\Berkay\Models\Article::LEATHER_GUIDE_SLUG, required: false);
@endphp

@if ($guide)
    <a class="bk-leather-card" href="{{ route('shop.berkay.magazine.show', $guide->slug) }}" aria-labelledby="bk-leather-card-title">
        <img src="{{ $guide->cover_url }}" width="360" height="240" alt="" loading="lazy">
        <div class="bk-leather-card-copy">
            <h2 id="bk-leather-card-title">{{ $guide->title }}</h2>
            <p>{{ $guide->excerpt }}</p>
            <span>{{ trans('berkay::leather.read-guide') }} <span aria-hidden="true">←</span></span>
        </div>
    </a>
@endif
