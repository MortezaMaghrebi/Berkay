@push('meta')
    <meta name="description" content="{{ $article->meta_description ?: $article->excerpt }}">
    <link rel="canonical" href="{{ route('shop.berkay.magazine.show', $article->slug) }}">
    <meta property="og:title" content="{{ $article->meta_title ?: $article->title }}">
    <meta property="og:type" content="article">
    <meta property="og:description" content="{{ $article->meta_description ?: $article->excerpt }}">
    <meta property="article:published_time" content="{{ $article->published_at->toAtomString() }}">
    <meta property="article:modified_time" content="{{ $article->updated_at->toAtomString() }}">
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article->title, 'description' => $article->excerpt, 'mainEntityOfPage' => route('shop.berkay.magazine.show', $article->slug), 'datePublished' => $article->published_at->toAtomString(), 'dateModified' => $article->updated_at->toAtomString(), 'inLanguage' => 'fa', 'author' => ['@type' => 'Organization', 'name' => core()->getConfigData('berkay.store.identity.name') ?: trans('berkay::app.brand'), 'url' => route('shop.home.index')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
