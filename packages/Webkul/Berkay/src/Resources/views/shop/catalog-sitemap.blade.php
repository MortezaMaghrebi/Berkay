{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('shop.home.index') }}</loc></url>
    @foreach ($categories as $category)
        <url><loc>{{ route('shop.product_or_category.index', $category->slug) }}</loc>@if ($category->updated_at)<lastmod>{{ \Illuminate\Support\Carbon::parse($category->updated_at)->toAtomString() }}</lastmod>@endif</url>
    @endforeach
    @foreach ($products as $product)
        <url><loc>{{ route('shop.product_or_category.index', $product->url_key) }}</loc>@if ($product->updated_at)<lastmod>{{ \Illuminate\Support\Carbon::parse($product->updated_at)->toAtomString() }}</lastmod>@endif</url>
    @endforeach
</urlset>
