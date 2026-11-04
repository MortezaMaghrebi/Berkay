@push('meta')
    <meta name="description" content="{{ $article->meta_description ?: $article->excerpt }}">
    <link rel="canonical" href="{{ route('shop.berkay.magazine.show', $article->slug) }}">
    <meta property="og:title" content="{{ $article->meta_title ?: $article->title }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('shop.berkay.magazine.show', $article->slug) }}">
@endpush
<x-shop::layouts>
    <x-slot:title>{{ $article->meta_title ?: $article->title }}</x-slot>
    <article class="bk-section bk-reading">
        <a href="{{ route('shop.berkay.magazine.index') }}">{{ trans('berkay::app.magazine') }}</a>
        <h1>{{ $article->title }}</h1>
        <p class="bk-lead">{{ $article->excerpt }}</p>
        <div class="bk-prose">{!! clean_content($article->body) !!}</div>
    </article>
</x-shop::layouts>
