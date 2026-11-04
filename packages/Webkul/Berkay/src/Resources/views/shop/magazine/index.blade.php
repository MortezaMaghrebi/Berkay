<x-shop::layouts>
    <x-slot:title>{{ trans('berkay::app.magazine') }}</x-slot>
    <section class="bk-section">
        <h1>{{ trans('berkay::app.magazine') }}</h1>
        <p class="bk-lead">{{ trans('berkay::app.magazine-description') }}</p>
        <div class="bk-editorial-grid">
            @forelse ($articles as $article)
                <article class="bk-editorial-card">
                    <p>{{ $article->category }}</p>
                    <h2><a href="{{ route('shop.berkay.magazine.show', $article->slug) }}">{{ $article->title }}</a></h2>
                    <p>{{ $article->excerpt }}</p>
                    <a href="{{ route('shop.berkay.magazine.show', $article->slug) }}">{{ trans('berkay::app.read') }} ←</a>
                </article>
            @empty
                <p>{{ trans('berkay::app.empty-articles') }}</p>
            @endforelse
        </div>
        {{ $articles->links() }}
    </section>
</x-shop::layouts>
