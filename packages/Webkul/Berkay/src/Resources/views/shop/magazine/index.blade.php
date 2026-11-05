@push('meta')
    <meta name="description" content="{{ trans('berkay::app.magazine-description') }}">
    <link rel="canonical" href="{{ route('shop.berkay.magazine.index', request()->integer('page', 1) > 1 ? ['page' => request()->integer('page')] : []) }}">
@endpush
<x-shop::layouts>
    <x-slot:title>{{ trans('berkay::app.magazine') }}</x-slot>
    <section class="bk-section">
        <h1>{{ trans('berkay::app.magazine') }}</h1>
        <p class="bk-lead">{{ trans('berkay::app.magazine-description') }}</p>
        <div class="bk-story-grid">
            @forelse ($articles as $article)
                @include('berkay::shop.magazine.card', ['entry' => $article])
            @empty
                <p>{{ trans('berkay::app.empty-articles') }}</p>
            @endforelse
        </div>
        {{ $articles->links() }}
    </section>
</x-shop::layouts>
