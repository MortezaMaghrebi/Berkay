@php
    $headings = [];
    $body = preg_replace_callback('/<h2\b[^>]*>(.*?)<\/h2>/isu', function ($match) use (&$headings, $article) {
        $id = 'section-'.(count($headings) + 1);
        $headings[] = ['id' => $id, 'title' => strip_tags($match[1])];

        $illustration = count($headings) === 4 ? view('berkay::shop.magazine.inline-photo', ['article' => $article])->render() : '';

        return $illustration.'<h2 id="'.$id.'">'.$match[1].'</h2>';
    }, clean_content($article->body));
    $readingMinutes = max(1, (int) ceil(count(preg_split('/\s+/u', strip_tags($body), -1, PREG_SPLIT_NO_EMPTY)) / 180));
@endphp
@push('meta')
    <meta name="description" content="{{ $article->meta_description ?: $article->excerpt }}">
    <link rel="canonical" href="{{ route('shop.berkay.magazine.show', $article->slug) }}">
    <meta property="og:title" content="{{ $article->meta_title ?: $article->title }}">
    <meta property="og:image" content="{{ $article->cover_url }}">
    <meta property="og:image:alt" content="{{ $article->cover_alt ?: $article->title }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta property="og:type" content="article">
    <meta property="og:description" content="{{ $article->meta_description ?: $article->excerpt }}">
    <meta property="article:published_time" content="{{ $article->published_at->toAtomString() }}">
    <meta property="article:modified_time" content="{{ $article->updated_at->toAtomString() }}">
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article->title, 'image' => [$article->cover_url], 'description' => $article->excerpt, 'mainEntityOfPage' => route('shop.berkay.magazine.show', $article->slug), 'datePublished' => $article->published_at->toAtomString(), 'dateModified' => $article->updated_at->toAtomString(), 'inLanguage' => 'fa', 'author' => ['@type' => 'Organization', 'name' => core()->getConfigData('berkay.store.identity.name') ?: trans('berkay::app.brand'), 'url' => route('shop.home.index')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <meta property="og:url" content="{{ route('shop.berkay.magazine.show', $article->slug) }}">
@endpush
<x-shop::layouts>
    <x-slot:title>{{ $article->meta_title ?: $article->title }}</x-slot>
    <article class="bk-story">
        <header class="bk-story-header">
            <a href="{{ route('shop.berkay.magazine.index') }}">{{ trans('berkay::app.magazine') }} / {{ $article->category }}</a>
            <h1>{{ $article->title }}</h1>
            <p class="bk-lead">{{ $article->excerpt }}</p>
            <div class="bk-story-meta"><span>{{ $readingMinutes }} {{ trans('berkay::app.editorial.minutes') }}</span><time datetime="{{ $article->updated_at->toAtomString() }}">{{ trans('berkay::app.editorial.updated') }}: {{ $article->updated_at->format('Y/m/d') }}</time></div>
        </header>
        <figure class="bk-story-hero">
            <img src="{{ $article->cover_url }}" alt="{{ $article->cover_alt ?: $article->title }}" width="1200" height="800" fetchpriority="high">
            @unless ($article->cover_path)<figcaption>{{ trans('berkay::app.editorial.illustration') }}</figcaption>@endunless
        </figure>
        <div class="bk-story-layout">
            @if ($headings)
                <nav class="bk-story-toc" aria-label="{{ trans('berkay::app.editorial.contents') }}">
                    <h2>{{ trans('berkay::app.editorial.contents') }}</h2>
                    @foreach ($headings as $heading)
                        <a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>
                    @endforeach
                </nav>
            @endif
            <div class="bk-prose bk-story-body">{!! $body !!}</div>
        </div>
        @if ($article->slug === \Webkul\Berkay\Models\Article::LEATHER_GUIDE_SLUG)
            @include('berkay::shop.leather-guide')
        @endif

        @if ($related->count())
            <section class="bk-story-related"><h2>{{ trans('berkay::app.editorial.related') }}</h2>
                <div class="bk-story-grid">@foreach ($related as $entry) @include('berkay::shop.magazine.card', ['entry' => $entry]) @endforeach</div>
            </section>
        @endif
    </article>
</x-shop::layouts>
