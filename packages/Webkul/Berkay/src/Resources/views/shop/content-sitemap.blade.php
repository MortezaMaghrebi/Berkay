{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('shop.berkay.magazine.index') }}</loc></url>
    @foreach ($articles as $article)
        <url><loc>{{ route('shop.berkay.magazine.show', $article->slug) }}</loc><lastmod>{{ $article->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
</urlset>
