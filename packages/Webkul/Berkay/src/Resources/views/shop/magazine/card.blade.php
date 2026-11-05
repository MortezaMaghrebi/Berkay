<article class="bk-story-card">
    <a class="bk-story-cover" href="{{ route('shop.berkay.magazine.show', $entry->slug) }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $entry->cover_url }}" alt="" width="1200" height="800" loading="lazy" decoding="async">
    </a>
    <div class="bk-story-copy">
        <p class="bk-story-category">{{ $entry->category }}</p>
        <h2><a href="{{ route('shop.berkay.magazine.show', $entry->slug) }}">{{ $entry->title }}</a></h2>
        <p>{{ $entry->excerpt }}</p>
        <a class="bk-story-read" href="{{ route('shop.berkay.magazine.show', $entry->slug) }}">{{ trans('berkay::app.read') }} <span aria-hidden="true">←</span></a>
    </div>
</article>
