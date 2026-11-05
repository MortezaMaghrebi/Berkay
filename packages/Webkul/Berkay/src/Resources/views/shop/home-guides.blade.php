@inject('articles', \Webkul\Berkay\Repositories\ArticleRepository::class)
@php($guides = $articles->published())
@if ($guides->count())
    <section class="bk-home-guides">
        <h2>{{ trans('berkay::app.magazine') }}</h2>
        <div class="bk-editorial-grid">
            @foreach ($guides->take(3) as $guide)
                <article class="bk-editorial-card">
                    <h3><a href="{{ route('shop.berkay.magazine.show', $guide->slug) }}">{{ $guide->title }}</a></h3>
                    <p>{{ $guide->excerpt }}</p>
                    <a href="{{ route('shop.berkay.magazine.show', $guide->slug) }}">{{ trans('berkay::app.read') }} ←</a>
                </article>
            @endforeach
        </div>
    </section>
@endif
