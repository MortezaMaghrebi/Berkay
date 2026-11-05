@inject('articles', \Webkul\Berkay\Repositories\ArticleRepository::class)
@php($guides = $articles->published())
@if ($guides->count())
    <section class="bk-home-guides">
        <h2>{{ trans('berkay::app.magazine') }}</h2>
        <div class="bk-story-grid">
            @foreach ($guides->take(3) as $guide)
                @include('berkay::shop.magazine.card', ['entry' => $guide])
            @endforeach
        </div>
    </section>
@endif
